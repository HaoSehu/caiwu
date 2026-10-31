<?php

declare(strict_types=1);

use App\Support\EmailNotificationTemplateDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 补入实例归属变更通知模板 100030。
 *
 * 管理员转移实例归属后，原持有者与接收方都需要收到通知（见
 * App\Services\User\Concerns\HandlesAdminUserServices::notifyServiceTransferred），
 * 但模板默认值此前只到 100029。本迁移按 EmailNotificationTemplateDefaults 的默认值
 * 补入缺失记录，仅覆盖 is_custom=0 的场景，保留管理员自定义模板。
 */
return new class extends Migration
{
    private const TARGET_CODE = '100030';

    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $now = now();

        foreach (EmailNotificationTemplateDefaults::templates() as $index => $template) {
            if ((string) ($template['code'] ?? '') !== self::TARGET_CODE) {
                continue;
            }

            $existing = DB::table('notification_templates')
                ->where('channel', 'email')
                ->where('code', self::TARGET_CODE)
                ->first(['id', 'is_custom']);

            if ($existing === null) {
                DB::table('notification_templates')->insert([
                    'channel' => 'email',
                    'code' => self::TARGET_CODE,
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'audience' => $template['audience'],
                    'subject' => $template['subject'] ?? null,
                    'content' => $template['content'],
                    'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'provider_variables_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'provider_template_id' => null,
                    'is_enabled' => true,
                    'is_custom' => false,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return;
            }

            if ((int) $existing->is_custom === 1) {
                return;
            }

            DB::table('notification_templates')
                ->where('id', $existing->id)
                ->update([
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'audience' => $template['audience'],
                    'subject' => $template['subject'] ?? null,
                    'content' => $template['content'],
                    'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // 模板补入为单向同步，不做回滚（与同批次模板修复迁移口径一致）。
    }
};
