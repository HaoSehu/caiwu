<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Mail\MailDriverManager;
use App\Services\System\AdminLogService;
use App\Services\System\NotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationServiceEmailLogFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSmtpMailPluginEnabled();
    }

    public function test_notification_service_no_longer_uses_plugin_placeholder_smtp_values(): void
    {
        $content = file_get_contents(base_path('app/Services/System/NotificationService.php'));

        $this->assertIsString($content);
        $this->assertStringNotContainsString("\$host = 'plugin'", $content);
        $this->assertStringNotContainsString("\$username = 'plugin'", $content);
        $this->assertStringNotContainsString('邮件接口配置不完整', $content);
    }

    public function test_send_email_falls_back_to_email_logs_when_notification_logs_table_is_missing(): void
    {
        if (Schema::hasTable('notification_logs')) {
            $this->markTestSkipped('notification_logs 表存在，当前环境无法验证 email_logs 回退路径。');
        }

        $this->assertTrue(Schema::hasTable('email_logs'));

        $suffix = bin2hex(random_bytes(4));
        $to = "codex-mail-{$suffix}@example.com";
        $subject = "Codex 邮件回退测试 {$suffix}";
        $content = "<p>mail fallback {$suffix}</p>";

        $settings = [
            'email_enabled' => Setting::getValue('notification', 'email_enabled', '0'),
            'email_host' => Setting::getValue('notification', 'email_host', ''),
            'email_port' => Setting::getValue('notification', 'email_port', ''),
            'email_username' => Setting::getValue('notification', 'email_username', ''),
            'email_password' => Setting::getValue('notification', 'email_password', ''),
            'email_from_name' => Setting::getValue('notification', 'email_from_name', ''),
        ];

        $fakeMailManager = $this->makeFakeMailManager();

        $originalMailManager = app('mail.manager');

        try {
            Setting::setValue('notification', 'email_enabled', '1');
            Setting::setValue('notification', 'email_host', 'smtp.example.com');
            Setting::setValue('notification', 'email_port', '465');
            Setting::setValue('notification', 'email_username', 'no-reply@example.com');
            Setting::setValue('notification', 'email_password', 'test-secret');
            Setting::setValue('notification', 'email_from_name', 'Codex Test');

            app()->instance('mail.manager', $fakeMailManager);
            Mail::swap($fakeMailManager);

            app(NotificationService::class)->sendEmail($to, $subject, $content, NotificationService::TEMPLATE_INVOICE_NOTICE);

            $this->assertCount(1, $fakeMailManager->messages);
            $this->assertSame($to, $fakeMailManager->messages[0]['payload']['to'] ?? null);
            $this->assertSame($subject, $fakeMailManager->messages[0]['payload']['subject'] ?? null);
            $this->assertSame(['no-reply@example.com', 'Codex Test'], $fakeMailManager->messages[0]['payload']['from'] ?? null);

            $log = DB::table('email_logs')
                ->where('to_email', $to)
                ->where('subject', $subject)
                ->orderByDesc('id')
                ->first();

            $this->assertNotNull($log);
            $this->assertSame('success', $log->status ?? null);
            $this->assertNull($log->error_msg ?? null);
            $this->assertNotNull($log->sent_at ?? null);
        } finally {
            DB::table('email_logs')
                ->where('to_email', $to)
                ->where('subject', $subject)
                ->delete();

            foreach ($settings as $key => $value) {
                Setting::setValue('notification', $key, $value);
            }

            app()->instance('mail.manager', $originalMailManager);
            Mail::swap($originalMailManager);
        }
    }

    public function test_send_template_email_includes_logo_in_themed_html(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $to = "codex-template-{$suffix}@example.com";
        $notificationSettings = [
            'email_enabled' => Setting::getValue('notification', 'email_enabled', '0'),
            'email_host' => Setting::getValue('notification', 'email_host', ''),
            'email_port' => Setting::getValue('notification', 'email_port', ''),
            'email_username' => Setting::getValue('notification', 'email_username', ''),
            'email_password' => Setting::getValue('notification', 'email_password', ''),
            'email_from_name' => Setting::getValue('notification', 'email_from_name', ''),
        ];
        $basicSettings = [
            'site_name' => Setting::getValue('basic', 'site_name', ''),
            'site_logo' => Setting::getValue('basic', 'site_logo', ''),
        ];

        $fakeMailManager = $this->makeFakeMailManager();
        $originalMailManager = app('mail.manager');

        try {
            Setting::setValue('notification', 'email_enabled', '1');
            Setting::setValue('notification', 'email_host', 'smtp.example.com');
            Setting::setValue('notification', 'email_port', '465');
            Setting::setValue('notification', 'email_username', 'no-reply@example.com');
            Setting::setValue('notification', 'email_password', 'test-secret');
            Setting::setValue('notification', 'email_from_name', 'Codex Test');
            Setting::setValue('basic', 'site_name', 'Codex Billing');

            app()->instance('mail.manager', $fakeMailManager);
            Mail::swap($fakeMailManager);

            app(NotificationService::class)->sendTemplateEmail($to, NotificationService::TEMPLATE_EMAIL_CODE, [
                'code' => '482915',
                'expire_minutes' => 10,
            ]);

            $this->assertCount(1, $fakeMailManager->messages);

            $html = (string) ($fakeMailManager->messages[0]['html'] ?? '');

            $this->assertStringContainsString('<img class="mail-logo"', $html);
            $this->assertMatchesRegularExpression('/<img class="mail-logo"[^>]*width="\d+"[^>]*height="44"[^>]*>/i', $html);
            $this->assertStringNotContainsString('width="180"', $html);
            $this->assertStringContainsString('<span>自动通知邮件</span>', $html);
        } finally {
            $this->deleteEmailLogsByRecipient($to);

            foreach ($notificationSettings as $key => $value) {
                Setting::setValue('notification', $key, $value);
            }

            foreach ($basicSettings as $key => $value) {
                Setting::setValue('basic', $key, $value);
            }

            app()->instance('mail.manager', $originalMailManager);
            Mail::swap($originalMailManager);
        }
    }

    public function test_email_verification_code_is_redacted_in_logs(): void
    {
        if (! Schema::hasTable('notification_logs')) {
            $this->markTestSkipped('notification_logs 表不存在，无法验证统一通知日志脱敏。');
        }

        $suffix = bin2hex(random_bytes(4));
        $to = "codex-email-code-{$suffix}@example.com";
        $code = '482915';
        $settings = [
            'email_enabled' => Setting::getValue('notification', 'email_enabled', '0'),
            'email_host' => Setting::getValue('notification', 'email_host', ''),
            'email_port' => Setting::getValue('notification', 'email_port', ''),
            'email_username' => Setting::getValue('notification', 'email_username', ''),
            'email_password' => Setting::getValue('notification', 'email_password', ''),
            'email_from_name' => Setting::getValue('notification', 'email_from_name', ''),
        ];

        $fakeMailManager = $this->makeFakeMailManager();
        $originalMailManager = app('mail.manager');

        try {
            Setting::setValue('notification', 'email_enabled', '1');
            Setting::setValue('notification', 'email_host', 'smtp.example.com');
            Setting::setValue('notification', 'email_port', '465');
            Setting::setValue('notification', 'email_username', 'no-reply@example.com');
            Setting::setValue('notification', 'email_password', 'test-secret');
            Setting::setValue('notification', 'email_from_name', 'Codex Test');

            app()->instance('mail.manager', $fakeMailManager);
            Mail::swap($fakeMailManager);

            app(NotificationService::class)->sendEmailCode($to, $code);

            $log = DB::table('notification_logs')
                ->where('channel', 'email')
                ->where('recipient', $to)
                ->where('template_code', NotificationService::TEMPLATE_EMAIL_CODE)
                ->orderByDesc('id')
                ->first();

            $this->assertNotNull($log);
            $this->assertStringNotContainsString($code, (string) $log->content);
            $this->assertStringContainsString('已脱敏', (string) $log->content);

            DB::table('notification_logs')
                ->where('id', $log->id)
                ->update(['content' => "验证码：{$code}"]);

            $payload = app(AdminLogService::class)->getEmailLogs(['email' => $to], 15);
            $adminLog = collect($payload['data'] ?? [])
                ->first(fn (array $item): bool => ($item['to_email'] ?? null) === $to);

            $this->assertNotNull($adminLog);
            $this->assertStringNotContainsString($code, (string) ($adminLog['content'] ?? ''));
            $this->assertStringContainsString('已脱敏', (string) ($adminLog['content'] ?? ''));
        } finally {
            $this->deleteEmailLogsByRecipient($to);

            foreach ($settings as $key => $value) {
                Setting::setValue('notification', $key, $value);
            }

            app()->instance('mail.manager', $originalMailManager);
            Mail::swap($originalMailManager);
        }
    }

    private function deleteEmailLogsByRecipient(string $to): void
    {
        if (Schema::hasTable('notification_logs')) {
            DB::table('notification_logs')->where('recipient', $to)->delete();
        }

        if (Schema::hasTable('email_logs')) {
            DB::table('email_logs')->where('to_email', $to)->delete();
        }
    }

    private function makeFakeMailManager(): object
    {
        return new class
        {
            public array $messages = [];

            public function forgetMailers(): void {}

            public function html(string $html, callable $callback): void
            {
                $message = new class
                {
                    public array $payload = [];

                    public function to(string $value): self
                    {
                        $this->payload['to'] = $value;

                        return $this;
                    }

                    public function subject(string $value): self
                    {
                        $this->payload['subject'] = $value;

                        return $this;
                    }

                    public function from(string $address, ?string $name = null): self
                    {
                        $this->payload['from'] = [$address, $name];

                        return $this;
                    }
                };

                $callback($message);

                $this->messages[] = [
                    'html' => $html,
                    'payload' => $message->payload,
                ];
            }
        };
    }

    private function ensureSmtpMailPluginEnabled(): void
    {
        $this->ensurePluginTables();

        $scanner = app(PluginScanner::class);
        $installer = app(PluginInstaller::class);
        $scanner->requireManifest('mail', 'smtp');
        $plugin = $installer->install('mail', 'smtp');
        $installer->enable($plugin);

        $this->app->forgetInstance(MailDriverManager::class);
    }

    private function ensurePluginTables(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            Schema::create('integration_plugins', function (Blueprint $table): void {
                $table->id();
                $table->string('domain', 32);
                $table->string('slug', 120);
                $table->string('plugin_key', 120);
                $table->string('name', 120);
                $table->string('version', 32)->default('1.0.0');
                $table->string('provider_class', 255)->nullable();
                $table->string('entry_class', 255);
                $table->json('capabilities_json')->nullable();
                $table->json('config_schema_json')->nullable();
                $table->unsignedTinyInteger('status')->default(0);
                $table->timestamp('installed_at')->nullable();
                $table->timestamps();
                $table->unique(['domain', 'slug']);
                $table->unique(['domain', 'plugin_key']);
            });
        }

        if (! Schema::hasTable('integration_plugin_configs')) {
            Schema::create('integration_plugin_configs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('plugin_id');
                $table->json('config_json')->nullable();
                $table->longText('secret_json')->nullable();
                $table->json('has_secret_json')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique('plugin_id');
            });
        }
    }
}
