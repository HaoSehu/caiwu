<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ticket\AssignRequest;
use App\Http\Requests\Admin\Ticket\ReplyRequest;
use App\Http\Requests\Admin\Ticket\UploadImageRequest;
use App\Models\AdminUser;
use App\Models\Ticket;
use App\Services\Ticket\TicketService;
use App\Support\AdminPermissions;
use App\Support\AdminPrivacy;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TicketController extends Controller
{
    use ApiResponse;

    public function __construct(private TicketService $ticketService) {}

    public function summary()
    {
        return $this->success($this->ticketService->adminSummary());
    }

    public function index(Request $request)
    {
        $filters = $request->only(['keyword', 'status', 'priority', 'department']);
        $perPage = min((int) $request->input('page_size', 20), 100);
        $paginator = $this->ticketService->adminList($filters, $perPage);

        $items = collect($paginator->items())->map(function (Ticket $ticket) {
            $data = $ticket->toArray();
            $data['close_reason_label'] = TicketService::CLOSE_REASON_LABELS[$ticket->close_reason ?? ''] ?? null;

            return $data;
        })->all();

        return $this->success([
            'list' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    public function show(Ticket $ticket)
    {
        return $this->success($this->ticketService->detail($ticket));
    }

    public function uploadImage(UploadImageRequest $request)
    {
        $data = $request->validated();

        $image = $this->ticketService->uploadImage($request->user()->id, 'admin', $data['file']);

        return $this->success($image, '图片上传成功');
    }

    public function reply(ReplyRequest $request, Ticket $ticket)
    {
        $data = $request->validated();
        $reply = $this->ticketService->staffReply(
            $ticket,
            $request->user()->id,
            $data['content'] ?? null,
            $data['attachments'] ?? [],
            $data['quote_reply_id'] ?? null,
        );

        return $this->success($reply, '回复成功');
    }

    public function recall(Request $request, Ticket $ticket, int $replyId)
    {
        $this->ticketService->recallReply($ticket, $replyId, $request->user()->id, true);

        return $this->success(null, '消息已撤回');
    }

    public function close(Ticket $ticket)
    {
        $this->ticketService->staffClose($ticket);

        return $this->success(null, '工单已关闭');
    }

    public function assign(AssignRequest $request, Ticket $ticket)
    {
        $data = $request->validated();
        $ticket = $this->ticketService->assign($ticket, $data['assignee_id']);

        return $this->success($ticket->load('assignee:id,username,nickname'), '指派成功');
    }

    public function adminUsers()
    {
        $privacy = AdminPrivacy::current();
        $columns = ['id', 'username', 'nickname', 'role_id'];
        $hasEmailColumn = Schema::hasColumn('admin_users', 'email');

        if ($hasEmailColumn) {
            $columns[] = 'email';
        }

        $admins = AdminUser::query()
            ->withResolvedPermissionRelations()
            ->where('status', 1)
            ->orderBy('id')
            ->get($columns)
            ->filter(fn (AdminUser $admin) => $admin->hasPermission(AdminPermissions::TICKET_REPLY))
            ->values();

        return $this->success($admins->map(fn (AdminUser $a) => [
            'id' => (int) $a->id,
            'username' => $a->username,
            'nickname' => $a->display_name,
            'email' => $hasEmailColumn ? $privacy->email($a->email ?? '') : '',
        ])->values());
    }
}
