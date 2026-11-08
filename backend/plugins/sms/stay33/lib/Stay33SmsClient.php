<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Sms\Stay33\Lib;

use App\Support\PhoneMasker;
use App\Support\SmsTemplateCatalog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MC云短信客户端，对接 MCloud v10 开放 API。
 *
 * 协议要点（对齐上游 Mcloudsmsv10 v2.0 对接包）：
 * - 端点 `https://api.freescdn.com/api/v1/sms/send`，GET + query string；
 * - 鉴权 `uid` + `api_key`，其中 uid 兼容旧配置的 user_id / username；
 * - 号码参数 `phones`，通道参数 `channel_id`，正文 `content` 需自带【签名】；
 * - 响应 `{"code":0,"message":"...","data":{...}}`，code=0 表示成功。
 */
class Stay33SmsClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * 查询运营商模板列表（GET /sms/templates）。
     *
     * @return array{success: bool, message?: string, templates?: array<int, array<string, mixed>>}
     */
    public function queryTemplates(): array
    {
        if ($this->authHeaders() === []) {
            return ['success' => false, 'message' => '短信接口配置不完整'];
        }

        $result = $this->callApi($this->apiUrl('templates'), 'get');
        if (! is_array($result)) {
            return ['success' => false, 'message' => '短信接口请求失败，请稍后重试'];
        }

        if ((int) ($result['code'] ?? -1) !== 0) {
            return ['success' => false, 'message' => $this->resolveFailureMessage($result['message'] ?? '')];
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $list = is_array($data['list'] ?? null) ? $data['list'] : [];
        $templates = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }

            $templates[] = [
                'provider_template_id' => (string) ($item['id'] ?? ''),
                'provider_sign' => '',
                'content' => (string) ($item['content'] ?? ''),
                'variables' => $this->extractVariables((string) ($item['content'] ?? '')),
                'status' => $this->normalizeTemplateStatus($item['status'] ?? null),
                'reject_reason' => (string) ($item['reason'] ?? ''),
            ];
        }

        return ['success' => true, 'templates' => $templates];
    }

    /**
     * 提交或修改运营商模板（POST /sms/templates；带 id 表示修改）。
     *
     * @return array{success: bool, message?: string, provider_template_id?: string, status?: string}
     */
    public function submitTemplate(?string $providerTemplateId, string $name, string $scene, string $content, string $remark = '', string $sampleImage = ''): array
    {
        if ($this->authHeaders() === []) {
            return ['success' => false, 'message' => '短信接口配置不完整'];
        }

        if (trim($name) === '' || trim($content) === '') {
            return ['success' => false, 'message' => '模板名称与内容不能为空'];
        }

        $payload = [
            'name' => trim($name),
            'scene' => $this->normalizeTemplateScene($scene, $content),
            'content' => trim($content),
        ];
        if ($providerTemplateId !== null && $providerTemplateId !== '') {
            $payload['id'] = (int) $providerTemplateId;
        }
        if (trim($remark) !== '') {
            $payload['remark'] = trim($remark);
        }
        if (trim($sampleImage) !== '') {
            $payload['sample_image'] = trim($sampleImage);
        }

        $result = $this->callApi($this->apiUrl('templates'), 'post', $payload);
        if (! is_array($result)) {
            return ['success' => false, 'message' => '短信接口请求失败，请稍后重试'];
        }

        $code = (int) ($result['code'] ?? -1);
        if ($code !== 0) {
            return ['success' => false, 'message' => $this->resolveFailureMessage($result['message'] ?? '')];
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        return [
            'success' => true,
            'provider_template_id' => (string) ($data['id'] ?? $providerTemplateId ?? ''),
            'status' => $this->normalizeTemplateStatus($data['status'] ?? null),
            'content' => trim($content),
            'variables' => $this->extractVariables(trim($content)),
        ];
    }

    /**
     * 查询单个模板的审核状态（GET /sms/templates 后按 id 过滤）。
     *
     * @return array{success: bool, message?: string, status?: string, reject_reason?: string}
     */
    public function queryTemplateStatus(string $providerTemplateId): array
    {
        $result = $this->queryTemplates();
        if ($result['success'] !== true) {
            return ['success' => false, 'message' => $result['message'] ?? '模板状态回查失败'];
        }

        $target = trim($providerTemplateId);
        foreach ($result['templates'] ?? [] as $template) {
            if (($template['provider_template_id'] ?? '') !== $target) {
                continue;
            }

            return [
                'success' => true,
                'status' => (string) ($template['status'] ?? 'pending'),
                'reject_reason' => (string) ($template['reject_reason'] ?? ''),
            ];
        }

        // 运营商侧已不存在该模板：与 pull 的删除探测语义保持一致
        return ['success' => true, 'status' => 'deleted', 'reject_reason' => ''];
    }

    /**
     * 查询签名列表（GET /sms/signatures）。
     *
     * @return array{success: bool, message?: string, signs?: array<int, array<string, mixed>>}
     */
    public function querySignatures(): array
    {
        if ($this->authHeaders() === []) {
            return ['success' => false, 'message' => '短信接口配置不完整'];
        }

        $result = $this->callApi($this->apiUrl('signatures'), 'get');
        if (! is_array($result)) {
            return ['success' => false, 'message' => '短信接口请求失败，请稍后重试'];
        }

        if ((int) ($result['code'] ?? -1) !== 0) {
            return ['success' => false, 'message' => $this->resolveFailureMessage($result['message'] ?? '')];
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $list = is_array($data['list'] ?? null) ? $data['list'] : [];
        $signs = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }

            $signs[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'status' => $this->normalizeTemplateStatus($item['status'] ?? null),
                'status_text' => (string) ($item['status_text'] ?? ''),
                'carrier_ok' => (bool) ($item['carrier_ok'] ?? false),
            ];
        }

        return ['success' => true, 'signs' => $signs];
    }

    /**
     * 通用 GET/POST 调用：复用主备端点、鉴权头与超时。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|false
     */
    private function callApi(string $url, string $method, array $payload = []): array|false
    {
        $request = $this->http()->acceptJson()->timeout($this->timeout());
        foreach ($this->authHeaders() as $name => $value) {
            $request = $request->withHeaders([$name => $value]);
        }

        try {
            $response = $method === 'post' ? $request->post($url, $payload) : $request->get($url);
        } catch (\Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        $json = $response->json();

        return is_array($json) ? $json : false;
    }

    /**
     * 由发送端点推导同域的资源地址：…/api/v1/sms/send → …/api/v1/sms/{resource}。
     */
    private function apiUrl(string $resource): string
    {
        $endpoint = rtrim($this->configString('api_endpoint', 'https://api.freescdn.com/api/v1/sms/send'), '/');
        $base = preg_replace('#/send$#', '', $endpoint) ?? $endpoint;

        return $base.'/'.trim($resource, '/');
    }

    /**
     * 官方模板状态：0 待审核 / 1 已通过 / 2 已拒绝。
     */
    private function normalizeTemplateStatus(mixed $status): string
    {
        return match ((string) (is_scalar($status) ? $status : '')) {
            '1' => 'approved',
            '2' => 'rejected',
            '3' => 'deleted',
            default => 'pending',
        };
    }

    /**
     * 模板场景：验证码类内容必须走 verify，否则按通知类提交。
     */
    private function normalizeTemplateScene(string $scene, string $content): string
    {
        $scene = strtolower(trim($scene));
        if (in_array($scene, ['verify', 'notify', 'marketing'], true)) {
            return $scene;
        }

        return str_contains($content, '验证码') ? 'verify' : 'notify';
    }

    /**
     * 从模板正文提取变量名（${var} / {{var}} / {var}）。
     *
     * @return array<int, string>
     */
    private function extractVariables(string $content): array
    {
        preg_match_all('/\$\{([a-zA-Z0-9_]+)\}|\{\{\s*([a-zA-Z0-9_]+)\s*\}\}|(?<![\{@$])\{([a-zA-Z0-9_]+)\}(?![}])/u', $content, $matches);

        /** @var array<int, string> $names */
        $names = array_merge($matches[1], $matches[2], $matches[3]);
        $names = array_map('strval', $names);

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    /**
     * 发送验证码短信：文案渲染后走公共请求核心，成功回执附带验证码上下文。
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendVerifyCode(string $phone, string $code, array $options = []): array
    {
        $phone = trim($phone);
        $code = trim($code);

        if ($phone === '' || $code === '') {
            return $this->failure('短信发送参数不完整');
        }

        $signName = $this->optionString($options, 'sign_name', $this->configString('sign_name'));
        $templateCode = $this->optionString($options, 'template_code', $this->configString('template_code', SmsTemplateCatalog::TEMPLATE_VERIFY_CODE));
        if ($signName === '') {
            return $this->failure('短信接口配置不完整');
        }

        $channel = $this->resolveChannel($options);
        $content = $this->stripCodeMarkers($this->renderContent($code, $options));

        $outcome = $this->performSend($phone, $content, $this->normalizeSignName($signName), $channel);
        if (isset($outcome['failure'])) {
            return $outcome['failure'];
        }

        /** @var array<string, mixed> $result */
        $result = $outcome['result'];

        return $this->successPayload($result, $templateCode, [
            'code' => $code,
            'sign_name' => $signName,
            'channel' => $channel,
        ]);
    }

    /**
     * 发送模板正文短信：签名处理后走公共请求核心，成功回执附带回显正文。
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendMessage(string $phone, string $templateCode, string $content, array $options = []): array
    {
        $phone = trim($phone);
        $templateCode = trim($templateCode);
        $content = trim($content);

        if ($phone === '' || $content === '') {
            return $this->failure('短信发送参数不完整');
        }

        $signName = $this->optionString($options, 'sign_name', $this->configString('sign_name'));
        if ($signName === '') {
            return $this->failure('短信接口配置不完整');
        }

        $channel = $this->resolveChannel($options);
        $signedContent = $this->stripCodeMarkers($content);

        $outcome = $this->performSend($phone, $signedContent, $this->normalizeSignName($signName), $channel);
        if (isset($outcome['failure'])) {
            return $outcome['failure'];
        }

        /** @var array<string, mixed> $result */
        $result = $outcome['result'];

        return $this->successPayload($result, $templateCode, [
            'content' => $signedContent,
            'sign_name' => $signName,
            'channel' => $channel,
        ]);
    }

    /**
     * 公共请求核心：凭据校验 → 拼装报文 → 请求 → 业务码与投递结果判定 → 失败告警。
     * 成功时返回上游原始响应（result），任一环节失败返回统一 failure 结构。
     *
     * @return array{result?: array<string, mixed>, failure?: array<string, mixed>}
     */
    private function performSend(string $phone, string $content, string $signature, string $channel): array
    {
        if ($this->authHeaders() === []) {
            return ['failure' => $this->failure('短信接口配置不完整')];
        }

        $params = [
            'phones' => $this->normalizePhone($phone),
            'content' => $content,
        ];

        // 官方签名参数：不传则要求 content 自带【签名】。插件统一走该参数，
        // 避免在本地猜测签名位置与是否重复。
        if ($signature !== '') {
            $params['signature'] = $signature;
        }

        // channel_id 官方声明为 int，不传由上游选第一个可用通道；
        // 空值不能作为参数发出，否则会被当成通道 0 或触发参数错误。
        if ($channel !== '') {
            $channelId = (int) $channel;
            if ($channelId > 0) {
                $params['channel_id'] = $channelId;
            }
        }

        $result = $this->request($params, $phone);
        if (! is_array($result)) {
            return ['failure' => $this->failure('短信接口请求失败，请稍后重试')];
        }

        // 官方错误码：code=0 表示「接口调用成功」，其余为业务失败。
        if ((int) ($result['code'] ?? -1) !== 0) {
            $message = $this->resolveFailureMessage($result['message'] ?? ($result['msg'] ?? ''));
            Log::warning('[短信] MC云短信发送失败', [
                'code' => $this->stringifyCode($result['code'] ?? -1),
                'message' => $message,
                'phone' => PhoneMasker::mask($phone),
            ]);

            return ['failure' => $this->failure($message, $result)];
        }

        // code=0 只代表接口调用成功；data.success 才是个体送达数量。
        // 批量下发全部被拦截时仍是 code=0，不能据此记为发送成功。
        $deliveryFailure = $this->resolveDeliveryFailure($result);
        if ($deliveryFailure !== null) {
            Log::warning('[短信] MC云短信投递失败', [
                'code' => 0,
                'message' => $deliveryFailure,
                'phone' => PhoneMasker::mask($phone),
                'delivery' => $this->deliverySummary($result),
            ]);

            return ['failure' => $this->failure($deliveryFailure, $result)];
        }

        return ['result' => $result];
    }

    /**
     * 从官方响应 data 中判定个体投递结果。
     *
     * 官方文档：data { total, success, failed, blocked[], blocked_count, consumed }。
     * - total 为 0 或 success 为 0：本批次没有任何号码被受理；
     * - blocked 非空 / blocked_count > 0：部分号码被风控拦截，按失败上报；
     * - failed > 0：部分号码发送失败，按失败上报（避免把部分失败记成完全成功）。
     *
     * 缺失这些字段时不做判定，保持对精简响应（仅 code/message）的兼容。
     *
     * @param  array<string, mixed>  $result
     */
    private function resolveDeliveryFailure(array $result): ?string
    {
        $data = is_array($result['data'] ?? null) ? $result['data'] : null;
        if ($data === null || $data === []) {
            return null;
        }

        $summary = $this->deliverySummary($result);
        $total = $summary['total'];
        $success = $summary['success'];
        $failed = $summary['failed'];
        $blocked = $summary['blocked'];

        if ($total === null && $success === null && $failed === null && $blocked === null) {
            return null;
        }

        $total ??= 0;
        $success ??= 0;
        $failed ??= 0;
        $blocked ??= 0;

        if ($success === 0) {
            $reason = trim((string) ($data['error'] ?? ''));

            return $reason !== '' ? mb_substr($reason, 0, 120) : '短信发送失败，请稍后重试';
        }

        if ($blocked > 0 || $failed > 0) {
            $parts = [];
            if ($failed > 0) {
                $parts[] = '失败 '.$failed.' 条';
            }
            if ($blocked > 0) {
                $parts[] = '被拦截 '.$blocked.' 条';
            }

            return '短信部分投递失败（成功 '.$success.'/'.$total.'，'.implode('，', $parts).'）';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{total: int|null, success: int|null, failed: int|null, blocked: int|null}
     */
    private function deliverySummary(array $result): array
    {
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        return [
            'total' => $this->nullableInt($data['total'] ?? null),
            'success' => $this->nullableInt($data['success'] ?? null),
            'failed' => $this->nullableInt($data['failed'] ?? null),
            'blocked' => $this->nullableInt($data['blocked_count'] ?? (is_array($data['blocked'] ?? null) ? count($data['blocked']) : null)),
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function stringifyCode(mixed $code): string
    {
        return is_scalar($code) ? (string) $code : '-1';
    }

    /**
     * 上游成功响应的回执组装：提取回执号，并回显模板编码与模板参数。
     *
     * v10 成功响应只有 data 对象、没有旧协议的 success_data[].sms_code，
     * 回执号按常见字段依次探测，全部缺失时保持 null，不伪造。
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $templateParams
     * @return array<string, mixed>
     */
    private function successPayload(array $result, string $templateCode, array $templateParams): array
    {
        return [
            'success' => true,
            'status' => 'success',
            'request_id' => $this->resolveRequestId($result),
            'template_code' => $templateCode,
            'template_params' => $templateParams,
            'raw' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function resolveRequestId(array $result): ?string
    {
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        foreach (['id', 'sms_id', 'batch_id', 'message_id', 'task_id', 'request_id'] as $key) {
            $value = $data[$key] ?? $result[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|int>  $params
     * @return array<string, mixed>|false
     */
    private function request(array $params, string $phone): array|false
    {
        foreach ($this->endpoints() as $endpoint) {
            try {
                $request = $this->http()
                    ->acceptJson()
                    ->timeout($this->timeout());

                // 官方鉴权：API Key 走 X-Api-Key + X-Uid，AccessKey 走
                // X-Access-Key + X-Access-Secret。凭据放 header，不进 URL query，
                // 避免密钥出现在上游访问日志、代理/CDN 日志与异常上报的 URL 里。
                foreach ($this->authHeaders() as $name => $value) {
                    $request = $request->withHeaders([$name => $value]);
                }

                $response = $request->get($endpoint, $params);
            } catch (ConnectionException $exception) {
                Log::warning('[短信] MC云短信接口连接失败', [
                    'endpoint' => $endpoint,
                    'phone' => PhoneMasker::mask($phone),
                    'message' => $exception->getMessage(),
                ]);

                continue;
            } catch (\Throwable $exception) {
                Log::warning('[短信] MC云短信接口请求异常', [
                    'endpoint' => $endpoint,
                    'phone' => PhoneMasker::mask($phone),
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            if (! $response->successful()) {
                Log::warning('[短信] MC云短信接口 HTTP 状态异常', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'phone' => PhoneMasker::mask($phone),
                ]);

                continue;
            }

            $json = $response->json();

            return is_array($json) ? $json : false;
        }

        return false;
    }

    /**
     * 主备端点：默认只启用 v10 主端点，管理员可用配置覆盖或补备端点。
     *
     * @return array<int, string>
     */
    private function endpoints(): array
    {
        $endpoints = [
            $this->configString('api_endpoint', 'https://api.freescdn.com/api/v1/sms/send'),
            $this->configString('backup_endpoint'),
        ];

        return array_values(array_unique(array_filter($endpoints, static fn (string $endpoint): bool => $endpoint !== '')));
    }

    /**
     * 鉴权 UID：新版配置键为 uid，兼容旧配置的 user_id / username。
     */
    private function resolveUid(): string
    {
        foreach (['uid', 'user_id', 'username'] as $key) {
            $value = $this->configString($key);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * API Key：新版配置键为 api_key，兼容旧配置的 key。
     */
    private function resolveApiKey(): string
    {
        return $this->configString('api_key', $this->configString('key'));
    }

    /**
     * 按官方文档组装鉴权头：X-Api-Key + X-Uid。
     *
     * 凭据放 header 而非 URL query，避免密钥进入上游访问日志、代理/CDN
     * 日志与异常上报的 URL 字段。两者任一缺失时返回空数组，由调用方在
     * {@see performSend()} 里提前判失败，不会发出未鉴权请求。
     *
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        $uid = $this->resolveUid();
        $apiKey = $this->resolveApiKey();
        if ($uid === '' || $apiKey === '') {
            return [];
        }

        return [
            'X-Api-Key' => $apiKey,
            'X-Uid' => $uid,
        ];
    }

    /**
     * 号码归一：只做「平台补的 +86 前缀」这一件确定的事。
     *
     * 平台 `MessageTransport` 会给大陆号码补 +86 再交给驱动，而官方示例的
     * phones 是纯数字大陆号，因此这里剥掉大陆号码的 +86/0086/86 前缀与分隔符。
     * 非大陆号码（+852、+44 等）原样保留：官方文档只要求「换行或逗号分隔」，
     * 没有声明可以去掉国际前缀，本地擅自改写会改变号码语义。
     */
    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        $compact = preg_replace('/[\s\-()]/u', '', $phone) ?? $phone;

        foreach (['+86', '0086', '86'] as $prefix) {
            if (! str_starts_with($compact, $prefix)) {
                continue;
            }

            $rest = substr($compact, strlen($prefix));
            if (preg_match('/^1[3-9]\d{9}$/', $rest) === 1) {
                return $rest;
            }
        }

        // 大陆号码未带国家码时只清理分隔符；其它情况保留原输入
        return preg_match('/^1[3-9]\d{9}$/', $compact) === 1 ? $compact : $phone;
    }

    /**
     * 渲染验证码正文。签名不在这里拼接——官方提供 signature 参数，
     * 由 {@see performSend()} 单独下发，正文不会再被本地加【】。
     *
     * @param  array<string, mixed>  $options
     */
    private function renderContent(string $code, array $options): string
    {
        $template = $this->optionString(
            $options,
            'template_content',
            $this->configString('template_content', '您的验证码是：{{code}}。')
        );

        $replacements = [
            'code' => $code,
            'min' => '5',
            'expire_minutes' => '5',
        ];

        foreach ($replacements as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
            $template = str_replace('{{ '.$key.' }}', $value, $template);
            $template = str_replace('{'.$key.'}', $value, $template);
        }

        return $template;
    }

    private function stripCodeMarkers(string $content): string
    {
        $content = preg_replace('/\{codes\}(.*?)\{\/codes\}/su', '$1', $content) ?? $content;

        return str_replace(['{codes}', '{/codes}'], '', $content);
    }

    private function normalizeSignName(string $signName): string
    {
        return trim(str_replace(['【', '】'], '', $signName));
    }

    /**
     * v10 的发信通道 ID 原样透传；留空表示由上游按账号默认通道发送。
     *
     * @param  array<string, mixed>  $options
     */
    private function resolveChannel(array $options): string
    {
        return $this->optionString($options, 'channel', $this->configString('channel'));
    }

    private function timeout(): int
    {
        $timeout = (int) ($this->config['timeout_seconds'] ?? 10);

        return max(1, min(60, $timeout));
    }

    private function http(): PendingRequest
    {
        return Http::withOptions([
            'verify' => $this->sslVerifyOption(),
        ]);
    }

    private function sslVerifyOption(): bool|string
    {
        $caBundle = $this->resolveCaBundle();

        if ($this->resolveSslVerify() && $caBundle !== '' && is_file($caBundle)) {
            return $caBundle;
        }

        return $this->resolveSslVerify();
    }

    private function resolveSslVerify(): bool
    {
        $value = $this->config['ssl_verify'] ?? null;
        if ($value !== null && $value !== '') {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return filter_var(config('idc.sms.ssl_verify', true), FILTER_VALIDATE_BOOL);
    }

    private function resolveCaBundle(): string
    {
        $value = $this->config['ca_bundle'] ?? null;
        if ($value !== null && $value !== '') {
            return trim((string) $value);
        }

        return trim((string) config('idc.sms.ca_bundle', ''));
    }

    private function configString(string $key, string $default = ''): string
    {
        $value = $this->config[$key] ?? null;

        return trim((string) (($value !== null && $value !== '') ? $value : $default));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function optionString(array $options, string $key, string $default = ''): string
    {
        $value = $options[$key] ?? null;

        return trim((string) (($value !== null && $value !== '') ? $value : $default));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function failure(string $message, array $raw = []): array
    {
        return [
            'success' => false,
            'message' => $message !== '' ? $message : '短信发送失败，请稍后重试',
            'raw' => $raw,
        ];
    }

    private function resolveFailureMessage(mixed $message): string
    {
        $text = trim((string) $message);
        if ($text === '') {
            return '短信发送失败，请稍后重试';
        }

        if (preg_match('/sk_(live|test)|[a-z0-9]{24,}/i', $text) === 1) {
            return '短信发送失败，请稍后重试';
        }

        return mb_substr($text, 0, 120);
    }
}
