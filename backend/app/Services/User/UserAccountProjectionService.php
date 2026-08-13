<?php

declare(strict_types=1);

namespace App\Services\User;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * user_accounts 账户真源巡检与回填工具。
 *
 * users 表的 balance/credit_limit/referral_* 旧列已迁移删除（余额真源为 user_accounts），
 * 本服务不再引用已删列：仅负责找出缺失账户的用户并回填默认账户行，供运维命令使用。
 */
class UserAccountProjectionService
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(int $sampleLimit = 20): array
    {
        $this->ensureTablesExist();

        return [
            'users_count' => DB::table('users')->count(),
            'user_accounts_count' => DB::table('user_accounts')->count(),
            'users_without_account' => $this->missingUsersQuery()->count(),
            'missing_user_ids' => $this->missingUsersQuery()
                ->orderBy('u.id')
                ->limit($sampleLimit)
                ->pluck('u.id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function backfill(bool $execute, int $chunkSize = 500): array
    {
        $this->ensureTablesExist();

        $chunkSize = max(1, $chunkSize);
        $before = $this->inspect();

        if (! $execute) {
            return [
                'dry_run' => true,
                'inserted' => 0,
                'backup_path' => null,
                'before' => $before,
                'after' => $before,
            ];
        }

        $directory = storage_path('app/account-user-account-backfills');
        File::ensureDirectoryExists($directory);

        $backupPath = $directory.'/user_accounts_missing_'.now()->format('Ymd_His').'.json';
        // 分块流式写出备份：避免一次性把全部缺失用户载入内存，大表回填更平稳。
        // count 在结尾收尾时统一写入，避免占位与真实值重复键。
        File::put($backupPath, '{"generated_at":'.json_encode(now()->toISOString(), JSON_UNESCAPED_SLASHES).',"rows":[');

        $inserted = 0;
        $lastId = 0;
        $backupRows = 0;
        $firstChunk = true;

        do {
            $rows = $this->missingUsersQuery()
                ->where('u.id', '>', $lastId)
                ->orderBy('u.id')
                ->limit($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $now = now()->toDateTimeString();
            $payload = [];
            $backupEntries = [];

            foreach ($rows as $row) {
                $lastId = max($lastId, (int) $row->id);
                $accountPayload = $this->buildAccountPayload((object) $row, $now);
                $payload[] = $accountPayload;
                $backupEntries[] = [
                    'user_id' => (int) $row->id,
                    'user_account_payload' => $accountPayload,
                ];
            }

            if (! $firstChunk) {
                File::append($backupPath, ',');
            }
            File::append($backupPath, json_encode($backupEntries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $backupRows += count($backupEntries);
            $firstChunk = false;

            if ($payload !== []) {
                // 每个分块独立提交，避免大表回填的长时间事务（undo/binlog 膨胀、锁持有）。
                $inserted += DB::transaction(static function () use ($payload): int {
                    return DB::table('user_accounts')->insertOrIgnore($payload);
                });
            }
        } while (true);

        File::append($backupPath, '],"count":'.$backupRows.'}');

        return [
            'dry_run' => false,
            'inserted' => $inserted,
            'backup_path' => $backupPath,
            'before' => $before,
            'after' => $this->inspect(),
        ];
    }

    private function ensureTablesExist(): void
    {
        foreach (['users', 'user_accounts'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException("缺少数据表：{$table}");
            }
        }
    }

    private function missingUsersQuery(): Builder
    {
        return DB::table('users as u')
            ->leftJoin('user_accounts as ua', 'ua.user_id', '=', 'u.id')
            ->whereNull('ua.user_id')
            ->select(['u.id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAccountPayload(object $row, string $timestamp): array
    {
        // users 旧余额列已删除，缺失账户按默认 0 回填；余额真源为 user_accounts。
        return [
            'user_id' => (int) $row->id,
            'cash_balance' => '0.00',
            'credit_limit' => '0.00',
            'referral_frozen_balance' => '0.00',
            'referral_available_balance' => '0.00',
            'referral_pending_withdrawal_balance' => '0.00',
            'referral_withdrawn_balance' => '0.00',
            'version' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }
}
