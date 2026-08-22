<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillActivityLogsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('activity_logs')->where('event_id', 'like', 'oplog:%')->delete();
        DB::table('activity_logs')->where('event_id', 'backfill-test-anchor')->delete();
        DB::table('operation_logs')->whereIn('ip_address', ['backfill-test-old', 'backfill-test-new'])->delete();
    }

    protected function tearDown(): void
    {
        DB::table('activity_logs')->where('event_id', 'like', 'oplog:%')->delete();
        DB::table('activity_logs')->where('event_id', 'backfill-test-anchor')->delete();
        DB::table('operation_logs')->whereIn('ip_address', ['backfill-test-old', 'backfill-test-new'])->delete();

        parent::tearDown();
    }

    public function test_dry_run_reports_pending_rows_without_writing(): void
    {
        $this->seedAnchorAndOperationRows();

        $this->artisan('log:backfill-activity')
            ->expectsOutputToContain('待回填 1')
            ->assertSuccessful();

        $this->assertSame(
            0,
            DB::table('activity_logs')->where('event_id', 'like', 'oplog:%')->count(),
            'dry-run 不得写入任何镜像行',
        );
    }

    public function test_execute_backfills_pre_window_rows_with_expected_mapping(): void
    {
        $operationId = $this->seedAnchorAndOperationRows();

        $this->artisan('log:backfill-activity', ['--execute' => true])
            ->expectsOutputToContain('回填完成')
            ->assertSuccessful();

        $mirror = DB::table('activity_logs')->where('event_id', 'oplog:'.$operationId)->first();

        $this->assertNotNull($mirror, '窗口前行应生成 oplog: 镜像');
        $this->assertSame('access', $mirror->stream);
        $this->assertSame('guest', $mirror->actor_type);
        $this->assertSame('GET api/v2/site/config', $mirror->action);
        $this->assertSame('site', $mirror->subject_type);
        $this->assertSame('anchor-trace-100', $mirror->trace_id);
        $this->assertSame('2026-06-01 10:00:00', substr((string) $mirror->created_at, 0, 19));
    }

    public function test_execute_is_idempotent_on_repeated_runs(): void
    {
        $operationId = $this->seedAnchorAndOperationRows();

        $this->artisan('log:backfill-activity', ['--execute' => true])->assertSuccessful();
        $this->artisan('log:backfill-activity', ['--execute' => true])
            ->expectsOutputToContain('本次新插入 0 行')
            ->assertSuccessful();

        $this->assertSame(
            1,
            DB::table('activity_logs')->where('event_id', 'oplog:'.$operationId)->count(),
            '重复执行不得产生第二份镜像',
        );
    }

    public function test_rows_within_dual_write_window_are_skipped(): void
    {
        $this->seedAnchorAndOperationRows();

        $this->artisan('log:backfill-activity', ['--execute' => true])->assertSuccessful();

        $newRow = DB::table('operation_logs')->where('ip_address', 'backfill-test-new')->first();

        $this->assertNotNull($newRow);
        $this->assertSame(
            0,
            DB::table('activity_logs')->where('event_id', 'oplog:'.$newRow->id)->count(),
            '双写期内的行由运行时镜像负责，回填不得重复补写',
        );
    }

    /**
     * 造窗口锚点（2026-07-01 的 activity 行）+ 两行 operation：窗口前 1 行、窗口内 1 行。
     *
     * @return int 确保回填的 operation_logs 行主键
     */
    private function seedAnchorAndOperationRows(): int
    {
        DB::table('activity_logs')->insert([
            'event_id' => 'backfill-test-anchor',
            'stream' => 'business',
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_name' => 'System',
            'module' => 'test',
            'action' => 'anchor',
            'description' => '[test] anchor',
            'subject_type' => 'test',
            'subject_id' => null,
            'context' => null,
            'ip_address' => null,
            'trace_id' => null,
            'created_at' => '2026-07-01 00:00:00',
            'updated_at' => '2026-07-01 00:00:00',
        ]);

        $oldId = DB::table('operation_logs')->insertGetId([
            'user_id' => null,
            'user_type' => 'guest',
            'action' => 'GET api/v2/site/config',
            'module' => 'site',
            'subject_id' => null,
            'context' => json_encode(['request_id' => 'anchor-trace-100', 'status' => 200]),
            'ip_address' => 'backfill-test-old',
            'created_at' => '2026-06-01 10:00:00',
        ]);

        DB::table('operation_logs')->insert([
            'user_id' => 1,
            'user_type' => 'client',
            'action' => 'service.power_on',
            'module' => 'service',
            'subject_id' => 5,
            'context' => json_encode(['trace_id' => 'runtime-mirror-owns']),
            'ip_address' => 'backfill-test-new',
            'created_at' => '2026-07-02 10:00:00',
        ]);

        return (int) $oldId;
    }
}
