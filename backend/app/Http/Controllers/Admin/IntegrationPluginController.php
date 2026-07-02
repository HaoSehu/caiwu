<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IntegrationPlugin\IndexIntegrationPluginRequest;
use App\Http\Requests\Admin\IntegrationPlugin\InstallIntegrationPluginRequest;
use App\Http\Requests\Admin\IntegrationPlugin\UpdateIntegrationPluginConfigRequest;
use App\Models\AdminUser;
use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\IntegrationPluginService;
use Illuminate\Http\Request;

class IntegrationPluginController extends Controller
{
    public function __construct(
        private readonly IntegrationPluginService $pluginService,
    ) {}

    public function index(IndexIntegrationPluginRequest $request)
    {
        $domain = trim((string) ($request->validated()['domain'] ?? ''));
        $list = $this->pluginService->list($domain !== '' ? $domain : null);

        return $this->success([
            'list' => $list,
            'total' => count($list),
            'page' => 1,
            'page_size' => count($list),
        ]);
    }

    public function scan(IndexIntegrationPluginRequest $request)
    {
        $domain = trim((string) ($request->validated()['domain'] ?? ''));
        $list = $this->pluginService->list($domain !== '' ? $domain : null);

        return $this->success([
            'list' => $list,
            'total' => count($list),
        ]);
    }

    public function install(InstallIntegrationPluginRequest $request)
    {
        $data = $request->validated();

        return $this->success($this->pluginService->install(
            (string) $data['domain'],
            (string) $data['slug'],
        ), '插件安装成功');
    }

    public function show(IntegrationPlugin $plugin)
    {
        return $this->success($this->pluginService->detail($plugin));
    }

    public function updateConfig(UpdateIntegrationPluginConfigRequest $request, IntegrationPlugin $plugin)
    {
        $admin = $request->user();

        return $this->success(
            $this->pluginService->updateConfig(
                $plugin,
                (array) $request->validated()['config'],
                $admin instanceof AdminUser ? $admin : null,
            ),
            '插件配置已更新'
        );
    }

    public function enable(IntegrationPlugin $plugin)
    {
        return $this->success($this->pluginService->enable($plugin), '插件已启用');
    }

    public function disable(IntegrationPlugin $plugin)
    {
        return $this->success($this->pluginService->disable($plugin), '插件已停用');
    }

    public function destroy(IntegrationPlugin $plugin)
    {
        $this->pluginService->uninstall($plugin);

        return $this->success(null, '插件已删除');
    }

    public function healthCheck(Request $request, IntegrationPlugin $plugin)
    {
        return $this->success($this->pluginService->healthCheck($plugin));
    }

    public function testEmail(Request $request, IntegrationPlugin $plugin)
    {
        $validated = $request->validate([
            'account_index' => 'required|integer|min:0',
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string|max:5000',
        ]);

        return $this->success(
            $this->pluginService->testEmail($plugin, $validated),
            '测试邮件发送成功'
        );
    }

    public function testSms(Request $request, IntegrationPlugin $plugin)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        return $this->success(
            $this->pluginService->testSms($plugin, $validated),
            '测试短信发送成功'
        );
    }
}
