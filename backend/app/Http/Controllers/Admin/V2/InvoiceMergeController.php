<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Invoice\MergeInvoicesRequest;
use App\Http\Resources\Admin\V2\AdminActionResultResource;
use App\Http\Resources\Admin\V2\AdminInvoiceMergeDetailResource;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Services\Finance\InvoiceMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 账单合并（M1）管理端入口。
 * 路由挂载（由主控合入 routes/v2-admin.php）：
 * - POST /invoices/merge                → permission:invoice.manage
 * - POST /invoices/{invoice}/unmerge    → permission:invoice.manage
 * - GET  /invoices/{invoice}/merge-items → permission:invoice.detail
 * 守卫/事务/审计均在 InvoiceMergeService；控制器只做编组与响应。
 */
class InvoiceMergeController extends Controller
{
    public function __construct(
        private readonly InvoiceMergeService $invoiceMerges,
    ) {}

    /**
     * 合并同用户多张待支付账单为一张总账单。
     */
    public function merge(MergeInvoicesRequest $request): JsonResponse
    {
        $payload = $request->payload();
        $master = $this->invoiceMerges->merge(
            $payload['user_id'],
            $payload['invoice_ids'],
            $this->resolveOperatorId($request),
        );

        return $this->success(AdminActionResultResource::make([
            'id' => (int) $master->id,
            'status' => 'completed',
            'message' => sprintf('已合并为总账单 %s', (string) $master->invoice_no),
            'detail' => (new AdminInvoiceMergeDetailResource($master->fresh(['mergeItems.childInvoice'])))->resolve(),
        ])->resolve());
    }

    /**
     * 拆分合并总账单：子账单恢复独立待支付，总账单作废。
     */
    public function unmerge(Request $request, Invoice $invoice): JsonResponse
    {
        $master = $this->invoiceMerges->unmerge($invoice, $this->resolveOperatorId($request));

        return $this->success(AdminActionResultResource::make([
            'id' => (int) $master->id,
            'status' => 'completed',
            'message' => sprintf('总账单 %s 已拆分作废，子账单恢复独立待支付', (string) $master->invoice_no),
        ])->resolve());
    }

    /**
     * 总账单子账单清单（含各自支付状态）。
     */
    public function mergeItems(Invoice $invoice): JsonResponse
    {
        $invoice->loadMissing('mergeItems.childInvoice');

        return $this->success([
            'merge' => (new AdminInvoiceMergeDetailResource($invoice))->resolve(),
        ]);
    }

    private function resolveOperatorId(Request $request): int
    {
        $actor = $request->user();

        return $actor instanceof AdminUser ? (int) $actor->getKey() : 0;
    }
}
