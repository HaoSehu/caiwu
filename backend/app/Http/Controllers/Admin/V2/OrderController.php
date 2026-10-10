<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Order\ListOrdersRequest;
use App\Http\Requests\Admin\V2\Order\ShowOrderRequest;
use App\Http\Resources\Admin\V2\AdminOrderDetailResource;
use App\Http\Resources\Admin\V2\AdminOrderSummaryResource;
use App\Models\Order;
use App\Services\Finance\OrderV2QueryService;
use App\Services\ProductCatalog\ProductFullPathResolver;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderV2QueryService $orders,
        private readonly ProductFullPathResolver $productFullPathResolver,
    ) {}

    public function index(ListOrdersRequest $request): JsonResponse
    {
        return $this->paginate(
            $this->orders->paginateAdminOrders($request->validated()),
            fn (Order $order): array => (new AdminOrderSummaryResource($order, $this->productFullPathResolver))->resolve()
        );
    }

    public function show(ShowOrderRequest $request, int $order): JsonResponse
    {
        return $this->success([
            'order' => (new AdminOrderDetailResource($this->orders->findAdminOrder($order), $this->productFullPathResolver))->resolve(),
        ]);
    }
}
