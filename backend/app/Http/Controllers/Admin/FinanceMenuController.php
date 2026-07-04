<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\OrderListRequest;
use App\Http\Requests\Admin\Finance\RechargeListRequest;
use App\Http\Requests\Admin\Finance\UpgradeOrderListRequest;
use App\Http\Requests\Admin\FinanceMenu\NewCustomerDailySummaryRequest;
use App\Http\Requests\Admin\FinanceMenu\ProductIncomeSummaryRequest;
use App\Services\Finance\AdminFinanceQueryService;

class FinanceMenuController extends Controller
{
    public function __construct(
        private readonly AdminFinanceQueryService $financeQueryService,
    ) {}

    public function recharges(RechargeListRequest $request)
    {
        return $this->paginate(
            $this->financeQueryService->paginateRecharges($request->validated(), $request->perPage())
        );
    }

    public function newCustomerDailySummary(NewCustomerDailySummaryRequest $request)
    {
        $data = $request->validated();

        return $this->success(
            $this->financeQueryService->dailyCustomerSummary(
                (string) ($data['start_date'] ?? $data['month']),
                isset($data['end_date']) ? (string) $data['end_date'] : null
            )
        );
    }

    public function productIncomeSummary(ProductIncomeSummaryRequest $request)
    {
        $data = $request->validated();

        return $this->success(
            $this->financeQueryService->productIncomeSummary(
                (string) ($data['start_date'] ?? $data['month']),
                isset($data['end_date']) ? (string) $data['end_date'] : null
            )
        );
    }

    public function renewalOrders(OrderListRequest $request)
    {
        return $this->paginate(
            $this->financeQueryService->paginateOrders($request->validated(), $request->perPage(), OrderType::RENEW)
        );
    }

    public function upgradeOrders(UpgradeOrderListRequest $request)
    {
        return $this->paginate(
            $this->financeQueryService->paginateUpgradeOrders($request->validated(), $request->perPage())
        );
    }
}
