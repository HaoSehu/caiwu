<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginPackageHasher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PluginBackfillSourceHashCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dry_run_reports_without_writing(): void
    {
        $plugin = $this->installPluginWithEmptySourceHash();

        $exitCode = Artisan::call('plugins:backfill-source-hash', ['--json' => true]);
        $decoded = json_decode(trim(Artisan::output()), true);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray($decoded);
        $this->assertSame('dry-run', $decoded['mode']);
        $this->assertGreaterThanOrEqual(1, $decoded['pending']);
        $this->assertSame(0, $decoded['written']);
        $this->assertSame('', (string) ($plugin->fresh() ?? $plugin)->source_hash);
    }

    public function test_execute_writes_current_package_hash(): void
    {
        $plugin = $this->installPluginWithEmptySourceHash();

        $exitCode = Artisan::call('plugins:backfill-source-hash', ['--execute' => true, '--json' => true]);
        $decoded = json_decode(trim(Artisan::output()), true);

        $this->assertSame(0, $exitCode);
        $this->assertSame('execute', $decoded['mode']);
        $this->assertGreaterThanOrEqual(1, $decoded['written']);

        $expected = app(PluginPackageHasher::class)->packageHash(base_path('plugins/gateways/demo_pay'));
        $this->assertSame($expected, (string) ($plugin->fresh() ?? $plugin)->source_hash);
    }

    public function test_execute_is_idempotent(): void
    {
        $this->installPluginWithEmptySourceHash();

        Artisan::call('plugins:backfill-source-hash', ['--execute' => true, '--json' => true]);
        Artisan::call('plugins:backfill-source-hash', ['--execute' => true, '--json' => true]);
        $decoded = json_decode(trim(Artisan::output()), true);

        $this->assertSame(0, $decoded['written']);
        $this->assertGreaterThanOrEqual(1, $decoded['unchanged']);
        $this->assertSame(0, $decoded['failed']);
    }

    public function test_dry_run_and_execute_together_are_rejected(): void
    {
        $exitCode = Artisan::call('plugins:backfill-source-hash', ['--dry-run' => true, '--execute' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('不能同时使用', $output);
    }

    private function installPluginWithEmptySourceHash(): IntegrationPlugin
    {
        $installer = app(PluginInstaller::class);
        $plugin = $installer->install('payment', 'demo_pay');
        $plugin->forceFill(['source_hash' => null])->save();

        return $plugin->fresh() ?? $plugin;
    }
}
