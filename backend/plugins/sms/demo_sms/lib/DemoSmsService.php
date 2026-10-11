<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Sms\DemoSms\Lib;

use App\Services\Sms\Data\SmsMessageRequest;
use App\Services\Sms\Data\SmsSendRequest;
use App\Services\Sms\Data\SmsSendResult;
use App\Support\NumericCodeNormalizer;
use App\Support\SmsTemplateCatalog;

class DemoSmsService
{
    public function key(): string
    {
        return 'demo_sms';
    }

    public function label(): string
    {
        return 'Demo 短信';
    }

    public function sendMessage(SmsMessageRequest $request): SmsSendResult
    {
        return new SmsSendResult(
            status: 'success',
            requestId: 'demo-sms-'.date('YmdHis'),
            templateCode: $request->templateCode,
            templateParams: ['content' => $request->content],
            raw: [
                'demo' => true,
                'phone' => $request->phone,
                'content' => $request->content,
            ],
        );
    }

    public function sendVerifyCode(SmsSendRequest $request): SmsSendResult
    {
        return new SmsSendResult(
            status: 'success',
            requestId: 'demo-sms-'.date('YmdHis'),
            templateCode: (string) $request->option('template_code', SmsTemplateCatalog::TEMPLATE_VERIFY_CODE),
            templateParams: ['code' => $request->code],
            raw: ['demo' => true, 'phone' => $request->phone],
        );
    }

    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
        $config = is_array($request['config'] ?? null) ? $request['config'] : [];

