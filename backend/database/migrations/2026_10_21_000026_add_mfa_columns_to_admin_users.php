<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理员双因素认证（SEC-1）：为 admin_users 增加 TOTP 绑定所需列。
 *
 * - mfa_secret：TOTP 密钥经 Crypt 加密后的密文。列宽定 512 而不是 255：
 *   Base32 密钥 32 字符加密后密文约 236 字符，看似放得下，但 APP_KEY 轮换
 *   走 previous_keys 时密文前缀/填充会变化，且未来若换 AES-256-GCM 会再增长；
 *   256 级别的余量只会换来一次线上截断故障，不值得赌。
 * - mfa_enabled_at：非空即表示已启用双因素认证；清空即解绑。
 *   用「时间戳非空」而不是布尔列，是为了同时留下绑定时间。
 * - mfa_recovery_codes：恢复码的哈希数组（JSON），每条只存 Hash::make 结果，
 *   与密码同等级保护；使用后即时从数组中移除。
 *
 * 三列均可空，存量管理员不受影响，保持「未启用」语义。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_users')) {
            return;
        }

        Schema::table('admin_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('admin_users', 'mfa_secret')) {
                $table->string('mfa_secret', 512)->nullable()->after('password')->comment('双因素认证 TOTP 密钥密文，未绑定为 NULL');
            }

            if (! Schema::hasColumn('admin_users', 'mfa_enabled_at')) {
                $table->timestamp('mfa_enabled_at')->nullable()->after('mfa_secret')->comment('双因素认证启用时间，NULL 表示未启用');
            }

            if (! Schema::hasColumn('admin_users', 'mfa_recovery_codes')) {
                $table->json('mfa_recovery_codes')->nullable()->after('mfa_enabled_at')->comment('恢复码哈希数组（JSON），仅存 Hash 结果');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_users')) {
            return;
        }

        Schema::table('admin_users', function (Blueprint $table): void {
            if (Schema::hasColumn('admin_users', 'mfa_recovery_codes')) {
                $table->dropColumn('mfa_recovery_codes');
            }

            if (Schema::hasColumn('admin_users', 'mfa_enabled_at')) {
                $table->dropColumn('mfa_enabled_at');
            }

            if (Schema::hasColumn('admin_users', 'mfa_secret')) {
                $table->dropColumn('mfa_secret');
            }
        });
    }
};
