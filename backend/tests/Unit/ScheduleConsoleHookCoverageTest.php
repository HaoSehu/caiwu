<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class ScheduleConsoleHookCoverageTest extends TestCase
{
    public function test_console_schedule_registers_only_heartbeat_command(): void
    {
        $source = file_get_contents(base_path('routes/console.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("Schedule::command('scheduler:heartbeat')", $source);
        $this->assertStringNotContainsString('queue:work', $source);
        $this->assertStringNotContainsString('db:archive-logs', $source);
    }
}
