<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_coupons', function (Blueprint $table): void {
            $table->string('uid', 32)->nullable()->unique()->after('id');
            $table->timestamp('used_at')->nullable()->after('claimed_at');
            $table->timestamp('revoked_at')->nullable()->after('used_at');
            $table->timestamp('reserved_until')->nullable()->after('revoked_at');
            $table->index(['user_id', 'status'], 'user_coupons_user_status_uidx');
        });
    }

    public function down(): void
    {
        Schema::table('user_coupons', function (Blueprint $table): void {
            $table->dropIndex('user_coupons_user_status_uidx');
            $table->dropUnique('user_coupons_uid_unique');
            $table->dropColumn(['uid', 'used_at', 'revoked_at', 'reserved_until']);
        });
    }
};
