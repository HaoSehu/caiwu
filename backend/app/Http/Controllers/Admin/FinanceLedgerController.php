<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\FinanceLedgerListRequest;
use App\Services\Finance\FinanceLedgerQueryService;

class FinanceLedgerController extends Controller
{
    public function __construct(
        private readonly FinanceLedgerQueryService $ledgerQueryService,
    ) {}

    public function index(FinanceLedgerListRequest $request)
    {
        return $this->success(
            $this->ledgerQueryService->paginateForAdmin(
                $request->filters(),
                $request->perPage()
            )
        );
    }

    public function summary(FinanceLedgerListRequest $request)
    {
        return $this->success(
            $this->ledgerQueryService->summaryForAdmin($request->filters())
        );
    }

    public function show(int $id)
    {
        return $this->success(
            $this->ledgerQueryService->detailForAdmin($id)
        );
    }
}
