<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_groups')) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW `first_product_groups` AS
            SELECT
                `id`,
                `code`,
                `name`,
                `slug`,
                `description`,
                `icon`,
                `banner_image`,
                `sort_order`,
                `is_visible`,
                `is_system`,
                `legacy_product_type`,
                `product_type`,
                `level`,
                `created_at`,
                `updated_at`
            FROM `product_groups`
            WHERE `level` = 1
            WITH CASCADED CHECK OPTION
        SQL);
    }

    public function down(): void
    {
        // Do not restore the unsafe placeholder view this migration repairs.
    }
};
