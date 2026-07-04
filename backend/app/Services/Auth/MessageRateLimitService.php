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

        $ip = $ip ? trim($ip) : null;
        if (! $ip || $config['ip_minute_limit'] <= 0) {
            return ['ok' => true];
        }

        $ipMinuteKey = $this->key($channel, 'ip-minute', $ip);
        if (RateLimiter::tooManyAttempts($ipMinuteKey, $config['ip_minute_limit'])) {
            return [
                'ok' => false,
                'message' => '当前 IP 每分钟发送次数已达上限，请稍后再试',
            ];
        }

        return ['ok' => true];
    }

    public function hit(string $channel, string $target, ?string $ip = null): void
    {
        $config = $this->getConfig($channel);

        if (! $config['enabled']) {
            return;
        }

        $ip = $ip ? trim($ip) : null;
        if ($ip && $config['ip_minute_limit'] > 0) {
            RateLimiter::hit(
                $this->key($channel, 'ip-minute', $ip),
                60
            );
        }
    }

    private function getConfig(string $channel): array
    {
        return [
            'enabled' => $this->boolValue("{$channel}_rate_limit_enabled", false),
            'ip_minute_limit' => $this->intValue("{$channel}_ip_minute_limit", 6),
        ];
    }

    private function key(string $channel, string $scope, string $identifier): string
    {
        return 'message-rate-limit:'.$channel.':'.$scope.':'.sha1($identifier);
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
