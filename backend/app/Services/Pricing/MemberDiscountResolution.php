<?php

declare(strict_types=1);

namespace App\Services\Pricing;

/**
 * 用户当前命中的会员折扣解析结果。
 *
 * @param  int|null  $levelId  当前生效等级 ID，null 表示无生效等级
 * @param  string|null  $levelName  当前生效等级名称
 * @param  array<string, array{group_id: int, type: int, value: float, group_name: string}>  $rules  以营销组 ID 为键的规则表
 */
final class MemberDiscountResolution
{
    /**
     * @param  array<string, array{group_id: int, type: int, value: float, group_name: string}>  $rules
     */
    public function __construct(
        public readonly ?int $levelId,
        public readonly ?string $levelName,
        public readonly array $rules = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }
}
