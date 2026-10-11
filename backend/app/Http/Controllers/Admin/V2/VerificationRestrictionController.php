<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\V2\AdminActionResultResource;
use App\Http\Resources\Admin\V2\AdminVerificationRestrictionResource;
use App\Services\System\OperationLogService;
use App\Services\Verification\VerificationRestrictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M11 未实名自动限制：管理端配置读写。
 *
 * 路由（写入 INTEGRATION.md，由集成阶段挂载）：
 * - GET /v2/admin/settings/verification-restrictions   permission: settings.view
 * - PUT /v2/admin/settings/verification-restrictions   permission: settings.manage
 *
 * 裁决口径：enabled 默认 false（上线不自动拦截，由管理员显式开启）；
 * scope 默认 purchase/recharge/withdrawal/service_transfer_receive 开、renewal 关。
 */
class VerificationRestrictionController extends Controller
{
    public function __construct(
        private readonly VerificationRestrictionService $restrictions,
        private readonly OperationLogService $operationLogService,
    ) {}

    public function show(): JsonResponse
    {
        return $this->success(
            AdminVerificationRestrictionResource::make($this->restrictions->get())->resolve()
        );
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'exempt_member_level_ids' => ['present', 'array'],
            'exempt_member_level_ids.*' => ['integer', 'min:1'],
            'scope' => ['present', 'array'],
            'scope.*' => ['required', 'boolean'],
        ]);

        $scopeInput = (array) $request->input('scope', []);
        $unknown = array_diff(array_keys($scopeInput), VerificationRestrictionService::SCOPES);
        if ($unknown !== []) {
            return $this->error(42200, '不支持的受限范围: '.implode(', ', $unknown));
        }

        $validated['scope'] = $scopeInput;

        $this->restrictions->save($validated);

        $this->operationLogService->write(
            userId: (int) ($request->user()->id ?? 0),
            userType: 'admin',
            action: 'verification.restriction.update',
            module: 'settings',
            targetId: 0,
            detail: $validated,
            ipAddress: (string) $request->ip(),
        );

        return $this->success(AdminActionResultResource::make([
            'id' => 'verification-restrictions',
            'status' => 'completed',
            'message' => '实名限制配置已更新',
            'detail' => $validated,
        ])->resolve(), '实名限制配置已更新');
    }
}
