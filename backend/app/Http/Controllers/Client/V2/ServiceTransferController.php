<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\V2;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\V2\ClientServiceTransferResource;
use App\Models\Service;
use App\Models\ServiceTransfer;
use App\Models\User;
use App\Services\Service\ServiceTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 客户端服务转移入口：发起 / 列表 / 确认 / 拒绝 / 撤销。
 * 资源归属校验：只能操作与自己相关的转移记录。
 */
class ServiceTransferController extends Controller
{
    public function __construct(
        private readonly ServiceTransferService $transfers,
    ) {}

    /**
     * 发起转移（转出方）：POST /v2/client/services/{service}/transfer-requests。
     * body：to_user_email / to_user_id 二选一。
     */
    public function initiate(Request $request, Service $service): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || (int) $service->user_id !== (int) $user->id) {
            throw new BusinessException('仅服务归属人可以发起转移', 40300, 403);
        }

        $validated = $request->validate([
            'to_user_id' => ['nullable', 'integer', 'min:1'],
            'to_user_email' => ['nullable', 'string', 'email', 'max:191'],
        ]);

        $toUserId = $this->resolveToUserId($validated);
        if ($toUserId === null) {
            throw new BusinessException('请提供接收方用户 ID 或邮箱');
        }

        $transfer = $this->transfers->initiate($service, $toUserId, ServiceTransfer::INITIATED_BY_FROM, null);

        return $this->success([
            'transfer' => (new ClientServiceTransferResource($transfer->loadMissing(['service', 'fromUser', 'toUser'])))->resolve(),
        ], '转移请求已发起，等待对方确认');
    }

    /**
     * 我发起/我接收的转移列表：GET /v2/client/service-transfer-requests。
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new BusinessException('未登录', 40100, 401);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'integer', 'between:0,5'],
        ]);

        $paginator = ServiceTransfer::query()
            ->where(function ($query) use ($user): void {
                $query->where('from_user_id', (int) $user->id)->orWhere('to_user_id', (int) $user->id);
            })
            ->when(isset($validated['status']), fn ($query) => $query->where('status', (int) $validated['status']))
            ->with(['service:id,name,domain', 'fromUser:id,email', 'toUser:id,email'])
            ->orderByDesc('id')
            ->paginate(min(max((int) ($validated['page_size'] ?? 15), 1), 50));

        return $this->paginate($paginator, ClientServiceTransferResource::class);
    }

    /**
     * 确认：POST /v2/client/service-transfer-requests/{transfer}/confirm。
     */
    public function confirm(Request $request, ServiceTransfer $transfer): JsonResponse
    {
        $user = $this->authorizeParticipant($request, $transfer);

        $confirmed = $this->transfers->confirm($transfer, (int) $user->id, false);

        return $this->success([
            'transfer' => (new ClientServiceTransferResource($confirmed->loadMissing(['service', 'fromUser', 'toUser'])))->resolve(),
        ], '已确认');
    }

    /**
     * 拒绝：POST /v2/client/service-transfer-requests/{transfer}/reject。
     */
    public function reject(Request $request, ServiceTransfer $transfer): JsonResponse
    {
        $user = $this->authorizeParticipant($request, $transfer);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $rejected = $this->transfers->reject($transfer, (int) $user->id, $validated['reason'] ?? null, false);

        return $this->success([
            'transfer' => (new ClientServiceTransferResource($rejected->loadMissing(['service', 'fromUser', 'toUser'])))->resolve(),
        ], '已拒绝');
    }

    /**
     * 撤销（发起方）：POST /v2/client/service-transfer-requests/{transfer}/cancel。
     */
    public function cancel(Request $request, ServiceTransfer $transfer): JsonResponse
    {
        $user = $this->authorizeParticipant($request, $transfer);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $cancelled = $this->transfers->cancel($transfer, (int) $user->id, $validated['reason'] ?? null, false);

        return $this->success([
            'transfer' => (new ClientServiceTransferResource($cancelled->loadMissing(['service', 'fromUser', 'toUser'])))->resolve(),
        ], '已撤销');
    }

    private function authorizeParticipant(Request $request, ServiceTransfer $transfer): User
    {
        $user = $request->user();
        if (! $user instanceof User || ! $transfer->involvesUser((int) $user->id)) {
            throw new BusinessException('无权操作该转移请求', 40300, 403);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveToUserId(array $validated): ?int
    {
        $toUserId = isset($validated['to_user_id']) ? (int) $validated['to_user_id'] : 0;
        if ($toUserId > 0) {
            return $toUserId;
        }

        $email = trim((string) ($validated['to_user_email'] ?? ''));
        if ($email === '') {
            return null;
        }

        $toUserId = (int) (User::query()->where('email', $email)->value('id') ?? 0);
        if ($toUserId <= 0) {
            throw new BusinessException('接收方账号不存在');
        }

        return $toUserId;
    }
}
