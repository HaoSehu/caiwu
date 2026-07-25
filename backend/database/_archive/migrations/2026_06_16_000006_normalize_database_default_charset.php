<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TARGET_CHARSET = 'utf8mb4';

    private const TARGET_COLLATION = 'utf8mb4_unicode_ci';

    private const PREVIOUS_CHARSET = 'utf8mb3';

    private const PREVIOUS_COLLATION = 'utf8_general_ci';

    public function up(): void
    {
        $this->assertIndexedCharacterColumnsUseTargetCollation();

        DB::statement(sprintf(
            'ALTER DATABASE %s CHARACTER SET %s COLLATE %s',
            $this->quoteIdentifier(DB::getDatabaseName()),
            self::TARGET_CHARSET,
            self::TARGET_COLLATION
        ));
    }

    public function down(): void
    {
        DB::statement(sprintf(
            'ALTER DATABASE %s CHARACTER SET %s COLLATE %s',
            $this->quoteIdentifier(DB::getDatabaseName()),
            self::PREVIOUS_CHARSET,
            self::PREVIOUS_COLLATION
        ));
    }

    private function assertIndexedCharacterColumnsUseTargetCollation(): void
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT DISTINCT c.TABLE_NAME, c.COLUMN_NAME, c.COLLATION_NAME
            FROM information_schema.COLUMNS c
            INNER JOIN information_schema.STATISTICS s
              ON s.TABLE_SCHEMA = c.TABLE_SCHEMA
             AND s.TABLE_NAME = c.TABLE_NAME
             AND s.COLUMN_NAME = c.COLUMN_NAME
            WHERE c.TABLE_SCHEMA = ?
              AND c.CHARACTER_SET_NAME IS NOT NULL
              AND c.COLLATION_NAME <> ?
            ORDER BY c.TABLE_NAME, c.COLUMN_NAME
            SQL,
            [DB::getDatabaseName(), self::TARGET_COLLATION]
        );

        if ($rows === []) {
            return;
        }

        $columns = array_map(
            fn ($row) => "{$row->TABLE_NAME}.{$row->COLUMN_NAME}({$row->COLLATION_NAME})",
            $rows
        );

        throw new RuntimeException(
            'Refuse to change database default collation while indexed character columns still use non-target collation: '
            .implode(', ', $columns)
        );
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};
