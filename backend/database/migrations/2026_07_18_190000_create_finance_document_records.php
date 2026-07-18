<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('origin_invoice_id')
                ->nullable()
                ->after('order_id')
                ->constrained('invoices')
                ->nullOnDelete();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('refund_no', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('refund_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('status')->default(1);
            $table->string('refund_method', 32)->default('balance');
            $table->string('currency', 3)->default('CNY');
            $table->string('reason', 255)->nullable();
            $table->string('gateway_refund_no', 100)->nullable();
            $table->string('operator_type', 30)->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('operator_name', 50)->nullable();
            $table->string('trace_id', 64)->nullable()->index();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status', 'id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('recharge_records', function (Blueprint $table): void {
            $table->id();
            $table->string('record_no', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained('payments')->nullOnDelete();
            $table->foreignId('account_transaction_id')->nullable()->unique()->constrained('account_transactions')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('origin_recharge_record_id')->nullable()->constrained('recharge_records')->nullOnDelete();
            $table->string('scene', 30);
            $table->string('direction', 8);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('CNY');
            $table->string('entry_type', 30);
            $table->string('remark', 255)->nullable();
            $table->string('operator_type', 30)->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('operator_name', 50)->nullable();
            $table->string('trace_id', 64)->nullable()->index();
            $table->timestamps();

            $table->index(['invoice_id', 'id']);
            $table->index(['order_id', 'id']);
            $table->index(['origin_recharge_record_id', 'id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recharge_records');
        Schema::dropIfExists('refunds');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('origin_invoice_id');
        });
    }
};
