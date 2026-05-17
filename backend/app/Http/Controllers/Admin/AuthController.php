<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use App\Support\AccountIdentifier;
use App\Support\TextSanitizer;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * 管理员登录
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $result = $this->authService->adminLogin(
            $request->input('username'),
            $request->input('password'),
            $request->ip()
        );

        return $this->success($result, '登录成功');
    }

    /**
     * 获取当前管理员信息
     */
    public function info(Request $request)
    {
        $admin = $request->user();
        $admin->load('role');

        return $this->success($this->serializeAdmin($admin));
    }

    public function updateProfile(Request $request)
    {
        $request->merge([
            'email' => AccountIdentifier::normalizeOptionalEmail((string) $request->input('email')),
        ]);

        $data = $request->validate([
            'nickname' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
        ]);

        $admin = $request->user();
        $nickname = TextSanitizer::clean((string) ($data['nickname'] ?? ''));

        $admin->update([
            'nickname' => $nickname !== '' ? $nickname : null,
            'email' => $data['email'] ?? null,
        ]);

        $admin->load('role');

        return $this->success($this->serializeAdmin($admin), '资料更新成功');
    }

    /**
     * 登出
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, '已退出登录');
    }

    private function serializeAdmin(object $admin): array
    {
        return [
            'id' => (int) $admin->id,
            'username' => (string) $admin->username,
            'nickname' => (string) ($admin->nickname ?? ''),
            'email' => (string) ($admin->email ?? ''),
            'role' => (string) ($admin->role?->label ?? $admin->role?->name ?? 'unknown'),
            'permissions' => $admin->resolvedPermissions(),
        ];
    }
}
