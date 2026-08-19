<?php

declare(strict_types=1);

namespace Tests\Feature\Installer;

use App\Services\Installer\DatabaseSetupService;
use Tests\TestCase;

class InstallerDatabaseVerifyApiTest extends TestCase
{
    public function test_database_verify_failure_uses_error_envelope(): void
    {
        $this->app->instance(DatabaseSetupService::class, new class extends DatabaseSetupService
        {
            public function verify(array $data): array
            {
                throw new \RuntimeException('数据库连接失败');
            }
        });
        $this->postJson('/install/api/database/verify', ['host' => '127.0.0.1', 'port' => 3306, 'database' => 'idc_test', 'username' => 'idc', 'password' => ''])->assertStatus(422)->assertJsonPath('code', 42200);
    }
}
