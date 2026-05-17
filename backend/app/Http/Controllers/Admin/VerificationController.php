<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Verification\ListVerificationsRequest;
use App\Http\Resources\Admin\AdminVerificationListResource;
use App\Models\User;
use App\Services\Auth\AdminVerificationQueryService;
use App\Services\Auth\VerificationService;
use App\Services\System\OperationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct(
        private VerificationService $verificationService,
        private OperationLogService $operationLogService,
        private AdminVerificationQueryService $verificationQueryService,
    ) {}

    /**
     * 实名记录列表
     */
    public function index(ListVerificationsRequest $request)
    {
        return $this->paginate(
            $this->verificationQueryService->paginate($request->filters(), $request->perPage()),
            AdminVerificationListResource::class
        );
    }

    /**
     * 实名统计与接口配置
     */
    public function summary()
    {
        return $this->success($this->verificationQueryService->summary());
    }

    /**
     * 实名记录详情
     */
    public function show(User $user)
    {
        return $this->success($this->verificationQueryService->detail($user));
    }

    /**
     * 实名历史记录
     */
    public function history(User $user)
    {
        return $this->success($this->verificationQueryService->history($user));
    }

    /**
     * 解绑实名认证
     */
    public function unbind(User $user, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'reject_reason' => ['required', 'string', 'max:255'],
        ], [
            'reject_reason.required' => '请输入驳回原因',
        ]);

        $adminUser = $request->user();
        $adminUserId = $adminUser?->id;
        $adminName = $adminUser?->name ?? $adminUser?->username;

        $result = $this->verificationService->unbind(
            $user,
            $adminUserId,
            $adminName,
            (string) $payload['reject_reason']
        );

        $this->operationLogService->write(
            userId: $adminUserId,
            userType: 'admin',
            action: 'verification.unbind',
            module: 'verification',
            targetId: $user->id,
            detail: [
                'user_id' => $user->id,
                'user_name' => $user->display_name,
                'real_name' => $result['real_name'],
                'unbound_at' => $result['unbound_at'],
                'operator' => $result['operator'],
                'reject_reason' => $result['reject_reason'],
            ],
            ipAddress: $request->ip(),
        );

        return $this->success($result, '驳回成功');
    }
}
