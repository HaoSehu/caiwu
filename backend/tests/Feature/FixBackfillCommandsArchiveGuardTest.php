<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\SchemaMetadataCache;
use Illuminate\Database\Schema\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * M-72 回归：两个财务回填命令在 T+7 归档（000016 把旧表改名为 archived_*）后
 * 必须优雅跳过，而不是直接 SQLSTATE 42S02 崩溃。
 *
 * 缺陷背景：finance:backfill-refund-records 直查 refunds、finance:backfill-ledger-sources
 * 直查 account_transactions，均无表存在性护栏；对照命令
 * （BackfillOrderRefundAmountCommand / AuditLedgerConsistencyCommand）早已按表存在性适配。
 *
 * 用 Schema 局部 mock 模拟「归档后旧表不存在」的现场（其余表走真实探测）：
 * 命令必须返回 SUCCESS 并在输出中说明已退役，且不再查询旧表。
 */
class FixBackfillCommandsArchiveGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        SchemaMetadataCache::flush();
    }

    protected function tearDown(): void
    {
        SchemaMetadataCache::flush();

        parent::tearDown();
    }

    #[Test]
    public function refund_records_backfill_skips_gracefully_when_refunds_table_archived(): void
    {
        $this->fakeMissingTables(['refunds']);

        $exit = Artisan::call('finance:backfill-refund-records', ['--apply' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, '旧表归档后应优雅跳过而不是失败退出：'.$output);
        $this->assertStringContainsString('已归档', $output);
        $this->assertStringContainsString('已退役', $output);
    }

    #[Test]
    public function ledger_sources_backfill_skips_source_stage_when_legacy_table_archived(): void
    {
        $this->fakeMissingTables(['account_transactions']);

        $exit = Artisan::call('finance:backfill-ledger-sources', ['--with-source' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, '旧台账归档后 source 段应优雅跳过：'.$output);
        $this->assertStringContainsString('已归档', $output);
        $this->assertStringContainsString('已退役', $output);
        // 退役段不应产生待回填统计（candidates 保持 0）
        $this->assertStringContainsString('[source_type] 待回填=0 已处理=0 失败=0', $output);
    }

    #[Test]
    public function ledger_sources_bridge_stage_survives_legacy_table_archival_in_dry_run(): void
    {
        $this->fakeMissingTables(['account_transactions']);

        // --with-bridge 在 dry-run 下只扫描候选，不写库；归档后桥接段仍应可执行（不触旧表）。
        $exit = Artisan::call('finance:backfill-ledger-sources', ['--with-bridge' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, '归档后桥接段 dry-run 不应失败：'.$output);
        $this->assertStringContainsString('[recharge 桥接]', $output);
        $this->assertStringContainsString('[source_type] 待回填=0', $output);
    }

    /**
     * 局部 mock Schema facade：只让指定表探测为「不存在」，其余调用走真实实现。
     *
     * @param  list<string>  $missingTables
     */
    private function fakeMissingTables(array $missingTables): void
    {
        $real = Schema::getFacadeRoot();
        $this->assertInstanceOf(Builder::class, $real, 'Schema facade 根对象应为 Schema Builder');

        /** @var Mockery\MockInterface&Builder $mock */
        $mock = Mockery::mock($real)->makePartial();
        $mock->shouldReceive('hasTable')->andReturnUsing(
            static fn (string $table): bool => in_array($table, $missingTables, true) ? false : (bool) $real->hasTable($table)
        );

        Schema::swap($mock);
    }
}
