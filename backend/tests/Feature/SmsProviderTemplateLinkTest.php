<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\SmsProviderTemplate;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Services\Sms\SmsProviderTemplateService;
use App\Services\System\ActivityLogService;
use App\Services\System\NotificationTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * 短信模板挂接（业务码 ↔ 运营商模板）：
 * 1. 同一业务码「先解绑后挂接」；
 * 2. 业务码粒度互斥锁保证并发挂接串行化（2026-10-20 审查 P2-1）；
 * 3. 无论成功还是拒绝，锁都要释放，不能把业务码永久锁死。
 */
class SmsProviderTemplateLinkTest extends TestCase
{
    use DatabaseTransactions;

    public function test_link_replaces_existing_mapping_and_releases_lock(): void
    {
        $existing = $this->makeMapping('TEST_BIZ_CODE');
        $target = $this->makeMapping(null);
        $service = $this->makeService('TEST_BIZ_CODE');

        $service->link((int) $target->id, 'TEST_BIZ_CODE');

        $this->assertNull($existing->fresh()->business_code, '同业务码的旧挂接必须被解绑');
        $this->assertSame('TEST_BIZ_CODE', $target->fresh()->business_code);

        $lock = Cache::lock('lock:sms-provider-template-link:TEST_BIZ_CODE', 15);
        $this->assertTrue($lock->get(), '挂接完成后必须释放互斥锁');
        $lock->release();
    }

    public function test_link_is_rejected_when_business_code_is_locked(): void
    {
        $target = $this->makeMapping(null);
        $service = $this->makeService('TEST_BIZ_LOCK');

        $lock = Cache::lock('lock:sms-provider-template-link:TEST_BIZ_LOCK', 15);
        $this->assertTrue($lock->get());

        try {
            $service->link((int) $target->id, 'TEST_BIZ_LOCK');
            $this->fail('同一业务码并发挂接必须被拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame(40900, $exception->getErrorCode());
            $this->assertSame(409, $exception->getCode());
        } finally {
            $lock->release();
        }

        $this->assertNull($target->fresh()->business_code, '被拒绝的挂接不得修改映射');
    }

    private function makeService(string $businessCode): SmsProviderTemplateService
    {
        $templates = Mockery::mock(NotificationTemplateService::class);
        $templates->shouldReceive('find')
            ->with('sms', $businessCode)
            ->andReturn(['content' => '短信内容', 'is_enabled' => true]);

        $logs = Mockery::mock(ActivityLogService::class);
        $logs->shouldReceive('logSafely')->zeroOrMoreTimes()->andReturnNull();

        $bindingResolver = Mockery::mock(IntegrationDriverBindingResolver::class);
        $bindingResolver->shouldReceive('smsDriverKey')->zeroOrMoreTimes()->andReturn('test_plugin');

        return new SmsProviderTemplateService(
            Mockery::mock(PluginRuntimeRegistry::class),
            $bindingResolver,
            $templates,
            $logs,
        );
    }

    private function makeMapping(?string $businessCode): SmsProviderTemplate
    {
        return SmsProviderTemplate::query()->create([
            'plugin_key' => 'test_plugin',
            'title' => '测试模板',
            'business_code' => $businessCode,
            'provider_template_id' => 'PT'.uniqid(),
            'content' => '短信内容',
            'status' => SmsProviderTemplate::STATUS_APPROVED,
            'synced_at' => now(),
        ]);
    }
}
