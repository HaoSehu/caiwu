<?php

declare(strict_types=1);

use App\Support\EmailNotificationTemplateDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 对齐客户工单邮件模板 100023/100024 的变量声明：
 *
 * - 100023（工单开通通知）：notice 含 {{login_tip}}，与 100024 同为 sendClientTicketNotification
 *   发送路径（统一携带 tickets_url），补入 tickets_url 声明，消除与 100024 的声明差异。
 * - 100024（工单新回复通知）：正文（rows/notice）从未使用 tickets_url，发送侧携带该变量
 *   仅因公共路径统一传参，移除声明使「声明 = 正文实际可用变量」语义成立。
 *
 * 两个模板的正文均无变化，仅更新 is_custom=0 记录的 variables_json。
 */
return new class extends Migration
{
    private const TARGET_CODES = ['100023', '100024'];

    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        foreach (EmailNotificationTemplateDefaults::templates() as $template) {
            $code = (string) ($template['code'] ?? '');

            if (! in_array($code, self::TARGET_CODES, true)) {
                continue;
            }

            DB::table('notification_templates')
                ->where('channel', 'email')
                ->where('code', $code)
                ->where('is_custom', 0)
                ->update([
                    'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // 模板声明对齐为单向同步，不做回滚（与同批次 100004 修复迁移口径一致）。
    }
};
