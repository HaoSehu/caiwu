<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Finance\ListBalanceLogsRequest;
use App\Services\Finance\ClientFinanceQueryService;

class FinanceController extends Controller
{
    public function __construct(
        private ClientFinanceQueryService $financeQueryService,
    ) {}

    /**
     * 浣欓鍙樺姩璁板綍
     */
    public function balanceLogs(ListBalanceLogsRequest $request)
    {
        return $this->success(
            $this->financeQueryService->paginateBalanceLogs(
                $request->user(),
                $request->filters(),
                $request->perPage()
            )
        );
    }

    public function balanceLogsSummary(ListBalanceLogsRequest $request)
    {
        return $this->success(
            $this->financeQueryService->balanceLogSummary(
                $request->user(),
                $request->filters()
            )
        );
    }
}
