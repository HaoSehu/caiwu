<?php

declare(strict_types=1);

namespace App\Services\Upstream\Drivers\HostingPanelApi;

use App\Exceptions\BusinessException;
use App\Models\Supplier;
use App\Services\Upstream\Contracts\ProvidesConsoleAccess;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesConsoleNetwork;
use App\Services\Upstream\Contracts\ProvidesConsoleRuntime;
use App\Services\Upstream\Contracts\ProvidesConsoleSecurity;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\Contracts\ProvidesStatusSync;
use App\Services\Upstream\Drivers\HostingPanelApi\Concerns\HandlesApiConfigOptions;
use App\Services\Upstream\Drivers\HostingPanelApi\Concerns\HandlesCatalogNormalization;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class HostingPanelApiTransport implements ProvidesConsoleAccess, ProvidesConsoleCatalog, ProvidesConsoleNetwork, ProvidesConsoleRuntime, ProvidesConsoleSecurity, ProvidesProvisioning, ProvidesRenewal, ProvidesScheduledAuthRefresh, ProvidesStatusSync
{
    use HandlesApiConfigOptions, HandlesCatalogNormalization;

    private const DEFAULT_JWT_CACHE_TTL_SECONDS = 1800;

    private const MIN_JWT_CACHE_TTL_SECONDS = 300;

    private const MAX_JWT_CACHE_TTL_SECONDS = 7200;

    private const DNS_CACHE_TTL_SECONDS = 300;

    private const DEFAULT_DNS_RESOLVER_TIMEOUT_SECONDS = 3;

    private const CONFIG_OPTION_FIELD_MAP = [
        1 => null,
        2 => null,
        3 => null,
        4 => 'ip_num',
        5 => 'os',
        6 => 'cpu',
        7 => 'cpu',
        8 => 'memory',
        9 => 'memory',
        10 => 'bw',
        11 => 'bw',
        12 => 'area',
        13 => 'system_disk_size',
        14 => 'system_disk_size',
        15 => null,
        16 => 'cpu',
        17 => 'memory',
        18 => 'bw',
        19 => 'system_disk_size',
        20 => null,
    ];

    private const RANGE_OPTION_TYPES = [4, 7, 9, 11, 14, 15, 16, 17, 18, 19];

    private const CONFIG_PRICING_CYCLE_MAP = [
        'hour' => 'hour',
        'day' => 'day',
        'ontrial' => 'ontrial',
        'monthly' => 'monthly',
        'quarterly' => 'quarterly',
        'semiannually' => 'semiannually',
        'annually' => 'annually',
        'biennially' => 'biennially',
        'triennially' => 'triennially',
        'fourly' => 'fourly',
        'fively' => 'fively',
        'sixly' => 'sixly',
        'sevenly' => 'sevenly',
        'eightly' => 'eightly',
        'ninely' => 'ninely',
        'tenly' => 'tenly',
        'onetime' => 'one_time',
        'one_time' => 'one_time',
    ];

    private array $serviceConfig;

    public function __construct()
    {
        $defaultConfig = config('idc.hosting_panel_api', []);
        $this->serviceConfig = [
            'user_agent' => (string) ($defaultConfig['user_agent'] ?? 'mozilla/5.0 (compatible; msie 5.01; windows nt 5.0)'),
            'ssl_verify' => $this->normalizeBoolean($defaultConfig['ssl_verify'] ?? true),
            'ca_bundle' => (string) ($defaultConfig['ca_bundle'] ?? ''),
            'allowed_hosts' => array_values(array_filter(array_map(
                static fn (string $item): string => strtolower(trim($item)),
                explode(',', (string) ($defaultConfig['allowed_hosts'] ?? ''))
            ))),
            'jwt_cache_store' => trim((string) ($defaultConfig['jwt_cache_store'] ?? 'redis')) ?: 'redis',
            'dns_resolver_timeout' => max(
                (int) ($defaultConfig['dns_resolver_timeout'] ?? self::DEFAULT_DNS_RESOLVER_TIMEOUT_SECONDS),
                1
            ),
            'timeout' => max((int) ($defaultConfig['timeout'] ?? 30), 1),
            'connect_timeout' => max((int) ($defaultConfig['connect_timeout'] ?? 10), 1),
        ];
    }

    /**
     * 使用供应商 API 账号登录上游主机面板，获取 JWT。
     */
    public function login(Supplier $supplier): string
    {
        $cacheKey = $this->jwtCacheKey($supplier);
        $cachedJwt = trim((string) $this->jwtCache()->get($cacheKey, ''));

        if ($cachedJwt !== '') {
            return $cachedJwt;
        }

        $startedAt = microtime(true);
        $response = $this->loginResponse($supplier);
        $jwt = trim((string) ($response['jwt'] ?? ''));

        if ($jwt === '') {
            throw new BusinessException((string) ($response['msg'] ?? '获取jwt会话失败!'), 42200);
        }

        $ttlSeconds = $this->resolveJwtCacheTtlSeconds($jwt);
        $this->jwtCache()->put($cacheKey, $jwt, now()->addSeconds($ttlSeconds));

        $this->safeLog('info', '[主机面板接口] JWT缓存写入', [
            'supplier_id' => $supplier->id,
            'cache_store' => $this->serviceConfig['jwt_cache_store'],
            'ttl_seconds' => $ttlSeconds,
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        return $jwt;
    }

    /**
     * 强制刷新 JWT（忽略缓存，重新登录上游）。
     */
    public function refreshJwt(Supplier $supplier): string
    {
        $cacheKey = $this->jwtCacheKey($supplier);
        $this->jwtCache()->forget($cacheKey);

        $startedAt = microtime(true);
        $response = $this->loginResponse($supplier);
        $jwt = trim((string) ($response['jwt'] ?? ''));

        if ($jwt === '') {
            throw new BusinessException((string) ($response['msg'] ?? '刷新JWT失败'), 42200);
        }

        $ttlSeconds = $this->resolveJwtCacheTtlSeconds($jwt);
        $this->jwtCache()->put($cacheKey, $jwt, now()->addSeconds($ttlSeconds));

        $this->safeLog('info', '[主机面板接口] JWT强制刷新', [
            'supplier_id' => $supplier->id,
            'cache_store' => $this->serviceConfig['jwt_cache_store'],
            'ttl_seconds' => $ttlSeconds,
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        return $jwt;
    }

    /**
     * 使用供应商 API 账号登录上游主机面板，返回完整响应体。
     */
    public function loginResponse(Supplier $supplier): array
    {
        return $this->request($supplier, 'POST', '/v1/login_api', [
            'account' => (string) $supplier->api_username,
            'password' => (string) $supplier->api_key,
        ]);
    }

    /**
     * 获取上游账号基础资料。
     */
    public function getUserProfile(Supplier $supplier): array
    {
        $response = $this->get($supplier, '/v1/user', $this->login($supplier));
        $data = $response['data'] ?? null;

        if (! is_array($data) || ! isset($data['client']) || ! is_array($data['client'])) {
            throw new BusinessException((string) ($response['msg'] ?? '获取会员基础资料失败'), 42200);
        }

        return $data;
    }

    /**
     * 获取上游账号余额。
     */
    public function getBalance(Supplier $supplier): array
    {
        $response = $this->getUserProfile($supplier);
        $client = $response['client'];

        return [
            'balance' => (string) ($client['credit'] ?? '0.00'),
            'client' => $client,
            'country' => is_array($response['country'] ?? null) ? $response['country'] : [],
        ];
    }

    public function getHostRenewInfo(Supplier $supplier, int $hostId, ?string $billingCycle = null): array
    {
        $jwt = $this->login($supplier);
        $query = [];

        if ($billingCycle !== null && trim($billingCycle) !== '') {
            $query['billingcycle'] = trim($billingCycle);
        }

        return $this->get($supplier, "/v1/hosts/{$hostId}/renew", $jwt, $query);
    }

    public function renewHost(Supplier $supplier, int $hostId, string $billingCycle): array
    {
        $jwt = $this->login($supplier);

        return $this->post($supplier, "/v1/hosts/{$hostId}/renew", [
            'billingcycle' => trim($billingCycle),
        ], $jwt);
    }

    public function setHostAutoRenew(Supplier $supplier, int $hostId, int $initiativeRenew): array
    {
        $jwt = $this->login($supplier);

        return $this->put($supplier, "/v1/hosts/{$hostId}/renew", [
            'initiative_renew' => $initiativeRenew === 1 ? 1 : 0,
        ], $jwt);
    }

    public function getHostDetail(Supplier $supplier, int $hostId, ?string $jwt = null): array
    {
        $resolvedJwt = $jwt !== null && trim($jwt) !== ''
            ? trim($jwt)
            : $this->login($supplier);

        return $this->get($supplier, "/v1/hosts/{$hostId}", $resolvedJwt);
    }

    public function getHostUpgradeConfigOptions(Supplier $supplier, int $hostId, ?string $jwt = null): array
    {
        $resolvedJwt = $jwt !== null && trim($jwt) !== ''
            ? trim($jwt)
            : $this->login($supplier);
        $response = $this->get($supplier, "/v1/hosts/{$hostId}/actions/upgradeconfig", $resolvedJwt);
        $payload = $this->extractResponsePayload($response);

        return [
            'response' => $response,
            'payload' => $payload,
            'currency' => is_array($payload['currency'] ?? null) ? $payload['currency'] : [],
            'options' => $this->normalizeHostUpgradeConfigOptions(
                is_array($payload['host'] ?? null) ? $payload['host'] : []
            ),
        ];
    }

    public function previewHostConfigUpgrade(Supplier $supplier, int $hostId, array $configOption, ?string $jwt = null): array
    {
        $resolvedJwt = $jwt !== null && trim($jwt) !== ''
            ? trim($jwt)
            : $this->login($supplier);

        return $this->post($supplier, "/v1/hosts/{$hostId}/actions/upgradeconfig", [
            'configoption' => $configOption,
        ], $resolvedJwt);
    }

    public function checkoutHostConfigUpgrade(Supplier $supplier, int $hostId, ?string $jwt = null): array
    {
        $resolvedJwt = $jwt !== null && trim($jwt) !== ''
            ? trim($jwt)
            : $this->login($supplier);

        return $this->post($supplier, "/v1/hosts/{$hostId}/actions/upgradeconfig/checkout", [], $resolvedJwt);
    }

    /**
     * 通过上游客户端面板的 servicedetail?action=flowpacket 页面购买流量包。
     * 部分上游仍保留 /dcim/buy_flow_packet 接口，因此页面动作失败时保留旧接口回退。
     */
    public function buyFlowPacket(Supplier $supplier, string $rootUrl, int $flowPacketId, int $hostId, ?string $jwt = null): array
    {
        $resolvedJwt = $jwt !== null && trim($jwt) !== '' ? trim($jwt) : '';
        $headers = [];

        if ($resolvedJwt === '') {
            $webSessionCookie = $this->resolveSupplierWebSessionCookie($supplier);
            if ($webSessionCookie !== '') {
                $headers[] = 'Cookie: '.$webSessionCookie;
            } else {
                $resolvedJwt = $this->login($supplier);
            }
        }

        $rootUrl = rtrim($rootUrl, '/');
        $payload = [
            'flow_packet_id' => $flowPacketId,
            'service_id' => $hostId,
            'id' => $hostId,
        ];

        $pageUrl = $rootUrl.'/servicedetail?'.http_build_query([
            'id' => $hostId,
            'action' => 'flowpacket',
        ]);

        $pageResponse = $this->post($supplier, $pageUrl, $payload, $resolvedJwt !== '' ? $resolvedJwt : null, $headers);
        if ((int) ($pageResponse['code'] ?? -1) === 0) {
            return $pageResponse;
        }

        $url = $rootUrl.'/dcim/buy_flow_packet';

        $legacyResponse = $this->post($supplier, $url, $payload, $resolvedJwt !== '' ? $resolvedJwt : null, $headers);
        if ((int) ($legacyResponse['code'] ?? -1) === 0) {
            return $legacyResponse;
        }

        return $pageResponse;
    }

    public function post(Supplier $supplier, string $uri, array|string $payload = [], ?string $jwt = null, array $headers = [], array $query = []): array
    {
        return $this->request($supplier, 'POST', $uri, $payload, $jwt, $headers, $query);
    }

    public function get(Supplier $supplier, string $uri, ?string $jwt = null, array $query = [], array $headers = []): array
    {
        return $this->request($supplier, 'GET', $uri, [], $jwt, $headers, $query);
    }

    public function getText(Supplier $supplier, string $uri, ?string $jwt = null, array $query = [], array $headers = []): string
    {
        return $this->requestText($supplier, 'GET', $uri, [], $jwt, $headers, $query);
    }

    public function parallelGet(Supplier $supplier, array $requests, ?string $jwt = null, array $headers = []): array
    {
        $baseUrl = trim((string) $supplier->api_url);

        if ($baseUrl === '') {
            throw new BusinessException('供应商接口地址未配置', 42200);
        }

        $jwt = $jwt !== null && trim($jwt) !== ''
            ? trim($jwt)
            : $this->login($supplier);
        $requestHeaders = $this->buildHeaders($jwt, $headers);
        $headerMap = $this->buildHeaderMap($requestHeaders);
        $httpOptions = $this->buildHttpClientOptions();
        $requestsByAlias = [];

        foreach ($requests as $key => $request) {
            $rawAlias = is_string($key) ? $key : (string) ($request['key'] ?? $key);
            $alias = (string) $rawAlias;
            $query = is_array($request['query'] ?? null) ? $request['query'] : [];
            $urlsAlias = $this->normalizePoolAlias($alias);
            $requestsByAlias[$alias] = [
                'pool_alias' => $urlsAlias,
                'url' => $this->buildUrl($baseUrl, (string) ($request['uri'] ?? ''), $query),
            ];
        }

        $startedAt = microtime(true);
        $responses = Http::pool(function (Pool $pool) use ($requestsByAlias, $headerMap, $httpOptions) {
            $pendingRequests = [];

            foreach ($requestsByAlias as $requestMeta) {
                $request = $pool->as($requestMeta['pool_alias'])
                    ->withHeaders($headerMap)
                    ->connectTimeout($this->serviceConfig['connect_timeout'])
                    ->timeout($this->serviceConfig['timeout'])
                    ->withOptions($httpOptions);

                if ($this->serviceConfig['user_agent'] !== '') {
                    $request = $request->withUserAgent($this->serviceConfig['user_agent']);
                }

                $pendingRequests[] = $request->get($requestMeta['url']);
            }

            return $pendingRequests;
        });

        $normalized = [];
        $errors = [];
        $statusCodes = [];

        foreach ($requestsByAlias as $alias => $requestMeta) {
            $response = $responses[$requestMeta['pool_alias']] ?? null;
            $decoded = [];
            $error = '';
            $status = 0;
            $contentType = '';

            if ($response instanceof Response) {
                $status = $response->status();
                $body = trim((string) $response->body(), "\xEF\xBB\xBF");
                $contentType = trim((string) $response->header('Content-Type', ''));
                $decoded = json_decode($body, true);

                if (! is_array($decoded)) {
                    $decoded = [];
                    $error = $this->buildInvalidJsonMessage($status, $contentType, $body);
                }
            } elseif ($response instanceof \Throwable) {
                $error = '并发请求连接失败：'.$response->getMessage();
            } else {
                $error = '并发请求未返回有效响应';
            }

            if ($status === 401 && $jwt !== null && trim($jwt) !== '') {
                $this->forgetJwtCache($supplier);
            }

            $normalized[$alias] = [
                'status_code' => $status,
                'response' => $decoded,
                'error' => $error,
                'content_type' => $contentType,
            ];
            $statusCodes[$alias] = $status;
            if ($error !== '') {
                $errors[$alias] = $error;
            }
        }

        $totalMs = $this->elapsedMilliseconds($startedAt);
        $logContext = [
            'supplier_id' => $supplier->id,
            'count' => count($requestsByAlias),
            'duration_ms' => $totalMs,
            'status_codes' => $statusCodes,
        ];
        if ($errors !== []) {
            $logContext['errors'] = $errors;
        }
        $this->safeLog('info', '[主机面板接口] 并发接口完成', $logContext);

        return $normalized;
    }

    private function normalizePoolAlias(string $alias): string
    {
        $trimmed = trim($alias);

        if ($trimmed === '') {
            return 'request_'.bin2hex(random_bytes(4));
        }

        return preg_match('/^\d+$/', $trimmed) === 1
            ? 'request_'.$trimmed
            : $trimmed;
    }

    public function put(Supplier $supplier, string $uri, array|string $payload = [], ?string $jwt = null, array $headers = [], array $query = []): array
    {
        return $this->request($supplier, 'PUT', $uri, $payload, $jwt, $headers, $query);
    }

    public function requestText(
        Supplier $supplier,
        string $method,
        string $uri,
        array|string $payload = [],
        ?string $jwt = null,
        array $headers = [],
        array $query = []
    ): string {
        $baseUrl = trim((string) $supplier->api_url);

        if ($baseUrl === '') {
            throw new BusinessException('供应商接口地址未配置', 42200);
        }

        $jwt = $this->resolveRequestJwt($supplier, $jwt, $uri, $headers);
        $method = strtoupper(trim($method));
        $url = $this->buildUrl(
            $baseUrl,
            $uri,
            $method === 'GET' ? array_merge($query, is_array($payload) ? $payload : []) : $query
        );
        $requestHeaders = $this->buildHeaders($jwt, $headers);
        $body = $this->buildRequestBody($method, $payload);

        $this->applyUserAgent();

        $startedAt = microtime(true);
        $context = stream_context_create($this->buildContextOptions($method, $requestHeaders, $body));
        error_clear_last();
        $output = @file_get_contents($url, false, $context);
        $lastError = error_get_last();
        $responseHeaders = is_array($http_response_header) ? $http_response_header : [];
        $httpCode = $this->resolveHttpCode($responseHeaders);

        if ($output === false) {
            $this->safeLog('error', '[主机面板接口] 文本接口请求失败', [
                'supplier_id' => $supplier->id,
                'method' => $method,
                'url' => $this->maskUrlForLog($url),
                'http_code' => $httpCode,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error' => $lastError['message'] ?? 'unknown error',
            ]);

            throw new BusinessException('主机面板接口请求失败：'.($lastError['message'] ?? 'unknown error'), 50000);
        }

        if ($httpCode === 401 && $jwt !== null && trim($jwt) !== '') {
            $this->forgetJwtCache($supplier);
        }

        $this->safeLog('info', '[主机面板接口] 文本接口响应', [
            'supplier_id' => $supplier->id,
            'method' => $method,
            'url' => $this->maskUrlForLog($url),
            'http_code' => $httpCode,
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
            'response_preview' => $this->truncateLogValue(trim((string) $output, "\xEF\xBB\xBF")),
        ]);

        return (string) $output;
    }

    /**
     * 发送主机面板接口请求。
     */
    public function request(
        Supplier $supplier,
        string $method,
        string $uri,
        array|string $payload = [],
        ?string $jwt = null,
        array $headers = [],
        array $query = []
    ): array {
        $baseUrl = trim((string) $supplier->api_url);

        if ($baseUrl === '') {
            throw new BusinessException('供应商接口地址未配置', 42200);
        }

        $jwt = $this->resolveRequestJwt($supplier, $jwt, $uri, $headers);
        $method = strtoupper(trim($method));
        $url = $this->buildUrl(
            $baseUrl,
            $uri,
            $method === 'GET' ? array_merge($query, is_array($payload) ? $payload : []) : $query
        );
        $requestHeaders = $this->buildHeaders($jwt, $headers);
        $body = $this->buildRequestBody($method, $payload);

        $this->applyUserAgent();

        $startedAt = microtime(true);
        $context = stream_context_create($this->buildContextOptions($method, $requestHeaders, $body));
        error_clear_last();
        $output = @file_get_contents($url, false, $context);
        $lastError = error_get_last();
        $responseHeaders = is_array($http_response_header) ? $http_response_header : [];
        $httpCode = $this->resolveHttpCode($responseHeaders);
        $contentType = $this->resolveHeaderValue($responseHeaders, 'Content-Type');

        if ($output === false) {
            $this->safeLog('error', '[主机面板接口] 接口请求失败', [
                'supplier_id' => $supplier->id,
                'method' => $method,
                'url' => $this->maskUrlForLog($url),
                'http_code' => $httpCode,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error' => $lastError['message'] ?? 'unknown error',
            ]);

            throw new BusinessException('主机面板接口请求失败：'.($lastError['message'] ?? 'unknown error'), 50000);
        }

        $output = trim($output, "\xEF\xBB\xBF");
        $decoded = json_decode($output, true);

        if ($httpCode === 401 && $jwt !== null && trim($jwt) !== '') {
            $this->forgetJwtCache($supplier);
        }

        $this->safeLog('info', '[主机面板接口] 接口响应', [
            'supplier_id' => $supplier->id,
            'method' => $method,
            'url' => $this->maskUrlForLog($url),
            'http_code' => $httpCode,
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
            'response' => is_array($decoded) ? $this->summarizeLogResponse($decoded) : $this->truncateLogValue($output),
        ]);

        if (! is_array($decoded)) {
            throw new BusinessException($this->buildInvalidJsonMessage($httpCode, $contentType, $output), 50000);
        }

        return $decoded;
    }

    private function applyUserAgent(): void
    {
        if ($this->serviceConfig['user_agent'] === '') {
            return;
        }

        @ini_set('user_agent', $this->serviceConfig['user_agent']);
    }

    private function buildHeaders(?string $jwt, array $headers = []): array
    {
        $requestHeaders = [];

        if ($jwt !== null && trim($jwt) !== '') {
            $requestHeaders[] = 'authorization: JWT '.trim($jwt);
        }

        foreach ($headers as $header) {
            $header = trim((string) $header);
            if ($header !== '') {
                $requestHeaders[] = $header;
            }
        }

        return $requestHeaders;
    }

    private function buildRequestBody(string $method, array|string $payload): ?string
    {
        if ($method === 'GET') {
            return null;
        }

        if (is_array($payload)) {
            return http_build_query($payload);
        }

        return (string) $payload;
    }

    private function extractResponsePayload(array $response): array
    {
        return is_array($response['data'] ?? null) ? $response['data'] : $response;
    }

    private function normalizeHostUpgradeConfigOptions(array $options): array
    {
        return collect($options)
            ->filter(fn ($item) => is_array($item))
            ->values()
            ->map(function (array $item, int $index) {
                $type = (int) ($item['option_type'] ?? 0);
                $name = trim((string) ($item['option_name'] ?? $item['name'] ?? ''));
                $nameParts = explode('|', $name, 2);
                $displayName = trim((string) (count($nameParts) > 1 ? $nameParts[1] : $name));
                $field = $this->resolveConfigOptionField(
                    $item,
                    self::CONFIG_OPTION_FIELD_MAP[$type] ?? null,
                    count($nameParts) > 1 ? trim($nameParts[0]) : '',
                    $displayName
                );
                $subOptions = $this->normalizeRemoteConfigSubOptions($item['sub'] ?? [], $type);
                $currentSubId = (int) ($item['subid'] ?? 0);
                $currentQty = is_numeric($item['qty'] ?? null) ? (int) $item['qty'] : null;
                $currentSub = $currentSubId > 0
                    ? collect($subOptions)->first(fn (array $sub) => (int) ($sub['id'] ?? 0) === $currentSubId)
                    : null;
                $sortOrder = (int) ($item['sort_order'] ?? $item['order'] ?? ($index + 1));

                return [
                    ...$item,
                    'id' => (int) ($item['id'] ?? $item['oid'] ?? 0),
                    'oid' => (int) ($item['oid'] ?? $item['id'] ?? 0),
                    'field' => $field,
                    'name' => $displayName !== '' ? $displayName : $name,
                    'option_name' => $name,
                    'option_type' => $type,
                    'current_sub_id' => $currentSubId > 0 ? $currentSubId : null,
                    'current_qty' => $currentQty,
                    'current_label' => trim((string) (
                        $item['suboption_name']
                        ?? $item['suboption_name_first']
                        ?? $currentSub['version']
                        ?? $currentSub['option_name']
                        ?? ''
                    )),
                    'qty_minimum' => (int) ($item['qty_minimum'] ?? 0),
                    'qty_maximum' => (int) ($item['qty_maximum'] ?? 0),
                    'qty_stage' => max(0, (int) ($item['qty_stage'] ?? 0)),
                    'unit' => trim((string) ($item['unit'] ?? '')),
                    'sort_order' => $sortOrder,
                    'order' => $sortOrder,
                    'sub' => $subOptions,
                ];
            })
            ->all();
    }

    private function buildContextOptions(string $method, array $headers, ?string $body): array
    {
        $sslVerify = $this->serviceConfig['ssl_verify'];
        $httpOptions = [
            'method' => $method,
            // 保留错误响应体，便于解析上游返回的 JSON 错误。
            'ignore_errors' => true,
            'timeout' => $this->serviceConfig['timeout'],
        ];

        if ($headers !== []) {
            $httpOptions['header'] = implode("\r\n", $headers);
        }

        if ($body !== null) {
            $httpOptions['content'] = $body;
        }

        $sslOptions = [
            'capture_peer_cert' => true,
            'verify_peer' => $sslVerify,
            'verify_peer_name' => $sslVerify,
        ];

        if ($sslVerify && $this->serviceConfig['ca_bundle'] !== '' && is_file($this->serviceConfig['ca_bundle'])) {
            $sslOptions['cafile'] = $this->serviceConfig['ca_bundle'];
        }

        return [
            'http' => $httpOptions,
            'ssl' => $sslOptions,
        ];
    }

    private function buildHeaderMap(array $headers): array
    {
        $headerMap = [];

        foreach ($headers as $header) {
            [$name, $value] = array_pad(explode(':', (string) $header, 2), 2, null);
            $name = trim((string) $name);
            $value = trim((string) $value);

            if ($name === '' || $value === '') {
                continue;
            }

            $headerMap[$name] = $value;
        }

        return $headerMap;
    }

    private function buildHttpClientOptions(): array
    {
        $verify = $this->serviceConfig['ssl_verify'];

        if ($verify && $this->serviceConfig['ca_bundle'] !== '' && is_file($this->serviceConfig['ca_bundle'])) {
            $verify = $this->serviceConfig['ca_bundle'];
        }

        return [
            'verify' => $verify,
        ];
    }

    private function buildUrl(string $baseUrl, string $uri, array $query = []): string
    {
        $this->assertTrustedBaseUrl($baseUrl);

        $baseUrl = rtrim($baseUrl, '/');
        $uri = trim($uri);

        $url = preg_match('#^https?://#i', $uri)
            ? $uri
            : $baseUrl.'/'.ltrim($uri, '/');

        if ($query === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($query);
    }

    private function assertTrustedBaseUrl(string $baseUrl): void
    {
        $parsed = parse_url(trim($baseUrl));
        if (! is_array($parsed)) {
            throw new BusinessException('供应商接口地址格式不正确', 42200);
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parsed['host'] ?? '')));

        if ($scheme === '' || $host === '') {
            throw new BusinessException('供应商接口地址格式不正确', 42200);
        }

        if ($scheme !== 'https' && ! app()->environment('local')) {
            throw new BusinessException('供应商接口地址必须使用 HTTPS', 42200);
        }

        $shouldBypassLocalTestDns = $this->shouldBypassDnsLookupForLocalTestHost($host);
        $allowedHosts = (array) ($this->serviceConfig['allowed_hosts'] ?? []);
        if ($allowedHosts !== []) {
            $matched = collect($allowedHosts)->contains(
                fn (string $allowedHost): bool => $host === $allowedHost || str_ends_with($host, '.'.$allowedHost)
            );

            if (! $matched) {
                throw new BusinessException('供应商接口域名不在允许范围内', 42200);
            }
        }

        if ($shouldBypassLocalTestDns) {
            return;
        }

        foreach ($this->resolveHostAddresses($host) as $ipAddress) {
            $publicIp = filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($publicIp === false) {
                throw new BusinessException('供应商接口地址禁止解析到内网或保留地址', 42200);
            }
        }
    }

    private function resolveHostAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if ($this->shouldBypassDnsLookupForLocalTestHost($host)) {
            return ['127.0.0.1'];
        }

        // 域名本身是合法 Redis key 字符（允许 . 分隔），无需 md5
        $cacheKey = 'upstream:hosting_panel_api:dns:'.strtolower($host);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $addresses = $this->lookupHostAddresses($host);
        Cache::put($cacheKey, $addresses, now()->addSeconds(self::DNS_CACHE_TTL_SECONDS));

        return $addresses;
    }

    /**
     * @return array<int, string>
     */
    protected function lookupHostAddresses(string $host): array
    {
        $addresses = [];

        try {
            $addresses = $this->runDnsLookupProcess($host, (int) $this->serviceConfig['dns_resolver_timeout']);
        } catch (\Throwable $e) {
            if ($e instanceof ProcessTimedOutException) {
                $this->safeLog('warning', '[主机面板接口] 异步DNS解析超时', ['host' => $host, 'message' => $e->getMessage()]);
            } else {
                $this->safeLog('warning', '[主机面板接口] 异步DNS解析失败', ['host' => $host, 'message' => $e->getMessage()]);
            }
        }

        if ($addresses === []) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);

            if (is_array($records)) {
                foreach ($records as $record) {
                    $ipAddress = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
                    if ($ipAddress !== '') {
                        $addresses[] = $ipAddress;
                    }
                }
            }
        }

        if ($addresses === []) {
            $fallback = @gethostbynamel($host);

            if (is_array($fallback)) {
                foreach ($fallback as $ipAddress) {
                    $ipAddress = (string) $ipAddress;
                    if ($ipAddress !== '') {
                        $addresses[] = $ipAddress;
                    }
                }
            }
        }

        throw_if($addresses === [], new BusinessException('无法解析供应商接口域名', 42200));

        return array_values(array_unique($addresses));
    }

    private function shouldBypassDnsLookupForLocalTestHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }

        if (app()->environment('local', 'testing')) {
            return str_ends_with($host, '.test')
                || str_ends_with($host, '.example.test')
                || str_ends_with($host, '.localhost');
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function runDnsLookupProcess(string $host, int $timeoutSeconds): array
    {
        $phpBinary = trim((string) PHP_BINARY);
        if ($phpBinary === '') {
            return [];
        }

        $script = <<<'PHP'
$host = (string) ($argv[1] ?? '');
$addresses = [];

if ($host !== '') {
    $records = @dns_get_record($host, DNS_A + DNS_AAAA);

    if (is_array($records)) {
        foreach ($records as $record) {
            $ipAddress = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ipAddress !== '') {
                $addresses[] = $ipAddress;
            }
        }
    }

    if ($addresses === []) {
        $fallback = @gethostbynamel($host);
        if (is_array($fallback)) {
            foreach ($fallback as $ipAddress) {
                $ipAddress = (string) $ipAddress;
                if ($ipAddress !== '') {
                    $addresses[] = $ipAddress;
                }
            }
        }
    }
}

echo json_encode(array_values(array_unique($addresses)));
PHP;

        $process = new Process([$phpBinary, '-r', $script, $host]);
        $process->setTimeout(max($timeoutSeconds, 1));
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $decoded = json_decode((string) $process->getOutput(), true);
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($ipAddress) => is_string($ipAddress) && trim($ipAddress) !== '')
            ->values()
            ->all();
    }

    private function maskUrlForLog(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return '[upstream-url]';
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = ! empty($parts['path']) ? '/***' : '';
        $query = ! empty($parts['query']) ? '?***' : '';

        return $scheme.substr($host, 0, 2).'***'.substr($host, -2).$port.$path.$query;
    }

    private function resolveHttpCode(array $responseHeaders): int
    {
        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', (string) $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function resolveHeaderValue(array $responseHeaders, string $headerName): string
    {
        $normalizedHeaderName = strtolower(trim($headerName));

        foreach ($responseHeaders as $header) {
            $line = trim((string) $header);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            if (strtolower(trim($name)) === $normalizedHeaderName) {
                return trim($value);
            }
        }

        return '';
    }

    private function buildInvalidJsonMessage(int $httpCode, string $contentType, string $body): string
    {
        $body = trim($body);
        $contentType = trim($contentType);
        $bodyPreview = str_replace(["\r", "\n", "\t"], ' ', $body);
        $bodyPreview = trim(preg_replace('/\s+/u', ' ', $bodyPreview) ?? $bodyPreview);
        $bodyPreview = mb_substr($bodyPreview, 0, 120);

        if ($body === '') {
            return $httpCode > 0
                ? "主机面板接口返回空响应（HTTP {$httpCode}）"
                : '主机面板接口返回空响应';
        }

        if ($this->looksLikeHtmlResponse($body, $contentType)) {
            $suffix = $bodyPreview !== '' ? "，响应片段：{$bodyPreview}" : '';

            return $httpCode > 0
                ? "主机面板接口返回 HTML 页面而不是 JSON（HTTP {$httpCode}）{$suffix}"
                : "主机面板接口返回 HTML 页面而不是 JSON{$suffix}";
        }

        $suffix = $bodyPreview !== '' ? "，响应片段：{$bodyPreview}" : '';
        $contentTypeSuffix = $contentType !== '' ? "，Content-Type: {$contentType}" : '';

        return $httpCode > 0
            ? "主机面板接口返回异常，未解析到有效 JSON（HTTP {$httpCode}{$contentTypeSuffix}）{$suffix}"
            : "主机面板接口返回异常，未解析到有效 JSON{$contentTypeSuffix}{$suffix}";
    }

    private function looksLikeHtmlResponse(string $body, string $contentType = ''): bool
    {
        if ($contentType !== '' && str_contains(strtolower($contentType), 'text/html')) {
            return true;
        }

        $prefix = strtolower(ltrim($body));

        return str_starts_with($prefix, '<!doctype html')
            || str_starts_with($prefix, '<html')
            || str_starts_with($prefix, '<script')
            || str_starts_with($prefix, '<body');
    }

    private function sanitizeLogPayload(array|string $payload): array
    {
        if (is_array($payload)) {
            return $this->sanitizeLogArray($payload);
        }

        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            return ['raw' => $this->sanitizeLogArray($decoded)];
        }

        return ['raw' => $this->truncateLogValue($payload)];
    }

    private function sanitizeLogArray(array $data): array
    {
        if (array_is_list($data)) {
            $preview = array_map(
                fn ($item) => is_array($item) ? $this->sanitizeLogArray($item) : $this->truncateLogValue($item),
                array_slice($data, 0, 6)
            );

            return [
                'count' => count($data),
                'preview' => $preview,
            ];
        }

        $sanitized = [];
        $index = 0;

        foreach ($data as $key => $value) {
            if ($index >= 20) {
                $sanitized['_truncated_keys'] = count($data) - 20;
                break;
            }

            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, ['password', 'api_key', 'jwt', 'authorization'], true)) {
                $sanitized[$key] = '***';
                $index++;

                continue;
            }

            if (in_array($normalizedKey, ['author_url', 'pay_html'], true)) {
                $sanitized[$key] = '[omitted]';
                $index++;

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeLogArray($value);
                $index++;

                continue;
            }

            $sanitized[$key] = $this->truncateLogValue($value);
            $index++;
        }

        return $sanitized;
    }

    private function summarizeLogResponse(mixed $response): mixed
    {
        if (! is_array($response)) {
            return $this->truncateLogValue($response);
        }

        $summary = $this->sanitizeLogArray($response);

        $payload = is_array($response['data'] ?? null) ? $response['data'] : [];
        if ($payload !== [] && is_array($payload['list'] ?? null)) {
            $summary['chart_summary'] = [
                'chart_type' => trim((string) ($payload['chart_type'] ?? '')),
                'unit' => trim((string) ($payload['unit'] ?? '')),
                'labels' => $this->sanitizeLogArray(is_array($payload['label'] ?? null) ? $payload['label'] : [$payload['label'] ?? '']),
                'series_count' => count($payload['list']),
                'point_counts' => collect($payload['list'])
                    ->map(fn ($series) => is_array($series) ? count($series) : 0)
                    ->values()
                    ->all(),
            ];
        }

        return $summary;
    }

    private function truncateLogValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return strlen($value) > 240
            ? substr($value, 0, 237).'...'
            : $value;
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

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function resolveResponseDurationMs(mixed $response): ?int
    {
        if (! $response || ! method_exists($response, 'handlerStats')) {
            return null;
        }

        $stats = (array) $response->handlerStats();
        $seconds = $stats['total_time'] ?? null;

        return is_numeric($seconds) ? (int) round(((float) $seconds) * 1000) : null;
    }

    private function jwtCacheKey(Supplier $supplier): string
    {
        return 'upstream:hosting_panel_api:jwt:'.$supplier->id;
    }

    private function jwtCache(): CacheRepository
    {
        $store = trim((string) ($this->serviceConfig['jwt_cache_store'] ?? 'redis'));

        try {
            return Cache::store($store !== '' ? $store : config('cache.default', 'redis'));
        } catch (\Throwable $exception) {
            $this->safeLog('warning', '[主机面板接口] JWT缓存仓库不可用，回退默认缓存仓库', [
                'store' => $store,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return Cache::store(config('cache.default', 'file'));
        }
    }

    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::log($level, $message, $context);
        } catch (\Throwable) {
        }
    }

    private function forgetJwtCache(Supplier $supplier): void
    {
        $this->jwtCache()->forget($this->jwtCacheKey($supplier));
    }

    private function resolveRequestJwt(Supplier $supplier, ?string $jwt, string $uri, array $headers = []): ?string
    {
        $jwt = trim((string) $jwt);
        if ($jwt !== '') {
            return $jwt;
        }

        if ($this->isLoginEndpoint($uri)) {
            return null;
        }

        if ($this->hasCookieHeader($headers)) {
            return null;
        }

        return $this->login($supplier);
    }

    private function hasCookieHeader(array $headers): bool
    {
        foreach ($headers as $header) {
            if (str_starts_with(strtolower(trim((string) $header)), 'cookie:')) {
                return true;
            }
        }

        return false;
    }

    private function resolveSupplierWebSessionCookie(Supplier $supplier): string
    {
        $notes = trim((string) ($supplier->notes ?? ''));
        if ($notes === '') {
            return '';
        }

        $decoded = json_decode($notes, true);
        if (is_array($decoded)) {
            foreach (['web_session_cookie', 'upstream_cookie', 'cookie'] as $key) {
                $value = trim((string) ($decoded[$key] ?? ''));
                if ($value !== '') {
                    return $this->normalizeCookieHeaderValue($value);
                }
            }
        }

        if (preg_match('/(?:web_session_cookie|upstream_cookie|cookie)\s*[=:]\s*(.+)$/imu', $notes, $match) === 1) {
            return $this->normalizeCookieHeaderValue((string) $match[1]);
        }

        if (preg_match('/\bZJMF_[A-Z0-9_]+\s*=/i', $notes) === 1) {
            return $this->normalizeCookieHeaderValue($notes);
        }

        return '';
    }

    private function normalizeCookieHeaderValue(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^Cookie\s*:\s*/i', '', $value) ?? $value;
        $value = strtok($value, "\r\n") ?: '';

        return trim($value);
    }

    private function isLoginEndpoint(string $uri): bool
    {
        $uri = strtolower(trim($uri));

        return $uri === '/v1/login_api'
            || str_contains($uri, '/v1/login_api?');
    }

    private function resolveJwtCacheTtlSeconds(string $jwt): int
    {
        $payload = $this->decodeJwtPayload($jwt);
        $expiresAt = (int) ($payload['exp'] ?? 0);

        if ($expiresAt <= 0) {
            return self::DEFAULT_JWT_CACHE_TTL_SECONDS;
        }

        $ttlSeconds = $expiresAt - time() - 300;

        return max(
            self::MIN_JWT_CACHE_TTL_SECONDS,
            min($ttlSeconds, self::MAX_JWT_CACHE_TTL_SECONDS)
        );
    }

    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            return [];
        }

        $decoded = json_decode($this->decodeBase64Url($parts[1]), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function decodeBase64Url(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    private function supportsConfigTemplate(array $product): bool
    {
        return in_array(trim((string) ($product['type'] ?? '')), ['dcimcloud', 'cloud', 'vps'], true);
    }

    private function buildCloudConfigTemplate(array $product): array
    {
        $autoParameters = $this->extractCloudProductParameters($product);

        return array_map(function (array $item, int $index) use ($autoParameters) {
            return [
                'spec_key' => $item['field'],
                'source' => 'mofang_api',
                'field' => $item['field'],
                'name' => $item['name'],
                'parameter' => $autoParameters[$item['field']] ?? '',
                'description' => $item['description'],
                'required' => $item['required'] ? 1 : 0,
                'default_value' => $item['default_value'],
                'sort_order' => $index + 1,
                'hidden' => 0,
                'allow_upgrade' => 0,
                'allow_promo_code' => 1,
            ];
        }, $this->cloudConfigCatalog(), array_keys($this->cloudConfigCatalog()));
    }

    private function cloudConfigCatalog(): array
    {
        return [
            [
                'field' => 'area',
                'name' => '数据中心',
                'description' => '区域 ID，数据中心和节点 id 至少传一个。',
                'required' => true,
                'default_value' => '-',
            ],
            [
                'field' => 'node',
                'name' => '节点 id',
                'description' => '节点 ID，数据中心和节点 id 至少传一个。',
                'required' => false,
                'default_value' => '不传递系统将自动分配',
            ],
            [
                'field' => 'os',
                'name' => '操作系统',
                'description' => '镜像管理中的操作系统 ID。',
                'required' => true,
                'default_value' => '-',
            ],
            [
                'field' => 'cpu',
                'name' => 'CPU',
                'description' => '开通实例时分配的 CPU 核心数。',
                'required' => true,
                'default_value' => '-',
            ],
            [
                'field' => 'memory',
                'name' => '内存',
                'description' => '开通实例时分配的内存大小，单位 M。',
                'required' => true,
                'default_value' => '-',
            ],
            [
                'field' => 'system_disk_size',
                'name' => '系统盘',
                'description' => '支持固定系统盘、按系统区分大小以及指定存储 ID 三种传递方式。',
                'required' => false,
                'default_value' => '不传递默认 50G，windows 系统盘最小 30G',
            ],
            [
                'field' => 'network_type',
                'name' => '网络类型',
                'description' => 'normal 为经典网络，vpc 为 VPC 网络。',
                'required' => true,
                'default_value' => '-',
            ],
            [
                'field' => 'bw',
                'name' => '带宽',
                'description' => '上下行带宽，单位 Mbps。',
                'required' => false,
                'default_value' => '不传递默认为 0Mbps',
            ],
            [
                'field' => 'in_bw',
                'name' => '流入带宽',
                'description' => '进带宽，若配置该参数则优先使用。',
                'required' => false,
                'default_value' => '不传递默认为 0Mbps',
            ],
            [
                'field' => 'flow_limit',
                'name' => '流量',
                'description' => '流量大小，单位 G。',
                'required' => false,
                'default_value' => '不传递默认为不限量',
            ],
            [
                'field' => 'flow_way',
                'name' => '流量方向',
                'description' => '控制流量统计方向，可选 in、out、all。',
                'required' => false,
                'default_value' => '不传递默认为 all',
            ],
            [
                'field' => 'ip_num',
                'name' => 'IP 数量',
                'description' => '实例分配的 IPv4 数量。',
                'required' => true,
                'default_value' => '-',
            ],
            [
                'field' => 'data_disk_size',
                'name' => '数据盘',
                'description' => '数据盘大小，单位 G，可附带存储 ID。',
                'required' => false,
                'default_value' => '不传递默认无数据盘',
            ],
            [
                'field' => 'snap_num',
                'name' => '快照数量',
                'description' => '控制实例快照数量上限。',
                'required' => false,
                'default_value' => '不传递默认 2 个',
            ],
            [
                'field' => 'backup_num',
                'name' => '备份数量',
                'description' => '控制实例备份数量上限。',
                'required' => false,
                'default_value' => '不传递默认 2 个',
            ],
            [
                'field' => 'nat_acl_limit',
                'name' => 'NAT 转发',
                'description' => '控制 NAT 转发数量。',
                'required' => false,
                'default_value' => '不传递默认不支持',
            ],
            [
                'field' => 'nat_web_limit',
                'name' => '共享建站',
                'description' => '控制 NAT 建站数量。',
                'required' => false,
                'default_value' => '不传递默认不支持',
            ],
            [
                'field' => 'system_disk_io_limit',
                'name' => '系统盘性能',
                'description' => '系统盘读写带宽和 IOPS 限制。',
                'required' => false,
                'default_value' => '不传递默认不限制',
            ],
            [
                'field' => 'data_disk_io_limit',
                'name' => '数据盘性能',
                'description' => '数据盘读写带宽和 IOPS 限制。',
                'required' => false,
                'default_value' => '不传递默认不限制',
            ],
            [
                'field' => 'ip_group',
                'name' => 'IP 分组',
                'description' => 'IP 管理中的 IP 分组 ID。',
                'required' => false,
                'default_value' => '-',
            ],
            [
                'field' => 'node_group',
                'name' => '节点分组',
                'description' => '节点管理中的节点分组 ID。',
                'required' => false,
                'default_value' => '-',
            ],
            [
                'field' => 'node_priority',
                'name' => '节点选择优先级',
                'description' => '创建实例时的节点分配策略。',
                'required' => false,
                'default_value' => '不传递默认数量平均',
            ],
            [
                'field' => 'IP_MACBond',
                'name' => '嵌套虚拟化',
                'description' => '控制 IP-MAC 绑定开关。',
                'required' => false,
                'default_value' => '不传递默认开启绑定',
            ],
            [
                'field' => 'cpu_limit',
                'name' => 'CPU 限制',
                'description' => '实例 CPU 使用率限制。',
                'required' => false,
                'default_value' => '不传递默认以上游系统设置为准',
            ],
            [
                'field' => 'traffic_bill_type',
                'name' => '流量计费周期',
                'description' => '控制流量清零周期。',
                'required' => false,
                'default_value' => '不传递默认每月 1 日清零',
            ],
            [
                'field' => 'type',
                'name' => '云节点类型',
                'description' => '控制云节点类型。',
                'required' => false,
                'default_value' => '不传递默认 KVM 加强版',
            ],
            [
                'field' => 'advanced_cpu',
                'name' => '智能 CPU',
                'description' => '监控系统中的智能 CPU 规则 ID。',
                'required' => false,
                'default_value' => '-',
            ],
            [
                'field' => 'advanced_bw',
                'name' => '智能带宽',
                'description' => '监控系统中的智能带宽规则 ID。',
                'required' => false,
                'default_value' => '-',
            ],
            [
                'field' => 'port',
                'name' => '端口',
                'description' => '支持随机端口或指定端口。',
                'required' => false,
                'default_value' => '不传递默认不支持',
            ],
            [
                'field' => 'ipv6_num',
                'name' => 'ipv6 数量',
                'description' => '实例分配的 IPv6 数量。',
                'required' => false,
                'default_value' => '-',
            ],
            [
                'field' => 'resource_package',
                'name' => '资源包',
                'description' => '上游资源包 ID。',
                'required' => false,
                'default_value' => '不传递默认不支持',
            ],
            [
                'field' => 'gpu_num',
                'name' => 'GPU 数量',
                'description' => '实例分配的 GPU 数量。',
                'required' => false,
                'default_value' => '不传递默认不支持',
            ],
            [
                'field' => 'niccard',
                'name' => '网卡驱动',
                'description' => '控制网卡驱动类型。',
                'required' => false,
                'default_value' => '不传递默认不支持',
            ],
        ];
    }

    private function extractCloudProductParameters(array $product): array
    {
        $description = $this->normalizeDescriptionText((string) ($product['description'] ?? ''));
        $facts = [];

        if (preg_match('/CPU[:：]\s*(\d+(?:\.\d+)?)\s*核/iu', $description, $matches) === 1) {
            $cpu = (int) round((float) $matches[1]);
            if ($cpu > 0) {
                $facts['cpu'] = "{$cpu}|{$cpu} 核心";
            }
        }

        if (preg_match('/内存[:：]\s*(\d+(?:\.\d+)?)\s*G/iu', $description, $matches) === 1) {
            $memoryGb = (float) $matches[1];
            $memoryMb = (int) round($memoryGb * 1024);
            if ($memoryMb > 0) {
                $memoryText = rtrim(rtrim(number_format($memoryGb, 2, '.', ''), '0'), '.');
                $facts['memory'] = "{$memoryMb}|{$memoryText}G";
            }
        }

        if (preg_match('/带宽[:：]\s*(\d+(?:\.\d+)?)\s*M/iu', $description, $matches) === 1) {
            $bandwidth = rtrim(rtrim(number_format((float) $matches[1], 2, '.', ''), '0'), '.');
            if ($bandwidth !== '' && (float) $bandwidth > 0) {
                $facts['bw'] = "{$bandwidth}|{$bandwidth}Mbps";
            }
        }

        if (preg_match('/流量[:：]\s*(\d+(?:\.\d+)?)\s*T/iu', $description, $matches) === 1) {
            $flowTb = (float) $matches[1];
            $flowGb = (int) round($flowTb * 1024);
            if ($flowGb > 0) {
                $flowText = rtrim(rtrim(number_format($flowTb, 2, '.', ''), '0'), '.');
                $facts['flow_limit'] = "{$flowGb}|{$flowText}T";
            }
        } elseif (preg_match('/流量[:：]\s*(\d+(?:\.\d+)?)\s*G/iu', $description, $matches) === 1) {
            $flowGb = rtrim(rtrim(number_format((float) $matches[1], 2, '.', ''), '0'), '.');
            if ($flowGb !== '' && (float) $flowGb > 0) {
                $facts['flow_limit'] = "{$flowGb}|{$flowGb}G";
            }
        }

        if (preg_match('/硬盘[:：]\s*(\d+(?:\.\d+)?)\s*G/iu', $description, $matches) === 1) {
            $disk = rtrim(rtrim(number_format((float) $matches[1], 2, '.', ''), '0'), '.');
            if ($disk !== '' && (float) $disk > 0) {
                $facts['system_disk_size'] = "{$disk}|系统盘";
            }
        }

        $contextText = mb_strtolower(
            implode(' ', array_filter([
                (string) ($product['name'] ?? ''),
                (string) ($product['group_name'] ?? ''),
                (string) ($product['group_label'] ?? ''),
                $description,
            ]))
        );

        if (str_contains($contextText, '轻量')) {
            $facts['type'] = 'lightHost|KVM 轻量版';
        } elseif (str_contains($contextText, 'hyper-v') || str_contains($contextText, 'hyperv')) {
            $facts['type'] = 'hyperv|Hyper-V';
        } elseif (str_contains($contextText, '拨号')) {
            $facts['type'] = 'adsl|拨号云';
        }

        return $facts;
    }

    private function normalizeDescriptionText(string $description): string
    {
        $description = preg_replace('/<br\s*\/?>/iu', "\n", $description) ?? $description;
        $description = strip_tags($description);
        $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace("/\r\n|\r/u", "\n", $description) ?? $description;

        return trim($description);
    }
}
