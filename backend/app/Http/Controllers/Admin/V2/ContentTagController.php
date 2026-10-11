<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\ContentTag\ListContentTagsRequest;
use App\Http\Requests\Admin\V2\ContentTag\StoreContentTagRequest;
use App\Http\Requests\Admin\V2\ContentTag\UpdateContentTagRequest;
use App\Http\Resources\Admin\V2\ContentTagResource;
use App\Models\ContentTag;
use App\Services\Content\ContentTagService;
use Illuminate\Http\JsonResponse;

class ContentTagController extends Controller
{
    public function __construct(
        private readonly ContentTagService $contentTagService,
    ) {}

    public function index(ListContentTagsRequest $request): JsonResponse
    {
        $paginator = $this->contentTagService->adminList(
            filters: $request->filters(),
            perPage: $request->perPage(50, 100),
        );

        return $this->paginate($paginator, ContentTagResource::class);
    }

    public function store(StoreContentTagRequest $request): JsonResponse
    {
        $tag = $this->contentTagService->create($request->validated());

        return $this->success(ContentTagResource::make($tag)->resolve(), '标签创建成功');
    }

    public function update(UpdateContentTagRequest $request, ContentTag $tag): JsonResponse
    {
        $tag = $this->contentTagService->update($tag, $request->validated());

        return $this->success(ContentTagResource::make($tag)->resolve(), '标签更新成功');
    }

    public function destroy(ContentTag $tag): JsonResponse
    {
        // 仍被文章绑定时服务层抛 BusinessException 提示先解绑，不做静默级联
        $this->contentTagService->delete($tag);

        return $this->success(null, '标签删除成功');
    }
}
