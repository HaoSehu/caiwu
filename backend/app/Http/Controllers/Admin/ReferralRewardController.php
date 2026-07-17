<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReferralReward\IndexRequest;
use App\Models\ReferralReward;
use App\Services\Referral\ReferralService;

class ReferralRewardController extends Controller
{
    public function __construct(private ReferralService $referralService) {}

    public function index(IndexRequest $request)
    {
        $filters = $request->validated();

        $perPage = max(1, min((int) ($filters['page_size'] ?? 20), 100));
        $paginator = $this->referralService->adminRewardLogs($filters, $perPage);

        return $this->success([
            'list' => collect($paginator->items())->map(fn (ReferralReward $item) => [
                'id' => $item->id,
                'status' => (int) $item->status,
                'order_amount' => number_format((float) $item->order_amount, 2, '.', ''),
                'reward_rate' => number_format((float) $item->reward_rate, 2, '.', ''),
                'reward_amount' => number_format((float) $item->reward_amount, 2, '.', ''),
                'available_at' => $item->available_at?->format('Y-m-d H:i:s'),
                'released_at' => $item->released_at?->format('Y-m-d H:i:s'),
                'rewarded_at' => $item->rewarded_at?->format('Y-m-d H:i:s'),
                'remark' => $item->remark,
                'referrer' => $item->referrer ? [
                    'id' => $item->referrer->id,
                    'email' => $item->referrer->email,
                    'nickname' => $item->referrer->nickname,
                    'display_name' => $item->referrer->display_name,
                ] : null,
                'referred_user' => $item->referredUser ? [
                    'id' => $item->referredUser->id,
                    'email' => $item->referredUser->email,
                    'nickname' => $item->referredUser->nickname,
                    'display_name' => $item->referredUser->display_name,
                ] : null,
                'order' => $item->order ? [
                    'id' => $item->order->id,
                    'order_no' => $item->order->order_no,
                    'product_display_name' => $this->referralService->resolveRewardProductDisplayName($item),
                ] : null,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'custom_display_name' => $item->product->custom_display_name,
                    'display_name' => $this->referralService->resolveRewardProductDisplayName($item),
                ] : null,
            ])->values()->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }
}
