<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PluginRegistrationAuditCommandTest extends TestCase
{
    public function test_audit_command_json_output_matches_exit_code(): void
    {
        $exitCode = Artisan::call('plugins:audit-registrations', ['--json' => true]);
        $decoded = json_decode(trim(Artisan::output()), true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('violations', $decoded);
        $this->assertArrayHasKey('count', $decoded);
        $this->assertSame(count($decoded['violations']), $decoded['count']);

        // 有违规返回 1、无违规返回 0，两种环境一致可判定。
        $this->assertSame($decoded['violations'] === [] ? 0 : 1, $exitCode);
    }

    public function test_audit_command_human_output_reflects_result(): void
    {
        $exitCode = Artisan::call('plugins:audit-registrations');
        $output = Artisan::output();

        if ($exitCode === 0) {
            $this->assertStringContainsString('审计通过', $output);
        } else {
            $this->assertStringContainsString('extra.console_commands', $output);
        }
    }
}
