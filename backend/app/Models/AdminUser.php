<?php

namespace App\Models;

use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
    use HasApiTokens;

    private static ?bool $adminUserRolesTableAvailable = null;
    private static ?bool $rolePermissionsTableAvailable = null;
    private static ?bool $permissionsTableAvailable = null;

    protected $table = 'admin_users';

    protected $fillable = [
        'username', 'password', 'role_id', 'nickname', 'email',
        'status', 'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'password'      => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $admin): void {
            if (! $admin->exists || ! Schema::hasTable('admin_user_roles')) {
                return;
            }

            $roleId = (int) ($admin->role_id ?? 0);
            if ($roleId <= 0) {
                DB::table('admin_user_roles')
                    ->where('admin_user_id', $admin->id)
                    ->delete();

                return;
            }

            DB::table('admin_user_roles')->updateOrInsert(
                [
                    'admin_user_id' => (int) $admin->id,
                    'role_id' => $roleId,
                ],
                []
            );
        });
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'admin_user_roles');
    }

    public function getDisplayNameAttribute(): string
    {
        $nickname = trim((string) $this->nickname);
        if ($nickname !== '') {
            return $nickname;
        }

        $username = trim((string) $this->username);
        if ($username !== '') {
            return $username;
        }

        return trim((string) ($this->email ?? ''));
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->resolvedPermissions();
        if ($permissions === []) {
            return false;
        }

        return in_array(AdminPermissions::ALL, $permissions, true)
            || in_array($permission, $permissions, true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeWithResolvedPermissionRelations(Builder $query): Builder
    {
        return $query->with([
            'roles.permissionItems',
            'role.permissionItems',
        ]);
    }

    public function resolvedPermissions(): array
    {
        if (self::hasAdminPermissionTables()) {
            $roles = $this->resolveRolesForRead();
            if ($roles !== []) {
                $permissions = collect($roles)
                    ->flatMap(fn (Role $role) => $role->resolvedPermissions())
                    ->filter(fn ($permission) => is_string($permission) && trim($permission) !== '')
                    ->unique()
                    ->values()
                    ->all();

                if ($permissions !== []) {
                    return $permissions;
                }
            }
        }

        return $this->role?->resolvedPermissions() ?? [];
    }

    public function resolvedRoleLabel(): string
    {
        $roles = $this->resolveRolesForRead();
        if ($roles !== []) {
            $primary = $roles[0];

            return trim((string) ($primary->label ?? $primary->name ?? ''));
        }

        return trim((string) ($this->role?->label ?? $this->role?->name ?? ''));
    }

    /**
     * @return Role[]
     */
    private function resolveRolesForRead(): array
    {
        if (! self::adminUserRolesTableAvailable()) {
            return [];
        }

        if ($this->relationLoaded('roles')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, Role> $roles */
            $roles = $this->getRelation('roles');

            return $roles->values()->all();
        }

        return $this->roles()
            ->with('permissionItems')
            ->orderBy('roles.id')
            ->get()
            ->all();
    }

    private static function hasAdminPermissionTables(): bool
    {
        return self::adminUserRolesTableAvailable()
            && self::rolePermissionsTableAvailable()
            && self::permissionsTableAvailable();
    }

    private static function adminUserRolesTableAvailable(): bool
    {
        if (self::$adminUserRolesTableAvailable === null) {
            self::$adminUserRolesTableAvailable = Schema::hasTable('admin_user_roles');
        }

        return self::$adminUserRolesTableAvailable;
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
