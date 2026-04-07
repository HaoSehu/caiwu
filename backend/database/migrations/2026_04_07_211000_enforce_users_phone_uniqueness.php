<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'phone')) {
            return;
        }

        DB::table('users')
            ->where('phone', '')
            ->update(['phone' => null]);

        $duplicates = DB::table('users')
            ->select('phone', DB::raw('COUNT(*) as aggregate_count'))
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->groupBy('phone')
            ->having('aggregate_count', '>', 1)
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $phones = $duplicates->pluck('phone')->map(fn ($phone) => (string) $phone)->implode(', ');

            throw new RuntimeException('users.phone 存在重复值，无法添加唯一索引，请先清洗数据：' . $phones);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('phone', 'users_phone_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_phone_unique');
        });
    }
};
