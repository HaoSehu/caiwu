<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_categories')) {
            return;
        }

        Schema::table('product_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_categories', 'product_type')) {
                $table->string('product_type', 50)->nullable()->after('product_type_id');
            }
        });

        if (Schema::hasTable('product_types')) {
            DB::statement("UPDATE product_categories pc LEFT JOIN product_types pt ON pt.id = pc.product_type_id SET pc.product_type = COALESCE(NULLIF(pc.product_type, ''), pt.code, 'other')");
        } else {
            DB::statement("UPDATE product_categories SET product_type = COALESCE(NULLIF(product_type, ''), 'other')");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_categories')) {
            return;
        }

        Schema::table('product_categories', function (Blueprint $table): void {
            if (Schema::hasColumn('product_categories', 'product_type')) {
                $table->dropColumn('product_type');
            }
        });
    }
};
