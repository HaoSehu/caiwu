<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Support\AccountIdentifier;
use Illuminate\Support\Facades\Hash;

class ClientLoginFlowService
{
    public function __construct(
        private AuthService $authService,
        private GeeTestService $geeTestService,
        private LoginRiskControlService $loginRiskControlService,
    ) {}

    /**
     * 密码登录全流程：软锁检查 → 条件极验 → 登录 → 失败计数 / 成功清理。
     */
    public function login(string $rawAccount, string $password, string $ip, ?string $userAgent, mixed $captchaInput): array
    {
        $account = AccountIdentifier::normalizeAccount($rawAccount);

        $this->assertLoginNotLocked($account, $ip);

        if ($this->loginRiskControlService->shouldRequireCaptcha($account, $ip)) {
            $this->assertCaptchaVerified($captchaInput, $ip, true);
        }

        try {
            $result = $this->authService->clientLogin($account, $password, $ip, $userAgent);
        } catch (BusinessException $exception) {
            if ($exception->getErrorCode() === 40100) {
                $this->loginRiskControlService->recordFailedAttempt($account, $ip);
                $this->authService->notifyClientLoginFailureOnce($account, $ip, $userAgent);
            }

            throw $exception;
        }

        $this->loginRiskControlService->clearSuccessfulLogin($account, $ip);

        return $result;
    }

    /**
     * 验证码插件未启用时的兜底锁定：失败次数超阈值直接拒绝登录。
     * $includeAccountDimension 为 false 时仅按 IP 维度锁定（验证码通道不连带密码通道的账号锁定）。
     */
    public function assertLoginNotLocked(string $account, string $ip, bool $includeAccountDimension = true): void
    {
        if ($this->loginRiskControlService->isLoginLocked($account, $ip, $includeAccountDimension)) {
            throw new BusinessException('登录尝试次数过多，请稍后再试', 42900, 429);
        }
    }

    /**
     * 极验行为验证，未通过时抛业务异常；$captchaRequired 时附带 captcha_required 载荷驱动前端弹码。
     */
    public function assertCaptchaVerified(mixed $captchaInput, string $ip, bool $captchaRequired = false): void
    {
        $result = $this->geeTestService->verify($captchaInput, $ip);

        if (! ($result['ok'] ?? false)) {
            throw new BusinessException(
                $result['message'] ?? '行为验证未通过，请重试',
                42210,
                422,
                $captchaRequired ? ['captcha_required' => true] : null
            );
        }
    }

    /**
     * 敏感操作（提现账户改绑等）的登录密码二次确认。
     * 失败计入登录风控，防止持有 token 的高频尝试把该接口当密码爆破预言机。
     */
    public function verifyPasswordWithRiskControl(User $user, string $password, string $ip): void
    {
        $account = AccountIdentifier::normalizeAccount((string) ($user->email ?? $user->phone ?? ''));

        $this->assertLoginNotLocked($account, $ip);

        if (! Hash::check($password, (string) $user->password)) {
            $this->loginRiskControlService->recordFailedAttempt($account, $ip);

            throw new BusinessException('登录密码错误', 42200, 422);
        }

        // 密码已确认：解除该账号密码通道的失败计数，避免后续验证码校验失败把已确认密码的用户连带锁定。
        $this->loginRiskControlService->clearSuccessfulLogin($account, $ip);
    }
}
