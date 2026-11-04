<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 去重：account_bound 场景同时挂了 sms:100029 与 sms:100045，内容重复。
 *
 * 100029「成功绑定提醒(客户)」是系统原有的账号绑定短信，100045 是
 * 2026_11_04_000003 插入的等价模板。保留原有 100029，删除 100045 及其关联。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        DB::table('message_scene_templates')
            ->where('channel', 'sms')
            ->where('template_code', '100045')
            ->delete();

        DB::table('notification_templates')
            ->where('channel', 'sms')
            ->where('code', '100045')
            ->delete();
    }

    public function down(): void
    {
        // 去重不回滚。
    }
};
