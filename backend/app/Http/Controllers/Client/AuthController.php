<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\GeeTestService;
use App\Services\Auth\LoginRiskControlService;
use App\Services\Auth\MessageRateLimitService;
use App\Services\Auth\VerificationCodeService;
use App\Services\System\NotificationService;
use App\Services\System\SmsService;
use App\Support\AccountIdentifier;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private GeeTestService $geeTestService,
        private LoginRiskControlService $loginRiskControlService,
        private MessageRateLimitService $messageRateLimitService,
        private NotificationService $notificationService,
        private SmsService $smsService,
        private VerificationCodeService $codeService,
    ) {}

    /**
     * 客户登录
     */
    public function login(Request $request)
    {
        $request->merge([
            'account' => $this->resolveSubmittedAccount($request),
        ]);

        $data = $request->validate([
            'account' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail) {
                if (AccountIdentifier::detectType((string) $value) === null) {
                    $fail('请输入正确的邮箱或手机号');
                }
            }],
            'password' => 'required|string|min:6',
        ]);

        $normalizedAccount = AccountIdentifier::normalizeAccount((string) $data['account']);
        $requestIp = (string) $request->ip();

        if (
            $this->loginRiskControlService->shouldRequireCaptcha($normalizedAccount, $requestIp)
            && ($response = $this->ensureGeeTestVerified($request, true))
        ) {
            return $response;
        }

        try {
            $result = $this->authService->clientLogin(
                $normalizedAccount,
                (string) $data['password'],
                $requestIp,
                $request->userAgent()
            );
        } catch (BusinessException $exception) {
            if ($exception->getErrorCode() === 40100) {
                $this->loginRiskControlService->recordFailedAttempt($normalizedAccount, $requestIp);
                $this->authService->notifyClientLoginFailureOnce(
                    $normalizedAccount,
                    $requestIp,
                    $request->userAgent()
                );
            }

            throw $exception;
        }

        $this->loginRiskControlService->clearSuccessfulLogin($normalizedAccount, $requestIp);

        return $this->success($result, '登录成功');
    }

    /**
     * 客户注册
     */
    public function register(Request $request)
    {
        $request->merge([
            'account' => $this->resolveSubmittedAccount($request),
            'email' => AccountIdentifier::normalizeOptionalEmail((string) $request->input('email')),
            'phone' => AccountIdentifier::normalizeOptionalPhone((string) $request->input('phone')),
        ]);

        $data = $request->validate([
            'account' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail) {
                if (AccountIdentifier::detectType((string) $value) === null) {
                    $fail('请输入正确的邮箱或手机号');
                }
            }],
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
            'nickname' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'phone' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== null && $value !== '' && AccountIdentifier::detectType((string) $value) !== 'phone') {
                    $fail('请输入正确的手机号');
                }
            }],
            'referral_code' => 'nullable|string|max:24',
        ]);

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

    public function exchangeLoginAsCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:32', 'max:128'],
        ]);

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
        ]);
        if (User::accountTableAvailable()) {
            $user->loadMissing('account');
        }
        if (User::profileTableAvailable()) {
            $user->loadMissing('profile');
        }
        $memberLevel = $user->memberLevel;

        return $this->success([
            'id' => $user->id,
            'email' => (string) ($user->email ?? ''),
            'nickname' => $user->nickname,
            'display_name' => (string) ($user->display_name ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'balance' => (string) $user->balance,
            'referral_code' => $user->referral_code,
            'referrer_user_id' => $user->referrer_user_id,
            'member_level_id' => $user->member_level_id,
            'total_sales_amount' => $user->total_sales_amount,
            'member_level' => $memberLevel ? [
                'id' => $memberLevel->id,
                'name' => $memberLevel->name,
                'code' => $memberLevel->code,
                'reward_rate' => $memberLevel->reward_rate,
            ] : null,
            'status' => $user->status,
            'is_verified' => $user->is_verified,
            'real_name' => $user->real_name,
            'id_card_masked' => $this->maskIdCard((string) $user->id_card),
            'verification_status' => $user->verification_status,
            'verification_message' => $user->verification_message,
            'verification_certify_id' => $user->verification_certify_id,
            'login_email_alert' => (int) $user->login_email_alert,
            'login_notify' => (int) (($user->login_notify ?? null) ?? $user->login_email_alert),
            'login_location_alert' => (int) ($user->login_location_alert ?? 1),
            'password_change_alert' => (int) ($user->password_change_alert ?? 1),
            'phone_change_alert' => (int) ($user->phone_change_alert ?? 1),
            'email_change_alert' => (int) ($user->email_change_alert ?? 1),
            'marketing_alert' => (int) ($user->marketing_alert ?? 0),
            'alipay_account' => [
                'real_name' => $user->alipay_real_name,
                'account' => $user->alipay_account,
                'is_bound' => $this->hasBoundAlipayAccount($user),
            ],
            'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_ip' => (string) ($user->last_login_ip ?? ''),
            'verified_at' => $user->verified_at?->format('Y-m-d H:i:s'),
            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 登出
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, '已退出登录');
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'nickname' => 'nullable|string|max:50',
        ]);

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
            'is_bound' => $this->hasBoundAlipayAccount($user),
        ]);
    }

    public function updateAlipayAccount(Request $request)
    {
        $data = $request->validate([
            'real_name' => ['required', 'string', 'max:80'],
            'account' => ['required', 'regex:/^1[3-9]\d{9}$/'],
            'code' => ['required', 'string', 'size:6'],
        ], [
            'account.regex' => '请输入正确的支付宝手机号',
        ]);

        $user = $request->user();
        $phone = trim((string) $data['account']);
        $verified = $this->codeService->verifyPhoneCode($user->id, $phone, (string) $data['code']);
        if (! $verified) {
            $verified = $this->codeService->verifyPhoneCode('guest', $phone, (string) $data['code']);
        }

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
            'is_bound' => $freshUser ? $this->hasBoundAlipayAccount($freshUser) : true,
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

    public function updateNotificationPreferences(Request $request)
    {
        $data = $request->validate([
            'login_notify' => 'required|boolean',
            'login_location_alert' => 'required|boolean',
            'password_change_alert' => 'required|boolean',
            'phone_change_alert' => 'required|boolean',
            'email_change_alert' => 'required|boolean',
            'marketing_alert' => 'required|boolean',
        ]);

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

    public function updatePhone(Request $request)
    {
        $request->merge([
            'phone' => AccountIdentifier::normalizePhone((string) $request->input('phone')),
        ]);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) {
                if (AccountIdentifier::detectType((string) $value) !== 'phone') {
                    $fail('请输入正确的手机号');
                }
            }],
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $phone = (string) $data['phone'];
        $verified = $this->codeService->verifyPhoneCode($user->id, $phone, (string) $data['code']);
        if (! $verified) {
            $verified = $this->codeService->verifyPhoneCode('guest', $phone, (string) $data['code']);
        }

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

    public function sendPhoneCode(Request $request)
    {
        $request->merge([
            'phone' => AccountIdentifier::normalizePhone((string) $request->input('phone')),
        ]);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) {
                if (AccountIdentifier::detectType((string) $value) !== 'phone') {
                    $fail('请输入正确的手机号');
                }
            }],
        ]);

        if ($response = $this->ensureGeeTestVerified($request)) {
            return $response;
        }

        $userId = $this->resolveCodeOwnerId($request);
        $phone = (string) $data['phone'];
        $code = (string) random_int(100000, 999999);
        $ip = $request->ip();

        if ($response = $this->ensureMessageRateLimitPassed('sms', $phone, $ip)) {
            return $response;
        }

        try {
            $this->smsService->sendVerifyCode($phone, $code);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error(42200, '短信服务暂不可用，请稍后重试');
        }

        $this->messageRateLimitService->hit('sms', $phone, $ip);
        $this->codeService->storePhoneCode($userId, $phone, $code);

        return $this->success(null, '短信验证码已发送');
    }

    public function updateEmail(Request $request)
    {
        $request->merge([
            'email' => AccountIdentifier::normalizeEmail((string) $request->input('email')),
        ]);

        $data = $request->validate([
            'email' => 'required|email|max:100|unique:users,email,'.$request->user()->id,
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $email = (string) $data['email'];
        $verified = $this->codeService->verifyEmailCode($user->id, $email, (string) $data['code']);
        if (! $verified) {
            $verified = $this->codeService->verifyEmailCode('guest', $email, (string) $data['code']);
        }

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

    public function sendEmailCode(Request $request)
    {
        $request->merge([
            'email' => AccountIdentifier::normalizeEmail((string) $request->input('email')),
        ]);

        $data = $request->validate([
            'email' => 'required|email|max:100',
        ]);

        if ($response = $this->ensureGeeTestVerified($request)) {
            return $response;
        }

        $userId = $this->resolveCodeOwnerId($request);
        $email = (string) $data['email'];
        $code = (string) random_int(100000, 999999);
        $ip = $request->ip();

        if ($response = $this->ensureMessageRateLimitPassed('email', $email, $ip)) {
            return $response;
        }

        try {
            $this->notificationService->sendEmailCode($email, $code);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error(42200, '邮件服务暂不可用，请稍后重试');
        }

        $this->messageRateLimitService->hit('email', $email, $ip);
        $this->codeService->storeEmailCode($userId, $email, $code);

        return $this->success(null, '邮箱验证码已发送');
    }

    public function resetPassword(Request $request)
    {
        $request->merge([
            'account' => $this->resolveSubmittedAccount($request),
        ]);

        $data = $request->validate([
            'account' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail) {
                if (AccountIdentifier::detectType((string) $value) === null) {
                    $fail('请输入正确的邮箱或手机号');
                }
            }],
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $accountType = AccountIdentifier::detectType((string) $data['account']);
        $account = AccountIdentifier::normalizeAccount((string) $data['account']);
        $user = $this->authService->findClientByAccount($accountType, $account);

        if (! $user) {
            return $this->error(42200, $accountType === 'phone' ? '手机号未注册' : '邮箱未注册');
        }

        $verified = $accountType === 'phone'
            ? $this->codeService->verifyPhoneCode('guest', $account, (string) $data['code'])
            : $this->codeService->verifyEmailCode('guest', $account, (string) $data['code']);

        if (! $verified) {
            $verified = $accountType === 'phone'
                ? $this->codeService->verifyPhoneCode((int) $user->id, $account, (string) $data['code'])
                : $this->codeService->verifyEmailCode((int) $user->id, $account, (string) $data['code']);
        }

        if (! $verified) {
            return $this->error(42200, $accountType === 'phone' ? '短信验证码错误或已过期' : '邮箱验证码错误或已过期');
        }

        $this->authService->resetClientPassword($user, (string) $data['password']);

        return $this->success(null, '密码重置成功');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'oldPassword' => 'required|string|min:6',
            'newPassword' => 'required|string|min:6',
            'confirmPassword' => 'required|string|same:newPassword',
        ]);

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

    private function hasBoundAlipayAccount(User $user): bool
    {
        return trim((string) $user->alipay_real_name) !== ''
            && trim((string) $user->alipay_account) !== '';
    }

    private function ensureGeeTestVerified(Request $request, bool $captchaRequired = false)
    {
        $result = $this->geeTestService->verify($request->input('captcha'));

        if (! ($result['ok'] ?? false)) {
            return $this->error(
                42210,
                $result['message'] ?? '行为验证未通过，请重试',
                $captchaRequired ? ['captcha_required' => true] : null
            );
        }

        return null;
    }

    private function ensureMessageRateLimitPassed(string $channel, string $target, ?string $ip)
    {
        $result = $this->messageRateLimitService->check($channel, $target, $ip);

        if (! ($result['ok'] ?? false)) {
            return $this->error(42200, $result['message'] ?? '发送过于频繁，请稍后重试');
        }

        return null;
    }

    private function resolveSubmittedAccount(Request $request): string
    {
        $account = trim((string) $request->input('account', ''));
        if ($account !== '') {
            return $account;
        }

        $email = trim((string) $request->input('email', ''));
        if ($email !== '') {
            return $email;
        }

        return trim((string) $request->input('phone', ''));
    }

    private function maskIdCard(string $idCard): string
    {
        if ($idCard === '') {
            return '';
        }

        $length = mb_strlen($idCard);
        if ($length <= 8) {
            return $idCard;
        }

        return mb_substr($idCard, 0, 1).str_repeat('*', max($length - 2, 1)).mb_substr($idCard, -1);
    }
}