        return match ($action) {
            'sms.send_message' => $this->success($action, $this->sendMessage(new SmsMessageRequest(
                phone: (string) ($payload['phone'] ?? ''),
                templateCode: (string) ($payload['template_code'] ?? ''),
                content: (string) ($payload['content'] ?? ''),
                options: $this->resolveOptions($payload, $config),
            ))->toArray()),
            'sms.send_verify_code' => $this->success($action, $this->sendVerifyCode(new SmsSendRequest(
                phone: (string) ($payload['phone'] ?? ''),
                code: (string) ($payload['code'] ?? ''),
                options: $this->resolveOptions($payload, $config),
            ))->toArray()),
            'sms.test' => $this->success($action, $this->sendVerifyCode(new SmsSendRequest(
                phone: (string) ($payload['phone'] ?? ''),
                code: NumericCodeNormalizer::normalizeSixDigit((string) ($payload['code'] ?? '')),
                options: $this->resolveOptions($payload, $config),
            ))->toArray()),
            'sms.templates.pull' => $this->success($action, $this->pullTemplates($config)),
            'sms.templates.push' => $this->success($action, $this->pushTemplate($payload, $config)),
            'sms.templates.update' => $this->success($action, $this->updateTemplate($payload, $config)),
            'sms.templates.delete' => $this->success($action, $this->deleteTemplate($payload, $config)),
            'sms.templates.defaults' => $this->success($action, $this->defaultTemplates()),
            'sms.templates.status' => $this->success($action, $this->queryTemplateStatus($payload, $config)),
            default => ['success' => false, 'action' => $action, 'message' => 'Unsupported plugin action', 'data' => []],
        };
    }

    /**
     * 模拟运营商模板库（cache 持久化，跨请求保留）。
     *
     * 结构：[provider_template_id => [content, status, reject_reason, variables, sign, pushed_at]]
     *
     * @return array<string, array<string, mixed>>
     */
    private function templateStore(array $config): array
    {
        $store = cache()->get('demo_sms.provider_templates');

        if (! is_array($store) || $store === []) {
            $store = [
                'DEMO_100001' => $this->seedTemplate(
                    '您的验证码为${code}，${min}分钟内有效。',
                    'approved',
                    ['code', 'min']
                ),
                'DEMO_100003' => $this->seedTemplate(
                    '尊敬的客户，您的服务将于${expire_date}到期，续费金额${amount}元。',
                    'pending',
                    ['expire_date', 'amount']
                ),
            ];
            cache()->put('demo_sms.provider_templates', $store, $this->storeTtl($config));
        }

        return $store;
    }

    /**
     * @return array<string, mixed>
     */
    private function seedTemplate(string $content, string $status, array $variables): array
    {
        return [
            'content' => $content,
            'status' => $status,
            'reject_reason' => '',
            'variables' => $variables,
            'sign' => 'Demo签名',
            'pushed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * sms.templates.pull：返回模拟库全部模板（统一结构，协议契约与真实插件一致）。
     *
     * @return array<string, mixed>
     */
    private function pullTemplates(array $config): array
    {
        $templates = [];
        foreach ($this->templateStore($config) as $providerTemplateId => $row) {
            $templates[] = [
                'provider_template_id' => (string) $providerTemplateId,
                'provider_sign' => (string) ($row['sign'] ?? ''),
                'content' => (string) ($row['content'] ?? ''),
                'variables' => array_values((array) ($row['variables'] ?? [])),
                'status' => (string) ($row['status'] ?? 'pending'),
                'reject_reason' => (string) ($row['reject_reason'] ?? ''),
            ];
        }

        return ['templates' => $templates, 'synced_at' => date('Y-m-d H:i:s')];
    }

    /**
     * sms.templates.push：模拟提交审核（draft → pending）。
     * 本地文案翻译为运营商 ${var} 占位语法（真实插件亦在此层做协议翻译），
     * payload {title, content, remark} 对齐 ZJMF createCnTemplate 参数。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function pushTemplate(array $payload, array $config): array
    {
        $content = trim((string) ($payload['content'] ?? ''));
        if ($content === '') {
            throw new \InvalidArgumentException('缺少必要参数：content');
        }

        $translated = $this->translateLocalSyntax($content);
        preg_match_all('/\$\{([a-zA-Z0-9_]+)\}/u', $translated, $matches);
        $variables = array_values(array_unique((array) ($matches[1] ?? [])));

        $providerTemplateId = 'DEMO_'.strtoupper(dechex((int) (microtime(true) * 1000) % 0xFFFFFFF)).'_'.random_int(100, 999);

        // review_mode：auto_approve=提交即过审（联调快路径）；manual=走 pending→
        // simulate_approve_seconds 后自动过审（完整闭环演示）。
        $reviewMode = (string) ($config['review_mode'] ?? 'manual');
        $status = $reviewMode === 'auto_approve' ? 'approved' : 'pending';

        $store = $this->templateStore($config);
        $store[$providerTemplateId] = [
            'content' => $translated,
            'status' => $status,
            'reject_reason' => '',
            'variables' => $variables,
            'sign' => (string) ($config['sign_name'] ?? 'Demo签名'),
            'pushed_at' => date('Y-m-d H:i:s'),
        ];
        cache()->put('demo_sms.provider_templates', $store, $this->storeTtl($config));

        return [
            'provider_template_id' => $providerTemplateId,
            'status' => $status,
            'content' => $translated,
            'variables' => $variables,
            'provider_sign' => (string) ($config['sign_name'] ?? 'Demo签名'),
        ];
    }

    /**
     * sms.templates.update：模拟修改模板并重新提审（对齐 ZJMF putCnTemplate）。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function updateTemplate(array $payload, array $config): array
    {
        $providerTemplateId = trim((string) ($payload['provider_template_id'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));
        if ($providerTemplateId === '' || $content === '') {
            throw new \InvalidArgumentException('缺少必要参数：provider_template_id / content');
        }

        $store = $this->templateStore($config);
        if (! isset($store[$providerTemplateId])) {
            return ['success' => false, 'message' => '运营商侧不存在该模板：'.$providerTemplateId];
        }

        $translated = $this->translateLocalSyntax($content);
        preg_match_all('/\$\{([a-zA-Z0-9_]+)\}/u', $translated, $matches);
        $reviewMode = (string) ($config['review_mode'] ?? 'manual');
        $status = $reviewMode === 'auto_approve' ? 'approved' : 'pending';

        $store[$providerTemplateId] = array_merge($store[$providerTemplateId], [
            'content' => $translated,
            'status' => $status,
            'reject_reason' => '',
            'variables' => array_values(array_unique((array) ($matches[1] ?? []))),
            'pushed_at' => date('Y-m-d H:i:s'),
        ]);
        cache()->put('demo_sms.provider_templates', $store, $this->storeTtl($config));

        return [
            'provider_template_id' => $providerTemplateId,
            'status' => $status,
            'content' => $translated,
        ];
    }

    /**
     * sms.templates.delete：模拟删除运营商侧模板。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function deleteTemplate(array $payload, array $config): array
    {
        $providerTemplateId = trim((string) ($payload['provider_template_id'] ?? ''));
        if ($providerTemplateId === '') {
            throw new \InvalidArgumentException('缺少必要参数：provider_template_id');
        }

        $store = $this->templateStore($config);
        unset($store[$providerTemplateId]);
        cache()->put('demo_sms.provider_templates', $store, $this->storeTtl($config));

        return ['provider_template_id' => $providerTemplateId, 'deleted' => true];
    }

    /**
     * sms.templates.defaults：插件默认模板种子（对齐 ZJMF install() 返回
     * smsTemplate.php 的机制），平台侧落为 draft 草稿。
     *
     * @return array<string, mixed>
     */
    private function defaultTemplates(): array
    {
        return [
            'defaults' => [
                [
                    'title' => '账单支付提醒',
                    'content' => '${username},您已成功支付账单号#${invoiceid},账单金额${total}.',
                    'remark' => '账单支付成功通知',
                    'variables' => ['username', 'invoiceid', 'total'],
                ],
                [
                    'title' => '发送验证码',
                    'content' => '验证码${code},5分钟内有效,请勿泄漏于他人',
                    'remark' => '登录/注册验证码',
                    'variables' => ['code'],
                ],
                [
                    'title' => '服务到期提醒',
                    'content' => '${username}，您的产品${product_name}将于${product_end_time}到期，请及时续费',
                    'remark' => '续费提醒',
                    'variables' => ['username', 'product_name', 'product_end_time'],
                ],
            ],
        ];
    }

    /**
     * 本地 {{var}}/{{#var}}/{var} 语法 → 运营商 ${var} 语法。
     */
    private function translateLocalSyntax(string $content): string
    {
        $withoutSections = preg_replace('/\{\{\s*#\w+\s*\}\}|\{\{\s*\/\w+\s*\}\}/u', '', $content) ?? $content;

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}|\{([a-zA-Z0-9_]+)\}/u',
            static fn (array $matches): string => '${'.($matches[1] !== '' && $matches[1] !== null ? $matches[1] : (string) $matches[2]).'}',
            $withoutSections
        );
    }

    /**
     * sms.templates.status：pending 超过 simulate_approve_seconds（默认 60 秒）
     * 自动流转为 approved，模拟运营商审核回调。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function queryTemplateStatus(array $payload, array $config): array
    {
        $providerTemplateId = trim((string) ($payload['provider_template_id'] ?? ''));
        if ($providerTemplateId === '') {
            throw new \InvalidArgumentException('缺少必要参数：provider_template_id');
        }

        $store = $this->templateStore($config);
        if (! isset($store[$providerTemplateId])) {
            return ['provider_template_id' => $providerTemplateId, 'status' => 'deleted', 'reject_reason' => ''];
        }

        $row = $store[$providerTemplateId];
        if (($row['status'] ?? '') === 'pending') {
            $approveSeconds = max(0, (int) ($config['simulate_approve_seconds'] ?? 60));
            $pushedAt = strtotime((string) ($row['pushed_at'] ?? 'now')) ?: time();
            if (time() - $pushedAt >= $approveSeconds) {
                $row['status'] = 'approved';
                $store[$providerTemplateId] = $row;
                cache()->put('demo_sms.provider_templates', $store, $this->storeTtl($config));
            }
        }

        return [
            'provider_template_id' => $providerTemplateId,
            'status' => (string) ($row['status'] ?? 'pending'),
            'reject_reason' => (string) ($row['reject_reason'] ?? ''),
        ];
    }

    /**
     * @return \DateInterval|int
     */
    private function storeTtl(array $config)
    {
        $hours = max(1, (int) ($config['simulate_store_hours'] ?? 24));

        return now()->addHours($hours);
    }

    private function success(string $action, array $data): array
    {
        return [
            'success' => true,
            'action' => $action,
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function resolveOptions(array $payload, array $config): array
    {
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];

        if (! array_key_exists('sign_name', $options) && isset($config['sign_name'])) {
            $options['sign_name'] = $config['sign_name'];
        }

        if (! array_key_exists('template_code', $options) && isset($config['template_code'])) {
            $options['template_code'] = $config['template_code'];
        }

        return $options;
    }
}
