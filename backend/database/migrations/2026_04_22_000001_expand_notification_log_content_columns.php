<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // notification_logs.content — HTML 邮件内容可超过 TEXT 的 64 KB 限制
        if (Schema::hasTable('notification_logs') && Schema::hasColumn('notification_logs', 'content')) {
            DB::statement('ALTER TABLE `notification_logs` MODIFY `content` MEDIUMTEXT NOT NULL');
        }

        // notification_logs.error_msg — 确保也是 TEXT 避免长异常信息截断
        if (Schema::hasTable('notification_logs') && Schema::hasColumn('notification_logs', 'error_msg')) {
            DB::statement('ALTER TABLE `notification_logs` MODIFY `error_msg` TEXT NULL');
        }

        // email_logs.content — 同理
        if (Schema::hasTable('email_logs') && Schema::hasColumn('email_logs', 'content')) {
            DB::statement('ALTER TABLE `email_logs` MODIFY `content` MEDIUMTEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_logs') && Schema::hasColumn('notification_logs', 'content')) {
            DB::statement('ALTER TABLE `notification_logs` MODIFY `content` TEXT NOT NULL');
        }

        if (Schema::hasTable('notification_logs') && Schema::hasColumn('notification_logs', 'error_msg')) {
            DB::statement('ALTER TABLE `notification_logs` MODIFY `error_msg` TEXT NULL');
        }

        if (Schema::hasTable('email_logs') && Schema::hasColumn('email_logs', 'content')) {
            DB::statement('ALTER TABLE `email_logs` MODIFY `content` TEXT NULL');
        }
    }
};
