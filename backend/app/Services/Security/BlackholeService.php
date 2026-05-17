<?php

namespace App\Services\Security;

use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlackholeService
{
    private const DEFAULT_CACHE_TTL_SECONDS = 60;

    private const DEFAULT_TIMEOUT_SECONDS = 12;

    private bool|string $verifyOption;

    private int $timeout;

    private int $cacheTtlSeconds;

    private string $userAgent;

    private string $ningboBaseUrl;

    private string $shiyanBaseUrl;

    private string $publicBaseUrl;

    private string $hongkongApiUrl;

    private string $us1TrafficBaseUrl;

    public function __construct()
    {
        $config = config('idc.blackhole', []);
        $sslVerify = filter_var($config['ssl_verify'] ?? true, FILTER_VALIDATE_BOOL);
        $caBundle = trim((string) ($config['ca_bundle'] ?? ''));

        $this->verifyOption = $caBundle !== '' && is_file($caBundle) ? $caBundle : $sslVerify;
        $this->timeout = max(1, (int) ($config['timeout'] ?? self::DEFAULT_TIMEOUT_SECONDS));
        $this->cacheTtlSeconds = max(1, (int) ($config['cache_ttl_seconds'] ?? self::DEFAULT_CACHE_TTL_SECONDS));
        $this->userAgent = trim((string) ($config['user_agent'] ?? ''));
        $this->ningboBaseUrl = rtrim((string) ($config['ningbo_base_url'] ?? 'http://160.202.238.2:81'), '/');
        $this->shiyanBaseUrl = rtrim((string) ($config['shiyan_base_url'] ?? 'http://160.202.238.2:90'), '/');
        $this->publicBaseUrl = rtrim((string) ($config['public_base_url'] ?? 'https://blackhole.jdidc.cn'), '/');
        $this->hongkongApiUrl = (string) ($config['hongkong_api_url'] ?? 'https://mianban.288cloud.com/ddos/api/');
        $this->us1TrafficBaseUrl = rtrim((string) ($config['us1_traffic_base_url'] ?? 'https://do.yazzi.net/index/history'), '/');
    }

    public function query(string $ip): array
    {
        $normalizedIp = trim($ip);
        $cacheKey = 'blackhole:query:v3:'.md5($normalizedIp);
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $result = $this->performQuery($normalizedIp);

        if (($result['available_source_count'] ?? 0) > 0) {
            Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTtlSeconds));
        }

        return $result;
    }

    public function addNingboWhitelist(string $ip, string $domain): array
    {
        $response = $this->requestJson(
            '宁波域名过白',
            $this->ningboBaseUrl.'/api/gb.php',
            ['ip' => trim($ip), 'name' => trim($domain)]
        );

        if (($response['ok'] ?? false) !== true) {
            return $this->buildOperationFailure('ningbo_whitelist', '宁波域名过白', $response['message'] ?? '请求失败');
        }

        $payload = $response['data'];
        $businessCode = (int) ($payload['code'] ?? 0);
        $message = (string) ($payload['message'] ?? ($businessCode === 200 ? '过白成功' : '过白失败'));

        if ($businessCode !== 200) {
            return $this->buildOperationFailure('ningbo_whitelist', '宁波域名过白', $message, [
                'business_code' => $businessCode,
                'raw' => $payload,
            ]);
        }

        return $this->buildOperationSuccess('ningbo_whitelist', '宁波域名过白', $message, [
            'business_code' => $businessCode,
            'raw' => $payload,
        ]);
    }

    public function setShiyanLayer7Rule(string $ip, int $ruleId, bool $enabled): array
    {
        $response = $this->requestJson(
            '十堰7层策略切换',
            $this->shiyanBaseUrl.'/use/request.php',
            [
                'ip' => trim($ip),
                'id' => $ruleId,
                'status' => $enabled ? 1 : 0,
            ]
        );

        if (($response['ok'] ?? false) !== true) {
            return $this->buildOperationFailure('shiyan_layer7_toggle', '十堰7层策略切换', $response['message'] ?? '请求失败');
        }

        $payload = $response['data'];
        $businessCode = (int) ($payload['status'] ?? 0);
        $message = (string) ($payload['msg'] ?? ($businessCode === 200 ? '操作成功' : '操作失败'));

        if ($businessCode !== 200) {
            return $this->buildOperationFailure('shiyan_layer7_toggle', '十堰7层策略切换', $message, [
                'business_code' => $businessCode,
                'raw' => $payload,
            ]);
        }

        return $this->buildOperationSuccess('shiyan_layer7_toggle', '十堰7层策略切换', $message, [
            'business_code' => $businessCode,
            'rule_id' => $ruleId,
            'enabled' => $enabled,
            'raw' => $payload,
        ]);
    }

    public function addShiyanLayer4Rule(string $ip, int $mode): array
    {
        $response = $this->requestHtml(
            '十堰4层策略新增',
            $this->shiyanBaseUrl.'/through/through.php',
            'POST',
            [
                'action' => 'add',
                'ip' => trim($ip),
                'mode' => $mode,
            ]
        );

        if (($response['ok'] ?? false) !== true) {
            return $this->buildOperationFailure('shiyan_layer4_add', '十堰4层策略新增', $response['message'] ?? '请求失败');
        }

        $parsed = $this->parseShiyanLayer4MutationHtml($response['body'], '新增成功', '新增失败');

        if (($parsed['success'] ?? false) !== true) {
            return $this->buildOperationFailure('shiyan_layer4_add', '十堰4层策略新增', $parsed['message'] ?? '新增失败', [
                'mode' => $mode,
            ]);
        }

        return $this->buildOperationSuccess('shiyan_layer4_add', '十堰4层策略新增', $parsed['message'] ?? '新增成功', [
            'mode' => $mode,
        ]);
    }

    public function deleteShiyanLayer4Rule(string $ip, string $ruleId): array
    {
        $primary = $this->submitShiyanLayer4DeleteRequest([
            'action' => 'delete',
            'ip' => trim($ip),
            'id' => trim($ruleId),
        ]);

        if (($primary['success'] ?? false) === true || ! $this->shouldRetryLayer4DeleteWithRuleId($primary['message'] ?? '')) {
            return $primary;
        }

        return $this->submitShiyanLayer4DeleteRequest([
            'action' => 'delete',
            'ip' => trim($ip),
            'rule_id' => trim($ruleId),
        ]);
    }

    private function performQuery(string $ip): array
    {
        $sources = [
            'shiyan_blackhole' => $this->queryShiyanBlackhole($ip),
            'shiyan_layer7' => $this->queryShiyanLayer7($ip),
            'shiyan_layer4' => $this->queryShiyanLayer4($ip),
            'shiyan_flow' => $this->queryShiyanFlow($ip),
            'ningbo_blackhole' => $this->queryNingboBlackhole($ip),
            'hongkong_blackhole' => $this->queryHongkongBlackhole($ip),
            'us1_traffic' => $this->buildUs1TrafficSource($ip),
        ];

        $details = [];
        $warnings = [];
        $availableSourceCount = 0;
        $blackholeAvailableCount = 0;
        $blackholedCount = 0;
        $hasUnavailableSource = false;

        foreach ($sources as $source) {
            if (($source['available'] ?? false) === true) {
                $availableSourceCount++;
            }

            if (($source['kind'] ?? '') === 'blackhole' && ($source['available'] ?? false) === true) {
                $blackholeAvailableCount++;
            }

            if (($source['blackholed'] ?? false) === true) {
                $blackholedCount++;
            }

            if (in_array($source['status'] ?? '', ['warning', 'unavailable'], true)) {
                $hasUnavailableSource = true;
                $warnings[] = [
                    'provider' => $source['label'],
                    'message' => $source['message'],
                ];
            }

            $details[] = [
                'provider' => $source['label'],
                'kind' => $source['kind'],
                'status' => $source['status'],
                'blackholed' => (bool) ($source['blackholed'] ?? false),
                'available' => (bool) ($source['available'] ?? false),
                'summary' => $source['summary'],
                'message' => $source['message'],
            ];
        }

        $overallStatus = 'normal';
        $overallMessage = '未发现黑洞记录';

        if ($blackholedCount > 0) {
            $overallStatus = 'blackholed';
            $overallMessage = sprintf('在 %d 个来源发现黑洞记录', $blackholedCount);
        } elseif ($blackholeAvailableCount === 0) {
            $overallStatus = 'unavailable';
            $overallMessage = '黑洞查询来源暂不可用';
        } elseif ($hasUnavailableSource) {
            $overallStatus = 'partial';
            $overallMessage = '部分来源查询成功，部分来源暂不可用';
        }

        return [
            'ip' => $ip,
            'ip_version' => str_contains($ip, ':') ? 6 : 4,
            'blackholed' => $blackholedCount > 0,
            'overall_status' => $overallStatus,
            'overall_message' => $overallMessage,
            'query_at' => now()->toDateTimeString(),
            'available_source_count' => $availableSourceCount,
            'total_source_count' => count($sources),
            'warnings' => $warnings,
            'details' => $details,
            'meta' => [
                'ningbo_base_url' => $this->ningboBaseUrl,
                'shiyan_base_url' => $this->shiyanBaseUrl,
                'public_base_url' => $this->publicBaseUrl,
            ],
            'sources' => $sources,
        ];
    }

    private function queryShiyanBlackhole(string $ip): array
    {
        $response = $this->requestJson(
            '十堰黑洞查询',
            $this->shiyanBaseUrl.'/blackhole/blackholeapi.php',
            ['ip' => $ip]
        );

        if (($response['ok'] ?? false) !== true) {
            return $this->buildUnavailableSource('shiyan_blackhole', '十堰黑洞记录', 'blackhole', $response['message'] ?? '查询失败');
        }

        $payload = $response['data'];
        $records = array_map(
            fn (array $record) => $this->normalizeTimestamps($record, ['start_time', 'end_time', 'create_time', 'update_time']),
            is_array($payload['records'] ?? null) ? $payload['records'] : []
        );
        $found = (bool) ($payload['found'] ?? false);
        $count = (int) ($payload['count'] ?? count($records));

        return [
            'key' => 'shiyan_blackhole',
            'label' => '十堰黑洞记录',
            'kind' => 'blackhole',
            'available' => true,
            'blackholed' => $found,
            'status' => $found ? 'blackholed' : 'normal',
            'summary' => $found ? sprintf('%d 条黑洞记录', $count) : '未发现黑洞记录',
            'message' => (string) ($payload['message'] ?? ($found ? '查询成功' : '该 IP 暂无黑洞记录')),
            'count' => $count,
            'records' => $records,
        ];
    }

    private function queryShiyanLayer7(string $ip): array
    {
        $response = $this->requestJson(
            '十堰 7 层策略',
            $this->shiyanBaseUrl.'/use/find.php',
            ['ip' => $ip]
        );

        if (($response['ok'] ?? false) !== true) {
            return $this->buildUnavailableSource('shiyan_layer7', '十堰7层策略', 'policy', $response['message'] ?? '查询失败');
        }

        $payload = $response['data'];
        if ((int) ($payload['status'] ?? 0) !== 200 || ! is_array($payload['data'] ?? null)) {
            return $this->buildWarningSource('shiyan_layer7', '十堰7层策略', 'policy', (string) ($payload['msg'] ?? '上游返回异常'));
        }

        $data = $payload['data'];
        $list = array_map(
            fn (array $item) => $this->normalizeTimestamps($item, ['create_time']),
            is_array($data['list'] ?? null) ? $data['list'] : []
        );
        $enabledList = array_values(array_filter($list, fn (array $item): bool => (int) ($item['status'] ?? 0) === 1));
        $enabledCount = (int) ($data['app_enabled_count'] ?? count($enabledList));
        $count = (int) ($data['count'] ?? count($list));

        return [
            'key' => 'shiyan_layer7',
            'label' => '十堰7层策略',
            'kind' => 'policy',
            'available' => true,
            'blackholed' => false,
            'status' => $enabledCount > 0 ? 'info' : 'normal',
            'summary' => sprintf('已启用 %d / %d 条策略', $enabledCount, $count),
            'message' => (string) ($payload['msg'] ?? '查询成功'),
            'count' => $count,
            'enabled_count' => $enabledCount,
            'app_enabled_count' => $enabledCount,
            'app_max' => (int) ($data['app_max'] ?? 0),
            'apply_rule_id' => $data['apply_rule_id'] ?? null,
            'pass_through' => (int) ($data['pass_through'] ?? 0),
            'enabled_list' => $enabledList,
            'list' => $list,
        ];
    }

    private function queryShiyanLayer4(string $ip): array
    {
        $response = $this->requestHtml(
            '十堰 4 层策略',
            $this->shiyanBaseUrl.'/through/through.php',
            'POST',
            ['action' => 'search', 'search_ip' => $ip]
        );

        if (($response['ok'] ?? false) !== true) {
            return $this->buildUnavailableSource('shiyan_layer4', '十堰4层策略', 'policy', $response['message'] ?? '查询失败');
        }

        $parsed = $this->parseShiyanLayer4Html($response['body']);
        $count = (int) ($parsed['count'] ?? count($parsed['records'] ?? []));

        return [
            'key' => 'shiyan_layer4',
            'label' => '十堰4层策略',
            'kind' => 'policy',
            'available' => true,
            'blackholed' => false,
            'status' => $count > 0 ? 'info' : 'normal',
            'summary' => $count > 0 ? sprintf('%d 条4层策略', $count) : '未配置4层策略',
            'message' => (string) ($parsed['message'] ?? '查询成功'),
            'count' => $count,
            'columns' => $parsed['columns'] ?? [],
            'list' => $parsed['records'] ?? [],
        ];
    }

    private function queryShiyanFlow(string $ip): array
    {
        $response = $this->requestJson(
            '十堰攻击流量图表',
            $this->shiyanBaseUrl.'/flow/flowapi.php',
            ['ip' => $ip]
        );

        if (($response['ok'] ?? false) !== true) {
            return $this->buildUnavailableSource('shiyan_flow', '十堰攻击流量图表', 'traffic', $response['message'] ?? '查询失败');
        }

        $payload = $response['data'];
        if ((int) ($payload['status'] ?? 0) !== 200 || ! is_array($payload['data'] ?? null)) {
            return $this->buildWarningSource('shiyan_flow', '十堰攻击流量图表', 'traffic', (string) ($payload['msg'] ?? '上游返回异常'));
        }

        $data = $payload['data'];
        $info = is_array($data['info'] ?? null)
            ? $this->normalizeTimestamps($data['info'], ['active_time'])
            : [];
        $samples = array_map(
            fn (array $item) => $this->normalizeFlowSample($item),
            is_array($data['list'] ?? null) ? $data['list'] : []
        );

        return [
            'key' => 'shiyan_flow',
            'label' => '十堰攻击流量图表',
            'kind' => 'traffic',
            'available' => true,
            'blackholed' => false,
            'status' => ! empty($samples) ? 'info' : 'normal',
            'summary' => ! empty($samples) ? sprintf('%d 个流量采样点', count($samples)) : '暂无攻击流量样本',
            'message' => (string) ($payload['msg'] ?? '查询成功'),
            'info' => $info,
            'samples' => $samples,
            'metrics' => $this->calculateFlowMetrics($samples),
        ];
    }

    private function queryNingboBlackhole(string $ip): array
    {
        $response = $this->requestJson(
            '宁波黑洞查询',
            $this->ningboBaseUrl.'/api/blackhole.php',
            ['ip' => $ip]
        );

        if (($response['ok'] ?? false) !== true) {
            return $this->buildUnavailableSource('ningbo_blackhole', '宁波黑洞查询', 'blackhole', $response['message'] ?? '查询失败');
        }

        $payload = $response['data'];
        $code = (int) ($payload['code'] ?? 0);
        $records = array_map(
            fn (array $record) => $this->normalizeTimestamps($record, ['created_at', 'unclose_time']),
            is_array($payload['data'] ?? null) ? $payload['data'] : []
        );

        if ($code !== 200) {
            return [
                'key' => 'ningbo_blackhole',
                'label' => '宁波黑洞查询',
                'kind' => 'blackhole',
                'available' => false,
                'blackholed' => false,
                'status' => 'warning',
                'summary' => (string) ($payload['message'] ?? '查询失败'),
                'message' => (string) ($payload['message'] ?? '查询失败'),
                'count' => 0,
                'records' => [],
                'business_code' => $code,
            ];
        }

        $count = count($records);

        return [
            'key' => 'ningbo_blackhole',
            'label' => '宁波黑洞查询',
            'kind' => 'blackhole',
            'available' => true,
            'blackholed' => $count > 0,
            'status' => $count > 0 ? 'blackholed' : 'normal',
            'summary' => $count > 0 ? sprintf('%d 条宁波黑洞记录', $count) : '未发现宁波黑洞记录',
            'message' => (string) ($payload['message'] ?? '查询成功'),
            'count' => $count,
            'records' => $records,
            'business_code' => $code,
        ];
    }

    private function queryHongkongBlackhole(string $ip): array
    {
        $response = $this->requestJson('香港黑洞查询', $this->hongkongApiUrl, ['ip' => $ip]);

        if (($response['ok'] ?? false) !== true) {
            return $this->buildUnavailableSource('hongkong_blackhole', '香港黑洞', 'blackhole', $response['message'] ?? '查询失败');
        }

        $payload = $response['data'] ?? [];
        $statusCode = (int) ($payload['status'] ?? 0);
        $message = (string) ($payload['msg'] ?? '查询成功');

        if ($statusCode !== 200 || empty($payload['data'])) {
            return [
                'key' => 'hongkong_blackhole',
                'label' => '香港黑洞',
                'kind' => 'blackhole',
                'available' => $statusCode === 200,
                'blackholed' => false,
                'status' => $statusCode === 200 ? 'normal' : 'warning',
                'summary' => $statusCode === 200 ? '未发现香港黑洞记录' : '香港黑洞查询异常',
                'message' => $message,
                'total' => 0,
                'matched' => 0,
                'displayed' => 0,
                'columns' => [],
                'records' => [],
            ];
        }

        $tableHtml = (string) ($payload['table'] ?? '');
        $table = $tableHtml !== '' ? $this->extractBestHtmlTable($tableHtml) : ['columns' => [], 'records' => []];
        $records = $table['records'];
        $count = count($records);

        return [
            'key' => 'hongkong_blackhole',
            'label' => '香港黑洞',
            'kind' => 'blackhole',
            'available' => true,
            'blackholed' => $count > 0,
            'status' => $count > 0 ? 'blackholed' : 'normal',
            'summary' => $count > 0 ? sprintf('命中 %d 条香港黑洞记录', $count) : '未发现香港黑洞记录',
            'message' => $message,
            'total' => $count,
            'matched' => $count,
            'displayed' => $count,
            'columns' => $table['columns'],
            'records' => $records,
            'flow' => is_array($payload['flow'] ?? null) ? $payload['flow'] : null,
        ];
    }

    private function buildUs1TrafficSource(string $ip): array
    {
        $query = http_build_query([
            'logo' => 0,
            'ip' => $ip,
        ]);

        return [
            'key' => 'us1_traffic',
            'label' => '美国黑洞',
            'kind' => 'image',
            'available' => true,
            'blackholed' => false,
            'status' => 'info',
            'summary' => '已生成美国黑洞流量图地址',
            'message' => '超出 20G 为封禁状态，可通过流量图判断',
            'query_url' => $this->publicBaseUrl.'/US1.php',
            'image_url' => $this->us1TrafficBaseUrl.'?'.$query,
        ];
    }

    private function requestJson(string $label, string $url, array $payload = []): array
    {
        try {
            $response = $this->baseRequest()->get($url, $payload);
        } catch (\Throwable $exception) {
            Log::warning('Blackhole upstream JSON request failed', [
                'label' => $label,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => $label.' 请求失败',
            ];
        }

        if (! $response->ok()) {
            return [
                'ok' => false,
                'message' => sprintf('%s 请求失败（HTTP %d）', $label, $response->status()),
            ];
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            return [
                'ok' => false,
                'message' => $label.' 返回非 JSON',
            ];
        }

        return [
            'ok' => true,
            'data' => $decoded,
        ];
    }

    private function requestHtml(string $label, string $url, string $method, array $payload = []): array
    {
        try {
            $request = $this->baseRequest()->accept('text/html,application/xhtml+xml');
            $response = strtoupper($method) === 'POST'
                ? $request->asForm()->post($url, $payload)
                : $request->get($url, $payload);
        } catch (\Throwable $exception) {
            Log::warning('Blackhole upstream HTML request failed', [
                'label' => $label,
                'url' => $url,
                'method' => strtoupper($method),
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => $label.' 请求失败',
            ];
        }

        if (! $response->ok()) {
            return [
                'ok' => false,
                'message' => sprintf('%s 请求失败（HTTP %d）', $label, $response->status()),
            ];
        }

        return [
            'ok' => true,
            'body' => $response->body(),
        ];
    }

    private function submitShiyanLayer4DeleteRequest(array $payload): array
    {
        $response = $this->requestHtml(
            '十堰4层策略删除',
            $this->shiyanBaseUrl.'/through/through.php',
            'POST',
            $payload
        );

        if (($response['ok'] ?? false) !== true) {
            return $this->buildOperationFailure('shiyan_layer4_delete', '十堰4层策略删除', $response['message'] ?? '请求失败', [
                'rule_id' => $payload['id'] ?? $payload['rule_id'] ?? null,
            ]);
        }

        $parsed = $this->parseShiyanLayer4MutationHtml($response['body'], '删除成功', '删除失败');

        if (($parsed['success'] ?? false) !== true) {
            return $this->buildOperationFailure('shiyan_layer4_delete', '十堰4层策略删除', $parsed['message'] ?? '删除失败', [
                'rule_id' => $payload['id'] ?? $payload['rule_id'] ?? null,
            ]);
        }

        return $this->buildOperationSuccess('shiyan_layer4_delete', '十堰4层策略删除', $parsed['message'] ?? '删除成功', [
            'rule_id' => $payload['id'] ?? $payload['rule_id'] ?? null,
        ]);
    }

    private function baseRequest(): PendingRequest
    {
        $request = Http::timeout($this->timeout)
            ->retry(1, 200)
            ->withOptions(['verify' => $this->verifyOption]);

        if ($this->userAgent !== '') {
            $request = $request->withUserAgent($this->userAgent);
        }

        return $request;
    }

    private function parseShiyanLayer4Html(string $html): array
    {
        $text = preg_replace('/\s+/u', ' ', trim(strip_tags($html))) ?? '';
        $message = str_contains($text, '查询成功') ? '查询成功' : '查询完成';
        $count = 0;

        if (preg_match('/共\s*(\d+)\s*条记录/u', $text, $matches) === 1) {
            $count = (int) $matches[1];
        }

        $table = $this->extractBestHtmlTable($html);

        return [
            'message' => $message,
            'count' => $count,
            'columns' => $table['columns'],
            'records' => $table['records'],
        ];
    }

    private function parseShiyanLayer4MutationHtml(string $html, string $successFallback, string $failureFallback): array
    {
        $feedback = $this->extractHtmlFeedback($html);
        $message = trim((string) ($feedback['message'] ?? ''));
        $className = strtolower((string) ($feedback['class'] ?? ''));

        $isSuccess = $className !== '' && str_contains($className, 'ok');
        $isFailure = $className !== '' && str_contains($className, 'error');

        if (! $isSuccess && ! $isFailure && $message !== '') {
            if (preg_match('/成功/u', $message) === 1) {
                $isSuccess = true;
            }

            if (preg_match('/失败|错误|缺少/u', $message) === 1) {
                $isFailure = true;
            }
        }

        if ($message === '') {
            $text = preg_replace('/\s+/u', ' ', trim(strip_tags($html))) ?? '';
            if (preg_match('/(新增成功|删除成功|操作成功|查询成功|新增失败[^。]*|删除失败[^。]*|操作失败[^。]*|请求失败[^。]*|缺少[^。]*参数[^。]*)/u', $text, $matches) === 1) {
                $message = trim($matches[1]);
            }
        }

        if ($message === '') {
            $message = $isSuccess ? $successFallback : $failureFallback;
        }

        return [
            'success' => $isSuccess && ! $isFailure,
            'message' => $message,
        ];
    }

    private function parseSharedHtmlResponse(string $html, string $ip): array
    {
        $text = preg_replace('/\s+/u', ' ', trim(strip_tags($html))) ?? '';
        $message = '';

        if (preg_match('/(未找到\s*IP\s*".*?"\s*的黑洞记录|暂无黑洞记录)/u', $text, $matches) === 1) {
            $message = trim($matches[1]);
        }

        $total = $this->extractLabelCount($text, '总记录数');
        $matched = $this->extractLabelCount($text, '查询结果');
        $displayed = $this->extractLabelCount($text, '全部显示');
        $table = $this->extractBestHtmlTable($html);
        $records = $table['records'];
        $columns = $table['columns'];
        $found = ($matched > 0) || ! empty($records);

        if ($message === '') {
            $message = $found ? '查询成功' : sprintf('未找到 IP "%s" 的黑洞记录', $ip);
        }

        return [
            'found' => $found,
            'message' => $message,
            'total' => $total,
            'matched' => $matched,
            'displayed' => $displayed,
            'columns' => $columns,
            'records' => $records,
        ];
    }

    private function extractLabelCount(string $text, string $label): int
    {
        if (preg_match('/'.preg_quote($label, '/').'\s*[:：]?\s*(\d+)/u', $text, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function extractBestHtmlTable(string $html): array
    {
        $default = [
            'columns' => [],
            'records' => [],
        ];

        if (! class_exists(DOMDocument::class)) {
            return $default;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $encodedHtml = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')
            : $html;

        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$encodedHtml, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if ($loaded === false) {
            return $default;
        }

        $best = $default;

        foreach ($dom->getElementsByTagName('table') as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }

            $parsed = $this->parseHtmlTable($table);
            if (count($parsed['records']) > count($best['records'])) {
                $best = $parsed;
            }
        }

        return $best;
    }

    private function extractHtmlFeedback(string $html): array
    {
        $default = [
            'class' => '',
            'message' => '',
        ];

        if (! class_exists(DOMDocument::class)) {
            return $default;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $encodedHtml = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')
            : $html;

        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$encodedHtml, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if ($loaded === false) {
            return $default;
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $className = trim((string) $element->getAttribute('class'));
            if ($className === '') {
                continue;
            }

            $normalizedClass = ' '.strtolower($className).' ';
            if (! str_contains($normalizedClass, ' feedback ') && ! str_contains($normalizedClass, ' status ')) {
                continue;
            }

            $message = $this->normalizeTextContent($element->textContent);
            if ($message === '') {
                continue;
            }

            return [
                'class' => $className,
                'message' => $message,
            ];
        }

        return $default;
    }

    private function parseHtmlTable(DOMElement $table): array
    {
        $columns = [];
        $records = [];

        foreach ($table->getElementsByTagName('tr') as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $headerCells = $row->getElementsByTagName('th');
            if ($headerCells->length > 0 && $columns === []) {
                $columns = $this->extractCellTexts($headerCells);

                continue;
            }

            $cells = $row->getElementsByTagName('td');
            if ($cells->length === 0) {
                continue;
            }

            $values = $this->extractCellTexts($cells);
            if ($values === [] || count(array_filter($values, fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            if (count($values) === 1 && preg_match('/未查询到规则|暂无/u', $values[0]) === 1) {
                continue;
            }

            if ($columns === []) {
                $columns = array_map(
                    fn (int $index): string => '列'.($index + 1),
                    range(0, count($values) - 1)
                );
            }

            $record = [];
            foreach ($values as $index => $value) {
                $column = $columns[$index] ?? ('列'.($index + 1));
                $record[$column] = $value;
            }

            $records[] = $record;
        }

        return [
            'columns' => $columns,
            'records' => $records,
        ];
    }

    private function extractCellTexts(iterable $cells): array
    {
        $values = [];

        foreach ($cells as $cell) {
            if (! $cell instanceof DOMElement) {
                continue;
            }

            $values[] = $this->normalizeTextContent($cell->textContent);
        }

        return $values;
    }

    private function normalizeTextContent(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return $value;
    }

    private function normalizeFlowSample(array $sample): array
    {
        $sample = $this->normalizeTimestamps($sample, ['time', 'created_at']);

        $inSize = $this->toFloat($sample['in_size'] ?? 0);
        $inDropSize = $this->toFloat($sample['in_drop_size'] ?? 0);
        $afterFilterSize = max($inSize - $inDropSize, 0);

        $sample['time_label'] = $this->buildFlowTimeLabel($sample);
        $sample['before_mbps'] = $this->bytesPerSecondToMbps($inSize);
        $sample['after_mbps'] = $this->bytesPerSecondToMbps($afterFilterSize);

        return $sample;
    }

    private function buildFlowTimeLabel(array $sample): string
    {
        $hour = trim((string) ($sample['hour'] ?? ''));
        $minute = trim((string) ($sample['minute'] ?? ''));

        if ($hour !== '' || $minute !== '') {
            return sprintf('%02d:%02d', (int) $hour, (int) $minute);
        }

        return trim((string) ($sample['time'] ?? ''));
    }

    private function calculateFlowMetrics(array $samples): array
    {
        if ($samples === []) {
            return [
                'sample_count' => 0,
                'avg_before_mbps' => 0.0,
                'avg_after_mbps' => 0.0,
                'peak_before_mbps' => 0.0,
                'peak_after_mbps' => 0.0,
            ];
        }

        $beforeValues = array_map(fn (array $sample): float => (float) ($sample['before_mbps'] ?? 0), $samples);
        $afterValues = array_map(fn (array $sample): float => (float) ($sample['after_mbps'] ?? 0), $samples);

        return [
            'sample_count' => count($samples),
            'avg_before_mbps' => round(array_sum($beforeValues) / count($beforeValues), 2),
            'avg_after_mbps' => round(array_sum($afterValues) / count($afterValues), 2),
            'peak_before_mbps' => round(max($beforeValues), 2),
            'peak_after_mbps' => round(max($afterValues), 2),
        ];
    }

    private function bytesPerSecondToMbps(float $bytesPerSecond): float
    {
        return round(($bytesPerSecond * 8) / 1000000, 2);
    }

    private function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function normalizeTimestamps(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_numeric($value)) {
                $timestamp = (int) $value;
                if ($timestamp > 0) {
                    $payload[$key] = date('Y-m-d H:i:s', $timestamp);
                }
            }
        }

        return $payload;
    }

    private function shouldRetryLayer4DeleteWithRuleId(string $message): bool
    {
        return preg_match('/rule[_\s-]?id/u', $message) === 1;
    }

    private function buildOperationSuccess(string $key, string $label, string $message, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'success' => true,
            'message' => $message,
        ], $extra);
    }

    private function buildOperationFailure(string $key, string $label, string $message, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'success' => false,
            'message' => $message,
        ], $extra);
    }

    private function buildUnavailableSource(string $key, string $label, string $kind, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'kind' => $kind,
            'available' => false,
            'blackholed' => false,
            'status' => 'unavailable',
            'summary' => '暂不可用',
            'message' => $message,
        ];
    }

    private function buildWarningSource(string $key, string $label, string $kind, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'kind' => $kind,
            'available' => false,
            'blackholed' => false,
            'status' => 'warning',
            'summary' => '上游返回异常',
            'message' => $message,
        ];
    }
}
