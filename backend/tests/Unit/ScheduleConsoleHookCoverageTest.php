<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class ScheduleConsoleHookCoverageTest extends TestCase
{
    public function test_console_schedule_does_not_register_commands_that_bypass_hooks(): void
    {
        $source = file_get_contents(base_path('routes/console.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('Schedule::command(', $source);
    }
}
