<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Finance\ListFinanceLedgerRequest;
use App\Services\Finance\FinanceLedgerQueryService;

class FinanceLedgerController extends Controller
{
    public function __construct(
        private readonly FinanceLedgerQueryService $ledgerQueryService,
    ) {}

    public function index(ListFinanceLedgerRequest $request)
    {
        return $this->success(
            $this->ledgerQueryService->paginateForClient(
                $request->user(),
                $request->filters(),
                $request->perPage()
            )
        );
    }

    public function summary(ListFinanceLedgerRequest $request)
    {
        return $this->success(
            $this->ledgerQueryService->summaryForClient(
                $request->user(),
                $request->filters()
            )
        );
    }

    public function show(ListFinanceLedgerRequest $request, int $id)
    {
        return $this->success(
            $this->ledgerQueryService->detailForClient($request->user(), $id)
        );
    }
}
