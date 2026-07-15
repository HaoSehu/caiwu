<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\ListContentCategoriesRequest;
use App\Http\Requests\Admin\Content\StoreContentCategoryRequest;
use App\Http\Requests\Admin\Content\UpdateContentCategoryRequest;
use App\Http\Resources\Content\ContentCategoryResource;
use App\Models\ContentCategory;
use App\Services\Content\ContentCategoryService;

class ContentCategoryController extends Controller
{
    public function __construct(private ContentCategoryService $contentCategoryService) {}

    public function index(ListContentCategoriesRequest $request)
    {
        $list = $this->contentCategoryService->adminList(
            (string) ($request->validated('content_type') ?? $request->validated('type'))
        );

        return $this->success(ContentCategoryResource::collection($list)->resolve());
    }

    public function store(StoreContentCategoryRequest $request)
    {
        $category = $this->contentCategoryService->create(
            data: $request->validated(),
            adminId: (int) $request->user()->id,
        );

        return $this->success(new ContentCategoryResource($category), '分类创建成功');
    }

    public function update(UpdateContentCategoryRequest $request, ContentCategory $category)
    {
        $category = $this->contentCategoryService->update(
            category: $category,
            data: $request->validated(),
            adminId: (int) $request->user()->id,
        );

        return $this->success(new ContentCategoryResource($category), '分类更新成功');
    }

    public function destroy(ContentCategory $category)
    {
        $this->contentCategoryService->delete($category);

        return $this->success(null, '分类删除成功');
    }
}
