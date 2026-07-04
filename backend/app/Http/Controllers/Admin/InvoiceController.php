<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\InvoiceListRequest;
use App\Http\Requests\Admin\Invoice\CancelRequest;
use App\Models\Invoice;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\InvoiceService;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private CheckoutService $checkoutService,
    ) {}

    public function index(InvoiceListRequest $request)
    {
        return $this->paginate($this->invoiceService->adminList($request->validated(), $request->perPage()));
    }

    public function show(int $id)
    {
        return $this->success($this->invoiceService->adminDetail($id));
    }

    public function cancel(CancelRequest $request, int $id)
    {
        $invoice = Invoice::findOrFail($id);
        $updated = $this->checkoutService->cancel($invoice, [
            'actor_type' => 'admin',
            'actor_user_id' => (int) ($request->user()?->id ?? 0),
            'actor_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
            'ip_address' => (string) $request->ip(),
            'reason' => 'admin_manual_cancel',
        ]);

        return $this->success($this->invoiceService->adminDetail((int) $updated->id), '账单已取消');
    }
}
