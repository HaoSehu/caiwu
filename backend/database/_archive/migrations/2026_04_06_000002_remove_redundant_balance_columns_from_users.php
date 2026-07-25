<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $legacyColumns = [
        'balance',
        'credit_limit',
        'referral_frozen_amount',
        'referral_available_amount',
        'referral_withdrawing_amount',
        'referral_withdrawn_amount',
    ];

    public function up(): void
    {
        $existingLegacyColumns = $this->getExistingLegacyColumns();

        if (count($existingLegacyColumns) === count($this->legacyColumns)) {
            DB::statement(<<<'SQL'
                UPDATE user_accounts ua
                INNER JOIN users u ON u.id = ua.user_id
                SET
                    ua.cash_balance = u.balance,
                    ua.credit_limit = u.credit_limit,
                    ua.referral_frozen_balance = u.referral_frozen_amount,
                    ua.referral_available_balance = u.referral_available_amount,
                    ua.referral_pending_withdrawal_balance = u.referral_withdrawing_amount,
                    ua.referral_withdrawn_balance = u.referral_withdrawn_amount,
                    ua.updated_at = CURRENT_TIMESTAMP
                WHERE
                    ua.cash_balance <> u.balance
                    OR ua.credit_limit <> u.credit_limit
                    OR ua.referral_frozen_balance <> u.referral_frozen_amount
                    OR ua.referral_available_balance <> u.referral_available_amount
                    OR ua.referral_pending_withdrawal_balance <> u.referral_withdrawing_amount
                    OR ua.referral_withdrawn_balance <> u.referral_withdrawn_amount
            SQL);
        }

        if ($existingLegacyColumns !== []) {
            Schema::table('users', function (Blueprint $table) use ($existingLegacyColumns) {
                $table->dropColumn($existingLegacyColumns);
            });
        }
    }

    public function down(): void
    {
        $missingLegacyColumns = array_values(array_filter(
            $this->legacyColumns,
            fn (string $column): bool => ! Schema::hasColumn('users', $column)
        ));

        if ($missingLegacyColumns !== []) {
            Schema::table('users', function (Blueprint $table) use ($missingLegacyColumns) {
                if (in_array('balance', $missingLegacyColumns, true)) {
                    $table->decimal('balance', 12, 2)->default(0)->after('admin_note');
                }

                if (in_array('credit_limit', $missingLegacyColumns, true)) {
                    $table->decimal('credit_limit', 12, 2)->default(0)->after('balance');
                }

                if (in_array('referral_frozen_amount', $missingLegacyColumns, true)) {
                    $table->decimal('referral_frozen_amount', 12, 2)->default(0)->after('total_sales_amount');
                }

                if (in_array('referral_available_amount', $missingLegacyColumns, true)) {
                    $table->decimal('referral_available_amount', 12, 2)->default(0)->after('referral_frozen_amount');
                }

                if (in_array('referral_withdrawing_amount', $missingLegacyColumns, true)) {
                    $table->decimal('referral_withdrawing_amount', 12, 2)->default(0)->after('referral_available_amount');
                }

                if (in_array('referral_withdrawn_amount', $missingLegacyColumns, true)) {
                    $table->decimal('referral_withdrawn_amount', 12, 2)->default(0)->after('referral_withdrawing_amount');
                }
            });
        }

        if (count($this->getExistingLegacyColumns()) === count($this->legacyColumns)) {
            DB::statement(<<<'SQL'
                UPDATE users u
                INNER JOIN user_accounts ua ON ua.user_id = u.id
                SET
                    u.balance = ua.cash_balance,
                    u.credit_limit = ua.credit_limit,
                    u.referral_frozen_amount = ua.referral_frozen_balance,
                    u.referral_available_amount = ua.referral_available_balance,
                    u.referral_withdrawing_amount = ua.referral_pending_withdrawal_balance,
                    u.referral_withdrawn_amount = ua.referral_withdrawn_balance,
                    u.updated_at = CURRENT_TIMESTAMP
            SQL);
        }
    }

    private function getExistingLegacyColumns(): array
    {
        return array_values(array_filter(
            $this->legacyColumns,
            fn (string $column): bool => Schema::hasColumn('users', $column)
        ));
    }
};
