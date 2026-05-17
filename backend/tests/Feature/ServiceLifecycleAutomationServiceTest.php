<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ServiceStatus;
use App\Models\Service;
use App\Services\Automation\ServiceLifecycleAutomationService;
use App\Services\System\NotificationService;
use App\Services\System\SettingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceLifecycleAutomationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::connection('sqlite')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('nickname')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->json('provision_data')->nullable();
            $table->string('suspended_reason')->nullable();
            $table->unsignedTinyInteger('auto_renew')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropIfExists('services');
        Schema::connection('sqlite')->dropIfExists('users');

        parent::tearDown();
    }

    public function test_it_will_not_terminate_a_service_immediately_when_terminate_days_is_zero(): void
    {
        $service = Service::query()->create([
            'name' => '娴嬭瘯鏈嶅姟A',
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->subDays(2),
            'provision_data' => [],
        ]);

        $automationService = new ServiceLifecycleAutomationService(
            $this->fakeSettingService([
                'expire_suspend_enabled' => true,
                'expire_suspend_after_days' => 0,
                'expire_suspend_notify_enabled' => false,
                'expire_terminate_enabled' => true,
                'expire_terminate_after_days' => 0,
            ]),
            $this->createMock(NotificationService::class),
        );

        $summary = $automationService->handle();
        $service->refresh();

        $this->assertSame(1, $summary['suspended']);
        $this->assertSame(0, $summary['cancelled']);
        $this->assertSame(ServiceStatus::SUSPENDED, (int) $service->status);
        $this->assertSame('expired', $service->suspended_reason);
        $this->assertNotEmpty($service->provision_data['expired_suspended_at'] ?? null);
    }

    public function test_it_clears_suspend_reason_when_a_service_is_auto_cancelled(): void
    {
        $service = Service::query()->create([
            'name' => '娴嬭瘯鏈嶅姟B',
            'status' => ServiceStatus::SUSPENDED,
            'expires_at' => now()->subDays(5),
            'suspended_reason' => 'expired',
            'provision_data' => [
                'expired_suspended_at' => now()->subDays(3)->format('Y-m-d H:i:s'),
            ],
        ]);

        $automationService = new ServiceLifecycleAutomationService(
            $this->fakeSettingService([
                'expire_suspend_enabled' => true,
                'expire_suspend_after_days' => 0,
                'expire_suspend_notify_enabled' => false,
                'expire_terminate_enabled' => true,
                'expire_terminate_after_days' => 1,
            ]),
            $this->createMock(NotificationService::class),
        );

        $summary = $automationService->handle();
        $service->refresh();

        $this->assertSame(1, $summary['cancelled']);
        $this->assertSame(ServiceStatus::CANCELLED, (int) $service->status);
        $this->assertNull($service->suspended_reason);
        $this->assertArrayNotHasKey('expired_suspended_at', $service->provision_data);
    }

    private function fakeSettingService(array $config): SettingService
    {
        return new class($config) extends SettingService
        {
            public function __construct(private array $config) {}

            public function getAutomationConfig(): array
            {
                return array_merge(self::defaultAutomationConfig(), $this->config);
            }
        };
    }
}
