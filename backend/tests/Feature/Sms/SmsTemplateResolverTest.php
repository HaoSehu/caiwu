<?php

declare(strict_types=1);

namespace Tests\Feature\Sms;

use App\Models\SmsProviderTemplate;
use App\Services\Sms\SmsTemplateResolver;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 短信运营商模板解析器（短信模板管理机制 2026-10-11）：
 * 1. 仅 approved 映射可被解析（pending/rejected/deleted 一律视同无映射）；
 * 2. 映射按 plugin_key 过滤——换绑驱动后旧插件映射自然失效；
 * 3. 同业务码多条 approved 取 synced_at 最新（并列按 id）。
 * 使用 DatabaseTransactions，映射行测试内自建。
 */
class SmsTemplateResolverTest extends TestCase
{
    use DatabaseTransactions;

    private SmsTemplateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new SmsTemplateResolver;
    }

    public function test_approved_mapping_resolves_by_driver_and_business_code(): void
    {
        $this->makeMapping('aliyun', 'SMS_486530322', '100003', 'approved');

        $mapping = $this->resolver->resolve('100003', 'aliyun');

        $this->assertNotNull($mapping);
        $this->assertSame('SMS_486530322', $mapping->provider_template_id);
        $this->assertSame('100003', $mapping->business_code);
    }

    public function test_non_approved_statuses_are_excluded(): void
    {
        foreach (['pending', 'rejected', 'deleted'] as $status) {
            $this->makeMapping('aliyun', 'SMS_'.strtoupper($status), '100004', $status);

            $this->assertNull(
                $this->resolver->resolve('100004', 'aliyun'),
                '状态 '.$status.' 的映射不得进入发送解析'
            );
        }
    }

    public function test_other_driver_mapping_is_not_used(): void
    {
        $this->makeMapping('demo_sms', 'DEMO_OTHER', '100005', 'approved');

        $this->assertNull($this->resolver->resolve('100005', 'aliyun'), '其他插件的映射不得被解析命中');
    }

    public function test_latest_synced_at_wins_among_multiple_approved(): void
    {
        $this->makeMapping('aliyun', 'SMS_OLD', '100003', 'approved', now()->subHours(2));
        $this->makeMapping('aliyun', 'SMS_NEW', '100003', 'approved', now()->subHour());

        $mapping = $this->resolver->resolve('100003', 'aliyun');

        $this->assertNotNull($mapping);
        $this->assertSame('SMS_NEW', $mapping->provider_template_id);
    }

    public function test_tie_breaks_by_id_desc(): void
    {
        $sameTime = now()->subHour();
        $old = $this->makeMapping('aliyun', 'SMS_TIE_OLD', '100006', 'approved', $sameTime);
        $new = $this->makeMapping('aliyun', 'SMS_TIE_NEW', '100006', 'approved', $sameTime);

        $mapping = $this->resolver->resolve('100006', 'aliyun');

        $this->assertNotNull($mapping);
        $this->assertTrue($new->id > $old->id);
        $this->assertSame('SMS_TIE_NEW', $mapping->provider_template_id);
    }

    public function test_blank_inputs_return_null(): void
    {
        $this->makeMapping('aliyun', 'SMS_X', '100007', 'approved');

        $this->assertNull($this->resolver->resolve('', 'aliyun'));
        $this->assertNull($this->resolver->resolve('100007', ''));
        $this->assertNull($this->resolver->resolve('100007', null));
    }

    private function makeMapping(
        string $pluginKey,
        string $providerTemplateId,
        string $businessCode,
        string $status,
        ?CarbonInterface $syncedAt = null,
    ): SmsProviderTemplate {
        return SmsProviderTemplate::query()->create([
            'plugin_key' => $pluginKey,
            'business_code' => $businessCode,
            'provider_template_id' => $providerTemplateId,
            'provider_sign' => '签名',
            'content' => '内容 ${site_name}',
            'variables_json' => ['site_name'],
            'status' => $status,
            'reject_reason' => $status === 'rejected' ? '含敏感词' : '',
            'synced_at' => $syncedAt ?? now(),
        ]);
    }
}
