<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('content_articles', 'meta_title')) {
                $table->string('meta_title', 200)->nullable()->after('keywords');
            }
            if (! Schema::hasColumn('content_articles', 'meta_description')) {
                $table->string('meta_description', 500)->nullable()->after('meta_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('content_articles', function (Blueprint $table) {
            if (Schema::hasColumn('content_articles', 'meta_description')) {
                $table->dropColumn('meta_description');
            }
            if (Schema::hasColumn('content_articles', 'meta_title')) {
                $table->dropColumn('meta_title');
            }
        });
    }
};
