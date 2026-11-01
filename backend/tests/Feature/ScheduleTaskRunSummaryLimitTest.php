<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Resources\Admin\V2\AdminScheduleTaskRunResource;
use App\Models\ScheduleTaskRun;
use App\Services\Automation\Heartbeat\ScheduleTaskRunRepository;
use App\Services\Automation\Heartbeat\ScheduleTickRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 运行台账 summary 字节上限回归。
 *
 * 背景：同一次执行的同一份结果，审计镜像侧受
 * ScheduleRunLogService::CRON_MIRROR_SUMMARY_MAX_BYTES(4096) 约束，
 * 台账侧 markSucceeded 却把 handler 返回的完整数组直写 summary，没有上限，
 * 是 schedule_task_runs 单表体量失控的直接原因之一。
 * 本测试锁定两侧同口径：超过 4096 字节的结果必须被收敛为摘要结构。
 *
 * 2P0-C（2026-11-01）：markSucceeded 增加指纹去重——同一 task_key 连续两次
 * 成功且 summary 完全一致时，后一次落紧凑引用（__same_as_previous）而不是
 * 重复完整内容；首行与内容变化行必须原样保留。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ScheduleTaskRunSummaryLimitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_oversized_summary_is_limited_before_persist(): void
    {
        $now = CarbonImmutable::now();
        $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($now);

        $run = ScheduleTaskRun::query()->create([
            'schedule_tick_id' => (int) $tick->id,
            'task_key' => 'summary-limit-probe',
            'task_name' => '台账摘要上限探针',
            'source' => 'heartbeat',
            'queue' => 'caiwu_schedule',
            'status' => ScheduleTaskRun::STATUS_RUNNING,
            'started_at' => $now,
        ]);

        // 构造明显超过 4096 字节的结果（单字段 12KB 随机内容）。
        $huge = ['output' => str_repeat('x', 12288)];
        $ok = app(ScheduleTaskRunRepository::class)->markSucceeded((int) $run->id, $huge, 12);
        $this->assertTrue($ok, '台账状态迁移应成功');

        $stored = ScheduleTaskRun::query()->findOrFail((int) $run->id);
        $encoded = (string) json_encode($stored->summary, JSON_UNESCAPED_UNICODE);

        $this->assertLessThanOrEqual(
            4096,
            strlen($encoded),
            '台账 summary 落库后不得超过 4096 字节',
        );
    }

    public function test_small_summary_preserves_original_content(): void
    {
        $now = CarbonImmutable::now();
        $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($now);

        $run = ScheduleTaskRun::query()->create([
            'schedule_tick_id' => (int) $tick->id,
            'task_key' => 'summary-keep-probe',
            'task_name' => '台账摘要保持探针',
            'source' => 'heartbeat',
            'queue' => 'caiwu_schedule',
            'status' => ScheduleTaskRun::STATUS_RUNNING,
            'started_at' => $now,
        ]);

        $summary = ['processed' => 3, 'output' => '普通结果'];
        app(ScheduleTaskRunRepository::class)->markSucceeded((int) $run->id, $summary, 7);

        $stored = ScheduleTaskRun::query()->findOrFail((int) $run->id);

        // MySQL json 列不保留顶层键的书写顺序，因此比对内容而非顺序。
        $this->assertEquals($summary, $stored->summary, '未超限的结果必须原样保留，不得被改写');
        $this->assertArrayNotHasKey('__truncated', (array) $stored->summary, '未超限结果不应被降级为摘要结构');
    }

    /**
     * 2P0-C 指纹去重：同一 task_key 连续成功且 summary 一致 → 次行紧凑引用；
     * 内容变化 → 原样恢复。行本身逐次写入（timing 语义不动）。
     */
    public function test_repeated_summary_collapses_to_compact_reference(): void
    {
        $taskKey = 'summary-dedupe-probe-'.uniqid();
        $repository = app(ScheduleTaskRunRepository::class);
        $now = CarbonImmutable::now();

        // 唯一键 (schedule_tick_id, task_key, source)：三次运行必须落在不同槽位。
        $slot = 0;
        $makeRun = static function () use ($taskKey, $now, &$slot): int {
            // 槽位为 15 分钟粒度（900s），步长 20 分钟确保三次运行各落新槽。
            $tickAt = $now->modify('+'.(++$slot * 1200).' seconds');
            $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($tickAt);
            $run = ScheduleTaskRun::query()->create([
                'schedule_tick_id' => (int) $tick->id,
                'task_key' => $taskKey,
                'task_name' => '台账指纹去重探针',
                'source' => 'heartbeat',
                'queue' => 'caiwu_schedule',
                'status' => ScheduleTaskRun::STATUS_RUNNING,
                'started_at' => $tickAt,
            ]);

            return (int) $run->id;
        };

        // 隔离指纹缓存，避免同 task_key 其他用例泄漏。
        Cache::flush();

        // 第一次成功：无前驱，原样落库。
        $firstRunId = $makeRun();
        $summary = ['processed' => 5, 'orders_cancelled' => 0, 'output' => '常态结果'];
        $repository->markSucceeded($firstRunId, $summary, 11);
        $first = ScheduleTaskRun::query()->findOrFail($firstRunId);
        $this->assertEquals($summary, $first->summary, '首次成功必须原样保留完整 summary');

        // 第二次成功且 summary 完全一致：落紧凑引用。
        $secondRunId = $makeRun();
        $repository->markSucceeded($secondRunId, $summary, 12);
        $second = ScheduleTaskRun::query()->findOrFail($secondRunId);
        $this->assertTrue((bool) ($second->summary['__same_as_previous'] ?? false), '重复 summary 应收敛为紧凑引用');
        $this->assertSame($firstRunId, (int) ($second->summary['__previous_run_id'] ?? 0), '紧凑引用必须指向上一行');

        // 第三次成功且 summary 变化：原样恢复完整内容。
        $thirdRunId = $makeRun();
        $changed = ['processed' => 6, 'orders_cancelled' => 1, 'output' => '有新事件'];
        $repository->markSucceeded($thirdRunId, $changed, 13);
        $third = ScheduleTaskRun::query()->findOrFail($thirdRunId);
        $this->assertEquals($changed, $third->summary, '内容变化后必须恢复原样落库');
        $this->assertArrayNotHasKey('__same_as_previous', (array) $third->summary, '变化行不应带紧凑引用标记');
    }

    /**
     * 审查 P1-① 失败打断前驱链：成功→失败→相同 summary 成功时，
     * 恢复成功的行必须原样落完整 summary，紧凑引用不得跨过失败行。
     */
    public function test_failure_resets_summary_fingerprint(): void
    {
        $taskKey = 'summary-fp-reset-probe-'.uniqid();
        $repository = app(ScheduleTaskRunRepository::class);
        $now = CarbonImmutable::now();

        $slot = 0;
        $makeRun = static function (string $status) use ($taskKey, $now, &$slot): int {
            $tickAt = $now->modify('+'.(++$slot * 1200).' seconds');
            $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($tickAt);
            $run = ScheduleTaskRun::query()->create([
                'schedule_tick_id' => (int) $tick->id,
                'task_key' => $taskKey,
                'task_name' => '台账指纹重置探针',
                'source' => 'heartbeat',
                'queue' => 'caiwu_schedule',
                'status' => $status,
                'started_at' => $tickAt,
            ]);

            return (int) $run->id;
        };

        Cache::flush();

        // 第一次成功：记住指纹。
        $firstRunId = $makeRun(ScheduleTaskRun::STATUS_RUNNING);
        $summary = ['processed' => 4, 'state' => 'normal'];
        $repository->markSucceeded($firstRunId, $summary, 21);

        // 第二次执行失败：必须清除指纹（失败打断前驱链）。
        $secondRunId = $makeRun(ScheduleTaskRun::STATUS_RUNNING);
        $repository->markRetrying($secondRunId, '临时故障', 22);
        $repository->markTerminalFailed($secondRunId, '重试耗尽', 23);

        // 第三次成功且 summary 与第一次完全一致：指纹已被失败清除，
        // 必须原样落完整 summary——紧凑引用不得跨过失败行。
        $thirdRunId = $makeRun(ScheduleTaskRun::STATUS_RUNNING);
        $repository->markSucceeded($thirdRunId, $summary, 24);
        $third = ScheduleTaskRun::query()->findOrFail($thirdRunId);
        $this->assertEquals($summary, $third->summary, '失败后的恢复成功必须落完整 summary');
        $this->assertArrayNotHasKey('__same_as_previous', (array) $third->summary, '紧凑引用不得跨过失败行');
    }

    /**
     * 审查 P1-② CAS 拒绝不记忆指纹：markSucceeded 更新失败（行已被
     * 并发终态化）时不得写入指纹，避免后续 __previous_run_id 指向
     * 从未成功落库的 run。
     */
    public function test_cas_rejection_does_not_remember_fingerprint(): void
    {
        $taskKey = 'summary-cas-reject-probe-'.uniqid();
        $repository = app(ScheduleTaskRunRepository::class);
        $now = CarbonImmutable::now();

        $slot = 0;
        $makeRun = static function (string $status) use ($taskKey, $now, &$slot): int {
            $tickAt = $now->modify('+'.(++$slot * 1200).' seconds');
            $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($tickAt);
            $run = ScheduleTaskRun::query()->create([
                'schedule_tick_id' => (int) $tick->id,
                'task_key' => $taskKey,
                'task_name' => '台账CAS拒绝探针',
                'source' => 'heartbeat',
                'queue' => 'caiwu_schedule',
                'status' => $status,
                'started_at' => $tickAt,
            ]);

            return (int) $run->id;
        };

        Cache::flush();

        // 第一次成功：合法记住 fp→run1。
        $firstRunId = $makeRun(ScheduleTaskRun::STATUS_RUNNING);
        $summary = ['processed' => 7, 'state' => 'steady'];
        $this->assertTrue($repository->markSucceeded($firstRunId, $summary, 31));

        // 第二次：行直接以 failed 终态创建（绕过 markTerminalFailed 的 forget，
        // 模拟「并发终态化后 markSucceeded 才到达」），CAS 必须拒绝。
        $secondRunId = $makeRun(ScheduleTaskRun::STATUS_FAILED);
        $this->assertFalse(
            $repository->markSucceeded($secondRunId, $summary, 32),
            '非 running 行的 markSucceeded 必须被 CAS 拒绝',
        );

        // 第三次成功且 summary 一致：紧凑引用必须指向 run1（真实成功行），
        // 不得指向 run2（从未成功落库的行）。
        $thirdRunId = $makeRun(ScheduleTaskRun::STATUS_RUNNING);
        $repository->markSucceeded($thirdRunId, $summary, 33);
        $third = ScheduleTaskRun::query()->findOrFail($thirdRunId);
        $this->assertTrue((bool) ($third->summary['__same_as_previous'] ?? false), '仍应对合法前驱去重');
        $this->assertSame(
            $firstRunId,
            (int) ($third->summary['__previous_run_id'] ?? 0),
            '紧凑引用必须指向真实成功行，CAS 拒绝的 run 不得被记忆',
        );
    }

    /**
     * 链深锁定（审查补发现）：连续三次相同 summary 成功时，紧凑引用必须
     * 恒指向链头（最近一次内容变化的首行），不得形成 run3→run2→run1 的
     * 链式引用。引用行不得刷新指纹链头。
     */
    public function test_repeated_reference_stays_flat_to_chain_head(): void
    {
        $taskKey = 'summary-chain-head-probe-'.uniqid();
        $repository = app(ScheduleTaskRunRepository::class);
        $now = CarbonImmutable::now();

        $slot = 0;
        $makeRun = static function () use ($taskKey, $now, &$slot): int {
            $tickAt = $now->modify('+'.(++$slot * 1200).' seconds');
            $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($tickAt);
            $run = ScheduleTaskRun::query()->create([
                'schedule_tick_id' => (int) $tick->id,
                'task_key' => $taskKey,
                'task_name' => '台账链深锁定探针',
                'source' => 'heartbeat',
                'queue' => 'caiwu_schedule',
                'status' => ScheduleTaskRun::STATUS_RUNNING,
                'started_at' => $tickAt,
            ]);

            return (int) $run->id;
        };

        Cache::flush();

        $summary = ['processed' => 5, 'state' => 'steady'];
        $firstRunId = $makeRun();
        $repository->markSucceeded($firstRunId, $summary, 41);
        $secondRunId = $makeRun();
        $repository->markSucceeded($secondRunId, $summary, 42);
        $thirdRunId = $makeRun();
        $repository->markSucceeded($thirdRunId, $summary, 43);

        $second = ScheduleTaskRun::query()->findOrFail($secondRunId);
        $third = ScheduleTaskRun::query()->findOrFail($thirdRunId);

        $this->assertSame(
            $firstRunId,
            (int) ($second->summary['__previous_run_id'] ?? 0),
            '第二次成功应指向链头',
        );
        $this->assertSame(
            $firstRunId,
            (int) ($third->summary['__previous_run_id'] ?? 0),
            '第三次成功必须仍指向链头，不得指向引用行形成链式引用',
        );
    }

    /**
     * 审查 P2-④ 呈现层翻译：紧凑引用的 summary 通过 Resource 输出时，
     * 不得裸露 __same_as_previous/__previous_run_id 内部哨兵键，
     * 而应翻译为语义结构（note + ref_run_id）。
     */
    public function test_resource_translates_dedup_markers_to_semantic_structure(): void
    {
        $taskKey = 'resource-translate-probe-'.uniqid();
        $repository = app(ScheduleTaskRunRepository::class);
        $now = CarbonImmutable::now();

        $slot = 0;
        $makeRun = static function () use ($taskKey, $now, &$slot): int {
            $tickAt = $now->modify('+'.(++$slot * 1200).' seconds');
            $tick = app(ScheduleTickRepository::class)->firstOrCreateSlot($tickAt);
            $run = ScheduleTaskRun::query()->create([
                'schedule_tick_id' => (int) $tick->id,
                'task_key' => $taskKey,
                'task_name' => '台账呈现翻译探针',
                'source' => 'heartbeat',
                'queue' => 'caiwu_schedule',
                'status' => ScheduleTaskRun::STATUS_RUNNING,
                'started_at' => $tickAt,
            ]);

            return (int) $run->id;
        };

        Cache::flush();

        $summary = ['processed' => 8, 'state' => 'repeat'];
        $firstRunId = $makeRun();
        $repository->markSucceeded($firstRunId, $summary, 51);
        $secondRunId = $makeRun();
        $repository->markSucceeded($secondRunId, $summary, 52);

        $secondRun = ScheduleTaskRun::query()->findOrFail($secondRunId);
        $payload = AdminScheduleTaskRunResource::make($secondRun)->resolve();

        $this->assertIsArray($payload['summary'], 'summary 应为数组');
        $this->assertArrayNotHasKey('__same_as_previous', $payload['summary'], '内部哨兵键不得泄漏给前端');
        $this->assertArrayNotHasKey('__previous_run_id', $payload['summary'], '内部引用键不得泄漏给前端');
        $this->assertTrue((bool) ($payload['summary']['deduplicated'] ?? false), '应标记为去重');
        $this->assertSame($firstRunId, (int) ($payload['summary']['ref_run_id'] ?? 0), 'ref_run_id 应指向链头');
        $this->assertStringContainsString((string) $firstRunId, (string) ($payload['summary']['note'] ?? ''), 'note 应含引用运行编号');
    }
}
