<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Ticket\TicketService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    use ApiResponse;

    public function __construct(private TicketService $ticketService) {}

    public function index(Request $request)
    {
        $filters = $request->only(['keyword', 'status']);
        $perPage = max(1, min((int) $request->input('page_size', 15), 50));
        $paginator = $this->ticketService->clientList($request->user()->id, $filters, $perPage);

        return $this->success([
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'department' => ['required', Rule::in(TicketService::DEPARTMENTS)],
            'subject' => ['required', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', 'integer', 'between:1,4'],
            'service_id' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array', 'max:9'],
            'attachments.*' => ['required', 'string', 'max:255'],
        ]);

        $ticket = $this->ticketService->create($request->user()->id, $data);

        return $this->success($ticket, '工单提交成功');
    }

    public function serviceOptions(Request $request)
    {
        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->success(
            $this->ticketService->clientServiceOptions(
                (int) $request->user()->id,
                (string) ($data['keyword'] ?? ''),
                (int) ($data['limit'] ?? 20),
            )
        );
    }

    public function uploadImage(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);

        $image = $this->ticketService->uploadImage($request->user()->id, 'client', $data['file']);

        return $this->success($image, '图片上传成功');
    }

    public function show(Request $request, int $id)
    {
        $ticket = Ticket::where('user_id', $request->user()->id)->findOrFail($id);

        return $this->success($this->ticketService->detail($ticket));
    }

    public function reply(Request $request, int $id)
    {
        $ticket = Ticket::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:9'],
            'attachments.*' => ['required', 'string', 'max:255'],
            'quote_reply_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $reply = $this->ticketService->clientReply(
            $ticket,
            $request->user()->id,
            $data['content'] ?? null,
            $data['attachments'] ?? [],
            $data['quote_reply_id'] ?? null,
        );

        return $this->success($reply, '回复成功');
    }

    public function recall(Request $request, int $id, int $replyId)
    {
        $ticket = Ticket::where('user_id', $request->user()->id)->findOrFail($id);
        $this->ticketService->recallReply($ticket, $replyId, $request->user()->id);

        return $this->success(null, '消息已撤回');
    }

    public function close(Request $request, int $id)
    {
        $ticket = Ticket::where('user_id', $request->user()->id)->findOrFail($id);
        $this->ticketService->clientClose($ticket, $request->user()->id);

        return $this->success(null, '工单已关闭');
    }
}
