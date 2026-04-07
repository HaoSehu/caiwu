<?php

namespace App\Models;

use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'permissions'];

    private static ?bool $rolePermissionsTableAvailable = null;
    private static ?bool $permissionsTableAvailable = null;

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $role): void {
            if (! $role->exists || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions')) {
                return;
            }

            $permissionKeys = AdminPermissions::resolveRolePermissions(
                roleName: (string) $role->name,
                storedPermissions: (array) ($role->permissions ?? [])
            );

            foreach ($permissionKeys as $permissionKey) {
                DB::table('permissions')->updateOrInsert(
                    ['permission_key' => $permissionKey],
                    [
                        'module' => self::detectPermissionModule($permissionKey),
                        'name' => self::buildPermissionName($permissionKey),
                        'description' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('permission_key', $permissionKeys)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            DB::table('role_permissions')
                ->where('role_id', $role->id)
                ->delete();

            if ($permissionIds === []) {
                return;
            }

            DB::table('role_permissions')->insert(
                array_map(
                    fn (int $permissionId) => [
                        'role_id' => (int) $role->id,
                        'permission_id' => $permissionId,
                    ],
                    $permissionIds
                )
            );
        });
    }

    public function adminUsers()
    {
        return $this->hasMany(AdminUser::class);
    }

    public function permissionItems()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function resolvedPermissions(): array
    {
        if (self::permissionRelationTablesAvailable()) {
            $permissions = $this->resolvePermissionsFromRelationTable();
            if ($permissions !== []) {
                return AdminPermissions::resolveRolePermissions(
                    roleName: (string) $this->name,
                    storedPermissions: $permissions
                );
            }
        }

        return AdminPermissions::resolveRolePermissions(
            roleName: (string) $this->name,
            storedPermissions: (array) ($this->permissions ?? [])
        );
    }

    /**
     * @return string[]
     */
    private function resolvePermissionsFromRelationTable(): array
    {
        if ($this->relationLoaded('permissionItems')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, Permission> $permissionItems */
            $permissionItems = $this->getRelation('permissionItems');

            return $permissionItems
                ->pluck('permission_key')
                ->filter(fn ($key) => is_string($key) && trim($key) !== '')
                ->values()
                ->all();
        }

        if (! $this->exists) {
            return [];
        }

        return $this->permissionItems()
            ->pluck('permission_key')
            ->filter(fn ($key) => is_string($key) && trim($key) !== '')
            ->values()
            ->all();
    }

    private static function detectPermissionModule(string $permissionKey): string
    {
        if ($permissionKey === AdminPermissions::ALL) {
            return 'system';
        }

        $parts = explode('.', $permissionKey);

        return $parts[0] !== '' ? $parts[0] : 'system';
    }

    private static function buildPermissionName(string $permissionKey): string
    {
        if ($permissionKey === AdminPermissions::ALL) {
            return '全部权限';
        }

        return str_replace('.', ' / ', $permissionKey);
    }

    private static function permissionRelationTablesAvailable(): bool
    {
        return self::rolePermissionsTableAvailable() && self::permissionsTableAvailable();
    }

    private static function rolePermissionsTableAvailable(): bool
    {
        if (self::$rolePermissionsTableAvailable === null) {
            self::$rolePermissionsTableAvailable = Schema::hasTable('role_permissions');
        }

        return self::$rolePermissionsTableAvailable;
    }

    private static function permissionsTableAvailable(): bool
    {
        if (self::$permissionsTableAvailable === null) {
            self::$permissionsTableAvailable = Schema::hasTable('permissions');
        }

        return self::$permissionsTableAvailable;
    }
}
