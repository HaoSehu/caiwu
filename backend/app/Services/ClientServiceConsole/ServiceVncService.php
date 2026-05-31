<?php

declare(strict_types=1);

namespace App\Services\ClientServiceConsole;

use App\Exceptions\BusinessException;
use App\Models\Service;
use App\Models\User;
use App\Services\System\OperationLogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * VNC 子服务
 * 负责：getVncUrlForUser、resolveVncToken、resolvePublicVncTokenPayload
 *       及所有 VNC URL 解析辅助方法
 */
class ServiceVncService
{
    private const VNC_TOKEN_TTL_SECONDS = 600;

    public function __construct(
        private readonly OperationLogService $operationLogService,
        private readonly ServiceDetailService $detailService,
        private readonly ServiceTransformService $transformService,
    ) {}

    public function getVncUrlForUser(User $user, int $serviceId, array $context = []): array
    {
        $service = $this->detailService->findUserService($user, $serviceId, [
            'product:id,product_type,product_group_id,supplier_id,provision_module,config_options,purchase_requires',
            'product.categoryMapping:id,parent_group_id,product_type,name,slogan,slug',
            'product.categoryMapping.parent:id,parent_group_id,product_type,name,slogan,slug',
            'product.supplier',
            'order:id,order_no,status,paid_at,created_at',
        ]);
        throw_if(! $this->transformService->canExecuteConsoleActions($service), new BusinessException('当前实例状态不支持该操作', 42200));

        try {
            [$runtime, $supplier, $hostId, $jwt] = $this->detailService->resolveUpstreamContext($service);
        } catch (BusinessException $exception) {
            throw $this->normalizeVncBusinessException($exception);
        }

        $response = $runtime->post(
            $supplier,
            '/provision/default',
            ['func' => 'vnc', 'id' => $hostId],
            $jwt,
            ['content-type: application/x-www-form-urlencoded']
        );
        $this->assertVncSuccess($response);

        $payload = $this->detailService->extractPayload($response);
        $upstreamVncUrl = trim((string) ($payload['url'] ?? $payload['vnc'] ?? $payload['link'] ?? ''));

        if ($upstreamVncUrl === '') {
            throw new BusinessException('上游未返回VNC链接', 50000);
        }

        $novncBaseUrl = $this->resolveNoVncBaseUrl($context);

        $message = trim((string) ($response['msg'] ?? '')) ?: '获取VNC链接成功';
        $vncUrl = $upstreamVncUrl;

        if ($novncBaseUrl !== '') {
            $vncParams = $this->extractVncParams($upstreamVncUrl);

            $this->safeLog('info', '[VNC] 解析结果', [
                'service_id' => $serviceId,
                'has_host' => isset($vncParams['host']) && $vncParams['host'] !== '',
                'has_port' => isset($vncParams['port']) && $vncParams['port'] > 0,
                'has_path' => isset($vncParams['path']) && $vncParams['path'] !== '',
                'encrypt' => $vncParams['encrypt'] ?? '(空)',
                'has_password' => isset($vncParams['password']) && $vncParams['password'] !== '',
            ]);

            if (! empty($vncParams)) {
                $vncParams = $this->withCachedVncCredentials($service, $vncParams);

                $token = bin2hex(random_bytes(24));
                Cache::put('vnc_token:'.$token, array_merge($vncParams, [
                    'service_id' => $serviceId,
                    'allowed_origin' => $this->resolveAllowedVncOrigin($context),
                    'single_use' => ($context['actor_type'] ?? 'client') !== 'admin',
                ]), now()->addSeconds(self::VNC_TOKEN_TTL_SECONDS));

                $viewerUrl = $this->resolveNoVncViewerUrl($novncBaseUrl);
                $queryParams = [
                    'token' => $token,
                    'service_id' => $serviceId,
                    'relay_path' => $this->resolveVncRelayPath(),
                ];

                // 当 noVNC 被托管在与业务 API 不同的源（跨域 iframe 场景），
                // vnc.html 需要通过 api_base 显式指向业务 API，否则相对路径
                // 会打到 noVNC 宿主并触发 "sessionStorage cross-origin" 类错误。
                $apiBase = $this->resolveVncApiBase($novncBaseUrl, $context);
                if ($apiBase !== '') {
                    $queryParams['api_base'] = $apiBase;
                }

                $vncUrl = $viewerUrl.(str_contains($viewerUrl, '?') ? '&' : '?').http_build_query($queryParams);
            } else {
                $this->safeLog('warning', '[VNC] noVNC地址已配置但上游链接解析失败，已回退使用上游链接', [
                    'service_id' => $serviceId,
                    'novnc_base_url' => $novncBaseUrl,
                ]);
            }
        }

        $this->operationLogService->writeServiceConsoleLog($service, 'service.console.vnc.get', [
            'category' => 'vnc',
            'summary' => '获取VNC链接',
            'host_id' => $hostId,
            'message' => $message,
        ], $context);

        return [
            'message' => $message,
            'url' => $vncUrl,
            'detail' => $this->transformService->transformDetail($service),
        ];
    }

