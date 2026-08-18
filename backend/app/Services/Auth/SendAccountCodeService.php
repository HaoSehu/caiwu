<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\System\NotificationService;
use App\Services\System\SmsService;
use App\Support\AccountIdentifier;
use Throwable;

class SendAccountCodeService
{
    private const PHONE_BOUND_PURPOSES = ['verify_bound_phone', 'verify_phone'];

    private const EMAIL_BOUND_PURPOSES = ['verify_bound_email', 'change_email'];

    public function __construct(
        private AuthService $authService,
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
        // 登录场景：校验账号是否存在且正常
        if ($purpose === 'login') {
            $user = $this->authService->findClientByAccount(AccountIdentifier::detectType($phone), $phone);
            if (! $user) {
                throw new BusinessException('手机号未注册', 42200, 422);
            }
            if ($user->status !== 1) {
                throw new BusinessException('账号已被禁用', 40300, 403);
            }
        }

        // 旧手机验证场景：目标必须是当前账号已绑定的手机，防止向任意号码发送旧码
        if (in_array($purpose, self::PHONE_BOUND_PURPOSES, true)) {
            $currentPhone = trim((string) ($actor?->phone ?? ''));
            if ($currentPhone === '' || $currentPhone !== $phone) {
                throw new BusinessException('目标手机号与当前绑定不一致', 42200, 422);
            }
        }

        $this->ensureMessageRateLimitPassed('sms', $phone, $ip);

        $code = (string) random_int(100000, 999999);

        try {
            $this->smsService->sendVerifyCode($phone, $code, [
                'purpose' => $purpose,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            throw new BusinessException('短信服务暂不可用，请稍后重试', 42200, 422);
        }

        $this->messageRateLimitService->hit('sms', $phone, $ip);
        $this->codeService->storePhoneCode($codeOwner, $phone, $code);
    }

    /**
     * 发送邮箱验证码：purpose 校验 → 限流 → 发送 → 落码。
     */
    public function sendEmailCode(?User $actor, string $email, string $purpose, ?string $ip, int|string $codeOwner): void
    {
        // 登录场景：校验账号是否存在且正常
        if ($purpose === 'login') {
            $user = $this->authService->findClientByAccount(AccountIdentifier::detectType($email), $email);
            if (! $user) {
                throw new BusinessException('邮箱未注册', 42200, 422);
            }
            if ($user->status !== 1) {
                throw new BusinessException('账号已被禁用', 40300, 403);
            }
        }

        // 旧邮箱验证场景：目标必须是当前账号已绑定的邮箱，防止向任意邮箱发送旧码
        if (in_array($purpose, self::EMAIL_BOUND_PURPOSES, true)) {
            $currentEmail = trim((string) ($actor?->email ?? ''));
            if ($currentEmail === '' || $currentEmail !== $email) {
                throw new BusinessException('目标邮箱与当前绑定不一致', 42200, 422);
            }
        }

        $this->ensureMessageRateLimitPassed('email', $email, $ip);

        $code = (string) random_int(100000, 999999);

        try {
            $this->notificationService->sendEmailCode($email, $code);
        } catch (Throwable $exception) {
            report($exception);

            throw new BusinessException('邮件服务暂不可用，请稍后重试', 42200, 422);
        }

        $this->messageRateLimitService->hit('email', $email, $ip);
        $this->codeService->storeEmailCode($codeOwner, $email, $code);
    }

    private function ensureMessageRateLimitPassed(string $channel, string $target, ?string $ip): void
    {
        $result = $this->messageRateLimitService->check($channel, $target, $ip);

        if (! ($result['ok'] ?? false)) {
            throw new BusinessException($result['message'] ?? '发送过于频繁，请稍后重试', 42200, 422);
        }
    }
}
