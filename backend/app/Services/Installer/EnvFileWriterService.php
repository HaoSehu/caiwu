<?php

declare(strict_types=1);

namespace App\Services\Installer;

use RuntimeException;

class EnvFileWriterService
{
    public function write(array $values): string
    {
        $target = (string) config('installer.env_path', base_path('.env'));
        $template = base_path('.env.example');
        if (! is_file($template)) {
            throw new RuntimeException('.env.example 不存在');
        }
        if (is_file($target)) {
            $backupDir = (string) config('installer.backup_path');
            if (! is_dir($backupDir)) {
                mkdir($backupDir, 0750, true);
            }
            copy($target, $backupDir.DIRECTORY_SEPARATOR.'.env.'.date('YmdHis').'-'.bin2hex(random_bytes(3)).'.bak');
        }
        $content = (string) file_get_contents($template);
        $values = array_merge(['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32))], $values);
        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format((string) $value);
            $pattern = '/^'.preg_quote((string) $key, '/').'=.*$/m';
            $content = preg_match($pattern, $content) ? preg_replace($pattern, $line, $content) : $content."\n{$line}\n";
        }
        $directory = dirname($target);
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        if (file_put_contents($target, $content, LOCK_EX) === false) {
            throw new RuntimeException('写入 .env 失败');
        }

        return $target;
    }

    private function format(string $value): string
    {
        return $value === '' || preg_match('/[^A-Za-z0-9_+.:\/-]/u', $value) ? '"'.str_replace('"', '\\"', $value).'"' : $value;
    }
}
