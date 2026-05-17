<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\ListContentArticlesRequest;
use App\Http\Requests\Admin\Content\StoreContentArticleRequest;
use App\Http\Requests\Admin\Content\UpdateContentArticleRequest;
use App\Http\Resources\Content\ContentArticleResource;
use App\Models\ContentArticle;
use App\Services\Content\ContentArticleService;
use App\Services\Content\MediaFileService;
use App\Support\UploadUrl;
use Illuminate\Http\Request;

class ContentArticleController extends Controller
{
    public function __construct(
        private ContentArticleService $contentArticleService,
        private MediaFileService $mediaFileService,
    ) {}

    public function summary()
    {
        return $this->success($this->contentArticleService->adminSummary());
    }

    public function index(ListContentArticlesRequest $request)
    {
        $paginator = $this->contentArticleService->adminList(
            filters: $request->validated(),
            perPage: $request->perPage(),
        );

        return $this->paginate($paginator, ContentArticleResource::class);
    }

    public function show(ContentArticle $article)
    {
        $article->load([
            'contentCategory',
            'creator:id,username,nickname',
            'updater:id,username,nickname',
        ]);

        return $this->success(new ContentArticleResource($article));
    }

    public function store(StoreContentArticleRequest $request)
    {
        $article = $this->contentArticleService->create(
            data: $request->validated(),
            adminId: (int) $request->user()->id,
            traceId: $request->header('X-Request-Id'),
        );

        return $this->success(new ContentArticleResource($article), '内容创建成功');
    }

    public function update(UpdateContentArticleRequest $request, ContentArticle $article)
    {
        $article = $this->contentArticleService->update(
            article: $article,
            data: $request->validated(),
            adminId: (int) $request->user()->id,
            traceId: $request->header('X-Request-Id'),
        );

        return $this->success(new ContentArticleResource($article), '内容更新成功');
    }

    public function destroy(Request $request, ContentArticle $article)
    {
        $this->contentArticleService->delete($article);

        return $this->success(null, '内容删除成功');
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);

        $mediaFile = $this->mediaFileService->upload(
            file: $request->file('file'),
            adminId: (int) $request->user()->id,
            group: 'content',
        );

        return $this->success([
            'id' => $mediaFile->id,
            'url' => UploadUrl::resolve($mediaFile->path),
        ], '上传成功');
    }
}
