<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 回填已实名用户的昵称（真实姓名 > 自定义）。
 *
 * 2026-11-05 起实名通过时会把 users.nickname 落成真实姓名（VerificationService::syncUserStatus），
 * 存量已实名用户当时还没有这条规则，昵称仍是注册时填的字符串；这里按同一口径补一次：
 * verification_status = 2（或历史双轨的 is_verified = 1）且 real_name 非空、昵称与真实姓名不一致的行，
 * 把 nickname 改成 real_name。
 *
 * 只做数据回填、不建索引，幂等：执行后再跑一遍不会再有匹配行。
 * 用户在此之后自行改回的自定义昵称不回滚（迁移一次性、无触发器）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->where(function ($query): void {
                $query->where('verification_status', 2)
                    ->orWhere(function ($legacy): void {
                        $legacy->where('verification_status', 0)->where('is_verified', 1);
                    });
            })
            ->whereNotNull('real_name')
            ->where('real_name', '<>', '')
            ->whereRaw('nickname <> real_name')
            ->whereNull('deleted_at')
            ->update([
                'nickname' => DB::raw('real_name'),
            ]);
    }

    public function down(): void
    {
        // 数据回填不可逆：原自定义昵称未留快照，回滚会让实名用户的用户名重新变成注册值，
        // 与本规则相反。此处刻意留空，需要恢复请从备份还原。
    }
};
