<?php

declare(strict_types=1);

namespace App\Services\Installer;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class InstallRunnerService
{
    public function __construct(private readonly InstallerStateService $state, private readonly DatabaseSetupService $database, private readonly EnvFileWriterService $env) {}

    public function run(array $data): array
    {
        $this->state->assertNotInstalled();
        $db = $this->database->verify($data);
        $this->env->write([
            'APP_NAME' => (string) $data['app_name'], 'APP_URL' => (string) $data['app_url'],
            'FRONTEND_URL' => (string) $data['frontend_url'], 'CLIENT_CONSOLE_URL' => (string) $data['client_console_url'], 'ADMIN_URL' => (string) $data['admin_url'],
            'DB_HOST' => (string) $data['host'], 'DB_PORT' => (string) $data['port'], 'DB_DATABASE' => (string) $data['database'], 'DB_USERNAME' => (string) $data['username'], 'DB_PASSWORD' => (string) $data['password'],
        ]);
        $result = Process::timeout((int) config('installer.timeout', 600))->path(base_path())->env(['INSTALL_ADMIN_USERNAME' => (string) $data['admin_username'], 'INSTALL_ADMIN_PASSWORD' => (string) $data['admin_password']])->run([PHP_BINARY, 'artisan', 'app:install']);
        if (! $result->successful()) {
            throw new RuntimeException('系统安装失败：'.trim($result->errorOutput() ?: $result->output()));
        }
        $this->state->markInstalled((string) $data['admin_username']);

        return ['admin_username' => (string) $data['admin_username'], 'admin_url' => (string) $data['admin_url'], 'steps' => ['database' => $db, 'install' => 'completed']];
    }
}
