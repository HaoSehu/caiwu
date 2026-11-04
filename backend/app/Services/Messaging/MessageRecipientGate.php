<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\AdminUser;
use App\Models\User;

/**
 * 收件人状态闸门：停用账号不再接收任何渠道消息。
 *
 * 与 howstack DatabaseMessageRecipientDirectory::active 等价：
 * 客户查 users.status，管理员查 admin_users.status，机器人群恒为 true。
 */
final class MessageRecipientGate
{
    public function active(string $audience, int $accountId): bool
    {
        if ($audience === 'robot') {
            return true;
        }

        if ($accountId <= 0) {
            return false;
        }

        if ($audience === 'customer') {
            $user = User::query()->find($accountId, ['id', 'status']);

            return $user instanceof User && (int) $user->status === 1;
        }

        $admin = AdminUser::query()->find($accountId, ['id', 'status']);

        return $admin instanceof AdminUser && (int) $admin->status === 1;
    }
}
