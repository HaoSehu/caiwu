<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $supplierLegacyColumns = [
        'interface_type',
        'api_url',
        'api_username',
        'api_key',
        'provider_config',
    ];

    /**
     * @var list<string>
     */
    private array $productLegacyColumns = [
        'provision_module',
        'supplier_id',
        'supplier_product_id',
    ];

    public function up(): void
    {
        $this->dropProductLegacyIndexes();
        $this->dropForeignKeysForColumns('products', ['supplier_id']);
        $this->dropColumnsIfPresent('products', $this->productLegacyColumns);
        $this->dropColumnsIfPresent('suppliers', $this->supplierLegacyColumns);
    }

    public function down(): void
    {
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                if (! Schema::hasColumn('suppliers', 'interface_type')) {
                    $table->string('interface_type', 50)->nullable()->after('sort_order');
                }

                if (! Schema::hasColumn('suppliers', 'api_url')) {
                    $table->string('api_url', 255)->nullable()->comment('接口地址')->after('interface_type');
                }

                if (! Schema::hasColumn('suppliers', 'api_username')) {
                    $table->string('api_username', 100)->nullable()->comment('接口用户名')->after('api_url');
                }

                if (! Schema::hasColumn('suppliers', 'api_key')) {
                    $table->string('api_key', 255)->nullable()->comment('接口密钥')->after('api_username');
                }

                if (! Schema::hasColumn('suppliers', 'provider_config')) {
                    $table->longText('provider_config')->nullable()->after('api_key');
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! Schema::hasColumn('products', 'provision_module')) {
                    $table->string('provision_module', 50)->nullable()->comment('开通模块或上游驱动代码')->after('sort_order');
                }

                if (! Schema::hasColumn('products', 'supplier_id')) {
                    $table->unsignedBigInteger('supplier_id')->nullable()->comment('供应商接口ID')->after('auto_setup');
                }

                if (! Schema::hasColumn('products', 'supplier_product_id')) {
                    $table->unsignedBigInteger('supplier_product_id')->nullable()->comment('供应商侧商品ID')->after('supplier_id');
                }
            });

            Schema::table('products', function (Blueprint $table): void {
                if (! $this->indexExists('products', 'products_supplier_id_index') && Schema::hasColumn('products', 'supplier_id')) {
                    $table->index('supplier_id', 'products_supplier_id_index');
                }

                if (
                    ! $this->indexExists('products', 'products_supplier_product_status_id_idx')
                    && $this->hasColumns('products', ['supplier_id', 'supplier_product_id', 'status', 'id'])
                ) {
                    $table->index(['supplier_id', 'supplier_product_id', 'status', 'id'], 'products_supplier_product_status_id_idx');
                }
            });
        }
    }

    private function dropProductLegacyIndexes(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            foreach (['products_supplier_product_status_id_idx', 'products_supplier_id_index'] as $indexName) {
                if ($this->indexExists('products', $indexName)) {
                    $table->dropIndex($indexName);
                }
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumnsIfPresent(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existing = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn($tableName, $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropForeignKeysForColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $database = DB::getDatabaseName();
        $constraintNames = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $tableName)
            ->whereIn('COLUMN_NAME', $columns)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME')
            ->unique()
            ->values()
            ->all();

        if ($constraintNames === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($constraintNames): void {
            foreach ($constraintNames as $constraintName) {
                $table->dropForeign((string) $constraintName);
            }
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $tableName)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasColumns(string $tableName, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }
};
