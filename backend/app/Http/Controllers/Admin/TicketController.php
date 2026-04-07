<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Ticket;
use App\Services\TicketService;
use App\Traits\ApiResponse;
use App\Support\AdminPermissions;
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

        return $this->success([
            'list'      => $paginator->items(),
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    public function show(Ticket $ticket)
    {
        return $this->success($this->ticketService->detail($ticket));
    }

    public function uploadImage(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $image = $this->ticketService->uploadImage($request->user()->id, 'admin', $data['file']);

        return $this->success($image, '图片上传成功');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:9'],
            'attachments.*' => ['required', 'string', 'max:255'],
        ]);
        $reply = $this->ticketService->staffReply(
            $ticket,
            $request->user()->id,
            $data['content'] ?? null,
            $data['attachments'] ?? []
        );
        return $this->success($reply, '回复成功');
    }

    public function close(Ticket $ticket)
    {
        $this->ticketService->staffClose($ticket);
        return $this->success(null, '工单已关闭');
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['assignee_id' => ['required', 'integer', 'exists:admin_users,id']]);
        $ticket = $this->ticketService->assign($ticket, $data['assignee_id']);
        return $this->success($ticket->load('assignee:id,username,nickname'), '指派成功');
    }

    public function adminUsers()
    {
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
            'id'       => (int) $a->id,
            'username' => $a->username,
            'nickname' => $a->display_name,
            'email'    => $hasEmailColumn ? (string) ($a->email ?? '') : '',
        ])->values());
    }
}
