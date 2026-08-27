<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\MarketingProductGroup;
use App\Models\MarketingProductGroupItem;
use App\Models\MemberLevel;
use App\Models\MemberLevelGroupDiscount;
use App\Models\User;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

/**
 * 会员等级 × 营销产品组 折扣计价服务。
 *
 * 规则装载按等级缓存，矩阵写入后通过版本号整体失效；
 * 折扣类型语义见 MemberLevelGroupDiscount：type=1 为 bates 语义折后保留比例（90=九折），
 * type=2 为固定金额减免。
 */
class MemberGroupDiscountService
{
    private const CACHE_TTL_SECONDS = 300; // 5分钟，与会员等级列表缓存保持一致

    /** @var array<int, MemberDiscountResolution> 请求内的用户级解析缓存（报价/建单一请求内多次试算复用） */
    private array $resolutionsByUserId = [];

    /**
     * 解析用户当前可用的会员折扣；无等级或等级被禁用时规则集为空。
     */
    public function resolveForUser(User|int $user): MemberDiscountResolution
    {
        if (is_int($user)) {
            if (isset($this->resolutionsByUserId[$user])) {
                return $this->resolutionsByUserId[$user];
            }

            $found = User::query()->find($user);
            if ($found === null) {
                $empty = new MemberDiscountResolution(null, null, []);
                $this->resolutionsByUserId[$user] = $empty;

                return $empty;
            }
            $user = $found;
        }

        $userId = (int) $user->id;
        if (isset($this->resolutionsByUserId[$userId])) {
            return $this->resolutionsByUserId[$userId];
        }

        $levelId = (int) ($user->member_level_id ?? 0);
        if ($levelId <= 0) {
            $empty = new MemberDiscountResolution(null, null, []);
            $this->resolutionsByUserId[$userId] = $empty;

            return $empty;
        }

        $level = MemberLevel::query()->enabled()->whereKey($levelId)->first();
        $resolution = $level === null
            ? new MemberDiscountResolution(null, null, [])
            : new MemberDiscountResolution($level->id, (string) $level->name, $this->rulesForLevel($levelId));
        $this->resolutionsByUserId[$userId] = $resolution;

        return $resolution;
    }

    /**
     * 试算某商品金额可享受的会员折扣；规则未命中返回 null，金额保持原价。
     *
     * @return array{discount_amount: string, final_amount: string, level_id: int, level_name: ?string, snapshot: array<string, mixed>}|null
     */
    public function applyForProduct(User|int $user, int $productId, float $amount): ?array
    {
        $resolution = $this->resolveForUser($user);
        if ($resolution->isEmpty()) {
            return null;
        }

        $hitRules = MarketingProductGroupItem::query()
            ->where('product_id', $productId)
            ->whereIn('marketing_product_group_id', array_keys($resolution->rules))
            ->pluck('marketing_product_group_id')
            ->map(fn ($groupId) => $resolution->rules[(string) $groupId] ?? null)
            ->filter()
            ->all();
        if ($hitRules === []) {
            return null;
        }

        // 同商品命中多个营销组时取减免最大（最终价最低）的一条
        $bestRule = null;
        $bestDiscount = 0.0;
        foreach ($hitRules as $rule) {
            $discount = $this->discountOf($amount, (int) $rule['type'], (float) $rule['value']);
            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $bestRule = $rule;
            }
        }

        if ($bestRule === null || $bestDiscount <= 0.0) {
            return null;
        }

        $clampedDiscount = number_format(min($bestDiscount, max($amount, 0.0)), 2, '.', '');

        return [
            'discount_amount' => $clampedDiscount,
            'final_amount' => number_format(max($amount - $bestDiscount, 0.0), 2, '.', ''),
            'level_id' => $resolution->levelId,
            'level_name' => $resolution->levelName,
            'snapshot' => [
                'member_level_id' => $resolution->levelId,
                'member_level_name' => $resolution->levelName,
                'marketing_product_group_id' => (int) $bestRule['group_id'],
                'group_name' => (string) $bestRule['group_name'],
                'discount_type' => (int) $bestRule['type'],
                'discount_value' => (float) $bestRule['value'],
                'discount_amount' => $clampedDiscount,
            ],
        ];
    }

    /**
     * 矩阵或营销组变更后调用：版本号自增使旧缓存键自然过期。
     */
    public function invalidate(): void
    {
        // forget 后读取侧会固定回写初值，必须改为取旧值自增写回
        $version = (int) Cache::pull(CacheKey::memberGroupDiscountVersion(), 0);
        Cache::forever(CacheKey::memberGroupDiscountVersion(), $version + 1);
    }

    /**
     * @return array<string, array{group_id: int, type: int, value: float, group_name: string}>
     */
    private function rulesForLevel(int $levelId): array
    {
        $version = (int) Cache::get(CacheKey::memberGroupDiscountVersion(), 1);

        return Cache::remember(
            CacheKey::memberGroupRules($version, $levelId),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($levelId): array {
                $rules = [];
                $rows = MemberLevelGroupDiscount::query()
                    ->where('member_level_id', $levelId)
                    ->get();

                if ($rows->isEmpty()) {
                    return $rules;
                }

                $groupNames = MarketingProductGroup::query()
                    ->whereIn('id', $rows->pluck('marketing_product_group_id'))
                    ->pluck('name', 'id');

                foreach ($rows as $row) {
                    $groupId = (int) $row->marketing_product_group_id;
                    $rules[(string) $groupId] = [
                        'group_id' => $groupId,
                        'type' => (int) $row->discount_type,
                        'value' => (float) $row->discount_value,
                        'group_name' => (string) ($groupNames->get($groupId) ?? ''),
                    ];
                }

                return $rules;
            }
        );
    }

    private function discountOf(float $amount, int $type, float $value): float
    {
        if ($amount <= 0.0) {
            return 0.0;
        }

        return match ($type) {
            MemberLevelGroupDiscount::TYPE_PERCENT => round($amount * (100 - min(max($value, 0.0), 100.0)) / 100, 2),
            MemberLevelGroupDiscount::TYPE_FIXED => round(min(max($value, 0.0), $amount), 2),
            default => 0.0,
        };
    }
}
