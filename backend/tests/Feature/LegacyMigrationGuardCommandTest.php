<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * 旧库迁移子系统弃用护栏命令层回归（B5-86 命令覆盖 · 批次 1；G1-01 裁决落地验证）：
 * 1. 全部 35 个业务 `migrate:*` 命令均带 `[已弃用·旧库迁移]` 描述前缀；
 * 2. 命令执行即拒绝（exit=1）并输出拒绝原因；
 * 3. 框架内置 migrate 系列（migrate/fresh/install/refresh/reset/rollback/status）不受护栏影响。
 */
class LegacyMigrationGuardCommandTest extends TestCase
{
    /** 框架内置 migrate 命令（不受弃用护栏约束） */
    private const FRAMEWORK_MIGRATE_COMMANDS = [
        'migrate',
        'migrate:fresh',
        'migrate:install',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'migrate:status',
    ];

    public function test_all_business_migrate_commands_are_marked_deprecated(): void
    {
        $business = array_filter(
            Artisan::all(),
            static fn ($command, string $name): bool => str_starts_with($name, 'migrate:')
                && ! in_array($name, self::FRAMEWORK_MIGRATE_COMMANDS, true),
            ARRAY_FILTER_USE_BOTH,
        );

        $this->assertCount(35, $business, '业务 migrate:* 命令应恰为 35 个（新增/删除时同步本断言）');

        foreach ($business as $name => $command) {
            $this->assertStringStartsWith('[已弃用·旧库迁移]', $command->getDescription(), $name);
        }
    }

    public function test_framework_migrate_commands_are_not_guarded(): void
    {
        foreach (self::FRAMEWORK_MIGRATE_COMMANDS as $name) {
            $command = Artisan::all()[$name] ?? null;
            $this->assertNotNull($command, "框架命令 [{$name}] 应存在");
            $this->assertStringNotContainsString('[已弃用·旧库迁移]', $command->getDescription(), $name);
        }
    }

    public function test_deprecated_commands_refuse_execution(): void
    {
        $this->artisan('migrate:trade:reconcile')
            ->expectsOutputToContain('已拒绝执行')
            ->assertExitCode(1);

        $this->artisan('migrate:identity:users')
            ->expectsOutputToContain('已拒绝执行')
            ->assertExitCode(1);

        $this->artisan('migrate:account:reconcile')
            ->expectsOutputToContain('已拒绝执行')
            ->assertExitCode(1);
    }
}
