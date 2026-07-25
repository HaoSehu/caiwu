<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safe, additive engineering fixes (applied via a dedicated --path so the
 * team's pending product-group-hierarchy migration is NOT triggered).
 *
 *  1. admin_user_roles: add surrogate primary key `id`. This junction table
 *     had no primary key at all (only a composite UNIQUE KEY).
 *  2. invoices.discount: widen DECIMAL(10,2) -> DECIMAL(12,2) so it matches
 *     every other monetary column in the schema.
 *
 * Both changes are non-breaking: existing INSERT/UPDATE paths keep working and
 * all current values fit the new definitions. Guards make the migration
 * idempotent / re-runnable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_user_roles') && ! Schema::hasColumn('admin_user_roles', 'id')) {
            DB::statement('ALTER TABLE `admin_user_roles` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'discount')) {
            DB::statement("ALTER TABLE `invoices` MODIFY `discount` DECIMAL(12,2) NOT NULL DEFAULT '0.00'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'discount')) {
            DB::statement("ALTER TABLE `invoices` MODIFY `discount` DECIMAL(10,2) NOT NULL DEFAULT '0.00'");
        }

        if (Schema::hasTable('admin_user_roles') && Schema::hasColumn('admin_user_roles', 'id')) {
            DB::statement('ALTER TABLE `admin_user_roles` DROP COLUMN `id`');
        }
    }
};
