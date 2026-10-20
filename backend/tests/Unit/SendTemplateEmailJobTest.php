<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\SendTemplateEmailJob;
use Tests\TestCase;

/**
 * 邮件队列任务的投递键必须在序列化/反序列化（队列重试）后保持不变，
 * 否则每次重试都会被当成新消息，attempts 无法归并。
 */
class SendTemplateEmailJobTest extends TestCase
{
    public function test_delivery_key_survives_serialization(): void
    {
        $job = new SendTemplateEmailJob('user@example.test', '100001', ['code' => '123456']);
        $key = $job->deliveryKey;

        $this->assertNotSame('', $key);

        $restored = unserialize(serialize($job));
        $this->assertInstanceOf(SendTemplateEmailJob::class, $restored);
        $this->assertSame($key, $restored->deliveryKey, '重试必须沿用同一投递键');
    }

    public function test_each_job_gets_a_unique_delivery_key(): void
    {
        $first = new SendTemplateEmailJob('a@example.test', '100001');
        $second = new SendTemplateEmailJob('a@example.test', '100001');

        $this->assertNotSame($first->deliveryKey, $second->deliveryKey);
    }
}
