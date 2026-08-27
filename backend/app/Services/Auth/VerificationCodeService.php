<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\CacheKey;
use App\Support\EmailNotificationTemplateDefaults;
use Illuminate\Support\Facades\Cache;

class VerificationCodeService
{
    private const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * 邮箱验证码默认有效期（分钟）：与邮件模板正文 expire_minutes、
     * 插件测试邮件兜底文案共用 EmailNotificationTemplateDefaults 的单一来源。
     */
    private const EMAIL_CODE_TTL_MINUTES = EmailNotificationTemplateDefaults::EMAIL_CODE_EXPIRE_MINUTES;

    /**
     * 手机验证码默认有效期（分钟）：短信侧历史口径为 5 分钟，
     * 与阿里云 ValidTime=300 秒、短信正文 ${min} 相互咬合，勿并入邮件的 10 分钟。
     */
    private const PHONE_CODE_TTL_MINUTES = 5;

    /**
     * 账号维度验证码校验：先按 guest 维度校验，未命中且用户存在时回退用户维度。
     */
    public function verifyAccountCode(string $accountType, string $account, string $code, ?User $user): bool
    {
        $verified = $accountType === 'phone'
            ? $this->verifyPhoneCode('guest', $account, $code)
            : $this->verifyEmailCode('guest', $account, $code);

        if (! $verified && $user) {
            $verified = $accountType === 'phone'
                ? $this->verifyPhoneCode((int) $user->id, $account, $code)
                : $this->verifyEmailCode((int) $user->id, $account, $code);
        }

        return $verified;
    }

    /**
     * 存储邮箱验证码（email 渠道薄壳，实际逻辑见 storeCode）。
     */
    public function storeEmailCode(int|string $userId, string $email, string $code, int $minutes = self::EMAIL_CODE_TTL_MINUTES): void
    {
        $this->storeCode('email', $userId, $email, $code, $minutes);
    }

    /**
     * 验证邮箱验证码（email 渠道薄壳，实际逻辑见 verifyCode）。
     */
    public function verifyEmailCode(int|string $userId, string $email, string $code): bool
    {
        return $this->verifyCode('email', $userId, $email, $code);
    }

    /**
     * 存储手机验证码（phone 渠道薄壳，实际逻辑见 storeCode）。
     */
    public function storePhoneCode(int|string $userId, string $phone, string $code, int $minutes = self::PHONE_CODE_TTL_MINUTES): void
    {
        $this->storeCode('phone', $userId, $phone, $code, $minutes);
    }

    /**
     * 验证手机验证码（phone 渠道薄壳，实际逻辑见 verifyCode）。
     */
    public function verifyPhoneCode(int|string $userId, string $phone, string $code): bool
    {
        return $this->verifyCode('phone', $userId, $phone, $code);
    }

    /**
     * 存储指定渠道验证码并清空错误尝试计数。
     */
    private function storeCode(string $channel, int|string $userId, string $target, string $code, int $minutes): void
    {
        $cacheKey = CacheKey::verificationCode($this->targetKey($channel, $userId, $target));
        Cache::store('redis_volatile')->put($cacheKey, $code, now()->addMinutes($minutes));
        Cache::store('redis_volatile')->forget($this->attemptKey($channel, $userId, $target));
    }

    /**
     * 预校验手机验证码：命中不消费，供换绑流程第一步使用，最终提交仍需消费原验证码复核。
     */
    public function peekPhoneCode(int|string $userId, string $phone, string $code): bool
    {
        return $this->verifyCode('phone', $userId, $phone, $code, false);
    }

    /**
     * 预校验邮箱验证码：命中不消费，供换绑流程第一步使用，最终提交仍需消费原验证码复核。
     */
    public function peekEmailCode(int|string $userId, string $email, string $code): bool
    {
        return $this->verifyCode('email', $userId, $email, $code, false);
    }

    /**
     * 校验指定渠道验证码：命中即作废；未命中仅在有码可校验时计入失败尝试。
     */
    private function verifyCode(string $channel, int|string $userId, string $target, string $code, bool $consume = true): bool
    {
        $cacheKey = CacheKey::verificationCode($this->targetKey($channel, $userId, $target));
        $attemptKey = $this->attemptKey($channel, $userId, $target);
        $cached = Cache::store('redis_volatile')->get($cacheKey);

        if ($cached !== null && $cached === $code) {
            if ($consume) {
                Cache::store('redis_volatile')->forget($cacheKey);
                Cache::store('redis_volatile')->forget($attemptKey);
            }

            return true;
        }

        return $cached !== null && $this->recordFailedAttempt($attemptKey, $cacheKey);
    }

    /**
     * 记录错误尝试，超过上限后作废验证码。
     */
    private function recordFailedAttempt(string $attemptKey, string $cacheKey): bool
    {
        $attempts = (int) Cache::store('redis_volatile')->increment($attemptKey, 1);
        Cache::store('redis_volatile')->put($attemptKey, $attempts, now()->addMinutes(10));

        if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::store('redis_volatile')->forget($cacheKey);
            Cache::store('redis_volatile')->forget($attemptKey);
        }

        return false;
    }

    /**
     * 目标标识归一化：邮箱取小写 md5 指纹，手机取纯数字串（与原键拼接结果逐字一致）。
     */
    private function targetIdentity(string $channel, string $target): string
    {
        if ($channel === 'email') {
            return md5(mb_strtolower($target));
        }

        return (string) preg_replace('/\D+/', '', $target);
    }

    /**
     * 验证码本体的渠道目标键。
     */
    private function targetKey(string $channel, int|string $userId, string $target): string
    {
        return CacheKey::verificationTargetKey($channel, $userId, $this->targetIdentity($channel, $target));
    }

    /**
     * 错误尝试计数的渠道目标键。
     */
    private function attemptKey(string $channel, int|string $userId, string $target): string
    {
        return CacheKey::verificationAttemptKey($channel, $userId, $this->targetIdentity($channel, $target));
    }
}
