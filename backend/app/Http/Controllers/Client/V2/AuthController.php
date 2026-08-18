<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\V2\Auth\ExchangeLoginAsCodeRequest;
use App\Http\Requests\Client\V2\Auth\LoginByCodeRequest;
use App\Http\Requests\Client\V2\Auth\LoginRequest;
use App\Http\Requests\Client\V2\Auth\RegisterRequest;
use App\Http\Requests\Client\V2\Auth\ResetPasswordRequest;
use App\Http\Requests\Client\V2\Auth\SendEmailCodeRequest;
use App\Http\Requests\Client\V2\Auth\SendPhoneCodeRequest;
use App\Http\Requests\Client\V2\Auth\UpdateAlipayAccountRequest;
use App\Http\Requests\Client\V2\Auth\UpdateEmailRequest;
use App\Http\Requests\Client\V2\Auth\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Client\V2\Auth\UpdatePasswordRequest;
use App\Http\Requests\Client\V2\Auth\UpdatePhoneRequest;
use App\Http\Requests\Client\V2\Auth\UpdateProfileRequest;
use App\Http\Resources\Client\V2\ClientUserInfoResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\ClientLoginFlowService;
use App\Services\Auth\GeeTestService;
use App\Services\Auth\SendAccountCodeService;
use App\Services\Auth\VerificationCodeService;
use App\Support\AccountIdentifier;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private GeeTestService $geeTestService,
        private ClientLoginFlowService $loginFlowService,
        private SendAccountCodeService $sendAccountCodeService,
        private VerificationCodeService $codeService,
    ) {}

    /**
     * 客户登录
     */
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $result = $this->loginFlowService->login(
            (string) $data['account'],
            (string) $data['password'],
            (string) $request->ip(),
            $request->userAgent(),
            $request->input('captcha')
        );

        return $this->success($result, '登录成功');
    }

    /**
     * 客户验证码登录
     */
    public function loginByCode(LoginByCodeRequest $request)
    {
        $data = $request->validated();

        $account = AccountIdentifier::normalizeAccount((string) $data['account']);
        $accountType = AccountIdentifier::detectType($account);
        $code = (string) $data['code'];

        // 验证码插件未启用时的兜底锁定：防止验证码登录通道被爆破。
        // 验证码本身就是第二因素，不连带密码通道的账号维度软锁定（避免第三方用密码通道失败锁定他人验证码登录）。
        $this->loginFlowService->assertLoginNotLocked($account, (string) $request->ip(), false);

        // 先校验验证码再解析用户，未注册与验证码错误统一文案并保持时序一致
        $user = $this->authService->findClientByAccount($accountType, $account);

        if (! $this->codeService->verifyAccountCode($accountType, $account, $code, $user) || ! $user) {
            return $this->error(42200, '账号或验证码错误');
        }

        $result = $this->authService->clientLoginByCode(
            $account,
            $code,
            (string) $request->ip(),
            $request->userAgent()
        );

        return $this->success($result, '登录成功');
    }

    /**
     * 客户注册
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $accountType = AccountIdentifier::detectType((string) $data['account']);
        $account = AccountIdentifier::normalizeAccount((string) $data['account']);
        $verified = $accountType === 'phone'
            ? $this->codeService->verifyPhoneCode('guest', $account, (string) $data['code'])
            : $this->codeService->verifyEmailCode('guest', $account, (string) $data['code']);

        if (! $verified) {
            return $this->error(42200, $accountType === 'phone' ? '短信验证码错误或已过期' : '邮箱验证码错误或已过期');
        }

        $result = $this->authService->clientRegister(
            array_merge($data, [
                'account' => $account,
                'email' => $accountType === 'email' ? $account : ($data['email'] ?? null),
                'phone' => $accountType === 'phone' ? $account : ($data['phone'] ?? null),
            ]),
            $request->ip()
        );

        return $this->success($result, '注册成功');
    }

    public function captchaConfig()
    {
        return $this->success([
            'enabled' => $this->geeTestService->isEnabled(),
            'captcha_id' => $this->geeTestService->getCaptchaId(),
            'script_url' => $this->geeTestService->getScriptUrl(),
        ]);
    }

    public function captchaScript()
    {
        try {
            $scriptContent = $this->geeTestService->getScriptContent();
        } catch (\Throwable $exception) {
            report($exception);

            $scriptContent = $this->geeTestService->getFallbackScriptContent();
        }

        return response($scriptContent, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=43200',
        ]);
    }

    public function exchangeLoginAsCode(ExchangeLoginAsCodeRequest $request)
    {
        $data = $request->validated();

        return $this->success(
            $this->authService->exchangeAdminLoginAsCode(
                (string) $data['code'],
                (string) $request->ip(),
                (string) ($request->userAgent() ?? '')
            ),
            '代登录成功'
        );
    }

    /**
     * 获取当前用户信息
     */
    public function info(Request $request)
    {
        $user = $request->user();
        $user->loadMissing([
            'memberLevel',
            'account',
        ]);

        return $this->success(new ClientUserInfoResource($user));
    }

    /**
     * 登出
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, '已退出登录');
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $data = $request->validated();

        $freshUser = $this->authService->updateClientProfile(
            $request->user(),
            $data,
            $this->clientOperationContext($request)
        );

        return $this->success([
            'nickname' => $freshUser?->nickname,
            'display_name' => $freshUser?->display_name,
        ], '资料更新成功');
    }

    public function alipayAccount(Request $request)
    {
        $user = $request->user();

        return $this->success([
            'real_name' => $user->alipay_real_name,
            'account' => $user->alipay_account,
            'is_bound' => $user->hasBoundAlipayAccount(),
        ]);
    }

    public function updateAlipayAccount(UpdateAlipayAccountRequest $request)
    {
        $data = $request->validated();

        $user = $request->user();
        $requestIp = (string) $request->ip();

        // 提现账户改绑必须登录密码二次确认，防止登录态被滥用直接改绑提现账户
        $this->loginFlowService->verifyPasswordWithRiskControl($user, (string) $data['password'], $requestIp);

        $phone = trim((string) $data['account']);
        $verified = $this->codeService->verifyPhoneCode($user->id, $phone, (string) $data['code']);

        if (! $verified) {
            return $this->error(42200, '短信验证码错误或已过期');
        }

        $freshUser = $this->authService->updateClientAlipayAccount(
            $user,
            [
                'real_name' => (string) $data['real_name'],
                'account' => $phone,
            ],
            $this->clientOperationContext($request)
        );

        return $this->success([
            'real_name' => $freshUser?->alipay_real_name,
            'account' => $freshUser?->alipay_account,
            'is_bound' => $freshUser ? $freshUser->hasBoundAlipayAccount() : true,
        ], '支付宝资料保存成功');
    }

    public function notificationPreferences(Request $request)
    {
        $user = $request->user();

        return $this->success([
            'login_notify' => (int) (($user->login_notify ?? null) ?? $user->login_email_alert),
            'login_location_alert' => (int) ($user->login_location_alert ?? 1),
            'password_change_alert' => (int) ($user->password_change_alert ?? 1),
            'phone_change_alert' => (int) ($user->phone_change_alert ?? 1),
            'email_change_alert' => (int) ($user->email_change_alert ?? 1),
            'marketing_alert' => (int) ($user->marketing_alert ?? 0),
        ]);
    }

    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request)
    {
        $data = $request->validated();

        $freshUser = $this->authService->updateClientNotificationPreferences(
            $request->user(),
            $data,
            $this->clientOperationContext($request)
        );

        return $this->success([
            'login_notify' => (int) (($freshUser->login_notify ?? null) ?? $freshUser->login_email_alert),
            'login_location_alert' => (int) ($freshUser->login_location_alert ?? 1),
            'password_change_alert' => (int) ($freshUser->password_change_alert ?? 1),
            'phone_change_alert' => (int) ($freshUser->phone_change_alert ?? 1),
            'email_change_alert' => (int) ($freshUser->email_change_alert ?? 1),
            'marketing_alert' => (int) ($freshUser->marketing_alert ?? 0),
        ], '通知设置更新成功');
    }

    public function updatePhone(UpdatePhoneRequest $request)
    {
        $data = $request->validated();

        $user = $request->user();
        $phone = (string) $data['phone'];
        $oldPhone = trim((string) ($user->phone ?? ''));

        // 已有绑定手机时，必须验证旧手机验证码，防止登录态被直接换绑
        if ($oldPhone !== '' && ! $this->codeService->verifyPhoneCode($user->id, $oldPhone, (string) $data['old_code'])) {
            return $this->error(42200, '原手机验证码错误或已过期');
        }

        $verified = $this->codeService->verifyPhoneCode($user->id, $phone, (string) $data['code']);
        if (! $verified) {
            return $this->error(42200, '短信验证码错误或已过期');
        }

        $freshUser = $this->authService->updateClientPhone(
            $user,
            $phone,
            $this->clientOperationContext($request)
        );

        return $this->success([
            'phone' => $freshUser->phone,
        ], '手机号修改成功');
    }

    public function sendPhoneCode(SendPhoneCodeRequest $request)
    {
        $data = $request->validated();

        $this->loginFlowService->assertCaptchaVerified($request->input('captcha'), (string) $request->ip());

        $this->sendAccountCodeService->sendPhoneCode(
            $request->user(),
            (string) $data['phone'],
            (string) ($data['purpose'] ?? 'generic'),
            $request->ip(),
            $this->resolveCodeOwnerId($request)
        );

        return $this->success(null, '短信验证码已发送');
    }

    public function updateEmail(UpdateEmailRequest $request)
    {
        $data = $request->validated();

        $user = $request->user();
        $email = (string) $data['email'];
        $oldEmail = trim((string) ($user->email ?? ''));

        // 已有绑定邮箱时，必须验证旧邮箱验证码，防止登录态被直接换绑
        if ($oldEmail !== '' && ! $this->codeService->verifyEmailCode($user->id, $oldEmail, (string) $data['old_code'])) {
            return $this->error(42200, '原邮箱验证码错误或已过期');
        }

        $verified = $this->codeService->verifyEmailCode($user->id, $email, (string) $data['code']);
        if (! $verified) {
            return $this->error(42200, '邮箱验证码错误或已过期');
        }

        $freshUser = $this->authService->updateClientEmail(
            $user,
            $email,
            $this->clientOperationContext($request)
        );

        return $this->success([
            'email' => $freshUser->email,
        ], '邮箱修改成功');
    }

    public function sendEmailCode(SendEmailCodeRequest $request)
    {
        $data = $request->validated();

        $this->loginFlowService->assertCaptchaVerified($request->input('captcha'), (string) $request->ip());

        $this->sendAccountCodeService->sendEmailCode(
            $request->user(),
            (string) $data['email'],
            (string) ($data['purpose'] ?? 'generic'),
            $request->ip(),
            $this->resolveCodeOwnerId($request)
        );

        return $this->success(null, '邮箱验证码已发送');
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->validated();

        $accountType = AccountIdentifier::detectType((string) $data['account']);
        $account = AccountIdentifier::normalizeAccount((string) $data['account']);

        // 先校验验证码再解析用户，未注册与验证码错误统一文案并保持时序一致
        $user = $this->authService->findClientByAccount($accountType, $account);

        if (! $this->codeService->verifyAccountCode($accountType, $account, (string) $data['code'], $user) || ! $user) {
            return $this->error(42200, '账号或验证码错误');
        }

        $this->authService->resetClientPassword($user, (string) $data['password']);

        return $this->success(null, '密码重置成功');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $data = $request->validated();

        $this->authService->updateClientPassword(
            $request->user(),
            (string) $data['oldPassword'],
            (string) $data['newPassword'],
            $this->clientOperationContext($request)
        );

        return $this->success(null, '密码修改成功');
    }

    private function clientOperationContext(Request $request): array
    {
        return [
            'ip_address' => (string) $request->ip(),
            'user_agent' => (string) ($request->userAgent() ?? ''),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
        ];
    }

    private function resolveCodeOwnerId(Request $request): int|string
    {
        $user = $request->user();
        if ($user) {
            return $user->id;
        }

        $token = $request->bearerToken();
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken?->tokenable instanceof User) {
                return (int) $accessToken->tokenable->id;
            }
        }

        return 'guest';
    }
}
