<?php

declare(strict_types=1);

namespace Tests\Unit\Installer;

use App\Services\Installer\EnvFileWriterService;
use Tests\TestCase;

class EnvFileWriterServiceTest extends TestCase
{
    public function test_writer_replaces_values_and_backs_up_existing_env(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'installer-'.bin2hex(random_bytes(4));
        mkdir($directory, 0750, true);
        $target = $directory.DIRECTORY_SEPARATOR.'.env';
        file_put_contents($target, "APP_NAME=old\nAPP_KEY=old-key\n");
        config(['installer.env_path' => $target, 'installer.backup_path' => $directory.DIRECTORY_SEPARATOR.'backups']);
        (new EnvFileWriterService)->write(['APP_NAME' => '新站点', 'APP_KEY' => 'base64:test']);
        $this->assertStringContainsString('APP_NAME="新站点"', (string) file_get_contents($target));
        $this->assertNotEmpty(glob($directory.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'.*.bak'));
        @unlink($target);
        array_map('unlink', glob($directory.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'.*.bak') ?: []);
        @rmdir($directory.DIRECTORY_SEPARATOR.'backups');
        @rmdir($directory);
    }
}
