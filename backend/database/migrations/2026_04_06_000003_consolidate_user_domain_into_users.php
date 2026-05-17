<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_profiles')) {
            DB::statement(<<<'SQL'
                UPDATE users u
                INNER JOIN user_profiles up ON up.user_id = u.id
                SET
                    u.nickname = COALESCE(NULLIF(u.nickname, ''), up.nickname),
                    u.company = COALESCE(NULLIF(u.company, ''), up.company),
                    u.qq = COALESCE(NULLIF(u.qq, ''), up.qq),
                    u.admin_note = COALESCE(NULLIF(u.admin_note, ''), up.admin_note),
                    u.updated_at = CURRENT_TIMESTAMP
            SQL);
        }

        if (Schema::hasTable('user_verifications')) {
            DB::statement(<<<'SQL'
                UPDATE users u
                INNER JOIN user_verifications uv ON uv.user_id = u.id
                SET
                    u.is_verified = CASE WHEN uv.verification_status = 2 THEN 1 ELSE u.is_verified END,
                    u.real_name = COALESCE(NULLIF(u.real_name, ''), uv.real_name),
                    u.id_card = COALESCE(NULLIF(u.id_card, ''), uv.id_card_encrypted),
                    u.verification_status = CASE WHEN u.verification_status = 0 THEN uv.verification_status ELSE u.verification_status END,
                    u.verification_message = CASE WHEN u.verification_message = '' THEN COALESCE(uv.verification_message, '') ELSE u.verification_message END,
                    u.verification_certify_id = COALESCE(NULLIF(u.verification_certify_id, ''), uv.certify_id),
                    u.verified_at = COALESCE(u.verified_at, uv.verified_at),
                    u.updated_at = CURRENT_TIMESTAMP
            SQL);
        }

        if (Schema::hasTable('user_referrals')) {
            DB::statement(<<<'SQL'
                UPDATE users u
                INNER JOIN user_referrals ur ON ur.user_id = u.id
                SET
                    u.referral_code = COALESCE(NULLIF(u.referral_code, ''), ur.referral_code),
                    u.referrer_user_id = COALESCE(u.referrer_user_id, ur.referrer_user_id),
                    u.referred_at = COALESCE(u.referred_at, ur.referred_at),
                    u.member_level_id = COALESCE(u.member_level_id, ur.member_level_id),
                    u.total_sales_amount = CASE WHEN u.total_sales_amount = 0 THEN COALESCE(ur.total_sales_amount, 0) ELSE u.total_sales_amount END,
                    u.updated_at = CURRENT_TIMESTAMP
            SQL);
        }

        if (! Schema::hasIndex('users', 'users_referral_code_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('referral_code', 'users_referral_code_unique');
            });
        }

        if (! Schema::hasIndex('users', 'users_member_level_status_idx')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['member_level_id', 'status'], 'users_member_level_status_idx');
            });
        }

        if (! Schema::hasIndex('users', 'users_verification_status_idx')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('verification_status', 'users_verification_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('users', 'users_verification_status_idx')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_verification_status_idx');
            });
        }

        if (Schema::hasIndex('users', 'users_member_level_status_idx')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_member_level_status_idx');
            });
        }

        if (Schema::hasIndex('users', 'users_referral_code_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_referral_code_unique');
            });
        }
    }
};
