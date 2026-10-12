<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Supplier;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfAuthManager;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfConsoleService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceTransport;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/ZjmfAuthManager.php';
require_once __DIR__.'/../lib/ZjmfFinanceTransport.php';
require_once __DIR__.'/../lib/ZjmfConsoleService.php';

/**
 * 电源状态刷新（POST /dcim/refresh_power_status）回归：
 * - 请求参数 id=host_id & is_api=1，走表单头；
 * - 响应标准化为 power_status + power_label（des 缺失时按状态词表兜底）。
 */
class ZjmfConsoleServicePowerStatusTest extends TestCase
{
    public function test_posts_refresh_power_status_with_host_id_and_normalizes(): void
    {
        $platform = $this->createMock(HostingPanelApiTransport::class);
        $platform->expects($this->once())->method('request')->with(
            $this->anything(),
            'POST',
            '/dcim/refresh_power_status',
            ['id' => 77, 'is_api' => 1],
            'jwt-1',
            [
                'content-type: application/x-www-form-urlencoded',
                'Authorization: Bearer jwt-1',
            ],
            [],
        )->willReturn(['status' => 200, 'data' => ['status' => 'on', 'des' => '开机']]);

        $label = $this->consoleService($platform)->getPowerStatus($this->supplier(), 77, 'jwt-1');

        $this->assertSame(['power_status' => 'on', 'power_label' => '开机'], $label);
    }

    public function test_blank_state_falls_back_to_wordbook_and_unknown(): void
    {
        $platform = $this->createMock(HostingPanelApiTransport::class);
        $platform->method('request')->willReturn(['status' => 200, 'data' => ['status' => 'paused']]);

        $label = $this->consoleService($platform)->getPowerStatus($this->supplier(), 77, 'jwt-1');

        // 上游未带 des 时按状态词表兜底（paused=挂起）
        $this->assertSame(['power_status' => 'paused', 'power_label' => '挂起'], $label);
    }

    public function test_unknown_state_keeps_payload_des_when_present(): void
    {
        $platform = $this->createMock(HostingPanelApiTransport::class);
        $platform->method('request')->willReturn(['status' => 200, 'data' => ['status' => 'task', 'des' => '重装系统中']]);

        $label = $this->consoleService($platform)->getPowerStatus($this->supplier(), 77, 'jwt-1');

        // 词表外的状态透传上游 des 文案
        $this->assertSame(['power_status' => 'task', 'power_label' => '重装系统中'], $label);
    }

    private function supplier(): Supplier
    {
        $supplier = new Supplier;
        $supplier->id = 9;

        return $supplier;
    }

    private function consoleService(HostingPanelApiTransport $platform): ZjmfConsoleService
    {
        return new ZjmfConsoleService(new ZjmfFinanceTransport(
            $platform,
            new ZjmfAuthManager($platform),
        ));
    }
}
