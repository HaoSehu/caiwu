<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Exceptions\BusinessException;
use App\Jobs\ProcessPaidOrderFulfillmentJob;
use App\Jobs\ProcessPaidOrderReferralRewardJob;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Finance\PaymentService;
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\Provisioning\ProvisionService;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\Upstream\Contracts\ProvidesSynchronousNewPurchaseFulfillment;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use App\Services\User\AccountService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaidOrderBusinessFlowDispatcherTest extends TestCase
{
    public function test_web_payment_dispatches_fulfillment_to_queue_instead_of_after_response_sync_execution(): void
    {
        Queue::fake();
        config()->set('queue.default', 'database');

        [$invoice, $order] = $this->createPaidInvoiceWithOrder();

        $this->runAsWebRequest(function () use ($invoice): void {
            app(PaidOrderBusinessFlowDispatcher::class)->dispatchPaidInvoice($invoice->fresh(['order']), 'trace-web-payment');
        });

        Queue::assertPushedOn('referral', ProcessPaidOrderReferralRewardJob::class);
        Queue::assertPushedOn('provision', ProcessPaidOrderFulfillmentJob::class, function (ProcessPaidOrderFulfillmentJob $job) use ($order): bool {
            return (int) $job->orderId === (int) $order->id;
        });
    }

    public function test_web_payment_fulfills_synchronously_when_provider_declares_capability(): void
    {
        Queue::fake([ProcessPaidOrderReferralRewardJob::class]);
        config()->set('queue.default', 'database');

        $product = new Product;
        $product->setAttribute('id', 981);
        $order = new Order;
        $order->setAttribute('id', 982);
        $order->setAttribute('type', 'new');
        $order->setRelation('product', $product);
        $invoice = new Invoice;
        $invoice->setAttribute('id', 983);
        $invoice->setRelation('order', $order);

        $bindingResolver = $this->mock(PluginBindingResolver::class, function ($mock): void {
            $mock->shouldReceive('providerKeyForProduct')
                ->once()
                ->andReturn('declared_sync_provider');
        });

        $driver = new class implements UpstreamDriver
        {
            public function key(): string
            {
                return 'declared_sync_provider';
            }

            public function label(): string
            {
                return 'Declared sync provider';
            }

            public function capabilities(): array
            {
                return [ProvidesSynchronousNewPurchaseFulfillment::class];
            }

            public function supports(string $capability): bool
            {
                return $capability === ProvidesSynchronousNewPurchaseFulfillment::class;
            }

            public function resolve(string $capability): ?object
            {
                return null;
            }
        };
        $dispatcher = new PaidOrderBusinessFlowDispatcher(new ProviderResolver(
            new ProviderRegistry([$driver]),
            $bindingResolver,
        ));
        $this->mock(PaymentService::class, function ($mock) use ($order): void {
            $mock->shouldReceive('processPaidOrderFulfillmentById')
                ->once()
                ->with((int) $order->id)
                ->andReturn(null);
        });

        $this->runAsWebRequest(function () use ($dispatcher, $invoice): void {
            $dispatcher->dispatchPaidInvoice($invoice, 'trace-declared-sync');
        });

        Queue::assertNotPushed(ProcessPaidOrderFulfillmentJob::class);
    }

    public function test_web_payment_queues_zjmf_new_purchase_after_sync_capability_is_removed(): void
    {
        Queue::fake();
        config()->set('queue.default', 'database');

        $product = new Product;
        $product->setAttribute('id', 984);
        $order = new Order;
        $order->setAttribute('id', 985);
        $order->setAttribute('type', 'new');
        $order->setRelation('product', $product);
        $invoice = new Invoice;
        $invoice->setAttribute('id', 986);
        $invoice->setRelation('order', $order);

        $bindingResolver = $this->mock(PluginBindingResolver::class, function ($mock): void {
            $mock->shouldReceive('providerKeyForProduct')
                ->once()
                ->andReturn(ProviderKey::ZJMF_FINANCE_API);
        });

        $driver = new class implements UpstreamDriver
        {
            public function key(): string
            {
                return ProviderKey::ZJMF_FINANCE_API;
            }

            public function label(): string
            {
                return 'ZJMF 财务接口';
            }

            public function capabilities(): array
            {
                return [];
            }

            public function supports(string $capability): bool
            {
                return false;
            }

            public function resolve(string $capability): ?object
            {
                return null;
            }
        };
        $dispatcher = new PaidOrderBusinessFlowDispatcher(new ProviderResolver(
            new ProviderRegistry([$driver]),
            $bindingResolver,
        ));

        $this->runAsWebRequest(function () use ($dispatcher, $invoice): void {
            $dispatcher->dispatchPaidInvoice($invoice, 'trace-zjmf-async');
        });

        Queue::assertPushedOn('provision', ProcessPaidOrderFulfillmentJob::class, function (ProcessPaidOrderFulfillmentJob $job) use ($order): bool {
            return (int) $job->orderId === (int) $order->id;
        });
    }

    public function test_paid_order_jobs_are_unique_by_order_id(): void
    {
        $fulfillmentJob = new ProcessPaidOrderFulfillmentJob(123);
        $referralJob = new ProcessPaidOrderReferralRewardJob(123, 'trace-unique');

        $this->assertInstanceOf(ShouldBeUnique::class, $fulfillmentJob);
        $this->assertInstanceOf(ShouldBeUnique::class, $referralJob);
        $this->assertSame('123', $fulfillmentJob->uniqueId());
        $this->assertSame('123', $referralJob->uniqueId());
    }

    public function test_renew_payment_dispatches_fulfillment_to_queue_without_referral_reward(): void
    {
        Queue::fake();
        config()->set('queue.default', 'database');

        [$invoice, $order] = $this->createPaidInvoiceWithOrder('renew');

        $this->runAsWebRequest(function () use ($invoice): void {
            app(PaymentService::class)->handlePaidInvoice($invoice->fresh(['order']), 'trace-renew-payment');
        });

        Queue::assertNotPushed(ProcessPaidOrderReferralRewardJob::class);
        Queue::assertPushedOn('provision', ProcessPaidOrderFulfillmentJob::class, function (ProcessPaidOrderFulfillmentJob $job) use ($order): bool {
            return (int) $job->orderId === (int) $order->id;
        });
    }

    public function test_balance_order_payment_keeps_fulfillment_pending_until_job_completes(): void
    {
        Queue::fake();
        config()->set('queue.default', 'database');

        [$invoice, $order, $user] = $this->createPaidInvoiceWithOrder(
            'renew',
            OrderStatus::PENDING,
            InvoiceStatus::UNPAID,
        );
        app(AccountService::class)->setCashBalance($user, '100.00');

        $this->runAsWebRequest(function () use ($order, $user): void {
            app(PaymentService::class)->payOrderByBalance($order, $user, ['trace_id' => 'trace-pending-payment']);
        });

        $configSnapshot = (array) ($invoice->fresh()?->config_snapshot ?? []);

        $this->assertTrue((bool) ($configSnapshot['fulfillment_pending'] ?? false));
        $this->assertSame('renew', $configSnapshot['fulfillment_type'] ?? null);
        Queue::assertPushedOn('provision', ProcessPaidOrderFulfillmentJob::class, function (ProcessPaidOrderFulfillmentJob $job) use ($order): bool {
            return (int) $job->orderId === (int) $order->id;
        });
    }

    public function test_fulfillment_pending_is_cleared_after_successful_job_execution(): void
    {
        [$invoice, $order] = $this->createPaidInvoiceWithOrder();
        $invoice->forceFill([
            'config_snapshot' => [
                'fulfillment_pending' => true,
                'fulfillment_type' => 'new',
            ],
        ])->save();

        $this->mock(ProvisionService::class, function ($mock): void {
            $mock->shouldReceive('processPaidOrder')->once()->andReturn(null);
        });

        app(PaymentService::class)->processPaidOrderFulfillmentById((int) $order->id);

        $configSnapshot = (array) ($invoice->fresh()?->config_snapshot ?? []);

        $this->assertFalse((bool) ($configSnapshot['fulfillment_pending'] ?? true));
        $this->assertArrayHasKey('fulfillment_cleared_at', $configSnapshot);
    }

    public function test_fulfillment_pending_remains_when_renewal_is_not_completed(): void
    {
        [$invoice, $order] = $this->createPaidInvoiceWithOrder('renew');
        $invoice->forceFill([
            'config_snapshot' => [
                'fulfillment_pending' => true,
                'fulfillment_type' => 'renew',
            ],
        ])->save();

        $this->mock(ServiceRenewService::class, function ($mock): void {
            $mock->shouldReceive('processPaidRenewOrder')->once()->andReturn(null);
            $mock->shouldReceive('isRenewInvoiceFulfilled')->once()->andReturnFalse();
        });

        try {
            app(PaymentService::class)->processPaidOrderFulfillmentById((int) $order->id);
            $this->fail('未完成的续费履约必须交由队列重试');
        } catch (BusinessException $exception) {
            $this->assertSame('支付后履约未完成，等待后续重试', $exception->getMessage());
        }

        $configSnapshot = (array) ($invoice->fresh()?->config_snapshot ?? []);

        $this->assertTrue((bool) ($configSnapshot['fulfillment_pending'] ?? false));
        $this->assertArrayNotHasKey('fulfillment_cleared_at', $configSnapshot);
    }

    public function test_fulfillment_pending_remains_when_new_purchase_requires_manual_provisioning(): void
    {
        [$invoice, $order] = $this->createPaidInvoiceWithOrder();
        $invoice->forceFill([
            'config_snapshot' => [
                'fulfillment_pending' => true,
                'fulfillment_type' => 'new',
            ],
        ])->save();

        try {
            app(PaymentService::class)->processPaidOrderFulfillmentById((int) $order->id);
            $this->fail('待人工开通的新购不能被当作已完成履约');
        } catch (BusinessException $exception) {
            $this->assertSame('支付后履约未完成，等待后续重试', $exception->getMessage());
        }

        $configSnapshot = (array) ($invoice->fresh()?->config_snapshot ?? []);

        $this->assertTrue((bool) ($configSnapshot['fulfillment_pending'] ?? false));
        $this->assertArrayNotHasKey('fulfillment_cleared_at', $configSnapshot);
        $this->assertSame(OrderStatus::PROCESSING, (int) $order->fresh()->status);
    }

    /**
     * @return array{0: Invoice, 1: Order, 2: User}
     */
    private function createPaidInvoiceWithOrder(
        string $type = 'new',
        int $orderStatus = OrderStatus::PAID,
        int $invoiceStatus = InvoiceStatus::PAID,
    ): array {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $user = User::query()->create([
            'email' => 'paid-dispatch-'.$suffix.'@example.com',
            'password' => 'secret123',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $product = Product::query()->create([
            'name' => '支付后队列开通测试商品 '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '48.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 1,
        ]);

        $order = Order::query()->create([
            'order_no' => 'ORDPAIDDISPATCH'.$suffix,
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'product_spec_snapshot' => (string) $product->name,
            'product_type_snapshot' => (string) $product->product_type,
            'type' => $type,
            'amount' => '48.00',
            'discount' => '0.00',
            'paid_amount' => $orderStatus === OrderStatus::PENDING ? '0.00' : '48.00',
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'config_snapshot' => [],
            'config_pricing_snapshot' => [],
            'status' => $orderStatus,
            'paid_at' => $orderStatus === OrderStatus::PENDING ? null : now(),
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INVPAIDDISPATCH'.$suffix,
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'product_id' => (int) $product->id,
            'type' => $type,
            'amount' => '48.00',
            'discount' => '0.00',
            'paid_amount' => $invoiceStatus === InvoiceStatus::UNPAID ? '0.00' : '48.00',
            'status' => $invoiceStatus,
            'paid_at' => $invoiceStatus === InvoiceStatus::UNPAID ? null : now(),
            'due_date' => now()->addDay(),
            'billing_cycle' => 'monthly',
            'quantity' => 1,
        ]);

        return [$invoice, $order, $user];
    }

    private function runAsWebRequest(callable $callback): void
    {
        $consoleFlag = new \ReflectionProperty(app(), 'isRunningInConsole');
        $consoleFlag->setAccessible(true);
        $originalConsoleFlag = $consoleFlag->getValue(app());
        $consoleFlag->setValue(app(), false);

        try {
            $callback();
        } finally {
            $consoleFlag->setValue(app(), $originalConsoleFlag);
        }
    }
}
