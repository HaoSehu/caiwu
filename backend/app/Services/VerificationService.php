<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\VerificationHistory;
use App\Support\SensitiveDataSanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class VerificationService
{
    private const API_STATUS_SUCCESS = 200;
    private const API_STATUS_FAILED = 400;
    private const API_STATUS_NETWORK_ERROR = 500;

    private const RESULT_STATUS_SUCCESS = 1;
    private const RESULT_STATUS_FAILED = 2;
    private const RESULT_STATUS_NETWORK_ERROR = 3;
    private const RESULT_STATUS_PENDING = 4;
    private const RESULT_STATUS_UNBOUND = 5;

    private const VERIFICATION_TYPE_PERSONAL = 'personal';
    private const DEFAULT_API_ENDPOINT = 'https://idc.stay33.cn/realname/certapi.php';
    private const QR_CODE_URL_CACHE_PREFIX = 'verification:qrcode_url:';
    private const QR_CODE_URL_CACHE_TTL_SECONDS = 7200;

    private array $config;
    private ?bool $verificationHistoryTableAvailable = null;
    private ?array $lastRequestFailure = null;

    public function __construct()
    {
        $defaultConfig = (array) config('idc.verification', []);

        $this->config = [
            'api' => (string) Setting::getValue('verification', 'verification_api', $defaultConfig['api'] ?? ''),
            'key' => (string) Setting::getValue('verification', 'verification_key', $defaultConfig['key'] ?? ''),
            'biz_code' => (string) Setting::getValue('verification', 'verification_biz_code', $defaultConfig['biz_code'] ?? 'FACE'),
            'api_endpoint' => (string) ($defaultConfig['api_endpoint'] ?? self::DEFAULT_API_ENDPOINT),
            'ssl_verify' => $this->normalizeBoolean($defaultConfig['ssl_verify'] ?? true),
            'ca_bundle' => (string) ($defaultConfig['ca_bundle'] ?? ''),
            'return_url' => $this->resolveCallbackUrl(),
        ];
    }

    public function initVerification(User $user, string $realname, string $idcard, string $certType = 'IDENTITY_CARD'): array
    {
        $verification = $this->ensureVerificationProfile($user);
        $previousCertifyId = trim((string) $verification->certify_id);

        $response = $this->getCertifyId($realname, $idcard, $certType);
        $this->assertSourceResponseSuccess($response, '认证初始化失败');

        $certifyId = (string) ($response['certify_id'] ?? '');

        DB::transaction(function () use ($user, $realname, $idcard, $certifyId): void {
            $verification = $this->lockVerificationProfile($user->id);
            $verification->forceFill([
                'verification_type' => self::VERIFICATION_TYPE_PERSONAL,
                'verification_status' => self::RESULT_STATUS_PENDING,
                'real_name' => $realname,
                'id_card_encrypted' => $idcard,
                'certify_id' => $certifyId,
                'verification_message' => '等待认证',
                'last_submitted_at' => now(),
                'verified_at' => null,
            ])->save();

            $user->unsetRelation('verificationProfile');
            $this->createHistoryEntry($user->fresh() ?? $user, $certifyId);
        });

        if ($previousCertifyId !== '' && $previousCertifyId !== $certifyId) {
            $this->forgetQrCodeUrlCache($previousCertifyId);
        }

        return [
            'certify_id' => $certifyId,
            'status' => self::RESULT_STATUS_SUCCESS,
            'message' => '实名认证初始化成功',
        ];
    }

    public function startVerificationSession(User $user, string $realname, string $idcard, string $certType = 'IDENTITY_CARD'): array
    {
        $result = $this->initVerification($user, $realname, $idcard, $certType);
        $qrcode = $this->generateQrCode($result['certify_id']);

        return array_merge($result, $qrcode);
    }

    public function generateQrCode(string $certifyId): array
    {
        $response = $this->generateScanForm($certifyId);
        $this->assertSourceResponseSuccess($response, '获取认证链接失败');

        $remoteUrl = trim((string) ($response['url'] ?? ''));
        if (! $this->isValidRemoteUrl($remoteUrl)) {
            throw new BusinessException('生成认证链接失败', 42200);
        }

        $this->cacheQrCodeUrl($certifyId, $remoteUrl);

        return [
            'url' => $remoteUrl,
            'proxy_url' => $this->buildQrCodeProxyUrl($certifyId),
        ];
    }

    public function resolveQrCodeRedirectUrl(string $certifyId): string
    {
        $certifyId = trim($certifyId);
        if ($certifyId === '') {
            throw new BusinessException('认证会话不存在或已失效', 42200);
        }

        $cachedUrl = Cache::get($this->buildQrCodeUrlCacheKey($certifyId));
        if (is_string($cachedUrl) && $this->isValidRemoteUrl($cachedUrl)) {
            return $cachedUrl;
        }

        $response = $this->generateScanForm($certifyId);
        $this->assertSourceResponseSuccess($response, '获取认证链接失败');

        $remoteUrl = trim((string) ($response['url'] ?? ''));
        if (! $this->isValidRemoteUrl($remoteUrl)) {
            throw new BusinessException('生成认证链接失败', 42200);
        }

        $this->cacheQrCodeUrl($certifyId, $remoteUrl);

        return $remoteUrl;
    }

    public function queryStatus(string $certifyId): array
    {
        $maxRetries = 3;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $result = $this->getAliyunAuthStatus($certifyId);

            if ($result['status'] !== self::RESULT_STATUS_NETWORK_ERROR) {
                return $result;
            }

            if ($attempt < $maxRetries - 1) {
                sleep(2);
            }
        }

        return [
            'status' => self::RESULT_STATUS_FAILED,
            'msg' => '网络请求失败，请刷新页面重试',
        ];
    }

    public function syncUserStatus(User $user, array $result, ?string $certifyId = null): User
    {
        DB::transaction(function () use ($user, $result, $certifyId): void {
            $verification = $this->lockVerificationProfile($user->id);
            $payload = [
                'verification_message' => (string) ($result['msg'] ?? ''),
            ];

            if ($certifyId) {
                $payload['certify_id'] = $certifyId;
            }

            if (($result['status'] ?? null) === self::RESULT_STATUS_SUCCESS) {
                $payload['verification_status'] = 2;
                $payload['verification_message'] = (string) ($result['msg'] ?? '审核通过');
                $payload['verified_at'] = now();
            } elseif (($result['status'] ?? null) === self::RESULT_STATUS_NETWORK_ERROR) {
                $payload['verification_status'] = (int) $verification->verification_status > 0
                    ? (int) $verification->verification_status
                    : self::RESULT_STATUS_PENDING;
            } elseif (($result['status'] ?? null) === self::RESULT_STATUS_PENDING) {
                $payload['verification_status'] = self::RESULT_STATUS_PENDING;
            } else {
                $payload['verification_status'] = 3;
                $payload['verified_at'] = null;
            }

            $verification->forceFill($payload)->save();
            $user->unsetRelation('verificationProfile');
            $this->syncHistoryEntry($user->fresh() ?? $user, $certifyId);
        });

        return $user->fresh() ?? $user;
    }

    public function restartVerificationSession(User $user): array
    {
        $verification = $this->ensureVerificationProfile($user);
        $realName = trim((string) $verification->real_name);
        $idCard = trim((string) $verification->id_card_encrypted);

        if ($realName === '' || $idCard === '') {
            throw new BusinessException('缺少实名认证信息，无法重新生成会话，请重新提交认证资料', 42200);
        }

        $result = $this->initVerification($user, $realName, $idCard);
        $qrcode = $this->generateQrCode($result['certify_id']);

        return array_merge($result, $qrcode);
    }

    public function getConfigSummary(): array
    {
        return [
            'verification_api_masked' => $this->maskConfigValue((string) $this->config['api']),
            'verification_biz_code' => (string) $this->config['biz_code'],
            'configured' => trim((string) $this->config['api']) !== '' && trim((string) $this->config['key']) !== '',
        ];
    }

    public function unbind(User $user, ?int $adminUserId = null, ?string $adminName = null, ?string $rejectReason = null): array
    {
        $verification = $this->ensureVerificationProfile($user);
        $realName = (string) $verification->real_name;
        $idCard = (string) $verification->id_card_encrypted;
        $certifyId = (string) $verification->certify_id;
        $resolvedRejectReason = trim((string) $rejectReason);
        $rejectMessage = $resolvedRejectReason !== '' ? $resolvedRejectReason : '管理员驳回';

        if ((int) $verification->verification_status !== 2) {
            throw new BusinessException('该用户未通过实名认证，无法解绑', 42200);
        }

        DB::transaction(function () use ($user, $realName, $idCard, $certifyId, $rejectMessage): void {
            $verification = $this->lockVerificationProfile($user->id);
            $verification->forceFill([
                'verification_status' => self::RESULT_STATUS_UNBOUND,
                'verification_message' => $rejectMessage,
                'certify_id' => null,
                'verified_at' => null,
            ])->save();

            $user->unsetRelation('verificationProfile');

            if (! $this->canPersistVerificationHistory()) {
                return;
            }

            try {
                VerificationHistory::create([
                    'user_id' => $user->id,
                    'real_name' => $realName,
                    'id_card' => $idCard,
                    'verification_status' => self::RESULT_STATUS_UNBOUND,
                    'verification_message' => $rejectMessage,
                    'verification_certify_id' => $certifyId,
                    'verification_biz_code' => (string) $this->config['biz_code'],
                    'verification_type' => self::VERIFICATION_TYPE_PERSONAL,
                    'submitted_at' => now(),
                    'completed_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                $this->handleHistoryPersistenceFailure('unbind', $exception, [
                    'user_id' => $user->id,
                    'verification_certify_id' => $certifyId,
                ]);
            }
        });

        return [
            'user_id' => $user->id,
            'real_name' => $this->maskName($realName),
            'unbound_at' => now()->format('Y-m-d H:i:s'),
            'operator' => $adminName ?? '系统',
            'reject_reason' => $rejectMessage,
        ];
    }

    private function getCertifyId(string $realname, string $idcard, string $certType): array
    {
        $this->assertConfigReady();

        $params = [
            'outer_order_no' => $this->buildOuterOrderNo(),
            'biz_code' => (string) $this->config['biz_code'],
            'cert_type' => $certType,
            'cert_name' => $realname,
            'cert_no' => $idcard,
            'return_url' => (string) $this->config['return_url'],
        ];

        $this->writeLog('getCertifyId-请求参数', [
            'realname' => $this->maskName($realname),
            'idcard' => $this->maskIdCard($idcard),
            'cert_type' => $certType,
        ]);

        $result = $this->apiHttpRequestCurl('initialize', $params);

        if ($result === null) {
            return [
                'status' => self::API_STATUS_NETWORK_ERROR,
                'msg' => $this->getLastRequestFailureMessage(),
            ];
        }

        if ((int) ($result['status'] ?? 0) === self::API_STATUS_SUCCESS && trim((string) ($result['certify_id'] ?? '')) !== '') {
            return [
                'status' => self::API_STATUS_SUCCESS,
                'msg' => (string) ($result['msg'] ?? '请求成功'),
                'certify_id' => (string) $result['certify_id'],
            ];
        }

        return [
            'status' => self::API_STATUS_FAILED,
            'msg' => (string) ($result['msg'] ?? '实名认证接口配置错误,请联系管理员'),
        ];
    }

    private function generateScanForm(string $certifyId): array
    {
        $this->assertConfigReady();
        $this->writeLog('generateScanForm-请求参数', ['certify_id' => $certifyId]);

        $result = $this->apiHttpRequestCurl('certify', ['certify_id' => $certifyId]);

        if ($result === null) {
            return [
                'status' => self::API_STATUS_NETWORK_ERROR,
                'msg' => $this->getLastRequestFailureMessage(),
            ];
        }

        $url = trim((string) ($result['url'] ?? ''));
        if ($url === '') {
            return [
                'status' => self::API_STATUS_FAILED,
                'msg' => (string) ($result['msg'] ?? '获取认证链接失败,请联系管理员'),
            ];
        }

        return [
            'status' => self::API_STATUS_SUCCESS,
            'msg' => (string) ($result['msg'] ?? '请打开实名认证链接继续认证'),
            'url' => $url,
        ];
    }

    private function getAliyunAuthStatus(string $certifyId): array
    {
        $this->assertConfigReady();
        $this->writeLog('getAliyunAuthStatus-请求参数', ['certify_id' => $certifyId]);

        $result = $this->apiHttpRequestCurl('query', ['certify_id' => $certifyId]);

        if ($result === null) {
            return [
                'status' => self::RESULT_STATUS_NETWORK_ERROR,
                'msg' => $this->getLastRequestFailureMessage(),
            ];
        }

        if ((int) ($result['status'] ?? 0) === self::API_STATUS_SUCCESS) {
            return [
                'status' => self::RESULT_STATUS_SUCCESS,
                'msg' => '审核通过',
            ];
        }

        $msg = (string) ($result['msg'] ?? '未知错误');
        $waitingKeywords = ['等待', '认证中', '待认证', '处理中', '审核中'];

        foreach ($waitingKeywords as $keyword) {
            if (str_contains($msg, $keyword)) {
                return [
                    'status' => self::RESULT_STATUS_PENDING,
                    'msg' => $msg,
                ];
            }
        }

        return [
            'status' => self::RESULT_STATUS_FAILED,
            'msg' => $msg,
        ];
    }

    private function apiHttpRequestCurl(string $action, array $params, string $method = 'POST'): ?array
    {
        $this->lastRequestFailure = null;
        $responseHeaders = [];

        $api = rtrim((string) $this->config['api_endpoint'], '?') . '?action=' . $action;
        $headers = [
            'api:' . (string) $this->config['api'],
            'key:' . (string) $this->config['key'],
            'Content-Type: application/x-www-form-urlencoded',
        ];
        $postfields = http_build_query($params);

        $this->writeLog('APIHttpRequestCURL-发送请求', [
            'action' => $action,
            'api_url' => $api,
            'params' => $params,
        ]);

        $ch = curl_init();

        if (strtoupper($method) !== 'GET') {
            curl_setopt_array($ch, [
                CURLOPT_URL => $api,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => $postfields,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headers,
            ]);
        } else {
            curl_setopt_array($ch, [
                CURLOPT_URL => $api . '&' . $postfields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_BINARYTRANSFER => true,
            ]);
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, string $headerLine) use (&$responseHeaders): int {
            $trimmedLine = trim($headerLine);
            if ($trimmedLine === '' || ! str_contains($trimmedLine, ':')) {
                return strlen($headerLine);
            }

            [$name, $value] = explode(':', $trimmedLine, 2);
            $responseHeaders[strtolower(trim($name))] = trim($value);

            return strlen($headerLine);
        });

        if (str_starts_with($api, 'https://')) {
            $sslVerify = (bool) $this->config['ssl_verify'];
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

            $caBundle = (string) $this->config['ca_bundle'];
            if ($caBundle !== '' && is_file($caBundle)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
            }
        }

        $output = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->writeLog('APIHttpRequestCURL-执行状态', [
            'http_code' => $httpCode,
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
            'server' => $responseHeaders['server'] ?? '',
            'eo_log_uuid' => $responseHeaders['eo-log-uuid'] ?? '',
        ]);

        curl_close($ch);

        if ($output === false) {
            $this->lastRequestFailure = [
                'type' => 'curl',
                'action' => $action,
                'http_code' => $httpCode,
                'curl_errno' => $curlErrno,
                'curl_error' => $curlError,
                'server' => $responseHeaders['server'] ?? '',
                'eo_log_uuid' => $responseHeaders['eo-log-uuid'] ?? '',
            ];

            $this->writeLog('APIHttpRequestCURL-请求失败', $this->lastRequestFailure);

            if ($this->isSslCertificateError($curlError)) {
                $message = (bool) $this->config['ssl_verify']
                    ? '实名认证接口 SSL 证书校验失败，请配置 CA 证书，或将 VERIFICATION_SSL_VERIFY=false'
                    : '实名认证接口连接失败，请检查服务器网络或上游证书链配置';

                throw new BusinessException($message, 42200);
            }

            return null;
        }

        $output = trim($output, "\xEF\xBB\xBF");
        $decoded = json_decode($output, true);

        $this->writeLog('APIHttpRequestCURL-响应内容', [
            'raw' => $output,
            'decoded' => $decoded,
            'server' => $responseHeaders['server'] ?? '',
            'eo_log_uuid' => $responseHeaders['eo-log-uuid'] ?? '',
        ]);

        if (! is_array($decoded)) {
            $this->lastRequestFailure = $this->buildInvalidResponseFailure($action, $httpCode, $output, $responseHeaders);
            $this->writeLog('APIHttpRequestCURL-响应异常', [
                'raw' => $output,
                'json_error' => json_last_error_msg(),
                'server' => $responseHeaders['server'] ?? '',
                'eo_log_uuid' => $responseHeaders['eo-log-uuid'] ?? '',
            ]);

            return null;
        }

        return $decoded;
    }

    private function assertSourceResponseSuccess(array $response, string $fallbackMessage): void
    {
        $status = (int) ($response['status'] ?? 0);
        if ($status === self::API_STATUS_SUCCESS) {
            return;
        }

        $message = trim((string) ($response['msg'] ?? '')) ?: $fallbackMessage;
        $errorCode = $status === self::API_STATUS_NETWORK_ERROR ? 50000 : 42200;

        throw new BusinessException($message, $errorCode);
    }

    private function createHistoryEntry(User $user, string $certifyId): void
    {
        if (! $this->canPersistVerificationHistory()) {
            return;
        }

        try {
            VerificationHistory::create([
                'user_id' => $user->id,
                'real_name' => (string) $user->real_name,
                'id_card' => (string) $user->id_card,
                'verification_status' => self::RESULT_STATUS_PENDING,
                'verification_message' => (string) $user->verification_message,
                'verification_certify_id' => $certifyId,
                'verification_biz_code' => (string) $this->config['biz_code'],
                'verification_type' => self::VERIFICATION_TYPE_PERSONAL,
                'submitted_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->handleHistoryPersistenceFailure('create', $exception, [
                'user_id' => $user->id,
                'verification_certify_id' => $certifyId,
            ]);
        }
    }

    private function syncHistoryEntry(User $user, ?string $certifyId = null): void
    {
        if (! $this->canPersistVerificationHistory()) {
            return;
        }

        try {
            $query = VerificationHistory::query()
                ->where('user_id', $user->id);

            if ($certifyId) {
                $query->where('verification_certify_id', $certifyId);
            }

            $history = $query->latest('id')->first();

            if (! $history) {
                $history = VerificationHistory::create([
                    'user_id' => $user->id,
                    'real_name' => (string) $user->real_name,
                    'id_card' => (string) $user->id_card,
                    'verification_status' => (int) $user->verification_status,
                    'verification_message' => (string) $user->verification_message,
                    'verification_certify_id' => $certifyId ?: $user->verification_certify_id,
                    'verification_biz_code' => (string) $this->config['biz_code'],
                    'verification_type' => self::VERIFICATION_TYPE_PERSONAL,
                    'submitted_at' => now(),
                ]);
            }

            $status = (int) $user->verification_status;

            $history->forceFill([
                'real_name' => (string) $user->real_name,
                'id_card' => (string) $user->id_card,
                'verification_status' => $status,
                'verification_message' => (string) $user->verification_message,
                'verification_certify_id' => $certifyId ?: $history->verification_certify_id,
                'verification_biz_code' => (string) $this->config['biz_code'],
                'verification_type' => self::VERIFICATION_TYPE_PERSONAL,
                'completed_at' => in_array($status, [2, 3, self::RESULT_STATUS_UNBOUND], true) ? ($user->verified_at ?? now()) : null,
            ])->save();
        } catch (\Throwable $exception) {
            $this->handleHistoryPersistenceFailure('sync', $exception, [
                'user_id' => $user->id,
                'verification_certify_id' => $certifyId ?: (string) $user->verification_certify_id,
            ]);
        }
    }

    private function canPersistVerificationHistory(): bool
    {
        if ($this->verificationHistoryTableAvailable !== null) {
            return $this->verificationHistoryTableAvailable;
        }

        try {
            return $this->verificationHistoryTableAvailable = Schema::hasTable('verification_histories');
        } catch (\Throwable $exception) {
            $this->verificationHistoryTableAvailable = false;

            Log::warning('[实名认证] verificationHistory-表检查失败', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function handleHistoryPersistenceFailure(string $action, \Throwable $exception, array $context = []): void
    {
        if (str_contains(strtolower($exception->getMessage()), 'verification_histories')) {
            $this->verificationHistoryTableAvailable = false;
        }

        Log::warning('[实名认证] verificationHistory-' . $action . '-失败', SensitiveDataSanitizer::sanitize(array_merge($context, [
            'error' => $exception->getMessage(),
        ])));
    }

    private function assertConfigReady(): void
    {
        if (trim((string) $this->config['api']) === '' || trim((string) $this->config['key']) === '') {
            throw new BusinessException('实名认证接口未配置，请先在管理端填写 API 信息', 42200);
        }
    }

    private function resolveCallbackUrl(): string
    {
        $frontendUrl = trim((string) config('app.frontend_url', ''));
        if ($frontendUrl !== '') {
            return rtrim($frontendUrl, '/') . '/api/client/verification/callback';
        }

        return rtrim((string) config('app.url', ''), '/') . '/api/client/verification/callback';
    }

    private function buildOuterOrderNo(): string
    {
        return 'ZGYD' . now()->format('YmdHis') . random_int(1000, 9999);
    }

    private function cacheQrCodeUrl(string $certifyId, string $remoteUrl): void
    {
        Cache::put(
            $this->buildQrCodeUrlCacheKey($certifyId),
            $remoteUrl,
            now()->addSeconds(self::QR_CODE_URL_CACHE_TTL_SECONDS)
        );
    }

    private function forgetQrCodeUrlCache(string $certifyId): void
    {
        Cache::forget($this->buildQrCodeUrlCacheKey($certifyId));
    }

    private function buildQrCodeProxyUrl(string $certifyId): string
    {
        $frontendUrl = trim((string) config('app.frontend_url', ''));
        if ($frontendUrl !== '') {
            return rtrim($frontendUrl, '/') . '/api/client/verification/scan?certify_id=' . rawurlencode($certifyId);
        }

        return rtrim((string) config('app.url', ''), '/') . '/api/client/verification/scan?certify_id=' . rawurlencode($certifyId);
    }

    private function buildQrCodeUrlCacheKey(string $certifyId): string
    {
        return self::QR_CODE_URL_CACHE_PREFIX . md5($certifyId);
    }

    private function isValidRemoteUrl(string $url): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    private function buildInvalidResponseFailure(string $action, int $httpCode, string $output, array $responseHeaders = []): array
    {
        $failure = [
            'type' => 'invalid_json',
            'action' => $action,
            'http_code' => $httpCode,
            'json_error' => json_last_error_msg(),
            'raw' => mb_substr($output, 0, 500),
            'server' => (string) ($responseHeaders['server'] ?? ''),
            'eo_log_uuid' => (string) ($responseHeaders['eo-log-uuid'] ?? ''),
        ];

        if ($httpCode === 520 && trim($output) === '') {
            $failure['type'] = 'empty_http_response';
        }

        if ($this->isEdgeOneBlockResponse($output)) {
            $failure['type'] = 'edgeone_block';
            $failure['status_code'] = $this->extractEdgeOneStatusCode($output);
            $failure['request_id'] = $this->extractEdgeOneRequestId($output);
        }

        return $failure;
    }

    private function getLastRequestFailureMessage(): string
    {
        if (! is_array($this->lastRequestFailure)) {
            return '网络请求失败，请稍后重试';
        }

        if (($this->lastRequestFailure['type'] ?? '') === 'curl') {
            $curlError = trim((string) ($this->lastRequestFailure['curl_error'] ?? ''));
            $curlErrno = (int) ($this->lastRequestFailure['curl_errno'] ?? 0);

            if ($curlError !== '') {
                return "实名认证接口请求失败(cURL {$curlErrno})：{$curlError}";
            }

            return '实名认证接口请求失败，请检查服务器网络连通性';
        }

        if (($this->lastRequestFailure['type'] ?? '') === 'edgeone_block') {
            $requestId = trim((string) ($this->lastRequestFailure['request_id'] ?? ''));
            $statusCode = trim((string) ($this->lastRequestFailure['status_code'] ?? '567'));

            return $requestId !== ''
                ? "实名认证上游接口被安全策略拦截(状态码 {$statusCode}，Request ID: {$requestId})，请联系上游在 EdgeOne 放行当前服务器 IP"
                : "实名认证上游接口被安全策略拦截(状态码 {$statusCode})，请联系上游在 EdgeOne 放行当前服务器 IP";
        }

        if (($this->lastRequestFailure['type'] ?? '') === 'invalid_json') {
            return '实名认证接口返回异常，未解析到有效 JSON';
        }

        if (($this->lastRequestFailure['type'] ?? '') === 'empty_http_response') {
            $httpCode = (int) ($this->lastRequestFailure['http_code'] ?? 0);
            $server = trim((string) ($this->lastRequestFailure['server'] ?? ''));
            $eoLogUuid = trim((string) ($this->lastRequestFailure['eo_log_uuid'] ?? ''));

            if ($server !== '' || $eoLogUuid !== '') {
                return $eoLogUuid !== ''
                    ? "实名认证上游接口返回 HTTP {$httpCode} 空响应(server: {$server}, eo-log-uuid: {$eoLogUuid})，请联系上游排查网关或 EdgeOne 规则"
                    : "实名认证上游接口返回 HTTP {$httpCode} 空响应(server: {$server})，请联系上游排查网关或安全策略";
            }

            return "实名认证上游接口返回 HTTP {$httpCode} 空响应，请联系上游排查接口网关";
        }

        return '网络请求失败，请稍后重试';
    }

    private function isEdgeOneBlockResponse(string $output): bool
    {
        return str_contains($output, 'EdgeOne')
            && str_contains($output, '请求已被站点的安全策略拦截');
    }

    private function extractEdgeOneRequestId(string $output): string
    {
        if (preg_match('/id=requestId>(\d+)</', $output, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    private function extractEdgeOneStatusCode(string $output): string
    {
        if (preg_match('/id=statusCode>(\d+)</', $output, $matches) === 1) {
            return $matches[1];
        }

        return '567';
    }

    private function writeLog(string $method, mixed $data): void
    {
        $payload = is_array($data) ? $data : ['message' => $data];

        Log::channel('stack')->info('[实名认证] ' . $method, SensitiveDataSanitizer::sanitize($payload));
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return (bool) $value;
    }

    private function isSslCertificateError(string $curlError): bool
    {
        $message = strtolower($curlError);

        return str_contains($message, 'ssl certificate problem')
            || str_contains($message, 'certificate verify failed')
            || str_contains($message, 'self-signed certificate');
    }

    private function maskConfigValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $length = mb_strlen($value);
        if ($length <= 8) {
            return '已配置';
        }

        return mb_substr($value, 0, 4) . '******' . mb_substr($value, -4);
    }

    private function maskName(string $name): string
    {
        $len = mb_strlen($name);
        if ($len <= 1) {
            return '*';
        }

        if ($len === 2) {
            return mb_substr($name, 0, 1) . '*';
        }

        return mb_substr($name, 0, 1) . str_repeat('*', $len - 2) . mb_substr($name, -1);
    }

    private function maskIdCard(string $idcard): string
    {
        $len = strlen($idcard);
        if ($len < 8) {
            return str_repeat('*', $len);
        }

        return substr($idcard, 0, 4) . str_repeat('*', $len - 8) . substr($idcard, -4);
    }

    private function ensureVerificationProfile(User $user): UserVerification
    {
        $profile = UserVerification::query()->find($user->id);

        return $profile ?? UserVerification::query()->create([
            'user_id' => $user->id,
            'verification_type' => self::VERIFICATION_TYPE_PERSONAL,
            'verification_status' => 0,
            'real_name' => null,
            'id_card_encrypted' => null,
            'certify_id' => null,
            'verification_message' => null,
            'last_submitted_at' => null,
            'verified_at' => null,
        ]);
    }

    private function lockVerificationProfile(int $userId): UserVerification
    {
        $profile = UserVerification::query()->lockForUpdate()->find($userId);
        if ($profile) {
            return $profile;
        }

        $this->ensureVerificationProfile(User::query()->findOrFail($userId));

        return UserVerification::query()->lockForUpdate()->findOrFail($userId);
    }
}
