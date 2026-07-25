<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L3 — 删除 servers 遗留空表
 *
 * 经确认：
 *   - servers 表 0 行数据
 *   - app/ 目录无任何 Model/Controller/Service 引用 Server 类（排除迁移脚本）
 *   - MigrateCatalogServersCommand 是历史旧库数据迁移命令，
 *     源库和目标库均为 idc，已无实际用途
 *   - 表结构与新插件化供应商体系（integration_plugins/supplier_plugin_bindings）重叠
 *
 * 直接 DROP，保留 down() 以便回滚。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('servers');
    }

    public function down(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('hostname', 200)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('ip_address', 45)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('type', 30)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('module', 50)->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->json('module_config')->nullable();
            $table->integer('max_accounts')->default(0);
            $table->integer('current_accounts')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }
};
