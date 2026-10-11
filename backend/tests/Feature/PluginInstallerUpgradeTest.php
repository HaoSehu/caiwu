<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\IntegrationPluginService;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginScanner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PluginInstallerUpgradeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_upgrade_syncs_registration_with_stale_manifest_hash(): void
    {
        $installer = app(PluginInstaller::class);
        $scanner = app(PluginScanner::class);

        $plugin = $installer->install('payment', 'demo_pay');
        $this->assertSame('demo_pay', (string) $plugin->plugin_key);
        $this->assertNotNull($plugin->manifest_hash);

        // 模拟磁盘文件比登记记录新（插件文件被替换/升级）。
        $plugin->forceFill(['manifest_hash' => 'stale-hash'])->save();

        $result = $installer->upgrade($plugin->fresh() ?? $plugin);

        $fresh = $result['plugin']->fresh();
        $this->assertInstanceOf(IntegrationPlugin::class, $fresh);
        $this->assertSame('1.0.0', (string) $fresh->version);
        $this->assertSame(
            $scanner->manifestContentHash('payment', 'demo_pay'),
            (string) $fresh->manifest_hash,
        );
        $this->assertSame(['added' => [], 'removed' => [], 'changed' => []], $result['diff']);
        $this->assertSame('1.0.0', $result['previous_version']);
    }

    public function test_list_marks_has_update_for_stale_manifest(): void
    {
        // list() 会过滤 demo 插件，has_update 用真实插件验证。
        $installer = app(PluginInstaller::class);
        $plugin = $installer->install('payment', 'ali_pay');

        $service = app(IntegrationPluginService::class);
        $items = collect($service->list('payment'))->firstWhere('slug', 'ali_pay');
        $this->assertNotNull($items);
        $this->assertFalse((bool) $items['has_update']);

        $plugin->forceFill(['manifest_hash' => 'stale'])->save();

        $items = collect($service->list('payment'))->firstWhere('slug', 'ali_pay');
        $this->assertNotNull($items);
        $this->assertTrue((bool) $items['has_update']);
    }

    public function test_zjmf_finance_console_command_declaration_passes_install_validation(): void
    {
        // console_commands 是受控命令注册面：zjmf_finance 声明了 RestoreZjmfBillingCommand，
        // 安装校验应通过（类存在、位于插件目录内、继承 Command）。
        $installer = app(PluginInstaller::class);
        $plugin = $installer->install('upstream', 'zjmf_finance');

        $this->assertSame('zjmf_finance_api', (string) $plugin->plugin_key);
    }
}
