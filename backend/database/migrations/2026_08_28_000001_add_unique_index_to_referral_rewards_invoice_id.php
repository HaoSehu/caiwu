<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// referral_rewards.invoice_id 补唯一约束：invoice 来源返利发放的数据库级幂等兜底
// （缓存锁失效/降级时防双插）。invoice_id 可空，MySQL 唯一索引不约束 NULL，不影响订单维度记录。
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('referral_rewards')) {
            return;
        }

        $duplicates = DB::table('referral_rewards')
            ->select('invoice_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('invoice_id')
            ->groupBy('invoice_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('invoice_id');

        throw_if($duplicates->isNotEmpty(), new \RuntimeException(
            'referral_rewards 存在重复 invoice_id（'.implode(',', $duplicates->all()).'），需先人工合并奖励记录再加唯一索引'
        ));

        if ($this->indexExists('referral_rewards', 'referral_rewards_invoice_id_unique')) {
            return;
        }

        Schema::table('referral_rewards', function (Blueprint $table): void {
            $table->unique('invoice_id', 'referral_rewards_invoice_id_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('referral_rewards')) {
            return;
        }

        if (! $this->indexExists('referral_rewards', 'referral_rewards_invoice_id_unique')) {
            return;
        }

        Schema::table('referral_rewards', function (Blueprint $table): void {
            $table->dropUnique('referral_rewards_invoice_id_unique');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }
};
