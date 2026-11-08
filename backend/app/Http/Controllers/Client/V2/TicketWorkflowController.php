<?php

namespace App\Http\Controllers\Client\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\V2\Action\ClientActionRequest;
use App\Http\Requests\Client\V2\Ticket\ListTicketRepliesRequest;
use App\Http\Requests\Client\V2\Ticket\ListTicketsRequest;
use App\Http\Requests\Client\V2\Ticket\ReplyRequest;
use App\Http\Requests\Client\V2\Ticket\ServiceOptionsRequest;
use App\Http\Requests\Client\V2\Ticket\ShowTicketRequest;
use App\Http\Requests\Client\V2\Ticket\StoreRequest;
use App\Http\Requests\Client\V2\Ticket\UploadImageRequest;
use App\Http\Resources\Client\V2\ClientTicketListItemResource;
use App\Http\Resources\Ticket\V2\TicketDetailResource;
use App\Http\Resources\Ticket\V2\TicketReplyResource;
use App\Http\Resources\V2\ActionResultResource;
use App\Services\Client\V2\ClientActionV2Service;
use App\Services\Ticket\TicketService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketWorkflowController extends Controller
{
    use ApiResponse;

    public function __construct(
        private TicketService $ticketService,
        private ClientActionV2Service $actions,
    ) {}

    public function index(ListTicketsRequest $request)
    {
        $filters = $request->only(['keyword', 'status']);
        // 页大小统一由基类 perPage() 提取，消除内联钳制逻辑。
        $paginator = $this->ticketService->clientList($request->user()->id, $filters, $request->perPage());

        return $this->paginate($paginator, ClientTicketListItemResource::class);
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        $ticket = $this->ticketService->create($request->user()->id, $data);

        return $this->success($ticket, '工单提交成功');
    }

    public function serviceOptions(ServiceOptionsRequest $request)
    {
        $data = $request->validated();

        return $this->success(
            $this->ticketService->clientServiceOptions(
                (int) $request->user()->id,
                (string) ($data['keyword'] ?? ''),
                (int) ($data['limit'] ?? 20),
            )
        );
    }

    public function uploadImage(UploadImageRequest $request)
    {
        $data = $request->validated();

        $image = $this->ticketService->uploadImage($request->user()->id, 'client', $data['file']);

        return $this->success($image, '图片上传成功');
    }

    public function reply(ReplyRequest $request, int $id)
    {
        $ticket = $this->ticketService->findForUser((int) $request->user()->id, $id);
        $data = $request->validated();
        $reply = $this->ticketService->clientReply(
            $ticket,
            $request->user()->id,
            $data['content'] ?? null,
            $data['attachments'] ?? [],
            $data['quote_reply_id'] ?? null,
        );

        return $this->success($reply, '回复成功');
    }

    public function close(Request $request, int $id)
    {
        $ticket = $this->ticketService->findForUser((int) $request->user()->id, $id);
        $this->ticketService->clientClose($ticket, $request->user()->id);

        return $this->success(null, '工单已关闭');
    }

    public function reopen(Request $request, int $id)
    {
        $ticket = $this->ticketService->findForUser((int) $request->user()->id, $id);
        $updated = $this->ticketService->reopen($ticket, [
            'operator_type' => 'client',
            'operator_id' => (int) $request->user()->id,
            'trace_id' => (string) $request->header('X-Request-Id', ''),
        ]);

        return $this->success([
            'id' => (int) $updated->id,
            'status' => (int) $updated->status,
        ], '工单已重新开启');
    }

    public function show(ShowTicketRequest $request, int $id): JsonResponse
    {
        return $this->success([
            'ticket' => (new TicketDetailResource($this->ticketService->v2Detail(
                $this->ticketService->findForUser((int) $request->user()->id, $id)
            )))->resolve(),
        ]);
    }

    public function replies(ListTicketRepliesRequest $request, int $id): JsonResponse
    {
        return $this->paginate(
            $this->ticketService->v2Replies(
                $this->ticketService->findForUser((int) $request->user()->id, $id),
                (int) ($request->validated('page_size') ?? 20)
            ),
            TicketReplyResource::class
        );
    }

    public function recallReply(ClientActionRequest $request, int $id, int $reply)
    {
        return $this->success(ActionResultResource::make(
            $this->actions->recallTicketReply($request->user(), $id, $reply)
        )->resolve(), '消息已撤回');
    }
}
