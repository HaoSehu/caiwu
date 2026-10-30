<?php

declare(strict_types=1);

namespace App\Services\Admin\V2;

use App\Constants\OrderStatus;
use App\Models\AdminUser;
use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;

/**
 * 管理端订单写操作：目前只有取消。
 *
 * 存在的意义是收口「挂账僵尸订单」的人工出口——
 * 挂账订单被豁免支付窗口自动清理后，关联账单若被取消，订单会永久停在 PENDING，
 * 只能在管理端显式取消（口径见 docs/参考资料/后端/财务单据生成规则.md §6.6）。
 * 取消逻辑本身复用 OrderService::cancel()，状态机与审计不在这里重复实现。
 */
class AdminOrderActionV2Service
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function cancel(Order $order, Request $request): array
    {
        if ((int) $order->status !== OrderStatus::CANCELLED) {
            // 审计口径与 AdminInvoiceActionV2Service::cancel 对齐（D-3）：同一个 activity_logs
            // 表的 order.cancel / invoice.cancel 属同一动作族，人名列必须同源，否则管理员昵称与
            // 登录名不同时，两笔相邻审计会显示成两个人。取 username → email → 'admin'，
            // 与账单侧一致（admin_users 无 name 列，账单侧那截 ?->name 恒为 null）。
            $actor = $request->user();
            $actorName = 'admin';
            if ($actor instanceof AdminUser) {
                $actorName = (string) ($actor->username ?? $actor->email ?? 'admin');
            }

            $order = $this->orders->cancel($order, [
                'actor_type' => 'admin',
                'actor_user_id' => $actor instanceof AdminUser ? (int) $actor->getKey() : 0,
                'actor_name' => $actorName,
                'trace_id' => (string) $request->header('X-Request-Id', ''),
                'ip_address' => (string) $request->ip(),
                'reason' => 'admin_order_cancel',
            ]);
        } else {
            $order = $order->fresh() ?? $order;
        }

        $invoice = $order->invoice;

        return [
            'id' => (int) $order->id,
            'status' => 'completed',
            'message' => '订单已取消',
            'detail' => [
                'type' => 'cancellation',
                'order' => [
                    'id' => (int) $order->id,
                    'order_no' => (string) $order->order_no,
                    'status' => (int) $order->status,
                ],
                'invoice' => $invoice === null ? null : [
                    'id' => (int) $invoice->id,
                    'invoice_no' => (string) $invoice->invoice_no,
                    'status' => (int) $invoice->status,
                ],
            ],
        ];
    }
}
