<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReferralWithdrawal\ApproveRequest;
use App\Http\Requests\Admin\ReferralWithdrawal\IndexRequest;
use App\Http\Requests\Admin\ReferralWithdrawal\RejectRequest;
use App\Models\ReferralWithdrawal;
use App\Services\Referral\ReferralService;

class ReferralWithdrawalController extends Controller
{
    public function __construct(private ReferralService $referralService) {}

    public function index(IndexRequest $request)
    {
        $filters = $request->validated();

        $perPage = max(1, min((int) ($filters['page_size'] ?? 20), 100));
        $paginator = $this->referralService->adminWithdrawalList($filters, $perPage);

        return $this->success([
            'list' => collect($paginator->items())->map(fn (ReferralWithdrawal $item) => [
                'id' => $item->id,
                'amount' => number_format((float) $item->amount, 2, '.', ''),
                'method' => $item->method,
                'account_name' => $item->account_name_display,
                'account_no' => $item->account_no,
                'status' => (int) $item->status,
                'remark' => $item->remark,
                'operator' => $item->operator,
                'trace_id' => $item->trace_id,
                'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                'processed_at' => $item->processed_at?->format('Y-m-d H:i:s'),
                'user' => $item->user ? [
                    'id' => $item->user->id,
                    'email' => $item->user->email,
                    'nickname' => $item->user->nickname,
                    'display_name' => $item->user->display_name,
                ] : null,
            ])->values()->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    public function approve(ApproveRequest $request, ReferralWithdrawal $withdrawal)
    {
        $data = $request->validated();

        $record = $this->referralService->processWithdrawal(
            withdrawal: $withdrawal,
            action: 'approve',
            operatorUserId: (int) ($request->user()?->id ?? 0),
            operator: $request->user()?->username ?: 'admin',
            remark: $data['remark'] ?? null,
            traceId: (string) $request->header('X-Request-Id', ''),
        );

        return $this->success([
            'id' => $record->id,
            'status' => (int) $record->status,
            'processed_at' => $record->processed_at?->format('Y-m-d H:i:s'),
        ], '提现已通过');
    }

    public function reject(RejectRequest $request, ReferralWithdrawal $withdrawal)
    {
        $data = $request->validated();

        $record = $this->referralService->processWithdrawal(
            withdrawal: $withdrawal,
            action: 'reject',
            operatorUserId: (int) ($request->user()?->id ?? 0),
            operator: $request->user()?->username ?: 'admin',
            remark: $data['remark'],
            traceId: (string) $request->header('X-Request-Id', ''),
        );

        return $this->success([
            'id' => $record->id,
            'status' => (int) $record->status,
            'processed_at' => $record->processed_at?->format('Y-m-d H:i:s'),
        ], '提现已拒绝');
    }
}
