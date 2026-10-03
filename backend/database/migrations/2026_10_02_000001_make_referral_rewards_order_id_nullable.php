<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// referral_rewards.order_id 放宽为可空：修复"无订单账单"的推荐奖励必然写入失败。
// ReferralService 的 invoice-only 路径只写 invoice_id 不写 order_id，而本列此前
// 为 NOT NULL 无默认值且 strict 模式开启，insert 必然抛"Field 'order_id' doesn't
// have a default value"，异常只记日志不重抛，奖励静默丢失。
//
// 为什么放宽列而不是默认写 0：order_id 上有 UNIQUE 索引 referral_rewards_order_id_unique，
// 默认写 0 会导致全库只能存在一条 invoice-only 奖励记录。可空后 MySQL 唯一索引
// 允许多个 NULL，订单路径的唯一性约束照旧生效。
// 本迁移只放宽约束、不删列不删索引、不丢数据，且幂等可重复执行。
return new class extends Migration
{
    public function up(): void
    {
        if ($this->orderIdIsNullable()) {
            return;
        }

        DB::statement('ALTER TABLE `referral_rewards` MODIFY `order_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (! $this->orderIdIsNullable()) {
            return;
        }

        // 刻意不删数据：推荐奖励是资金相关记录，回滚脚本无权替业务做处置决定。
        // 存在 order_id 为空的奖励记录时中止并报出数量，由人工核对后再回滚。
        $orphanCount = (int) DB::table('referral_rewards')->whereNull('order_id')->count();

        throw_if($orphanCount > 0, new RuntimeException(sprintf(
            'referral_rewards 存在 %d 条 order_id 为空的记录（无订单账单的奖励），'
            .'回滚为 NOT NULL 会丢失这些资金记录，请先人工核对处置后再回滚',
            $orphanCount
        )));

        DB::statement('ALTER TABLE `referral_rewards` MODIFY `order_id` BIGINT UNSIGNED NOT NULL');
    }

    // 查 information_schema 判断 order_id 当前是否可空，保证 up/down 幂等。
    // 表不存在时视为无需处理，避免全新库按迁移顺序执行到本文件时中断。
    private function orderIdIsNullable(): bool
    {
        if (! Schema::hasTable('referral_rewards')) {
            return true;
        }

        $column = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['referral_rewards', 'order_id']
        );

        return $column !== null && strtoupper((string) $column->IS_NULLABLE) === 'YES';
    }
};
