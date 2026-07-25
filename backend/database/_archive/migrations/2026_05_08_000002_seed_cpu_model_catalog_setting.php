<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $exists = DB::table('settings')
            ->where('group_key', 'product')
            ->where('item_key', 'cpu_model_catalog')
            ->exists();

        if ($exists) {
            return;
        }

        // 只补一个空目录默认值，不覆盖已有的 CPU 型号目录数据。
        DB::table('settings')->insert([
            'group_key' => 'product',
            'item_key' => 'cpu_model_catalog',
            'item_value' => '[]',
        ]);
    }

    public function down(): void
    {
        // 这是目录初始化数据，回滚时不主动清理，避免误删后续人工维护内容。
    }
};
