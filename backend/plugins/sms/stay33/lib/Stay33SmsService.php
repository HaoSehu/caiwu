<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Sms\Stay33\Lib;

use App\Support\NumericCodeNormalizer;

/**
 * MC云短信插件服务层，按上游 Mcloudsmsv10 v2.0 对接包重构。
 *
 * 与参考实现的对应关系：
 * - `config/smsTemplate.php` 的 20 条 `@var(名称)` 种子模板 → {@see defaultTemplates()}（文案与变量语法在导入时翻译）；
 * - `templateParam()` 的 `@var()` 变量替换 → {@see translateLocalSyntax()}，在模板提审/导入时统一翻译为平台 `${var}` 语法；
 * - `templateSign()` 的签名包裹 → 客户端 {@see Stay33SmsClient::applySign()}；
 * - `sendCnSms()` 的 uid / key / phone / content / channel 五元组 → 客户端 v10 GET 请求；
 * - 6 个模板动作沿用平台「提交即过审」文本通道语义（参考实现无模板审核 API）。
 */
class Stay33SmsService
{
    private ?Stay33SmsClient $client = null;

    public function key(): string
    {
        return 'stay33';
    }

    public function label(): string
    {
        return 'MC云短信';
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
        $config = is_array($request['config'] ?? null) ? $request['config'] : [];

        if ($action === 'sms.test') {
            return $this->send(
                $action,
                $payload,
                $config,
                NumericCodeNormalizer::normalizeSixDigit((string) ($payload['code'] ?? ''))
            );
        }

        if ($action === 'sms.send_message') {
            return $this->sendMessage($action, $payload, $config);
        }

        if ($action === 'sms.send_verify_code') {
            return $this->send($action, $payload, $config, trim((string) ($payload['code'] ?? '')));
        }

        // 按场景返回插件内置的验证码文案（参考插件 sendCnSms 的验证码正文能力）。
        if ($action === 'sms.verify_code_template') {
            return $this->success($action, [
                'template' => $this->verifyCodeTemplate((string) ($payload['purpose'] ?? 'generic')),
            ]);
        }

        // 模板管理 action：官方提供 /sms/templates 查询与提交（带 id 即修改），
        // 因此走真实接口而非占位。官方没有删除模板的接口，delete 仅移除本地挂接。
        if ($action === 'sms.templates.pull') {
            $result = $this->client($config)->queryTemplates();
            if ($result['success'] !== true) {
                return $this->failed($action, (string) ($result['message'] ?? '拉取运营商模板失败'));
            }

            return $this->success($action, [
                'templates' => $result['templates'] ?? [],
                'synced_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($action === 'sms.templates.push' || $action === 'sms.templates.update') {
            $content = trim((string) ($payload['content'] ?? ''));
            if ($content === '') {
                return $this->failed($action, '缺少必要参数：content');
            }

            $translated = $this->translateLocalSyntax($content);
            $title = trim((string) ($payload['title'] ?? '')) ?: mb_substr($translated, 0, 20);
            $providerTemplateId = $action === 'sms.templates.update'
                ? trim((string) ($payload['provider_template_id'] ?? ''))
                : null;

            $result = $this->client($config)->submitTemplate(
                providerTemplateId: $providerTemplateId !== '' ? $providerTemplateId : null,
                name: $title,
                scene: trim((string) ($payload['scene'] ?? '')),
                content: $translated,
                remark: trim((string) ($payload['remark'] ?? '')),
                sampleImage: trim((string) ($payload['sample_image'] ?? '')),
            );

            if ($result['success'] !== true) {
                return $this->failed($action, (string) ($result['message'] ?? '提交运营商模板失败'));
            }

            return $this->success($action, [
                'provider_template_id' => (string) ($result['provider_template_id'] ?? ''),
                'status' => (string) ($result['status'] ?? 'pending'),
                'content' => $translated,
                'variables' => $this->extractVariables($translated),
            ]);
        }

        if ($action === 'sms.templates.status') {
            $providerTemplateId = trim((string) ($payload['provider_template_id'] ?? ''));
            if ($providerTemplateId === '') {
                return $this->failed($action, '缺少必要参数：provider_template_id');
            }

            $result = $this->client($config)->queryTemplateStatus($providerTemplateId);
            if ($result['success'] !== true) {
                return $this->failed($action, (string) ($result['message'] ?? '模板状态回查失败'));
            }

            return $this->success($action, [
                'provider_template_id' => $providerTemplateId,
                'status' => (string) ($result['status'] ?? 'pending'),
                'reject_reason' => (string) ($result['reject_reason'] ?? ''),
            ]);
        }

        // 官方未提供删除模板接口：只从本地映射移除，不动运营商侧。
        if ($action === 'sms.templates.delete') {
            return $this->success($action, [
                'provider_template_id' => trim((string) ($payload['provider_template_id'] ?? '')),
                'deleted' => true,
            ]);
        }

        if ($action === 'sms.templates.defaults') {
            return $this->success($action, ['defaults' => $this->defaultTemplates()]);
        }

        // 查询签名列表（GET /sms/signatures），供管理端选择已审核签名。
        if ($action === 'sms.fetch_signs') {
            $result = $this->client($config)->querySignatures();
            if ($result['success'] !== true) {
                return $this->failed($action, (string) ($result['message'] ?? '获取签名列表失败'));
            }

            return $this->success($action, ['signs' => $result['signs'] ?? []]);
        }

        return [
            'success' => false,
            'action' => $action,
            'message' => 'Unsupported plugin action',
            'data' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function success(string $action, array $data): array
    {
        return [
            'success' => true,
            'action' => $action,
            'message' => '',
            'data' => $data,
        ];
    }

    /**
     * 按场景返回验证码文案模板（`${code}` / `${min}` 占位）。
     *
     * 参考插件把验证码正文内置在发送逻辑里；平台上验证码由系统层统一组装，
     * 因此改为通过 `sms.verify_code_template` 下发，供管理端「同步验证码模板」写入业务模板。
     */
    private function verifyCodeTemplate(string $purpose): string
    {
        return match (strtolower(trim($purpose))) {
            'reset', 'reset_password', 'password_reset' => '尊敬的客户，您正在重置密码，您的验证码为${code}。以上验证码${min}分钟内有效，请注意保密，切勿告知他人。',
            'change_phone', 'phone_change', 'update_phone' => '尊敬的客户，您正在修改手机号，您的验证码为${code}。以上验证码${min}分钟内有效，请注意保密，切勿告知他人。',
            'bind_phone', 'new_phone' => '尊敬的客户，您正在绑定手机号，您的验证码为${code}。以上验证码${min}分钟内有效，请注意保密，切勿告知他人。',
            'verify_bound_phone', 'verify_phone' => '尊敬的客户，您正在验证绑定手机号，您的验证码为${code}。以上验证码${min}分钟内有效，请注意保密，切勿告知他人。',
            default => '您的验证码为${code}。尊敬的客户，以上验证码${min}分钟内有效，请注意保密，切勿告知他人。',
        };
    }

    /**
     * 从模板原文提取变量名（`@var(名称)` / `${名称}` / `{{名称}}` / `{名称}`）。
     *
     * @return array<int, string>
     */
    private function extractVariables(string $content): array
    {
        preg_match_all('/@var\(\s*([a-zA-Z0-9_]+)\s*\)|\$\{([a-zA-Z0-9_]+)\}|\{\{\s*([a-zA-Z0-9_]+)\s*\}\}|(?<![@{])\{([a-zA-Z0-9_]+)\}(?![}])/u', $content, $matches);

        /** @var array<int, string> $names */
        $names = array_merge($matches[1], $matches[2], $matches[3], $matches[4]);
        $names = array_map('strval', $names);

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    /**
     * 把参考插件的 `@var(名称)` 语法翻译为平台既有的 `${名称}` 语法。
     *
     * 平台下发模板种子、发送侧渲染都使用 `${var}` / `{var}` / `{{var}}`；
     * 参考插件用 `@var(var)`，这里统一翻译，避免同一份文案两套占位语法。
     */
    private function translateLocalSyntax(string $content): string
    {
        return (string) preg_replace(
            '/@var\(\s*([a-zA-Z0-9_]+)\s*\)/u',
            '${$1}',
            $content
        );
    }

    /**
     * 插件默认模板种子：对齐上游 Mcloudsmsv10 `config/smsTemplate.php` 的 20 条模板。
     *
     * 参考实现每行是 `{title, content, name}`：`name` 是 ZJMF 侧的短信业务键
     * （如 client_login_success / host_active）。这里原样保留为 `code`，
     * 便于后续与平台 message_scene_templates 的业务码对照。
     * 文案里的 `@var()` 在此翻译为平台 `${}` 语法。
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultTemplates(): array
    {
        $seed = [
            ['验证码', '您的验证码为@var(code)。尊敬的客户，以上验证码5分钟内有效，请注意保密，切勿告知他人。', '验证码', 'code'],
            ['用户登录', '您的账号成功登录系统，如不是本人操作请及时修改密码', '登录通知', 'client_login_success'],
            ['用户注册', '感谢您支持@var(system_website_name)', '注册通知', 'client_register_success'],
            ['客户更改密码', '您的密码被改为：@var(client_password)，请注意账户安全！', '密码变更通知', 'client_change_password'],
            ['订单创建', '您已下单，订单：@var(order_id)（订单号），请及时支付！', '订单创建通知', 'order_create'],
            ['产品开通中', '您的产品：@var(product_name)（产品名称）正在开通，请耐心等待！', '开通中通知', 'host_pending'],
            ['开通成功', '您的产品：@var(product_name)（产品名称），已开通可使用！', '开通成功通知', 'host_active'],
            ['产品暂停通知', '尊敬的客户，您使用的产品：@var(product_name)（产品名称），由于@var(product_suspend_reason) 原因，现在已停用！', '暂停通知', 'host_suspend'],
            ['产品解除暂停通知', '您的产品：@var(product_name)（产品名称），已解除暂停！', '解除暂停通知', 'host_unsuspend'],
            ['产品删除通知', '您的产品：@var(product_name)（产品名称），由于到期未续费，已删除！', '删除通知', 'host_terminate'],
            ['产品升降级', '您已成功升级产品@var(product_name)，感谢您的支持！', '升降级通知', 'host_upgrad'],
            ['第一次续费提醒', '您的产品：@var(product_name)（产品名称），还有@var(renewal_first)天到期，请及时续费！', '续费提醒一', 'host_renewal_first'],
            ['第二次续费提醒', '您的产品：@var(product_name)（产品名称），还有@var(renewal_second)天到期，请及时续费！', '续费提醒二', 'host_renewal_second'],
            ['逾期付款第一次提醒', '您的产品：@var(product_name)（产品名称）已到期，将会删除，请及时续费！', '逾期提醒一', 'host_overdue_first'],
            ['逾期付款第二次提醒', '您的产品：@var(product_name)（产品名称）已到期，将会删除，请及时续费！', '逾期提醒二', 'host_overdue_second'],
            ['逾期付款第三次提醒', '您的产品：@var(product_name)（产品名称）已到期，将会删除，请及时续费！', '逾期提醒三', 'host_overdue_third'],
            ['订单未付款通知', '您的订单：@var(order_id)（订单号）尚未支付，金额@var(order_amount)，请及时支付！', '订单未付通知', 'order_overdue'],
            ['订单金额修改', '您的订单：@var(order_id)（订单号）金额修改为@var(order_amount)，请及时支付！', '订单金额修改通知', 'admin_order_amount'],
            ['订单支付通知', '您的订单：@var(order_id)（订单号）支付成功，支付金额为：@var(order_amount)元！', '支付成功通知', 'order_pay'],
            ['充值成功通知', '充值成功，本次充值金额为：@var(order_amount)元！', '充值成功通知', 'order_recharge'],
        ];

        $defaults = [];
        foreach ($seed as [$title, $content, $remark, $code]) {
            $translated = $this->translateLocalSyntax($content);
            $defaults[] = [
                'title' => $title,
                'content' => $translated,
                'remark' => $remark,
                'code' => $code,
                'variables' => $this->extractVariables($translated),
            ];
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function send(string $action, array $payload, array $config, string $code): array
    {
        $phone = trim((string) ($payload['phone'] ?? ''));
        if ($phone === '') {
            return $this->failed($action, '缺少必要参数：phone');
        }

        if ($code === '') {
            return $this->failed($action, '缺少必要参数：code');
        }

        $result = $this->client($config)->sendVerifyCode(
            phone: $phone,
            code: $code,
            options: is_array($payload['options'] ?? null) ? $payload['options'] : [],
        );

        return [
            'success' => (bool) ($result['success'] ?? false),
            'action' => $action,
            'message' => (string) ($result['message'] ?? (($result['success'] ?? false) ? '短信发送成功' : '短信发送失败，请稍后重试')),
            'data' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function sendMessage(string $action, array $payload, array $config): array
    {
        $phone = trim((string) ($payload['phone'] ?? ''));
        $templateCode = trim((string) ($payload['template_code'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));

        if ($phone === '') {
            return $this->failed($action, '缺少必要参数：phone');
        }

        if ($content === '') {
            return $this->failed($action, '缺少必要参数：content');
        }

        $result = $this->client($config)->sendMessage(
            phone: $phone,
            templateCode: $templateCode,
            content: $content,
            options: is_array($payload['options'] ?? null) ? $payload['options'] : [],
        );

        return [
            'success' => (bool) ($result['success'] ?? false),
            'action' => $action,
            'message' => (string) ($result['message'] ?? (($result['success'] ?? false) ? '短信发送成功' : '短信发送失败，请稍后重试')),
            'data' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failed(string $action, string $message): array
    {
        return [
            'success' => false,
            'action' => $action,
            'message' => $message,
            'data' => [
                'success' => false,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function client(array $config): Stay33SmsClient
    {
        if ($this->client === null) {
            $this->client = new Stay33SmsClient($config);
        }

        return $this->client;
    }
}
