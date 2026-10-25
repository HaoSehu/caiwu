<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * db:seed 的默认入口。
 *
 * 标准 Laravel 播种入口是 `php artisan db:seed`（`php artisan migrate --seed` 同样会走到这里），
 * 但本项目在 run() 中保持为空：默认配置由 SettingsSeeder 直接提供，不通过 db:seed 触发。
 *
 * 新环境的系统初始化请使用数据库安装脚本（`python backend/scripts/install_db.py`，
 * 或 `bash backend/scripts/install_db.sh`），它会依次完成：建库 → 生成 APP_KEY →
 * 导入 schema baseline → 执行迁移 → 采纳升级账本（`db:upgrade --adopt`）→
 * 通过 `php artisan tinker --execute` 调用 SettingsSeeder::seed() 写入默认配置 →
 * 创建默认管理员 cerbo（口令由 INSTALL_ADMIN_PASSWORD 指定）。
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 默认不写入任何数据；系统初始化请使用 backend/scripts/install_db 脚本，默认配置见 SettingsSeeder。
    }
}
