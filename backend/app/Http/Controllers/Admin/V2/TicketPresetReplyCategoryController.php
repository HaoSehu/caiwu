<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\TicketPresetReply\SavePresetReplyCategoryRequest;
use App\Http\Resources\Admin\V2\AdminTicketPresetReplyCategoryResource;
use App\Models\TicketPresetReplyCategory;
use App\Services\Ticket\TicketPresetReplyService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketPresetReplyCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(private TicketPresetReplyService $categories) {}

    public function index(): JsonResponse
    {
        return $this->success([
            'list' => AdminTicketPresetReplyCategoryResource::collection(
                $this->categories->listCategories()
            )->resolve(),
        ]);
    }

    public function store(SavePresetReplyCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->createCategory($request->payload());

        return $this->success([
            'category' => AdminTicketPresetReplyCategoryResource::make($category)->resolve(),
        ], '分类创建成功');
    }

    public function update(SavePresetReplyCategoryRequest $request, TicketPresetReplyCategory $category): JsonResponse
    {
        $category = $this->categories->updateCategory($category, $request->payload());

        return $this->success([
            'category' => AdminTicketPresetReplyCategoryResource::make($category)->resolve(),
        ], '分类更新成功');
    }

    public function destroy(TicketPresetReplyCategory $category): JsonResponse
    {
        $this->categories->deleteCategory($category);

        return $this->success(null, '分类已删除');
    }
}
