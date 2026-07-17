<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DOMAIN = 'upstream';

    private const LEGACY_PROVIDER_KEY = 'mofang_finance_api';

    private const ZJMF_PROVIDER_KEY = 'zjmf_finance_api';

    private const LEGACY_SLUG = 'mofang_finance';

    private const ZJMF_SLUG = 'zjmf_finance';

    /** @var array<string, list<string>> */
    private const PROVIDER_KEY_COLUMNS = [
        'integration_plugins' => ['plugin_key'],
        'integration_plugin_bindings' => ['provider_key', 'binding_key'],
        'integration_plugin_runtime_logs' => ['plugin_key'],
        'supplier_plugin_bindings' => ['provider_key'],
        'product_upstream_bindings' => ['provider_key'],
        'service_upstream_bindings' => ['provider_key'],
        'service_runtime_snapshots' => ['provider_key'],
        'service_connection_snapshots' => ['provider_key'],
        'service_provision_attempts' => ['provider_key'],
        'suppliers' => ['interface_type'],
        'products' => ['provision_module'],
    ];

    /** @var array<string, list<string>> */
    private const TEXT_REFERENCE_COLUMNS = [
        'integration_plugin_configs' => ['config_json', 'secret_json', 'has_secret_json'],
        'integration_plugin_bindings' => ['config_json', 'secret_json', 'has_secret_json', 'runtime_policy_json'],
        'supplier_plugin_bindings' => ['config_json', 'secret_json', 'has_secret_json'],
        'product_upstream_bindings' => ['upstream_product_snapshot_json', 'option_schema_json', 'provision_policy_json'],
        'products' => ['config_options', 'purchase_requires'],
        'orders' => ['config_snapshot', 'provision_snapshot_json'],
        'service_upstream_bindings' => ['runtime_snapshot_json', 'connection_snapshot_json'],
        'service_runtime_snapshots' => ['resource_json', 'metrics_json', 'snapshot_json'],
        'service_connection_snapshots' => ['connection_json', 'secret_json', 'has_secret_json'],
        'service_provision_attempts' => ['request_meta_json', 'response_meta_json'],
        'service_lifecycle_logs' => ['payload_json'],
        'service_operation_logs' => ['request_payload_json', 'response_payload_json'],
        'service_remote_snapshots' => ['snapshot_payload_json'],
        'integration_plugin_runtime_logs' => ['request_meta_json', 'response_meta_json'],
        'settings' => ['item_key', 'item_value'],
        'operation_logs' => ['action', 'module', 'context'],
        'services' => ['provision_data'],
        'schedule_task_runs' => ['summary'],
        'schedule_run_logs' => ['context'],
        'automation_logs' => ['meta'],
    ];

    /** @var array<string, string> */
    private const TASK_KEYS = [
        'refresh-mofang-finance-auth' => 'refresh-zjmf-finance-auth',
        'sync-mofang-finance-inventory-and-services' => 'sync-zjmf-finance-inventory-and-services',
    ];

    /** @var array<string, string> */
    private const TASK_TITLES = [
        '魔方财务认证刷新' => 'ZJMF 财务认证刷新',
        '魔方财务库存与服务同步' => 'ZJMF 财务库存与服务同步',
    ];

    public function up(): void
    {
        $this->replaceIdentity(
            self::LEGACY_PROVIDER_KEY,
            self::ZJMF_PROVIDER_KEY,
            self::LEGACY_SLUG,
            self::ZJMF_SLUG,
            [
                'name' => 'ZJMF 财务接口',
                'provider_class' => 'Caiwu\\Plugins\\Servers\\ZjmfFinance\\Providers\\ZjmfFinanceServiceProvider',
                'entry_class' => 'Caiwu\\Plugins\\Servers\\ZjmfFinance\\ZjmfFinancePlugin',
            ],
        );
    }

    public function down(): void
    {
        throw new LogicException('ZJMF 插件标识迁移不可回滚。');
    }

    /**
     * @param  array{name: string, provider_class: string, entry_class: string}  $metadata
     */
    private function replaceIdentity(
        string $fromProviderKey,
        string $toProviderKey,
        string $fromSlug,
        string $toSlug,
        array $metadata,
    ): void {
        DB::transaction(function () use ($fromProviderKey, $toProviderKey, $fromSlug, $toSlug, $metadata): void {
            $this->replacePluginIdentity($fromProviderKey, $toProviderKey, $fromSlug, $toSlug, $metadata);
            $this->replaceExactValues(self::PROVIDER_KEY_COLUMNS, $fromProviderKey, $toProviderKey);
            $this->replaceContainingValues(self::PROVIDER_KEY_COLUMNS, $fromSlug, $toSlug);
            $this->replaceExactValue('integration_plugin_runtime_logs', 'slug', $fromSlug, $toSlug);
            $this->replaceTextReferences($fromProviderKey, $toProviderKey);
            $this->replaceTextReferences($fromSlug, $toSlug);
            $this->replaceTaskReferences($fromProviderKey);
        });
    }

    /**
     * @param  array{name: string, provider_class: string, entry_class: string}  $metadata
     */
    private function replacePluginIdentity(
        string $fromProviderKey,
        string $toProviderKey,
        string $fromSlug,
        string $toSlug,
        array $metadata,
    ): void {
        if (! Schema::hasTable('integration_plugins')) {
            return;
        }

        $plugins = DB::table('integration_plugins')
            ->where('domain', self::DOMAIN)
            ->where(function ($query) use ($fromProviderKey, $toProviderKey, $fromSlug, $toSlug): void {
                $query
                    ->where('slug', $fromSlug)
                    ->orWhere('slug', $toSlug)
                    ->orWhere('plugin_key', $fromProviderKey)
                    ->orWhere('plugin_key', $toProviderKey);
            })
            ->lockForUpdate()
            ->get(['id', 'slug', 'plugin_key']);

        $legacyPlugins = $plugins->filter(
            fn (object $plugin): bool => $plugin->slug === $fromSlug || $plugin->plugin_key === $fromProviderKey,
        );
        $targetPlugins = $plugins->filter(
            fn (object $plugin): bool => $plugin->slug === $toSlug || $plugin->plugin_key === $toProviderKey,
        );

        if ($legacyPlugins->count() > 1 || $targetPlugins->count() > 1) {
            throw new RuntimeException('ZJMF 插件标识存在冲突，拒绝自动迁移。');
        }

        if ($legacyPlugins->isEmpty() && $targetPlugins->isEmpty()) {
            return;
        }

        $targetPluginId = (int) ($targetPlugins->first()?->id ?? $legacyPlugins->first()?->id);

        if ($legacyPlugins->isNotEmpty() && $targetPlugins->isNotEmpty()) {
            $legacyPluginId = (int) $legacyPlugins->first()->id;
            $this->replacePluginReferences($legacyPluginId, $targetPluginId);

            DB::table('integration_plugins')
                ->where('id', $legacyPluginId)
                ->delete();
        }

        DB::table('integration_plugins')
            ->where('id', $targetPluginId)
            ->update(array_merge($metadata, [
                'slug' => $toSlug,
                'plugin_key' => $toProviderKey,
                'updated_at' => now(),
            ]));
    }

    private function replacePluginReferences(int $legacyPluginId, int $targetPluginId): void
    {
        $references = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('REFERENCED_TABLE_NAME', 'integration_plugins')
            ->get(['TABLE_NAME', 'COLUMN_NAME']);

        foreach ($references as $reference) {
            DB::table($reference->TABLE_NAME)
                ->where($reference->COLUMN_NAME, $legacyPluginId)
                ->update([$reference->COLUMN_NAME => $targetPluginId]);
        }
    }

    /**
     * @param  array<string, list<string>>  $columnsByTable
     */
    private function replaceExactValues(array $columnsByTable, string $from, string $to): void
    {
        foreach ($columnsByTable as $table => $columns) {
            foreach ($columns as $column) {
                $this->replaceExactValue($table, $column, $from, $to);
            }
        }
    }

    /**
     * @param  array<string, list<string>>  $columnsByTable
     */
    private function replaceContainingValues(array $columnsByTable, string $from, string $to): void
    {
        foreach ($columnsByTable as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->where($column, 'like', '%'.$from.'%')
                    ->update([
                        $column => DB::raw(sprintf(
                            "REPLACE(`%s`, '%s', '%s')",
                            $column,
                            str_replace("'", "''", $from),
                            str_replace("'", "''", $to),
                        )),
                    ]);
            }
        }
    }

    private function replaceExactValue(string $table, string $column, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where($column, $from)
            ->update([$column => $to]);
    }

    private function replaceTextReferences(string $from, string $to): void
    {
        foreach (self::TEXT_REFERENCE_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->where($column, 'like', '%'.$from.'%')
                    ->update([
                        $column => DB::raw(sprintf(
                            "REPLACE(`%s`, '%s', '%s')",
                            $column,
                            str_replace("'", "''", $from),
                            str_replace("'", "''", $to),
                        )),
                    ]);
            }
        }
    }

    private function replaceTaskReferences(string $fromProviderKey): void
    {
        $taskKeys = $fromProviderKey === self::LEGACY_PROVIDER_KEY
            ? self::TASK_KEYS
            : array_flip(self::TASK_KEYS);
        $taskTitles = $fromProviderKey === self::LEGACY_PROVIDER_KEY
            ? self::TASK_TITLES
            : array_flip(self::TASK_TITLES);

        foreach ($taskKeys as $fromTaskKey => $toTaskKey) {
            $this->assertTaskKeyCanChange($fromTaskKey, $toTaskKey);
            $this->replaceExactValue('schedule_task_runs', 'task_key', $fromTaskKey, $toTaskKey);
            $this->replaceExactValue('schedule_run_logs', 'task_name', $fromTaskKey, $toTaskKey);
            $this->replaceExactValue('automation_logs', 'task_key', $fromTaskKey, $toTaskKey);
            $this->replaceTextReferences($fromTaskKey, $toTaskKey);
        }

        foreach ($taskTitles as $fromTitle => $toTitle) {
            $this->replaceExactValue('schedule_task_runs', 'task_name', $fromTitle, $toTitle);
            $this->replaceExactValue('schedule_run_logs', 'task_name', $fromTitle, $toTitle);
            $this->replaceTextReferences($fromTitle, $toTitle);
        }
    }

    private function assertTaskKeyCanChange(string $fromTaskKey, string $toTaskKey): void
    {
        if (Schema::hasTable('schedule_task_runs')) {
            $scheduleRunConflict = DB::table('schedule_task_runs as source')
                ->join('schedule_task_runs as target', function ($join) use ($toTaskKey): void {
                    $join
                        ->on('target.schedule_tick_id', '=', 'source.schedule_tick_id')
                        ->on('target.source', '=', 'source.source')
                        ->where('target.task_key', $toTaskKey);
                })
                ->where('source.task_key', $fromTaskKey)
                ->exists();

            if ($scheduleRunConflict) {
                throw new RuntimeException('ZJMF 调度运行记录存在冲突，拒绝自动迁移。');
            }
        }

        if (! Schema::hasTable('automation_logs')) {
            return;
        }

        $automationLogConflict = DB::table('automation_logs as source')
            ->join('automation_logs as target', function ($join) use ($toTaskKey): void {
                $join
                    ->on('target.action', '=', 'source.action')
                    ->on('target.object_type', '=', 'source.object_type')
                    ->on('target.object_id', '=', 'source.object_id')
                    ->on('target.rule_key', '=', 'source.rule_key')
                    ->where('target.task_key', $toTaskKey);
            })
            ->where('source.task_key', $fromTaskKey)
            ->exists();

        if ($automationLogConflict) {
            throw new RuntimeException('ZJMF 自动化日志存在冲突，拒绝自动迁移。');
        }
    }
};
