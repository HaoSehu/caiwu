<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Models\AccountTransaction;

/**
 * 资金流服务内余额台账（account_transactions）的统一写入口。
 * 支付/充值/退款/混合各流的台账行都必须经由本类落库，禁止在资金流服务内直写台账表，
 * 以保证 event_type/source_type 口径与余额快照全局一致
 * （认证费、返利与管理员调整等既有链路另有直写点，不在本次收敛范围）。
 */
class FinanceLedgerWriter
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function createBalanceLog(
        int $userId,
        string $eventType,
        float $changeAmount,
        string $balanceAfter,
        ?int $referenceId = null,
        string $remark = '',
        array $context = [],
    ): AccountTransaction {
        $sourceType = $this->resolveAccountTransactionSourceType($eventType);

        return AccountTransaction::query()->create([
            'user_id' => $userId,
            'account_type' => 'cash',
            'event_type' => $eventType,
            'change_amount' => number_format($changeAmount, 2, '.', ''),
            'balance_after' => $balanceAfter,
            'source_type' => $sourceType,
            'source_id' => $referenceId && $referenceId > 0 ? $referenceId : null,
            'origin_type' => $sourceType,
            'origin_id' => $referenceId && $referenceId > 0 ? $referenceId : null,
            'remark' => $remark,
            'operator' => trim((string) ($context['operator'] ?? '')) ?: null,
            'trace_id' => trim((string) ($context['trace_id'] ?? '')) ?: null,
        ]);
    }

    private function resolveAccountTransactionSourceType(string $eventType): ?string
    {
        return match (FinanceLedgerEventType::normalize(trim($eventType))) {
            FinanceLedgerEventType::RECHARGE => 'payment',
            FinanceLedgerEventType::MANUAL_RECHARGE,
            FinanceLedgerEventType::MANUAL_DEDUCTION,
            FinanceLedgerEventType::SYSTEM_ADJUSTMENT => 'manual_adjustment',
            FinanceLedgerEventType::INVOICE_PAYMENT,
            FinanceLedgerEventType::INVOICE_REFUND => 'invoice',
            FinanceLedgerEventType::REFERRAL_CREDIT_CASH => 'referral_withdrawal',
            default => null,
        };
    }
}
