<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductionMigrationSafetyTest extends TestCase
{
    public function test_candidate_migrations_do_not_destroy_mapping_or_identity_data(): void
    {
        $migrationRoot = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        $mappingMigration = file_get_contents($migrationRoot.DIRECTORY_SEPARATOR.'2026_07_21_000002_drop_legacy_product_group_mapping_columns.php');
        $identityMigration = file_get_contents($migrationRoot.DIRECTORY_SEPARATOR.'2026_07_20_002550_replace_id_card_encrypted_with_plaintext.php');

        $this->assertIsString($mappingMigration);
        $this->assertIsString($identityMigration);
        $this->assertStringNotContainsString('dropColumn', $mappingMigration);
        $this->assertStringNotContainsString('DB::table', $identityMigration);
        $this->assertStringNotContainsString('randomIdCard', $identityMigration);
    }
}
