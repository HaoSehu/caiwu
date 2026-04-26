<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\Supplier;
use App\Services\AutoRenewService;
use App\Services\BillingAutomationService;
use App\Services\CheckoutService;
use App\Services\CouponCampaignService;
use App\Services\InvoiceCleanupAutomationService;
use App\Services\ProductCatalogService;
use App\Services\ReferralService;
use App\Services\ScheduleTaskService;
use App\Services\ScheduleTaskTriggerService;
use App\Services\ServiceLifecycleAutomationService;
use App\Services\ServiceStatusSyncService;
use App\Services\SettingService;
use App\Services\TicketAutomationService;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScheduleTaskExecutionCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqliteInMemoryDatabase();
        $this->createSettingsTable();
    }

    protected function tearDown(): void
    {
        foreach (['jobs', 'orders', 'services', 'suppliers', 'settings'] as $table) {
            Schema::connection('sqlite')->dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_schedule_task_overview_lists_registered_schedule_tasks(): void
    {
        $service = app(ScheduleTaskService::class);

        $tasks = collect($service->overview()['tasks'] ?? [])->values();

        $this->assertGreaterThanOrEqual(11, $tasks->count());

        foreach ([
            'refresh-hosting-panel-auth',
            'service-auto-renew',
            'referral-release-rewards',
            'service-lifecycle-maintenance',
            'service-status-sync',
            'billing-maintenance',
            'product-upstream-config-sync',
            'coupon-campaign-dispatch',
            'ticket-auto-close',
            'order-cleanup',
            'queue-backlog-drain',
        ] as $expectedKey) {
            $this->assertTrue(
                $tasks->contains(fn (array $task): bool => ($task['key'] ?? null) === $expectedKey),
                'Missing schedule task key: ' . $expectedKey
            );
        }

        $this->assertTrue(
            $tasks->contains(fn (array $task): bool => ($task['key'] ?? null) === 'VNC Relay 守护' && ($task['manual_triggerable'] ?? true) === false),
            'Missing VNC Relay 守护 schedule entry.'
        );

        $cleanupTask = $tasks->firstWhere('key', 'order-cleanup');
        $this->assertIsArray($cleanupTask);
        $this->assertSame('账单与充值清理', $cleanupTask['title'] ?? null);
        $this->assertTrue((bool) ($cleanupTask['manual_triggerable'] ?? false));
    }

    public function test_manual_dispatch_executes_each_triggerable_schedule_task(): void
    {
        $this->createSuppliersTable();

        Supplier::query()->create([
            'name' => 'Mock Supplier',
            'interface_type' => 'hosting_panel_api',
            'status' => 1,
        ]);

        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->expects($this->once())
            ->method('refreshJwt')
            ->with($this->callback(fn ($supplier): bool => $supplier instanceof Supplier && (int) $supplier->status === 1));
        app()->instance(HostingPanelApiTransport::class, $transport);

        $autoRenewService = $this->createMock(AutoRenewService::class);
        $autoRenewService->expects($this->once())
            ->method('handle')
            ->with(10)
            ->willReturn(['matched' => 0, 'paid' => 0, 'pending' => 0, 'failed' => 0, 'skipped' => 0]);
        app()->instance(AutoRenewService::class, $autoRenewService);

        $referralService = $this->createMock(ReferralService::class);
        $referralService->expects($this->once())
            ->method('releaseMaturedRewards')
            ->with()
            ->willReturn(0);
        app()->instance(ReferralService::class, $referralService);

        $billingAutomationService = $this->createMock(BillingAutomationService::class);
        $billingAutomationService->expects($this->once())
            ->method('handle')
            ->with()
            ->willReturn(['renew_notice_sent' => 0, 'renew_orders_created' => 0]);
        app()->instance(BillingAutomationService::class, $billingAutomationService);

        $couponCampaignService = $this->createMock(CouponCampaignService::class);
        $couponCampaignService->expects($this->once())
            ->method('dispatchDueCampaigns')
            ->with()
            ->willReturn(['matched' => 0, 'triggered' => 0, 'skipped' => 0, 'failed' => 0, 'coupon_ids' => []]);
        app()->instance(CouponCampaignService::class, $couponCampaignService);

        $productCatalogService = $this->createMock(ProductCatalogService::class);
        $productCatalogService->expects($this->once())
            ->method('syncUpstreamProductConfigOptions')
            ->with()
            ->willReturn(['matched' => 0, 'synced' => 0, 'failed' => 0]);
        app()->instance(ProductCatalogService::class, $productCatalogService);

        $serviceLifecycleAutomationService = $this->createMock(ServiceLifecycleAutomationService::class);
        $serviceLifecycleAutomationService->expects($this->once())
            ->method('handle')
            ->with()
            ->willReturn(['suspended' => 0, 'cancelled' => 0]);
        app()->instance(ServiceLifecycleAutomationService::class, $serviceLifecycleAutomationService);

        $serviceStatusSyncService = $this->createMock(ServiceStatusSyncService::class);
        $serviceStatusSyncService->expects($this->once())
            ->method('handle')
            ->with()
            ->willReturn(['scanned' => 0, 'synced' => 0, 'failed' => 0, 'skipped' => 0]);
        app()->instance(ServiceStatusSyncService::class, $serviceStatusSyncService);

        $ticketAutomationService = $this->createMock(TicketAutomationService::class);
        $ticketAutomationService->expects($this->once())
            ->method('handle')
            ->with()
            ->willReturn(['closed' => 0]);
        app()->instance(TicketAutomationService::class, $ticketAutomationService);

        $settingService = $this->createMock(SettingService::class);
        $settingService->expects($this->once())
            ->method('getAutomationConfig')
            ->willReturn([
                'pending_order_cleanup_enabled' => false,
                'pending_order_cleanup_after_hours' => 1,
                'pending_recharge_cleanup_enabled' => false,
                'pending_recharge_cleanup_after_days' => 0,
            ]);
        $invoiceCleanupAutomationService = new InvoiceCleanupAutomationService(
            $settingService,
            $this->createMock(CheckoutService::class),
        );
        app()->instance(InvoiceCleanupAutomationService::class, $invoiceCleanupAutomationService);

        Artisan::shouldReceive('call')
            ->once()
            ->with('orders:sync-processing-status')
            ->andReturn(0);
        Artisan::shouldReceive('call')
            ->once()
            ->withArgs(function (string $command, array $parameters = []): bool {
                return $command === 'queue:work'
                    && ($parameters['--queue'] ?? null) === 'referral,provision,default'
                    && ($parameters['--sleep'] ?? null) === 1
                    && ($parameters['--tries'] ?? null) === 3
                    && ($parameters['--stop-when-empty'] ?? null) === true
                    && ($parameters['--max-time'] ?? null) === 50;
            })
            ->andReturn(0);

        $service = app(ScheduleTaskTriggerService::class);

        foreach ([
            'refresh-hosting-panel-auth',
            'service-auto-renew',
            'referral-release-rewards',
            'billing-maintenance',
            'coupon-campaign-dispatch',
            'product-upstream-config-sync',
            'service-lifecycle-maintenance',
            'service-status-sync',
            'ticket-auto-close',
            'order-cleanup',
            'sync-processing-order-status',
            'queue-backlog-drain',
        ] as $taskKey) {
            $result = $service->dispatch($taskKey, 1);

            $this->assertSame($taskKey, $result['task'] ?? null);
            $this->assertSame('sync', $result['execution_mode'] ?? null);
        }
    }

    public function test_sync_processing_order_status_command_is_a_compatibility_no_op(): void
    {
        $this->createServicesTable();
        $this->createOrdersTable();

        $service = Service::query()->create([
            'status' => ServiceStatus::ACTIVE,
        ]);

        $order = Order::query()->create([
            'order_no' => 'ORD-SCHEDULE-001',
            'service_id' => $service->id,
            'status' => OrderStatus::PROCESSING,
        ]);

        $this->artisan('orders:sync-processing-status')->assertExitCode(0);

        $this->assertSame(OrderStatus::PROCESSING, (int) $order->fresh()->status);
    }

    public function test_queue_backlog_worker_command_exits_cleanly_when_queue_is_empty(): void
    {
        $this->createJobsTable();

        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.connection', 'sqlite');
        config()->set('queue.connections.database.table', 'jobs');
        config()->set('queue.connections.database.queue', 'default');

        $this->artisan('queue:work', [
            '--queue' => 'referral,provision,default',
            '--sleep' => 1,
            '--tries' => 3,
            '--stop-when-empty' => true,
            '--max-time' => 50,
        ])->assertExitCode(0);

        $this->assertSame(0, DB::connection('sqlite')->table('jobs')->count());
    }

    private function useSqliteInMemoryDatabase(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('sqlite');
        app('db')->setDefaultConnection('sqlite');
        DB::connection('sqlite')->getPdo();
    }

    private function createSettingsTable(): void
    {
        Schema::connection('sqlite')->create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group_key', 100);
            $table->string('item_key', 100);
            $table->text('item_value')->nullable();
        });
    }

    private function createSuppliersTable(): void
    {
        Schema::connection('sqlite')->create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('interface_type')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    private function createServicesTable(): void
    {
        Schema::connection('sqlite')->create('services', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    private function createOrdersTable(): void
    {
        Schema::connection('sqlite')->create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_no')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    private function createJobsTable(): void
    {
        Schema::connection('sqlite')->create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }
}
