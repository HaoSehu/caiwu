<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\V2\AdminActionResultResource;
use App\Http\Resources\Admin\V2\AdminServiceTransferResource;
use App\Models\Service;
use App\Models\ServiceTransfer;
use App\Models\User;
use App\Services\Service\ServiceTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理端服务转移：列表 / 详情 / 代发起（单确认豁免）/ 强制复核通过 / 驳回。
 */
class ServiceTransferController extends Controller
{
    public function __construct(
        private readonly ServiceTransferService $transfers,
    ) {}

    /**
     * GET /v2/admin/service-transfers?status=。
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'integer', 'between:0,5'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ]);

        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $paginator = ServiceTransfer::query()
            ->when(isset($validated['status']), fn ($query) => $query->where('status', (int) $validated['status']))
            ->when(isset($validated['service_id']), fn ($query) => $query->where('service_id', (int) $validated['service_id']))
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->whereHas('fromUser', fn ($q) => $q->where('email', 'like', '%'.$keyword.'%'))
                    ->orWhereHas('toUser', fn ($q) => $q->where('email', 'like', '%'.$keyword.'%'));
            })
            ->with(['service:id,name,domain', 'fromUser:id,email', 'toUser:id,email'])
            ->orderByDesc('id')
            ->paginate(min(max((int) ($validated['page_size'] ?? 15), 1), 100));

        return $this->paginate($paginator, AdminServiceTransferResource::class);
    }

    /**
     * GET /v2/admin/service-transfers/{transfer}。
     */
    public function show(ServiceTransfer $transfer): JsonResponse
    {
        return $this->success([
            'transfer' => (new AdminServiceTransferResource($transfer->loadMissing(['service', 'fromUser', 'toUser'])))->resolve(),
        ]);
    }

    /**
     * POST /v2/admin/service-transfers：管理员代发起（双确认豁免为单确认，写 operator）。
     * body：service_id + to_user_id（或 to_user_email）。
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'min:1'],
            'to_user_id' => ['nullable', 'integer', 'min:1'],
            'to_user_email' => ['nullable', 'string', 'email', 'max:191'],
        ]);

        $service = Service::query()->find((int) $validated['service_id']);
        if (! $service instanceof Service) {
            throw new BusinessException('目标服务不存在');
        }

        $toUserId = isset($validated['to_user_id']) && (int) $validated['to_user_id'] > 0
            ? (int) $validated['to_user_id']
            : (int) (User::query()->where('email', trim((string) ($validated['to_user_email'] ?? '')))->value('id') ?? 0);
        if ($toUserId <= 0) {
            throw new BusinessException('请提供有效的接收方用户');
        }

        $operator = $request->user();
        $transfer = $this->transfers->initiate($service, $toUserId, ServiceTransfer::INITIATED_BY_ADMIN, $this->operatorId($operator));

        return $this->success([
            'transfer' => (new AdminServiceTransferResource($transfer->loadMissing(['service', 'fromUser', 'toUser'])))->resolve(),
        ], '管理员代发起成功，等待接收方确认');
    }

    /**
     * POST /v2/admin/service-transfers/{transfer}/approve：强制复核通过并生效。
     */
    public function approve(Request $request, ServiceTransfer $transfer): JsonResponse
    {
        $this->ensurePending($transfer);

        // 强制复核通过：管理员代双方确认后直接生效。
        $transfer->forceFill([
            'from_confirmed_at' => $transfer->from_confirmed_at ?? now(),
            'to_confirmed_at' => $transfer->to_confirmed_at ?? now(),
            'status' => ServiceTransfer::STATUS_CONFIRMED,
            'operator_id' => $this->operatorId($request->user()) ?? $transfer->operator_id,
        ])->save();

        $executed = $this->transfers->execute($transfer);

        return $this->success(AdminActionResultResource::make([
            'status' => 'completed',
            'message' => '转移已复核通过并生效',
            'detail' => ['transfer' => (new AdminServiceTransferResource($executed->loadMissing(['service', 'fromUser', 'toUser'])))->resolve()],
        ])->resolve(), '转移已复核通过并生效');
    }

    /**
     * POST /v2/admin/service-transfers/{transfer}/reject：人工复核驳回。
     */
    public function reject(Request $request, ServiceTransfer $transfer): JsonResponse
    {
        $this->ensurePending($transfer);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $rejected = $this->transfers->reject($transfer, 0, $validated['reason'] ?? null, true);
        $rejected->forceFill([
            'operator_id' => $this->operatorId($request->user()) ?? $rejected->operator_id,
        ])->save();

        return $this->success(AdminActionResultResource::make([
            'status' => 'rejected',
            'message' => '转移已驳回',
            'detail' => ['transfer' => (new AdminServiceTransferResource($rejected->loadMissing(['service', 'fromUser', 'toUser'])))->resolve()],
        ])->resolve(), '转移已驳回');
    }

    private function ensurePending(ServiceTransfer $transfer): void
    {
        if (! in_array((int) $transfer->status, ServiceTransfer::ACTIVE_STATUSES, true)) {
            throw new BusinessException('该转移请求已结束，无法复核');
        }
    }

    private function operatorId(?User $operator): ?int
    {
        $id = $operator?->getAuthIdentifier();

        return $id === null ? null : (int) $id;
    }
}
