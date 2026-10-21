<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FinanceOperation;
use App\Support\SchemaMetadataCache;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 清理过期财务幂等记录（config/finance.php 的 idempotency.ttl_days 承诺的命令）。
 *
 * 幂等表 finance_operations 会随退款/充值等操作持续增长；超过保留期的
 * SUCCEEDED 行可安全删除（重放已不可能命中），FAILED 行同样到期清理。
 * 默认 dry-run，--execute 才写库。
 */
class PruneFinanceOperationsCommand extends Command
{
    protected $signature = 'finance:prune-operations
        {--execute : 真正删除（默认 dry-run 只统计）}
        {--days= : 覆盖保留天数（默认取 config finance.idempotency.ttl_days）}';

    protected $description = '清理过期财务幂等记录（默认 dry-run）';

    public function handle(): int
    {
        if (! SchemaMetadataCache::hasTable('finance_operations')) {
            $this->error('finance_operations 表不存在');

            return self::FAILURE;
        }

        $days = (int) ($this->option('days') ?: config('finance.idempotency.ttl_days', 30));
        if ($days <= 0) {
            $this->error('保留天数必须大于 0');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);
        $query = FinanceOperation::query()->where('created_at', '<', $cutoff);
        $candidates = (clone $query)->count();

        if (! (bool) $this->option('execute')) {
            $this->info(sprintf('dry-run：%d 条早于 %s 的幂等记录待清理（保留 %d 天）', $candidates, $cutoff->toDateTimeString(), $days));

            return self::SUCCESS;
        }

        $deleted = 0;
        $query->orderBy('id')->chunkById(500, function ($rows) use (&$deleted): void {
            $deleted += FinanceOperation::query()->whereIn('id', $rows->pluck('id')->all())->delete();
        });

        $this->info(sprintf('已清理 %d 条幂等记录（保留 %d 天）', $deleted, $days));

        return self::SUCCESS;
    }
}
