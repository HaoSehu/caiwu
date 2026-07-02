<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Supplier;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangAuthManager;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangFinanceTransport;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangRenewService;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangStatusService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MofangRenewAndStatusServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PluginFileLoader::class)->ensureLoaded(
            app(PluginScanner::class)->requireManifest('upstream', 'mofang_finance')
        );

        Cache::flush();
    }

    #[Test]
    public function renew_service_invoice_submits_funds_and_reads_host_detail_inside_plugin(): void
    {
        $fakeTransport = new class extends HostingPanelApiTransport
        {
            public array $uris = [];

            public function request(
                Supplier $supplier,
                string $method,
                string $uri,
                array|string $payload = [],
                ?string $jwt = null,
                array $headers = [],
                array $query = []
            ): array {
                $this->uris[] = "{$method} {$uri}";

                return match ($uri) {
                    '/v1/login_api' => [
                        'status' => 200,
                        'code' => 200,
                        'jwt' => 'jwt-renew-test',
                    ],
                    '/v1/hosts/7788/renew' => [
                        'status' => 200,
                        'data' => [
                            'invoiceid' => 8899,
                        ],
                    ],
                    '/v1/invoices/8899/fund' => [
                        'status' => 1001,
                        'data' => [
                            'paid' => true,
                        ],
                    ],
                    '/v1/hosts/7788' => [
                        'status' => 200,
                        'data' => [
                            'host' => [
                                'id' => 7788,
                                'domain' => 'srv7788.example.test',
                                'domainstatus' => 'Active',
                                'nextduedate' => 1785600000,
                            ],
                        ],
                    ],
                    default => [
                        'status' => 404,
                        'msg' => 'not found',
                    ],
                };
            }
        };

        $service = new MofangRenewService($this->makeMofangTransport($fakeTransport));
        $result = $service->renewServiceInvoice($this->makeSupplier(), 7788, 'monthly');

        $this->assertSame(8899, $result['upstream_invoice_id']);
        $this->assertSame('Active', $result['host_detail']['domainstatus'] ?? null);
        $this->assertContains('POST /v1/hosts/7788/renew', $fakeTransport->uris);
        $this->assertContains('POST /v1/invoices/8899/fund', $fakeTransport->uris);
        $this->assertContains('GET /v1/hosts/7788', $fakeTransport->uris);
    }

    #[Test]
    public function sync_service_statuses_returns_platform_writable_snapshot_payloads(): void
    {
        $fakeTransport = new class extends HostingPanelApiTransport
        {
            public array $batchRequests = [];

            public function request(
                Supplier $supplier,
                string $method,
                string $uri,
                array|string $payload = [],
                ?string $jwt = null,
                array $headers = [],
                array $query = []
            ): array {
                return [
                    'status' => 200,
                    'code' => 200,
                    'jwt' => 'jwt-status-test',
                ];
            }

            public function parallelGet(Supplier $supplier, array $requests, ?string $jwt = null, array $headers = []): array
            {
                $this->batchRequests = $requests;

                return [
                    'detail_501' => [
                        'status_code' => 200,
                        'response' => [
                            'status' => 200,
                            'data' => [
                                'host' => [
                                    'domain' => 'srv501.example.test',
                                    'domainstatus' => 'Active',
                                    'product_id' => '9001',
                                    'product_name' => 'Mofang VPS',
                                    'dedicatedip' => '203.0.113.10',
                                    'assignedips' => ['203.0.113.11'],
                                    'config_option' => ['cpu' => 2],
                                    'os' => 'CentOS',
                                    'username' => 'root',
                                    'password' => 'Secret123',
                                    'port' => '22',
                                    'internalip' => '10.0.0.5',
                                    'nextduedate' => 1785600000,
                                ],
                            ],
                        ],
                    ],
                    'runtime_501' => [
                        'status_code' => 200,
                        'response' => [
                            'status' => 200,
                            'data' => [
                                'status' => 'running',
                                'des' => 'running',
                            ],
                        ],
                    ],
                ];
            }
        };

        $service = new MofangStatusService($this->makeMofangTransport($fakeTransport));
        $result = $service->syncServiceStatuses($this->makeSupplier(), [
            [
                'service_id' => 501,
                'host_id' => 7788,
            ],
        ]);

        $this->assertSame('/v1/hosts/7788', $fakeTransport->batchRequests['detail_501']['uri'] ?? null);
        $this->assertSame('/v1/hosts/7788/module/status', $fakeTransport->batchRequests['runtime_501']['uri'] ?? null);
        $this->assertSame('Active', $result['services'][501]['host']['domainstatus'] ?? null);
        $this->assertSame(9001, $result['services'][501]['host']['product_id'] ?? null);
        $this->assertSame('running', $result['services'][501]['runtime']['status'] ?? null);
    }

    private function makeSupplier(): Supplier
    {
        $supplier = new Supplier;
        $supplier->forceFill([
            'id' => 1001,
            'interface_type' => 'mofang_finance_api',
        ]);

        return $supplier;
    }

    private function makeMofangTransport(HostingPanelApiTransport $transport): MofangFinanceTransport
    {
        return new MofangFinanceTransport(
            $transport,
            new MofangAuthManager($transport),
        );
    }
}
