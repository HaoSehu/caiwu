<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\OrderListRequest;
use App\Services\Finance\AdminFinanceQueryService;

class OrderController extends Controller
{
    public function __construct(
        private readonly AdminFinanceQueryService $financeQueryService,
    ) {}

    public function index(OrderListRequest $request)
    {
        return $this->paginate(
            $this->financeQueryService->paginateOrders($request->validated(), $request->perPage())
        );
    }

    public function show(int $id)
    {
        return $this->success($this->financeQueryService->getOrderDetail($id));
    }
}
