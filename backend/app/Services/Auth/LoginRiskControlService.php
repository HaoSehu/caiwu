<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class LoginRiskControlService
{
    private const ACCOUNT_IP_MAX_ATTEMPTS = 1;

    private const ACCOUNT_IP_DECAY_SECONDS = 900;

    private const ACCOUNT_MAX_ATTEMPTS = 1;

    private const ACCOUNT_DECAY_SECONDS = 1800;

    private const IP_MAX_ATTEMPTS = 8;

    private const IP_DECAY_SECONDS = 1800;

    // GeeTest 未启用时的软锁定阈值：比验证码触发阈值宽松，避免误伤正常用户
    private const SOFT_LOCK_ACCOUNT_IP_MAX_ATTEMPTS = 5;

    private const SOFT_LOCK_ACCOUNT_IP_DECAY_SECONDS = 900;

    private const SOFT_LOCK_ACCOUNT_MAX_ATTEMPTS = 10;

    private const SOFT_LOCK_ACCOUNT_DECAY_SECONDS = 1800;

    private const FAILURE_ALERT_DECAY_SECONDS = self::ACCOUNT_DECAY_SECONDS;

    public function __construct(
        private GeeTestService $geeTestService,
    ) {}

    /**
     * GeeTest 未启用时的兜底锁定：账号+IP 或账号维度失败次数超阈值即锁定登录，
     * 防止验证码插件未配置时暴力破解完全无防护。
     */
    public function isLoginLocked(string $account, ?string $ip = null): bool
    {
        if ($this->geeTestService->isEnabled()) {
            return false;
        }

        $account = $this->normalizeAccount($account);
        $ip = $this->normalizeIp($ip);

        if ($account !== '' && $ip !== '' && RateLimiter::tooManyAttempts(
            $this->accountIpKey($account, $ip),
            self::SOFT_LOCK_ACCOUNT_IP_MAX_ATTEMPTS
        )) {
            return true;
        }

        return $account !== '' && RateLimiter::tooManyAttempts(
            $this->accountKey($account),
            self::SOFT_LOCK_ACCOUNT_MAX_ATTEMPTS
        );
    }

    public function shouldRequireCaptcha(string $account, ?string $ip = null): bool
    {
        if (! $this->geeTestService->isEnabled()) {
            return false;
        }

        $account = $this->normalizeAccount($account);
        $ip = $this->normalizeIp($ip);

        if ($account !== '' && $ip !== '' && RateLimiter::tooManyAttempts(
            $this->accountIpKey($account, $ip),
            self::ACCOUNT_IP_MAX_ATTEMPTS
        )) {
            return true;
        }

        if ($account !== '' && RateLimiter::tooManyAttempts(
            $this->accountKey($account),
            self::ACCOUNT_MAX_ATTEMPTS
        )) {
            return true;
        }

        return $ip !== '' && RateLimiter::tooManyAttempts(
            $this->ipKey($ip),
            self::IP_MAX_ATTEMPTS
        );
    }

    public function recordFailedAttempt(string $account, ?string $ip = null): void
    {
        $account = $this->normalizeAccount($account);
        $ip = $this->normalizeIp($ip);

        if ($account !== '' && $ip !== '') {
            RateLimiter::hit($this->accountIpKey($account, $ip), self::ACCOUNT_IP_DECAY_SECONDS);
        }

        if ($account !== '') {
            RateLimiter::hit($this->accountKey($account), self::ACCOUNT_DECAY_SECONDS);
        }

        if ($ip !== '') {
            RateLimiter::hit($this->ipKey($ip), self::IP_DECAY_SECONDS);
        }
    }

    public function clearSuccessfulLogin(string $account, ?string $ip = null): void
    {
        $account = $this->normalizeAccount($account);
        $ip = $this->normalizeIp($ip);

        if ($account !== '' && $ip !== '') {
            RateLimiter::clear($this->accountIpKey($account, $ip));
        }

        if ($account !== '') {
            RateLimiter::clear($this->accountKey($account));
            Cache::store('redis_volatile')->forget($this->failureAlertKey($account));
        }

        if ($ip !== '') {
            RateLimiter::clear($this->ipKey($ip));
        }
    }

    public function acquireFailureAlertLock(string $account): bool
    {
        $account = $this->normalizeAccount($account);
        if ($account === '') {
            return false;
        }

        return Cache::store('redis_volatile')->add(
            $this->failureAlertKey($account),
            1,
            now()->addSeconds(self::FAILURE_ALERT_DECAY_SECONDS)
        );
    }

    private function accountIpKey(string $account, string $ip): string
    {
        return 'login-risk:account-ip:'.sha1($account.'|'.$ip);
    }

    private function accountKey(string $account): string
    {
        return 'login-risk:account:'.sha1($account);
    }

    private function ipKey(string $ip): string
    {
        return 'login-risk:ip:'.sha1($ip);
    }

    private function failureAlertKey(string $account): string
    {
        return 'login-risk:failure-alert:'.sha1($account);
    }

    private function normalizeAccount(string $account): string
    {
        return mb_strtolower(trim($account));
    }

    private function normalizeIp(?string $ip): string
    {
        return trim((string) $ip);
    }
}
