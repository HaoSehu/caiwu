<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'remark')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->string('remark', 255)->nullable()->after('product_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'remark')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('remark');
        });
    }
};
