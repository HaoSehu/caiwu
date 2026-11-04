<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Services\System\NotificationTemplateService;
use Throwable;

/**
 * 从短信插件的「号码认证参数配置」拉取内置验证码模板。
 *
 * 与 SmsProviderTemplateService 的区别：
 * - 后者拉的是运营商侧的通用模板库（sms.templates.pull），需要走审核与挂接；
 * - 本服务拉的是插件为验证码场景**内置**的文案模板（sms.verify_code_template），
 *   如阿里云号码认证的 5 个赠送模板（登录/注册、修改绑定手机号、重置密码、
 *   绑定新手机号、验证绑定手机号），无需审核，直接可用于发送。
 *
 * 拉取后将插件文案写入对应的业务模板（验证码类），并标记为插件接管字段，
 * 使「插件优先」在后续编辑中持续生效。
 */
final class SmsVerifyCodeTemplateSyncService
{
    /**
     * 号码认证场景 purpose => [系统业务模板编码, 运营商模板 ID]。
     *
     * 数组键 '100001' 会被 PHP 归一化为整数，故用 int|string 声明。
     * 第二个元素是阿里云号码认证赠送模板的编号（100001–100005），
     * 插件 action 只返回文案不返回编号，此处按阿里云后台的固定对应关系补齐。
     *
     * @var array<int|string, array{0: string, 1: string}>
     */
    private const PURPOSE_BY_TEMPLATE = [
        // 登录/注册验证码（阿里云 100001）
        'generic' => ['100001', '100001'],
        // 找回密码（阿里云 100003）
        'reset' => ['100001', '100003'],
        // 修改绑定手机号（阿里云 100002）
        'change_phone' => ['100004', '100002'],
        // 绑定新手机号（阿里云 100004）
        'bind_phone' => ['100048', '100004'],
        // 验证绑定手机号（阿里云 100005）
        'verify_bound_phone' => ['100048', '100005'],
    ];

    public function __construct(
        private readonly PluginRuntimeRegistry $runtime,
        private readonly NotificationTemplateService $templates,
    ) {}

    /**
     * 拉取当前绑定插件内置的验证码模板，返回每条 purpose 的文案。
     *
     * @return array{purposes: array<string, string>, applied: int}
     */
    public function pull(?string $pluginKey = null): array
    {
        $pluginKey = trim((string) ($pluginKey ?? 'aliyun'));
        if ($pluginKey === '') {
            throw new BusinessException('短信插件未绑定，无法拉取验证码模板');
        }

        $purposes = ['generic', 'reset', 'change_phone', 'bind_phone', 'verify_bound_phone'];
        $collected = [];

        foreach ($purposes as $purpose) {
            try {
                $result = $this->runtime->execute(PluginDomain::SMS, $pluginKey, 'sms.verify_code_template', [
                    'purpose' => $purpose,
                ]);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            $template = trim((string) ($result['data']['template'] ?? ''));
            if ($template !== '') {
                $collected[$purpose] = $template;
            }
        }

        if ($collected === []) {
            throw new BusinessException('当前短信插件未提供验证码模板');
        }

        return [
            'purposes' => $collected,
            'applied' => $this->applyToTemplates($collected),
            'plugin_key' => $pluginKey,
        ];
    }

    /**
     * 把插件模板写回业务模板的正文。
     *
     * 插件按 ${code} / ${min} 占位，系统短信模板用 {code} 单括号语法，写入前统一转换。
     *
     * @param  array<string, string>  $purposes
     */
    private function applyToTemplates(array $purposes): int
    {
        $applied = 0;
        $seen = [];

        foreach (self::PURPOSE_BY_TEMPLATE as $purpose => [$templateCode, $providerTemplateId]) {
            if (! isset($purposes[$purpose]) || isset($seen[$templateCode])) {
                continue;
            }

            $content = str_replace(
                ['${code}', '${min}'],
                ['{code}', '{min}'],
                $purposes[$purpose]
            );

            if ($this->templates->updateSmsTemplateContent((string) $templateCode, $content, $providerTemplateId)) {
                $seen[$templateCode] = true;
                $applied++;
            }
        }

        return $applied;
    }
}
