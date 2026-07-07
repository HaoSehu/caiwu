<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_PRODUCT_TYPE = 'other';

    private const PRODUCT_TYPES = [
        'cloud_server',
        'game_cloud',
        'cloud_desktop',
        'bare_metal',
        'cdn',
        'other',
        'physical_machine',
        'web_hosting',
    ];

    private const LEGACY_MAP = [
        'vps' => 'cloud_server',
        'dedicated' => 'game_cloud',
        'domain' => 'cloud_desktop',
        'type_iwjqnj' => 'bare_metal',
        'other' => 'cdn',
        'type_ipragu' => 'other',
        'type_tgynng' => 'physical_machine',
        'type_1' => 'web_hosting',
        'hosting' => 'web_hosting',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('first_product_groups')) {
            return;
        }

        if (! Schema::hasColumn('first_product_groups', 'product_type')) {
            Schema::table('first_product_groups', function (Blueprint $table): void {
                $table->string('product_type', 50)->nullable()->index('idx_first_product_groups_product_type');
            });
        }

        DB::table('first_product_groups')
            ->select(['id', 'code', 'legacy_product_type', 'product_type'])
            ->orderBy('id')
            ->get()
            ->each(function (object $group): void {
                DB::table('first_product_groups')
                    ->where('id', $group->id)
                    ->update([
                        'product_type' => $this->resolveProductType(
                            $group->product_type ?? null,
                            $group->code ?? null,
                            $group->legacy_product_type ?? null
                        ),
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('first_product_groups') || ! Schema::hasColumn('first_product_groups', 'product_type')) {
            return;
        }

        Schema::table('first_product_groups', function (Blueprint $table): void {
            try {
                $table->dropIndex('idx_first_product_groups_product_type');
            } catch (Throwable) {
                // Index may not exist on partially migrated databases.
            }

            $table->dropColumn('product_type');
        });
    }

    private function resolveProductType(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value === '') {
                continue;
            }

            if (in_array($value, self::PRODUCT_TYPES, true)) {
                return $value;
            }

            if (isset(self::LEGACY_MAP[$value])) {
                return self::LEGACY_MAP[$value];
            }
        }

        return self::DEFAULT_PRODUCT_TYPE;
    }
};
