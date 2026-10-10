<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\PluginRegistrationAuditCommand;
use App\Services\Integrations\Plugins\PluginConsoleRegistrationAuditor;
use Tests\Fixtures\Plugins\FakePluginCommand;
use Tests\TestCase;

class PluginConsoleRegistrationAuditorTest extends TestCase
{
    private PluginConsoleRegistrationAuditor $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditor = new PluginConsoleRegistrationAuditor;
    }

    public function test_is_under_root_handles_separators_and_boundaries(): void
    {
        $this->assertTrue($this->auditor->isUnderRoot('/var/www/backend/plugins/x/Y.php', '/var/www/backend/plugins'));
        $this->assertTrue($this->auditor->isUnderRoot('E:\\caiwu\\backend\\plugins\\x\\Y.php', 'E:/caiwu/backend/plugins'));
        $this->assertTrue($this->auditor->isUnderRoot('/var/www/backend/plugins/x/Y.php', '/var/www/backend/plugins/'));
        $this->assertFalse($this->auditor->isUnderRoot('/var/www/backend/plugins2/Y.php', '/var/www/backend/plugins'));
        $this->assertFalse($this->auditor->isUnderRoot('/var/www/backend/plugins', '/var/www/backend/plugins'));
        $this->assertFalse($this->auditor->isUnderRoot('/var/www/backend/plugins/Y.php', ''));
    }

    public function test_violations_for_detects_command_defined_under_plugins_root(): void
    {
        $violations = $this->auditor->violationsFor(
            ['fake-plugin:demo' => new FakePluginCommand],
            base_path('tests/Fixtures'),
        );

        $this->assertCount(1, $violations);
        $this->assertSame('fake-plugin:demo', $violations[0]['name']);
        $this->assertSame(FakePluginCommand::class, $violations[0]['class']);
        $this->assertStringContainsString('Fixtures', $violations[0]['file']);
    }

    public function test_violations_for_ignores_core_commands(): void
    {
        $violations = $this->auditor->violationsFor(
            ['plugins:audit-registrations' => new PluginRegistrationAuditCommand],
            base_path('plugins'),
        );

        $this->assertSame([], $violations);
    }
}
