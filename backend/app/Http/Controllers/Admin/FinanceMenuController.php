<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FinanceMenu\NewCustomerDailySummaryRequest;
use App\Http\Requests\Admin\FinanceMenu\ProductIncomeSummaryRequest;
use App\Services\Finance\AdminFinanceQueryService;
use Illuminate\Http\Request;

class FinanceMenuController extends Controller
{
    public function __construct(
        private readonly AdminFinanceQueryService $financeQueryService,
    ) {}

    public function recharges(Request $request)
    {
        $filters = $request->only(['keyword', 'status', 'date_range']);
        $perPage = max(1, min((int) $request->input('page_size', 20), 100));

        return $this->paginate(
            $this->financeQueryService->paginateRecharges($filters, $perPage)
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

    public function renewalOrders(Request $request)
    {
        $filters = $request->only(['keyword', 'status', 'date_range']);
        $perPage = max(1, min((int) $request->input('page_size', 20), 100));

        return $this->paginate(
            $this->financeQueryService->paginateOrders($filters, $perPage, 'renew')
        );
    }

    public function addonOrders(Request $request)
    {
        $filters = $request->only(['keyword', 'status', 'kind', 'date_range']);
        $perPage = max(1, min((int) $request->input('page_size', 20), 100));

        return $this->paginate(
            $this->financeQueryService->paginateAddonOrders($filters, $perPage)
        );
    }
}
