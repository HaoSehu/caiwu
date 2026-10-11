<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesCommission\V2\SalesCommissionSettlementResource;
use App\Models\SalesCommissionSettlement;
use App\Services\SalesCommission\SalesCommissionSettlementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesCommissionSettlementController extends Controller
{
    public function __construct(
        private readonly SalesCommissionSettlementService $settlementService,
    ) {}

    /**
     * 结算批次列表：?status=&admin_user_id=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalesCommissionSettlement::query()
            ->with(['adminUser:id,username,nickname,email']);

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        if ($request->filled('admin_user_id')) {
            $query->where('admin_user_id', (int) $request->input('admin_user_id'));
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        /** @var LengthAwarePaginator<int, SalesCommissionSettlement> $paginator */
        $paginator = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginate($paginator, SalesCommissionSettlementResource::class);
    }

    public function show(SalesCommissionSettlement $settlement): JsonResponse
    {
        $settlement->load(['adminUser:id,username,nickname,email', 'commissions.invoice:id,invoice_no']);

        return $this->success(SalesCommissionSettlementResource::make($settlement)->resolve());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'admin_user_id' => ['required', 'integer', 'exists:admin_users,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $operator = $request->user();

        $settlement = $this->settlementService->create(
            adminUserId: (int) $data['admin_user_id'],
            periodStart: (string) $data['period_start'],
            periodEnd: (string) $data['period_end'],
            operatorId: (int) ($operator->id ?? 0),
            remark: $data['remark'] ?? null,
        );

        return $this->success(
            SalesCommissionSettlementResource::make($settlement->fresh(['adminUser:id,username,nickname,email']))->resolve(),
            '结算批次已创建'
        );
    }

    public function approve(Request $request, SalesCommissionSettlement $settlement): JsonResponse
    {
        $data = $request->validate([
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $operator = $request->user();

        $result = $this->settlementService->approve(
            settlement: $settlement,
            operatorId: (int) ($operator->id ?? 0),
            remark: $data['remark'] ?? null,
        );

        return $this->success(
            SalesCommissionSettlementResource::make($result)->resolve(),
            '已确认发放（线下发放登记，不动用户余额）'
        );
    }

    public function reject(Request $request, SalesCommissionSettlement $settlement): JsonResponse
    {
        $data = $request->validate([
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $operator = $request->user();

        $result = $this->settlementService->reject(
            settlement: $settlement,
            operatorId: (int) ($operator->id ?? 0),
            remark: $data['remark'] ?? null,
        );

        return $this->success(
            SalesCommissionSettlementResource::make($result)->resolve(),
            '批次已驳回，批次内计提已退回待结算'
        );
    }
}
