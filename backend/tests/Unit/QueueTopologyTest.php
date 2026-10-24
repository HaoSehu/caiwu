<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BackupQueue;
use App\Support\QueueTopology;
use Tests\TestCase;

/**
 * 队列分组口径的唯一实现。
 *
 * queue:drain、app:serve 与定时任务页命令清单都从这里取分组，
 * 因此「一个队列只归一个 Worker」与「备份队列名配错不放倒整轮 drain」
 * 这两条约束必须在这里锁死。
 */
class QueueTopologyTest extends TestCase
{
    protected function tearDown(): void
    {
        config([
            'queue.caiwu_provision_queues' => null,
            'queue.caiwu_business_queues' => null,
            'queue.caiwu_backup_queue' => null,
            'queue.caiwu_schedule_queue' => null,
        ]);

        parent::tearDown();
    }

    public function test_resolve_assigns_each_queue_to_exactly_one_group(): void
    {
        // 存量部署里 backup 可能同时出现在 CAIWU_BUSINESS_QUEUES 与 CAIWU_SCHEDULE_QUEUE
        config([
            'queue.caiwu_provision_queues' => 'provision,backup',
            'queue.caiwu_business_queues' => 'backup,provision,referral,automation',
            'queue.caiwu_backup_queue' => 'backup',
            'queue.caiwu_schedule_queue' => 'backup,automation',
        ]);

        $groups = QueueTopology::resolve();

        $this->assertSame(['provision'], $groups['provision']);
        $this->assertSame(['backup'], $groups['backup']);
        $this->assertSame(['automation'], $groups['automation']);
        $this->assertSame(['referral'], $groups['business'], 'business 只能留下没有任何组认领的队列');
    }

    public function test_resolve_keeps_other_groups_when_backup_name_is_invalid(): void
    {
        config([
            'queue.caiwu_provision_queues' => 'provision',
            'queue.caiwu_business_queues' => 'referral,notification,coupon,default',
            'queue.caiwu_backup_queue' => 'backup,archive',
            'queue.caiwu_schedule_queue' => 'automation',
        ]);

        $errors = [];
        $groups = QueueTopology::resolve($errors);

        $this->assertSame([], $groups['backup'], '备份队列名非法时 backup 组必须缺席');
        $this->assertSame(['provision'], $groups['provision']);
        $this->assertSame(['referral', 'notification', 'coupon', 'default'], $groups['business']);
        $this->assertSame(['automation'], $groups['automation']);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('备份队列名必须是单个队列名', (string) $errors[0]);
    }

    public function test_backup_name_falls_back_to_default_when_configured_value_is_invalid(): void
    {
        // 展示型快照（定时任务页）必须拿到可用值，否则整页 500
        config(['queue.caiwu_backup_queue' => 'backup,archive']);

        $errors = [];
        $name = QueueTopology::backupName($errors);

        $this->assertSame(BackupQueue::DEFAULT_NAME, $name);
        $this->assertCount(1, $errors);
    }

    public function test_normalize_trims_dedupes_and_drops_blank_entries(): void
    {
        $this->assertSame(
            ['coupon', 'default'],
            QueueTopology::normalize(' coupon , , coupon,default ,'),
        );
        $this->assertSame([], QueueTopology::normalize('   '));
    }
}
