<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Content\ReindexMediaFilesRequest;
use App\Http\Requests\Admin\V2\MediaFile\DeleteMediaFileRequest;
use App\Http\Requests\Admin\V2\MediaFile\ListMediaFilesRequest;
use App\Http\Requests\Admin\V2\MediaFile\StoreMediaFileRequest;
use App\Http\Resources\Admin\V2\AdminMediaFileResource;
use App\Http\Resources\V2\ActionResultResource;
use App\Models\MediaFile;
use App\Services\Admin\V2\AdminOperationalActionV2Service;
use App\Services\Content\MediaFileService;
use App\Services\Content\Storage\MediaStorageResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class MediaFileController extends Controller
{
    public function __construct(
        private readonly AdminOperationalActionV2Service $actions,
        private readonly MediaFileService $mediaFileService,
        private readonly MediaStorageResolver $storageResolver,
    ) {}

    /**
     * 媒体库可用的存储区列表（slug + 显示名）。
     *
     * 前端据此渲染存储区标签与上传目标选择；渠道增减只影响此列表，
     * 不影响历史文件 disk 字段的展示（前端对未知 disk 回退显示原值）。
     */
    public function storageDrivers(): JsonResponse
    {
        return $this->success([
            'drivers' => $this->storageResolver->availableDriverDetails(),
        ]);
    }

    public function index(ListMediaFilesRequest $request): JsonResponse
    {
        $filters = $request->validated();

        if (($filters['group'] ?? null) === MediaFileService::HERO_VIDEO_GROUP) {
            return $this->heroVideoResponse($request, $filters);
        }

        $paginator = $this->mediaFileService->list(
            filters: array_intersect_key($filters, array_flip(['group', 'keyword', 'type'])),
            perPage: $request->perPage(24, 100),
        );

        // 统一走基类 paginate() 封装，保持分页信封由 ApiResponseBuilder 单点生成。
        return $this->paginate($paginator, AdminMediaFileResource::class);
    }

    public function store(StoreMediaFileRequest $request): JsonResponse
    {
        $mediaFile = $this->mediaFileService->upload(
            file: $request->file('file'),
            adminId: (int) $request->user()->id,
            group: trim((string) $request->input('group', 'content')) ?: 'content',
            driver: $request->has('driver') ? trim((string) $request->input('driver')) : null,
        );

        return $this->success(AdminMediaFileResource::make($mediaFile)->resolve(), '上传成功');
    }

    public function destroy(DeleteMediaFileRequest $request, MediaFile $mediaFile): JsonResponse
    {
        $this->mediaFileService->delete($mediaFile);

        return $this->success(null, '删除成功');
    }

    public function references(MediaFile $mediaFile): JsonResponse
    {
        return $this->success([
            'references' => $this->mediaFileService->checkReferences($mediaFile),
        ]);
    }

    public function reindex(ReindexMediaFilesRequest $request): JsonResponse
    {
        $result = $this->actions->reindexMediaFiles((int) $request->user()->id);

        return $this->success(ActionResultResource::make($result)->resolve(), (string) $result['message']);
    }

    private function heroVideoResponse(ListMediaFilesRequest $request, array $filters): JsonResponse
    {
        $items = collect($this->mediaFileService->listHeroVideos((string) ($filters['keyword'] ?? '')));

        if (($filters['type'] ?? null) === 'image') {
            $items = collect();
        }

        $page = max((int) ($filters['page'] ?? 1), 1);
        $pageSize = $request->perPage(24, 100);
        $list = $items->forPage($page, $pageSize)->values()->all();

        // 内存集合分页包装回标准分页器复用基类 paginate()，
        // 保持分页信封由 ApiResponseBuilder 单点生成（输出形状与原手拼一致）。
        return $this->paginate(new LengthAwarePaginator(
            $list,
            $items->count(),
            $pageSize,
            $page,
        ), AdminMediaFileResource::class);
    }
}
