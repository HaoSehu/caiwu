<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\InvoiceMergeItem;
use App\Services\SalesCommission\SalesCommissionService;
use App\Services\System\ActivityLogService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 账单合并（M1）：同用户多张待支付账单合并为一张总账单统一支付。
 *
 * 资金红线：本服务不改余额、不写 AccountTransaction 台账、不生成红字账单、
 * 不动 Payment 结构、不回退推广奖励——只做「待支付账单合并 + 总账单支付
 * 成功后子账单联动结清」的账面状态编排。
 *
 * 锁序约定（与 TradeLifecycleService「先 Invoice 后 Order」一致）：
 * - merge：仅按 id 升序锁子账单（总账单此时尚不存在，无父子交叉）；
 * - unmerge / settleMergedChildren：先锁总账单，再按 id 升序锁子账单。
 *   settleMergedChildren 在 markInvoicePaid 事务内被调用时，总账单行锁
 *   已由调用方持有，同事务重复加锁不阻塞，顺序仍为 父→子。
 *
 * 退款口径（2026-10-11 裁决）：总账单支付成功后的退款按子账单为对象，
 * 总账单不做退款入口；InvoiceRefundMarkService 等入口应拒绝 merge_role=1
 * 的账单（守卫锚点见 docs/_local/parallel-impl-2026-10-11/m1/INTEGRATION.md）。
 */
class InvoiceMergeService
{
    public function __construct(
        private readonly ActivityLogService $activityLogs,
    ) {}

