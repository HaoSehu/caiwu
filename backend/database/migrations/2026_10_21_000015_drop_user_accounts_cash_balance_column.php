<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T+7 归档第一步：删除 user_accounts.cash_balance（现金真源已完成迁移）。
 *
 * 护栏设计（双保险，缺一不可）：
 * 1. 未设置 FINANCE_ALLOW_RETIRE=true 时静默跳过（只告警不执行）——避免发版/测试环境
 *    的常规 migrate 误删列；跳过时迁移记录照常落库，T+7 按 Runbook 用
 *    rollback（no-op）+ migrate（带环境变量）两步重放；
 * 2. 即使设置了 ALLOW_RETIRE，FINANCE_HOT_STANDBY 仍开启时硬失败——
 *    内核热备还在写该列，删列会让所有资金写入报错。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->retirementEnabled('up')) {
            return;
        }

        if ((bool) config('finance.hot_standby', true)) {
            throw new RuntimeException(
                'FINANCE_HOT_STANDBY 仍为开启：内核热备还在写 user_accounts.cash_balance，'
                .'先置 FINANCE_HOT_STANDBY=false 并 config:cache 后再执行本迁移',
            );
        }

        Schema::table('user_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('user_accounts', 'cash_balance')) {
                $table->dropColumn('cash_balance');
            }
        });
    }

    public function down(): void
    {
        if (! $this->retirementEnabled('down')) {
            return;
        }

        Schema::table('user_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_accounts', 'cash_balance')) {
                $table->decimal('cash_balance', 20, 2)->default(0)->comment('[已退役] 切换期热备列；真源 finance_accounts');
            }
        });
    }

    private function retirementEnabled(string $direction): bool
    {
        if ((bool) env('FINANCE_ALLOW_RETIRE', false)) {
            return true;
        }

        // 注意：匿名迁移类没有 $this->command（访问即 ErrorException），输出统一走日志通道
        logger()->warning(sprintf(
            '[T+7] 000015 %s skipped: FINANCE_ALLOW_RETIRE=true not set (run per Runbook 7 days after stable cutover)',
            $direction,
        ));

        return false;
    }
};
