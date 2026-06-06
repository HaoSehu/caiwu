<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function newCustomerDailySummary(Request $request)
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'required_without:start_date'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
        ]);

        return $this->success(
            $this->financeQueryService->dailyCustomerSummary(
                (string) ($data['start_date'] ?? $data['month']),
                isset($data['end_date']) ? (string) $data['end_date'] : null
            )
        );
    }

    public function productIncomeSummary(Request $request)
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'required_without:start_date'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
        ]);

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
