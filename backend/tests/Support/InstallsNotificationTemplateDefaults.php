<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Schema;

trait InstallsNotificationTemplateDefaults
{
    protected function installNotificationTemplateDefaults(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        foreach ($this->notificationTemplateDefaultMigrations() as $migration) {
            $instance = require database_path('migrations/'.$migration);
            $instance->up();
        }
    }

    /**
     * @return list<string>
     */
    private function notificationTemplateDefaultMigrations(): array
    {
        return [
            '2026_07_06_124000_seed_notification_templates_defaults.php',
            '2026_07_06_125000_convert_sms_template_variables_to_single_braces.php',
            '2026_07_06_130000_renumber_sms_notification_template_codes.php',
            '2026_07_07_090000_update_email_notification_templates_html.php',
            '2026_07_07_100000_replace_email_notification_templates_with_legacy_catalog.php',
            '2026_07_08_003000_refine_email_notification_template_visuals.php',
            '2026_07_08_004000_remove_email_template_visual_cards.php',
        ];
    }
}
