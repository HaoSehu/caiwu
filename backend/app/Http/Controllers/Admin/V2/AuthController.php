<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Auth\DisableMfaRequest;
use App\Http\Requests\Admin\V2\Auth\EnableMfaRequest;
use App\Http\Requests\Admin\V2\Auth\LoginRequest;
use App\Http\Requests\Admin\V2\Auth\UpdatePasswordRequest;
use App\Http\Requests\Admin\V2\Auth\UpdateProfileRequest;
use App\Http\Requests\Admin\V2\Auth\VerifyMfaRequest;
use App\Http\Resources\Admin\V2\AdminAuthProfileResource;
use App\Http\Resources\Admin\V2\AdminAuthSessionResource;
use App\Services\Admin\Rbac\AdminStaffService;
use App\Services\Auth\AdminMfaService;
use App\Services\Auth\AuthService;
use App\Support\TextSanitizer;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly AdminStaffService $adminStaffService,
        private readonly AdminMfaService $adminMfaService,
    ) {}

    public function login(LoginRequest $request)
    {
        $result = $this->authService->adminLogin(
            (string) $request->input('username'),
            (string) $request->input('password'),
            (string) $request->ip(),
            $request->userAgent(),
        );

        $message = ($result['mfa_required'] ?? false) ? '请输入动态验证码完成登录' : '登录成功';

        return $this->success(AdminAuthSessionResource::make($result)->resolve(), $message);
    }

    /**
     * 双因素登录第二步：挑战凭证 + 动态码（或恢复码）换正式令牌。
     */
    public function verifyMfa(VerifyMfaRequest $request)
    {
        $result = $this->authService->verifyAdminMfaLogin(
            (string) $request->input('challenge_token'),
            (string) $request->input('code'),
            (string) $request->ip(),
            $request->userAgent(),
        );

        return $this->success(AdminAuthSessionResource::make($result)->resolve(), '登录成功');
    }

    /**
     * 第一步：生成 TOTP 绑定草稿（密钥只进缓存，确认后才落库）。
     */
    public function setupMfa(Request $request)
    {
        return $this->success($this->adminMfaService->beginSetup($request->user()));
    }

    /**
     * 第二步：用一次动态码确认绑定，返回仅此一次可见的恢复码。
     */
    public function enableMfa(EnableMfaRequest $request)
    {
        $result = $this->adminMfaService->enable(
            $request->user(),
            (string) $request->input('code'),
            (string) $request->ip(),
        );

        return $this->success($result, '双因素认证已启用，请立即保存恢复码');
    }

    /**
     * 解绑：同时校验当前密码与动态码（或恢复码）。
     */
    public function disableMfa(DisableMfaRequest $request)
    {
        $this->adminMfaService->disable(
            $request->user(),
            (string) $request->input('password'),
            (string) $request->input('code'),
            (string) $request->ip(),
        );

        return $this->success(null, '双因素认证已解绑');
    }

    public function info(Request $request)
    {
        $admin = $request->user();
        $admin->loadMissing('role');

        return $this->success([
            'admin' => AdminAuthProfileResource::make($admin)->resolve(),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $data = $request->validated();
        $admin = $request->user();
        $nickname = TextSanitizer::clean((string) ($data['nickname'] ?? ''));

        $admin->update([
            'nickname' => $nickname !== '' ? $nickname : null,
        ]);

        $admin->loadMissing('role');

        return $this->success([
            'admin' => AdminAuthProfileResource::make($admin)->resolve(),
        ], '资料更新成功');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $payload = $request->payload();

        $this->adminStaffService->updateOwnPassword(
            staff: $request->user(),
            currentPassword: (string) $payload['current_password'],
            password: (string) $payload['password'],
            ipAddress: (string) $request->ip(),
        );

        return $this->success(null, '密码已更新');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(null, '已退出登录');
    }
}
