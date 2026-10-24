<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\ExportDatabaseBackupJob;
use App\Services\Automation\Heartbeat\QueueDrainService;
use App\Support\BackupQueue;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 备份队列名的空串兜底。
 *
 * `.env` 显式写 `CAIWU_BACKUP_QUEUE=` 时 env() 返回空串，config 默认值 'backup'
 * 不生效：队列名会退化成空串，DatabaseQueue::pushOn('') 又回退到 default 队列，
 * 而 default 是 business 组的成员，900 秒的 mysqldump 会重新堵住业务队列。
 */
class BackupQueueTest extends TestCase
{
    protected function tearDown(): void
    {
        config(['queue.caiwu_backup_queue' => null]);

        parent::tearDown();
    }

    public function test_name_returns_configured_value(): void
    {
        config(['queue.caiwu_backup_queue' => 'backup_archive']);

        $this->assertSame('backup_archive', BackupQueue::name());
    }

    public function test_name_falls_back_when_configured_value_is_empty(): void
    {
        // 空串与纯空白都要兜底，否则 trim 之后的空队列名会被当作「未指定」回退 default
        foreach (['', '   ', "\t"] as $blank) {
            config(['queue.caiwu_backup_queue' => $blank]);

            $this->assertSame('backup', BackupQueue::name(), '空值 '.var_export($blank, true).' 应回退默认队列名');
        }
    }

    public function test_name_trims_surrounding_whitespace(): void
    {
        config(['queue.caiwu_backup_queue' => ' backup ']);

        $this->assertSame('backup', BackupQueue::name());
    }

    public function test_name_rejects_comma_separated_value(): void
    {
        // worker 会把 --queue 按逗号拆开逐个 pop，而 Job 投递只认字面队列名：
        // 配成多队列时任务永远没人取，必须让配置错误当场暴露
        config(['queue.caiwu_backup_queue' => 'backup,archive']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('备份队列名必须是单个队列名');

        BackupQueue::name();
    }

    public function test_backup_job_does_not_fall_back_to_default_queue_on_blank_config(): void
    {
        config(['queue.caiwu_backup_queue' => '']);

        $job = new ExportDatabaseBackupJob(Str::uuid()->toString());

        $this->assertSame('backup', $job->queue, '空串配置下备份仍必须进 backup 组，不能回退 default');
    }

    public function test_drain_keeps_backup_group_when_configured_value_is_empty(): void
    {
        config([
            'queue.caiwu_provision_queues' => 'provision',
            'queue.caiwu_business_queues' => 'referral,notification,coupon,default',
            'queue.caiwu_backup_queue' => '',
            'queue.caiwu_schedule_queue' => 'automation',
        ]);

        $method = new ReflectionMethod(QueueDrainService::class, 'workerDefinitions');
        $method->setAccessible(true);

        /** @var array<string, string> $definitions */
        $definitions = $method->invoke(app(QueueDrainService::class));

        $this->assertSame('backup', (string) ($definitions['backup'] ?? ''), '空串配置下 backup 组不能消失');
        $this->assertSame('referral,notification,coupon,default', (string) ($definitions['business'] ?? ''));
    }
}
