<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * 客群筛选公共组件（M9 批量群发与 M12 客户关怀共用）。
 *
 * filter_json → users 查询构造器 + 分块迭代器。条件键白名单校验，
 * 非法键抛 BusinessException，防止把任意查询条件透传到数据库。
 *
 * 内置条件（全部可选，键不出现即不限制）：
 * - status: int|int[]  账号状态（users.status，1=正常）
 * - verified: bool     实名状态（true=已实名；口径与 User::hasCompletedVerification 一致：
 *                      is_verified=1 或 verification_status=2；false=未实名）
 * - member_level_ids: int[]  会员等级
 * - registered_between: [start, end]  注册时间区间（users.created_at，Y-m-d 或 Y-m-d H:i:s）
 * - last_login_before: string  最近登录早于该时间（users.last_login_at；从未登录
 *                      （last_login_at 为 NULL）视为满足条件，即天然属于流失侧）
 * - product_ids: int[]  名下产品（services.product_id 存在，含任意状态、未软删）
 * - balance_between: [min, max]  现金余额区间（真源 user_accounts.cash_balance）
 */
class UserSegmentService
{
    public const FILTER_KEYS = [
        'status',
        'verified',
        'member_level_ids',
        'registered_between',
        'last_login_before',
        'product_ids',
        'balance_between',
    ];

    /**
     * 构造客群查询（users 表）。
     *
     * @param  array<string, mixed>  $filter
     * @return Builder<User>
     */
    public function resolveBuilder(array $filter): Builder
    {
        $this->assertAllowedKeys($filter);

        return User::query()->where(function (Builder $query) use ($filter): void {
            $this->applyStatus($query, $filter['status'] ?? null);
            $this->applyVerified($query, $filter['verified'] ?? null);
            $this->applyMemberLevels($query, $filter['member_level_ids'] ?? null);
            $this->applyRegisteredBetween($query, $filter['registered_between'] ?? null);
            $this->applyLastLoginBefore($query, $filter['last_login_before'] ?? null);
            $this->applyProductIds($query, $filter['product_ids'] ?? null);
            $this->applyBalanceBetween($query, $filter['balance_between'] ?? null);
        });
    }

    /**
     * 按主键键集分页迭代命中用户（只取发送所需列），供分片派发使用。
     * 语义对齐 chunkById：以 id 游标翻页，避免大偏移量与写入位移导致的漏发/重发。
     *
     * @param  Builder<User>  $builder
     * @return \Generator<int, array{id: int, email: string, nickname: string}>
     */
    public function chunkedUsers(Builder $builder, int $chunkSize = 100): \Generator
    {
        $lastId = 0;
        $chunkSize = max(1, $chunkSize);

        do {
            $users = $builder->clone()
                ->select(['id', 'email', 'nickname'])
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunkSize)
                ->get();

            foreach ($users as $user) {
                $lastId = (int) $user->id;

                yield [
                    'id' => $lastId,
                    'email' => trim((string) $user->email),
                    'nickname' => trim((string) $user->nickname),
                ];
            }
        } while ($users->count() === $chunkSize);
    }

    /**
     * 试算命中数（不落库）。
     *
     * @param  array<string, mixed>  $filter
     */
    public function countHits(array $filter): int
    {
        return (int) $this->resolveBuilder($filter)->count();
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function assertAllowedKeys(array $filter): void
    {
        $unknown = array_values(array_diff(array_keys($filter), self::FILTER_KEYS));

        if ($unknown !== []) {
            throw new BusinessException('客群条件包含不支持的键：'.implode(', ', $unknown));
        }
    }

    private function applyStatus(Builder $query, mixed $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        $statuses = is_array($status) ? $status : [$status];
        $statuses = array_map(intval(...), $statuses);
        $query->whereIn('status', $statuses);
    }

    private function applyVerified(Builder $query, mixed $verified): void
    {
        if ($verified === null) {
            return;
        }

        $passed = filter_var($verified, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($passed === null) {
            throw new BusinessException('verified 条件必须为布尔值');
        }

        // 实名通过口径与 User::hasCompletedVerification() 保持一致：is_verified=1 或 verification_status=2。
        $expression = '(is_verified = 1 OR verification_status = 2)';
        $passed
            ? $query->whereRaw($expression)
            : $query->whereRaw('NOT ('.$expression.')');
    }

    private function applyMemberLevels(Builder $query, mixed $levelIds): void
    {
        if ($levelIds === null) {
            return;
        }

        $ids = $this->normalizeIntArray($levelIds, 'member_level_ids');
        if ($ids !== []) {
            $query->whereIn('member_level_id', $ids);
        }
    }

    private function applyRegisteredBetween(Builder $query, mixed $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = $this->normalizeRange($range, 'registered_between');
        $this->assertValidDate($start);
        $this->assertValidDate($end);
        $query->whereBetween('created_at', [$start, $end]);
    }

    private function applyLastLoginBefore(Builder $query, mixed $before): void
    {
        if ($before === null || $before === '') {
            return;
        }

        // 绑定前校验格式，防任意字符串进入 SQL 时间比较。
        $this->assertValidDate($before);

        // 从未登录（NULL）视作流失侧，纳入命中。
        $query->where(function (Builder $inner) use ($before): void {
            $inner->whereNull('last_login_at')->orWhere('last_login_at', '<', $before);
        });
    }

    /**
     * 日期参数校验：仅接受 Y-m-d 或 Y-m-d H:i:s 且必须可解析为真实时间。
     */
    private function assertValidDate(mixed $value): void
    {
        if (! is_string($value)
            || preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1
            || strtotime($value) === false) {
            throw new BusinessException('客群条件日期格式不合法');
        }
    }

    private function applyProductIds(Builder $query, mixed $productIds): void
    {
        if ($productIds === null) {
            return;
        }

        $ids = $this->normalizeIntArray($productIds, 'product_ids');
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // 名下产品：services 任意状态（含暂停），排除软删；绑定查询参数防注入。
        $query->whereRaw(
            'EXISTS (SELECT 1 FROM services WHERE services.user_id = users.id '
            ."AND services.deleted_at IS NULL AND services.product_id IN ({$placeholders}))",
            $ids
        );
    }

    private function applyBalanceBetween(Builder $query, mixed $range): void
    {
        if ($range === null) {
            return;
        }

        [$min, $max] = $this->normalizeRange($range, 'balance_between');

        if (! is_numeric($min) || ! is_numeric($max)) {
            throw new BusinessException('balance_between 必须为数值区间');
        }

        // 余额真源为 user_accounts.cash_balance（users 表余额列已删除），用 whereExists 关联。
        $query->whereRaw(
            'EXISTS (SELECT 1 FROM user_accounts WHERE user_accounts.user_id = users.id '
            .'AND user_accounts.cash_balance >= ? AND user_accounts.cash_balance <= ?)',
            [(float) $min, (float) $max]
        );
    }

    /**
     * @return int[]
     */
    private function normalizeIntArray(mixed $value, string $key): array
    {
        $items = is_array($value) ? $value : [$value];
        $ids = [];

        foreach ($items as $item) {
            if (! is_numeric($item)) {
                throw new BusinessException($key.' 必须为整数数组');
            }

            $ids[] = (int) $item;
        }

        return $ids;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizeRange(mixed $value, string $key): array
    {
        if (! is_array($value) || count($value) !== 2) {
            throw new BusinessException($key.' 必须为 [start, end] 二元数组');
        }

        $values = array_values($value);
        foreach ($values as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                throw new BusinessException($key.' 区间元素类型不合法');
            }
        }

        return [(string) $values[0], (string) $values[1]];
    }
}
