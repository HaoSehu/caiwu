<?php

declare(strict_types=1);

namespace Tests\Feature\Installer;

use App\Services\Installer\EnvironmentCheckService;
use Tests\TestCase;

class InstallerEnvironmentApiTest extends TestCase
{
    public function test_environment_endpoint_returns_structured_checks(): void
    {
        $this->app->instance(EnvironmentCheckService::class, new class extends EnvironmentCheckService
        {
            public function check(): array
            {
                return [['name' => 'PHP 版本', 'passed' => true, 'detail' => '8.2', 'level' => '必需']];
            }

            public function passed(): bool
            {
                return true;
            }
        });
        $this->getJson('/install/api/environment')->assertOk()->assertJsonPath('data.passed', true)->assertJsonPath('data.items.0.name', 'PHP 版本');
    }
}
