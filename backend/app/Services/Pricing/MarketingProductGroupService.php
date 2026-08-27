<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Exceptions\BusinessException;
use App\Models\MarketingProductGroup;
use App\Models\MemberLevel;
use App\Models\MemberLevelGroupDiscount;
use App\Models\Product;
use App\Services\System\OperationLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 营销产品组管理：组 CRUD 与商品圈品。
 *
 * 一个商品可归入多个营销组；删除保护——组内仍有商品或已被折扣矩阵引用时禁止删除。
 */
class MarketingProductGroupService
{
    public function __construct(
        private readonly OperationLogService $operationLogService,
    ) {}

    /**
     * @return Collection<int, MarketingProductGroup>
     */
    public function list(): Collection
    {
        return MarketingProductGroup::query()
            ->withCount('items')
            ->ordered()
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(MarketingProductGroup $group): array
    {
        return [
            'group' => $group,
            'product_ids' => $group->items()->pluck('product_id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    public function create(array $data, array $context = []): MarketingProductGroup
    {
        $group = DB::transaction(function () use ($data) {
            return MarketingProductGroup::query()->create($this->preparePayload($data));
        });

        $this->logChange('marketing_group.create', $group->id, [
            'name' => (string) $group->name,
            'sort_order' => (int) $group->sort_order,
        ], $context);

        return $group;
    }

    public function update(MarketingProductGroup $group, array $data, array $context = []): MarketingProductGroup
    {
        $previousName = (string) $group->name;
        $updated = DB::transaction(function () use ($group, $data) {
            return tap($group, fn (MarketingProductGroup $model): bool => $model->update($this->preparePayload($data, $group)));
        });
        app(MemberGroupDiscountService::class)->invalidate();

        $this->logChange('marketing_group.update', $updated->id, [
            'from_name' => $previousName,
            'to_name' => (string) $updated->name,
            'sort_order' => (int) $updated->sort_order,
        ], $context);

        return $updated->refresh();
    }

    public function delete(MarketingProductGroup $group, array $context = []): void
    {
        throw_if(
            $group->items()->exists(),
            new BusinessException('当前营销组下仍有商品，无法删除'),
        );
        throw_if(
            $group->discounts()->exists(),
            new BusinessException('当前营销组已配置会员折扣，无法删除'),
        );

        $groupId = (int) $group->id;
        $groupName = (string) $group->name;
        $group->delete();

        $this->logChange('marketing_group.delete', $groupId, [
            'name' => $groupName,
        ], $context);
    }

    /**
     * 整包替换某等级的矩阵规则；空数组即清空该等级全部折扣。
     *
     * @param  array<int, array{marketing_product_group_id: int, discount_type: int, discount_value: float}>  $rules
     */
    public function syncDiscounts(MemberLevel $level, array $rules, array $context = []): void
    {
        DB::transaction(function () use ($level, $rules): void {
            MemberLevelGroupDiscount::query()
                ->where('member_level_id', $level->id)
                ->delete();

            if ($rules !== []) {
                MemberLevelGroupDiscount::query()->insert(array_map(fn (array $rule): array => [
                    'member_level_id' => (int) $level->id,
                    'marketing_product_group_id' => (int) $rule['marketing_product_group_id'],
                    'discount_type' => (int) $rule['discount_type'],
                    'discount_value' => number_format((float) $rule['discount_value'], 2, '.', ''),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $rules));
            }
        });
        // 整包替换矩阵直接影响计价命中，事务提交后必须失效缓存
        app(MemberGroupDiscountService::class)->invalidate();

        $this->logChange('member_level.group_discount_sync', (int) $level->id, [
            'member_level_name' => (string) $level->name,
            'rule_count' => count($rules),
            'rules' => array_map(fn (array $rule): array => [
                'marketing_product_group_id' => (int) $rule['marketing_product_group_id'],
                'discount_type' => (int) $rule['discount_type'],
                'discount_value' => (float) $rule['discount_value'],
            ], $rules),
        ], $context);
    }

    /**
     * 整包替换组的商品映射。
     *
     * @param  array<int, int|string>  $productIds
     */
    public function syncProducts(MarketingProductGroup $group, array $productIds, array $context = []): MarketingProductGroup
    {
        $ids = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $existsIds = Product::query()->whereIn('id', $ids)->pluck('id');
        throw_if(
            $existsIds->count() !== $ids->count(),
            new BusinessException('圈选的商品不存在或已下架'),
        );

        DB::transaction(function () use ($group, $ids): void {
            $group->items()->delete();
            if ($ids->isNotEmpty()) {
                $group->items()->createMany($ids->map(fn ($id) => ['product_id' => $id])->all());
            }
        });
        // 商品映射参与计价命中判断，整包替换后必须失效矩阵缓存
        app(MemberGroupDiscountService::class)->invalidate();

        $this->logChange('marketing_group.sync_products', (int) $group->id, [
            'name' => (string) $group->name,
            'product_ids' => $ids->all(),
            'product_count' => $ids->count(),
        ], $context);

        return $group->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preparePayload(array $data, ?MarketingProductGroup $group = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        throw_if($name === '', new BusinessException('营销组名称不能为空'));

        $nameExists = MarketingProductGroup::query()
            ->when($group?->id, fn ($query) => $query->where('id', '!=', $group->id))
            ->where('name', $name)
            ->exists();
        throw_if($nameExists, new BusinessException('营销组名称已存在'));

        $currentSortOrder = $group === null ? 0 : (int) $group->sort_order;

        return [
            'name' => $name,
            'sort_order' => max((int) ($data['sort_order'] ?? $currentSortOrder), 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $context
     */
    private function logChange(string $action, int $targetId, array $detail, array $context): void
    {
        $this->operationLogService->write(
            userId: ((int) ($context['actor_user_id'] ?? 0)) ?: null,
            userType: (string) ($context['actor_type'] ?? 'admin'),
            action: $action,
            module: str_starts_with($action, 'member_level.') ? 'member_level' : 'marketing_group',
            targetId: $targetId,
            detail: array_merge($detail, [
                'actor_name' => (string) ($context['actor_name'] ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? ''),
            ]),
            ipAddress: ($context['ip_address'] ?? null) ? (string) $context['ip_address'] : null,
        );
    }
}
