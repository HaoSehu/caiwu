<?php

declare(strict_types=1);

use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\PluginInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 主机面板接口插件安装（上游驱动插件化立项 2026-10-12）。
 *
 * hosting_panel_api 此前仅有系统内 Transport 实现而无 UpstreamDriver 注册
 * （ProviderRegistry 驱动只来自启用的 UPSTREAM 插件），认证刷新任务空转；
 * 本迁移以标准安装路径注册驱动并启用，零存量绑定、无数据订正。
 *
 * down 仅禁用（保留安装记录）：回滚目标为"驱动未注册"的历史现状，
 * 卸载记录的删除交由管理员在管理端显式操作。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            return;
        }

        $installer = app(PluginInstaller::class);
        $plugin = $installer->install('upstream', 'hosting_panel');

        if (! $plugin->isEnabled()) {
            $installer->enable($plugin);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            return;
        }

        $plugin = IntegrationPlugin::query()
            ->where('domain', 'upstream')
            ->where('slug', 'hosting_panel')
            ->first();

        if ($plugin instanceof IntegrationPlugin && $plugin->isEnabled()) {
            $plugin->forceFill([
                'status' => IntegrationPlugin::STATUS_DISABLED,
                'disabled_at' => now(),
            ])->save();
        }
    }
};
