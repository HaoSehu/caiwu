<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Finance\AdminFinanceQueryService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly AdminFinanceQueryService $financeQueryService,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['keyword', 'status', 'type', 'date_range']);
        $perPage = max(1, min((int) $request->input('page_size', 20), 100));

        return $this->paginate(
            $this->financeQueryService->paginateOrders($filters, $perPage)
        );
    }
}
