<?php

declare(strict_types=1);

namespace App\Services\Admin\V2;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 管理端补录：钱在系统外收付，系统仅记账。
 * 约束：不调用 adjustBalance/recordRecharge，不动用户余额、无台账、无充值记录；
 * 手动账单 = Invoice(manual) + Payment(gateway=manual) 两步入账，同一事务。
 */
class AdminManualEntryV2Service
{
    /**
     * 空交易号补录没有业务唯一键可查重：短窗内的同内容重复提交按双击/重试拦截。
     * 窗口取 120s 已覆盖双击与网络超时重试；更久的重复提交由管理员人工把关。
     */
    private const EMPTY_TRADE_NO_DUPLICATE_WINDOW_SECONDS = 120;

    public function __construct(
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * 补录账单（无订单关联的纯记账）。
     * 同用户同交易号经命名锁串行化（与 rechargeByGateway 同构），事务内复查 trade_no 消除并发双写窗口；
     * 空交易号时以补录内容指纹做命名锁 + 短窗查重，对齐防双击/重试重复入账。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function createManualInvoice(User $user, array $payload, array $context): array
    {
        $amount = round((float) $payload['amount'], 2);
        throw_if($amount <= 0, new BusinessException('补录金额必须大于 0'));

        $remark = trim((string) ($payload['remark'] ?? ''));
        $tradeNo = trim((string) ($payload['trade_no'] ?? ''));
        $paymentGateway = trim((string) ($payload['payment_gateway'] ?? '')) ?: 'manual';

        $entry = fn (): array => DB::transaction(function () use ($user, $payload, $amount, $remark, $tradeNo, $paymentGateway, $context): array {
            $this->assertTradeNoAvailable((int) $user->id, $tradeNo);
            $this->assertNotDuplicatedManualEntry((int) $user->id, $amount, $remark, $tradeNo);

            $created = $this->invoices->createDirect([
                'user_id' => (int) $user->id,
                'type' => InvoiceType::MANUAL,
                'amount' => $amount,
            ]);

            $created->forceFill(['remark' => $remark !== '' ? $remark : null])->save();

            $invoice = $this->invoices->markPaidManually($created, [
                'amount' => $amount,
                'paid_at' => $payload['paid_at'] ?? null,
                'payment_gateway' => $paymentGateway,
                'trade_no' => $tradeNo,
                'remark' => $remark,
                'sync_business_flow' => false,
            ], $context);

            return [
                'id' => (int) $invoice->id,
                'status' => 'completed',
                'message' => '补录账单成功',
                'detail' => [
                    'type' => 'manual_invoice_entry',
                    'invoice' => [
                        'id' => (int) $invoice->id,
                        'invoice_no' => (string) $invoice->invoice_no,
                        'amount' => number_format($amount, 2, '.', ''),
                        'status' => (int) $invoice->status,
                    ],
                ],
            ];
        });

        if ($tradeNo === '') {
            // 空交易号无业务唯一键：以「用户+金额+支付方式+备注」内容指纹做命名锁，
            // 双击/网络重试在锁内串行化，第二次进入事务后由短窗查重拦截。
            $fingerprint = md5((string) json_encode([
                'user_id' => (int) $user->id,
                'amount' => $amount,
                'payment_gateway' => $paymentGateway,
                'remark' => $remark,
            ], JSON_UNESCAPED_UNICODE));

            try {
                return Cache::lock('lock:admin:manual-trade:'.((int) $user->id).':'.$fingerprint, 10)->block(3, $entry);
            } catch (LockTimeoutException) {
                throw new BusinessException('相同内容的补录正在处理中，请稍候重试');
            }
        }

        try {
            return Cache::lock('lock:admin:manual-trade:'.((int) $user->id).':'.md5($tradeNo), 10)->block(3, $entry);
        } catch (LockTimeoutException) {
            throw new BusinessException('相同交易号的补录正在处理中，请稍候重试');
        }
    }

    /**
     * 补录订单（仅续费/附加配置）：订单+账单双写并直接入账；固定不触发开通/续期业务流转。
     * 同实例经命名锁串行化，事务内复查待支付订单与 trade_no，消除并发补录双记窗口。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function createManualOrder(User $user, array $payload, array $context): array
    {
        $type = (string) ($payload['type'] ?? '');
        throw_if(
            ! in_array($type, [OrderType::RENEW, OrderType::UPGRADE], true),
            new BusinessException('补录订单仅支持续费/附加配置')
        );

        $amount = round((float) $payload['amount'], 2);
        throw_if($amount <= 0, new BusinessException('补录金额必须大于 0'));

        // 归属校验：只允许给当前用户名下实例补录，杜绝横向越权。
        $service = Service::query()
            ->where('user_id', (int) $user->id)
            ->whereKey((int) ($payload['service_id'] ?? 0))
            ->first();
        throw_if(! $service instanceof Service, new BusinessException('服务实例不存在或不属于该用户'));

        $remark = trim((string) ($payload['remark'] ?? ''));
        $tradeNo = trim((string) ($payload['trade_no'] ?? ''));

        $entry = fn (): array => DB::transaction(function () use ($user, $payload, $service, $type, $amount, $remark, $tradeNo, $context): array {
            $pendingExists = Order::query()
                ->where('service_id', (int) $service->id)
                ->where('status', OrderStatus::PENDING)
                ->exists();
            throw_if($pendingExists, new BusinessException('该实例存在待支付订单，请先处理后再补录'));

            $this->assertTradeNoAvailable((int) $user->id, $tradeNo);
            $this->assertNotDuplicatedManualOrder($service, $type, $amount, $remark, $tradeNo);

            $created = Order::create([
                'order_no' => Order::generateOrderNo(),
                'user_id' => (int) $user->id,
                'product_id' => $service->product_id,
                'service_id' => (int) $service->id,
                'type' => $type,
                'amount' => $amount,
                'billing_cycle' => trim((string) ($payload['billing_cycle'] ?? '')) !== ''
                    ? (string) $payload['billing_cycle']
                    : (string) ($service->billing_cycle ?? ''),
                'quantity' => 1,
                'status' => OrderStatus::PENDING,
                'remark' => $remark,
                'operator' => (string) ($context['operator_name'] ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? ''),
            ]);

            $invoice = $this->invoices->createFromOrder($created);

            $this->invoices->markPaidManually($invoice, [
                'amount' => $amount,
                'paid_at' => $payload['paid_at'] ?? null,
                'payment_gateway' => trim((string) ($payload['payment_gateway'] ?? '')) ?: 'manual',
                'trade_no' => $tradeNo,
                'remark' => $remark,
                'sync_business_flow' => false,
            ], $context);

            $order = $created->fresh(['invoice']) ?? $created;

            return [
                'id' => (int) $order->id,
                'status' => 'completed',
                'message' => '补录订单成功',
                'detail' => [
                    'type' => 'manual_order_entry',
                    'order' => [
                        'id' => (int) $order->id,
                        'order_no' => (string) $order->order_no,
                        'status' => (int) $order->status,
                    ],
                    'invoice' => $order->invoice instanceof Invoice ? [
                        'id' => (int) $order->invoice->id,
                        'invoice_no' => (string) $order->invoice->invoice_no,
                    ] : null,
                ],
            ];
        });

        try {
            return Cache::lock('lock:admin:manual-order:'.((int) $service->id), 10)->block(3, $entry);
        } catch (LockTimeoutException) {
            throw new BusinessException('该实例的补录正在处理中，请稍候重试');
        }
    }

    private function assertTradeNoAvailable(int $userId, string $tradeNo): void
    {
        if ($tradeNo === '') {
            return;
        }

        $exists = Payment::query()
            ->where('user_id', $userId)
            ->where('trade_no', $tradeNo)
            ->exists();
        throw_if($exists, new BusinessException('该交易号已存在于该用户的其他支付记录中，请核实后重新填写'));
    }

    /**
     * 空交易号的账单补录按「用户+金额+备注」短窗查重，拦截双击/重试重复入账；
     * 有交易号时由 assertTradeNoAvailable 按业务唯一键把关，此处直接放行。
     */
    private function assertNotDuplicatedManualEntry(int $userId, float $amount, string $remark, string $tradeNo): void
    {
        if ($tradeNo !== '') {
            return;
        }

        $duplicateQuery = Invoice::query()
            ->where('user_id', $userId)
            ->where('type', InvoiceType::MANUAL)
            ->where('status', InvoiceStatus::PAID)
            ->where('amount', $amount);

        if ($remark !== '') {
            $duplicateQuery->where('remark', $remark);
        } else {
            $duplicateQuery->whereNull('remark');
        }

        $hasDuplicate = $duplicateQuery
            ->where('created_at', '>=', now()->subSeconds(self::EMPTY_TRADE_NO_DUPLICATE_WINDOW_SECONDS))
            ->exists();

        throw_if($hasDuplicate, new BusinessException('相同内容的补录刚刚已入账，请勿重复提交；如确需再次补录请修改备注或稍后再试'));
    }

    /**
     * 空交易号的订单补录按「实例+类型+金额+备注」短窗查重：首次补录后订单已转
     * 已支付，pendingExists 不再拦截，重复提交会重复入账，这里补齐同窗防护。
     */
    private function assertNotDuplicatedManualOrder(Service $service, string $type, float $amount, string $remark, string $tradeNo): void
    {
        if ($tradeNo !== '') {
            return;
        }

        $duplicateQuery = Order::query()
            ->where('service_id', (int) $service->id)
            ->where('type', $type)
            ->where('status', OrderStatus::PAID)
            ->where('amount', $amount);

        if ($remark !== '') {
            $duplicateQuery->where('remark', $remark);
        } else {
            $duplicateQuery->whereNull('remark');
        }

        $hasDuplicate = $duplicateQuery
            ->where('created_at', '>=', now()->subSeconds(self::EMPTY_TRADE_NO_DUPLICATE_WINDOW_SECONDS))
            ->exists();

        throw_if($hasDuplicate, new BusinessException('该实例相同内容的补录刚刚已完成，请勿重复提交；如确需再次补录请修改备注或稍后再试'));
    }
}
