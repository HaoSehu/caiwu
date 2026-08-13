<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\User\UserAccountProjectionService;
use Illuminate\Console\Command;

class BackfillUserAccountsCommand extends Command
{
    protected $signature = 'account:backfill-user-accounts
        {--dry-run : 只统计影响范围，不写入数据}
        {--execute : 执行回填写入；未指定时默认 dry-run}
        {--json : 以 JSON 输出结果}
        {--chunk=500 : 每批处理数量}';

    protected $description = '为缺失账户源行的用户回填默认 user_accounts 记录（余额真源，users 旧列已删除）';

    public function handle(UserAccountProjectionService $service): int
    {
        $execute = (bool) $this->option('execute') && ! (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $result = $service->backfill($execute, $chunkSize);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->info($execute ? '账户投影回填完成' : '账户投影回填预览');
            $this->line('- dry_run: '.($result['dry_run'] ? 'true' : 'false'));
            $this->line('- users_without_account_before: '.$result['before']['users_without_account']);
            $this->line('- inserted: '.$result['inserted']);
            $this->line('- users_without_account_after: '.$result['after']['users_without_account']);

            if ($result['backup_path']) {
                $this->line('- backup_path: '.$result['backup_path']);
            }

            if (! $execute) {
                $this->warn('默认 dry-run，实际未写入。确认影响面和备份策略后使用 --execute 执行。');
            }
        }

        return self::SUCCESS;
    }
}
