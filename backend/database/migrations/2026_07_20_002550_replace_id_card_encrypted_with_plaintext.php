<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 历史证件号不得在结构迁移中被随机值覆盖；数据脱敏必须使用独立、可审计的受控流程。
    }

    public function down(): void
    {
        // up() 不修改数据，无需回滚。
    }
};
