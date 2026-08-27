<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\System\NotificationService;
use App\Services\System\SmsService;
use Throwable;

class SendAccountCodeService
{
    private const PHONE_BOUND_PURPOSES = ['verify_bound_phone', 'verify_phone'];

    private const EMAIL_BOUND_PURPOSES = ['verify_bound_email', 'change_email'];

    public function __construct(
        private MessageRateLimitService $messageRateLimitService,
        private NotificationService $notificationService,
        private SmsService $smsService,
        private VerificationCodeService $codeService,
    ) {}

    /**
     * 发送短信验证码：purpose 校验 → 限流 → 发送 → 落码。
     */
    public function sendPhoneCode(?User $actor, string $phone, string $purpose, ?string $ip, int|string $codeOwner): void
    {
        $this->sendChannelCode('sms', $actor, $phone, $purpose, $ip, $codeOwner);
    }

    /**
     * 发送邮箱验证码：purpose 校验 → 限流 → 发送 → 落码。
     */
    public function sendEmailCode(?User $actor, string $email, string $purpose, ?string $ip, int|string $codeOwner): void
    {
        $this->sendChannelCode('email', $actor, $email, $purpose, $ip, $codeOwner);
    }

    /**
     * 短信/邮件双通道的公共发送核心，仅保留渠道差异点：
     * 错误文案、旧绑定校验的 purpose 集合、实际发送动作与落码方法。
     */
    private function sendChannelCode(string $channel, ?User $actor, string $target, string $purpose, ?string $ip, int|string $codeOwner): void
    {
        $isSms = $channel === 'sms';

        // 旧手机/旧邮箱验证场景：目标必须是当前账号已绑定的值，防止向任意账号发送旧码
        $boundPurposes = $isSms ? self::PHONE_BOUND_PURPOSES : self::EMAIL_BOUND_PURPOSES;
        if (in_array($purpose, $boundPurposes, true)) {
            $boundValue = $isSms ? $actor?->phone : $actor?->email;
            $currentBound = trim((string) $boundValue);
            if ($currentBound === '' || $currentBound !== $target) {
                throw new BusinessException(
                    $isSms ? '目标手机号与当前绑定不一致' : '目标邮箱与当前绑定不一致',
                    42200,
                    422
                );
            }
        }

        // 登录/重置密码场景：防止用户枚举，仅向已注册账号实际发送验证码
        // 但对所有请求返回统一成功响应（攻击者无法通过响应差异判断账号是否存在）
        $loginPurposes = ['login', 'reset', 'reset_password', 'password_reset'];
        if (in_array($purpose, $loginPurposes, true)) {
            $accountExists = $isSms
                ? User::where('phone', $target)->exists()
                : User::where('email', $target)->exists();

            if (! $accountExists) {
                // 账号不存在：执行限流检查（防止滥用）但不实际发送验证码
                $this->ensureMessageRateLimitPassed($channel, $target, $ip);
                $this->messageRateLimitService->hit($channel, $target, $ip);

                // 静默返回，外部调用者看到的是成功（防止枚举）
                return;
            }
        }

        $this->ensureMessageRateLimitPassed($channel, $target, $ip);

        $code = (string) random_int(100000, 999999);

        try {
            if ($isSms) {
                $this->smsService->sendVerifyCode($target, $code, [
                    'purpose' => $purpose,
                ]);
            } else {
                $this->notificationService->sendEmailCode($target, $code);
            }
        } catch (Throwable $exception) {
            report($exception);

            throw new BusinessException($isSms ? '短信服务暂不可用，请稍后重试' : '邮件服务暂不可用，请稍后重试', 42200, 422);
        }

        $this->messageRateLimitService->hit($channel, $target, $ip);

        if ($isSms) {
            $this->codeService->storePhoneCode($codeOwner, $target, $code);
        } else {
            $this->codeService->storeEmailCode($codeOwner, $target, $code);
        }
    }

    private function ensureMessageRateLimitPassed(string $channel, string $target, ?string $ip): void
    {
        $result = $this->messageRateLimitService->check($channel, $target, $ip);

        if (! ($result['ok'] ?? false)) {
            throw new BusinessException($result['message'] ?? '发送过于频繁，请稍后重试', 42200, 422);
        }
    }
}
