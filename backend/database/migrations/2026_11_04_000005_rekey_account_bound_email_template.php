<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 消除模板码跨渠道冲突：email:100031 与 sms:100031 语义不同。
 *
 * 历史：2026_11_04_000002 为 account_bound 新建邮件模板时复用了 100031，
 * 但 100031 在短信渠道已是「续费成功提醒(用户)」，归属 service_due。
 * 虽然唯一键是 (channel, code) 不冲突，但同码不同义会造成运维与对账混淆。
 *
 * 本迁移把 account_bound 的邮件模板改到新码 100048，并同步关联表。
 */
return new class extends Migration
{
    private const OLD_CODE = '100031';

    private const NEW_CODE = '100048';

    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $source = DB::table('notification_templates')
            ->where('channel', 'email')
            ->where('code', self::OLD_CODE)
            ->first();

        if ($source === null) {
            return;
        }

        $now = now();

        DB::table('notification_templates')->insertOrIgnore([
            'channel' => 'email',
            'code' => self::NEW_CODE,
            'name' => $source->name,
            'description' => $source->description,
            'audience' => $source->audience,
            'scene_code' => $source->scene_code,
            'subject' => $source->subject,
            'content' => $source->content,
            'variables_json' => $source->variables_json,
            'provider_variables_json' => $source->provider_variables_json,
            'provider_template_id' => null,
            'is_enabled' => 1,
            'is_custom' => 0,
            'sort_order' => (int) $source->sort_order,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 关联表改指向新码
        DB::table('message_scene_templates')
            ->where('channel', 'email')
            ->where('template_code', self::OLD_CODE)
            ->where('scene_code', 'account_bound')
            ->update(['template_code' => self::NEW_CODE, 'updated_at' => $now]);

        // 删除旧码的邮件模板（短信同码不受影响）
        DB::table('notification_templates')
            ->where('channel', 'email')
            ->where('code', self::OLD_CODE)
            ->delete();
    }

    public function down(): void
    {
        // 数据修正不回滚。
    }
};
