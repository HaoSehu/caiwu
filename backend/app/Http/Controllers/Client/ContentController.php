<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Content\PublishedListRequest;
use App\Http\Resources\Content\ContentArticleResource;
use App\Http\Resources\Content\ContentCategoryResource;
use App\Models\ContentArticle;
use App\Services\Content\ContentArticleService;
use App\Services\Content\NoticeReadService;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function __construct(
        private ContentArticleService $contentArticleService,
        private NoticeReadService $noticeReadService,
    ) {}

    public function overview()
    {
        $payload = $this->contentArticleService->publishedOverview(6, 6);

        return $this->success([
            'notices' => ContentArticleResource::collection($payload['notices'])->resolve(),
            'help_articles' => ContentArticleResource::collection($payload['help_articles'])->resolve(),
            'notice_categories' => ContentCategoryResource::collection($payload['notice_categories'])->resolve(),
            'help_categories' => ContentCategoryResource::collection($payload['help_categories'])->resolve(),
        ]);
    }

    public function notices(PublishedListRequest $request)
    {
        return $this->publishedList($request, ContentArticle::TYPE_NOTICE);
    }

    public function noticeDetail(Request $request, int $articleId)
    {
        $article = $this->contentArticleService->publishedDetail(ContentArticle::TYPE_NOTICE, $articleId);

        if ($request->user()) {
            $this->noticeReadService->markRead($request->user()->id, $articleId);
        }

        return $this->success(new ContentArticleResource($article));
    }

    public function noticeUnreadCount(Request $request)
    {
        $count = $this->noticeReadService->unreadCount($request->user()->id);

        return $this->success(['count' => $count]);
    }

    public function markNoticeRead(Request $request, int $articleId)
    {
        $this->noticeReadService->markRead($request->user()->id, $articleId);

        return $this->success();
    }

    public function markAllNoticesRead(Request $request)
    {
        $this->noticeReadService->markAllRead($request->user()->id);

        return $this->success();
    }

    public function helpArticles(PublishedListRequest $request)
    {
        return $this->publishedList($request, ContentArticle::TYPE_HELP);
    }

    public function helpDetail(int $articleId)
    {
        return $this->publishedDetail($articleId, ContentArticle::TYPE_HELP);
    }

    private function publishedList(PublishedListRequest $request, string $type)
    {
        $filters = $request->validated();

        if (empty($filters['category_id']) && ! empty($filters['content_category_id'])) {
            $filters['category_id'] = (int) $filters['content_category_id'];
        }

        $perPage = max(1, min((int) ($filters['page_size'] ?? 12), 50));
        $paginator = $this->contentArticleService->publishedList($type, $filters, $perPage);

        return $this->success([
            'list' => ContentArticleResource::collection($paginator->items())->resolve(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
            'categories' => ContentCategoryResource::collection($this->contentArticleService->publishedCategories($type))->resolve(),
        ]);
    }

    private function publishedDetail(int $articleId, string $type)
    {
        $article = $this->contentArticleService->publishedDetail($type, $articleId);

        return $this->success(new ContentArticleResource($article));
    }
}
