<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gateway_logs')) {
            return;
        }

        if (! Schema::hasColumn('gateway_logs', 'detail_key')) {
            Schema::table('gateway_logs', function (Blueprint $table): void {
                $table->string('detail_key', 180)
                    ->nullable()
                    ->after('response_data')
                    ->comment('请求/响应明细所在 gateway-json 日志文件的定位键');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gateway_logs') && Schema::hasColumn('gateway_logs', 'detail_key')) {
            Schema::table('gateway_logs', function (Blueprint $table): void {
                $table->dropColumn('detail_key');
            });
        }
    }
};
