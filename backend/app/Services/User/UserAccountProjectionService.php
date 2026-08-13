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
        $missingRows = $this->missingUsersQuery()
            ->orderBy('u.id')
            ->get();

        if (! $execute) {
            return [
                'dry_run' => true,
                'inserted' => 0,
                'backup_path' => null,
                'before' => $before,
                'after' => $before,
            ];
        }

        $backupPath = $this->writeBackup($missingRows->all());
        $inserted = 0;
        $lastId = 0;

        DB::transaction(function () use (&$inserted, &$lastId, $chunkSize): void {
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

                foreach ($rows as $row) {
                    $lastId = max($lastId, (int) $row->id);
                    $payload[] = $this->buildAccountPayload((object) $row, $now);
                }

                if ($payload !== []) {
                    $inserted += DB::table('user_accounts')->insertOrIgnore($payload);
                }
            } while (true);
        });

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
     * @param  array<int, object>  $rows
     */
    private function writeBackup(array $rows): string
    {
        $directory = storage_path('app/account-user-account-backfills');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/user_accounts_missing_'.now()->format('Ymd_His').'.json';
        $payload = [
            'generated_at' => now()->toISOString(),
            'count' => count($rows),
            'rows' => array_map(function (object $row): array {
                return [
                    'user_id' => (int) $row->id,
                    'user_account_payload' => $this->buildAccountPayload($row, now()->toDateTimeString()),
                ];
            }, $rows),
        ];

        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $path;
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
