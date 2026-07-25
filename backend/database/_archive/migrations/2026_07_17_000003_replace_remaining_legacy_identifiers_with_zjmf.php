<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const TEXT_TYPES = [
        'char',
        'varchar',
        'tinytext',
        'text',
        'mediumtext',
        'longtext',
        'json',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $columns = DB::table('information_schema.COLUMNS')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->whereIn('DATA_TYPE', self::TEXT_TYPES)
                ->orderBy('TABLE_NAME')
                ->orderBy('ORDINAL_POSITION')
                ->get(['TABLE_NAME', 'COLUMN_NAME']);

            foreach ($columns as $column) {
                $field = $this->identifier($column->COLUMN_NAME);

                DB::table($column->TABLE_NAME)
                    ->where($column->COLUMN_NAME, 'like', '%mofang%')
                    ->orWhere($column->COLUMN_NAME, 'like', '%魔方%')
                    ->update([
                        $column->COLUMN_NAME => DB::raw(
                            "REPLACE(REPLACE(`{$field}`, 'mofang', 'zjmf'), '魔方', 'ZJMF')",
                        ),
                    ]);
            }
        });
    }

    public function down(): void
    {
        throw new LogicException('ZJMF 全库标识迁移不可回滚。');
    }

    private function identifier(string $value): string
    {
        return str_replace('`', '``', $value);
    }
};
