<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Automation\Heartbeat\QueueDrainService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * queue:drain 的 Worker 分组：provision 与 backup 各自独立成组避免长任务队头阻塞，
 * 存量配置（CAIWU_BUSINESS_QUEUES 仍含 provision/backup）在运行时去重，
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
            'queue.caiwu_backup_queue' => null,
            'queue.caiwu_schedule_queue' => null,
        ]);

        parent::tearDown();
    }

    public function test_default_config_splits_provision_from_business(): void
    {
        config([
            'queue.caiwu_provision_queues' => 'provision',
            'queue.caiwu_business_queues' => 'referral,notification,coupon,default',
            'queue.caiwu_backup_queue' => 'backup',
            'queue.caiwu_schedule_queue' => 'automation',
        ]);

        $definitions = $this->workerDefinitions();

        $this->assertSame('provision', (string) ($definitions['provision'] ?? ''));
        $this->assertSame('referral,notification,coupon,default', (string) ($definitions['business'] ?? ''));
        $this->assertSame('backup', (string) ($definitions['backup'] ?? ''));
        $this->assertSame('automation', (string) ($definitions['automation'] ?? ''));
    }

    public function test_stale_business_config_containing_backup_is_deduplicated(): void
    {
        // 存量部署的 .env 里 CAIWU_BUSINESS_QUEUES 仍可能包含 backup
        config([
            'queue.caiwu_provision_queues' => 'provision',
            'queue.caiwu_business_queues' => 'backup,referral,notification,coupon,default',
            'queue.caiwu_backup_queue' => 'backup',
            'queue.caiwu_schedule_queue' => 'automation',
        ]);

        $definitions = $this->workerDefinitions();

        $this->assertSame('backup', (string) $definitions['backup']);
        $this->assertSame('referral,notification,coupon,default', (string) $definitions['business']);
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

    public function test_stale_schedule_config_containing_backup_is_deduplicated(): void
    {
        // 存量部署的 .env 里 CAIWU_SCHEDULE_QUEUE 仍可能包含 backup，
        // 且 CAIWU_BUSINESS_QUEUES 里可能写了 automation
        config([
            'queue.caiwu_provision_queues' => 'provision',
            'queue.caiwu_business_queues' => 'automation,referral,notification,coupon,default',
            'queue.caiwu_backup_queue' => 'backup',
            'queue.caiwu_schedule_queue' => 'backup,automation',
        ]);

        $definitions = $this->workerDefinitions();

        $this->assertSame('backup', (string) $definitions['backup']);
        $this->assertSame('automation', (string) $definitions['automation']);
        $this->assertSame('referral,notification,coupon,default', (string) $definitions['business']);
        $this->assertSame('provision', (string) $definitions['provision']);
    }

    public function test_empty_or_whitespace_entries_are_normalized(): void
    {
        config([
            'queue.caiwu_provision_queues' => '',
            'queue.caiwu_business_queues' => ' coupon , , coupon,default ,',
            // 备份队列只允许单个队列名，逗号串由 BackupQueue::name() 直接拒绝，
            // 这里只验证首尾空白的裁剪
            'queue.caiwu_backup_queue' => ' backup ',
            'queue.caiwu_schedule_queue' => 'automation',
        ]);

        $definitions = $this->workerDefinitions();

        $this->assertArrayNotHasKey('provision', $definitions);
        $this->assertSame('coupon,default', (string) $definitions['business']);
        $this->assertSame('backup', (string) $definitions['backup']);
    }

    public function test_invalid_backup_queue_name_skips_only_backup_group(): void
    {
        // 备份队列名写成逗号分隔的多个队列时 BackupQueue 会抛异常；
        // 该异常只能让 backup 组缺席，不能把其余三个 worker 一起停掉，
        // 否则一处 .env 写错会让 queue:drain 整条命令退出、业务队列全部停摆。
        config([
            'queue.caiwu_provision_queues' => 'provision',
            'queue.caiwu_business_queues' => 'referral,notification,coupon,default',
            'queue.caiwu_backup_queue' => 'backup,archive',
            'queue.caiwu_schedule_queue' => 'automation',
        ]);

        $errors = [];
        $definitions = $this->workerDefinitions($errors);

        $this->assertArrayNotHasKey('backup', $definitions, '备份队列名非法时 backup 组应缺席');
        $this->assertSame('provision', (string) ($definitions['provision'] ?? ''));
        $this->assertSame('referral,notification,coupon,default', (string) ($definitions['business'] ?? ''));
        $this->assertSame('automation', (string) ($definitions['automation'] ?? ''));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('备份队列名必须是单个队列名', (string) $errors[0]);
    }

    /**
     * workerDefinitions 为 protected，通过反射调用断言分组结果。
     *
     * @param  list<string>  $errors
     * @return array<string, string>
     */
    private function workerDefinitions(array &$errors = []): array
    {
        $method = new ReflectionMethod(QueueDrainService::class, 'workerDefinitions');
        $method->setAccessible(true);

        /** @var array<string, string> $definitions */
        $definitions = $method->invokeArgs(app(QueueDrainService::class), [&$errors]);

        return $definitions;
    }
}
