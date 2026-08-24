<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminUser;
use App\Services\Admin\Rbac\BuiltinAdminRoleService;
use App\Services\Installer\DatabaseSetupService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallSystemCommand extends Command
{
    protected $signature = 'app:install';

    protected $description = '初始化系统数据库和管理员账号';

    public function handle(DatabaseSetupService $database, BuiltinAdminRoleService $roles): int
    {
        $username = trim((string) env('INSTALL_ADMIN_USERNAME', 'cerbo'));
        $password = (string) env('INSTALL_ADMIN_PASSWORD', '');
        if (config('app.env') === 'production' && ($password === '' || strlen($password) < 12 || in_array($password, ['password', '123456789012'], true))) {
            $this->error('生产环境必须通过 INSTALL_ADMIN_PASSWORD 配置至少 12 位非默认密码');

            return self::FAILURE;
        }
        if ($password === '') {
            $password = 'cerbo-install-'.bin2hex(random_bytes(8));
        }
        try {
            $database->verify(config('database.connections.mysql'));
            $tableCount = (int) DB::selectOne('SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = ?', [DB::getDatabaseName()])->count;
            if ($tableCount === 0) {
                $path = database_path('schema/mysql-schema.sql');
                DB::unprepared((string) file_get_contents($path));
            } else {
                Artisan::call('migrate', ['--force' => true]);
            }
            $this->optimizeTables();
            if (Schema::hasTable('settings')) {
                SettingsSeeder::seed();
            }
            $roles->sync();
            $roleId = (int) DB::table('roles')->where('name', 'super_admin')->value('id');
            $admin = AdminUser::query()->firstOrNew(['username' => $username]);
            $isNewAdmin = ! $admin->exists;
            if ($isNewAdmin) {
                $admin->password = $password;
            }
            $admin->role_id = $roleId > 0 ? $roleId : null;
            $admin->status = 1;
            $admin->save();
            if (Schema::hasTable('admin_user_roles') && $roleId > 0) {
                DB::table('admin_user_roles')->upsert([['admin_user_id' => $admin->id, 'role_id' => $roleId]], ['admin_user_id', 'role_id'], []);
            }
            $this->info('系统安装完成');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('系统安装失败：'.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function optimizeTables(): void
    {
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_type', 'BASE TABLE')
            ->pluck('table_name');
        foreach ($tables as $table) {
            DB::statement('OPTIMIZE TABLE `'.str_replace('`', '``', (string) $table).'`');
        }
    }
}
