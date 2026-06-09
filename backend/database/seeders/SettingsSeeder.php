<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;

class SettingsSeeder
{
    /**
     * 系统核心默认配置。
     * 敏感字段（支付密钥、邮箱密码等）写入占位值，部署后通过管理后台更新。
     */
    public static function seed(): void
    {
        static::seedGroup('system', [
            'geetest_enabled' => '1',
            'geetest_captcha_id' => '',
            'geetest_captcha_key' => '',
            'provision_hostname_enforce' => '0',
            'provision_hostname_prefix' => 'srv',
            'provision_hostname_charsets' => 'number',
            'provision_hostname_length' => '12',
        ]);

        static::seedGroup('basic', [
            'site_name' => '创欧云',
            'browser_title' => '创欧云 - 云计算服务平台',
            'site_logo' => '',
            'site_favicon' => '',
            'service_phone' => '',
            'service_email' => '',
            'service_hours' => '',
            'icp_number' => '',
            'icp_license_number' => '',
            'terms_url' => '',
            'privacy_url' => '',
        ]);

        static::seedGroup('payment', [
            'alipay_enabled' => '0',
            'alipay_name' => '支付宝支付',
            'alipay_app_id' => '',
            'alipay_private_key' => '',
            'alipay_public_key' => '',
        ]);

        static::seedGroup('notification', [
            'email_enabled' => '0',
            'email_host' => '',
            'email_port' => '465',
            'email_username' => '',
            'email_password' => '',
            'email_from_name' => '创欧云',
            'sms_enabled' => '0',
            'sms_provider' => 'aliyun',
            'sms_access_key' => '',
            'sms_secret_key' => '',
            'sms_sign_name' => '',
            'sms_template_code' => '',
        ]);

        static::seedGroup('verification', [
            'verification_api' => '',
            'verification_biz_code' => '',
            'verification_key' => '',
        ]);

        static::seedGroup('message_limit', [
            'email_rate_limit_enabled' => '1',
            'email_cooldown_seconds' => '60',
            'email_target_hourly_limit' => '10',
            'email_ip_hourly_limit' => '20',
            'sms_rate_limit_enabled' => '1',
            'sms_cooldown_seconds' => '60',
            'sms_target_hourly_limit' => '10',
            'sms_ip_hourly_limit' => '20',
        ]);

        static::seedGroup('automation', [
            'expire_suspend_enabled' => '1',
            'expire_suspend_after_days' => '1',
            'expire_suspend_notify_enabled' => '1',
            'expire_unsuspend_notify_enabled' => '1',
            'expire_terminate_enabled' => '0',
            'expire_terminate_after_days' => '7',
            'renew_notice_enabled' => '1',
            'renew_notice_days_before' => '7',
            'renew_create_invoice_enabled' => '0',
            'invoice_unpaid_reminder_enabled' => '1',
            'invoice_unpaid_before_due_days' => '3',
            'invoice_overdue_reminder_days' => '3',
            'invoice_overdue_after_days' => '7',
            'ticket_auto_close_enabled' => '1',
            'ticket_auto_close_after_hours' => '72',
            'ticket_auto_close_schedule_mode' => 'daily',
            'ticket_auto_close_schedule_time' => '03:00',
            'pending_order_cleanup_enabled' => '1',
            'pending_order_cleanup_after_days' => '3',
            'pending_order_cleanup_after_hours' => '0',
            'pending_recharge_cleanup_enabled' => '1',
            'pending_recharge_cleanup_after_days' => '3',
            'service_lifecycle_schedule_mode' => 'daily',
            'service_lifecycle_schedule_time' => '02:00',
            'billing_maintenance_schedule_mode' => 'daily',
            'billing_maintenance_schedule_time' => '02:30',
            'order_cleanup_schedule_mode' => 'daily',
            'order_cleanup_schedule_time' => '03:30',
        ]);

        static::seedGroup('product', [
            'product_types' => '',
            'product_auto_publish' => '1',
            'product_sort_mode' => 'manual',
            'show_out_of_stock' => '1',
            'enable_stock_warning' => '0',
            'stock_warning_value' => '10',
            'default_billing_cycle' => 'monthly',
            'order_auto_cancel_minutes' => '30',
            'instance_spec_catalog' => '',
            'cpu_model_catalog' => '',
        ]);

        static::seedGroup('referral', [
            'enabled' => '0',
            'reward_freeze_days' => '30',
            'withdraw_min_amount' => '50.00',
        ]);

        static::seedGroup('traffic_package', [
            'traffic_package_enabled' => '0',
            'traffic_package_display_threshold_percent' => '80',
            'traffic_package_button_text' => '购买流量包',
            'traffic_package_option_field' => '',
            'traffic_package_option_keyword' => '',
            'traffic_package_allow_choice_mode' => '0',
            'traffic_package_allow_quantity_mode' => '0',
        ]);

        static::seedGroup('traffic_package_catalog', [
            'groups' => '[]',
            'items' => '[]',
        ]);

        static::seedGroup('home_hero', [
            'slides' => '[]',
            'features' => '[]',
        ]);

        static::seedGroup('content', [
            'published_cache_version' => '1',
        ]);
    }

    private static function seedGroup(string $group, array $values): void
    {
        $existingKeys = \Illuminate\Support\Facades\DB::table('settings')
            ->where('group_key', $group)
            ->pluck('item_key')
            ->map(fn ($v) => (string) $v)
            ->toArray();

        $missing = [];
        foreach ($values as $key => $value) {
            if (! in_array($key, $existingKeys, true)) {
                $missing[$key] = $value;
            }
        }

        if ($missing === []) {
            return;
        }

        Setting::setValues($group, $missing);
    }
}
