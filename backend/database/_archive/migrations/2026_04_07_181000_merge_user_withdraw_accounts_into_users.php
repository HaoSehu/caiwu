<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'alipay_real_name')) {
                $table->string('alipay_real_name', 50)->nullable()->after('verified_at');
            }

            if (! Schema::hasColumn('users', 'alipay_account')) {
                $table->string('alipay_account', 100)->nullable()->after('alipay_real_name');
            }
        });

        if (! Schema::hasTable('user_withdraw_accounts')) {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE users u
            INNER JOIN (
                SELECT fwa.user_id, fwa.account_name, fwa.account_no
                FROM user_withdraw_accounts fwa
                INNER JOIN (
                    SELECT
                        user_id,
                        COALESCE(
                            MAX(CASE WHEN status = 1 AND is_default = 1 THEN id END),
                            MAX(CASE WHEN status = 1 THEN id END),
                            MAX(id)
                        ) AS selected_id
                    FROM user_withdraw_accounts
                    GROUP BY user_id
                ) picked ON picked.selected_id = fwa.id
                WHERE fwa.account_type = 'alipay'
            ) wa ON wa.user_id = u.id
            SET
                u.alipay_real_name = COALESCE(NULLIF(u.alipay_real_name, ''), wa.account_name),
                u.alipay_account = COALESCE(NULLIF(u.alipay_account, ''), wa.account_no),
                u.updated_at = CURRENT_TIMESTAMP
        SQL);

        Schema::drop('user_withdraw_accounts');
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || Schema::hasTable('user_withdraw_accounts')) {
            return;
        }

        Schema::create('user_withdraw_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('account_type', 20);
            $table->string('account_name', 80);
            $table->string('account_no', 120);
            $table->unsignedTinyInteger('is_default')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'account_type', 'account_no'], 'user_withdraw_accounts_unique');
            $table->index(['user_id', 'is_default'], 'user_withdraw_accounts_user_default_idx');
            $table->index(['user_id', 'status'], 'user_withdraw_accounts_user_status_idx');
        });

        DB::statement(<<<'SQL'
            INSERT INTO user_withdraw_accounts (
                user_id,
                account_type,
                account_name,
                account_no,
                is_default,
                status,
                created_at,
                updated_at
            )
            SELECT
                u.id,
                'alipay',
                u.alipay_real_name,
                u.alipay_account,
                1,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            FROM users u
            WHERE COALESCE(NULLIF(u.alipay_real_name, ''), NULLIF(u.alipay_account, '')) IS NOT NULL
        SQL);
    }
};
