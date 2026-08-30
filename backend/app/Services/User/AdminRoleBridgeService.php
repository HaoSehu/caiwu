<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\AdminUser;
use App\Support\SchemaMetadataCache;
use Illuminate\Support\Facades\DB;

class AdminRoleBridgeService
{
    public function syncPrimaryRole(AdminUser $admin, ?int $roleId = null): void
    {
        if (! $admin->exists || ! SchemaMetadataCache::hasTable('admin_user_roles')) {
            return;
        }

        $resolvedRoleId = $roleId ?? (int) ($admin->role_id ?? 0);
        DB::table('admin_user_roles')
            ->where('admin_user_id', (int) $admin->id)
            ->delete();

        if ($resolvedRoleId <= 0) {
            return;
        }

        DB::table('admin_user_roles')->insert([
            'admin_user_id' => (int) $admin->id,
            'role_id' => $resolvedRoleId,
        ]);
    }
}
