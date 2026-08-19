<?php

declare(strict_types=1);

namespace App\Services\Installer;

use RuntimeException;

class InstallerStateService
{
    public function lockPath(): string
    {
        return (string) config('installer.lock_path');
    }

    public function isInstalled(): bool
    {
        return is_file($this->lockPath());
    }

    public function assertNotInstalled(): void
    {
        if ($this->isInstalled()) {
            throw new RuntimeException('系统已经安装完成');
        }
    }

    public function markInstalled(string $adminUsername): void
    {
        $path = $this->lockPath();
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('无法创建安装锁目录');
        }

        $payload = json_encode(['installed_at' => now()->toIso8601String(), 'admin_username' => $adminUsername], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false || file_put_contents($path, $payload, LOCK_EX) === false) {
            throw new RuntimeException('无法写入安装锁');
        }
    }
}
