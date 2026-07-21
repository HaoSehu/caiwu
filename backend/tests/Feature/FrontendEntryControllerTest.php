<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FrontendEntryControllerTest extends TestCase
{
    private string $entryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'caiwu-frontend-entries-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->entryDirectory);

        $entries = [];
        foreach (['site', 'client', 'admin'] as $application) {
            $entryPath = $this->entryDirectory.DIRECTORY_SEPARATOR.$application.'.html';
            File::put($entryPath, "<!doctype html><title>{$application}</title>");
            $entries[$application] = $entryPath;
        }

        config()->set('frontend.entries', $entries);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->entryDirectory);

        parent::tearDown();
    }

    public function test_site_deep_link_returns_the_site_entry(): void
    {
        $response = $this->get('/products/cloud-server')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_client_and_admin_deep_links_return_their_own_entries(): void
    {
        $clientResponse = $this->get('/client/orders/42')->assertOk();
        $this->assertStringContainsString('no-store', (string) $clientResponse->headers->get('Cache-Control'));

        $adminResponse = $this->get('/admin/users/42')->assertOk();
        $this->assertStringContainsString('no-store', (string) $adminResponse->headers->get('Cache-Control'));
    }

    public function test_api_requests_are_not_captured_by_the_site_spa_route(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', 'ok');
    }

    public function test_plugin_bridge_requests_are_not_captured_by_the_site_spa_route(): void
    {
        $this->get('/zjmf/v1/not-a-real-route')->assertNotFound();
    }

    public function test_missing_entry_returns_not_found(): void
    {
        config()->set('frontend.entries.client', $this->entryDirectory.DIRECTORY_SEPARATOR.'missing.html');

        $this->get('/client/login')->assertNotFound();
    }
}
