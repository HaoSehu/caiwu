<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * 列宽收敛迁移（2026_10_25_120100）的护栏契约：
 * up/down 两侧的相对收窄名单必须显式声明，且方向互斥——up 收窄的列在 down 中放宽，
 * down 中收窄的列在 up 中放宽。凡是收窄方向都必须实测存量长度后才能执行。
 */
class DatabaseColumnConvergenceMigrationTest extends TestCase
{
    private function migration(): object
    {
        /** @var object $migration */
        $migration = require base_path('database/migrations/2026_10_25_120100_converge_same_domain_column_types.php');

        return $migration;
    }

    /**
     * @return list<array{table: string, column: string, length: int}>
     */
    private function constant(string $name): array
    {
        $reflection = new ReflectionClass($this->migration());
        $constant = $reflection->getReflectionConstant($name);
        $this->assertNotFalse($constant, $name.' 必须存在');

        return $constant->getValue();
    }

    #[Test]
    public function up_narrowings_cover_the_expected_columns(): void
    {
        $map = [];
        foreach ($this->constant('NARROWINGS') as $item) {
            $map[$item['table'].'.'.$item['column']] = $item['length'];
        }

        $this->assertSame([
            'archive_audit_logs.status' => 24,
            'schedule_task_runs.status' => 24,
            'coupons.trace_id' => 64,
            'coupon_campaigns.trace_id' => 64,
            'user_coupons.trace_id' => 64,
        ], $map);
    }

    #[Test]
    public function rollback_narrowings_cover_the_columns_that_shrink_on_rollback(): void
    {
        $map = [];
        foreach ($this->constant('ROLLBACK_NARROWINGS') as $item) {
            $map[$item['table'].'.'.$item['column']] = $item['length'];
        }

        // up() 对这 4 列是放宽（20→24、20→24、32→40、100→128），
        // 因此 down() 相对当前宽度是收窄，回滚前必须逐列实测存量长度。
        $this->assertSame([
            'schedule_run_logs.status' => 20,
            'message_logs.status' => 20,
            'refunds.refund_no' => 32,
            'payments.trade_no' => 100,
        ], $map);

        $up = [];
        foreach ($this->constant('NARROWINGS') as $item) {
            $up[$item['table'].'.'.$item['column']] = $item['length'];
        }

        // 与 up 的收窄名单必须互斥：这 4 列在 up 方向是放宽，若出现在 NARROWINGS 里
        // 说明两侧目标宽度写反了，护栏参数也不再对应真实方向。
        foreach (array_keys($map) as $key) {
            $this->assertArrayNotHasKey($key, $up, $key.' 不应同时出现在 up 的收窄名单中');
        }
    }

    #[Test]
    public function both_directions_invoke_the_truncation_guard(): void
    {
        $source = (string) file_get_contents(base_path('database/migrations/2026_10_25_120100_converge_same_domain_column_types.php'));

        $this->assertStringContainsString('$this->assertNoTruncation(self::NARROWINGS);', $source);
        $this->assertStringContainsString('$this->assertNoTruncation(self::ROLLBACK_NARROWINGS);', $source);
        $this->assertStringContainsString('private function assertNoTruncation(array $targets): void', $source);
    }
}
