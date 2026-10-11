<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Order\ListOrdersRequest;
use App\Http\Requests\Admin\V2\Order\MarkOrderRefundRequest;
use App\Http\Requests\Admin\V2\Order\ShowOrderRequest;
use App\Http\Resources\Admin\V2\AdminActionResultResource;
use App\Http\Resources\Admin\V2\AdminOrderDetailResource;
use App\Http\Resources\Admin\V2\AdminOrderSummaryResource;
use App\Models\AdminUser;
use App\Models\Order;
use App\Services\Finance\InvoiceRefundMarkService;
use App\Services\Finance\OrderV2QueryService;
use App\Services\ProductCatalog\ProductFullPathResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderV2QueryService $orders,
        private readonly ProductFullPathResolver $productFullPathResolver,
        private readonly InvoiceRefundMarkService $refundMarks,
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

    /**
     * 退款标记（纯标记不动资金）：订单侧入口，内部解析关联账单后走同一守卫链
     * （订单↔账单联动；无关联账单直接拒绝）。权限由路由组 finance.refund 把关。
     */
    public function markRefund(MarkOrderRefundRequest $request, Order $order): JsonResponse
    {
        $actor = $this->resolveAdminActor($request);

        $result = $this->refundMarks->markOrderRefunded($order, $request->payload(), [
            'operator_id' => $actor['id'],
            'operator_name' => $actor['name'],
            'trace_id' => (string) $request->header('X-Request-Id', ''),
        ]);

        return $this->success(AdminActionResultResource::make($result)->resolve(), (string) $result['message']);
    }

    private function resolveAdminActor(Request $request): array
    {
        $actor = $request->user();

        if ($actor instanceof AdminUser) {
            return [
                'id' => (int) $actor->getKey(),
                'name' => (string) ($actor->username ?? $actor->nickname ?? 'admin'),
            ];
        }

        return ['id' => 0, 'name' => 'admin'];
    }
}
