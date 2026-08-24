<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\V2\ProductGroup\ListProductGroupChildrenRequest;
use App\Http\Requests\Site\V2\ProductGroup\ListProductGroupProductsRequest;
use App\Http\Requests\Site\V2\ProductGroup\ListProductGroupsRequest;
use App\Http\Resources\Site\V2\SiteProductCardResource;
use App\Http\Resources\Site\V2\SiteProductGroupResource;
use App\Services\ProductCatalog\ProductCatalogService;
use App\Services\ProductCatalog\ProductGroupV2QueryService;
use Illuminate\Http\JsonResponse;

class ProductGroupController extends Controller
{
    public function __construct(
        private readonly ProductGroupV2QueryService $productGroups,
        private readonly ProductCatalogService $catalog,
    ) {}

    public function index(ListProductGroupsRequest $request): JsonResponse
    {
        return $this->paginate(
            $this->productGroups->paginateSiteRootGroups($request->validated()),
            SiteProductGroupResource::class
        );
    }

    public function children(ListProductGroupChildrenRequest $request, int $group): JsonResponse
    {
        return $this->paginate(
            $this->productGroups->paginateSiteChildren($group, $request->validated()),
            SiteProductGroupResource::class
        );
    }

    public function catalog(ListProductGroupChildrenRequest $request, int $group): JsonResponse
    {
        // 一次返回该分组完整目录（子分组 + 各子组商品），切换分组仅需 1 个 RTT；
        // 走 ProductSiteService 的 redis 缓存，避免前端逐级拼 children → level3 商品。
        return $this->success($this->catalog->siteGroupCatalog($group));
    }

    public function products(ListProductGroupProductsRequest $request, int $group): JsonResponse
    {
        $payload = $request->validated();

        return $this->paginate(
            $this->productGroups->paginateSiteProducts($group, (int) $payload['level'], $payload),
            SiteProductCardResource::class
        );
    }
}
