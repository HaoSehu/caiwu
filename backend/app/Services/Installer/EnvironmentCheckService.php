<?php

declare(strict_types=1);

namespace App\Services\Installer;

class EnvironmentCheckService
{
    public function check(): array
    {
        $items = [];
        $items[] = $this->item('PHP 版本', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION, '必需');
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'curl', 'fileinfo'] as $extension) {
            $items[] = $this->item('扩展 '.$extension, extension_loaded($extension), extension_loaded($extension) ? '已安装' : '未安装', '必需');
        }
        foreach (['gd', 'zip', 'bcmath'] as $extension) {
            $items[] = $this->item('扩展 '.$extension, extension_loaded($extension), extension_loaded($extension) ? '已安装' : '未安装', '建议');
        }
        $mysqldumpAvailable = $this->mysqldumpAvailable();
        $items[] = $this->item('mysqldump', $mysqldumpAvailable, $mysqldumpAvailable ? '可用' : '未找到', '建议');
        foreach ($this->directories() as $label => $path) {
            $items[] = $this->item($label.' 可写', is_dir($path) && is_writable($path), $path, '必需');
        }
        $items[] = $this->item('upload_max_filesize', true, (string) ini_get('upload_max_filesize'), '信息');

        return $items;
    }

    public function passed(): bool
    {
        return collect($this->check())->where('level', '必需')->every(fn (array $item) => $item['passed']);
    }

    private function directories(): array
    {
        return [
            'storage' => storage_path(),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'backend' => base_path(),
        ];
    }

    private function mysqldumpAvailable(): bool
    {
        $configured = env('MYSQLDUMP_PATH');
        if (is_string($configured) && $configured !== '') {
            return is_file($configured);
        }
        if (! function_exists('exec')) {
            return false;
        }
        exec(PHP_OS_FAMILY === 'Windows' ? 'where mysqldump 2>NUL' : 'command -v mysqldump', $output, $code);

        return $code === 0 && $output !== [];
    }

    private function item(string $name, bool $passed, string $detail, string $level): array
    {
        return compact('name', 'passed', 'detail', 'level');
    }
}
