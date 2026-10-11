<?php

declare(strict_types=1);

namespace Tests\Feature\Sms;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Models\SmsProviderTemplate;
use App\Services\Sms\SmsDriverManager;
use App\Services\System\SmsService;
use App\Support\SchemaMetadataCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\Fixtures\Sms\FakeSmsDriver;
use Tests\TestCase;

/**
 * 发送链路接入运营商模板映射（短信模板管理机制 2026-10-11）：
 * 1. 命中 approved 映射时发送用映射的运营商模板 ID，声明变量经交集校验后
 *    以 options.template_variables 结构化传给插件；
 * 2. 声明变量缺失 → fail-fast（不让残缺参数打到运营商）；
 * 3. options 显式 provider_template_id 优先级最高（人工指定不被映射覆盖）；
 * 4. 无映射时回落 notification_templates.provider_template_id 旧列（人工兜底）。
 * 使用 DatabaseTransactions + 共享替身 FakeSmsDriver（key=aliyun 系统默认）。
 */
class SmsSendProviderMappingTest extends TestCase
{
    use DatabaseTransactions;

    private const BUSINESS_CODE = '190001';

    private FakeSmsDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new FakeSmsDriver('aliyun');
        app(SmsDriverManager::class)->register($this->driver);

        Setting::setValue('notification', 'sms_enabled', 1);

        NotificationTemplate::query()->create([
            'channel' => 'sms',
            'code' => self::BUSINESS_CODE,
            'name' => '解析链测试模板',
            'description' => '测试专用',
            'audience' => 'user',
            'subject' => null,
            'content' => '【${site_name}】您的服务将于${expire_date}到期。',
            'variables_json' => ['site_name', 'expire_date'],
            'provider_template_id' => 'LEGACY_1',
            'is_enabled' => true,
            'is_custom' => false,
            'sort_order' => 0,
        ]);
    }

    public function test_send_uses_approved_mapping_and_passes_template_variables(): void
    {
        $this->makeMapping('aliyun', 'SMS_MAPPED_1', 'approved', ['site_name', 'expire_date']);

        app(SmsService::class)->sendTemplateSms('13900000000', self::BUSINESS_CODE, [
            'expire_date' => '2026-12-31',
        ]);

        $this->assertCount(1, $this->driver->messages);
        $request = $this->driver->messages[0];
        $this->assertSame('SMS_MAPPED_1', $request->templateCode, '命中映射时必须用映射的运营商模板 ID');
        $this->assertSame(
            ['site_name', 'expire_date'],
            array_keys((array) $request->option('template_variables')),
            '声明变量必须结构化传给插件'
        );
        $this->assertSame('2026-12-31', (string) $request->option('template_variables')['expire_date']);
        $this->assertStringContainsString('2026-12-31', $request->content);
    }

    public function test_missing_declared_variable_fails_fast(): void
    {
        $this->makeMapping('aliyun', 'SMS_MAPPED_2', 'approved', ['site_name', 'expire_date', 'amount']);

        try {
            app(SmsService::class)->sendTemplateSms('13900000000', self::BUSINESS_CODE, [
                'expire_date' => '2026-12-31',
            ]);
            $this->fail('声明变量缺失必须 fail-fast');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('缺少变量', $exception->getMessage());
            $this->assertStringContainsString('amount', $exception->getMessage());
        }

        $this->assertCount(0, $this->driver->messages, 'fail-fast 不得触发插件发送');
    }

    public function test_explicit_option_overrides_mapping(): void
    {
        $this->makeMapping('aliyun', 'SMS_MAPPED_3', 'approved', ['site_name', 'expire_date']);

        app(SmsService::class)->sendTemplateSms('13900000000', self::BUSINESS_CODE, [
            'expire_date' => '2026-12-31',
        ], [
            'provider_template_id' => 'MANUAL_9',
        ]);

        $this->assertCount(1, $this->driver->messages);
        $this->assertSame('MANUAL_9', $this->driver->messages[0]->templateCode);
    }

    public function test_without_mapping_falls_back_to_legacy_column(): void
    {
        app(SmsService::class)->sendTemplateSms('13900000000', self::BUSINESS_CODE, [
            'expire_date' => '2026-12-31',
        ]);

        $this->assertCount(1, $this->driver->messages);
        $this->assertSame('LEGACY_1', $this->driver->messages[0]->templateCode, '无映射时回落旧列人工兜底');
        $this->assertSame([], (array) $this->driver->messages[0]->option('template_variables'));
    }

    public function test_rejected_mapping_is_not_used_and_falls_back(): void
    {
        $this->makeMapping('aliyun', 'SMS_REJECTED_1', 'rejected', ['site_name'], '含敏感词');

        app(SmsService::class)->sendTemplateSms('13900000000', self::BUSINESS_CODE, [
            'expire_date' => '2026-12-31',
        ]);

        $this->assertSame('LEGACY_1', $this->driver->messages[0]->templateCode);
    }

    private function makeMapping(
        string $pluginKey,
        string $providerTemplateId,
        string $status,
        array $variables,
        string $rejectReason = '',
    ): SmsProviderTemplate {
        $this->assertTrue(SchemaMetadataCache::hasTable('sms_provider_templates'), '迁移未执行');

        return SmsProviderTemplate::query()->create([
            'plugin_key' => $pluginKey,
            'business_code' => self::BUSINESS_CODE,
            'provider_template_id' => $providerTemplateId,
            'provider_sign' => '签名',
            'content' => '【${site_name}】您的服务将于${expire_date}到期。',
            'variables_json' => $variables,
            'status' => $status,
            'reject_reason' => $rejectReason,
            'synced_at' => now(),
        ]);
    }
}
