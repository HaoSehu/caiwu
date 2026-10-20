<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * T+7 归档第二步：旧财务表改名保留（不 DROP，留审计口径与意外回查）。
 *
 * account_transactions → archived_account_transactions
 * refunds              → archived_refunds
 *
 * 护栏：未设置 FINANCE_ALLOW_RETIRE=true 时静默跳过（同 000015）。
 * 前置依赖：000013 已把 recharge_records 外键重指到 finance_ledger_entries，
 * 否则本迁移会因外键引用改名失败——这是刻意设计的顺序约束。
 */
return new class extends Migration
{
    private const RENAMES = [
        'account_transactions' => 'archived_account_transactions',
        'refunds' => 'archived_refunds',
    ];

    public function up(): void
    {
        if (! $this->retirementEnabled('up')) {
            return;
        }

        foreach (self::RENAMES as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
    }

    public function down(): void
    {
        if (! $this->retirementEnabled('down')) {
            return;
        }

        foreach (self::RENAMES as $from => $to) {
            if (Schema::hasTable($to) && ! Schema::hasTable($from)) {
                Schema::rename($to, $from);
            }
        }
    }

    private function retirementEnabled(string $direction): bool
    {
        if ((bool) env('FINANCE_ALLOW_RETIRE', false)) {
            return true;
        }

        // 注意：匿名迁移类没有 $this->command（访问即 ErrorException），输出统一走日志通道
        logger()->warning(sprintf(
            '[T+7] 000016 %s skipped: FINANCE_ALLOW_RETIRE=true not set (run per Runbook 7 days after stable cutover)',
            $direction,
        ));

        return false;
    }
};
