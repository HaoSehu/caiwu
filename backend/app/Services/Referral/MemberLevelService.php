<?php

declare(strict_types=1);

namespace App\Services\Referral;

use App\Exceptions\BusinessException;
use App\Models\MemberLevel;
use App\Models\MemberLevelGroupDiscount;
use App\Models\User;
use App\Models\UserReferral;
use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 会员等级管理：等级为纯手工配置的身份档位（名称/折扣矩阵/启停），
 * 不再按销售额自动定级；邀请返利比例已剥离至推广大使（PromotionAmbassadorService），
 * 用户等级由管理员在用户详情页设置（UserService::adjustMemberLevel）。
 */
class MemberLevelService
{
    private const LIST_CACHE_TTL_SECONDS = 300; // 5分钟：会员等级不频繁变化，可适当延长

    /**
     * @return Collection<int, MemberLevel>
     */
    public function list(bool $enabledOnly = false): Collection
    {
        return Cache::remember(
            $this->buildListCacheKey($enabledOnly),
            now()->addSeconds(self::LIST_CACHE_TTL_SECONDS),
            fn () => MemberLevel::query()
                ->when($enabledOnly, fn ($query) => $query->enabled())
                ->orderBy('id')
                ->get()
        );
    }

    public function create(array $data): MemberLevel
    {
        $level = DB::transaction(function () use ($data) {
            $payload = $this->preparePayload($data);

            return MemberLevel::query()->create($payload);
        });

        $this->forgetListCaches();

        return $level;
    }

    public function update(MemberLevel $level, array $data): MemberLevel
    {
        $updatedLevel = DB::transaction(function () use ($level, $data) {
            $payload = $this->preparePayload($data, $level);
            $level->update($payload);

            return $level->refresh();
        });

        $this->forgetListCaches();

        return $updatedLevel;
    }

    public function delete(MemberLevel $level): void
    {
        throw_if(
            Schema::hasTable('user_referrals')
                ? UserReferral::query()->where('member_level_id', $level->id)->exists()
                : User::query()->where('member_level_id', $level->id)->exists(),
            new BusinessException('当前等级下仍有用户，无法删除'),
        );
        // 折扣矩阵无外键约束，直接删除会留下孤儿死规则进缓存参与计价
        throw_if(
            MemberLevelGroupDiscount::query()->where('member_level_id', $level->id)->exists(),
            new BusinessException('当前等级已配置折扣矩阵，请先清空后再删除'),
        );

        $level->delete();
        $this->forgetListCaches();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preparePayload(array $data, ?MemberLevel $level = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        throw_if($name === '', new BusinessException('等级名称不能为空'));

        $nameExists = MemberLevel::query()
            ->when($level?->id, fn ($query) => $query->where('id', '!=', $level->id))
            ->where('name', $name)
            ->exists();
        throw_if($nameExists, new BusinessException('等级名称已存在'));

        return [
            'name' => $name,
            'status' => (int) (($data['status'] ?? $level?->status ?? 1) ? 1 : 0),
            'remark' => $this->normalizeNullableString($data['remark'] ?? $level?->remark),
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function buildListCacheKey(bool $enabledOnly): string
    {
        return CacheKey::memberLevels($enabledOnly);
    }

    private function forgetListCaches(): void
    {
        Cache::forget(CacheKey::memberLevels(false));
        Cache::forget(CacheKey::memberLevels(true));
    }
}
