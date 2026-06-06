<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Integrations\Mofang\Adapters\MofangFinanceAdapter;
use App\Integrations\Mofang\Drivers\MofangFinanceDriver;
use App\Integrations\Mofang\Support\MofangCloudConfigTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Provisioning\ProvisionService;
use App\Services\System\SettingService;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiDriver;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use App\Support\ProductProvisionHostname;
use ArrayObject;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProvisionServiceHostnameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    #[Test]
    public function it_parses_upstream_hostname_rule_from_grouped_product_config_response(): void
    {
        $service = new ProvisionService(
            $this->makeProviderResolver(new class($this->buildUpstreamProductConfigResponse()) extends HostingPanelApiTransport
            {
                public function __construct(private readonly array $response) {}

                public function login(Supplier $supplier): string
                {
                    return 'jwt-test-token';
                }

                public function get(
                    Supplier $supplier,
                    string $uri,
                    ?string $jwt = null,
                    array $query = [],
                    array $headers = []
                ): array {
                    return $this->response;
                }
            }),
            new class extends SettingService
            {
                public function getProvisionHostnameConfig(): array
                {
                    return [
                        'prefix' => 'srv',
                        'length' => 12,
                        'pool' => '0123456789',
                    ];
                }
            }
        );

        $order = $this->makeOrder('ltser1234567890');
        $rule = $this->invokeResolveUpstreamProvisionHostnameRule($service, $order);

        $this->assertSame('ser', $rule['prefix'] ?? null);
        $this->assertSame(13, $rule['length'] ?? null);
        $this->assertSame('0123456789', $rule['pool'] ?? null);
    }

    #[Test]
    public function it_ignores_stale_empty_hostname_rule_cache_and_parses_single_product_json_host_rule(): void
    {
        Cache::put('provision:upstream_hostname_rule:101:9001', [], now()->addMinutes(30));

        $service = new ProvisionService(
            $this->makeProviderResolver(new class($this->buildSingleProductUpstreamProductConfigResponse()) extends HostingPanelApiTransport
            {
                public function __construct(private readonly array $response) {}

                public function login(Supplier $supplier): string
                {
                    return 'jwt-test-token';
                }

                public function get(
                    Supplier $supplier,
                    string $uri,
                    ?string $jwt = null,
                    array $query = [],
                    array $headers = []
                ): array {
                    return $this->response;
                }
            }),
            new class extends SettingService
            {
                public function getProvisionHostnameConfig(): array
                {
                    return [
                        'prefix' => 'srv',
                        'length' => 12,
                        'pool' => '0123456789',
                    ];
                }
            }
        );

        $order = $this->makeOrder('srv142353153');
        $rule = $this->invokeResolveUpstreamProvisionHostnameRule($service, $order);

        $this->assertSame('ser', $rule['prefix'] ?? null);
        $this->assertSame(15, $rule['length'] ?? null);
        $this->assertSame('0123456789', $rule['pool'] ?? null);
    }

    #[Test]
    public function it_uses_top_level_product_rule_when_host_payload_omits_nested_rule(): void
    {
        $service = new ProvisionService(
            $this->makeProviderResolver(new class($this->buildTopLevelProductRuleUpstreamProductConfigResponse()) extends HostingPanelApiTransport
            {
                public function __construct(private readonly array $response) {}

                public function login(Supplier $supplier): string
                {
                    return 'jwt-test-token';
                }

                public function get(
                    Supplier $supplier,
                    string $uri,
                    ?string $jwt = null,
                    array $query = [],
                    array $headers = []
                ): array {
                    return $this->response;
                }
            }),
            new class extends SettingService
            {
                public function getProvisionHostnameConfig(): array
                {
                    return [
                        'prefix' => 'srv',
                        'length' => 12,
                        'pool' => '0123456789',
                    ];
                }
            }
        );

        $order = $this->makeOrder('snapshot-host', 504);
        $hostname = $this->invokeResolveProvisionHostname($service, $order);

        $this->assertStringStartsWith('ser', $hostname);
        $this->assertSame(15, mb_strlen($hostname));
        $this->assertNotSame('snapshot-host', $hostname);
        $this->assertSame($hostname, (string) (($order->config_snapshot ?? [])['hostname'] ?? ''));
    }

    #[Test]
    public function it_preserves_mofang_provider_key_after_successful_upstream_provisioning(): void
    {
        $transport = new class extends HostingPanelApiTransport
        {
            public function __construct() {}

            public function login(Supplier $supplier): string
            {
                return 'jwt-test-token';
            }

            public function request(
                Supplier $supplier,
                string $method,
                string $uri,
                array|string $payload = [],
                ?string $jwt = null,
                array $headers = [],
                array $query = []
            ): array {
                return ['status' => 200, 'code' => 200, 'data' => []];
            }

            public function post(
                Supplier $supplier,
                string $uri,
                array|string $payload = [],
                ?string $jwt = null,
                array $headers = [],
                array $query = []
            ): array {
                if ($uri === '/v1/cart/checkout') {
                    return [
                        'status' => 200,
                        'code' => 200,
                        'data' => [
                            'invoiceid' => 8899,
                            'hostid' => [7788],
                        ],
                    ];
                }

                return ['status' => 200, 'code' => 200, 'data' => []];
            }

            public function get(
                Supplier $supplier,
                string $uri,
                ?string $jwt = null,
                array $query = [],
                array $headers = []
            ): array {
                if ($uri === '/v1/cart') {
                    return [
                        'status' => 200,
                        'code' => 200,
                        'data' => [
                            'gateway_list' => [
                                ['name' => 'credit'],
                            ],
                        ],
                    ];
                }

                if ($uri === '/v1/hosts/7788') {
                    return [
                        'status' => 200,
                        'code' => 200,
                        'data' => [
                            'host' => [
                                'domain' => 'srv7788.example.test',
                                'domainstatus' => 'Active',
                                'product_id' => 9001,
                                'product_name' => '魔方云服务器',
                            ],
                        ],
                    ];
                }

                return ['status' => 200, 'code' => 200, 'data' => []];
            }
        };

        $service = new ProvisionService(
            $this->makeProviderResolver($transport, true),
            new class extends SettingService
            {
                public function getProvisionHostnameConfig(): array
                {
                    return [
                        'prefix' => 'srv',
                        'length' => 12,
                        'pool' => '0123456789',
                    ];
                }
            }
        );

        $order = $this->makeOrder('srv7788', 505);
        $order->exists = true;
        $order->forceFill([
            'type' => 'new',
            'status' => OrderStatus::PAID,
        ]);
        $order->product->forceFill([
            'provision_module' => ProviderKey::MOFANG_FINANCE_API,
        ]);
        $order->product->supplier->forceFill([
            'interface_type' => ProviderKey::MOFANG_FINANCE_API,
        ]);

        $createdService = $service->processPaidOrder($order);

        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, $createdService->provision_data['provider'] ?? null);
        $this->assertSame(7788, $createdService->provision_data['upstream_host_id'] ?? null);
        $this->assertSame(ServiceStatus::ACTIVE, (int) $createdService->status);
    }

    #[Test]
    public function it_falls_back_to_snapshot_hostname_when_upstream_rule_is_unavailable(): void
    {
        $service = new ProvisionService(
            $this->makeProviderResolver(new class extends HostingPanelApiTransport
            {
                public function __construct() {}

                public function login(Supplier $supplier): string
                {
                    return 'jwt-test-token';
                }

                public function get(
                    Supplier $supplier,
                    string $uri,
                    ?string $jwt = null,
                    array $query = [],
                    array $headers = []
                ): array {
                    return ['data' => ['first_group' => []]];
                }
            }),
            new class extends SettingService
            {
                public function getProvisionHostnameConfig(): array
                {
                    return [
                        'prefix' => 'srv',
                        'length' => 12,
                        'pool' => '0123456789',
                    ];
                }
            }
        );

        $order = $this->makeOrder('ltser1234567890', 502);
        $hostname = $this->invokeResolveProvisionHostname($service, $order);

        $this->assertSame('ltser1234567890', $hostname);
    }

    #[Test]
    public function it_enforces_system_hostname_rule_even_when_product_rule_uses_fixed_hostname(): void
    {
        $service = new ProvisionService(
            $this->makeProviderResolver(new class extends HostingPanelApiTransport
            {
                public function __construct() {}

                public function login(Supplier $supplier): string
                {
                    return 'jwt-test-token';
                }

                public function get(
                    Supplier $supplier,
                    string $uri,
                    ?string $jwt = null,
                    array $query = [],
                    array $headers = []
                ): array {
                    return ['data' => ['first_group' => []]];
                }
            }),
            new class extends SettingService
            {
                public function getProvisionHostnameConfig(): array
                {
                    return [
                        'enforce' => true,
                        'prefix' => 'sys',
                        'length' => 12,
                        'pool' => '0123456789',
                    ];
                }
            }
        );

        $order = $this->makeOrder('snapshot-host', 503, [
            'provision_hostname' => [
                'mode' => ProductProvisionHostname::MODE_FIXED,
                'value' => 'fixed-host',
                'length' => 10,
            ],
        ]);
        $hostname = $this->invokeResolveProvisionHostname($service, $order);

        $this->assertMatchesRegularExpression('/^sys\d{9}$/', $hostname);
        $this->assertSame(12, mb_strlen($hostname));
        $this->assertNotSame('snapshot-host', $hostname);
        $this->assertNotSame('fixed-host', $hostname);
        $this->assertSame($hostname, (string) (($order->config_snapshot ?? [])['hostname'] ?? ''));
    }

    #[Test]
    public function retry_failed_provision_reuses_order_snapshot_hostname_when_resubmitting_to_upstream(): void
    {
        $captured = new ArrayObject;

        $provisionService = new ProvisionService(
            $this->makeProviderResolver(new class($captured) extends HostingPanelApiTransport
            {
                public function __construct(private readonly ArrayObject $captured) {}

                public function login(Supplier $supplier): string
                {
                    return 'jwt-test-token';
                }

                public function get(
                    Supplier $supplier,
                    string $uri,
                    ?string $jwt = null,
                    array $query = [],
                    array $headers = []
                ): array {
                    if ($uri === '/v1/productsconfig') {
                        return ['data' => ['first_group' => []]];
                    }

                    return ['status' => 200, 'data' => []];
                }

                public function request(
                    Supplier $supplier,
                    string $method,
                    string $uri,
                    array|string $payload = [],
                    ?string $jwt = null,
                    array $headers = [],
                    array $query = []
                ): array {
                    if ($uri === '/v1/cart/products') {
                        $this->captured['payload'] = json_decode((string) $payload, true);

                        return [
                            'status' => 400,
                            'msg' => '主机名前缀必须是ser;长度为13;',
                        ];
                    }

                    return ['status' => 200, 'data' => []];
                }
            }),
            new class extends SettingService
            {
                public function getProvisionHostnameConfig(): array
                {
                    return [
                        'prefix' => 'srv',
                        'length' => 12,
                        'pool' => '0123456789',
                    ];
                }
            }
        );

        [$order, $service] = $this->makeRetryOrderAndService('ser1234567890', 'srv034315321');

        try {
            $provisionService->retryFailedProvision($order);
            $this->fail('预期应抛出上游购物车错误');
        } catch (BusinessException $exception) {
            $this->assertSame('加入上游购物车失败：主机名前缀必须是ser;长度为13;', $exception->getMessage());
        }

        $this->assertSame('ser1234567890', (string) (($captured['payload']['host'] ?? '')));
        $this->assertSame('ser1234567890', (string) $service->domain);
        $this->assertSame(ServiceStatus::PENDING, (int) $service->status);
        $this->assertSame(OrderStatus::PROCESSING, (int) $order->status);
        $this->assertSame(
            '加入上游购物车失败：主机名前缀必须是ser;长度为13;',
            (string) (($service->provision_data ?? [])['provision_error'] ?? '')
        );
        $this->assertFalse(array_key_exists('upstream_host_id', (array) ($service->provision_data ?? [])));
    }

    #[Test]
    public function ensure_local_service_uses_resolved_hostname_as_instance_name(): void
    {
        $provisionService = new ProvisionService(
            $this->makeProviderResolver(new class extends HostingPanelApiTransport
            {
                public function __construct() {}

                public function login(Supplier $supplier): string
                {
                    return 'jwt-test-token';
                }

                public function get(
                    Supplier $supplier,
                    string $uri,
                    ?string $jwt = null,
                    array $query = [],
                    array $headers = []
                ): array {
                    return ['data' => ['first_group' => []]];
                }
            }),
            new class extends SettingService
            {
                public function getProvisionHostnameConfig(): array
                {
                    return [
                        'prefix' => 'srv',
                        'length' => 12,
                        'pool' => '0123456789',
                    ];
                }
            }
        );

        $order = $this->makeOrder('ltser1234567890', 501);
        $order->forceFill([
            'amount' => 5.00,
            'product_spec_snapshot' => '通用NAT-2vcpu-1gib',
            'config_snapshot' => [
                'hostname' => 'ltser1234567890',
                'cpu' => '2',
                'memory' => '2048',
            ],
        ]);
        $order->setRelation('service', null);

        $service = $this->invokeEnsureLocalService($provisionService, $order);

        $this->assertSame('通用NAT-2vcpu-1gib', (string) $service->name);
        $this->assertSame('ltser1234567890', (string) $service->domain);
    }

    #[Test]
    public function process_paid_order_keeps_instance_spec_name_after_upstream_success(): void
    {
        $provisionService = new ProvisionService(
            $this->makeProviderResolver(new class extends HostingPanelApiTransport
            {
                public function __construct() {}

                public function login(Supplier $supplier): string
                {
                    return 'jwt-test-token';
                }

                public function get(
                    Supplier $supplier,
                    string $uri,
                    ?string $jwt = null,
                    array $query = [],
                    array $headers = []
                ): array {
                    if ($uri === '/v1/productsconfig') {
                        return ['data' => ['first_group' => []]];
                    }

                    if ($uri === '/v1/cart') {
                        return [
                            'status' => 200,
                            'data' => [
                                'default_gateway' => 'AliPay',
                                'gateway_list' => [
                                    ['name' => 'AliPay'],
                                ],
                            ],
                        ];
                    }

                    if (str_contains($uri, '/v1/hosts/')) {
                        return [
                            'status' => 200,
                            'data' => [
                                'host' => [
                                    'id' => 75831,
                                    'domain' => 'ser380376647391',
                                    'domainstatus' => 'Active',
                                    'product_id' => 831,
                                    'product_name' => '西安云电脑 A型',
                                    'os' => 'CentOS-7.6.1810-x64',
                                ],
                            ],
                        ];
                    }

                    return ['status' => 200, 'data' => []];
                }

                public function request(
                    Supplier $supplier,
                    string $method,
                    string $uri,
                    array|string $payload = [],
                    ?string $jwt = null,
                    array $headers = [],
                    array $query = []
                ): array {
                    if ($uri === '/v1/cart/products') {
                        return ['status' => 200, 'msg' => 'Added successfully'];
                    }

                    if ($uri === '/v1/cart/settle') {
                        return [
                            'status' => 200,
                            'data' => [
                                'cart_products' => [
                                    ['productid' => '831'],
                                ],
                            ],
                        ];
                    }

                    if ($uri === '/v1/cart/checkout') {
                        return [
                            'status' => 200,
                            'msg' => 'Successful purchase',
                            'data' => ['invoiceid' => 978109],
                        ];
                    }

                    if ($uri === '/v1/invoices/978109/fund') {
                        return [
                            'status' => 1001,
                            'msg' => 'Payment completed',
                            'data' => [
                                'hostid' => [75831],
                                'url' => 'servicedetail?id=75831',
                            ],
                        ];
                    }

                    return ['status' => 200, 'data' => []];
                }
            }),
            new class extends SettingService
            {
                public function getProvisionHostnameConfig(): array
                {
                    return [
                        'prefix' => 'ser',
                        'length' => 15,
                        'pool' => '0123456789',
                    ];
                }
            }
        );

        $order = $this->makeOrder('ser380376647391', 505);
        $order->forceFill([
            'id' => null,
            'type' => 'new',
            'status' => OrderStatus::PAID,
            'amount' => 5.00,
            'product_spec_snapshot' => '通用NAT-2vcpu-1gib',
            'config_snapshot' => [
                'hostname' => 'ser380376647391',
                'cpu' => '2',
                'memory' => '1024',
            ],
        ])->save();
        $order->exists = true;
        $order->setRelation('service', null);

        $service = $provisionService->processPaidOrder($order);

        $this->assertNotNull($service);
        $this->assertSame('通用NAT-2vcpu-1gib', (string) $service->name);
        $this->assertSame(ServiceStatus::ACTIVE, (int) $service->status);
        $this->assertSame(OrderStatus::COMPLETED, (int) $order->status);
    }

    private function makeOrder(
        string $snapshotHostname,
        int $productId = 501,
        array $purchaseRequires = [
            'provision_hostname' => [
                'mode' => 'system',
            ],
        ]
    ): Order {
        $suffix = bin2hex(random_bytes(4));

        $supplier = Supplier::query()->create([
            'code' => 'test-hosting-'.$suffix,
            'name' => '测试供应商-'.$suffix,
            'interface_type' => 'hosting_panel_api',
            'status' => 1,
            'sort_order' => 0,
        ]);

        $user = User::query()->create([
            'email' => 'provision-hostname-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Provision Hostname',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);

        $product = Product::query()->create([
            'id' => $productId,
            'product_type' => 'vps',
            'remark' => '西安云电脑',
            'supplier_id' => (int) $supplier->id,
            'supplier_product_id' => 9001,
            'auto_setup' => 1,
            'provision_module' => 'hosting_panel_api',
            'pricing' => ['monthly' => '5.00'],
            'purchase_requires' => $purchaseRequires,
            'config_options' => [
                [
                    'field' => 'cpu',
                    'option_type' => 6,
                    'sub' => [
                        ['id' => '2', 'option_name' => '2核'],
                    ],
                ],
                [
                    'field' => 'memory',
                    'option_type' => 8,
                    'sub' => [
                        ['id' => '2048', 'option_name' => '2G'],
                    ],
                ],
            ],
        ]);
        $product->setRelation('supplier', $supplier);

        $order = new class extends Order
        {
            public function save(array $options = []): bool
            {
                $this->syncOriginal();

                return true;
            }
        };

        $order->forceFill([
            'id' => 3001,
            'order_no' => 'ORD20260404000001TEST',
            'user_id' => (int) $user->id,
            'product_id' => $productId,
            'billing_cycle' => 'monthly',
            'amount' => 5.00,
            'product_spec_snapshot' => '通用NAT-2vcpu-1gib',
            'config_snapshot' => [
                'hostname' => $snapshotHostname,
            ],
        ]);
        $order->setRelation('product', $product);

        return $order;
    }

    private function makeRetryOrderAndService(string $snapshotHostname, string $serviceDomain): array
    {
        $supplier = new Supplier([
            'id' => 102,
            'interface_type' => 'hosting_panel_api',
        ]);

        $product = new Product([
            'id' => 601,
            'name' => '成都云电脑 2H2G',
            'supplier_id' => 102,
            'supplier_product_id' => 665,
            'auto_setup' => 1,
            'provision_module' => 'hosting_panel_api',
            'purchase_requires' => [
                'provision_hostname' => [
                    'mode' => 'system',
                ],
            ],
            'config_options' => [],
        ]);
        $product->setRelation('supplier', $supplier);

        $order = new class extends Order
        {
            public function save(array $options = []): bool
            {
                $this->syncOriginal();

                return true;
            }
        };

        $order->forceFill([
            'id' => 4001,
            'order_no' => 'ORD20260404000002RETRY',
            'type' => 'new',
            'user_id' => 8001,
            'product_id' => 601,
            'service_id' => 5001,
            'billing_cycle' => 'monthly',
            'amount' => 99.00,
            'status' => OrderStatus::PROCESSING,
            'config_snapshot' => [
                'hostname' => $snapshotHostname,
                'password' => 'TestPass123',
            ],
        ]);
        $order->exists = true;
        $order->setRelation('product', $product);

        $service = new class extends Service
        {
            public function save(array $options = []): bool
            {
                $this->syncOriginal();

                return true;
            }
        };

        $service->forceFill([
            'id' => 5001,
            'user_id' => 8001,
            'product_id' => 601,
            'order_id' => 4001,
            'name' => '成都云电脑 2H2G',
            'domain' => $serviceDomain,
            'billing_cycle' => 'monthly',
            'amount' => 99.00,
            'status' => ServiceStatus::PENDING,
            'provision_data' => [
                'provision_error' => '旧的上游开通失败记录',
                'requested_config' => [
                    'hostname' => $snapshotHostname,
                    'password' => '***',
                ],
                'upstream_host_id' => 9999,
            ],
        ]);
        $service->exists = true;
        $service->setRelation('product', $product);
        $service->setRelation('order', $order);

        $order->setRelation('service', $service);

        return [$order, $service];
    }

    private function invokeResolveProvisionHostname(ProvisionService $service, Order $order): string
    {
        $method = new \ReflectionMethod($service, 'resolveProvisionHostname');
        $method->setAccessible(true);

        return (string) $method->invoke($service, $order);
    }

    private function invokeResolveUpstreamProvisionHostnameRule(ProvisionService $service, Order $order): array
    {
        $method = new \ReflectionMethod($service, 'resolveUpstreamProvisionHostnameRule');
        $method->setAccessible(true);

        return (array) $method->invoke($service, $order);
    }

    private function invokeEnsureLocalService(ProvisionService $service, Order $order): Service
    {
        $method = new \ReflectionMethod($service, 'ensureLocalService');
        $method->setAccessible(true);

        return $method->invoke($service, $order);
    }

    private function makeProviderResolver(HostingPanelApiTransport $transport, bool $includeMofang = false): ProviderResolver
    {
        $drivers = [
            new HostingPanelApiDriver($transport),
        ];

        if ($includeMofang) {
            $drivers[] = new MofangFinanceDriver(new MofangFinanceAdapter($transport, new MofangCloudConfigTemplate));
        }

        return new ProviderResolver(new ProviderRegistry($drivers));
    }

    private function buildUpstreamProductConfigResponse(): array
    {
        return [
            'data' => [
                'first_group' => [
                    [
                        'group' => [
                            [
                                'products' => [
                                    [
                                        'id' => 9001,
                                        'host' => [
                                            'show' => 1,
                                            'prefix' => 'ser',
                                            'len_num' => 13,
                                            'num' => 1,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildSingleProductUpstreamProductConfigResponse(): array
    {
        return [
            'data' => [
                'products' => [
                    'id' => 9001,
                    'host' => json_encode([
                        'show' => 0,
                        'prefix' => 'ser',
                        'rule' => [
                            'num' => 1,
                            'len_num' => 12,
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];
    }

    private function buildTopLevelProductRuleUpstreamProductConfigResponse(): array
    {
        return [
            'data' => [
                'product' => [
                    'id' => 9001,
                    'host' => [
                        'show' => 0,
                        'prefix' => 'ser',
                    ],
                    'rule' => [
                        'num' => 1,
                        'len_num' => 12,
                    ],
                ],
            ],
        ];
    }
}
