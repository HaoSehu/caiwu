<?php

namespace App\Services\Auth;

use App\Models\Setting;
use Illuminate\Support\Facades\RateLimiter;

class MessageRateLimitService
{
    public function check(string $channel, string $target, ?string $ip = null): array
    {
        $config = $this->getConfig($channel);

        if (! $config['enabled']) {
            return ['ok' => true];
        }

        $normalizedTarget = $this->normalizeTarget($channel, $target);
        $ip = $ip ? trim($ip) : null;

        if ($config['cooldown_seconds'] > 0) {
            $cooldownKey = $this->key($channel, 'cooldown', $normalizedTarget);
            if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
                $seconds = RateLimiter::availableIn($cooldownKey);

                return [
                    'ok' => false,
                    'message' => "发送过于频繁，请 {$seconds} 秒后重试",
                ];
            }
        }

        if ($config['target_hourly_limit'] > 0) {
            $targetHourlyKey = $this->key($channel, 'target-hourly', $normalizedTarget);
            if (RateLimiter::tooManyAttempts($targetHourlyKey, $config['target_hourly_limit'])) {
                return [
                    'ok' => false,
                    'message' => '当前接收目标发送次数已达上限，请稍后再试',
                ];
            }
        }

        if ($ip && $config['ip_hourly_limit'] > 0) {
            $ipHourlyKey = $this->key($channel, 'ip-hourly', $ip);
            if (RateLimiter::tooManyAttempts($ipHourlyKey, $config['ip_hourly_limit'])) {
                return [
                    'ok' => false,
                    'message' => '当前 IP 发送次数已达上限，请稍后再试',
                ];
            }
        }

        return ['ok' => true];
    }

    public function hit(string $channel, string $target, ?string $ip = null): void
    {
        $config = $this->getConfig($channel);

        if (! $config['enabled']) {
            return;
        }

        $normalizedTarget = $this->normalizeTarget($channel, $target);
        $ip = $ip ? trim($ip) : null;

        if ($config['cooldown_seconds'] > 0) {
            RateLimiter::hit(
                $this->key($channel, 'cooldown', $normalizedTarget),
                $config['cooldown_seconds']
            );
        }

        if ($config['target_hourly_limit'] > 0) {
            RateLimiter::hit(
                $this->key($channel, 'target-hourly', $normalizedTarget),
                3600
            );
        }

        if ($ip && $config['ip_hourly_limit'] > 0) {
            RateLimiter::hit(
                $this->key($channel, 'ip-hourly', $ip),
                3600
            );
        }
    }

    private function getConfig(string $channel): array
    {
        return [
            'enabled' => $this->boolValue("{$channel}_rate_limit_enabled", false),
            'cooldown_seconds' => $this->intValue("{$channel}_cooldown_seconds", 60),
            'target_hourly_limit' => $this->intValue("{$channel}_target_hourly_limit", 10),
            'ip_hourly_limit' => $this->intValue("{$channel}_ip_hourly_limit", 20),
        ];
    }

    private function key(string $channel, string $scope, string $identifier): string
    {
        return 'message-rate-limit:'.$channel.':'.$scope.':'.sha1($identifier);
    }

    private function normalizeTarget(string $channel, string $target): string
    {
        return match ($channel) {
            'email' => mb_strtolower(trim($target)),
            'sms' => preg_replace('/\D+/', '', $target) ?: trim($target),
            default => trim($target),
        };
    }

    private function intValue(string $key, int $default): int
    {
        $value = Setting::getValue('message_limit', $key, $default);
        $parsed = (int) $value;

        return $parsed >= 0 ? $parsed : $default;
    }

    private function boolValue(string $key, bool $default): bool
    {
        $value = Setting::getValue('message_limit', $key, $default ? '1' : '0');

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }
}
