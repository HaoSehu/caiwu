<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Invoice\CancelInvoiceActionRequest;
use App\Http\Requests\Admin\V2\Invoice\ListInvoicesRequest;
use App\Http\Requests\Admin\V2\Invoice\RefundInvoiceToBalanceRequest;
use App\Http\Requests\Admin\V2\Invoice\ShowInvoiceRequest;
use App\Http\Resources\Admin\V2\AdminActionResultResource;
use App\Http\Resources\Admin\V2\AdminInvoiceDetailResource;
use App\Http\Resources\Admin\V2\AdminInvoiceSummaryResource;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Admin\V2\AdminInvoiceActionV2Service;
use App\Services\Finance\InvoiceRefundService;
use App\Services\Finance\InvoiceV2QueryService;
use App\Services\System\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceV2QueryService $invoices,
        private readonly AdminInvoiceActionV2Service $actions,
        private readonly InvoiceRefundService $refunds,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(ListInvoicesRequest $request): JsonResponse
    {
        return $this->paginate(
            $this->invoices->paginateAdminInvoices($request->validated()),
            AdminInvoiceSummaryResource::class
        );
    }

    public function show(ShowInvoiceRequest $request, int $invoice): JsonResponse
    {
        return $this->success([
            'invoice' => (new AdminInvoiceDetailResource($this->invoices->adminInvoiceDetail($invoice)))->resolve(),
        ]);
    }

    public function cancel(CancelInvoiceActionRequest $request, Invoice $invoice): JsonResponse
    {
        $result = $this->actions->cancel($invoice, $request);

        return $this->success(AdminActionResultResource::make($result)->resolve(), (string) $result['message']);
    }

    /**
     * 退款到余额（唯一通道，动资金）。
     * 权限由路由组 finance.refund 把关；守卫/幂等/审计在 InvoiceRefundService。
     */
    public function refundToBalance(RefundInvoiceToBalanceRequest $request, Invoice $invoice): JsonResponse
    {
        $actor = $this->resolveAdminActor($request);
        $user = User::query()->findOrFail((int) $invoice->user_id);

        $result = $this->refunds->refundInvoiceToBalance($user, $invoice, $request->payload(), [
            'operator_id' => $actor['id'],
            'operator_name' => $actor['name'],
            'trace_id' => (string) $request->header('X-Request-Id', ''),
        ]);

        $refund = (array) ($result['refund'] ?? []);
        $alreadyRefunded = (bool) ($result['already_refunded'] ?? false);
        $message = $alreadyRefunded
            ? '该账单已退款到余额，未重复操作'
            : sprintf('已退款 %s 元至用户余额', number_format((float) ($refund['refund_amount'] ?? 0), 2, '.', ''));

        // 资金操作审计：与纯标记通道对齐（finance.refund_mark.*），写失败不阻断结果返回。
        $this->activityLogs->logSafely(
            'finance',
            'finance.refund_balance.executed',
            $message,
            'invoice',
            (int) $invoice->id,
            array_filter([
                'invoice_no' => (string) $invoice->invoice_no,
                'refund_amount' => (string) ($refund['refund_amount'] ?? ''),
                'refund_reason' => (string) ($refund['refund_reason'] ?? ''),
                'operator_name' => $actor['name'],
                'trace_id' => (string) $request->header('X-Request-Id', ''),
                'already_refunded' => $alreadyRefunded ? 1 : 0,
            ], static fn (mixed $value): bool => $value !== ''),
        );

        return $this->success(AdminActionResultResource::make([
            'id' => (int) ($result['refund_id'] ?? $result['refund_invoice_id'] ?? $invoice->id),
            'status' => $alreadyRefunded ? 'already_refunded' : 'completed',
            'message' => $message,
            'detail' => [
                'type' => 'invoice_refund_balance',
                'already_refunded' => $alreadyRefunded,
                'invoice_id' => (int) ($result['invoice_id'] ?? $invoice->id),
                'payment_id' => (int) ($result['payment_id'] ?? 0),
                'refund_id' => (int) ($result['refund_id'] ?? 0),
                'refund_invoice_id' => (int) ($result['refund_invoice_id'] ?? 0),
                'recharge_record_id' => (int) ($result['recharge_record_id'] ?? 0),
                'refund' => $refund,
            ],
        ])->resolve(), $message);
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
