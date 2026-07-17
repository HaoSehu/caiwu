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
            'config_options' => [],
        ]);

        $order = (new Order)->forceFill([
            'id' => 1881,
            'order_no' => 'ORDER-SESSION-1881',
            'product_id' => 771,
            'billing_cycle' => 'monthly',
            'domain' => 'ser1881.example.test',
            'config_snapshot' => [
                'hostname' => 'ser1881.example.test',
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
                if ($uri === '/v1/login_api') {
                    return ['status' => 200, 'jwt' => 'jwt-test-token'];
                }

                if ($uri === '/v1/hosts/7788') {
                    return [
                        'status' => 200,
                        'data' => [
                            'host' => [
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
                    '/v1/cart/products' => [
                        'response' => ['status' => 200, 'msg' => 'Added successfully'],
                        'headers' => [
                            'HTTP/1.1 200 OK',
                            'Set-Cookie: ZJMF_SESSION=cart-session-1881; path=/; HttpOnly',
                        ],
                        'http_code' => 200,
                        'content_type' => 'application/json',
                    ],
                    '/v1/cart' => [
                        'response' => [
                            'status' => 200,
                            'data' => [
                                'default_gateway' => 'balance',
                                'gateway_list' => [
                                    ['name' => 'balance'],
                                ],
                                'cart_products' => [
                                    ['productid' => 831],
                                ],
                            ],
                        ],
                        'headers' => [],
                        'http_code' => 200,
                        'content_type' => 'application/json',
                    ],
                    '/v1/cart/checkout' => [
                        'response' => [
                            'status' => 1001,
                            'msg' => 'Payment completed',
                            'data' => [
                                'invoiceid' => 9901881,
                                'hostid' => [7788],
                            ],
                        ],
                        'headers' => [],
                        'http_code' => 200,
                        'content_type' => 'application/json',
                    ],
                    '/v1/cart/clear' => [
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
        $this->assertTrue($this->callHasCookie($this->firstMetaCall($upstreamTransport->metaCalls, '/v1/cart')));
        $this->assertTrue($this->callHasCookie($this->firstMetaCall($upstreamTransport->metaCalls, '/v1/cart/checkout')));
        $this->assertTrue($this->callHasCookie($this->lastMetaCall($upstreamTransport->metaCalls, '/v1/cart/clear')));
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
}
