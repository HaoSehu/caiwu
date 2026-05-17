<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'supplier_product_id')) {
                $table->unsignedBigInteger('supplier_product_id')->nullable()->after('supplier_id');
            }

            if (! Schema::hasColumn('products', 'supplier_product_name')) {
                $table->string('supplier_product_name', 190)->nullable()->after('supplier_product_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'supplier_product_name')) {
                $table->dropColumn('supplier_product_name');
            }

            if (Schema::hasColumn('products', 'supplier_product_id')) {
                $table->dropColumn('supplier_product_id');
            }
        });
    }
};
