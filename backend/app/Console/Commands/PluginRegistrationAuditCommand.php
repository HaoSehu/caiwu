<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Integrations\Plugins\PluginConsoleRegistrationAuditor;
use Illuminate\Console\Command;

/**
 * 插件命令注册后置审计（B1b，2026-10-10 用户裁决）。
 *
 * 插件 Provider 不得注册系统级组件，控制台命令同属禁止项。boot 期统计会在
 * Provider 注册阶段触发 Kernel::bootstrap()，导致应用命令未注册（已实证并
 * 回退），因此改由本命令在控制台启动完成后对 Artisan 注册面做只读审计：
 * 发现插件来源的命令即报违规并返回退出码 1，供人工巡检或 CI 使用；
 * 已知例外（如 zjmf_finance 的历史命令）不做强制摘除，仅保持可见。
 */
class PluginRegistrationAuditCommand extends Command
{
    protected $signature = 'plugins:audit-registrations
        {--json : 以 JSON 输出结果}';

    protected $description = '审计插件命令注册：插件 Provider 不得注册控制台命令（发现即报违规）';

    public function handle(PluginConsoleRegistrationAuditor $auditor): int
    {
        $application = $this->getApplication();

        if ($application === null) {
            $this->error('控制台应用未就绪，无法执行注册审计。');

            return self::FAILURE;
        }

        $violations = $auditor->violationsFor($application->all(), base_path('plugins'));

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'violations' => $violations,
                'count' => count($violations),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $violations === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($violations === []) {
            $this->info('审计通过：未发现插件注册的控制台命令。');

            return self::SUCCESS;
        }

        $this->error('发现 '.count($violations).' 个由插件注册的控制台命令（违反插件边界：插件不得注册系统级命令）：');

        foreach ($violations as $violation) {
            $this->line(sprintf('- %s（%s）', $violation['name'], $violation['class']));
        }

        return self::FAILURE;
    }
}
