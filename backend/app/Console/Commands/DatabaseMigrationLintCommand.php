<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Upgrade\MigrationLinter;
use Illuminate\Console\Command;
use Throwable;

/**
 * 迁移文件静态检查：命名规范 + 结构变更幂等守卫。
 *
 * 用于提交前自检与 CI 门禁：只读扫描，不接触数据库。
 * 退出码 0 通过 / 1 存在未豁免违规或执行错误。
 */
class DatabaseMigrationLintCommand extends Command
{
    protected $signature = 'db:migration-lint {--json : 以 JSON 输出违规清单}';

    protected $description = '检查迁移文件命名规范与结构变更幂等守卫';

    public function handle(MigrationLinter $linter): int
    {
        try {
            $violations = $linter->lint(
                database_path('migrations'),
                MigrationLinter::defaultExemptPath(),
            );
        } catch (Throwable $exception) {
            $this->error('迁移检查失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($violations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } elseif ($violations === []) {
            $this->line('<info>迁移检查通过</info>');
        } else {
            foreach ($violations as $violation) {
                $this->error($violation['file'].'：'.implode('；', $violation['violations']));
            }
        }

        return $violations === [] ? self::SUCCESS : self::FAILURE;
    }
}
