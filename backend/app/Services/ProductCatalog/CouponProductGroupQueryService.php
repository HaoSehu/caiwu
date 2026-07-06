<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog;

use App\Exceptions\BusinessException;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CouponProductGroupQueryService
{
    public function paginateFirstGroups(array $filters, int $page, int $pageSize): LengthAwarePaginator
    {
        return FirstProductGroup::query()
            ->select(['id', 'code', 'name', 'sort_order', 'is_visible'])
            ->withCount([
                'secondProductGroups as children_count',
                'products as products_count',
                'products as direct_products_count' => fn (Builder $query) => $query->whereNull('second_product_group_id'),
            ])
            ->when($this->keyword($filters) !== '', function (Builder $query) use ($filters): void {
                $keyword = $this->keyword($filters);
                $query->where(function (Builder $builder) use ($keyword): void {
                    $builder
                        ->where('name', 'like', '%'.$keyword.'%')
                        ->orWhere('code', 'like', '%'.$keyword.'%');
                });
            })
            ->when($this->status($filters) !== null, fn (Builder $query) => $query->where('is_visible', $this->status($filters)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function paginateChildren(
        int $groupId,
        int $level,
        array $filters,
        int $page,
        int $pageSize,
    ): LengthAwarePaginator {
        if ($level === 1) {
            $this->firstGroup($groupId);

            return SecondProductGroup::query()
                ->select(['id', 'first_product_group_id', 'name', 'sort_order', 'is_visible'])
                ->where('first_product_group_id', $groupId)
                ->with(['firstProductGroup:id,code,name'])
                ->withCount([
                    'thirdProductGroups as children_count',
                    'products as products_count',
                    'products as direct_products_count' => fn (Builder $query) => $query->whereNull('third_product_group_id'),
                ])
                ->when($this->keyword($filters) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->keyword($filters).'%'))
                ->when($this->status($filters) !== null, fn (Builder $query) => $query->where('is_visible', $this->status($filters)))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate($pageSize, ['*'], 'page', $page);
        }

        if ($level === 2) {
            $this->secondGroup($groupId);

            return ThirdProductGroup::query()
                ->select(['id', 'second_product_group_id', 'name', 'sort_order', 'is_visible'])
                ->where('second_product_group_id', $groupId)
                ->with(['secondProductGroup:id,first_product_group_id,name', 'secondProductGroup.firstProductGroup:id,code,name'])
                ->withCount([
                    'products as products_count',
                    'products as direct_products_count',
                ])
                ->when($this->keyword($filters) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->keyword($filters).'%'))
                ->when($this->status($filters) !== null, fn (Builder $query) => $query->where('is_visible', $this->status($filters)))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->paginate($pageSize, ['*'], 'page', $page);
        }

        throw new BusinessException('商品分组层级不正确');
    }

    public function paginateProducts(
        int $groupId,
        int $level,
        array $filters,
        int $page,
        int $pageSize,
    ): LengthAwarePaginator {
        $query = Product::query()
            ->select($this->productColumns())
            ->with([
                'firstProductGroup:id,code,name',
                'secondProductGroup:id,first_product_group_id,name',
                'thirdProductGroup:id,second_product_group_id,name',
            ]);

        if ($level === 1) {
            $this->firstGroup($groupId);
            $query->where('first_product_group_id', $groupId)->whereNull('second_product_group_id');
        } elseif ($level === 2) {
            $this->secondGroup($groupId);
            $query->where('second_product_group_id', $groupId)->whereNull('third_product_group_id');
        } elseif ($level === 3) {
            $this->thirdGroup($groupId);
            $query->where('third_product_group_id', $groupId);
        } else {
            throw new BusinessException('商品分组层级不正确');
        }

        return $query
            ->when($this->keyword($filters) !== '', function (Builder $query) use ($filters): void {
                $keyword = $this->keyword($filters);
                $query->where(function (Builder $builder) use ($keyword): void {
                    $builder
                        ->where('custom_display_name', 'like', '%'.$keyword.'%')
                        ->orWhere('product_type', 'like', '%'.$keyword.'%')
                        ->orWhere('service_type_code', 'like', '%'.$keyword.'%');
                });
            })
            ->when($this->status($filters) !== null, fn (Builder $query) => $query->where('status', $this->status($filters)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    private function firstGroup(int $id): FirstProductGroup
    {
        $group = FirstProductGroup::query()->find($id);

        if (! $group instanceof FirstProductGroup) {
            throw new BusinessException('商品分组不存在', 40400, 404);
        }

        return $group;
    }

    private function secondGroup(int $id): SecondProductGroup
    {
        $group = SecondProductGroup::query()->find($id);

        if (! $group instanceof SecondProductGroup) {
            throw new BusinessException('商品分组不存在', 40400, 404);
        }

        return $group;
    }

    private function thirdGroup(int $id): ThirdProductGroup
    {
        $group = ThirdProductGroup::query()->find($id);

        if (! $group instanceof ThirdProductGroup) {
            throw new BusinessException('商品分组不存在', 40400, 404);
        }

        return $group;
    }

    /**
     * @return list<string>
     */
    private function productColumns(): array
    {
        return Product::optionalSelectColumns([
            'id',
            'first_product_group_id',
            'second_product_group_id',
            'third_product_group_id',
            'service_type_code',
            'product_type',
            'custom_display_name',
            'pricing',
            'status',
            'sort_order',
            'purchase_requires',
            'config_options',
            'updated_at',
            'deleted_at',
        ]) ?: ['products.*'];
    }

    private function keyword(array $filters): string
    {
        return trim((string) ($filters['keyword'] ?? ''));
    }

    private function status(array $filters): ?int
    {
        if (! array_key_exists('status', $filters) || $filters['status'] === null || $filters['status'] === '') {
            return null;
        }

        return (int) $filters['status'];
    }
}
