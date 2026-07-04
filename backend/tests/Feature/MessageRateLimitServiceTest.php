<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Auth\MessageRateLimitService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MessageRateLimitServiceTest extends TestCase
{
    public function test_message_limit_only_counts_single_ip_per_minute(): void
    {
        $originalRows = $this->snapshotMessageLimitSettings();
        Cache::flush();

        try {
            DB::table('settings')->where('group_key', 'message_limit')->delete();
            Setting::setValues('message_limit', [
                'email_rate_limit_enabled' => '1',
                'email_ip_minute_limit' => '2',
                'email_cooldown_seconds' => '60',
                'email_target_hourly_limit' => '1',
                'email_ip_hourly_limit' => '1',
            ]);

            $service = app(MessageRateLimitService::class);
            $ip = '203.0.113.10';

            $this->assertTrue($service->check('email', 'first@example.com', $ip)['ok']);
            $service->hit('email', 'first@example.com', $ip);

            $this->assertTrue($service->check('email', 'second@example.com', $ip)['ok']);
            $service->hit('email', 'second@example.com', $ip);

            $blocked = $service->check('email', 'third@example.com', $ip);
            $this->assertFalse($blocked['ok']);
            $this->assertSame('当前 IP 每分钟发送次数已达上限，请稍后再试', $blocked['message']);

            $this->assertTrue($service->check('email', 'first@example.com', '203.0.113.11')['ok']);
        } finally {
            Cache::flush();
            DB::table('settings')->where('group_key', 'message_limit')->delete();
            if ($originalRows !== []) {
                DB::table('settings')->insert($originalRows);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function snapshotMessageLimitSettings(): array
    {
        return DB::table('settings')
            ->where('group_key', 'message_limit')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }
}
