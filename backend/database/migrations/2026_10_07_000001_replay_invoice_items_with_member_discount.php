<?php

declare(strict_types=1);

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * W1 订正：明细投影（invoice_items）此前漏算会员折扣——unit_price 按「应付+券减免」计算、
 * discount_amount 只含券减免，导致带会员折扣账单的明细与汇总口径不一致（错值已持久化）。
 *
 * 修复方式：对带会员折扣的账单重放 syncInvoiceItemProjection（全删全插，幂等可重跑）。
 * 无会员折扣的账单新旧公式结果相同，无需重放。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasTable('invoice_items')) {
            return;
        }

        Invoice::query()
            ->where('member_discount_amount', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($invoices): void {
                $invoices->each(fn (Invoice $invoice) => $invoice->syncInvoiceItemProjection());
            });
    }

    public function down(): void
    {
        // 重放不可逆（旧投影即错误值），无需回滚。
    }
};
