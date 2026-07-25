<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_groups') && Schema::hasColumn('product_groups', 'title')) {
            Schema::table('product_groups', function (Blueprint $table): void {
                $table->dropColumn('title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_groups') && ! Schema::hasColumn('product_groups', 'title')) {
            Schema::table('product_groups', function (Blueprint $table): void {
                $table->string('title', 150)->nullable()->after('name');
            });
        }
    }
};
