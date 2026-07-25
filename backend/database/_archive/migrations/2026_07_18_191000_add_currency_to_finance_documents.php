<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('currency', 3)->default('CNY')->after('amount');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('currency', 3)->default('CNY')->after('amount');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('currency', 3)->default('CNY')->after('amount');
        });

        Schema::table('account_transactions', function (Blueprint $table): void {
            $table->string('currency', 3)->default('CNY')->after('change_amount');
        });
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};
