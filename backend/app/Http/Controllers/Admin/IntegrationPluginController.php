<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IntegrationPlugin\IndexIntegrationPluginRequest;
use App\Http\Requests\Admin\IntegrationPlugin\InstallIntegrationPluginRequest;
use App\Http\Requests\Admin\IntegrationPlugin\TestEmailRequest;
use App\Http\Requests\Admin\IntegrationPlugin\TestSmsRequest;
use App\Http\Requests\Admin\IntegrationPlugin\UpdateIntegrationPluginConfigRequest;
use App\Models\AdminUser;
use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\IntegrationPluginService;

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

    public function revealConfigSecret(IntegrationPlugin $plugin, string $key)
    {
        return $this->success($this->pluginService->revealConfigSecret($plugin, $key));
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
        $result = $this->pluginService->uninstall($plugin);

        return $this->success(
            $result,
            ! empty($result['archived']) ? '插件已停用并保留业务引用' : '插件已删除'
        );
    }

    public function healthCheck(IntegrationPlugin $plugin)
    {
        return $this->success($this->pluginService->healthCheck($plugin));
    }

    public function testEmail(TestEmailRequest $request, IntegrationPlugin $plugin)
    {
        return $this->success(
            $this->pluginService->testEmail($plugin, $request->validated()),
            '测试邮件发送成功'
        );
    }

    public function testSms(TestSmsRequest $request, IntegrationPlugin $plugin)
    {
        return $this->success(
            $this->pluginService->testSms($plugin, $request->validated()),
            '测试短信发送成功'
        );
    }
}
