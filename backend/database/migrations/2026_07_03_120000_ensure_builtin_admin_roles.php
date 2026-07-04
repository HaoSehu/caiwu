<?php

use App\Services\Admin\Rbac\BuiltinAdminRoleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        app(BuiltinAdminRoleService::class)->sync();
    }

    public function down(): void
    {
        // 系统默认角色是运行时契约的一部分，回滚迁移不删除业务角色数据。
    }
};
