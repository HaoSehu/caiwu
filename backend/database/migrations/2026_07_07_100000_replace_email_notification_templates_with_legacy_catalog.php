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

        DB::table('notification_templates')
            ->where('channel', 'email')
            ->delete();

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('group_key', 'notification')
                ->where('item_key', 'like', 'email_template_%')
                ->delete();
        }

        $now = now();
        foreach (EmailNotificationTemplateDefaults::templates() as $index => $template) {
            DB::table('notification_templates')->insert([
                'channel' => 'email',
                'code' => $template['code'],
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
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        DB::table('notification_templates')
            ->where('channel', 'email')
            ->whereIn('code', array_column(EmailNotificationTemplateDefaults::templates(), 'code'))
            ->delete();
    }
};
