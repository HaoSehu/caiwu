<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2 清理前缀重复索引（结构整改批 2026-10-10）。
 *
 * 逐组均为「短索引列集是另一索引的前缀」，对等值+范围查询无增益，只产生写放大；
 * 五个索引名在 app/ 内零引用（rg 实证）：
 * - automation_logs.task_key ⊂ automation_logs_unique_rule(task_key,action,object_type,object_id,rule_key)
 * - orders.user_id ⊂ orders_user_status_id_idx(user_id,status,id)
 * - services.user_id ⊂ services_user_status_id_idx(user_id,status,id)
 * - service_upstream_bindings.service_id ⊂ service_upstream_unique(service_id,plugin_id,upstream_service_id)
 * - referral_rewards.invoice_id 与 referral_rewards_invoice_id_unique 完全同列（保留唯一版）
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, index: string, columns: array<int, string>}>
     */
    private array $indexes = [
        ['table' => 'automation_logs', 'index' => 'automation_logs_task_key_index', 'columns' => ['task_key']],
        ['table' => 'orders', 'index' => 'orders_user_id_index', 'columns' => ['user_id']],
        ['table' => 'services', 'index' => 'services_user_id_index', 'columns' => ['user_id']],
        ['table' => 'service_upstream_bindings', 'index' => 'service_upstream_service_idx', 'columns' => ['service_id']],
        ['table' => 'referral_rewards', 'index' => 'referral_rewards_invoice_id_idx', 'columns' => ['invoice_id']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $target) {
            if (! Schema::hasTable($target['table']) || ! Schema::hasIndex($target['table'], $target['index'])) {
                continue;
            }

            Schema::table($target['table'], fn (Blueprint $table) => $table->dropIndex($target['index']));
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $target) {
            if (! Schema::hasTable($target['table']) || Schema::hasIndex($target['table'], $target['index'])) {
                continue;
            }

            Schema::table($target['table'], fn (Blueprint $table) => $table->index($target['columns'], $target['index']));
        }
    }
};
