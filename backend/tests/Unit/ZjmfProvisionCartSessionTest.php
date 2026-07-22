<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfAuthManager;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfProvisionService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ZjmfProvisionCartSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PluginFileLoader::class)->ensureLoaded(
            app(PluginScanner::class)->requireManifest('upstream', 'zjmf_finance')
        );
    }

    public function test_provision_cart_reuses_upstream_session_cookie(): void
    {
        config(['idc.hosting_panel_api.jwt_cache_store' => 'array']);
        Cache::store('array')->flush();

        $supplier = (new Supplier)->forceFill([
            'id' => 9981,
            'api_url' => 'https://zjmf.example.test',
            'api_username' => 'demo',
            'api_key' => 'secret',
        ]);

        $product = (new Product)->forceFill([
            'id' => 771,
            'name' => 'ZJMF 测试云主机',
            'purchase_requires' => [],
            'config_options' => [
                [
                    'id' => 68001,
                    'field' => 'cpu',
                    'option_type' => 6,
                    'sub' => [
                        ['id' => 68011, 'option_name_first' => '2', 'option_name' => '2核'],
                    ],
                ],
                [
                    'id' => 68002,
                    'field' => 'os',
                    'option_type' => 5,
                    'sub' => [
                        ['id' => 68012, 'option_name_first' => 'ubuntu', 'option_name' => 'Ubuntu'],
                    ],
                ],
            ],
        ]);

        $order = (new Order)->forceFill([
            'id' => 1881,
            'order_no' => 'ORDER-SESSION-1881',
            'product_id' => 771,
            'billing_cycle' => 'monthly',
            'domain' => 'ser1881.example.test',
            'config_snapshot' => [
                'hostname' => 'ser1881.example.test',
                'cpu' => '2',
                'os' => '68012',
            ],
        ]);
        $order->setRelation('product', $product);

        $upstreamTransport = new class extends HostingPanelApiTransport
        {
            public array $metaCalls = [];

            public function request(
                Supplier $supplier,
                string $method,
                string $uri,
                array|string $payload = [],
                ?string $jwt = null,
                array $headers = [],
                array $query = []
            ): array {
                if ($uri === '/zjmf_api_login') {
                    return ['status' => 200, 'jwt' => 'jwt-test-token'];
                }

                if ($uri === '/host/header') {
                    return [
                        'status' => 200,
                        'data' => [
                            'host_data' => [
                                'id' => 7788,
                                'domain' => 'ser1881.example.test',
                                'domainstatus' => 'Active',
                            ],
                        ],
                    ];
                }

                return ['status' => 200, 'data' => []];
            }

            public function requestWithMeta(
                Supplier $supplier,
                string $method,
                string $uri,
                array|string $payload = [],
                ?string $jwt = null,
                array $headers = [],
                array $query = []
            ): array {
                $this->metaCalls[] = compact('method', 'uri', 'payload', 'jwt', 'headers', 'query');

                return match ($uri) {
                    '/cart/get_product_config' => [
                        'response' => [
                            'status' => 200,
                            'data' => [
                                'product_pricings' => [
                                    ['relid' => 831, 'currency' => 1],
                                ],
                            ],
                        ],
                        'headers' => [],
                        'http_code' => 200,
                        'content_type' => 'application/json',
                    ],
                    '/cart/add_to_shop' => [
                        'response' => [
                            'status' => 200,
                            'msg' => 'Added successfully',
                            'data' => [],
                        ],
                        'headers' => [
                            'HTTP/1.1 200 OK',
                            'Set-Cookie: ZJMF_SESSION=cart-session-1881; path=/; HttpOnly',
                        ],
                        'http_code' => 200,
                        'content_type' => 'application/json',
                    ],
                    '/cart/settle' => [
                        'response' => [
                            'status' => 200,
                            'msg' => 'Checkout created',
                            'data' => [
                                'invoiceid' => 9901881,
                                'hostid' => [7788],
                            ],
                        ],
                        'headers' => [],
                        'http_code' => 200,
                        'content_type' => 'application/json',
                    ],
                    '/apply_credit' => [
                        'response' => [
                            'status' => 200,
                            'msg' => 'Payment completed',
                            'data' => [],
                        ],
                        'headers' => [],
                        'http_code' => 200,
                        'content_type' => 'application/json',
                    ],
                    '/cart/clear' => [
                        'response' => ['status' => 200, 'msg' => 'Cart cleared'],
                        'headers' => [],
                        'http_code' => 200,
                        'content_type' => 'application/json',
                    ],
                    default => [
                        'response' => ['status' => 200, 'data' => []],
                        'headers' => [],
                        'http_code' => 200,
                        'content_type' => 'application/json',
                    ],
                };
            }
        };

        $transport = new ZjmfFinanceTransport($upstreamTransport, new ZjmfAuthManager($upstreamTransport));
        $bindingResolver = new class extends PluginBindingResolver
        {
            public function upstreamProductIdForProduct(Product $product): ?string
            {
                return '831';
            }

            public function upstreamServiceIdForService(Service $service): ?string
            {
                return null;
            }
        };

        $provisionService = new ZjmfProvisionService($transport, $bindingResolver);

        $result = $provisionService->provisionOrder($order, $supplier);

        $this->assertSame(7788, $result['upstream_host_id']);
        $this->assertFalse($this->callHasCookie($upstreamTransport->metaCalls[0] ?? []));
        $this->assertFalse($this->callHasCookie($this->firstMetaCall($upstreamTransport->metaCalls, '/cart/add_to_shop')));
        $this->assertTrue($this->callHasCookie($this->firstMetaCall($upstreamTransport->metaCalls, '/cart/settle')));
        $this->assertTrue($this->callHasCookie($this->firstMetaCall($upstreamTransport->metaCalls, '/apply_credit')));
        $this->assertTrue($this->callHasCookie($this->lastMetaCall($upstreamTransport->metaCalls, '/cart/clear')));

        $addCall = $this->firstMetaCall($upstreamTransport->metaCalls, '/cart/add_to_shop');
        $this->assertSame(831, $addCall['payload']['pid'] ?? null);
        $this->assertSame('monthly', $addCall['payload']['billingcycle'] ?? null);
        $this->assertSame(1, $addCall['payload']['currencyid'] ?? null);
        $this->assertSame(68011, $addCall['payload']['configoption'][68001] ?? null);
        $this->assertSame(68012, $addCall['payload']['os']['id'] ?? null);
        $this->assertSame(0, $addCall['payload']['checkout'] ?? null);
        $this->assertTrue($this->callHasHeader($addCall, 'content-type: application/x-www-form-urlencoded'));

        $settleCall = $this->firstMetaCall($upstreamTransport->metaCalls, '/cart/settle');
        $this->assertSame([0], $settleCall['payload']['pos'] ?? null);
        $this->assertSame(1, $settleCall['payload']['checkout'] ?? null);

        $creditCall = $this->firstMetaCall($upstreamTransport->metaCalls, '/apply_credit');
        $this->assertSame(9901881, $creditCall['payload']['invoiceid'] ?? null);
        $this->assertSame(1, $creditCall['payload']['use_credit'] ?? null);
        $this->assertSame(1, $creditCall['payload']['enough'] ?? null);

        foreach ($upstreamTransport->metaCalls as $call) {
            $this->assertStringNotContainsString('/v1/', (string) ($call['uri'] ?? ''));
            $this->assertStringNotContainsString('/v10/', (string) ($call['uri'] ?? ''));
        }
    }

    private function firstMetaCall(array $calls, string $uri): array
    {
        foreach ($calls as $call) {
            if (($call['uri'] ?? '') === $uri) {
                return $call;
            }
        }

        return [];
    }

    private function lastMetaCall(array $calls, string $uri): array
    {
        $matched = [];

        foreach ($calls as $call) {
            if (($call['uri'] ?? '') === $uri) {
                $matched = $call;
            }
        }

        return $matched;
    }

    private function callHasCookie(array $call): bool
    {
        foreach ((array) ($call['headers'] ?? []) as $header) {
            $header = trim((string) $header);
            if (str_starts_with(strtolower($header), 'cookie:')
                && str_contains($header, 'ZJMF_SESSION=cart-session-1881')) {
                return true;
            }
        }

        return false;
    }

    private function callHasHeader(array $call, string $expectedHeader): bool
    {
        $expectedHeader = strtolower($expectedHeader);

        foreach ((array) ($call['headers'] ?? []) as $header) {
            if (strtolower(trim((string) $header)) === $expectedHeader) {
                return true;
            }
        }

        return false;
    }
}
