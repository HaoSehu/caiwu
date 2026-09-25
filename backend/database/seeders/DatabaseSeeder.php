<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * db:seed 的默认入口。
 *
 * 本项目实际播种入口是 `php artisan install-system`（内部调用 SettingsSeeder::seed()），
 * 这里仅保留空的默认 Seeder 以保证 `db:seed` 命令可执行；按需在 run() 中追加调用。
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 默认不写入任何数据；系统初始化请使用 install-system 命令。
    }
}
