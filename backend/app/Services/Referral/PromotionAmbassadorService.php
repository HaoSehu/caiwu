<?php

declare(strict_types=1);

namespace App\Services\Referral;

use App\Exceptions\BusinessException;
use App\Models\PromotionAmbassador;
use App\Models\User;
use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 推广大使管理：大使为新购/续费返利比例档位（名称/新购返利/续费返利/启停），
 * 继承原会员等级的邀请返利职责；用户档位由管理员在用户详情页指派
 * （UserService::adjustPromotionAmbassador），未指派分别回退全局 referral.reward_rate 与
 * referral.renewal_reward_rate。
 */
class PromotionAmbassadorService
{
    private const LIST_CACHE_TTL_SECONDS = 300; // 5分钟：大使档位不频繁变化，可适当延长

    /**
     * @return Collection<int, PromotionAmbassador>
     */
    public function list(bool $enabledOnly = false): Collection
    {
        return Cache::remember(
            $this->buildListCacheKey($enabledOnly),
            now()->addSeconds(self::LIST_CACHE_TTL_SECONDS),
            fn () => PromotionAmbassador::query()
                ->when($enabledOnly, fn ($query) => $query->enabled())
                ->orderBy('id')
                ->get()
        );
    }

    public function create(array $data): PromotionAmbassador
    {
        $ambassador = DB::transaction(function () use ($data) {
            return PromotionAmbassador::query()->create($this->preparePayload($data));
        });

        $this->forgetListCaches();

        return $ambassador;
    }

    public function update(PromotionAmbassador $ambassador, array $data): PromotionAmbassador
    {
        $updated = DB::transaction(function () use ($ambassador, $data) {
            $ambassador->update($this->preparePayload($data, $ambassador));

            return $ambassador->refresh();
        });

        $this->forgetListCaches();

        return $updated;
    }

    public function delete(PromotionAmbassador $ambassador): void
    {
        throw_if(
            User::query()->where('promotion_ambassador_id', $ambassador->id)->exists(),
            new BusinessException('当前大使档位下仍有用户，无法删除'),
        );

        $ambassador->delete();
        $this->forgetListCaches();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preparePayload(array $data, ?PromotionAmbassador $ambassador = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        throw_if($name === '', new BusinessException('大使名称不能为空'));

        $rewardRate = round((float) ($data['reward_rate'] ?? 0), 2);
        throw_if($rewardRate < 0 || $rewardRate > 100, new BusinessException('新购返利比例必须在 0-100 之间'));

        // 续费比例缺省继承原值：与 reward_rate 的“缺省归 0”不同，避免编辑漏传时重置运营配置
        $renewalRewardRate = round((float) ($data['renewal_reward_rate'] ?? $ambassador->renewal_reward_rate ?? 0), 2);
        throw_if($renewalRewardRate < 0 || $renewalRewardRate > 100, new BusinessException('续费返利比例必须在 0-100 之间'));

        $nameExists = PromotionAmbassador::query()
            ->when($ambassador?->id, fn ($query) => $query->where('id', '!=', $ambassador->id))
            ->where('name', $name)
            ->exists();
        throw_if($nameExists, new BusinessException('大使名称已存在'));

        $inheritedStatus = $ambassador?->status;

        return [
            'name' => $name,
            'reward_rate' => $rewardRate,
            'renewal_reward_rate' => $renewalRewardRate,
            'status' => (int) (($data['status'] ?? $inheritedStatus ?? 1) ? 1 : 0),
            // 显式传 null 表示清空备注，不能用 ?? 回退原值
            'remark' => $this->normalizeNullableString(
                array_key_exists('remark', $data) ? $data['remark'] : $ambassador?->remark
            ),
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function buildListCacheKey(bool $enabledOnly): string
    {
        return CacheKey::promotionAmbassadors($enabledOnly);
    }

    private function forgetListCaches(): void
    {
        Cache::forget(CacheKey::promotionAmbassadors(false));
        Cache::forget(CacheKey::promotionAmbassadors(true));
    }
}
