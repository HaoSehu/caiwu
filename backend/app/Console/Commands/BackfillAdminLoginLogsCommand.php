<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

/**
 * 把历史 activity_logs(module=auth, action=admin.login) 回填为
 * admin_login_logs 记录，保证登录日志切源后历史审计仍可见。
 *
 * 幂等键：legacy_activity_log_id = 源 activity_logs.id，依赖唯一索引，
 * 重复执行/中断重跑由 insertOrIgnore 自动跳过。
 *
 * 历史行只有成功登录（失败从未落库），因此 status 固定 1、failure_reason 为空；
 * 历史没有归纳后的浏览器/OS 数据，保持空串而不是伪造。
 * 源行不删除：activity_logs 仍按 activity 流清理/归档，回填是复制而非搬迁。
 */
class BackfillAdminLoginLogsCommand extends Command
{
    protected $signature = 'log:backfill-admin-login-logs
        {--execute : 实际写入；缺省为 dry-run，仅输出待回填数量}
        {--chunk=1000 : 每批处理的 activity_logs 行数}';

    protected $description = '把历史管理员登录审计（activity_logs admin.login）幂等回填到 admin_login_logs';

    public function handle(): int
    {
        if (! Schema::hasTable('activity_logs')
            || ! Schema::hasTable('admin_login_logs')
            || ! Schema::hasColumn('admin_login_logs', 'legacy_activity_log_id')) {
            $this->error('[log:backfill-admin-login-logs] 缺少 activity_logs / admin_login_logs.legacy_activity_log_id，'
                .'请先执行迁移。');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $total = $this->sourceQuery()->count();
        $already = (int) DB::table('admin_login_logs')->whereNotNull('legacy_activity_log_id')->count();

        $this->info("历史登录审计总数: {$total}（已回填 {$already}）");

        if ($total === 0) {
            $this->info('activity_logs 中没有 admin.login 历史记录，无需回填。');

            return self::SUCCESS;
        }

        $scanned = 0;
        $inserted = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');

        $this->sourceQuery()
            ->orderBy('id')
            ->chunkById($chunkSize, function (iterable $rows) use ($execute, &$scanned, &$inserted, $bar): void {
                $payload = [];
                foreach ($rows as $row) {
                    $payload[] = $this->buildLoginLogRow($row);
                }

                if ($payload === []) {
                    return;
                }

                $scanned += count($payload);
                if ($execute) {
                    $inserted += (int) DB::table('admin_login_logs')->insertOrIgnore($payload);
                }

                $bar->advance(count($payload));
            }, 'id');

        $bar->finish();
        $this->newLine(2);

        if ($execute) {
            $this->info("回填完成：扫描 {$scanned} 行，新插入 {$inserted} 行（已存在的 legacy 记录被唯一索引跳过）。");
        } else {
            $this->info("dry-run 完成：待扫描 {$scanned} 行。确认后加 --execute 执行。");
        }

        return self::SUCCESS;
    }

    /**
     * @return Builder
     */
    private function sourceQuery()
    {
        return DB::table('activity_logs')
            ->select(['id', 'actor_id', 'actor_name', 'context', 'ip_address', 'created_at'])
            ->where('module', 'auth')
            ->where('action', 'admin.login');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLoginLogRow(stdClass $row): array
    {
        $context = json_decode((string) $row->context, true);
        $context = is_array($context) ? $context : [];

        $username = trim((string) ($context['admin_username'] ?? ''));
        if ($username === '') {
            $username = trim((string) ($row->actor_name ?? ''));
        }

        return [
            'legacy_activity_log_id' => (int) $row->id,
            'admin_user_id' => $row->actor_id !== null ? (int) $row->actor_id : null,
            'username' => mb_substr($username, 0, 64),
            'nickname' => mb_substr(trim((string) ($context['admin_nickname'] ?? '')), 0, 64),
            'role_name' => mb_substr(trim((string) ($context['role_name'] ?? '')), 0, 64),
            'status' => 1,
            'failure_reason' => '',
            'client_ip' => mb_substr(trim((string) ($row->ip_address ?? '')), 0, 45),
            // 历史记录没有归纳后的客户端环境，保持空串，避免伪造数据。
            'browser' => '',
            'operating_system' => '',
            'logged_at' => (string) $row->created_at,
            'created_at' => (string) $row->created_at,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
