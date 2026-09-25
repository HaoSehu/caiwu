<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IntegrationPlugin;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\ClientServiceConsole\ClientServiceConsoleService;
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\Provisioning\AdminServiceListService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 服务列表契约护栏（D3B-01/D3B-02/D4B-01 回归）：
 * 1. 管理端与客户端控制台两套服务列表转换的重名键必须同型，且本次统一的
 *    product_display_name / product_full_path / auto_renew 必须同值；
 * 2. 列表批量预载（preloadServiceProjections）后的投影输出与逐行查询完全一致；
 * 3. 列表路径的绑定表查询次数不随行数线性增长。
 */
class ServiceListContractConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 管理端与客户端列表的顶层重名键（审查报告 D3B-01 清单）。
     */
    private const COMMON_TOP_LEVEL_KEYS = [
        'id', 'name', 'product_display_name', 'product_full_path', 'domain',
        'custom_hostname', 'has_custom_hostname', 'status', 'status_label',
        'billing_cycle', 'amount', 'expires_at', 'created_at', 'auto_renew',
        'product', 'invoice',
    ];

    /**
     * 嵌套重名键（D4B-04 补充证据：product.name/type、invoice.id/invoice_no）。
     */
    private const COMMON_NESTED_KEYS = [
        ['product', 'name'],
        ['product', 'type'],
        ['invoice', 'id'],
        ['invoice', 'invoice_no'],
    ];

    public function test_admin_and_client_list_items_share_same_types_and_values(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'name' => '测试云主机',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $service = $this->createService($user, $product, [
            'provision_data' => [
                'dedicated_ip' => '1.2.3.4',
                'os' => 'centos7',
                'upstream_status' => 'active',
            ],
        ]);
        $second = $this->createService($user, $product, []);
        $third = $this->createService($user, $product, ['domain' => 'plain.example.test']);
        // 测试库为多任务共享：管理端列表是全站的，这里只断言本测试创建的服务
        $createdIds = [(int) $service->id, (int) $second->id, (int) $third->id];

        $adminList = app(AdminServiceListService::class)->paginate(['page_size' => 100]);
        $clientList = app(ClientServiceConsoleService::class)->paginateForUser($user, ['page_size' => 20]);

        $adminById = collect($adminList['list'])->keyBy(fn (array $item) => (int) $item['id']);
        $clientById = collect($clientList['list'])->keyBy(fn (array $item) => (int) $item['id']);

        foreach ($createdIds as $serviceId) {
            $this->assertArrayHasKey($serviceId, $adminById, "管理端列表缺少本测试创建的服务 {$serviceId}");
            $this->assertArrayHasKey($serviceId, $clientById, "客户端列表缺少本测试创建的服务 {$serviceId}");

            $adminItem = $adminById[$serviceId];
            $clientItem = $clientById[$serviceId];

            foreach (self::COMMON_TOP_LEVEL_KEYS as $key) {
                $this->assertArrayHasKey($key, $adminItem, "管理端缺少重名键 {$key}");
                $this->assertArrayHasKey($key, $clientItem, "客户端缺少重名键 {$key}");
                $this->assertSame(
                    gettype($adminItem[$key]),
                    gettype($clientItem[$key]),
                    "服务 {$serviceId} 的重名键 {$key} 两侧类型不一致"
                );
            }

            foreach (self::COMMON_NESTED_KEYS as [$parent, $key]) {
                $this->assertSame(
                    gettype($adminItem[$parent][$key] ?? null),
                    gettype($clientItem[$parent][$key] ?? null),
                    "服务 {$serviceId} 的 {$parent}.{$key} 两侧类型不一致"
                );
            }

            // 本次有意统一的三个口径：同值断言（同输入同输出）
            $this->assertSame($adminItem['product_display_name'], $clientItem['product_display_name'], 'product_display_name 两侧取值不一致');
            $this->assertSame($adminItem['product_full_path'], $clientItem['product_full_path'], 'product_full_path 两侧取值不一致');
            $this->assertSame($adminItem['auto_renew'], $clientItem['auto_renew'], 'auto_renew 两侧类型/取值不一致（已统一为 int）');
            $this->assertSame($adminItem['amount'], $clientItem['amount']);
            $this->assertSame($adminItem['status_label'], $clientItem['status_label']);
        }
    }

    public function test_preloaded_projection_matches_row_by_row_projection(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'name' => '绑定云主机',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        // entry_class 为 NOT NULL 展示列且此处无消费方：写入真实存在的插件入口类，
        // 不再保留指向不存在类的死引用
        $plugin = IntegrationPlugin::query()->create([
            'domain' => 'servers',
            'slug' => 'list_contract_test',
            'plugin_key' => 'list_contract_test',
            'name' => '列表契约测试插件',
            'entry_class' => Caiwu\Plugins\Servers\DemoServers\DemoServersPlugin::class,
        ]);
        $service = $this->createService($user, $product, [
            'domain' => 'bound.example.test',
            'provision_data' => [
                'custom_legacy_key' => 'legacy-only',
            ],
        ]);
        DB::table('service_upstream_bindings')->insert([
            'service_id' => $service->id,
            'plugin_id' => $plugin->id,
            'provider_key' => 'hosting_panel_api',
            'upstream_service_id' => 'host-777',
            'upstream_account_id' => 'acc-1',
            'status_snapshot' => 'active',
            'runtime_snapshot_json' => json_encode(['runtime_status' => 'running']),
            'connection_snapshot_json' => json_encode(['dedicated_ip' => '5.6.7.8']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('service_runtime_snapshots')->insert([
            'service_id' => $service->id,
            'provider_key' => 'hosting_panel_api',
            'status_key' => 'running',
            'status_text' => '运行中',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('service_connection_snapshots')->insert([
            'service_id' => $service->id,
            'connection_type' => 'default',
            'provider_key' => 'hosting_panel_api',
            'hostname' => 'bound.example.test',
            'ip_address' => '9.9.9.9',
            'connection_json' => json_encode(['internal_ip' => '10.0.0.9']),
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 逐行查询口径（未预载的新实例）
        $rowWise = (new PluginBindingResolver)->serviceProvisionProjection($service->refresh(), true);

        // 批量预载口径（同一实例先 preload 再取投影）
        $batched = new PluginBindingResolver;
        $batched->preloadServiceProjections([(int) $service->id]);
        $batchedProjection = $batched->serviceProvisionProjection($service->refresh(), true);

        $this->assertSame($rowWise, $batchedProjection, '批量预载与逐行查询的投影输出必须完全一致');

        // serviceProvisionData 单一入口与原合并语义一致：投影键覆盖 legacy 同名键，legacy 独有键保留
        $merged = $batched->serviceProvisionData($service->refresh(), true);
        $this->assertSame('host-777', $merged['upstream_host_id']);
        $this->assertSame('9.9.9.9', (string) ($merged['dedicated_ip'] ?? ''), '投影键应覆盖 legacy 同名键');
        $this->assertSame('legacy-only', $merged['custom_legacy_key'] ?? null, 'legacy 独有键应原样保留');
    }

    public function test_client_list_binding_queries_do_not_scale_with_rows(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'name' => '批量云主机',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        for ($i = 0; $i < 5; $i++) {
            $this->createService($user, $product, ['domain' => "batch-{$i}.example.test"]);
        }

        $bindingQueryCount = 0;
        $runtimeQueryCount = 0;
        DB::listen(function (object $query) use (&$bindingQueryCount, &$runtimeQueryCount): void {
            if (str_contains($query->sql, 'service_upstream_bindings')) {
                $bindingQueryCount++;
            }
            if (str_contains($query->sql, 'service_runtime_snapshots')) {
                $runtimeQueryCount++;
            }
        });

        app(ClientServiceConsoleService::class)->paginateForUser($user, ['page_size' => 20]);

        // 修复前：每行约 6 次绑定查询（5 行 ≈ 30 次）；修复后：批量 whereIn 至多 1 次 + 少量常量开销
        $this->assertLessThan(10, $bindingQueryCount, "绑定表查询次数随行数线性增长：{$bindingQueryCount}");
        $this->assertLessThan(10, $runtimeQueryCount, "运行快照表查询次数随行数线性增长：{$runtimeQueryCount}");
    }

    private function createService(User $user, Product $product, array $overrides): Service
    {
        return Service::query()->create(array_merge([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => '服务实例',
            'domain' => 'svc.example.test',
            'billing_cycle' => 'monthly',
            'amount' => 35,
            'status' => 1,
            'provision_data' => [],
            'auto_renew' => 1,
            'expires_at' => now()->addMonth(),
        ], $overrides));
    }
}
