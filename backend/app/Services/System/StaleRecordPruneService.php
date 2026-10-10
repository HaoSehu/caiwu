<?php

declare(strict_types=1);

namespace App\Services\System;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 过期即无价值记录的批量清理（区别于日志归档：不导出、直接物理删除）。
 *
 * 覆盖三类：
 * - personal_access_tokens：过期/闲置令牌。闲置令牌在下次认证时会被
 *   AppServiceProvider 的 idle 检查自删，这里只是把"再也不会被认证触达"的行
 *   提前清掉，判定口径与认证时一致（last_used_at ?? created_at）。
 * - password_reset_tokens：超过 auth.passwords.users.expire（默认 60 分钟）即失效。
 * - failed_jobs：重放或放弃后即无保留价值，按 failed_at 保留窗口清理。
 *
 * 资金凭证、幂等状态等永不删除的表不在此列（见 config/log_archive.php excluded_tables）。
 */
class StaleRecordPruneService
{
    private const FAILED_JOBS_RETENTION_DAYS = 30;

    private const CHUNK_SIZE = 1000;

    /**
     * @return array{mode: string, tables: array<string, array{eligible: int, deleted: int}>, totals: array{eligible: int, deleted: int}}
     */
    public function prune(bool $dryRun = false): array
    {
        $targets = [
            'personal_access_tokens' => ['id', fn (): Builder => $this->staleAccessTokensQuery()],
            'password_reset_tokens' => ['email', fn (): Builder => $this->stalePasswordResetTokensQuery()],
            'failed_jobs' => ['id', fn (): Builder => $this->staleFailedJobsQuery()],
        ];

        $tables = [];
        $totalEligible = 0;
        $totalDeleted = 0;

        foreach ($targets as $table => [$keyColumn, $queryBuilder]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = $queryBuilder();
            $eligible = (int) $query->count();
            $deleted = 0;

            if (! $dryRun && $eligible > 0) {
                $deleted = $this->deleteByChunks($table, $query, $keyColumn);
            }

            $tables[$table] = ['eligible' => $eligible, 'deleted' => $deleted];
            $totalEligible += $eligible;
            $totalDeleted += $deleted;
        }

        return [
            'mode' => $dryRun ? 'dry_run' : 'execute',
            'tables' => $tables,
            'totals' => ['eligible' => $totalEligible, 'deleted' => $totalDeleted],
        ];
    }

    private function staleAccessTokensQuery(): Builder
    {
        $expirationMinutes = max((int) config('sanctum.expiration', 0), 0);
        $idleSeconds = max((int) config('sanctum.idle_timeout', 0), 0);

        return DB::table('personal_access_tokens')
            ->where(function (Builder $inner) use ($expirationMinutes, $idleSeconds): void {
                if ($expirationMinutes > 0) {
                    $inner->orWhere('expires_at', '<', now())
                        ->orWhere(function (Builder $legacy) use ($expirationMinutes): void {
                            // 旧令牌无 expires_at，按签发时间超过全局过期窗口清理。
                            $legacy->whereNull('expires_at')
                                ->where('created_at', '<', now()->subMinutes($expirationMinutes));
                        });
                }

                if ($idleSeconds > 0) {
                    // 与 AppServiceProvider 的 idle 判定同口径：last_used_at ?? created_at。
                    $inner->orWhereRaw('COALESCE(last_used_at, created_at) < ?', [now()->subSeconds($idleSeconds)]);
                }
            });
    }

    private function stalePasswordResetTokensQuery(): Builder
    {
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);
        $expireMinutes = $expireMinutes > 0 ? $expireMinutes : 60;

        return DB::table('password_reset_tokens')
            ->where('created_at', '<', now()->subMinutes($expireMinutes));
    }

    private function staleFailedJobsQuery(): Builder
    {
        return DB::table('failed_jobs')
            ->where('failed_at', '<', now()->subDays(self::FAILED_JOBS_RETENTION_DAYS));
    }

    /**
     * 分批按键删除，避免单条 DELETE 撞上大结果集长事务。
     */
    private function deleteByChunks(string $table, Builder $query, string $keyColumn): int
    {
        $deleted = 0;
        // orderBy 是追加语义，必须在循环外设置一次，避免多批后生成重复 ORDER BY。
        $query->orderBy($keyColumn);

        do {
            $keys = $query->limit(self::CHUNK_SIZE)->pluck($keyColumn);
            $count = $keys->count();
            if ($count === 0) {
                break;
            }

            $deleted += DB::table($table)->whereIn($keyColumn, $keys->all())->delete();
        } while ($count === self::CHUNK_SIZE);

        return $deleted;
    }
}
