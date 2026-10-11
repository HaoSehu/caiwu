<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\V2;

use App\Http\Controllers\Controller;
use App\Services\Ticket\TicketDepartmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * M3 客户端提单部门选项：仅启用中部门（id/name/description/sort_order）。
 */
class TicketDepartmentController extends Controller
{
    use ApiResponse;

    public function __construct(private TicketDepartmentService $departments) {}

    public function index(): JsonResponse
    {
        return $this->success([
            'list' => $this->departments->enabledDepartmentsForClient(),
        ]);
    }
}
