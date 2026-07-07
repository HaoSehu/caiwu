<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BUSINESS_VALUES = [
        'cloud_server',
        'game_cloud',
        'cloud_desktop',
        'bare_metal',
        'cdn',
        'other',
        'physical_machine',
        'web_hosting',
    ];

    private const LEGACY_VALUE_MAP = [
        'vps' => 'cloud_server',
        'dedicated' => 'game_cloud',
        'domain' => 'cloud_desktop',
        'hosting' => 'web_hosting',
        'type_iwjqnj' => 'bare_metal',
        'type_ipragu' => 'other',
        'type_tgynng' => 'physical_machine',
        'type_1' => 'web_hosting',
    ];

    private const LEGACY_MENU_CODE_MAP = [
        'vps' => 'cloud_server',
        'dedicated' => 'game_cloud',
        'domain' => 'cloud_desktop',
        'hosting' => 'web_hosting',
        'other' => 'cdn',
        'type_iwjqnj' => 'bare_metal',
        'type_ipragu' => 'other',
        'type_tgynng' => 'physical_machine',
        'type_1' => 'web_hosting',
    ];

    public function up(): void
    {
        $this->backfillProducts();
        $this->backfillSnapshotColumn('orders', 'product_type_snapshot');
        $this->backfillSnapshotColumn('invoices', 'product_type_snapshot');
    }

    public function down(): void
    {
        // The old menu-code product_type values are intentionally not restored.
    }

    private function backfillProducts(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'product_type')) {
            return;
        }

        $hasServiceTypeCode = Schema::hasColumn('products', 'service_type_code');
        $hasFirstProductGroupId = Schema::hasColumn('products', 'first_product_group_id')
            && Schema::hasTable('first_product_groups');

        $firstGroups = $hasFirstProductGroupId
            ? DB::table('first_product_groups')
                ->select(['id', 'code', 'product_type'])
                ->get()
                ->keyBy('id')
            : collect();

        $columns = ['id', 'product_type'];
        if ($hasServiceTypeCode) {
            $columns[] = 'service_type_code';
        }
        if ($hasFirstProductGroupId) {
            $columns[] = 'first_product_group_id';
        }

        DB::table('products')
            ->select($columns)
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($firstGroups, $hasServiceTypeCode, $hasFirstProductGroupId): void {
                foreach ($products as $product) {
                    $firstGroup = $hasFirstProductGroupId
                        ? $firstGroups->get((int) ($product->first_product_group_id ?? 0))
                        : null;
                    $businessType = $firstGroup
                        ? $this->businessTypeFromFirstGroup($firstGroup)
                        : $this->businessType((string) ($product->product_type ?? $product->service_type_code ?? ''));

                    $payload = [];
                    if ((string) ($product->product_type ?? '') !== $businessType) {
                        $payload['product_type'] = $businessType;
                    }

                    if ($hasServiceTypeCode && (string) ($product->service_type_code ?? '') !== $businessType) {
                        $payload['service_type_code'] = $businessType;
                    }

                    if ($payload !== []) {
                        DB::table('products')
                            ->where('id', (int) $product->id)
                            ->update($payload);
                    }
                }
            }, 'id');
    }

    private function backfillSnapshotColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->whereNotNull($column)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $current = trim((string) ($row->{$column} ?? ''));
                    if ($current === '') {
                        continue;
                    }

                    $businessType = $this->businessTypeFromMenuCode($current);
                    if ($businessType === $current) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', (int) $row->id)
                        ->update([$column => $businessType]);
                }
            }, 'id');
    }

    private function businessTypeFromFirstGroup(object $firstGroup): string
    {
        $productType = trim((string) ($firstGroup->product_type ?? ''));
        if ($productType !== '') {
            return $this->businessType($productType);
        }

        return $this->businessTypeFromMenuCode((string) ($firstGroup->code ?? ''));
    }

    private function businessType(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'other';
        }

        if (in_array($value, self::BUSINESS_VALUES, true)) {
            return $value;
        }

        return self::LEGACY_VALUE_MAP[$value] ?? 'other';
    }

    private function businessTypeFromMenuCode(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'other';
        }

        return self::LEGACY_MENU_CODE_MAP[$value] ?? $this->businessType($value);
    }
};
