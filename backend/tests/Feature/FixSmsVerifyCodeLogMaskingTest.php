<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MessageLog;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Services\Sms\SmsDriverManager;
use App\Services\System\AdminLogService;
use App\Services\System\AdminLogV2QueryService;
use App\Services\System\SmsService;
use App\Support\AuditParamRedactor;
use App\Support\SmsTemplateCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\Sms\FakeSmsDriver;
use Tests\TestCase;

/**
 * 短信验证码不落 message_logs 明文（M-50 修复回归）。
 *
 * visitor 默认角色带 log.list：验证码明文落日志 = 可接管任意账号。
 * - 写库：content 里本次码值替换为 ******，params_json.code 同样掩码；
 * - 读回：历史行的明文码在列表/详情出口再做一次掩码兜底。
 */
class FixSmsVerifyCodeLogMaskingTest extends TestCase
{
    use DatabaseTransactions;

    private FakeSmsDriver $aliyunDriver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aliyunDriver = new FakeSmsDriver('aliyun');
        app(SmsDriverManager::class)->register($this->aliyunDriver);

        Setting::setValue('notification', 'sms_enabled', 1);

        // 验证码模板按迁移口径预置：正文同时含 {code} 与保留字面量，便于断言掩码边界。
        NotificationTemplate::query()->updateOrCreate(
            ['channel' => 'sms', 'code' => SmsTemplateCatalog::TEMPLATE_VERIFY_CODE],
            [
                'name' => '发送验证码',
                'description' => '测试专用',
                'audience' => 'user',
                'subject' => null,
                'content' => '您的验证码{code}，该验证码5分钟内有效,请勿泄漏于他人。',
                'variables_json' => ['code'],
                'is_enabled' => true,
                'is_custom' => false,
                'sort_order' => 0,
            ],
        );
    }

    public function test_verify_code_is_masked_in_persisted_log(): void
    {
        $code = '654321';

        app(SmsService::class)->sendVerifyCode('13900000000', $code, ['purpose' => 'login']);

        // 发送侧仍用真实验证码（掩码只影响审计）。
        $this->assertCount(1, $this->aliyunDriver->verifyCodes);
        $this->assertSame($code, $this->aliyunDriver->verifyCodes[0]->code);

        $log = MessageLog::query()->where('channel', 'sms')->orderByDesc('id')->first();
        $this->assertNotNull($log);

        $this->assertStringNotContainsString($code, (string) $log->content, 'content 不得含验证码明文');
        $this->assertStringContainsString('******', (string) $log->content, '正文中码位应替换为掩码');

        $params = (array) $log->params_json;
        $this->assertSame('******', (string) ($params['code'] ?? ''), 'params_json.code 必须掩码');
        // 其余参数保留，审计仍能回答「哪个场景、有效期多久」。
        $this->assertSame('login', (string) ($params['purpose'] ?? ''));
        $this->assertSame('5', (string) ($params['min'] ?? ''));
    }

    public function test_template_sms_branch_masks_code_too(): void
    {
        $code = '112233';

        // 驱动 key 非 aliyun 时 sendVerifyCode 走模板分支（sendTemplateSms）。
        config(['integrations.sms.default' => 'stay33']);
        $templateDriver = new FakeSmsDriver('stay33');
        $sms = new SmsService(new SmsDriverManager([$templateDriver]));

        $sms->sendVerifyCode('13900000001', $code);

        $log = MessageLog::query()->where('channel', 'sms')->where('recipient', '13900000001')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString($code, (string) $log->content);
        $this->assertStringContainsString('******', (string) $log->content);
        $this->assertSame('******', (string) data_get((array) $log->params_json, 'code'));
    }

    public function test_admin_sms_list_and_detail_mask_legacy_plaintext_rows(): void
    {
        // 历史行（修复前写库）：正文与参数都是明文。
        $legacy = MessageLog::query()->create([
            'channel' => 'sms',
            'recipient' => '13900000002',
            'template_code' => 'aliyun_verify_code',
            'content' => '您的验证码为998877。以上验证码5分钟内有效，请注意保密。',
            'params_json' => ['code' => '998877', 'min' => '5', 'purpose' => 'login'],
            'provider' => 'aliyun',
            'status' => 'success',
            'origin_type' => 'sms_verify',
            'origin_id' => 0,
        ]);

        $payload = app(AdminLogService::class)->getSmsLogs(['phone' => '13900000002'], 10);
        $row = collect($payload['data'] ?? $payload['items'] ?? [])->first();

        $this->assertNotNull($row, '列表必须能取到该行');
        $this->assertStringNotContainsString('998877', (string) ($row['content'] ?? ''), '列表出口不得返回历史明文码');
        $this->assertStringContainsString('******', (string) ($row['content'] ?? ''));
        $this->assertSame('******', (string) data_get((array) ($row['params'] ?? []), 'code'));
        $this->assertSame('5', (string) data_get((array) ($row['params'] ?? []), 'min'), '非验证码参数原样保留');

        $detail = app(AdminLogV2QueryService::class)->detail('sms', (string) $legacy->id);
        $this->assertStringNotContainsString('998877', (string) ($detail['message'] ?? ''), '详情出口同样兜底掩码');
        $this->assertSame('******', (string) data_get((array) ($detail['context']['params'] ?? []), 'code'));
    }

    public function test_mask_helpers_do_not_touch_unrelated_numbers(): void
    {
        // 正文里同时有金额与验证码：只掩码验证码，金额原样保留。
        $content = '您的验证码为123456，订单金额 1234567.00 元，5 分钟内有效。';

        $masked = AuditParamRedactor::maskVerifyCodeText($content);

        $this->assertStringContainsString('验证码为******', $masked);
        $this->assertStringContainsString('1234567.00', $masked);
    }
}
