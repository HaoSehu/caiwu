<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Finance\ListFinanceRechargesRequest;
use App\Http\Requests\Admin\V2\Finance\ShowNewCustomerDailySummaryRequest;
use App\Http\Resources\Admin\V2\AdminFinanceRechargeListItemResource;
use App\Http\Resources\Admin\V2\AdminNewCustomerDailySummaryResource;
use App\Services\Finance\AdminFinanceQueryService;
use Illuminate\Http\JsonResponse;

class FinanceMenuController extends Controller
{
    public function __construct(
        private readonly AdminFinanceQueryService $financeQueryService,
    ) {}

    public function recharges(ListFinanceRechargesRequest $request): JsonResponse
    {
        return $this->paginate(
            $this->financeQueryService->paginateRecharges($request->filters(), $request->pageSize()),
            AdminFinanceRechargeListItemResource::class
        );
    }

    public function newCustomerDailySummary(ShowNewCustomerDailySummaryRequest $request): JsonResponse
    {
        $data = $request->filters();

        return $this->success(
            (new AdminNewCustomerDailySummaryResource(
                $this->financeQueryService->dailyCustomerSummary(
                    (string) ($data['start_date'] ?? $data['month']),
                    isset($data['end_date']) ? (string) $data['end_date'] : null
                )
            ))->resolve()
        );
    }
}
