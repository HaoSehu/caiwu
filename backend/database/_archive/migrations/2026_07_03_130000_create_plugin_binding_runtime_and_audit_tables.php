<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendIntegrationPlugins();
        $this->createPluginBindings();
        $this->createSupplierPluginBindings();
        $this->createProductUpstreamBindings();
        $this->createServiceUpstreamBindings();
        $this->createServiceRuntimeSnapshots();
        $this->createServiceConnectionSnapshots();
        $this->createServiceProvisionAttempts();
        $this->createPluginRuntimeLogs();
        $this->createArchiveAuditLogs();
        $this->extendPaymentTables();
        $this->extendNotificationTables();
    }

    public function down(): void
    {
        $this->dropNotificationExtensions();
        $this->dropPaymentExtensions();
        Schema::dropIfExists('archive_audit_logs');
        Schema::dropIfExists('integration_plugin_runtime_logs');
        Schema::dropIfExists('service_provision_attempts');
        Schema::dropIfExists('service_connection_snapshots');
        Schema::dropIfExists('service_runtime_snapshots');
        Schema::dropIfExists('service_upstream_bindings');
        Schema::dropIfExists('product_upstream_bindings');
        Schema::dropIfExists('supplier_plugin_bindings');
        Schema::dropIfExists('integration_plugin_bindings');
        $this->dropIntegrationPluginExtensions();
    }

    private function extendIntegrationPlugins(): void
    {
        Schema::table('integration_plugins', function (Blueprint $table): void {
            if (! Schema::hasColumn('integration_plugins', 'enabled_at')) {
                $table->timestamp('enabled_at')->nullable()->after('installed_at');
            }
            if (! Schema::hasColumn('integration_plugins', 'disabled_at')) {
                $table->timestamp('disabled_at')->nullable()->after('enabled_at');
            }
            if (! Schema::hasColumn('integration_plugins', 'installed_by')) {
                $table->unsignedBigInteger('installed_by')->nullable()->after('disabled_at');
            }
            if (! Schema::hasColumn('integration_plugins', 'enabled_by')) {
                $table->unsignedBigInteger('enabled_by')->nullable()->after('installed_by');
            }
            if (! Schema::hasColumn('integration_plugins', 'source_hash')) {
                $table->string('source_hash', 128)->nullable()->after('enabled_by');
            }
        });
    }

    private function createPluginBindings(): void
    {
        if (Schema::hasTable('integration_plugin_bindings')) {
            return;
        }

        Schema::create('integration_plugin_bindings', function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 32);
            $table->foreignId('plugin_id')->constrained('integration_plugins')->restrictOnDelete();
            $table->string('binding_type', 50)->comment('global/supplier/product/service/payment/notification/custom');
            $table->string('bindable_type', 120)->default('global');
            $table->unsignedBigInteger('bindable_id')->default(0);
            $table->string('binding_key', 120)->comment('同一对象下的绑定名');
            $table->string('provider_key', 120)->nullable()->comment('外部协议标识快照');
            $table->integer('priority')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->json('config_json')->nullable();
            $table->longText('secret_json')->nullable();
            $table->json('has_secret_json')->nullable();
            $table->json('runtime_policy_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('backfill_batch_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['domain', 'binding_type', 'bindable_type', 'bindable_id', 'binding_key'], 'plugin_bindings_unique');
            $table->index(['plugin_id', 'status'], 'plugin_bindings_plugin_status_idx');
            $table->index(['domain', 'provider_key', 'status'], 'plugin_bindings_domain_provider_status_idx');
            $table->index(['bindable_type', 'bindable_id', 'domain'], 'plugin_bindings_bindable_idx');
            $table->index('backfill_batch_id', 'plugin_bindings_backfill_batch_idx');
        });
    }

    private function createSupplierPluginBindings(): void
    {
        if (Schema::hasTable('supplier_plugin_bindings')) {
            return;
        }

        Schema::create('supplier_plugin_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('plugin_id')->constrained('integration_plugins')->restrictOnDelete();
            $table->string('provider_key', 120);
            $table->string('environment', 30)->default('production');
            $table->unsignedTinyInteger('status')->default(1);
            $table->integer('priority')->default(0);
            $table->string('base_url', 255)->nullable();
            $table->string('account_name', 120)->nullable();
            $table->json('config_json')->nullable();
            $table->longText('secret_json')->nullable();
            $table->json('has_secret_json')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status', 30)->nullable();
            $table->string('last_check_error', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('backfill_batch_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'plugin_id', 'environment'], 'supplier_plugin_unique');
            $table->index(['provider_key', 'status'], 'supplier_plugin_provider_status_idx');
            $table->index(['plugin_id', 'status'], 'supplier_plugin_plugin_status_idx');
            $table->index('backfill_batch_id', 'supplier_plugin_backfill_batch_idx');
        });
    }

    private function createProductUpstreamBindings(): void
    {
        if (Schema::hasTable('product_upstream_bindings')) {
            return;
        }

        Schema::create('product_upstream_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('supplier_plugin_binding_id')->constrained('supplier_plugin_bindings')->restrictOnDelete();
            $table->foreignId('plugin_id')->constrained('integration_plugins')->restrictOnDelete();
            $table->string('provider_key', 120);
            $table->string('upstream_product_id', 120);
            $table->json('upstream_product_snapshot_json')->nullable();
            $table->json('option_schema_json')->nullable();
            $table->json('provision_policy_json')->nullable();
            $table->boolean('auto_setup')->default(false);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_error', 500)->nullable();
            $table->string('backfill_batch_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'supplier_plugin_binding_id', 'upstream_product_id'], 'product_upstream_unique');
            $table->index(['product_id', 'status'], 'product_upstream_product_status_idx');
            $table->index(['provider_key', 'status'], 'product_upstream_provider_status_idx');
            $table->index(['plugin_id', 'status'], 'product_upstream_plugin_status_idx');
            $table->index('backfill_batch_id', 'product_upstream_backfill_batch_idx');
        });
    }

    private function createServiceUpstreamBindings(): void
    {
        if (Schema::hasTable('service_upstream_bindings')) {
            return;
        }

        Schema::create('service_upstream_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('product_upstream_binding_id')->nullable()->constrained('product_upstream_bindings')->nullOnDelete();
            $table->foreignId('supplier_plugin_binding_id')->nullable()->constrained('supplier_plugin_bindings')->nullOnDelete();
            $table->foreignId('plugin_id')->constrained('integration_plugins')->restrictOnDelete();
            $table->string('provider_key', 120);
            $table->string('upstream_service_id', 120);
            $table->string('upstream_account_id', 120)->nullable();
            $table->json('runtime_snapshot_json')->nullable();
            $table->json('connection_snapshot_json')->nullable();
            $table->string('status_snapshot', 60)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_error', 500)->nullable();
            $table->string('backfill_batch_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'plugin_id', 'upstream_service_id'], 'service_upstream_unique');
            $table->index('service_id', 'service_upstream_service_idx');
            $table->index(['provider_key', 'status_snapshot'], 'service_upstream_provider_status_idx');
            $table->index(['plugin_id', 'last_synced_at'], 'service_upstream_plugin_sync_idx');
            $table->index('backfill_batch_id', 'service_upstream_backfill_batch_idx');
        });
    }

    private function createServiceRuntimeSnapshots(): void
    {
        if (Schema::hasTable('service_runtime_snapshots')) {
            return;
        }

        Schema::create('service_runtime_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('service_upstream_binding_id')->nullable()->constrained('service_upstream_bindings')->nullOnDelete();
            $table->foreignId('plugin_id')->nullable()->constrained('integration_plugins')->nullOnDelete();
            $table->string('provider_key', 120)->nullable();
            $table->string('status_key', 60)->nullable();
            $table->string('status_text', 120)->nullable();
            $table->json('resource_json')->nullable();
            $table->json('metrics_json')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('backfill_batch_id', 64)->nullable();
            $table->timestamps();

            $table->unique('service_id', 'service_runtime_service_unique');
            $table->index(['plugin_id', 'synced_at'], 'service_runtime_plugin_synced_idx');
            $table->index(['provider_key', 'status_key'], 'service_runtime_provider_status_idx');
            $table->index('backfill_batch_id', 'service_runtime_backfill_batch_idx');
        });
    }

    private function createServiceConnectionSnapshots(): void
    {
        if (Schema::hasTable('service_connection_snapshots')) {
            return;
        }

        Schema::create('service_connection_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('service_upstream_binding_id')->nullable()->constrained('service_upstream_bindings')->nullOnDelete();
            $table->foreignId('plugin_id')->nullable()->constrained('integration_plugins')->nullOnDelete();
            $table->string('provider_key', 120)->nullable();
            $table->string('connection_type', 60)->default('default');
            $table->string('hostname', 255)->nullable();
            $table->string('ip_address', 120)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->json('connection_json')->nullable();
            $table->longText('secret_json')->nullable();
            $table->json('has_secret_json')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('backfill_batch_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'connection_type'], 'service_connection_service_type_unique');
            $table->index(['plugin_id', 'checked_at'], 'service_connection_plugin_checked_idx');
            $table->index(['provider_key', 'connection_type'], 'service_connection_provider_type_idx');
            $table->index('backfill_batch_id', 'service_connection_backfill_batch_idx');
        });
    }

    private function createServiceProvisionAttempts(): void
    {
        if (Schema::hasTable('service_provision_attempts')) {
            return;
        }

        Schema::create('service_provision_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('service_upstream_binding_id')->nullable()->constrained('service_upstream_bindings')->nullOnDelete();
            $table->foreignId('plugin_id')->nullable()->constrained('integration_plugins')->nullOnDelete();
            $table->string('provider_key', 120)->nullable();
            $table->string('action', 80);
            $table->string('attempt_status', 30);
            $table->string('trace_id', 64)->nullable();
            $table->json('request_meta_json')->nullable();
            $table->json('response_meta_json')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->string('backfill_batch_id', 64)->nullable();
            $table->timestamps();

            $table->index(['service_id', 'action', 'attempted_at'], 'service_attempt_service_action_idx');
            $table->index(['plugin_id', 'attempt_status', 'attempted_at'], 'service_attempt_plugin_status_idx');
            $table->index('trace_id', 'service_attempt_trace_idx');
            $table->index('backfill_batch_id', 'service_attempt_backfill_batch_idx');
        });
    }

    private function createPluginRuntimeLogs(): void
    {
        if (Schema::hasTable('integration_plugin_runtime_logs')) {
            return;
        }

        Schema::create('integration_plugin_runtime_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('trace_id', 64)->nullable();
            $table->string('domain', 32);
            $table->foreignId('plugin_id')->nullable()->constrained('integration_plugins')->nullOnDelete();
            $table->string('plugin_key', 120);
            $table->string('slug', 120);
            $table->string('action', 120);
            $table->unsignedBigInteger('binding_id')->nullable();
            $table->string('bindable_type', 120)->nullable();
            $table->unsignedBigInteger('bindable_id')->nullable();
            $table->string('actor_type', 50)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('status', 30);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->json('request_meta_json')->nullable();
            $table->json('response_meta_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('trace_id', 'plugin_runtime_trace_idx');
            $table->index(['plugin_id', 'created_at'], 'plugin_runtime_plugin_created_idx');
            $table->index(['domain', 'action', 'created_at'], 'plugin_runtime_domain_action_created_idx');
            $table->index(['status', 'created_at'], 'plugin_runtime_status_created_idx');
            $table->index(['bindable_type', 'bindable_id', 'created_at'], 'plugin_runtime_bindable_idx');
        });
    }

    private function createArchiveAuditLogs(): void
    {
        if (Schema::hasTable('archive_audit_logs')) {
            return;
        }

        Schema::create('archive_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id', 64);
            $table->string('table_name', 64);
            $table->string('mode', 30);
            $table->unsignedInteger('row_count')->default(0);
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->string('status', 30);
            $table->string('error_message', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('batch_id', 'archive_batch_idx');
            $table->index(['table_name', 'status', 'created_at'], 'archive_table_status_idx');
        });
    }

    private function extendPaymentTables(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'plugin_id')) {
                $table->unsignedBigInteger('plugin_id')->nullable()->after('invoice_id');
            }
            if (! Schema::hasColumn('payments', 'gateway_key')) {
                $table->string('gateway_key', 120)->nullable()->after('plugin_id');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (! $this->foreignKeyExists('payments', 'payments_plugin_fk')) {
                $table->foreign('plugin_id', 'payments_plugin_fk')->references('id')->on('integration_plugins')->restrictOnDelete();
            }
            if (! $this->indexExists('payments', 'payments_plugin_status_paid_idx')) {
                $table->index(['plugin_id', 'status', 'paid_at'], 'payments_plugin_status_paid_idx');
            }
            if (! $this->indexExists('payments', 'payments_plugin_trade_unique')) {
                $table->unique(['plugin_id', 'gateway_key', 'trade_no'], 'payments_plugin_trade_unique');
            }
        });

        Schema::table('payment_callbacks', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_callbacks', 'plugin_id')) {
                $table->unsignedBigInteger('plugin_id')->nullable()->after('payment_id');
            }
            if (! Schema::hasColumn('payment_callbacks', 'gateway_key')) {
                $table->string('gateway_key', 120)->nullable()->after('plugin_id');
            }
        });

        Schema::table('payment_callbacks', function (Blueprint $table): void {
            if (! $this->foreignKeyExists('payment_callbacks', 'payment_callbacks_plugin_fk')) {
                $table->foreign('plugin_id', 'payment_callbacks_plugin_fk')->references('id')->on('integration_plugins')->nullOnDelete();
            }
            if (! $this->indexExists('payment_callbacks', 'payment_callbacks_plugin_received_idx')) {
                $table->index(['plugin_id', 'received_at'], 'payment_callbacks_plugin_received_idx');
            }
            if (! $this->indexExists('payment_callbacks', 'payment_callbacks_gateway_key_idx')) {
                $table->index(['gateway_key', 'received_at'], 'payment_callbacks_gateway_key_idx');
            }
        });

        Schema::table('gateway_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('gateway_logs', 'plugin_id')) {
                $table->unsignedBigInteger('plugin_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('gateway_logs', 'gateway_key')) {
                $table->string('gateway_key', 120)->nullable()->after('plugin_id');
            }
            if (! Schema::hasColumn('gateway_logs', 'trace_id')) {
                $table->string('trace_id', 64)->nullable()->after('invoice_id');
            }
        });

        Schema::table('gateway_logs', function (Blueprint $table): void {
            if (! $this->foreignKeyExists('gateway_logs', 'gateway_logs_plugin_fk')) {
                $table->foreign('plugin_id', 'gateway_logs_plugin_fk')->references('id')->on('integration_plugins')->nullOnDelete();
            }
            if (! $this->indexExists('gateway_logs', 'gateway_logs_plugin_created_idx')) {
                $table->index(['plugin_id', 'created_at'], 'gateway_logs_plugin_created_idx');
            }
            if (! $this->indexExists('gateway_logs', 'gateway_logs_gateway_key_idx')) {
                $table->index(['gateway_key', 'created_at'], 'gateway_logs_gateway_key_idx');
            }
            if (! $this->indexExists('gateway_logs', 'gateway_logs_trace_idx')) {
                $table->index('trace_id', 'gateway_logs_trace_idx');
            }
        });
    }

    private function extendNotificationTables(): void
    {
        $this->extendLogTableWithPluginFields('notification_logs', afterColumn: 'id', driverColumn: 'channel');
        $this->extendLogTableWithPluginFields('email_logs', afterColumn: 'id');
        $this->extendLogTableWithPluginFields('sms_logs', afterColumn: 'id');
    }

    private function extendLogTableWithPluginFields(string $tableName, string $afterColumn = 'id', ?string $driverColumn = null): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $afterColumn): void {
            if (! Schema::hasColumn($tableName, 'plugin_id')) {
                $table->unsignedBigInteger('plugin_id')->nullable()->after($afterColumn);
            }
            if (! Schema::hasColumn($tableName, 'driver_key')) {
                $table->string('driver_key', 120)->nullable()->after('plugin_id');
            }
            if (! Schema::hasColumn($tableName, 'trace_id')) {
                $table->string('trace_id', 64)->nullable()->after('driver_key');
            }
        });

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $driverColumn): void {
            $prefix = match ($tableName) {
                'notification_logs' => 'notification_logs',
                'email_logs' => 'email_logs',
                'sms_logs' => 'sms_logs',
                default => $tableName,
            };

            if (! $this->foreignKeyExists($tableName, "{$prefix}_plugin_fk")) {
                $table->foreign('plugin_id', "{$prefix}_plugin_fk")->references('id')->on('integration_plugins')->nullOnDelete();
            }
            if (! $this->indexExists($tableName, "{$prefix}_plugin_created_idx")) {
                $table->index(['plugin_id', 'created_at'], "{$prefix}_plugin_created_idx");
            }
            if (! $this->indexExists($tableName, "{$prefix}_driver_created_idx")) {
                $table->index(['driver_key', 'created_at'], "{$prefix}_driver_created_idx");
            }
            if (! $this->indexExists($tableName, "{$prefix}_trace_idx")) {
                $table->index('trace_id', "{$prefix}_trace_idx");
            }
            if ($driverColumn !== null && Schema::hasColumn($tableName, $driverColumn) && ! $this->indexExists($tableName, "{$prefix}_channel_driver_idx")) {
                $table->index([$driverColumn, 'driver_key', 'created_at'], "{$prefix}_channel_driver_idx");
            }
        });
    }

    private function dropPaymentExtensions(): void
    {
        $this->dropColumnSet('gateway_logs', [
            'gateway_logs_plugin_fk',
            'gateway_logs_plugin_created_idx',
            'gateway_logs_gateway_key_idx',
            'gateway_logs_trace_idx',
        ], ['plugin_id', 'gateway_key', 'trace_id']);

        $this->dropColumnSet('payment_callbacks', [
            'payment_callbacks_plugin_fk',
            'payment_callbacks_plugin_received_idx',
            'payment_callbacks_gateway_key_idx',
        ], ['plugin_id', 'gateway_key']);

        $this->dropColumnSet('payments', [
            'payments_plugin_fk',
            'payments_plugin_status_paid_idx',
            'payments_plugin_trade_unique',
        ], ['plugin_id', 'gateway_key']);
    }

    private function dropNotificationExtensions(): void
    {
        foreach (['notification_logs', 'email_logs', 'sms_logs'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $prefix = $tableName;
            $indexes = [
                "{$prefix}_plugin_fk",
                "{$prefix}_plugin_created_idx",
                "{$prefix}_driver_created_idx",
                "{$prefix}_trace_idx",
                "{$prefix}_channel_driver_idx",
            ];
            $this->dropColumnSet($tableName, $indexes, ['plugin_id', 'driver_key', 'trace_id']);
        }
    }

    private function dropIntegrationPluginExtensions(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            return;
        }

        Schema::table('integration_plugins', function (Blueprint $table): void {
            foreach (['enabled_at', 'disabled_at', 'installed_by', 'enabled_by', 'source_hash'] as $column) {
                if (Schema::hasColumn('integration_plugins', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * @param  array<int, string>  $constraintOrIndexNames
     * @param  array<int, string>  $columns
     */
    private function dropColumnSet(string $tableName, array $constraintOrIndexNames, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $constraintOrIndexNames, $columns): void {
            foreach ($constraintOrIndexNames as $name) {
                if ($this->foreignKeyExists($tableName, $name)) {
                    $table->dropForeign($name);

                    continue;
                }

                if ($this->indexExists($tableName, $name)) {
                    $table->dropIndex($name);
                }
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $tableName)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $tableName)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }
};
