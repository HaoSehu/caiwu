<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SalesCommission\SalesCommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOwnerController extends Controller
{
    public function __construct(
        private readonly SalesCommissionService $salesCommissionService,
    ) {}

    /**
     * POST /v2/admin/users/{user}/sales-owner
     * 设置客户归属销售（admin_id 传 null/0 表示解除归属）；同一归属幂等。
     */
    public function store(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'admin_id' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $operator = $request->user();

        $changed = $this->salesCommissionService->setSalesOwner(
            user: $user,
            adminId: (int) ($data['admin_id'] ?? 0),
            reason: (string) $data['reason'],
            operatorId: (int) ($operator->id ?? 0),
        );

        $user->refresh();

        return $this->success([
            'user_id' => (int) $user->id,
            'sales_owner_admin_id' => $user->sales_owner_admin_id !== null ? (int) $user->sales_owner_admin_id : null,
            'sales_owner' => $user->salesOwner ? [
                'id' => (int) $user->salesOwner->id,
                'display_name' => (string) $user->salesOwner->display_name,
            ] : null,
            'sales_owner_assigned_at' => $user->sales_owner_assigned_at?->format('Y-m-d H:i:s'),
            'changed' => $changed,
        ], $changed ? '归属已更新' : '归属未变化（幂等跳过）');
    }
}
