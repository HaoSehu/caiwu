<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\PaymentGatewayCode;
use Illuminate\Support\Facades\DB;

class PaymentBoundaryAuditService
{
    /**
     * @return array<string,mixed>
     */
    public function inspect(int $baselineNonThirdParty = 66, int $sampleLimit = 20): array
    {
        $thirdPartyGateways = PaymentGatewayCode::thirdPartyGateways();
        $gatewayCounts = DB::table('payments')
            ->select('gateway')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('gateway')
            ->orderBy('gateway')
            ->pluck('total', 'gateway')
            ->map(fn ($value) => (int) $value)
            ->all();

        $thirdPartyCount = (int) DB::table('payments')
            ->whereIn('gateway', $thirdPartyGateways)
            ->count();
        $nonThirdPartyCount = (int) DB::table('payments')
            ->whereNotIn('gateway', $thirdPartyGateways)
            ->count();

        $samples = DB::table('payments')
            ->whereNotIn('gateway', $thirdPartyGateways)
            ->orderByDesc('id')
            ->limit($sampleLimit)
            ->get([
                'id',
                'payment_no',
                'user_id',
                'order_id',
                'invoice_id',
                'gateway',
                'amount',
                'status',
                'paid_at',
                'created_at',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'payment_no' => (string) $row->payment_no,
                'user_id' => (int) $row->user_id,
                'order_id' => $row->order_id !== null ? (int) $row->order_id : null,
                'invoice_id' => $row->invoice_id !== null ? (int) $row->invoice_id : null,
                'gateway' => (string) $row->gateway,
                'amount' => (string) $row->amount,
                'status' => (int) $row->status,
                'paid_at' => $row->paid_at,
                'created_at' => $row->created_at,
            ])
            ->values()
            ->all();

        return [
            'dry_run' => true,
            'checked_at' => now()->toDateTimeString(),
            'third_party_gateways' => $thirdPartyGateways,
            'gateway_counts' => $gatewayCounts,
            'summary' => [
                'third_party_payment_count' => $thirdPartyCount,
                'historical_non_third_party_payment_count' => $nonThirdPartyCount,
                'historical_non_third_party_baseline' => $baselineNonThirdParty,
                'historical_non_third_party_exceeded_baseline' => $nonThirdPartyCount > $baselineNonThirdParty,
            ],
            'samples' => [
                'historical_non_third_party_payments' => $samples,
            ],
        ];
    }
}
