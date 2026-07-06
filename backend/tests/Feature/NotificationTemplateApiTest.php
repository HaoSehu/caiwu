<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Models\Setting;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\Data\SmsMessageRequest;
use App\Services\Sms\Data\SmsSendRequest;
use App\Services\Sms\Data\SmsSendResult;
use App\Services\Sms\SmsDriverManager;
use App\Services\System\SmsService;
use App\Support\AdminPermissions;
use App\Support\SmsTemplateCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTemplateApiTest extends TestCase
{
    public function test_admin_notification_template_catalog_requires_settings_view_and_returns_email_sms_templates(): void
    {
        $this->getJson('/api/v2/admin/notification-templates')
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);

        Sanctum::actingAs($this->createAdmin([]));

        $this->getJson('/api/v2/admin/notification-templates')
            ->assertForbidden()
            ->assertJsonPath('code', 40300);

        Sanctum::actingAs($this->createAdmin([AdminPermissions::SETTINGS_VIEW]));

        $this->getJson('/api/v2/admin/notification-templates?per_page=20')
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['per_page']]]);

        $response = $this->getJson('/api/v2/admin/notification-templates?channel=sms')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.list.0.channel', 'sms');

        $templates = collect($response->json('data.list'));
        $this->assertSame($templates->count(), $response->json('data.total'));
        $this->assertSame(27, $templates->count());
        $this->assertTrue($templates->every(
            fn (array $template): bool => preg_match('/^\d+$/', (string) $template['code']) === 1
        ));
        $this->assertFalse($templates->pluck('code')->contains('send_code'));
        $this->assertFalse($templates->pluck('code')->contains('invoice_pay'));
        $this->assertTrue($templates->contains(
            fn (array $template): bool => $template['code'] === SmsTemplateCatalog::TEMPLATE_VERIFY_CODE
                && $template['name'] === '发送验证码'
                && $template['setting_keys']['content'] === SmsTemplateCatalog::contentSettingKey(SmsTemplateCatalog::TEMPLATE_VERIFY_CODE)
                && str_contains((string) $template['content'], '{code}')
        ));
        $this->assertTrue($templates->contains(
            fn (array $template): bool => $template['code'] === SmsTemplateCatalog::TEMPLATE_AUTO_RENEW_UPCOMING
                && $template['setting_keys']['content'] === SmsTemplateCatalog::contentSettingKey(SmsTemplateCatalog::TEMPLATE_AUTO_RENEW_UPCOMING)
        ));
        $this->assertTrue($templates->contains(
            fn (array $template): bool => $template['code'] === SmsTemplateCatalog::TEMPLATE_SERVICE_ACTIVATED
                && str_contains((string) $template['content'], '已开通')
        ));
        $this->assertTrue($templates->contains(
            fn (array $template): bool => $template['code'] === SmsTemplateCatalog::TEMPLATE_ADMIN_ORDER_PAID
                && $template['audience'] === 'user'
        ));

        $this->assertSame([
            'channel',
            'code',
            'name',
            'description',
            'audience',
            'subject',
            'content',
            'provider_template_id',
            'variables',
            'provider_variables',
            'setting_keys',
        ], array_keys($response->json('data.list.0')));
    }

    public function test_sms_verification_log_uses_configurable_template_content_and_redacts_code(): void
    {
        if (! Schema::hasTable('notification_logs') && ! Schema::hasTable('sms_logs')) {
            $this->markTestSkipped('通知日志表不存在，无法验证短信模板日志。');
        }

        $phone = '13900001234';
        $code = '482915';
        $driver = new NotificationTemplateFakeSmsDriver('demo_sms');
        $template = $this->notificationTemplate('sms', SmsTemplateCatalog::TEMPLATE_VERIFY_CODE);
        $settings = [
            'sms_enabled' => Setting::getValue('notification', 'sms_enabled', '0'),
            'sms_driver' => Setting::getValue('notification', 'sms_driver', ''),
            'sms_provider' => Setting::getValue('notification', 'sms_provider', ''),
        ];
        $templateSnapshot = [
            'content' => $template->content,
            'provider_template_id' => $template->provider_template_id,
            'is_custom' => $template->is_custom,
        ];

        try {
            Setting::setValue('notification', 'sms_enabled', '1');
            Setting::setValue('notification', 'sms_driver', 'demo_sms');
            Setting::setValue('notification', 'sms_provider', 'demo_sms');
            $template->forceFill([
                'content' => '验证码 {code}，{expire_minutes} 分钟内有效。',
                'provider_template_id' => 'SMS_CUSTOM_VERIFY',
            ])->save();

            $resolver = new NotificationTemplateFakeBindingResolver('demo_sms');
            (new SmsService(new SmsDriverManager([$driver], $resolver), $resolver))->sendVerifyCode($phone, $code);

            $this->assertSame('SMS_CUSTOM_VERIFY', $driver->lastMessageRequest?->templateCode);
            $this->assertSame('验证码 482915，5 分钟内有效。', $driver->lastMessageRequest?->content);

            $log = $this->latestSmsLog($phone);
            $this->assertNotNull($log);
            $this->assertSame(SmsTemplateCatalog::TEMPLATE_VERIFY_CODE, (string) ($log->template_code ?? ''));
            $this->assertStringContainsString('验证码 ***，5 分钟内有效。', (string) ($log->content ?? ''));
            $this->assertStringNotContainsString($code, (string) ($log->content ?? ''));
        } finally {
            foreach ($settings as $key => $value) {
                Setting::setValue('notification', (string) $key, $value);
            }

            NotificationTemplate::query()
                ->whereKey($template->getKey())
                ->update($templateSnapshot);

            $this->deleteSmsLogsByRecipient($phone);
        }
    }

    public function test_aliyun_sms_verification_uses_builtin_plugin_template_instead_of_system_template(): void
    {
        if (! Schema::hasTable('notification_logs') && ! Schema::hasTable('sms_logs')) {
            $this->markTestSkipped('通知日志表不存在，无法验证短信模板日志。');
        }

        $phone = '13900004567';
        $code = '392817';
        $driver = new NotificationTemplateFakeSmsDriver('aliyun');
        $template = $this->notificationTemplate('sms', SmsTemplateCatalog::TEMPLATE_VERIFY_CODE);
        $settings = [
            'sms_enabled' => Setting::getValue('notification', 'sms_enabled', '0'),
            'sms_driver' => Setting::getValue('notification', 'sms_driver', ''),
            'sms_provider' => Setting::getValue('notification', 'sms_provider', ''),
        ];
        $templateSnapshot = [
            'content' => $template->content,
            'provider_template_id' => $template->provider_template_id,
            'is_custom' => $template->is_custom,
        ];

        try {
            Setting::setValue('notification', 'sms_enabled', '1');
            Setting::setValue('notification', 'sms_driver', 'aliyun');
            Setting::setValue('notification', 'sms_provider', 'aliyun');
            $template->forceFill([
                'content' => '这段系统模板不应被阿里云使用 {code}',
                'provider_template_id' => 'SMS_SHOULD_NOT_BE_USED',
            ])->save();

            $resolver = new NotificationTemplateFakeBindingResolver('aliyun');
            (new SmsService(new SmsDriverManager([$driver], $resolver), $resolver))->sendVerifyCode($phone, $code, [
                'purpose' => 'reset_password',
            ]);

            $this->assertNull($driver->lastMessageRequest);
            $this->assertNotNull($driver->lastRequest);
            $this->assertSame('reset_password', $driver->lastRequest?->option('purpose'));
            $this->assertSame($code, $driver->lastRequest?->code);

            $log = $this->latestSmsLog($phone);
            $this->assertNotNull($log);
            $this->assertSame('100003', (string) ($log->template_code ?? ''));
            $this->assertStringNotContainsString($code, (string) ($log->content ?? ''));
            $this->assertStringNotContainsString('这段系统模板不应被阿里云使用', (string) ($log->content ?? ''));
        } finally {
            foreach ($settings as $key => $value) {
                Setting::setValue('notification', (string) $key, $value);
            }

            NotificationTemplate::query()
                ->whereKey($template->getKey())
                ->update($templateSnapshot);

            $this->deleteSmsLogsByRecipient($phone);
        }
    }

    public function test_admin_notification_template_save_writes_database_template_instead_of_settings(): void
    {
        $template = $this->notificationTemplate('sms', SmsTemplateCatalog::TEMPLATE_VERIFY_CODE);
        $templateSnapshot = [
            'content' => $template->content,
            'provider_template_id' => $template->provider_template_id,
            'is_custom' => $template->is_custom,
        ];
        $contentKey = SmsTemplateCatalog::contentSettingKey(SmsTemplateCatalog::TEMPLATE_VERIFY_CODE);
        $providerTemplateIdKey = SmsTemplateCatalog::providerTemplateIdSettingKey(SmsTemplateCatalog::TEMPLATE_VERIFY_CODE);
        $newContent = '后台保存验证码 {code}，{expire_minutes} 分钟内有效。';
        $newProviderTemplateId = 'SMS_DB_VERIFY';

        Sanctum::actingAs($this->createAdmin([AdminPermissions::SETTINGS_MANAGE]));

        try {
            $this->postJson('/api/v2/admin/settings', [
                'group' => 'notification',
                'settings' => [
                    $contentKey => $newContent,
                    $providerTemplateIdKey => $newProviderTemplateId,
                ],
            ])
                ->assertOk()
                ->assertJsonPath('code', 0);

            $template->refresh();

            $this->assertSame($newContent, (string) $template->content);
            $this->assertSame($newProviderTemplateId, (string) $template->provider_template_id);
            $this->assertTrue((bool) $template->is_custom);
            $this->assertNotSame($newContent, (string) DB::table('settings')
                ->where('group_key', 'notification')
                ->where('item_key', $contentKey)
                ->value('item_value'));
            $this->assertNotSame($newProviderTemplateId, (string) DB::table('settings')
                ->where('group_key', 'notification')
                ->where('item_key', $providerTemplateIdKey)
                ->value('item_value'));
        } finally {
            NotificationTemplate::query()
                ->whereKey($template->getKey())
                ->update($templateSnapshot);
        }
    }

    private function latestSmsLog(string $phone): ?object
    {
        if (Schema::hasTable('notification_logs')) {
            return DB::table('notification_logs')
                ->where('channel', 'sms')
                ->where('recipient', $phone)
                ->orderByDesc('id')
                ->first();
        }

        return DB::table('sms_logs')
            ->where('phone', $phone)
            ->orderByDesc('id')
            ->first();
    }

    private function deleteSmsLogsByRecipient(string $phone): void
    {
        if (Schema::hasTable('notification_logs')) {
            DB::table('notification_logs')
                ->where('channel', 'sms')
                ->where('recipient', $phone)
                ->delete();
        }

        if (Schema::hasTable('sms_logs')) {
            DB::table('sms_logs')->where('phone', $phone)->delete();
        }
    }

    private function notificationTemplate(string $channel, string $code): NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('channel', $channel)
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function createAdmin(array $permissions): AdminUser
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'notification-template-'.$suffix,
            'label' => 'Notification Template',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'notification-template-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Notification Template',
            'email' => 'notification-template-'.$suffix.'@example.com',
            'status' => 1,
        ]);
    }
}

