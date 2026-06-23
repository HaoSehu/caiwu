<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponCampaign\DestroyRequest;
use App\Http\Requests\Admin\CouponCampaign\IndexRequest;
use App\Http\Requests\Admin\CouponCampaign\StoreRequest;
use App\Http\Requests\Admin\CouponCampaign\ToggleStatusRequest;
use App\Http\Requests\Admin\CouponCampaign\TriggerRequest;
use App\Http\Requests\Admin\CouponCampaign\UpdateRequest;
use App\Models\CouponCampaign;
use App\Services\Finance\CouponCampaignService;
use Illuminate\Http\Request;

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

    public function index(IndexRequest $request)
    {
        $filters = $request->validated();

        $perPage = min(max((int) $request->input('page_size', 20), 1), 100);

        return $this->paginate(
            $this->couponCampaignService->adminList($filters, $perPage)
        );
    }

    public function store(StoreRequest $request)
    {
        return $this->success(
            $this->couponCampaignService->createCampaign(
                $request->validated(),
                $this->buildContext($request)
            ),
            '活动已创建'
        );
    }

    public function update(UpdateRequest $request, CouponCampaign $couponCampaign)
    {
        return $this->success(
            $this->couponCampaignService->updateCampaign(
                $couponCampaign,
                $request->validated(),
                $this->buildContext($request)
            ),
            '活动已更新'
        );
    }

    public function toggleStatus(ToggleStatusRequest $request, CouponCampaign $couponCampaign)
    {
        return $this->success(
            $this->couponCampaignService->toggleCampaignStatus($couponCampaign, $this->buildContext($request)),
            '活动状态已更新'
        );
    }

    public function trigger(TriggerRequest $request, CouponCampaign $couponCampaign)
    {
        return $this->success(
            $this->couponCampaignService->triggerCampaign($couponCampaign, $this->buildContext($request)),
            '活动批次已发放'
        );
    }

    public function destroy(DestroyRequest $request, CouponCampaign $couponCampaign)
    {
        $this->couponCampaignService->deleteCampaign($couponCampaign);

        return $this->success(null, '活动已删除');
    }

    private function buildContext(Request $request): array
    {
        return [
            'operator' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
        ];
    }
}
