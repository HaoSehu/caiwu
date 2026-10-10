<?php

declare(strict_types=1);

namespace Tests\Fixtures\Plugins;

use Illuminate\Console\Command;

/**
 * 测试夹具：模拟插件注册的控制台命令。
 *
 * 文件位于 tests/Fixtures/Plugins 下，供 PluginConsoleRegistrationAuditor
 * 的路径判定与违规收集测试使用（测试中以 tests/Fixtures 作为插件根目录）。
 */
class FakePluginCommand extends Command
{
    protected $signature = 'fake-plugin:demo';

    protected $description = '测试夹具命令';
}
