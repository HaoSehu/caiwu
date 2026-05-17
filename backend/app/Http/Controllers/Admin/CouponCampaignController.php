<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CouponCampaign;
use App\Services\Finance\CouponCampaignService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponCampaignController extends Controller
{
    public function __construct(
        private CouponCampaignService $couponCampaignService,
    ) {}

    public function summary(Request $request)
    {
        return $this->success(
            $this->couponCampaignService->adminSummary($request->only(['keyword', 'status']))
        );
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:0,1'],
        ]);

        $perPage = min(max((int) $request->input('page_size', 20), 1), 100);

        return $this->paginate(
            $this->couponCampaignService->adminList($filters, $perPage)
        );
    }

    public function store(Request $request)
    {
        return $this->success(
            $this->couponCampaignService->createCampaign(
                $this->validatedPayload($request),
                $this->buildContext($request)
            ),
            '活动已创建'
        );
    }

    public function update(Request $request, CouponCampaign $couponCampaign)
    {
        return $this->success(
            $this->couponCampaignService->updateCampaign(
                $couponCampaign,
                $this->validatedPayload($request),
                $this->buildContext($request)
            ),
            '活动已更新'
        );
    }

    public function toggleStatus(Request $request, CouponCampaign $couponCampaign)
    {
        return $this->success(
            $this->couponCampaignService->toggleCampaignStatus($couponCampaign, $this->buildContext($request)),
            '活动状态已更新'
        );
    }

    public function trigger(Request $request, CouponCampaign $couponCampaign)
    {
        return $this->success(
            $this->couponCampaignService->triggerCampaign($couponCampaign, $this->buildContext($request)),
            '活动批次已发放'
        );
    }

    public function destroy(CouponCampaign $couponCampaign)
    {
        $this->couponCampaignService->deleteCampaign($couponCampaign);

        return $this->success(null, '活动已删除');
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'trigger_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'issue_quantity' => ['required', 'integer', 'min:1'],
            'valid_duration_hours' => ['nullable', 'integer', 'min:1', 'max:87600'],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_scope' => ['required', Rule::in(['first_month', 'recurring', 'renew'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'billing_cycles' => ['nullable', 'array'],
            'billing_cycles.*' => ['string', 'max:30'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'first_order_only' => ['nullable', 'boolean'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function buildContext(Request $request): array
    {
        return [
            'operator' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
        ];
    }
}
