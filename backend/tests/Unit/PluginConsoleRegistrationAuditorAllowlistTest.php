<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\Plugins\PluginConsoleRegistrationAuditor;
use Tests\Fixtures\Plugins\FakePluginCommand;
use Tests\TestCase;

class PluginConsoleRegistrationAuditorAllowlistTest extends TestCase
{
    private PluginConsoleRegistrationAuditor $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditor = new PluginConsoleRegistrationAuditor;
    }

    public function test_allowlisted_plugin_commands_are_not_violations(): void
    {
        $violations = $this->auditor->violationsFor(
            ['fake-plugin:demo' => new FakePluginCommand],
            base_path('tests/Fixtures'),
            [FakePluginCommand::class],
        );

        $this->assertSame([], $violations);
    }

    public function test_unlisted_plugin_commands_still_violate(): void
    {
        $violations = $this->auditor->violationsFor(
            ['fake-plugin:demo' => new FakePluginCommand],
            base_path('tests/Fixtures'),
            ['Some\\Other\\Command'],
        );

        $this->assertCount(1, $violations);
        $this->assertSame(FakePluginCommand::class, $violations[0]['class']);
    }
}
