<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 服务状态五态化：0开通中/1运行中/2已暂停/3已删除/4异常。
 *
 * 原 3=已到期 已无运行时写入者（到期流水线为 SUSPENDED(reason=expired) → 宽限期后取消），
 * 值位重定义为 3=已删除；原 4=已取消 语义并入已删除，存量 4 订正为 3，值位 4 释放为异常。
 *
 * 注意：状态归并不再保留旧语义，down 仅恢复列注释。
 */
return new class extends Migration
{
    private const COLUMN_TYPE = 'tinyint NOT NULL DEFAULT 0';

    private const COMMENT = '服务状态：0开通中 1运行中 2已暂停 3已删除 4异常';

    private const RESTORE_COMMENT = '服务状态：0待开通 1运行中 2已暂停 3已到期 4已取消';

    public function up(): void
    {
        if (Schema::hasTable('services') && Schema::hasColumn('services', 'status')) {
            // 先订正存量：已取消(4) → 已删除(3)。原 3(已到期) 存量为零，单条订正无冲突。
            DB::table('services')
                ->where('status', 4)
                ->update(['status' => 3]);

            $comment = str_replace("'", "''", self::COMMENT);
            DB::statement('ALTER TABLE `services` MODIFY `status` '.self::COLUMN_TYPE." COMMENT '{$comment}'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('services') || ! Schema::hasColumn('services', 'status')) {
            return;
        }

        $comment = str_replace("'", "''", self::RESTORE_COMMENT);
        DB::statement('ALTER TABLE `services` MODIFY `status` '.self::COLUMN_TYPE." COMMENT '{$comment}'");
    }
};
