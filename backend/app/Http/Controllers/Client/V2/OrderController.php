<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\V2\Action\ClientActionRequest;
use App\Http\Requests\Client\V2\Order\ListOrdersRequest;
use App\Http\Requests\Client\V2\Order\ShowOrderRequest;
use App\Http\Resources\V2\ActionResultResource;
use App\Services\Client\V2\ClientActionV2Service;
use App\Services\Order\ClientOrderQueryService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly ClientOrderQueryService $orders,
        private readonly ClientActionV2Service $actions,
    ) {}

    public function cancel(ClientActionRequest $request, int $id)
    {
        return $this->success(ActionResultResource::make(
            $this->actions->cancelOrder($request->user(), $id, $request)
        )->resolve(), '订单已取消');
    }

    public function index(ListOrdersRequest $request)
    {
        return $this->success($this->orders->paginate(
            (int) $request->user()->id,
            $request->validated(),
            $this->paymentWindowExpiredContext($request),
        ));
    }

    public function show(int $id, ShowOrderRequest $request)
    {
        return $this->success($this->orders->detail(
            (int) $request->user()->id,
            $id,
            $this->paymentWindowExpiredContext($request),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentWindowExpiredContext(Request $request): array
    {
        return [
            'actor_type' => 'system',
            'actor_name' => 'payment-window-expired',
            'reason' => 'payment_window_expired',
            'ip_address' => $request->ip(),
        ];
    }
}
