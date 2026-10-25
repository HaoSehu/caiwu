<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 同域同义列类型收敛（数据库表结构审查与优化方案 B6 / M3、M4）。
 *
 * 只处理「同域、同义、同名列」的宽度漂移，逐个域收敛：
 * - 归档域 status 统一 varchar(24)：archive_items 已是 24，收敛 archive_audit_logs(30)；
 * - 调度域 status 统一 varchar(24)：收敛 schedule_run_logs(20) 与 schedule_task_runs(30)；
 * - 消息域 status 统一 varchar(24)：收敛 message_logs(20)（message_delivery_attempts 已是 24）；
 * - 券域 trace_id 统一 varchar(64)：收敛 coupons / coupon_campaigns / user_coupons(100)；
 * - 退款单号 refund_no 统一 varchar(40)：refunds(32) 取宽一侧，避免对只读旧表做 COPY 级收窄；
 * - 第三方交易号 trade_no 统一 varchar(128)：payments(100) 扩到 gateway_logs 的宽度，避免新网关超长静默截断。
 *
 * 收窄安全性：status 取值由类常量约束（最长 dispatch_failed / needs_recovery = 14），
 * trace_id 在 LogOperation 中间件被 mb_substr 硬截断到 64；即便如此仍在迁移执行前
 * 对每个收窄列实测 MAX(CHAR_LENGTH)，超限直接抛错中止，绝不依赖 MySQL 的截断行为。
 * 回滚同理：down() 要把 5 列收回原宽度（其中 schedule_run_logs/message_logs 的 status
 * 与 refunds.refund_no、payments.trade_no 相对当前是收窄），因此 down() 首行也做同样实测。
 *
 * 幂等：先比 information_schema 的当前宽度，已是目标宽度则跳过。
 */
