<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['gateway', 'trade_no'], 'payments_gateway_trade_no_unique');
            $table->index(['status', 'paid_at'], 'payments_status_paid_at_idx');
            $table->index(['user_id', 'status', 'created_at'], 'payments_user_status_created_idx');

            $table->string('remark', 255)->nullable()->comment('备注')->after('updated_at');
            $table->string('operator', 50)->nullable()->comment('操作人')->after('remark');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪号')->after('operator');
            $table->index('trace_id', 'payments_trace_id_idx');
        });

        Schema::table('payment_callbacks', function (Blueprint $table) {
            $table->index(['is_verified', 'received_at'], 'payment_callbacks_verified_received_idx');
            $table->index('gateway_trade_no', 'payment_callbacks_gateway_trade_no_idx');

            $table->string('remark', 255)->nullable()->comment('备注')->after('updated_at');
            $table->string('operator', 50)->nullable()->comment('操作人')->after('remark');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪号')->after('operator');
            $table->index('trace_id', 'payment_callbacks_trace_id_idx');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index(['user_id', 'product_id', 'status'], 'services_user_product_status_idx');
            $table->index(['auto_renew', 'expires_at'], 'services_auto_renew_expires_idx');
            $table->index(['status', 'updated_at'], 'services_status_updated_idx');
            $table->index('domain', 'services_domain_idx');

            $table->string('remark', 255)->nullable()->comment('备注')->after('updated_at');
            $table->string('operator', 50)->nullable()->comment('操作人')->after('remark');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪号')->after('operator');
            $table->index('trace_id', 'services_trace_id_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['user_id', 'due_date'], 'invoices_user_due_date_idx');
            $table->index(['type', 'status', 'due_date'], 'invoices_type_status_due_idx');
            $table->index('created_at', 'invoices_created_at_idx');

            $table->string('remark', 255)->nullable()->comment('备注')->after('updated_at');
            $table->string('operator', 50)->nullable()->comment('操作人')->after('remark');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪号')->after('operator');
            $table->index('trace_id', 'invoices_trace_id_idx');
        });

        Schema::table('account_transactions', function (Blueprint $table) {
            $table->index(['user_id', 'event_type', 'created_at'], 'account_transactions_user_event_created_idx');
            $table->index('trace_id', 'account_transactions_trace_id_idx');
            $table->index('created_at', 'account_transactions_created_at_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'orders_user_created_idx');
            $table->index(['service_id', 'status'], 'orders_service_status_idx');
            $table->index('paid_at', 'orders_paid_at_idx');
            $table->index(['type', 'created_at'], 'orders_type_created_idx');

            $table->string('remark', 255)->nullable()->comment('备注')->after('updated_at');
            $table->string('operator', 50)->nullable()->comment('操作人')->after('remark');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪号')->after('operator');
            $table->index('trace_id', 'orders_trace_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_created_idx');
            $table->dropIndex('orders_service_status_idx');
            $table->dropIndex('orders_paid_at_idx');
            $table->dropIndex('orders_type_created_idx');
            $table->dropIndex('orders_trace_id_idx');
            $table->dropColumn(['remark', 'operator', 'trace_id']);
        });

        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropIndex('account_transactions_user_event_created_idx');
            $table->dropIndex('account_transactions_trace_id_idx');
            $table->dropIndex('account_transactions_created_at_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_user_due_date_idx');
            $table->dropIndex('invoices_type_status_due_idx');
            $table->dropIndex('invoices_created_at_idx');
            $table->dropIndex('invoices_trace_id_idx');
            $table->dropColumn(['remark', 'operator', 'trace_id']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('services_user_product_status_idx');
            $table->dropIndex('services_auto_renew_expires_idx');
            $table->dropIndex('services_status_updated_idx');
            $table->dropIndex('services_domain_idx');
            $table->dropIndex('services_trace_id_idx');
            $table->dropColumn(['remark', 'operator', 'trace_id']);
        });

        Schema::table('payment_callbacks', function (Blueprint $table) {
            $table->dropIndex('payment_callbacks_verified_received_idx');
            $table->dropIndex('payment_callbacks_gateway_trade_no_idx');
            $table->dropIndex('payment_callbacks_trace_id_idx');
            $table->dropColumn(['remark', 'operator', 'trace_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_gateway_trade_no_unique');
            $table->dropIndex('payments_status_paid_at_idx');
            $table->dropIndex('payments_user_status_created_idx');
            $table->dropIndex('payments_trace_id_idx');
            $table->dropColumn(['remark', 'operator', 'trace_id']);
        });
    }
};
