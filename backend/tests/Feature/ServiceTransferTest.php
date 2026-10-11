<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceTransfer;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Service\ServiceTransferService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * M6 服务转移状态机回归：全流转 / 互斥 / 频控 / 未结账单拦截 /
 * 实名开关两种取值 / 过期批量关闭 / 站内信生成。
 *
 * 前置：service_transfers 表迁移（2026_10_11_170001）已应用。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ServiceTransferTest extends TestCase
{
    use DatabaseTransactions;

    private ServiceTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ServiceTransferService::class);
    }

    protected function tearDown(): void
    {
        // DatabaseTransactions 回滚不会清理 Laravel 缓存：Setting::getValue 的
        // Cache::remember（array 驱动按进程存活）会把本用例写入的设置泄漏到后续用例。
        Setting::forgetCachedGroup('service_transfer');
        Setting::forgetCachedGroup('verification');

        parent::tearDown();
    }

    public function test_initiate_requires_active_service(): void
    {
        [$from] = $this->makeUsers();
        $to = User::factory()->verified()->create();
        $service = $this->makeService($from, ServiceStatus::SUSPENDED);

        $this->expectException(BusinessException::class);
        $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);
    }

    public function test_initiate_rejects_same_user(): void
    {
        $from = User::factory()->create();
        $service = $this->makeService($from, ServiceStatus::ACTIVE);

        $this->expectException(BusinessException::class);
        $this->service->initiate($service, (int) $from->id, ServiceTransfer::INITIATED_BY_FROM);
    }

    public function test_initiate_rejects_banned_receiver(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create(['status' => 0]);
        $service = $this->makeService($from, ServiceStatus::ACTIVE);

        $this->expectException(BusinessException::class);
        $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);
    }

    public function test_initiate_blocks_unpaid_invoices(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->verified()->create();
        $service = $this->makeService($from, ServiceStatus::ACTIVE);
        $this->makeUnpaidInvoice($from, $service);

        try {
            $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);
            $this->fail('未结账单应拦截发起');
        } catch (BusinessException $exception) {
            $this->assertSame(ServiceTransferService::ERROR_UNPAID_INVOICES, $exception->getData()['error_code'] ?? '');
        }

        $this->assertSame(0, ServiceTransfer::query()->where('service_id', (int) $service->id)->count());
    }

    public function test_initiate_rejects_when_pending_transfer_exists(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->verified()->create();
        $service = $this->makeService($from, ServiceStatus::ACTIVE);

        $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);

        $this->expectException(BusinessException::class);
        $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_ADMIN);
    }

    public function test_rate_limit_three_transfers_per_seven_days(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->verified()->create();

        for ($i = 0; $i < 3; $i++) {
            $service = $this->makeService($from, ServiceStatus::ACTIVE);
            $transfer = $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);
            $transfer->forceFill(['status' => ServiceTransfer::STATUS_CANCELLED])->save();
        }

        $fourth = $this->makeService($from, ServiceStatus::ACTIVE);

        $this->expectException(BusinessException::class);
        $this->service->initiate($fourth, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);
    }

    public function test_verification_scope_enforced_when_enabled(): void
    {
        Setting::setValue('verification', 'restrictions', json_encode([
            'enabled' => true,
            'grace_days' => 0,
            'scope' => ['service_transfer_receive' => true],
        ]));

        $from = User::factory()->create();
        $to = User::factory()->create(); // 未实名
        $service = $this->makeService($from, ServiceStatus::ACTIVE);

        try {
            $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);
            $this->fail('未实名接收方应被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame('verification_required', $exception->getData()['error_code'] ?? '');
        }

        // 实名后放行。
        $to->forceFill(['is_verified' => 1, 'verification_status' => 2])->save();
        $transfer = $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);
        $this->assertSame(ServiceTransfer::STATUS_PENDING, (int) $transfer->status);
    }

    public function test_verification_scope_relaxed_when_disabled(): void
    {
        Setting::setValue('verification', 'restrictions', json_encode([
            'enabled' => false,
            'scope' => ['service_transfer_receive' => true],
        ]));

        $from = User::factory()->create();
        $to = User::factory()->create(); // 未实名但开关关闭
        $service = $this->makeService($from, ServiceStatus::ACTIVE);

        $transfer = $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);

        $this->assertSame(ServiceTransfer::STATUS_PENDING, (int) $transfer->status);
    }

    public function test_full_flow_from_initiation_to_completion(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->verified()->create();
        $service = $this->makeService($from, ServiceStatus::ACTIVE);

        $transfer = $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);

        // 发起即视为转出方确认，等待接收方。
        $this->assertSame(ServiceTransfer::STATUS_PENDING, (int) $transfer->status);
        $this->assertNotNull($transfer->from_confirmed_at);
        $this->assertNull($transfer->to_confirmed_at);
        $this->assertTrue($transfer->expires_at->between(now()->addHours(71), now()->addHours(73)), '确认有效期应为 72 小时');

        $completed = $this->service->confirm($transfer, (int) $to->id);

        $this->assertSame(ServiceTransfer::STATUS_COMPLETED, (int) $completed->status);
        $this->assertSame((int) $to->id, (int) $service->refresh()->user_id);

        // 站内信：发起 + 完成两类事件均已生成。
        foreach (['service_transfer_requested', 'service_transfer_completed'] as $type) {
            $this->assertTrue(
                UserNotification::query()->where('type', $type)->where('user_id', (int) $from->id)->exists(),
                "缺少站内信 {$type}"
            );
        }
    }

    public function test_receiver_reject_closes_transfer(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->verified()->create();
        $service = $this->makeService($from, ServiceStatus::ACTIVE);
        $transfer = $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);

        $rejected = $this->service->reject($transfer, (int) $to->id, '不需要该服务');

        $this->assertSame(ServiceTransfer::STATUS_REJECTED, (int) $rejected->status);
        $this->assertSame('不需要该服务', (string) $rejected->reject_reason);
        $this->assertSame((int) $from->id, (int) $service->refresh()->user_id);
    }

    public function test_initiator_cancel_closes_transfer(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->verified()->create();
        $service = $this->makeService($from, ServiceStatus::ACTIVE);
        $transfer = $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);

        $cancelled = $this->service->cancel($transfer, (int) $from->id);

        $this->assertSame(ServiceTransfer::STATUS_CANCELLED, (int) $cancelled->status);
    }

    public function test_unrelated_user_cannot_confirm_or_reject(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->verified()->create();
        $stranger = User::factory()->create();
        $service = $this->makeService($from, ServiceStatus::ACTIVE);
        $transfer = $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);

        try {
            $this->service->confirm($transfer, (int) $stranger->id);
            $this->fail('无关用户不应能确认');
        } catch (BusinessException) {
        }

        try {
            $this->service->reject($transfer, (int) $stranger->id);
            $this->fail('无关用户不应能拒绝');
        } catch (BusinessException) {
        }

        $this->assertSame(ServiceTransfer::STATUS_PENDING, (int) $transfer->refresh()->status);
    }

    public function test_expire_due_closes_stale_transfers(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->verified()->create();
        $service = $this->makeService($from, ServiceStatus::ACTIVE);

        $stale = $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);
        $stale->forceFill(['expires_at' => Carbon::parse('2026-01-01 00:00:00')])->save();

        $fresh = $this->makeService($from, ServiceStatus::ACTIVE);
        $active = $this->service->initiate($fresh, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);

        $expiredCount = $this->service->expireDue(now());

        $this->assertGreaterThanOrEqual(1, $expiredCount);
        $this->assertSame(ServiceTransfer::STATUS_EXPIRED, (int) $stale->refresh()->status);
        $this->assertSame(ServiceTransfer::STATUS_PENDING, (int) $active->refresh()->status);
    }

    // -------- fixtures --------

    /**
     * @return array{0: User, 1: User}
     */
    private function makeUsers(): array
    {
        return [User::factory()->create(), User::factory()->verified()->create()];
    }

    private function makeService(User $owner, int $status): Service
    {
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);

        return Service::query()->create([
            'user_id' => (int) $owner->id,
            'product_id' => (int) $product->id,
            'name' => 'transfer-test-'.uniqid(),
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => $status,
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function makeUnpaidInvoice(User $owner, Service $service): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $owner->id,
            'service_id' => (int) $service->id,
            'type' => 'renew',
            'amount' => 35.00,
            'status' => 0,
            'billing_cycle' => 'monthly',
        ]);
    }
}
