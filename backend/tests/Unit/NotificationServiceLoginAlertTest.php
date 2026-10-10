<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Mail\MailDriverManager;
use App\Services\System\NotificationService;
use App\Services\System\NotificationTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\Mail\FakeMailDriver;
use Tests\TestCase;

/**
 * 登录/登录失败 IP 提醒邮件回归：模板 100003 的「上次 IP」行必须始终有值，
 * 不得因 previous_ip 变量缺失渲染成空（历史缺陷）。
 */
class NotificationServiceLoginAlertTest extends TestCase
{
    use DatabaseTransactions;

    private ?FakeMailDriver $driver = null;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('notification', 'email_enabled', '1');
        $this->driver = null;
    }

    private function makeService(): NotificationService
    {
        $templates = new class extends NotificationTemplateService
        {
            public function find(string $channel, string $code): ?array
            {
                return [
                    'subject' => '登录IP提醒',
                    'content' => '上次IP：{{previous_ip}}，当前IP：{{ip}}，设备：{{device}}。',
                    'is_enabled' => true,
                ];
            }

            public function isEnabled(string $channel, string $code): bool
            {
                return true;
            }
        };

        $bindingResolver = new class extends IntegrationDriverBindingResolver
        {
            public function mailContext(?string $driverKey = null): array
            {
                return ['plugin_id' => null, 'driver_key' => 'fake'];
            }

            public function mailDriverCandidates(): array
            {
                return ['fake'];
            }

            public function mailDriverKey(): string
            {
                return 'fake';
            }
        };

        $this->driver = new FakeMailDriver('fake');

        return new NotificationService(
            new MailDriverManager([$this->driver], $bindingResolver),
            $bindingResolver,
            $templates
        );
    }

    private function sentHtml(): string
    {
        return $this->driver?->lastHtml() ?? '';
    }

    public function test_login_alert_renders_previous_ip_snapshot(): void
    {
        $service = $this->makeService();

        $service->sendLoginEmailAlertToAddress(
            'client@example.com',
            '张三',
            '2026-08-28 10:00:00',
            '203.0.113.9',
            'Mozilla/5.0',
            '198.51.100.7'
        );

        $this->assertStringContainsString('上次IP：198.51.100.7', $this->sentHtml());
        $this->assertStringNotContainsString('无历史记录', $this->sentHtml());
    }

    public function test_login_alert_renders_hint_when_no_previous_ip(): void
    {
        $service = $this->makeService();

        $service->sendLoginEmailAlertToAddress(
            'client@example.com',
            '张三',
            '2026-08-28 10:00:00',
            '203.0.113.9',
            'Mozilla/5.0',
            null
        );

        $this->assertStringContainsString('上次IP：无历史记录', $this->sentHtml());
    }

    public function test_login_failure_alert_never_leaves_previous_ip_empty(): void
    {
        $service = $this->makeService();

        $service->sendLoginFailureEmailAlertToAddress(
            'client@example.com',
            '张三',
            'client@example.com',
            '2026-08-28 10:00:00',
            '203.0.113.9',
            'Mozilla/5.0'
        );

        $this->assertStringContainsString('上次IP：无历史记录', $this->sentHtml());
    }
}
