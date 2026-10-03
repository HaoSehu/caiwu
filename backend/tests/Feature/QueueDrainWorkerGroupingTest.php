<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Automation\Heartbeat\QueueDrainService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * queue:drain 的 Worker 分组：provision 独立成组避免长任务队头阻塞，
 * 存量配置（CAIWU_BUSINESS_QUEUES 仍含 provision）在运行时去重，
 * 保证不会出现两个 worker 抢同一队列。
 */
class QueueDrainWorkerGroupingTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        // 清掉测试写入的 config，避免污染同进程后续用例
        config([
            'queue.caiwu_provision_queues' => null,
            'queue.caiwu_business_queues' => null,
            'queue.caiwu_schedule_queue' => null,
        ]);

        parent::tearDown();
    }

    public function test_default_config_splits_provision_from_business(): void
    {
        config([
            'queue.caiwu_provision_queues' => 'provision',
            'queue.caiwu_business_queues' => 'referral,notification,coupon,default',
            'queue.caiwu_schedule_queue' => 'automation',
        ]);

        $definitions = $this->workerDefinitions();

        $this->assertSame('provision', (string) ($definitions['provision'] ?? ''));
        $this->assertSame('referral,notification,coupon,default', (string) ($definitions['business'] ?? ''));
        $this->assertSame('automation', (string) ($definitions['automation'] ?? ''));
    }

    public function test_stale_business_config_containing_provision_is_deduplicated(): void
    {
        // 存量部署的 .env 里 CAIWU_BUSINESS_QUEUES 仍可能包含 provision
        config([
            'queue.caiwu_provision_queues' => 'provision',
            'queue.caiwu_business_queues' => 'provision,referral,notification,coupon,default',
            'queue.caiwu_schedule_queue' => 'automation',
        ]);

        $definitions = $this->workerDefinitions();

        $this->assertSame('provision', (string) $definitions['provision']);
        $this->assertStringNotContainsString('provision', (string) $definitions['business']);
        $this->assertSame('referral,notification,coupon,default', (string) $definitions['business']);
    }

    public function test_empty_or_whitespace_entries_are_normalized(): void
    {
        config([
            'queue.caiwu_provision_queues' => '',
            'queue.caiwu_business_queues' => ' coupon , , coupon,default ,',
            'queue.caiwu_schedule_queue' => 'automation',
        ]);

        $definitions = $this->workerDefinitions();

        $this->assertArrayNotHasKey('provision', $definitions);
        $this->assertSame('coupon,default', (string) $definitions['business']);
    }

    /**
     * workerDefinitions 为 protected，通过反射调用断言分组结果。
     *
     * @return array<string, string>
     */
    private function workerDefinitions(): array
    {
        $method = new ReflectionMethod(QueueDrainService::class, 'workerDefinitions');
        $method->setAccessible(true);

        /** @var array<string, string> $definitions */
        $definitions = $method->invoke(app(QueueDrainService::class));

        return $definitions;
    }
}