    /**
     * 合并同用户多张待支付账单为一张总账单。
     *
     * 总账单口径：amount/discount/member_discount_amount = Σ子账单、
     * paid_amount=0、due_date=MIN(子账单)、type/currency 取首张子账单、
     * merge_role=1、status=待支付。子账单写 merged_into_id/merged_at，
     * 关联快照落 invoice_merge_items（amount_snapshot 取合并时刻金额）。
     *
     * @param  list<int>  $invoiceIds  子账单ID列表（至少 2 张，重复ID自动去重）
     * @return Invoice 总账单
     *
     * @throws BusinessException 空/单张、跨用户、跨币种、含非待支付、含已合并、含总账单
     */
    public function merge(int $userId, array $invoiceIds, int $operatorId): Invoice
    {
        $normalizedIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $invoiceIds),
            static fn (int $id): bool => $id > 0
        )));

        if (count($normalizedIds) < 2) {
            throw new BusinessException('至少选择两张待支付账单进行合并');
        }

        $master = DB::transaction(function () use ($userId, $normalizedIds): Invoice {
            /** @var list<Invoice> $children */
            $children = Invoice::query()
                ->whereIn('id', $normalizedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()->all();

            if (count($children) !== count($normalizedIds)) {
                throw new BusinessException('部分账单不存在，无法合并');
            }

            $this->assertMergeable($userId, $children);

            /** @var Invoice $first */
            $first = $children[0];
            $master = Invoice::query()->create([
                'invoice_no' => Invoice::generateInvoiceNo(),
                'user_id' => $userId,
                'type' => (string) $first->type,
                'currency' => (string) $first->currency,
                'amount' => Money::format(Money::add(...array_map(
                    static fn (Invoice $invoice): mixed => $invoice->amount,
                    $children
                ))),
                'discount' => Money::format(Money::add(...array_map(
                    static fn (Invoice $invoice): mixed => $invoice->discount,
                    $children
                ))),
                'member_discount_amount' => Money::format(Money::add(...array_map(
                    static fn (Invoice $invoice): mixed => $invoice->member_discount_amount,
                    $children
                ))),
                'paid_amount' => '0.00',
                'due_date' => $this->earliestDueDate($children),
                'merge_role' => 1,
                'remark' => sprintf('合并账单，含 %d 张子账单', count($children)),
                'status' => InvoiceStatus::UNPAID,
            ]);

            $mergedAt = now();
            foreach ($children as $child) {
                $child->forceFill([
                    'merged_into_id' => (int) $master->id,
                    'merged_at' => $mergedAt,
                ])->save();

                InvoiceMergeItem::query()->create([
                    'master_invoice_id' => (int) $master->id,
                    'child_invoice_id' => (int) $child->id,
                    'child_invoice_no' => (string) $child->invoice_no,
                    'amount_snapshot' => Money::format($child->amount),
                ]);
            }

            return $master;
        });

        $this->activityLogs->logSafely(
            'finance',
            'invoice.merged',
            sprintf(
                '账单合并：用户 #%d 的 %d 张待支付账单合并为总账单 %s',
                $userId,
                count($normalizedIds),
                (string) $master->invoice_no
            ),
            'invoice',
            (int) $master->id,
            [
                'user_id' => $userId,
                'master_invoice_no' => (string) $master->invoice_no,
                'child_invoice_ids' => implode(',', $normalizedIds),
                'operator_id' => $operatorId,
            ],
        );

        return $master;
    }

    /**
     * 拆分合并总账单：解除子账单关联、删关联快照、总账单作废（仅未支付可拆）。
     *
     * 子账单解除 merged_into_id 后回到独立待支付，可正常单独支付；
     * 子账单自身数据（金额/明细/券）合并时未被改写，无需还原。
     *
     * @throws BusinessException 非总账单、已支付/已取消
     */
    public function unmerge(Invoice $master, int $operatorId): Invoice
    {
        $result = DB::transaction(function () use ($master): Invoice {
            /** @var Invoice $lockedMaster */
            $lockedMaster = Invoice::query()->lockForUpdate()->findOrFail((int) $master->id);

            if ((int) $lockedMaster->merge_role !== 1) {
                throw new BusinessException('该账单不是合并总账单，无法拆分');
            }
            if ((int) $lockedMaster->status !== InvoiceStatus::UNPAID) {
                throw new BusinessException('仅待支付的总账单支持拆分，已支付请按子账单走退款流程');
            }

            /** @var list<Invoice> $children */
            $children = Invoice::query()
                ->where('merged_into_id', (int) $lockedMaster->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()->all();

            $originalRemark = (string) $lockedMaster->remark;
            foreach ($children as $child) {
                $child->forceFill([
                    'merged_into_id' => null,
                    'merged_at' => null,
                ])->save();
            }

            InvoiceMergeItem::query()->where('master_invoice_id', (int) $lockedMaster->id)->delete();

            $lockedMaster->forceFill([
                'status' => InvoiceStatus::CANCELLED,
                'remark' => sprintf('%s；已拆分作废，子账单恢复独立待支付', $originalRemark !== '' ? $originalRemark : '合并账单'),
            ])->save();

            return $lockedMaster;
        });

        $this->activityLogs->logSafely(
            'finance',
            'invoice.unmerged',
            sprintf('账单拆分：总账单 %s 已作废，子账单恢复独立待支付', (string) $result->invoice_no),
            'invoice',
            (int) $result->id,
            [
                'user_id' => (int) $result->user_id,
                'master_invoice_no' => (string) $result->invoice_no,
                'operator_id' => $operatorId,
            ],
        );

        return $result;
    }

    /**
     * 总账单支付成功后联动结清子账单（在支付事务内调用，任一失败整体回滚）。
     *
     * 幂等：已支付（status=1）子账单跳过不重写资金列，可安全重放；
     * 子账单 paid_amount=amount、paid_at=总账单 paid_at。
     * 子账单关联订单投影不在此处改写（总账单支付只触一次履约编排，
     * 子账单订单投影由对账/履约链路按既有口径补齐）。
     *
     * @return int 本次实际结清的子账单数
     */
    public function settleMergedChildren(Invoice $master): int
    {
        if ((int) $master->merge_role !== 1) {
            return 0;
        }

        return (int) DB::transaction(function () use ($master): int {
            /** @var Invoice $lockedMaster */
            $lockedMaster = Invoice::query()->lockForUpdate()->findOrFail((int) $master->id);

            /** @var list<Invoice> $children */
            $children = Invoice::query()
                ->where('merged_into_id', (int) $lockedMaster->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()->all();

            $settled = 0;
            $paidAt = $lockedMaster->paid_at ?? now();
            foreach ($children as $child) {
                if ((int) $child->status !== InvoiceStatus::UNPAID) {
                    continue;
                }

                $child->forceFill([
                    'status' => InvoiceStatus::PAID,
                    'paid_amount' => Money::format($child->amount),
                    'paid_at' => $paidAt,
                ])->save();

                // M5 内部销售提成：按裁决以子账单为计提粒度（总账单在编排层被跳过）；
                // 失败仅告警不回滚结清事务（accrueForInvoice 自带幂等与状态守卫，可重放补提）。
                try {
                    app(SalesCommissionService::class)
                        ->accrueForInvoice($child, 'merge_settle');
                } catch (\Throwable $exception) {
                    report($exception);
                }

                $settled++;
            }

            return $settled;
        });
    }

    /**
     * 合并前置校验（锁内执行）：同用户、全部待支付、均未被合并、同币种；
     * 合并总账单（merge_role=1）不可再作为子账单参与合并（防重复合并链）。
     *
     * @param  list<Invoice>  $children
     */
    private function assertMergeable(int $userId, array $children): void
    {
        $currency = null;
        foreach ($children as $child) {
            if ((int) $child->user_id !== $userId) {
                throw new BusinessException(sprintf('账单 %s 不属于该用户，无法合并', (string) $child->invoice_no));
            }
            if ((int) $child->status !== InvoiceStatus::UNPAID) {
                throw new BusinessException(sprintf('账单 %s 不是待支付状态，无法合并', (string) $child->invoice_no));
            }
            if ($child->merged_into_id !== null) {
                throw new BusinessException(sprintf('账单 %s 已被合并，无法重复合并', (string) $child->invoice_no));
            }
            if ((int) $child->merge_role === 1) {
                throw new BusinessException(sprintf('账单 %s 是合并总账单，不支持再次合并', (string) $child->invoice_no));
            }

            $childCurrency = (string) $child->currency;
            if ($currency === null) {
                $currency = $childCurrency;
            } elseif ($childCurrency !== $currency) {
                throw new BusinessException('所选账单币种不一致，无法合并');
            }
        }
    }

    /**
     * 子账单最早到期日（MIN 口径，总账单按最早到期日催收）。
     *
     * @param  list<Invoice>  $children
     */
    private function earliestDueDate(array $children): ?string
    {
        $dueDates = [];
        foreach ($children as $child) {
            // due_date 经模型 'date' cast 运行时为 Carbon；Carbon::parse 对 Carbon 原样克隆，口径一致。
            if ($child->due_date !== null) {
                $dueDates[] = Carbon::parse($child->due_date)->toDateString();
            }
        }

        return $dueDates === [] ? null : min($dueDates);
    }
}
