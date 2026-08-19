<?php

declare(strict_types=1);

namespace Tests\Feature\Installer;

use Tests\TestCase;

class InstallerStatusApiTest extends TestCase
{
    public function test_status_reports_uninstalled_without_lock(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'installer-lock-');
        @unlink($path);
        config(['installer.lock_path' => $path]);
        $this->getJson('/install/api/status')->assertOk()->assertJsonPath('data.installed', false);
    }

    public function test_installer_api_is_not_available_after_lock(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'installer-lock-');
        file_put_contents($path, '{}');
        config(['installer.lock_path' => $path]);
        $this->getJson('/install/api/environment')->assertNotFound();
        @unlink($path);
    }
}