    public function previewVncToken(string $token): array
    {
        return $this->loadVncTokenParams($token, false);
    }

    public function resolveVncToken(string $token): array
    {
        return $this->loadVncTokenParams($token, true);
    }

    private function assertVncSuccess(array $response): void
    {
        try {
            $this->detailService->assertSuccess($response, '获取VNC链接');
        } catch (BusinessException $exception) {
            throw $this->normalizeVncBusinessException($exception);
        }
    }

    private function normalizeVncBusinessException(BusinessException $exception): BusinessException
    {
        $message = trim($exception->getMessage());
        if (stripos($message, 'account does not exist') !== false) {
            return new BusinessException('上游账号不存在，暂时无法打开 VNC 控制台，请联系管理员同步或修复上游实例账号。', 42200);
        }

        return $exception;
    }

    public function resolvePublicVncTokenPayload(string $token): array
    {
        $params = $this->loadVncTokenParams($token, false);

        return [
            'token' => $token,
            'service_id' => (int) ($params['service_id'] ?? 0),
            'relay_path' => $this->resolveVncRelayPath(),
            'username' => (string) ($params['username'] ?? ''),
            'target' => (string) ($params['target'] ?? ''),
            'password' => (string) ($params['password'] ?? ''),
        ];
    }

    private function withCachedVncCredentials(Service $service, array $vncParams): array
    {
        $provisionData = (array) ($service->provision_data ?? []);
        $cachedConnection = $this->transformService->readCachedConnection($provisionData);

        if (trim((string) ($vncParams['username'] ?? '')) === '') {
            $username = trim((string) ($cachedConnection['username'] ?? ''));
            if ($username !== '') {
                $vncParams['username'] = $username;
            }
        }

        if (trim((string) ($vncParams['password'] ?? '')) === '') {
            $password = (string) ($cachedConnection['password'] ?? '');
            if ($password !== '') {
                $vncParams['password'] = $password;
            }
        }

        return $vncParams;
    }

    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::log($level, $message, $context);
        } catch (\Throwable) {
        }
    }

    private function loadVncTokenParams(string $token, bool $consumeSingleUse): array
    {
        $cacheKey = 'vnc_token:'.$token;
        $params = Cache::get($cacheKey);

        throw_if(! is_array($params) || empty($params), new BusinessException('VNC 链接已过期或无效，请重新获取', 40400, 404));

        if ($consumeSingleUse && (bool) ($params['single_use'] ?? true)) {
            $params = Cache::pull($cacheKey);
            throw_if(! is_array($params) || empty($params), new BusinessException('VNC 链接已过期或无效，请重新获取', 40400, 404));
        }

        return $params;
    }

    // ── Private VNC URL parsing helpers ───────────────────────────────────

    private function resolveVncRelayPath(): string
    {
        $path = trim((string) config('idc.vnc_relay.path', '/ws/vnc'));
        if ($path === '') {
            return '/ws/vnc';
        }

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    private function extractVncParams(string $upstreamUrl): array
    {
        $upstreamUrl = trim($upstreamUrl);
        if ($upstreamUrl === '') {
            return [];
        }

        $parsedUrl = parse_url($upstreamUrl);
        if (! is_array($parsedUrl)) {
            return [];
        }

        $queryStr = trim((string) ($parsedUrl['query'] ?? ''));
        $queryParams = [];
        if ($queryStr !== '') {
            parse_str($queryStr, $queryParams);
        }

        // 情形一：含 base64 WSS（魔方/美得云格式）
        $encodedWssUrl = trim((string) ($queryParams['url'] ?? ''));
        if ($encodedWssUrl !== '') {
            $wssUrl = base64_decode($encodedWssUrl, true);
            if ($wssUrl !== false && preg_match('#^wss?://#i', trim($wssUrl))) {
                return $this->parseWssUrlToParams(trim($wssUrl), $queryParams);
            }
        }

        // 情形二：上游 URL 本身是 WSS
        $scheme = strtolower(trim((string) ($parsedUrl['scheme'] ?? '')));
        if (in_array($scheme, ['ws', 'wss'], true)) {
            return $this->parseWssUrlToParams($upstreamUrl, $queryParams);
        }

        // 情形三：query 直接含 host
        if (isset($queryParams['host']) && $queryParams['host'] !== '') {
            return $this->normalizeUpstreamVncParams($queryParams);
        }

        // 情形四：直接 host:port
        $host = trim((string) ($parsedUrl['host'] ?? ''));
        $port = (int) ($parsedUrl['port'] ?? 0);
        if ($host !== '' && $port > 0) {
            return $this->normalizeUpstreamVncParams(['host' => $host, 'port' => $port, 'encrypt' => '0']);
        }

        return [];
    }

    private function parseWssUrlToParams(string $wssUrl, array $upstreamParams = []): array
    {
        $parsed = parse_url($wssUrl);
        if (! is_array($parsed)) {
            return [];
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? 'wss'));
        $host = trim((string) ($parsed['host'] ?? ''));
        $port = (int) ($parsed['port'] ?? ($scheme === 'wss' ? 443 : 80));
        $path = ltrim((string) ($parsed['path'] ?? ''), '/');
        $wssQuery = trim((string) ($parsed['query'] ?? ''));

        if ($host === '') {
            return [];
        }

        $novncPath = $path;
        if ($wssQuery !== '') {
            $novncPath .= '?'.$wssQuery;
        }

        $params = [
            'host' => $host,
            'port' => $port,
            'encrypt' => $scheme === 'wss' ? '1' : '0',
            'origin' => $this->buildWebsocketOrigin($host, $port, $scheme === 'wss'),
        ];

        if ($novncPath !== '') {
            $params['path'] = $novncPath;
        }

        return $this->normalizeUpstreamVncParams(array_merge($upstreamParams, $params));
    }

    private function normalizeUpstreamVncParams(array $params): array
    {
        $host = trim((string) ($params['host'] ?? ''));
        if ($host === '') {
            return [];
        }

        $normalized = ['host' => $host];

        $port = (int) ($params['port'] ?? 0);
        if ($port > 0) {
            $normalized['port'] = $port;
        }

        if (array_key_exists('encrypt', $params)) {
            $encrypt = $params['encrypt'];
            $normalized['encrypt'] = ($encrypt === true || in_array(strtolower(trim((string) $encrypt)), ['1', 'true', 'yes', 'on'], true))
                ? '1'
                : '0';
        }

        $path = ltrim(trim((string) ($params['path'] ?? '')), '/');
        if ($path !== '') {
            $normalized['path'] = $path;
        }

        $password = trim((string) ($params['password'] ?? $params['pass'] ?? ''));
        if ($password !== '') {
            $normalized['password'] = $password;
        }

        $username = trim((string) ($params['username'] ?? $params['user'] ?? ''));
        if ($username !== '') {
            $normalized['username'] = $username;
        }

        $target = trim((string) ($params['target'] ?? ''));
        if ($target !== '') {
            $normalized['target'] = $target;
        }

        $origin = trim((string) ($params['origin'] ?? ''));
        if ($origin === '') {
            $origin = $this->buildWebsocketOrigin(
                $host,
                $port > 0 ? $port : ((($normalized['encrypt'] ?? '0') === '1') ? 443 : 80),
                (($normalized['encrypt'] ?? '0') === '1')
            );
        }

        if ($origin !== '') {
            $normalized['origin'] = $origin;
        }

        return $normalized;
    }

    private function buildWebsocketOrigin(string $host, int $port, bool $secure): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }

        $scheme = $secure ? 'https' : 'http';
        $defaultPort = $secure ? 443 : 80;

        if ($port > 0 && $port !== $defaultPort) {
            return sprintf('%s://%s:%d', $scheme, $host, $port);
        }

        return sprintf('%s://%s', $scheme, $host);
    }

    private function resolveNoVncBaseUrl(array $context = []): string
    {
        $frontendUrl = $this->resolveClientFacingBaseUrl($context);
        if ($frontendUrl === '') {
            $frontendUrl = rtrim((string) config('app.url', ''), '/');
        }

        if ($frontendUrl === '') {
            return '';
        }

        return $frontendUrl.'/vnc';
    }

    private function resolveNoVncViewerUrl(string $novncBaseUrl): string
    {
        $novncBaseUrl = trim($novncBaseUrl);
        if ($novncBaseUrl === '') {
            return '';
        }

        $parsedUrl = parse_url($novncBaseUrl);
        if ($parsedUrl === false) {
            return '';
        }

        $path = trim((string) ($parsedUrl['path'] ?? ''));
        if (preg_match('#/vnc(?:_auto)?\.html$#i', $path) === 1) {
            return rtrim($novncBaseUrl, '/');
        }

        return rtrim($novncBaseUrl, '/').'/vnc.html';
    }

    /**
     * 判断 noVNC 宿主与业务 API 是否跨域：
     *   - 若 noVNC 是相对路径或与 app.url 同源，返回空字符串（vnc.html 可直接用相对路径）
     *   - 若跨域，返回业务 API 的绝对基址，供 vnc.html 拼接 /api/... 请求
     */
    private function resolveVncApiBase(string $novncBaseUrl, array $context = []): string
    {
        $apiBase = $this->resolvePublicApiBase($context);
        if ($apiBase === '') {
            return '';
        }

        $novncBaseUrl = trim($novncBaseUrl);
        if ($novncBaseUrl === '') {
            return '';
        }

        // 相对路径的 noVNC 部署一定同源，无需 api_base
        if (! preg_match('#^https?://#i', $novncBaseUrl)) {
            return '';
        }

        $novncOrigin = $this->resolveOriginFromUrl($novncBaseUrl);
        $apiOrigin = $this->resolveOriginFromUrl($apiBase);

        if ($novncOrigin === '' || $apiOrigin === '') {
            return $apiBase;
        }

        return strcasecmp($novncOrigin, $apiOrigin) === 0 ? '' : $apiBase;
    }

    private function resolveAllowedVncOrigin(array $context = []): string
    {
        return $this->resolveClientFacingBaseUrl($context);
    }

    private function resolvePublicApiBase(array $context = []): string
    {
        $frontendUrl = $this->resolveClientFacingBaseUrl($context);
        if ($frontendUrl !== '') {
            return $frontendUrl;
        }

        return rtrim((string) config('app.url', ''), '/');
    }

    private function resolveClientFacingBaseUrl(array $context = []): string
    {
        $requestOrigin = $this->normalizeAbsoluteHttpUrl((string) ($context['request_origin'] ?? ''));
        if ($requestOrigin !== '') {
            return $requestOrigin;
        }

        return rtrim((string) config('app.frontend_url', ''), '/');
    }

    private function normalizeAbsoluteHttpUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '';
        }

        $port = (int) ($parts['port'] ?? 0);
        $defaultPort = $scheme === 'https' ? 443 : 80;

        if ($port > 0 && $port !== $defaultPort) {
            return sprintf('%s://%s:%d', $scheme, $host, $port);
        }

        return sprintf('%s://%s', $scheme, $host);
    }

    private function resolveOriginFromUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === '' || $host === '') {
            return '';
        }

        $port = (int) ($parts['port'] ?? 0);
        $defaultPort = $scheme === 'https' ? 443 : 80;

        if ($port > 0 && $port !== $defaultPort) {
            return sprintf('%s://%s:%d', $scheme, $host, $port);
        }

        return sprintf('%s://%s', $scheme, $host);
    }
}
