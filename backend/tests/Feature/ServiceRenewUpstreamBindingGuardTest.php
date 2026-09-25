<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderType;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\CouponService;
use App\Services\Finance\InvoiceService;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\System\OperationLogService;
use App\Services\System\SettingService;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * D3A-01/D3A-02 回归：
 * - 服务绑定缺失时通过 provision_data['upstream_host_id'] 兜底解析上游实例，
 *   供应商可解析时正常走上游续费（不再静默降级为纯本地续费）；
 * - 供应商绑定完全缺失时续费显式失败（BusinessException），禁止静默本地顺延；
 * - 无账单型上游（renew_completed_without_invoice，如 kanghostx）续费已完成时，
 *   不再因 upstream_invoice_id=0 命中硬校验而把成功续费记为失败。
 */
class ServiceRenewUpstreamBindingGuardTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_PROVIDER_KEY = 'fake_renewable';

    public function test_renew_falls_back_to_provision_data_host_id_and_succeeds_without_invoice(): void
    {
        // 场景 A：服务绑定缺失，provision_data 记录了上游实例；供应商经商品绑定可解析；
        // 模拟 kanghostx 无账单型上游：续费成功但 upstream_invoice_id=0。
        $driver = $this->fakeRenewalDriver();
        $driver->renewResult = [
            'upstream_invoice_id' => 0,
            'renew_completed_without_invoice' => true,
            'host_detail' => ['domainstatus' => 'active'],
        ];

        [$user, $service, $invoice, $originalExpiresAt] = $this->renewFixture($driver, withProductBinding: true);
        $service->forceFill([
            'provision_data' => ['upstream_host_id' => 85845],
        ])->save();

        $renewService = $this->makeService($driver);
        $updated = $renewService->processPaidRenewInvoice($invoice);

        $this->assertNotNull($updated);
        // 上游无账单号也必须按成功履约处理：到期顺延 + 履约状态 succeeded
        $this->assertTrue($updated->expires_at->gt($originalExpiresAt));
        $this->assertSame(
            'succeeded',
            (string) (data_get($updated->fresh()->provision_data, 'renew_fulfillment_status'))
        );
        $this->assertSame(
            'succeeded',
            (string) (data_get($invoice->fresh()->config_snapshot, 'renew_fulfillment_status'))
        );
        // D3A-01：hostId 来自 provision_data 兜底（服务无绑定行）
        $this->assertSame(85845, $driver->renewCalls[0]['host_id'] ?? 0);
    }

    public function test_renew_fails_explicitly_when_supplier_binding_missing(): void
    {
        // 场景 B：服务有上游实例标识（绑定行存在但供应商绑定缺失），供应商无法解析——
        // 修复前会静默走本地续费（到期顺延、上游不动），修复后显式失败转人工补绑定。
        $driver = $this->fakeRenewalDriver();

        [$user, $service, $invoice, $originalExpiresAt] = $this->renewFixture($driver, withProductBinding: false);
        DB::table('service_upstream_bindings')->insert([
            'service_id' => $service->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'upstream_service_id' => '85845',
            'supplier_plugin_binding_id' => null,
            'product_upstream_binding_id' => null,
        ]);

        try {
            $this->makeService($driver)->processPaidRenewInvoice($invoice);
            $this->fail('供应商绑定缺失时续费应当显式失败');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('服务缺少上游供应商绑定', $exception->getMessage());
        }

        $fresh = $service->fresh();
        // 到期时间未被续费顺延（按秒比较，规避数据库秒级截断与毫秒差异）
        $this->assertSame(
            $originalExpiresAt->format('Y-m-d H:i:s'),
            $fresh->expires_at?->format('Y-m-d H:i:s')
        );
        $this->assertNotSame(
            'succeeded',
            (string) (data_get($fresh->provision_data, 'renew_fulfillment_status'))
        );
        $this->assertSame([], $driver->renewCalls);
    }

    public function test_generic_renew_chain_still_requires_upstream_invoice_id(): void
    {
        // 场景 C（D3A-02 负向对照）：通用 renewHost+fund 链路（结果不带
        // renew_completed_without_invoice 标记）upstream_invoice_id=0 时，仍必须判定失败——
        // 无账单豁免只允许显式声明的上游使用，不得被泛化。
        $driver = $this->fakeRenewalDriver();
        $driver->renewResult = [
            'upstream_invoice_id' => 0,
            'host_detail' => ['domainstatus' => 'active'],
        ];

        [$user, $service, $invoice, $originalExpiresAt] = $this->renewFixture($driver, withProductBinding: true);
        $service->forceFill([
            'provision_data' => ['upstream_host_id' => 85845],
        ])->save();

        // 上游续费失败不向支付侧抛出（区别于绑定缺失的显式失败）：标记履约 failed 后返回，
        // 由重试/补偿机制兜底——这里断言的正是"无豁免标记时 0 账单号被判失败"这一生产语义。
        $updated = $this->makeService($driver)->processPaidRenewInvoice($invoice);

        $this->assertNotNull($updated);
        $fresh = $service->fresh();
        // 到期时间未被续费顺延，履约状态记为失败且错误信息可定位
        $this->assertSame(
            $originalExpiresAt->format('Y-m-d H:i:s'),
            $fresh->expires_at?->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            'failed',
            (string) (data_get($fresh->provision_data, 'renew_fulfillment_status'))
        );
        $this->assertStringContainsString(
            '上游未返回续费账单 ID',
            (string) (data_get($fresh->provision_data, 'renew_error'))
        );
    }

    /**
     * 无账单型续费假驱动：模拟 kanghostx 的能力声明与结果形状（不触网）。
     */
    private function fakeRenewalDriver(): object
    {
        return new class implements ProvidesRenewal, UpstreamDriver
        {
            /** @var array<int, array{host_id: int, billing_cycle: string}> */
            public array $renewCalls = [];

            /** @var array<string, mixed> */
            public array $renewResult = [];

            public function key(): string
            {
                return 'fake_renewable';
            }

            public function label(): string
            {
                return '测试续费驱动';
            }

            public function capabilities(): array
            {
                return [ProvidesRenewal::class];
            }

            public function supports(string $capability): bool
            {
                return $capability === ProvidesRenewal::class;
            }

            public function resolve(string $capability): ?object
            {
                return $this->supports($capability) ? $this : null;
            }

            public function renewHost(Supplier $supplier, int $hostId, string $billingCycle): array
            {
                return ['status' => 200, 'data' => ['host_id' => $hostId]];
            }

            public function renewServiceInvoice(Supplier $supplier, int $hostId, string $billingCycle): array
            {
                $this->renewCalls[] = ['host_id' => $hostId, 'billing_cycle' => $billingCycle];

                return $this->renewResult;
            }
        };
    }

    /**
     * @return array{0: User, 1: Service, 2: Invoice, 3: Carbon}
     */
    private function renewFixture(object $driver, bool $withProductBinding): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => $originalExpiresAt = now()->addDays(20),
        ]);
        $invoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'service_id' => $service->id,
            'type' => OrderType::RENEW,
            'amount' => 35.00,
            'status' => InvoiceStatus::PAID,
            'billing_cycle' => 'monthly',
            'paid_at' => now(),
        ]);

        if ($withProductBinding) {
            // 造"商品→供应商"绑定链，使续费能解析出供应商；服务级绑定保持缺失（D3A-01 场景）
            $supplier = Supplier::query()->create([
                'name' => '测试供应商-'.uniqid(),
                'code' => 'SUP-RN-'.uniqid(),
            ]);
            $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
                'supplier_id' => $supplier->id,
                'plugin_id' => $this->pluginId,
                'provider_key' => self::FAKE_PROVIDER_KEY,
                'status' => 1,
            ]);
            DB::table('product_upstream_bindings')->insert([
                'product_id' => $product->id,
                'supplier_plugin_binding_id' => $supplierBindingId,
                'plugin_id' => $this->pluginId,
                'provider_key' => self::FAKE_PROVIDER_KEY,
                'upstream_product_id' => 'p-'.uniqid(),
                'status' => 1,
            ]);
        }

        return [$user, $service, $invoice, $originalExpiresAt];
    }

    private function makeService(object $driver): ServiceRenewService
    {
        return new ServiceRenewService(
            app(InvoiceService::class),
            new ProviderResolver(new ProviderRegistry([$driver])),
            app(CouponService::class),
            app(OperationLogService::class),
            app(SettingService::class),
        );
    }

    private int $pluginId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // 注册 fake 插件行：provider_key → plugin_id 解析依赖 integration_plugins；
        // 插入发生在测试事务内，由 DatabaseTransactions 统一回滚，不做手动清理
        $existing = DB::table('integration_plugins')
            ->where('domain', PluginDomain::UPSTREAM)
            ->where('plugin_key', self::FAKE_PROVIDER_KEY)
            ->first(['id']);
        $this->pluginId = $existing !== null
            ? (int) $existing->id
            : (int) DB::table('integration_plugins')->insertGetId([
                'domain' => PluginDomain::UPSTREAM,
                'slug' => self::FAKE_PROVIDER_KEY,
                'plugin_key' => self::FAKE_PROVIDER_KEY,
                'name' => '测试续费插件',
                'entry_class' => 'Tests\\FakeRenewable',
                'status' => 1,
            ]);
    }
}
