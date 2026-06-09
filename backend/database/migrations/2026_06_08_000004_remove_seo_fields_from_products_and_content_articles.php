<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['meta_title', 'meta_description', 'meta_keywords'],
                static fn (string $column): bool => Schema::hasColumn('products', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('content_articles', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['meta_title', 'meta_description'],
                static fn (string $column): bool => Schema::hasColumn('content_articles', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'meta_title')) {
                $table->string('meta_title', 200)->nullable()->after('remark');
            }
            if (! Schema::hasColumn('products', 'meta_description')) {
                $table->string('meta_description', 500)->nullable()->after('meta_title');
            }
            if (! Schema::hasColumn('products', 'meta_keywords')) {
                $table->string('meta_keywords', 255)->nullable()->after('meta_description');
            }
        });

        Schema::table('content_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('content_articles', 'meta_title')) {
                $table->string('meta_title', 200)->nullable()->after('keywords');
            }
            if (! Schema::hasColumn('content_articles', 'meta_description')) {
                $table->string('meta_description', 500)->nullable()->after('meta_title');
            }
        });
    }
};
