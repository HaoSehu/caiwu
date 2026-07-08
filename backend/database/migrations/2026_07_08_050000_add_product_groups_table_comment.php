<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_groups')) {
            return;
        }

        DB::statement("ALTER TABLE product_groups COMMENT = '商品分组自引用树，承载商品分类层级真源'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_groups')) {
            return;
        }

        DB::statement("ALTER TABLE product_groups COMMENT = ''");
    }
};
