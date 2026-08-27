<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\MarketingProductGroup\StoreMarketingProductGroupRequest;
use App\Http\Requests\Admin\V2\MarketingProductGroup\SyncMarketingGroupProductsRequest;
use App\Http\Requests\Admin\V2\MarketingProductGroup\UpdateMarketingProductGroupRequest;
use App\Http\Resources\Admin\V2\AdminMarketingProductGroupResource;
use App\Models\MarketingProductGroup;
use App\Services\Pricing\MarketingProductGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingProductGroupController extends Controller
{
    public function __construct(
        private readonly MarketingProductGroupService $marketingGroups,
    ) {}

    public function index(): JsonResponse
    {
        $list = AdminMarketingProductGroupResource::collection($this->marketingGroups->list())->resolve();

        return $this->success($list);
    }

    public function show(MarketingProductGroup $marketingProductGroup): JsonResponse
    {
        $detail = $this->marketingGroups->detail($marketingProductGroup);
        $resource = AdminMarketingProductGroupResource::make($detail['group']);
        $payload = $resource->resolve();

        // 详情需回显整组商品映射，供编辑弹窗预选
        $payload['product_ids'] = array_map('intval', (array) ($detail['product_ids'] ?? []));

        return $this->success($payload);
    }

    public function store(StoreMarketingProductGroupRequest $request): JsonResponse
    {
        $group = $this->marketingGroups->create($request->payload(), $this->actorContext($request));

        return $this->success(AdminMarketingProductGroupResource::make($group)->resolve(), '营销组创建成功');
    }

    public function update(UpdateMarketingProductGroupRequest $request, MarketingProductGroup $marketingProductGroup): JsonResponse
    {
        $group = $this->marketingGroups->update($marketingProductGroup, $request->payload(), $this->actorContext($request));

        return $this->success(AdminMarketingProductGroupResource::make($group)->resolve(), '营销组更新成功');
    }

    public function destroy(MarketingProductGroup $marketingProductGroup, Request $request): JsonResponse
    {
        $this->marketingGroups->delete($marketingProductGroup, $this->actorContext($request));

        return $this->success(null, '营销组删除成功');
    }

    /**
     * 整包替换营销组的商品圈选。
     */
    public function syncProducts(SyncMarketingGroupProductsRequest $request, MarketingProductGroup $marketingProductGroup): JsonResponse
    {
        $this->marketingGroups->syncProducts($marketingProductGroup, $request->productIds(), $this->actorContext($request));

        return $this->success(null, '商品圈选已保存');
    }

    /**
     * @return array<string, mixed>
     */
    private function actorContext(Request $request): array
    {
        return [
            'actor_type' => 'admin',
            'actor_user_id' => (int) ($request->user()->id ?? 0),
            'actor_name' => (string) ($request->user()->username ?? ''),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
            'ip_address' => (string) $request->ip(),
        ];
    }
}
