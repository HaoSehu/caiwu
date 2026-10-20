<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\AdminLoginLog;
use App\Models\AdminUser;
use App\Support\UserAgentSummary;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 管理员登录尝试审计（成功与失败）。
 *
 * 写入失败仅记录运行日志，不阻断登录业务（与操作日志失败策略一致）。
 */
class AdminLoginLogService
{
    public function recordSuccess(AdminUser $admin, string $ip, ?string $userAgent = null): void
    {
        $this->write([
            'admin_user_id' => (int) $admin->id,
            'username' => (string) $admin->username,
            'nickname' => (string) ($admin->nickname ?? ''),
            'role_name' => $admin->resolvedRoleLabel(),
            'status' => AdminLoginLog::STATUS_SUCCESS,
            'failure_reason' => '',
        ], $ip, $userAgent);
    }

    /**
     * 记录失败的登录尝试。
     *
     * @param  AdminUser|null  $admin  账号存在时传入，用于保留编号与快照；未知账号传 null
     * @param  string  $username  登录时输入的账号（未知账号用于审计追溯）
     */
    public function recordFailure(
        ?AdminUser $admin,
        string $username,
        string $ip,
        ?string $userAgent,
        string $reason,
    ): void {
        $this->write([
            'admin_user_id' => $admin?->id !== null ? (int) $admin->id : null,
            'username' => $admin !== null ? (string) $admin->username : $username,
            // 未知账号的昵称列存当时输入的账号，不回查当前资料（对齐 howstack-bos）。
            'nickname' => $admin !== null ? (string) ($admin->nickname ?? '') : $username,
            'role_name' => $admin?->resolvedRoleLabel() ?? '',
            'status' => AdminLoginLog::STATUS_FAILED,
            'failure_reason' => $reason,
        ], $ip, $userAgent);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(array $attributes, string $ip, ?string $userAgent): void
    {
        try {
            $agent = UserAgentSummary::parse($userAgent);
            // LoginRequest 未限制 username 长度；按列宽截断，避免超长输入触发
            // SQLSTATE 22001 让审计记录整体丢失。
            AdminLoginLog::query()->create([
                'admin_user_id' => $attributes['admin_user_id'] ?? null,
                'username' => mb_substr(trim((string) ($attributes['username'] ?? '')), 0, 64),
                'nickname' => mb_substr(trim((string) ($attributes['nickname'] ?? '')), 0, 64),
                'role_name' => mb_substr(trim((string) ($attributes['role_name'] ?? '')), 0, 64),
                'status' => (int) ($attributes['status'] ?? AdminLoginLog::STATUS_FAILED),
                'failure_reason' => mb_substr(trim((string) ($attributes['failure_reason'] ?? '')), 0, 32),
                'client_ip' => mb_substr(trim($ip), 0, 45),
                'browser' => $agent['browser'],
                'operating_system' => $agent['operating_system'],
                'logged_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('[管理员登录日志] 写入失败', [
                'username' => mb_substr((string) ($attributes['username'] ?? ''), 0, 64),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
