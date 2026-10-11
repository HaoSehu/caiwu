<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Ticket\TransferTicketRequest;
use App\Http\Resources\Admin\V2\AdminTicketTransferResource;
use App\Models\Ticket;
use App\Services\Ticket\TicketService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * M3 工单转派入口（独立控制器，避免改动既有 TicketController）：
 * 换部门 + 可选指定处理人 + 转派流水，未指定处理人时按目标部门自动分派。
 */
class TicketTransferController extends Controller
{
    use ApiResponse;

    public function __construct(private TicketService $tickets) {}

    public function store(TransferTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $transferred = $this->tickets->transfer(
            $ticket,
            $request->toDepartmentId(),
            $request->toAssigneeId(),
            $request->reason(),
            $request->user()
        );

        return $this->success([
            'ticket' => AdminTicketTransferResource::make($transferred)->resolve(),
        ], '工单转派成功');
    }
}
