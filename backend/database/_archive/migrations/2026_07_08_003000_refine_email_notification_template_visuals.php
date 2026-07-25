<?php

use App\Support\EmailNotificationTemplateDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $now = now();
        $codes = [];

        foreach (EmailNotificationTemplateDefaults::templates() as $index => $template) {
            $code = (string) $template['code'];
            $codes[] = $code;

            DB::table('notification_templates')->updateOrInsert(
                [
                    'channel' => 'email',
                    'code' => $code,
                ],
                [
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'audience' => $template['audience'],
                    'subject' => $template['subject'],
                    'content' => $template['content'],
                    'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'provider_variables_json' => '[]',
                    'provider_template_id' => null,
                    'is_enabled' => true,
                    'is_custom' => false,
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        if (Schema::hasTable('settings')) {
            $legacySettingKeys = [];
            foreach ($codes as $code) {
                $legacySettingKeys[] = 'email_template_subject_'.$code;
                $legacySettingKeys[] = 'email_template_content_'.$code;
                $legacySettingKeys[] = 'email_template_css_'.$code;
            }

            DB::table('settings')
                ->where('group_key', 'notification')
                ->whereIn('item_key', $legacySettingKeys)
                ->delete();
        }
    }

    public function down(): void
    {
        // Visual template refinements are data-only defaults and are not rolled back.
    }
};