return new class extends Migration
{
    /**
     * 收窄项：{表, 列, 目标宽度}。执行前逐项实测存量最大长度。
     *
     * @var list<array{table: string, column: string, length: int}>
     */
    private const NARROWINGS = [
        ['table' => 'archive_audit_logs', 'column' => 'status', 'length' => 24],
        ['table' => 'schedule_task_runs', 'column' => 'status', 'length' => 24],
        ['table' => 'coupons', 'column' => 'trace_id', 'length' => 64],
        ['table' => 'coupon_campaigns', 'column' => 'trace_id', 'length' => 64],
        ['table' => 'user_coupons', 'column' => 'trace_id', 'length' => 64],
    ];

    /**
     * 回滚收窄项：down() 把列收回本迁移之前的宽度，其中 4 列相对当前宽度是收窄。
     *
     * @var list<array{table: string, column: string, length: int}>
     */
    private const ROLLBACK_NARROWINGS = [
        ['table' => 'schedule_run_logs', 'column' => 'status', 'length' => 20],
        ['table' => 'message_logs', 'column' => 'status', 'length' => 20],
        ['table' => 'refunds', 'column' => 'refund_no', 'length' => 32],
        ['table' => 'payments', 'column' => 'trade_no', 'length' => 100],
    ];

    public function up(): void
    {
        $this->assertNoTruncation(self::NARROWINGS);

        if ($this->needsChange('archive_audit_logs', 'status', 24)) {
            Schema::table('archive_audit_logs', function (Blueprint $table): void {
                $table->string('status', 24)->default('running')->change();
            });
        }

        if ($this->needsChange('schedule_run_logs', 'status', 24)) {
            Schema::table('schedule_run_logs', function (Blueprint $table): void {
                $table->string('status', 24)
                    ->default('success')
                    ->comment('执行状态: success, failed, skipped')
                    ->change();
            });
        }

        if ($this->needsChange('schedule_task_runs', 'status', 24)) {
            Schema::table('schedule_task_runs', function (Blueprint $table): void {
                $table->string('status', 24)->default('queued')->change();
            });
        }

        if ($this->needsChange('message_logs', 'status', 24)) {
            Schema::table('message_logs', function (Blueprint $table): void {
                $table->string('status', 24)->default('pending')->comment('发送状态')->change();
            });
        }

        foreach (['coupons', 'coupon_campaigns', 'user_coupons'] as $couponTable) {
            if ($this->needsChange($couponTable, 'trace_id', 64)) {
                Schema::table($couponTable, function (Blueprint $table): void {
                    $table->string('trace_id', 64)->nullable()->change();
                });
            }
        }

        if ($this->needsChange('refunds', 'refund_no', 40)) {
            Schema::table('refunds', function (Blueprint $table): void {
                $table->string('refund_no', 40)->change();
            });
        }

        if ($this->needsChange('payments', 'trade_no', 128)) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->string('trade_no', 128)->nullable()->comment('第三方交易号')->change();
            });
        }
    }

    public function down(): void
    {
        // 回滚路径同样可能截断：先实测再改，避免超长值被 MySQL 截断或报错中断。
        $this->assertNoTruncation(self::ROLLBACK_NARROWINGS);

        if ($this->needsChange('archive_audit_logs', 'status', 30)) {
            Schema::table('archive_audit_logs', function (Blueprint $table): void {
                $table->string('status', 30)->default('running')->change();
            });
        }

        if ($this->needsChange('schedule_run_logs', 'status', 20)) {
            Schema::table('schedule_run_logs', function (Blueprint $table): void {
                $table->string('status', 20)
                    ->default('success')
                    ->comment('执行状态: success, failed, skipped')
                    ->change();
            });
        }

        if ($this->needsChange('schedule_task_runs', 'status', 30)) {
            Schema::table('schedule_task_runs', function (Blueprint $table): void {
                $table->string('status', 30)->default('queued')->change();
            });
        }

        if ($this->needsChange('message_logs', 'status', 20)) {
            Schema::table('message_logs', function (Blueprint $table): void {
                $table->string('status', 20)->default('pending')->comment('发送状态')->change();
            });
        }

        foreach (['coupons', 'coupon_campaigns', 'user_coupons'] as $couponTable) {
            if ($this->needsChange($couponTable, 'trace_id', 100)) {
                Schema::table($couponTable, function (Blueprint $table): void {
                    $table->string('trace_id', 100)->nullable()->change();
                });
            }
        }

        if ($this->needsChange('refunds', 'refund_no', 32)) {
            Schema::table('refunds', function (Blueprint $table): void {
                $table->string('refund_no', 32)->change();
            });
        }

        if ($this->needsChange('payments', 'trade_no', 100)) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->string('trade_no', 100)->nullable()->comment('第三方交易号')->change();
            });
        }
    }

    /**
     * 收窄前列出所有将被截断的存量值，发现超限立即中止，避免 MySQL 静默截断。
     *
     * @param  list<array{table: string, column: string, length: int}>  $targets
     */
    private function assertNoTruncation(array $targets): void
    {
        foreach ($targets as $target) {
            $table = $target['table'];
            $column = $target['column'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $current = $this->columnLength($table, $column);

            if ($current === null || $current <= $target['length']) {
                continue;
            }

            /** @var int|null $maxLength */
            $maxLength = DB::table($table)->selectRaw(sprintf('MAX(CHAR_LENGTH(`%s`)) AS max_length', $column))->value('max_length');

            if ($maxLength !== null && (int) $maxLength > $target['length']) {
                throw new RuntimeException(sprintf(
                    '拒绝收窄 %s.%s：存量最大长度 %d 超过目标 varchar(%d)，请先清理或改为放宽。',
                    $table,
                    $column,
                    (int) $maxLength,
                    $target['length'],
                ));
            }
        }
    }

    /**
     * 当前宽度与目标宽度不同且列存在时才执行变更。
     */
    private function needsChange(string $table, string $column, int $length): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        return $this->columnLength($table, $column) !== $length;
    }

    private function columnLength(string $table, string $column): ?int
    {
        $value = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->value('CHARACTER_MAXIMUM_LENGTH');

        return $value === null ? null : (int) $value;
    }
};
