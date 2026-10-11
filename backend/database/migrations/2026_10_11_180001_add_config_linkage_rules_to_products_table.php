<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 产品配置选项联动规则（M7）：products 新增集中式规则列。
 *
 * - config_linkage_rules：JSON 数组，元素结构
 *   {id, when:{all:[{field,op,value}]}, effects:[{type,target,scope?,mode?,value?}]}。
 *   集中存储（规则跨选项引用），保存时由 ConfigLinkageRuleValidator 做结构/目标存在性/
 *   冲突/自引用校验，非法规则不允许落库。
 * - 无规则（NULL/空数组）时既有计价行为完全不变。
 *
 * 可重入：列先探测再建，重复执行不报错。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'config_linkage_rules')) {
                $table->json('config_linkage_rules')->nullable()->after('config_options')
                    ->comment('配置联动规则：[{id,when:{all:[{field,op,value}]},effects:[{type,target,...}]}]');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'config_linkage_rules')) {
                $table->dropColumn('config_linkage_rules');
            }
        });
    }
};
