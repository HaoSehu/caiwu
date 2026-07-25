<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('orders', 'product_spec_snapshot')) {
                    $table->string('product_spec_snapshot', 200)->nullable()->after('product_id');
                }
            });

            DB::statement("
                UPDATE orders
                SET product_spec_snapshot = COALESCE(NULLIF(product_spec_snapshot, ''), NULLIF(product_name_snapshot, ''))
            ");
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('invoices', 'product_spec_snapshot')) {
                    $table->string('product_spec_snapshot', 255)->nullable()->after('product_id');
                }
            });

            DB::statement("
                UPDATE invoices
                SET product_spec_snapshot = COALESCE(NULLIF(product_spec_snapshot, ''), NULLIF(product_name_snapshot, ''))
            ");
        }

        $this->cleanupCatalogSetting('instance_spec_catalog');
        $this->cleanupCatalogSetting('cpu_model_catalog');
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'product_spec_snapshot')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('product_spec_snapshot');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'product_spec_snapshot')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropColumn('product_spec_snapshot');
            });
        }
    }

    private function cleanupCatalogSetting(string $itemKey): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $rawValue = DB::table('settings')
            ->where('group_key', 'product')
            ->where('item_key', $itemKey)
            ->value('item_value');

        if (! is_string($rawValue) || trim($rawValue) === '') {
            return;
        }

        $decoded = json_decode($rawValue, true);
        if (! is_array($decoded)) {
            return;
        }

        $normalized = $this->stripProductNames($decoded);

        DB::table('settings')
            ->where('group_key', 'product')
            ->where('item_key', $itemKey)
            ->update([
                'item_value' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
    }

    private function stripProductNames(array $items): array
    {
        foreach ($items as &$item) {
            if (! is_array($item)) {
                continue;
            }

            unset($item['product_name']);

            if (isset($item['bindings']) && is_array($item['bindings'])) {
                foreach ($item['bindings'] as &$binding) {
                    if (! is_array($binding)) {
                        continue;
                    }

                    unset($binding['product_name']);
                }
                unset($binding);
            }

            if (isset($item['models']) && is_array($item['models'])) {
                $item['models'] = $this->stripProductNames($item['models']);
            }
        }
        unset($item);

        return $items;
    }
};
