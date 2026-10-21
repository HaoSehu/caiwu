<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 清除历史孤表 agent_applications（数据库结构分析报告 P1-13/P1-15）。
 *
 * 判定依据（2026-10-21 实库 + 代码复核）：
 * - 表内 0 行；
 * - app/ 内无任何模型、查询或资源引用该表（全库 grep 零命中）；
 * - database/migrations 与 database/_archive/migrations 均无建表迁移（历史快照回灌残留）。
 *
 * 该表原用于「代理商入驻申请」，功能未上线且留下了 api_key varchar(64) 明文列；
 * 保留它只会让「明文密钥列」继续出现在 schema 审计里。按 2026_10_10 删 product_groups
 * 的先例直接 DROP，不保留兼容层；结构 DDL 见提交前的 schema dump 历史版本。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agent_applications')) {
            return;
        }

        // 只在确认为空表时删除：有数据说明该表并非「未上线的残留」，需人工先评估用途。
        $rows = DB::table('agent_applications')->count();
        if ($rows > 0) {
            throw new RuntimeException(
                'agent_applications 存在 '.$rows.' 行数据，判定为在用或待评估表，拒绝自动删除；'
                .'请先确认业务归属后再清理。',
            );
        }

        Schema::dropIfExists('agent_applications');
    }

    public function down(): void
    {
        // 不自动恢复：该表无业务用途，如需重建请从数据库结构历史快照导入 DDL。
    }
};
