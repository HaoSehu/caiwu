<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_articles', function (Blueprint $table) {
            $table->timestamp('require_reread_at')->nullable()->after('view_count');
        });
    }

    public function down(): void
    {
        Schema::table('content_articles', function (Blueprint $table) {
            $table->dropColumn('require_reread_at');
        });
    }
};
