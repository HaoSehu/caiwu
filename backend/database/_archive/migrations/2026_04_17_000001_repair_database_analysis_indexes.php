<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->repairOrdersTable();
        $this->repairInvoicesTable();
        $this->repairServicesTable();

        $this->ensureIndex('services', 'services_product_id_idx', fn (Blueprint $table) => $table->index('product_id', 'services_product_id_idx'));
        $this->ensureIndex('services', 'services_order_id_idx', fn (Blueprint $table) => $table->index('order_id', 'services_order_id_idx'));

        $this->ensureIndex('orders', 'orders_product_id_idx', fn (Blueprint $table) => $table->index('product_id', 'orders_product_id_idx'));
        $this->ensureIndex(
            'orders',
            'orders_service_status_id_idx',
            fn (Blueprint $table) => $table->index(['service_id', 'status', 'id'], 'orders_service_status_id_idx')
        );

        $this->ensureIndex(
            'products',
            'products_supplier_product_status_id_idx',
            fn (Blueprint $table) => $table->index(
                ['supplier_id', 'supplier_product_id', 'status', 'id'],
                'products_supplier_product_status_id_idx'
            )
        );

        $this->ensureIndex(
            'tickets',
            'tickets_user_updated_at_idx',
            fn (Blueprint $table) => $table->index(['user_id', 'updated_at', 'id'], 'tickets_user_updated_at_idx')
        );
        $this->ensureIndex('tickets', 'tickets_service_id_idx', fn (Blueprint $table) => $table->index('service_id', 'tickets_service_id_idx'));

        $this->ensureIndex(
            'ticket_replies',
            'ticket_replies_ticket_created_id_idx',
            fn (Blueprint $table) => $table->index(['ticket_id', 'created_at', 'id'], 'ticket_replies_ticket_created_id_idx')
        );

        $this->ensureIndex(
            'operation_logs',
            'operation_logs_module_subject_created_idx',
            fn (Blueprint $table) => $table->index(
                ['module', 'subject_id', 'created_at', 'id'],
                'operation_logs_module_subject_created_idx'
            )
        );

        $this->ensureIndex(
            'referral_account_logs',
            'idx_referral_account_user_created_idx',
            fn (Blueprint $table) => $table->index(['user_id', 'created_at', 'id'], 'idx_referral_account_user_created_idx')
        );

        $this->ensureIndex(
            'verification_histories',
            'verification_histories_user_id_id_idx',
            fn (Blueprint $table) => $table->index(['user_id', 'id'], 'verification_histories_user_id_id_idx')
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('verification_histories', 'verification_histories_user_id_id_idx');
        $this->dropIndexIfExists('referral_account_logs', 'idx_referral_account_user_created_idx');
        $this->dropIndexIfExists('operation_logs', 'operation_logs_module_subject_created_idx');
        $this->dropIndexIfExists('ticket_replies', 'ticket_replies_ticket_created_id_idx');
        $this->dropIndexIfExists('tickets', 'tickets_service_id_idx');
        $this->dropIndexIfExists('tickets', 'tickets_user_updated_at_idx');
        $this->dropIndexIfExists('products', 'products_supplier_product_status_id_idx');
        $this->dropIndexIfExists('orders', 'orders_service_status_id_idx');
        $this->dropIndexIfExists('orders', 'orders_product_id_idx');
        $this->dropIndexIfExists('orders', 'orders_trace_id_idx');
        $this->dropIndexIfExists('invoices', 'invoices_trace_id_idx');
        $this->dropIndexIfExists('services', 'services_order_id_idx');
        $this->dropIndexIfExists('services', 'services_product_id_idx');
        $this->dropIndexIfExists('services', 'services_trace_id_idx');
    }

    private function repairOrdersTable(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $needsRemark = ! Schema::hasColumn('orders', 'remark');
        $needsOperator = ! Schema::hasColumn('orders', 'operator');
        $needsTraceIdColumn = ! Schema::hasColumn('orders', 'trace_id');
        $needsTraceIdIndex = ! Schema::hasIndex('orders', 'orders_trace_id_idx');

        if (! $needsRemark && ! $needsOperator && ! $needsTraceIdColumn && ! $needsTraceIdIndex) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($needsRemark, $needsOperator, $needsTraceIdColumn, $needsTraceIdIndex): void {
            if ($needsRemark) {
                $table->string('remark', 255)->nullable()->comment('备注')->after('updated_at');
            }

            if ($needsOperator) {
                $table->string('operator', 50)->nullable()->comment('操作人')->after('remark');
            }

            if ($needsTraceIdColumn) {
                $table->string('trace_id', 64)->nullable()->comment('链路追踪号')->after('operator');
            }

            if ($needsTraceIdIndex) {
                $table->index('trace_id', 'orders_trace_id_idx');
            }
        });
    }

    private function repairInvoicesTable(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $needsRemark = ! Schema::hasColumn('invoices', 'remark');
        $needsOperator = ! Schema::hasColumn('invoices', 'operator');
        $needsTraceIdColumn = ! Schema::hasColumn('invoices', 'trace_id');
        $needsTraceIdIndex = ! Schema::hasIndex('invoices', 'invoices_trace_id_idx');

        if (! $needsRemark && ! $needsOperator && ! $needsTraceIdColumn && ! $needsTraceIdIndex) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) use ($needsRemark, $needsOperator, $needsTraceIdColumn, $needsTraceIdIndex): void {
            if ($needsRemark) {
                $table->string('remark', 255)->nullable()->comment('备注')->after('updated_at');
            }

            if ($needsOperator) {
                $table->string('operator', 50)->nullable()->comment('操作人')->after('remark');
            }

            if ($needsTraceIdColumn) {
                $table->string('trace_id', 64)->nullable()->comment('链路追踪号')->after('operator');
            }

            if ($needsTraceIdIndex) {
                $table->index('trace_id', 'invoices_trace_id_idx');
            }
        });
    }

    private function repairServicesTable(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        $needsRemark = ! Schema::hasColumn('services', 'remark');
        $needsOperator = ! Schema::hasColumn('services', 'operator');
        $needsTraceIdColumn = ! Schema::hasColumn('services', 'trace_id');
        $needsTraceIdIndex = ! Schema::hasIndex('services', 'services_trace_id_idx');

        if (! $needsRemark && ! $needsOperator && ! $needsTraceIdColumn && ! $needsTraceIdIndex) {
            return;
        }

        Schema::table('services', function (Blueprint $table) use ($needsRemark, $needsOperator, $needsTraceIdColumn, $needsTraceIdIndex): void {
            if ($needsRemark) {
                $table->string('remark', 255)->nullable()->comment('备注')->after('updated_at');
            }

            if ($needsOperator) {
                $table->string('operator', 50)->nullable()->comment('操作人')->after('remark');
            }

            if ($needsTraceIdColumn) {
                $table->string('trace_id', 64)->nullable()->comment('链路追踪号')->after('operator');
            }

            if ($needsTraceIdIndex) {
                $table->index('trace_id', 'services_trace_id_idx');
            }
        });
    }

    private function ensureIndex(string $tableName, string $indexName, callable $definition): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
};
