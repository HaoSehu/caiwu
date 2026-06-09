<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('paid_at')->comment('退款完成时间');
            }

            if (! Schema::hasColumn('invoices', 'refund_amount')) {
                $table->decimal('refund_amount', 12, 2)->nullable()->after('paid_at')->comment('退款金额');
            }

            if (! Schema::hasColumn('invoices', 'refund_method')) {
                $table->string('refund_method', 32)->nullable()->after('paid_at')->comment('退款方式');
            }

            if (! Schema::hasColumn('invoices', 'refund_trace_id')) {
                $table->string('refund_trace_id', 64)->nullable()->after('paid_at')->comment('退款链路追踪号');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            foreach (['refund_trace_id', 'refund_method', 'refund_amount', 'refunded_at'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
