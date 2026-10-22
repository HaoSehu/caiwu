<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminUser;
use App\Services\Auth\AdminMfaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 管理员双因素认证应急解绑（SEC-1）。
 *
 * 管理员手机丢失、认证器被清空、恢复码也没有时，账号会被永久锁在双因素门外。
 * 本命令是唯一不依赖密码与动态码的逃生口——只能在服务器 shell 上执行，
 * 因此要显式 --force 才会真正写库，默认只做检测与提示。
 */
class DisableAdminMfaCommand extends Command
{
    protected $signature = 'admin:disable-mfa
        {username : 管理员登录名}
        {--force : 确认执行解绑（默认只检查不写库）}';

    protected $description = '应急解绑指定管理员的双因素认证，需 --force 确认';

    public function handle(AdminMfaService $mfaService): int
    {
        $username = trim((string) $this->argument('username'));
        if ($username === '') {
            $this->error('请提供管理员登录名。');

            return self::FAILURE;
        }

        try {
            $admin = AdminUser::query()->where('username', $username)->first();
        } catch (Throwable $exception) {
            Log::warning('[管理员双因素] 应急解绑：查询管理员失败', [
                'username' => $username,
                'message' => $exception->getMessage(),
            ]);
            $this->error('查询管理员失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        if ($admin === null) {
            $this->error('管理员不存在：'.$username);

            return self::FAILURE;
        }

        if (! $admin->hasMfaEnabled()) {
            $this->info('管理员 '.$username.' 未启用双因素认证，无需处理。');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force')) {
            $this->warn('管理员 '.$username.' 已启用双因素认证。');
            $this->line('本操作会清除其 TOTP 密钥与全部恢复码，使其下次登录只需密码。');
            $this->line('确认执行请追加 --force。');

            return self::SUCCESS;
        }

        try {
            $mfaService->forceDisable($admin, 'artisan:admin:disable-mfa');
        } catch (Throwable $exception) {
            Log::warning('[管理员双因素] 应急解绑失败', [
                'username' => $username,
                'message' => $exception->getMessage(),
            ]);
            $this->error('解绑失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('已清除管理员 '.$username.' 的双因素认证配置。');

        return self::SUCCESS;
    }
}
