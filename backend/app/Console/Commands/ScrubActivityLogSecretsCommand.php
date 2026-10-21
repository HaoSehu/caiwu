<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 清理历史审计行里的网关凭据明文（B-07 修复配套）。
 *
 * 2026-10-21 前 LogOperation 只剔除密码/令牌族，integration_plugin 配置里的
 * merchant_private_key / platform_public_key / secret_key 等以明文写进了
 * activity_logs.context。修复落库侧之后，存量行仍需一次性清洗。
 *
 * 范围严格锁定：
 * - 只处理 module=integration_plugin（插件配置保存）；
 * - 只把凭据键的值替换为 [REDACTED]，不删行、不改其它字段；
 * - 默认 dry-run，--execute 才写库；--json 输出机器可读结果。
 */
class ScrubActivityLogSecretsCommand extends Command
{
    protected $signature = 'activity-log:scrub-secrets
        {--execute : 真正写库（默认 dry-run 只统计）}
        {--json : 以 JSON 输出}';

    protected $description = '清理历史审计行中网关/插件配置的凭据明文（默认 dry-run）';

    /** @var list<string> */
    private const CREDENTIAL_SUFFIXES = ['_key', '_secret', '_pem', '_cert'];

    public function handle(): int
    {
        if (! Schema::hasTable('activity_logs')) {
            $this->error('activity_logs 表不存在');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $rows = DB::table('activity_logs')
            ->where('module', 'integration_plugin')
            ->whereNotNull('context')
            ->orderBy('id')
            ->get(['id', 'context']);

        $scanned = 0;
        $matched = 0;
        $updated = 0;
        $details = [];

        foreach ($rows as $row) {
            $scanned++;
            $context = json_decode((string) $row->context, true);
            if (! is_array($context)) {
                continue;
            }

            $scrubbed = $this->scrub($context);
            if ($scrubbed === $context) {
                continue;
            }

            $matched++;
            $details[] = ['id' => (int) $row->id];

            if ($execute) {
                DB::table('activity_logs')
                    ->where('id', (int) $row->id)
                    ->update(['context' => json_encode($scrubbed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                $updated++;
            }
        }

        $result = [
            'dry_run' => ! $execute,
            'scanned' => $scanned,
            'matched' => $matched,
            'updated' => $updated,
            'rows' => $details,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info(sprintf(
                '%s：扫描 %d 行，命中 %d 行，写入 %d 行',
                $execute ? '已执行' : 'dry-run',
                $scanned,
                $matched,
                $updated,
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function scrub(array $data): array
    {
        $changed = false;
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $nested = $this->scrub($value);
                $changed = $changed || $nested !== $value;
                $result[$key] = $nested;

                continue;
            }

            if ($this->isCredentialKey((string) $key) && is_string($value) && $value !== '') {
                $result[$key] = '[REDACTED]';
                $changed = true;

                continue;
            }

            $result[$key] = $value;
        }

        return $changed ? $result : $data;
    }

    private function isCredentialKey(string $key): bool
    {
        $normalized = strtolower(trim($key));

        if (str_contains($normalized, 'password') || str_contains($normalized, 'token')) {
            return true;
        }

        if (str_contains($normalized, 'secret')) {
            return true;
        }

        foreach (self::CREDENTIAL_SUFFIXES as $suffix) {
            if (str_ends_with($normalized, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
