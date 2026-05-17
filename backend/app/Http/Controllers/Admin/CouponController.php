<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\Finance\CouponService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function __construct(
        private CouponService $couponService,
    ) {}

    public function summary(Request $request)
    {
        $summary = $this->couponService->adminSummary(
            $request->only(['keyword', 'status', 'discount_type', 'distribution_type', 'discount_scope'])
        );

        return $this->success(
            [
                ...$summary,
                'enabled' => $this->couponService->isCouponFeatureEnabled(),
            ]
        );
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:0,1,expired'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'distribution_type' => ['nullable', Rule::in(['public', 'private'])],
            'discount_scope' => ['nullable', Rule::in(['first_month', 'recurring', 'renew'])],
        ]);

        $perPage = min(max((int) $request->input('page_size', 20), 1), 100);

        return $this->paginate(
            $this->couponService->adminList($filters, $perPage)
        );
    }

    public function productTree()
    {
        return $this->success([
            'tree' => $this->couponService->adminProductTree(),
        ]);
    }

    public function store(Request $request)
    {
        $coupon = $this->couponService->createCoupon(
            $this->validatedPayload($request),
            $this->buildContext($request)
        );

        return $this->success($coupon, '优惠券创建成功');
    }

    public function update(Request $request, Coupon $coupon)
    {
        $updatedCoupon = $this->couponService->updateCoupon(
            $coupon,
            $this->validatedPayload($request, $coupon),
            $this->buildContext($request)
        );

        return $this->success($updatedCoupon, '优惠券更新成功');
    }

    public function toggleStatus(Request $request, Coupon $coupon)
    {
        return $this->success(
            $this->couponService->toggleCouponStatus($coupon, $this->buildContext($request)),
            '优惠券状态已更新'
        );
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        $this->couponService->deleteCoupon($coupon, $this->buildContext($request));

        return $this->success(null, '优惠券删除成功');
    }

    private function validatedPayload(Request $request, ?Coupon $coupon = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_scope' => ['required', Rule::in(['first_month', 'recurring', 'renew'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'distribution_type' => ['required', Rule::in(['public', 'private'])],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'billing_cycles' => ['nullable', 'array'],
            'billing_cycles.*' => ['string', 'max:30'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'first_order_only' => ['nullable', 'boolean'],
            'total_usage_limit' => ['nullable', 'integer', 'min:0'],
            'per_user_limit' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
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