final class NotificationTemplateFakeBindingResolver extends IntegrationDriverBindingResolver
{
    public function __construct(
        private readonly string $driverKey,
    ) {}

    public function smsDriverKey(): string
    {
        return $this->driverKey;
    }

    public function smsDriverCandidates(): array
    {
        return [$this->driverKey];
    }

    public function smsContext(?string $driverKey = null): array
    {
        return [
            'plugin_id' => null,
            'driver_key' => $driverKey ?: $this->driverKey,
        ];
    }
}

final class NotificationTemplateFakeSmsDriver implements SmsDriver
{
    public ?SmsSendRequest $lastRequest = null;

    public ?SmsMessageRequest $lastMessageRequest = null;

    public function __construct(
        private readonly string $key,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return '测试短信';
    }

    public function sendMessage(SmsMessageRequest $request): SmsSendResult
    {
        $this->lastMessageRequest = $request;

        return new SmsSendResult(
            status: 'success',
            requestId: 'notification-template-message-test',
            templateCode: $request->templateCode,
            templateParams: ['content' => $request->content],
        );
    }

    public function sendVerifyCode(SmsSendRequest $request): SmsSendResult
    {
        $this->lastRequest = $request;
        $templateCode = match ((string) $request->option('purpose', 'generic')) {
            'change_phone', 'phone_change', 'update_phone' => '100002',
            'reset', 'reset_password', 'password_reset' => '100003',
            'bind_phone', 'new_phone' => '100004',
            'verify_bound_phone', 'verify_phone' => '100005',
            default => '100001',
        };

        return new SmsSendResult(
            status: 'success',
            requestId: 'notification-template-test',
            templateCode: $templateCode,
            templateParams: ['code' => $request->code],
        );
    }
}
