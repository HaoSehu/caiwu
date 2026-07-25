<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('order_id');
            $table->string('product_name_snapshot', 255)->nullable()->after('product_id');
            $table->string('product_type_snapshot', 100)->nullable()->after('product_name_snapshot');
            $table->unsignedBigInteger('service_id')->nullable()->after('product_type_snapshot');
            $table->unsignedBigInteger('coupon_id')->nullable()->after('service_id');
            $table->unsignedBigInteger('user_coupon_id')->nullable()->after('coupon_id');
            $table->string('coupon_code', 100)->nullable()->after('user_coupon_id');
            $table->decimal('discount', 10, 2)->default(0)->after('amount');
            $table->string('billing_cycle', 30)->nullable()->after('discount');
            $table->unsignedInteger('quantity')->default(1)->after('billing_cycle');
            $table->json('config_snapshot')->nullable()->after('quantity');
            $table->json('config_pricing_snapshot')->nullable()->after('config_snapshot');
            $table->json('coupon_snapshot')->nullable()->after('config_pricing_snapshot');

            $table->index('product_id', 'invoices_product_id_idx');
            $table->index('service_id', 'invoices_service_id_idx');
        });

        // referral_rewards 增加 invoice_id，后续迁移用
        if (Schema::hasTable('referral_rewards') && ! Schema::hasColumn('referral_rewards', 'invoice_id')) {
            Schema::table('referral_rewards', function (Blueprint $table) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('order_id');
                $table->index('invoice_id', 'referral_rewards_invoice_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('referral_rewards') && Schema::hasColumn('referral_rewards', 'invoice_id')) {
            Schema::table('referral_rewards', function (Blueprint $table) {
                $table->dropIndex('referral_rewards_invoice_id_idx');
                $table->dropColumn('invoice_id');
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_product_id_idx');
            $table->dropIndex('invoices_service_id_idx');

            $table->dropColumn([
                'product_id',
                'product_name_snapshot',
                'product_type_snapshot',
                'service_id',
                'coupon_id',
                'user_coupon_id',
                'coupon_code',
                'discount',
                'billing_cycle',
                'quantity',
                'config_snapshot',
                'config_pricing_snapshot',
                'coupon_snapshot',
            ]);
        });
    }
};
