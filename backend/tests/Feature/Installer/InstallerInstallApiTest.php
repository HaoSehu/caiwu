<?php

declare(strict_types=1);

namespace Tests\Feature\Installer;

use App\Services\Installer\InstallRunnerService;
use Tests\TestCase;

class InstallerInstallApiTest extends TestCase
{
    public function test_install_endpoint_returns_admin_projection_without_password(): void
    {
        $this->app->instance(InstallRunnerService::class, new class extends InstallRunnerService
        {
            public function __construct() {}

            public function run(array $data): array
            {
                return ['admin_username' => 'cerbo', 'admin_url' => 'http://admin.example.test', 'steps' => []];
            }
        });
        $payload = ['app_name' => '创欧云', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'idc_test', 'username' => 'idc', 'password' => '', 'app_url' => 'http://api.example.test', 'frontend_url' => 'http://www.example.test', 'client_console_url' => 'http://console.example.test', 'admin_url' => 'http://admin.example.test', 'admin_username' => 'cerbo', 'admin_password' => 'a-secure-password-123'];
        $this->postJson('/install/api/install', $payload)->assertOk()->assertJsonPath('data.admin_username', 'cerbo')->assertJsonMissingPath('data.admin_password');
    }
}
