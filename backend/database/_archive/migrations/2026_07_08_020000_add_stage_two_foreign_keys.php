<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<array{table: string, column: string, references: string, on_delete: string}>
     */
    private array $foreignKeys = [
        ['table' => 'account_transactions', 'column' => 'user_id', 'references' => 'users', 'on_delete' => 'RESTRICT'],
        ['table' => 'admin_user_roles', 'column' => 'admin_user_id', 'references' => 'admin_users', 'on_delete' => 'CASCADE'],
        ['table' => 'admin_user_roles', 'column' => 'role_id', 'references' => 'roles', 'on_delete' => 'RESTRICT'],
        ['table' => 'admin_users', 'column' => 'role_id', 'references' => 'roles', 'on_delete' => 'RESTRICT'],
        ['table' => 'content_articles', 'column' => 'category_id', 'references' => 'content_categories', 'on_delete' => 'SET NULL'],
        ['table' => 'coupon_campaigns', 'column' => 'last_coupon_id', 'references' => 'coupons', 'on_delete' => 'SET NULL'],
        ['table' => 'coupons', 'column' => 'coupon_campaign_id', 'references' => 'coupon_campaigns', 'on_delete' => 'SET NULL'],
        ['table' => 'gateway_logs', 'column' => 'invoice_id', 'references' => 'invoices', 'on_delete' => 'SET NULL'],
        ['table' => 'invoices', 'column' => 'service_id', 'references' => 'services', 'on_delete' => 'SET NULL'],
        ['table' => 'invoices', 'column' => 'coupon_id', 'references' => 'coupons', 'on_delete' => 'SET NULL'],
        ['table' => 'notice_reads', 'column' => 'user_id', 'references' => 'users', 'on_delete' => 'CASCADE'],
        ['table' => 'notice_reads', 'column' => 'article_id', 'references' => 'content_articles', 'on_delete' => 'CASCADE'],
        ['table' => 'orders', 'column' => 'user_id', 'references' => 'users', 'on_delete' => 'RESTRICT'],
        ['table' => 'orders', 'column' => 'product_id', 'references' => 'products', 'on_delete' => 'RESTRICT'],
        ['table' => 'orders', 'column' => 'service_id', 'references' => 'services', 'on_delete' => 'SET NULL'],
        ['table' => 'orders', 'column' => 'coupon_id', 'references' => 'coupons', 'on_delete' => 'SET NULL'],
        ['table' => 'orders', 'column' => 'user_coupon_id', 'references' => 'user_coupons', 'on_delete' => 'SET NULL'],
        ['table' => 'payments', 'column' => 'order_id', 'references' => 'orders', 'on_delete' => 'SET NULL'],
        ['table' => 'referral_account_logs', 'column' => 'user_id', 'references' => 'users', 'on_delete' => 'RESTRICT'],
        ['table' => 'referral_rewards', 'column' => 'referrer_user_id', 'references' => 'users', 'on_delete' => 'RESTRICT'],
        ['table' => 'referral_rewards', 'column' => 'referred_user_id', 'references' => 'users', 'on_delete' => 'RESTRICT'],
        ['table' => 'referral_rewards', 'column' => 'order_id', 'references' => 'orders', 'on_delete' => 'RESTRICT'],
        ['table' => 'referral_rewards', 'column' => 'invoice_id', 'references' => 'invoices', 'on_delete' => 'SET NULL'],
        ['table' => 'referral_rewards', 'column' => 'product_id', 'references' => 'products', 'on_delete' => 'SET NULL'],
        ['table' => 'referral_withdrawals', 'column' => 'user_id', 'references' => 'users', 'on_delete' => 'RESTRICT'],
        ['table' => 'services', 'column' => 'order_id', 'references' => 'orders', 'on_delete' => 'SET NULL'],
        ['table' => 'sessions', 'column' => 'user_id', 'references' => 'users', 'on_delete' => 'CASCADE'],
        ['table' => 'ticket_replies', 'column' => 'quote_reply_id', 'references' => 'ticket_replies', 'on_delete' => 'SET NULL'],
        ['table' => 'tickets', 'column' => 'service_id', 'references' => 'services', 'on_delete' => 'SET NULL'],
        ['table' => 'tickets', 'column' => 'assignee_id', 'references' => 'admin_users', 'on_delete' => 'SET NULL'],
        ['table' => 'user_coupons', 'column' => 'user_id', 'references' => 'users', 'on_delete' => 'RESTRICT'],
        ['table' => 'user_notifications', 'column' => 'user_id', 'references' => 'users', 'on_delete' => 'CASCADE'],
        ['table' => 'users', 'column' => 'referrer_user_id', 'references' => 'users', 'on_delete' => 'SET NULL'],
        ['table' => 'users', 'column' => 'member_level_id', 'references' => 'member_levels', 'on_delete' => 'SET NULL'],
        ['table' => 'verification_histories', 'column' => 'user_id', 'references' => 'users', 'on_delete' => 'RESTRICT'],
    ];

    public function up(): void
    {
        foreach ($this->foreignKeys as $foreignKey) {
            if (! $this->hasTableAndColumns($foreignKey)) {
                continue;
            }

            if ($this->hasForeignKey($foreignKey)) {
                continue;
            }

            $this->assertNoOrphans($foreignKey);
            $this->ensureLeftPrefixIndex($foreignKey);
            $this->addForeignKey($foreignKey);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->foreignKeys) as $foreignKey) {
            if (! $this->hasTableAndColumns($foreignKey)) {
                continue;
            }

            $constraint = $this->constraintName($foreignKey);
            if ($this->hasConstraint($foreignKey['table'], $constraint)) {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                    $foreignKey['table'],
                    $constraint
                ));
            }
        }
    }

    /**
     * @param  array{table: string, column: string, references: string, on_delete: string}  $foreignKey
     */
    private function hasTableAndColumns(array $foreignKey): bool
    {
        return $this->hasTable($foreignKey['table'])
            && $this->hasTable($foreignKey['references'])
            && $this->hasColumn($foreignKey['table'], $foreignKey['column'])
            && $this->hasColumn($foreignKey['references'], 'id');
    }

    /**
     * @param  array{table: string, column: string, references: string, on_delete: string}  $foreignKey
     */
    private function assertNoOrphans(array $foreignKey): void
    {
        $count = (int) DB::table($foreignKey['table'].' as child')
            ->leftJoin($foreignKey['references'].' as parent', 'child.'.$foreignKey['column'], '=', 'parent.id')
            ->whereNotNull('child.'.$foreignKey['column'])
            ->whereNull('parent.id')
            ->count();

        if ($count > 0) {
            throw new RuntimeException(sprintf(
                'Cannot add foreign key %s.%s -> %s.id: %d orphan rows found.',
                $foreignKey['table'],
                $foreignKey['column'],
                $foreignKey['references'],
                $count
            ));
        }
    }

    /**
     * @param  array{table: string, column: string, references: string, on_delete: string}  $foreignKey
     */
    private function ensureLeftPrefixIndex(array $foreignKey): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $foreignKey['table'])
            ->where('seq_in_index', 1)
            ->where('column_name', $foreignKey['column'])
            ->exists();

        if ($exists) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD INDEX `%s` (`%s`)',
            $foreignKey['table'],
            $this->indexName($foreignKey),
            $foreignKey['column']
        ));
    }

    /**
     * @param  array{table: string, column: string, references: string, on_delete: string}  $foreignKey
     */
    private function addForeignKey(array $foreignKey): void
    {
        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`id`) ON DELETE %s ON UPDATE NO ACTION',
            $foreignKey['table'],
            $this->constraintName($foreignKey),
            $foreignKey['column'],
            $foreignKey['references'],
            $foreignKey['on_delete']
        ));
    }

    /**
     * @param  array{table: string, column: string, references: string, on_delete: string}  $foreignKey
     */
    private function hasForeignKey(array $foreignKey): bool
    {
        return DB::table('information_schema.key_column_usage')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $foreignKey['table'])
            ->where('column_name', $foreignKey['column'])
            ->where('referenced_table_name', $foreignKey['references'])
            ->where('referenced_column_name', 'id')
            ->exists();
    }

    private function hasConstraint(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }

    private function hasTable(string $table): bool
    {
        return DB::table('information_schema.tables')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->exists();
    }

    private function hasColumn(string $table, string $column): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->exists();
    }

    /**
     * @param  array{table: string, column: string, references: string, on_delete: string}  $foreignKey
     */
    private function constraintName(array $foreignKey): string
    {
        return 'fk_stage2_'.$foreignKey['table'].'_'.$foreignKey['column'];
    }

    /**
     * @param  array{table: string, column: string, references: string, on_delete: string}  $foreignKey
     */
    private function indexName(array $foreignKey): string
    {
        return 'idx_stage2_'.$foreignKey['table'].'_'.$foreignKey['column'];
    }
};
