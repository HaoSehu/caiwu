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
        $this->addProductsIndexes();
        $this->addForeignKeys();
    }

    public function down(): void
    {
        $drops = [
            'invoices' => ['fk_invoices_order_id'],
            'tickets' => ['fk_tickets_user_id'],
            'products' => [
                'fk_products_product_group_id',
                'fk_products_first_product_group_id',
                'fk_products_second_product_group_id',
                'fk_products_third_product_group_id',
            ],
            'second_product_groups' => ['fk_second_product_groups_first_product_group_id'],
            'third_product_groups' => ['fk_third_product_groups_second_product_group_id'],
        ];

        foreach ($drops as $tableName => $constraints) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $constraints): void {
                foreach ($constraints as $constraint) {
                    if ($this->hasForeign($tableName, $constraint)) {
                        $table->dropForeign($constraint);
                    }
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                foreach ([
                    'idx_products_second_product_group_id',
                    'idx_products_third_product_group_id',
                ] as $indexName) {
                    if ($this->hasIndex('products', $indexName)) {
                        $table->dropIndex($indexName);
                    }
                }
            });
        }
    }

    private function addProductsIndexes(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (
                Schema::hasColumn('products', 'second_product_group_id')
                && ! $this->hasIndex('products', 'idx_products_second_product_group_id')
            ) {
                $table->index('second_product_group_id', 'idx_products_second_product_group_id');
            }

            if (
                Schema::hasColumn('products', 'third_product_group_id')
                && ! $this->hasIndex('products', 'idx_products_third_product_group_id')
            ) {
                $table->index('third_product_group_id', 'idx_products_third_product_group_id');
            }
        });
    }

    private function addForeignKeys(): void
    {
        if ($this->hasTableColumns('invoices', ['order_id']) && Schema::hasTable('orders')) {
            Schema::table('invoices', function (Blueprint $table): void {
                if (! $this->hasForeign('invoices', 'fk_invoices_order_id')) {
                    $table->foreign('order_id', 'fk_invoices_order_id')
                        ->references('id')->on('orders')
                        ->restrictOnDelete();
                }
            });
        }

        if ($this->hasTableColumns('tickets', ['user_id']) && Schema::hasTable('users')) {
            Schema::table('tickets', function (Blueprint $table): void {
                if (! $this->hasForeign('tickets', 'fk_tickets_user_id')) {
                    $table->foreign('user_id', 'fk_tickets_user_id')
                        ->references('id')->on('users')
                        ->restrictOnDelete();
                }
            });
        }

        if (
            $this->hasTableColumns('second_product_groups', ['first_product_group_id'])
            && Schema::hasTable('first_product_groups')
        ) {
            Schema::table('second_product_groups', function (Blueprint $table): void {
                if (! $this->hasForeign('second_product_groups', 'fk_second_product_groups_first_product_group_id')) {
                    $table->foreign('first_product_group_id', 'fk_second_product_groups_first_product_group_id')
                        ->references('id')->on('first_product_groups')
                        ->restrictOnDelete();
                }
            });
        }

        if (
            $this->hasTableColumns('third_product_groups', ['second_product_group_id'])
            && Schema::hasTable('second_product_groups')
        ) {
            Schema::table('third_product_groups', function (Blueprint $table): void {
                if (! $this->hasForeign('third_product_groups', 'fk_third_product_groups_second_product_group_id')) {
                    $table->foreign('second_product_group_id', 'fk_third_product_groups_second_product_group_id')
                        ->references('id')->on('second_product_groups')
                        ->restrictOnDelete();
                }
            });
        }

        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (
                Schema::hasColumn('products', 'product_group_id')
                && Schema::hasTable('product_groups')
                && ! $this->hasForeign('products', 'fk_products_product_group_id')
            ) {
                $table->foreign('product_group_id', 'fk_products_product_group_id')
                    ->references('id')->on('product_groups')
                    ->restrictOnDelete();
            }

            if (
                Schema::hasColumn('products', 'first_product_group_id')
                && Schema::hasTable('first_product_groups')
                && ! $this->hasForeign('products', 'fk_products_first_product_group_id')
            ) {
                $table->foreign('first_product_group_id', 'fk_products_first_product_group_id')
                    ->references('id')->on('first_product_groups')
                    ->restrictOnDelete();
            }

            if (
                Schema::hasColumn('products', 'second_product_group_id')
                && Schema::hasTable('second_product_groups')
                && ! $this->hasForeign('products', 'fk_products_second_product_group_id')
            ) {
                $table->foreign('second_product_group_id', 'fk_products_second_product_group_id')
                    ->references('id')->on('second_product_groups')
                    ->restrictOnDelete();
            }

            if (
                Schema::hasColumn('products', 'third_product_group_id')
                && Schema::hasTable('third_product_groups')
                && ! $this->hasForeign('products', 'fk_products_third_product_group_id')
            ) {
                $table->foreign('third_product_group_id', 'fk_products_third_product_group_id')
                    ->references('id')->on('third_product_groups')
                    ->restrictOnDelete();
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasTableColumns(string $tableName, array $columns): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }

    private function hasForeign(string $tableName, string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', Schema::getConnection()->getDatabaseName())
            ->where('table_name', $tableName)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', Schema::getConnection()->getDatabaseName())
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }
};
