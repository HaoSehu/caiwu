<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 保留历史映射列。生产迁移不得删除无法从现有业务数据重建的映射关系。
    }

    public function down(): void
    {
        // up() 不修改结构，无需回滚。
    }
};
