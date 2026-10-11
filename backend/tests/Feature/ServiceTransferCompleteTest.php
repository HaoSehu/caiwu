<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOperationLog;
use App\Models\ServiceTransfer;
use App\Models\Setting;
use App\Models\User;
use App\Services\Service\ServiceTransferService;
use App\Support\SchemaMetadataCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M6 服务转移生效事务回归：user_id 变更 + 留痕 + 幂等 + 人工复核开关停留 1。
 *
 * 前置：service_transfers 表迁移（2026_10_11_170001）已应用。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ServiceTransferCompleteTest extends TestCase
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
        // Cache::remember（array 驱动按进程存活）会把本用例写入的设置泄漏到后续用例
        // （如 manual_review=1 会令后续 confirm 停在状态 1 甚至抛 BusinessException）。
        Setting::forgetCachedGroup('service_transfer');
        Setting::forgetCachedGroup('verification');

        parent::tearDown();
    }

    public function test_execute_changes_owner_and_writes_operation_log(): void
    {
        $fixture = $this->pendingTransferFixture();
        /** @var Service $service */
        $service = $fixture['service'];
        /** @var User $to */
        $to = $fixture['to'];
        /** @var ServiceTransfer $transfer */
        $transfer = $fixture['transfer'];

        // 双方确认态由 confirm 流程负责写入（发起方发起时已确认）；
        // 本用例聚焦生效事务本身，先补齐接收方确认时间再 execute。
        $transfer->forceFill(['to_confirmed_at' => now()])->save();

        $completed = $this->service->execute($transfer->refresh());

        $this->assertSame(ServiceTransfer::STATUS_COMPLETED, (int) $completed->status);
        $this->assertSame((int) $to->id, (int) $service->refresh()->user_id);
        $this->assertNotNull($completed->to_confirmed_at);
        $this->assertNotNull($completed->risk_flags);

        // 留痕：service_operation_logs 写入一条 service_transfer 记录（表迁移：2026_10_11_190001）。
        if (SchemaMetadataCache::hasTable('service_operation_logs')) {
            $log = ServiceOperationLog::query()
                ->where('service_instance_id', (int) $service->id)
                ->where('operation_type', 'service_transfer')
                ->first();
            $this->assertNotNull($log, '服务转移应在 service_operation_logs 留痕');
            $this->assertSame('success', (string) $log->result_status);
            $this->assertSame((int) $transfer->id, (int) ($log->request_payload_json['transfer_id'] ?? 0));
        } else {
            $this->addWarning('service_operation_logs 表不存在（缺迁移），留痕断言已跳过');
        }
    }

    public function test_execute_is_idempotent(): void
    {
        $fixture = $this->pendingTransferFixture();
        /** @var Service $service */
        $service = $fixture['service'];
        /** @var ServiceTransfer $transfer */
        $transfer = $fixture['transfer'];

        $first = $this->service->execute($transfer->refresh());
        $second = $this->service->execute($first);

        $this->assertSame(ServiceTransfer::STATUS_COMPLETED, (int) $second->status);

        if (SchemaMetadataCache::hasTable('service_operation_logs')) {
            $logCount = ServiceOperationLog::query()
                ->where('service_instance_id', (int) $service->id)
                ->where('operation_type', 'service_transfer')
                ->count();
            $this->assertSame(1, $logCount, '重复 execute 不得重复留痕');
        }
    }

    public function test_manual_review_holds_transfer_at_status_one(): void
    {
        Setting::setValue('service_transfer', 'manual_review', '1');

        $fixture = $this->pendingTransferFixture();
        /** @var Service $service */
        $service = $fixture['service'];
        /** @var User $to */
        $to = $fixture['to'];
        /** @var ServiceTransfer $transfer */
        $transfer = $fixture['transfer'];

        $confirmed = $this->service->confirm($transfer->refresh(), (int) $to->id);

        // 强制复核开启：双方确认后停在 status=1，不自动生效。
        $this->assertSame(ServiceTransfer::STATUS_CONFIRMED, (int) $confirmed->status);
        $this->assertNotSame((int) $to->id, (int) $service->refresh()->user_id);

        // 管理员复核通过后生效。
        $completed = $this->service->execute($confirmed);
        $this->assertSame(ServiceTransfer::STATUS_COMPLETED, (int) $completed->status);
        $this->assertSame((int) $to->id, (int) $service->refresh()->user_id);
    }

    public function test_execute_rechecks_unpaid_invoice_and_rolls_back(): void
    {
        $fixture = $this->pendingTransferFixture();
        /** @var Service $service */
        $service = $fixture['service'];
        /** @var User $from */
        $from = $fixture['from'];
        /** @var ServiceTransfer $transfer */
        $transfer = $fixture['transfer'];

        // 生效前竞态插入未结账单：生效事务应整体回滚。
        Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $from->id,
            'service_id' => (int) $service->id,
            'type' => 'renew',
            'amount' => 35.00,
            'status' => 0,
            'billing_cycle' => 'monthly',
        ]);

        try {
            $this->service->execute($transfer->refresh());
            $this->fail('存在未结账单时生效事务应失败');
        } catch (BusinessException) {
        }

        $this->assertSame((int) $from->id, (int) $service->refresh()->user_id, '归属不得变更');
        $this->assertNotSame(ServiceTransfer::STATUS_COMPLETED, (int) $transfer->refresh()->status);
    }

    public function test_confirm_twice_without_manual_review_executes_once(): void
    {
        $fixture = $this->pendingTransferFixture();
        /** @var Service $service */
        $service = $fixture['service'];
        /** @var User $to */
        $to = $fixture['to'];
        /** @var ServiceTransfer $transfer */
        $transfer = $fixture['transfer'];

        $first = $this->service->confirm($transfer->refresh(), (int) $to->id);
        $second = $this->service->confirm($first, (int) $to->id);

        $this->assertSame(ServiceTransfer::STATUS_COMPLETED, (int) $second->status);

        if (SchemaMetadataCache::hasTable('service_operation_logs')) {
            $logCount = ServiceOperationLog::query()
                ->where('service_instance_id', (int) $service->id)
                ->where('operation_type', 'service_transfer')
                ->count();
            $this->assertSame(1, $logCount);
        }
    }

    // -------- fixtures --------

    /**
     * @return array{service: Service, from: User, to: User, transfer: ServiceTransfer}
     */
    private function pendingTransferFixture(): array
    {
        $from = User::factory()->create();
        $to = User::factory()->verified()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $service = Service::query()->create([
            'user_id' => (int) $from->id,
            'product_id' => (int) $product->id,
            'name' => 'transfer-complete-'.uniqid(),
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $transfer = $this->service->initiate($service, (int) $to->id, ServiceTransfer::INITIATED_BY_FROM);

        return ['service' => $service, 'from' => $from, 'to' => $to, 'transfer' => $transfer];
    }
}
