<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H3 — 强制 users.email / users.phone 非空
 *
 * 原列均为 nullable + UNIQUE。MySQL 唯一约束不保护 NULL，
 * 多个 NULL 可共存，注册路径如遗漏填写则绕过约束。
 *
 * 在开发环境先回填占位值，再将列改为 NOT NULL，
 * 彻底堵住 NULL 漏洞，同时保持唯一索引有效。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. 回填 NULL email（占位格式：placeholder-{id}@dev.local）
        DB::statement("
            UPDATE users
            SET email = CONCAT('placeholder-', id, '@dev.local')
            WHERE email IS NULL
        ");

        // 2. 回填 NULL phone（占位格式：000000{id 左补零至6位}）
        DB::statement("
            UPDATE users
            SET phone = CONCAT('000000', LPAD(id, 6, '0'))
            WHERE phone IS NULL
        ");

        // 3. email 改为 NOT NULL
        if (Schema::hasColumn('users', 'email')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->indexExists('users', 'users_email_unique')) {
                    $table->dropUnique('users_email_unique');
                }
                $table->string('email', 100)->nullable(false)->change();
                $table->unique('email', 'users_email_unique');
            });
        }

        // 4. phone 改为 NOT NULL
        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->indexExists('users', 'users_phone_unique')) {
                    $table->dropUnique('users_phone_unique');
                }
                $table->string('phone', 20)->nullable(false)->change();
                $table->unique('phone', 'users_phone_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'email')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->indexExists('users', 'users_email_unique')) {
                    $table->dropUnique('users_email_unique');
                }
                $table->string('email', 100)->nullable()->change();
                $table->unique('email', 'users_email_unique');
            });
        }

        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->indexExists('users', 'users_phone_unique')) {
                    $table->dropUnique('users_phone_unique');
                }
                $table->string('phone', 20)->nullable()->change();
                $table->unique('phone', 'users_phone_unique');
            });
        }
        // 注意：回填的占位值不清除（无害保留）
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return ! empty(DB::select('
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name   = ?
              AND index_name   = ?
            LIMIT 1
        ', [$table, $indexName]));
    }
};
