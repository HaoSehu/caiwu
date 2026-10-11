<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use ReflectionClass;
use Symfony\Component\Console\Command\Command;

/**
 * 插件控制台命令注册审计器（B1b，2026-10-10 用户裁决）。
 *
 * 插件边界约束：插件 Provider 不得注册系统级组件，控制台命令同属禁止项。
 * 运行时守卫（PluginProviderRegistry::assertNoSystemLevelRegistration）只覆盖
 * 路由/调度/中间件——boot 期统计控制台命令会提前触发 Kernel::bootstrap()，
 * 导致应用命令未注册（已实证回退）；本审计器改在控制台启动完成后执行，
 * 由 `plugins:audit-registrations` 命令调用，对注册面做只读检查。
 */
class PluginConsoleRegistrationAuditor
{
    /**
     * 收集注册来源位于插件根目录下的控制台命令。
     *
     * @param  iterable<string, Command>  $commands  命令名 => 命令实例
     * @param  array<int, string>  $allowlist  已在插件清单 extra.console_commands 声明的命令类，视为受控豁免
     * @return list<array{name: string, class: string, file: string}>
     */
    public function violationsFor(iterable $commands, string $pluginsRoot, array $allowlist = []): array
    {
        $allowlisted = array_values(array_filter(array_map(
            static fn (mixed $class): string => trim((string) $class),
            $allowlist,
        )));
        $violations = [];

        foreach ($commands as $name => $command) {
            $file = (new ReflectionClass($command))->getFileName();

            if ($file === false || ! $this->isUnderRoot($file, $pluginsRoot)) {
                continue;
            }

            if (in_array($command::class, $allowlisted, true)) {
                continue;
            }

            $violations[] = [
                'name' => (string) $name,
                'class' => $command::class,
                'file' => $file,
            ];
        }

        return $violations;
    }

    /**
     * 判断命令实现文件是否位于指定根目录之下（兼容 Windows/POSIX 路径分隔符）。
     */
    public function isUnderRoot(string $file, string $root): bool
    {
        $normalizedFile = str_replace('\\', '/', $file);
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');

        if ($normalizedRoot === '') {
            return false;
        }

        return str_starts_with($normalizedFile, $normalizedRoot.'/');
    }
}
