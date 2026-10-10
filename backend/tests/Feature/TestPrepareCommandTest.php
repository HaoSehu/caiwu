<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * test:prepare 防呆闸门（仅验证拒绝路径，不执行真实重建）：
 * 1. 缺 --force 拒绝；
 * 2. 目标库名不以 _test 结尾拒绝（防误伤 idc）。
 *
 * 真实重建演练走人工流程（mysqldump → test:prepare --force → 全量回归），
 * 不在用例内执行，避免套件中途重建测试库。
 */
class TestPrepareCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_refuses_without_force(): void
    {
        $this->artisan('test:prepare', ['--target' => 'idc_test'])
            ->expectsOutputToContain('缺少 --force')
            ->assertExitCode(1);
    }

    public function test_refuses_target_without_test_suffix(): void
    {
        $this->artisan('test:prepare', ['--force' => true, '--target' => 'idc'])
            ->expectsOutputToContain('必须以 _test 结尾')
            ->assertExitCode(1);
    }

    public function test_suffix_guard_wins_over_missing_force(): void
    {
        // 库名闸门先于 --force 闸门：即使传了 --force 也不放行非 _test 库。
        $this->artisan('test:prepare', ['--force' => true, '--target' => 'production_like_db'])
            ->expectsOutputToContain('必须以 _test 结尾')
            ->assertExitCode(1);
    }
}
