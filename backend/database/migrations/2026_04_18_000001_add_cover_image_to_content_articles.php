<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('content_articles', 'cover_image')) {
            return;
        }

        Schema::table('content_articles', function (Blueprint $table) {
            $table->string('cover_image', 500)->nullable()->after('keywords');
        });
    }

    public function down(): void
    {
        Schema::table('content_articles', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });
    }
};
