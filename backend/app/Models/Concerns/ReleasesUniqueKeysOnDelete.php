<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * 软删时释放全局唯一键。
 *
 * 背景：应用层唯一性检查（如用户注册、文章 slug 校验）走 Eloquent 的
 * SoftDeletes 全局作用域，把软删行视为"可复用"；而 MySQL 唯一索引不感知
 * 软删，同一列一旦写过就永久占用。二者语义矛盾会在"软删后重建同 email /
 * phone / slug"时抛出裸的重复键异常。
 *
 * 本 trait 在 `deleting` 事件中把需要释放的唯一列改写为 `del-<id>` 占位值，
 * 让数据库层与应用层"软删后可复用"的语义一致。占位值刻意保持极短：唯一列
 * 宽度差异很大（users.referral_code 只有 varchar(24)），拼上列名会溢出。
 * 占位值不可逆（原值只保留在调用方的审计日志里）。本仓库对这三类模型
 * 均未提供 restore 入口，因此不实现还原；如需恢复软删记录，须先由运维
 * 从审计日志取回原始值再手工改写。
 *
 * 注意：调用方删除操作应包在 DB::transaction 中，保证唯一列改写与
 * deleted_at 写入原子；不适用物理删除（forceDelete 直接跳过）。
 */
trait ReleasesUniqueKeysOnDelete
{
    public static function bootReleasesUniqueKeysOnDelete(): void
    {
        static::deleting(function (self $model): void {
            if ($model->isForceDeleting()) {
                return;
            }

            $values = [];
            foreach ($model->uniqueKeysToReleaseOnDelete() as $column) {
                $current = $model->getRawOriginal($column);
                if ($current === null || $current === '') {
                    continue;
                }
                $values[$column] = $model->buildReleasedUniqueValue($column, (string) $current);
            }

            if ($values === []) {
                return;
            }

            DB::table($model->getTable())
                ->where($model->getKeyName(), $model->getKey())
                ->update($values);
        });
    }

    /**
     * 软删时需要释放全局唯一键的列名。
     *
     * @return array<int, string>
     */
    abstract public function uniqueKeysToReleaseOnDelete(): array;

    protected function buildReleasedUniqueValue(string $column, string $current): string
    {
        // 唯一列宽度差异很大（referral_code 仅 varchar(24)），占位值必须极短；
        // 主键在同一列内天然唯一，够用且不会溢出。
        return 'del-'.(string) ($this->getKey() ?? '');
    }
}
