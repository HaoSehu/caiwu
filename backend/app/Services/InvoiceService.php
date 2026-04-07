<?php

namespace App\Services;

use App\Constants\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;

class InvoiceService
{
    /**
     * 根据订单创建账单
     */
    public function createFromOrder(Order $order): Invoice
    {
        return Invoice::create([
            'invoice_no' => Invoice::generateInvoiceNoFromOrderNo((string) $order->order_no),
            'user_id'    => $order->user_id,
            'order_id'   => $order->id,
            'type'       => $order->type === 'renew' ? 'renew' : 'normal',
            'amount'     => $order->amount - $order->discount,
            'status'     => InvoiceStatus::UNPAID,
            'due_date'   => now()->addDays(7),
        ]);
    }

    /**
     * 查询账单列表
     */
    public function adminList(array $filters, int $perPage = 20)
    {
        $query = Invoice::with(['user:id,email,nickname', 'order:id,order_no']);

        if (!empty($filters['invoice_no'])) {
            $query->where('invoice_no', $filters['invoice_no']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }
}
