<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Coupon\DestroyRequest;
use App\Http\Requests\Admin\Coupon\IndexRequest;
use App\Http\Requests\Admin\Coupon\StoreRequest;
use App\Http\Requests\Admin\Coupon\ToggleStatusRequest;
use App\Http\Requests\Admin\Coupon\UpdateRequest;
use App\Models\Coupon;
use App\Services\Finance\CouponService;
use Illuminate\Http\Request;

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

    public function index(IndexRequest $request)
    {
        $filters = $request->validated();

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

    public function store(StoreRequest $request)
    {
        $coupon = $this->couponService->createCoupon(
            $request->validated(),
            $this->buildContext($request)
        );

        return $this->success($coupon, '优惠券创建成功');
    }

    public function update(UpdateRequest $request, Coupon $coupon)
    {
        $updatedCoupon = $this->couponService->updateCoupon(
            $coupon,
            $request->validated(),
            $this->buildContext($request)
        );

        return $this->success($updatedCoupon, '优惠券更新成功');
    }

    public function toggleStatus(ToggleStatusRequest $request, Coupon $coupon)
    {
        return $this->success(
            $this->couponService->toggleCouponStatus($coupon, $this->buildContext($request)),
            '优惠券状态已更新'
        );
    }

    public function destroy(DestroyRequest $request, Coupon $coupon)
    {
        $this->couponService->deleteCoupon($coupon, $this->buildContext($request));

        return $this->success(null, '优惠券删除成功');
    }

    private function buildContext(Request $request): array
    {
        return [
            'operator' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
        ];
    }
}
