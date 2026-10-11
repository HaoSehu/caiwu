<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\PluginConsoleRegistrationAuditor;
use App\Services\Integrations\Plugins\PluginManifest;
use App\Services\Integrations\Plugins\PluginScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 插件命令注册后置审计（B1b，2026-10-10 用户裁决；2026-10-11 增加清单声明豁免）。
 *
 * 插件 Provider 不得注册系统级组件，控制台命令同属禁止项。boot 期统计会在
 * Provider 注册阶段触发 Kernel::bootstrap()，导致应用命令未注册（已实证并
 * 回退），因此改由本命令在控制台启动完成后对 Artisan 注册面做只读审计：
 * 插件来源命令若已在清单 extra.console_commands 显式声明（安装/启用时校验过
 * 类存在且位于插件目录内），视为受控豁免；未声明的插件来源命令报违规并返回
 * 退出码 1，供人工巡检或 CI 使用。
 */
class PluginRegistrationAuditCommand extends Command
{
    protected $signature = 'plugins:audit-registrations
        {--json : 以 JSON 输出结果}';

    protected $description = '审计插件命令注册：未在清单声明 extra.console_commands 的插件来源命令报违规';

    public function handle(PluginConsoleRegistrationAuditor $auditor, PluginScanner $scanner): int
    {
        $application = $this->getApplication();

        if ($application === null) {
            $this->error('控制台应用未就绪，无法执行注册审计。');

            return self::FAILURE;
        }

        $allowlist = $this->declaredConsoleCommands($scanner);
        $violations = $auditor->violationsFor($application->all(), base_path('plugins'), $allowlist);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'violations' => $violations,
                'count' => count($violations),
                'allowlisted' => $allowlist,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $violations === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($violations === []) {
            $this->info('审计通过：未发现未声明的插件来源控制台命令（已声明豁免 '.count($allowlist).' 个）。');

            return self::SUCCESS;
        }

        $this->error('发现 '.count($violations).' 个由插件注册且未在清单 extra.console_commands 声明的控制台命令：');

        foreach ($violations as $violation) {
            $this->line(sprintf('- %s（%s）', $violation['name'], $violation['class']));
        }

        return self::FAILURE;
    }

    /**
     * 汇总已启用插件清单中显式声明的控制台命令类。
     *
     * @return array<int, string>
     */
    private function declaredConsoleCommands(PluginScanner $scanner): array
    {
        $allowlist = [];

        try {
            $plugins = IntegrationPlugin::query()
                ->where('status', IntegrationPlugin::STATUS_ENABLED)
                ->get(['domain', 'slug']);
        } catch (Throwable $exception) {
            Log::warning('[plugins] 审计豁免清单读取失败，按无豁免处理', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        foreach ($plugins as $plugin) {
            try {
                $manifest = $scanner->find((string) $plugin->domain, (string) $plugin->slug);
                if (! $manifest instanceof PluginManifest) {
                    continue;
                }

                foreach ($manifest->extra['console_commands'] ?? [] as $declared) {
                    $class = is_string($declared) ? trim($declared) : trim((string) ($declared['class'] ?? ''));
                    if ($class !== '') {
                        $allowlist[] = $class;
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('[plugins] 审计豁免清单读取失败，按无豁免处理', [
                    'domain' => $plugin->domain,
                    'slug' => $plugin->slug,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return array_values(array_unique($allowlist));
    }
}
