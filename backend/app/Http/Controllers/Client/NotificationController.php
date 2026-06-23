<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Notification\IndexRequest;
use App\Services\Notification\InboxService;
use App\Services\Notification\UserNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private InboxService $inboxService,
        private UserNotificationService $userNotificationService,
    ) {}

    /** 站内信总未读数（公告 + 个性化） */
    public function unreadCount(Request $request)
    {
        return $this->success([
            'count' => $this->inboxService->unreadCount($request->user()->id),
        ]);
    }

    /** 铃铛下拉：最新若干条合并消息 */
    public function feed(Request $request)
    {
        $limit = max(1, min((int) $request->integer('limit', 10), 30));

        return $this->success([
            'list' => $this->inboxService->feed($request->user()->id, $limit),
            'unread_count' => $this->inboxService->unreadCount($request->user()->id),
        ]);
    }

    /** 站内信中心：完整列表（可只看未读，分页） */
    public function index(IndexRequest $request)
    {
        $validated = $request->validated();

        $page = (int) ($validated['page'] ?? 1);
        $pageSize = (int) ($validated['page_size'] ?? 15);
        $unreadOnly = (bool) ($validated['unread_only'] ?? false);

        $result = $this->inboxService->list($request->user()->id, $unreadOnly, $page, $pageSize);

        return $this->success([
            'list' => $result['list'],
            'total' => $result['total'],
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    /** 标记单条个性化消息已读 */
    public function markRead(Request $request, int $id)
    {
        $this->userNotificationService->markRead($request->user()->id, $id);

        return $this->success();
    }

    /** 全部标记已读（公告 + 个性化） */
    public function markAllRead(Request $request)
    {
        $this->inboxService->markAllRead($request->user()->id);

        return $this->success();
    }
}
