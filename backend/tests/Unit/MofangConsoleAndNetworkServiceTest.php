<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Supplier;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangAuthManager;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangConsoleService;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangFinanceTransport;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangNetworkService;
use Mockery;
use Tests\TestCase;

class MofangConsoleAndNetworkServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PluginFileLoader::class)->ensureLoaded(
            app(PluginScanner::class)->requireManifest('upstream', 'mofang_finance')
        );
    }

    public function test_console_service_requests_vnc_through_plugin_transport(): void
    {
        $supplier = $this->makeSupplier();
        $hostingTransport = Mockery::mock(HostingPanelApiTransport::class);
        $hostingTransport
            ->shouldReceive('request')
            ->once()
            ->with(
                $supplier,
                'POST',
                '/provision/default',
                ['func' => 'vnc', 'id' => 123],
                'jwt-token',
                ['content-type: application/x-www-form-urlencoded'],
                []
            )
            ->andReturn(['status' => 200, 'data' => ['url' => 'wss://vnc.example/ws']]);

        $service = new MofangConsoleService($this->makeTransport($hostingTransport));

        $response = $service->getVncUrl($supplier, 123, 'jwt-token');

        $this->assertSame('wss://vnc.example/ws', $response['data']['url']);
    }

    public function test_network_service_purchases_traffic_package_as_high_level_plugin_action(): void
    {
        $supplier = $this->makeSupplier();
        $hostingTransport = Mockery::mock(HostingPanelApiTransport::class);
        $hostingTransport
            ->shouldReceive('request')
            ->once()
            ->with($supplier, 'POST', '/v1/hosts/123/actions/upgradeconfig', ['configoption' => ['11' => 22]], 'jwt-token', [], [])
            ->andReturn(['status' => 200, 'data' => []]);
        $hostingTransport
            ->shouldReceive('request')
            ->once()
            ->with($supplier, 'POST', '/v1/hosts/123/actions/upgradeconfig/checkout', [], 'jwt-token', [], [])
            ->andReturn(['status' => 200, 'data' => ['invoiceid' => 456]]);
        $hostingTransport
            ->shouldReceive('request')
            ->once()
            ->with($supplier, 'POST', '/v1/invoices/456/fund', [], 'jwt-token', [], [])
            ->andReturn(['status' => 200, 'data' => []]);
        $hostingTransport
            ->shouldReceive('request')
            ->once()
            ->with($supplier, 'GET', '/v1/hosts/123', [], 'jwt-token', [], [])
            ->andReturn(['status' => 200, 'data' => ['host' => ['bwlimit' => 2048]]]);

        $transport = $this->makeTransport($hostingTransport);
        $consoleService = new MofangConsoleService($transport);
        $networkService = new MofangNetworkService($transport, $consoleService);

        $result = $networkService->purchaseTrafficPackage(
            $supplier,
            123,
            'upgradeconfig',
            ['11' => 22],
            0,
            'https://upstream.example',
            'jwt-token'
        );

        $this->assertSame(456, $result['upstream_invoice_id']);
        $this->assertSame(2048, $result['host_detail']['bwlimit']);
    }

    public function test_network_service_purchases_host_upgrade_as_high_level_plugin_action(): void
    {
        $supplier = $this->makeSupplier();
        $hostingTransport = Mockery::mock(HostingPanelApiTransport::class);
        $hostingTransport
            ->shouldReceive('request')
            ->once()
            ->with($supplier, 'POST', '/v1/hosts/77/actions/upgrade', ['product_id' => 88, 'billingcycle' => 'monthly'], 'jwt-token', [], [])
            ->andReturn(['status' => 200, 'data' => []]);
        $hostingTransport
            ->shouldReceive('request')
            ->once()
            ->with($supplier, 'PUT', '/v1/hosts/77/actions/upgrade/promo', ['promo_code' => 'PROMO10'], 'jwt-token', [], [])
            ->andReturn(['status' => 200, 'data' => []]);
        $hostingTransport
            ->shouldReceive('request')
            ->once()
            ->with($supplier, 'POST', '/v1/hosts/77/actions/upgrade/checkout', [], 'jwt-token', [], [])
            ->andReturn(['status' => 200, 'data' => ['invoiceid' => 990]]);
        $hostingTransport
            ->shouldReceive('request')
            ->once()
            ->with($supplier, 'POST', '/v1/invoices/990/fund', [], 'jwt-token', [], [])
            ->andReturn(['status' => 200, 'data' => []]);
        $hostingTransport
            ->shouldReceive('request')
            ->once()
            ->with($supplier, 'GET', '/v1/hosts/77', [], 'jwt-token', [], [])
            ->andReturn(['status' => 200, 'data' => ['host' => ['productid' => 88]]]);

        $transport = $this->makeTransport($hostingTransport);
        $consoleService = new MofangConsoleService($transport);
        $networkService = new MofangNetworkService($transport, $consoleService);

        $result = $networkService->purchaseHostUpgrade($supplier, 77, 88, 'monthly', 'PROMO10', 'jwt-token');

        $this->assertSame(990, $result['upstream_invoice_id']);
        $this->assertSame(88, $result['host_detail']['productid']);
    }

    private function makeTransport(HostingPanelApiTransport $hostingTransport): MofangFinanceTransport
    {
        return new MofangFinanceTransport($hostingTransport, new MofangAuthManager($hostingTransport));
    }

    private function makeSupplier(): Supplier
    {
        $supplier = new Supplier;
        $supplier->forceFill([
            'id' => 10,
            'api_url' => 'https://upstream.example/api',
            'interface_type' => 'mofang_finance_api',
        ]);

        return $supplier;
    }
}
