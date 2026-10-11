<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\TicketPresetReply\ListPresetRepliesRequest;
use App\Http\Requests\Admin\V2\TicketPresetReply\RenderPresetReplyRequest;
use App\Http\Requests\Admin\V2\TicketPresetReply\SavePresetReplyRequest;
use App\Http\Resources\Admin\V2\AdminTicketPresetReplyResource;
use App\Models\AdminUser;
use App\Models\Ticket;
use App\Models\TicketPresetReply;
use App\Services\Ticket\TicketDepartmentService;
use App\Services\Ticket\TicketPresetReplyService;
use App\Support\AdminPermissions;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketPresetReplyController extends Controller
{
    use ApiResponse;

    public function __construct(private TicketPresetReplyService $replies) {}

    public function index(ListPresetRepliesRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $operator = $request->user();

        // 管理岗（ticket.manage）全量；仅持有 ticket.reply 的客服按部门过滤（通用 + 本部门）。
        $items = $operator !== null && ! $operator->hasPermission(AdminPermissions::TICKET_MANAGE)
            ? $this->replies->listRepliesForStaff($operator, $filters)
            : $this->replies->listReplies($filters);

        return $this->success([
            'list' => AdminTicketPresetReplyResource::collection($items)->resolve(),
        ]);
    }

    public function store(SavePresetReplyRequest $request): JsonResponse
    {
        $reply = $this->replies->createReply($request->payload(), (int) $request->user()->id);

        return $this->success([
            'reply' => AdminTicketPresetReplyResource::make($reply)->resolve(),
        ], '预设回复创建成功');
    }

    public function update(SavePresetReplyRequest $request, TicketPresetReply $presetReply): JsonResponse
    {
        $reply = $this->replies->updateReply($presetReply, $request->payload());

        return $this->success([
            'reply' => AdminTicketPresetReplyResource::make($reply)->resolve(),
        ], '预设回复更新成功');
    }

    public function destroy(TicketPresetReply $presetReply): JsonResponse
    {
        $this->replies->deleteReply($presetReply);

        return $this->success(null, '预设回复已删除');
    }

    /**
     * 客服插入模板兜底渲染：服务端渲染占位符并原子自增 usage_count。
     */
    public function render(RenderPresetReplyRequest $request, TicketPresetReply $presetReply): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($request->ticketId());

        // 越权防护：渲染会回显工单 PII（用户昵称/邮箱等），操作者必须有权访问该工单。
        $operator = $request->user();
        assert($operator instanceof AdminUser);
        TicketDepartmentService::assertTicketAccessible($operator, $ticket);

        return $this->success([
            'content' => $this->replies->renderForTicket($presetReply, $ticket),
            'usage_count' => (int) $presetReply->refresh()->usage_count,
        ]);
    }
}
