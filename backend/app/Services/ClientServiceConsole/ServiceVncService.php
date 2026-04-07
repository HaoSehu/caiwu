<?php

declare(strict_types=1);

namespace App\Services\ClientServiceConsole;

use App\Exceptions\BusinessException;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\MofangFinanceClient;
use App\Services\OperationLogService;
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
        private readonly MofangFinanceClient   $mofangFinanceClient,
        private readonly OperationLogService   $operationLogService,
        private readonly ServiceDetailService  $detailService,
        private readonly ServiceTransformService $transformService,
    ) {}

    public function getVncUrlForUser(User $user, int $serviceId, array $context = []): array
    {
        $service = $this->detailService->findUserService($user, $serviceId, [
            'product:id,name,product_type,supplier_id,provision_module,config_options',
            'product.categoryMapping:id,legacy_group_id,parent_id,product_type,name,title,slogan,slug',
            'product.categoryMapping.parent:id,legacy_group_id,parent_id,product_type,name,title,slogan,slug',
            'product.supplier',
            'order:id,order_no,status,paid_at,created_at',
        ]);
        throw_if(! $this->transformService->canExecuteConsoleActions($service), new BusinessException('当前实例状态不支持该操作', 42200));

        [$supplier, $hostId, $jwt] = $this->detailService->resolveUpstreamContext($service);

        $response = $this->mofangFinanceClient->post(
            $supplier,
            '/provision/default',
            ['func' => 'vnc', 'id' => $hostId],
            $jwt,
            ['content-type: application/x-www-form-urlencoded']
        );
        $this->detailService->assertSuccess($response, '获取VNC链接');

        $payload        = $this->detailService->extractPayload($response);
        $upstreamVncUrl = trim((string) ($payload['url'] ?? $payload['vnc'] ?? $payload['link'] ?? ''));

        if ($upstreamVncUrl === '') {
            throw new BusinessException('上游未返回VNC链接', 50000);
        }

        $novncBaseUrl = trim((string) Setting::getValue('system', 'vnc_novnc_url', ''));
        if ($novncBaseUrl === '') {
            $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');
            if ($frontendUrl !== '') {
                $novncBaseUrl = $frontendUrl . '/vnc';
            }
        }

        $message = trim((string) ($response['msg'] ?? '')) ?: '获取VNC链接成功';
        $vncUrl  = $upstreamVncUrl;

        if ($novncBaseUrl !== '') {
            $vncParams = $this->extractVncParams($upstreamVncUrl);

            Log::info('[VNC] 解析结果', [
                'service_id'   => $serviceId,
                'has_host'     => isset($vncParams['host']) && $vncParams['host'] !== '',
                'has_port'     => isset($vncParams['port']) && $vncParams['port'] > 0,
                'has_path'     => isset($vncParams['path']) && $vncParams['path'] !== '',
                'encrypt'      => $vncParams['encrypt'] ?? '(空)',
                'has_password' => isset($vncParams['password']) && $vncParams['password'] !== '',
            ]);

            if (! empty($vncParams)) {
                $token = bin2hex(random_bytes(24));
                Cache::put('vnc_token:' . $token, array_merge($vncParams, [
                    'service_id' => $serviceId,
                ]), now()->addSeconds(self::VNC_TOKEN_TTL_SECONDS));

                $viewerUrl = $this->resolveNoVncViewerUrl($novncBaseUrl);
                $vncUrl    = $viewerUrl . (str_contains($viewerUrl, '?') ? '&' : '?') . http_build_query([
                    'token' => $token,
                    'service_id' => $serviceId,
                ]);
            } else {
                Log::warning('[VNC] noVNC地址已配置但上游链接解析失败，已回退使用上游链接', [
                    'service_id'    => $serviceId,
                    'novnc_base_url' => $novncBaseUrl,
                ]);
            }
        }

        $this->operationLogService->writeServiceConsoleLog($service, 'service.console.vnc.get', [
            'category' => 'vnc',
            'summary'  => '获取VNC链接',
            'host_id'  => $hostId,
            'message'  => $message,
        ], $context);

        return [
            'message' => $message,
            'url'     => $vncUrl,
            'detail'  => $this->transformService->transformDetail($service),
        ];
    }

    public function resolveVncToken(string $token): array
    {
        $params = Cache::pull('vnc_token:' . $token);

        throw_if(! is_array($params) || empty($params), new BusinessException('VNC 链接已过期或无效，请重新获取', 40400, 404));

        return $params;
    }

    public function resolvePublicVncTokenPayload(string $token): array
    {
        $params = $this->resolveVncToken($token);

        return [
            'token'      => $token,
            'service_id' => (int) ($params['service_id'] ?? 0),
            'relay_path' => $this->resolveVncRelayPath(),
            'password'   => trim((string) ($params['password'] ?? '')),
            'username'   => trim((string) ($params['username'] ?? '')),
            'target'     => trim((string) ($params['target'] ?? '')),
        ];
    }

    // ── Private VNC URL parsing helpers ───────────────────────────────────

    private function resolveVncRelayPath(): string
    {
        $path = trim((string) config('idc.vnc_relay.path', '/ws/vnc'));
        if ($path === '') {
            return '/ws/vnc';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
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

        $queryStr    = trim((string) ($parsedUrl['query'] ?? ''));
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

        $scheme   = strtolower((string) ($parsed['scheme'] ?? 'wss'));
        $host     = trim((string) ($parsed['host'] ?? ''));
        $port     = (int) ($parsed['port'] ?? ($scheme === 'wss' ? 443 : 80));
        $path     = ltrim((string) ($parsed['path'] ?? ''), '/');
        $wssQuery = trim((string) ($parsed['query'] ?? ''));

        if ($host === '') {
            return [];
        }

        $novncPath = $path;
        if ($wssQuery !== '') {
            $novncPath .= '?' . $wssQuery;
        }

        $params = [
            'host'    => $host,
            'port'    => $port,
            'encrypt' => $scheme === 'wss' ? '1' : '0',
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

        return $normalized;
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

        return rtrim($novncBaseUrl, '/') . '/vnc.html';
    }
}
