<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaFile\StoreRequest;
use App\Models\MediaFile;
use App\Services\Content\MediaFileService;
use App\Support\UploadUrl;
use Illuminate\Http\Request;

class MediaFileController extends Controller
{
    public function __construct(private MediaFileService $mediaFileService) {}

    public function index(Request $request)
    {
        if ($request->query('group') === MediaFileService::HERO_VIDEO_GROUP) {
            $items = $this->mediaFileService->listHeroVideos((string) $request->query('keyword', ''));

            return $this->success([
                'list' => $items,
                'total' => count($items),
                'page' => 1,
                'page_size' => count($items),
            ]);
        }

        $paginator = $this->mediaFileService->list(
            filters: $request->only(['group', 'keyword', 'type']),
            perPage: (int) ($request->query('page_size', 24)),
        );

        $items = $paginator->getCollection()->map(fn (MediaFile $item) => [
            'id' => $item->id,
            'filename' => $item->filename,
            'path' => $item->path,
            'url' => UploadUrl::resolve($item->path),
            'mime_type' => $item->mime_type,
            'size' => $item->size,
            'width' => $item->width,
            'height' => $item->height,
            'group' => $item->group,
            'type' => str_starts_with((string) $item->mime_type, 'video/') ? 'video' : 'image',
            'created_at' => optional($item->created_at)?->toDateTimeString(),
        ]);

        return $this->success([
            'list' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    public function store(StoreRequest $request)
    {
        $group = trim((string) $request->input('group', 'content')) ?: 'content';

        $mediaFile = $this->mediaFileService->upload(
            file: $request->file('file'),
            adminId: (int) $request->user()->id,
            group: $group,
        );

        return $this->success([
            'id' => $mediaFile->id,
            'filename' => $mediaFile->filename,
            'url' => UploadUrl::resolve($mediaFile->path),
            'path' => $mediaFile->path,
            'mime_type' => $mediaFile->mime_type,
            'size' => $mediaFile->size,
            'width' => $mediaFile->width,
            'height' => $mediaFile->height,
            'group' => $mediaFile->group,
            'type' => str_starts_with((string) $mediaFile->mime_type, 'video/') ? 'video' : 'image',
        ], '上传成功');
    }

    public function destroy(MediaFile $mediaFile)
    {
        $this->mediaFileService->delete($mediaFile);

        return $this->success(null, '删除成功');
    }

    public function reindex(Request $request)
    {
        $result = $this->mediaFileService->reindexMediaDirectory((int) $request->user()->id);

        return $this->success($result, '重新获取成功');
    }
}
