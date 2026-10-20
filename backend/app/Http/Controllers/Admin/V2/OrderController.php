<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Invoice\RefundInvoiceToBalanceRequest;
use App\Http\Requests\Admin\V2\Order\ListOrdersRequest;
use App\Http\Requests\Admin\V2\Order\ShowOrderRequest;
use App\Http\Resources\Admin\V2\AdminActionResultResource;
use App\Http\Resources\Admin\V2\AdminOrderDetailResource;
use App\Http\Resources\Admin\V2\AdminOrderSummaryResource;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\InvoiceRefundService;
use App\Services\Finance\OrderV2QueryService;
use App\Services\ProductCatalog\ProductFullPathResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderV2QueryService $orders,
        private readonly ProductFullPathResolver $productFullPathResolver,
        private readonly InvoiceRefundService $refunds,
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
     * 订单侧退款到余额：解析关联账单后复用账单退款服务（订单↔账单联动）。
     * 无关联账单直接拒绝；权限由路由组 finance.refund 把关。
     */
    public function refundToBalance(RefundInvoiceToBalanceRequest $request, Order $order): JsonResponse
    {
        $actor = $this->resolveAdminActor($request);
        $invoice = $order->invoice;
        $user = $order->user;

        throw_if($invoice === null, new BusinessException('订单没有关联账单，无法退款'));
        throw_if(! $user instanceof User, new BusinessException('订单缺少会员信息，无法退款'));

        $result = $this->refunds->refundInvoiceToBalance($user, $invoice, $request->payload(), [
            'operator_id' => $actor['id'],
            'operator_name' => $actor['name'],
            'operator_type' => 'admin',
            'trace_id' => (string) $request->header('X-Request-Id', ''),
        ]);

        return $this->success(AdminActionResultResource::make($result)->resolve(), '退款已退回会员余额');
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
