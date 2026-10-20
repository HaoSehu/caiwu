<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MessageDeliveryAttempt;
use App\Models\MessageLog;
use App\Models\Setting;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Mail\Contracts\MailDriver;
use App\Services\Mail\MailDriverManager;
use App\Services\System\AdminLogService;
use App\Services\System\NotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Mail\FakeMailDriver;
use Tests\TestCase;

/**
 * 消息逐次投递尝试（对齐 howstack-bos message_delivery_attempts）：
 * 1. 同一 delivery_key 的多次调用复用同一条 message_logs；
 * 2. 每次调用落一行 attempts，attempt_no 递增；
 * 3. 失败尝试记录异常类名与摘要，且不吞掉原异常；
 * 4. attempts 不保存收件地址与正文（列级约束由结构固定）。
 */
class MessageDeliveryAttemptTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('notification', 'email_enabled', '1');
    }

    public function test_retries_reuse_one_message_log_and_record_each_attempt(): void
    {
        $driver = new FakeMailDriver('fake');
        $service = $this->makeService($driver);
        $deliveryKey = 'delivery_'.uniqid();
        $to = $deliveryKey.'@example.test';

        $service->sendEmail($to, '主题', '<p>正文</p>', null, $deliveryKey, 1);
        $service->sendEmail($to, '主题', '<p>正文</p>', null, $deliveryKey, 2);

        $logs = MessageLog::query()->where('delivery_key', $deliveryKey)->get();
        $this->assertCount(1, $logs, '同一 delivery_key 只能有一条逻辑消息');
        $logId = (int) $logs->first()->getKey();

        $attempts = MessageDeliveryAttempt::query()
            ->where('message_log_id', $logId)
            ->orderBy('attempt_no')
            ->get();
        $this->assertCount(2, $attempts);
        $this->assertSame([1, 2], $attempts->pluck('attempt_no')->all());
        $this->assertSame(
            [MessageDeliveryAttempt::STATUS_SUCCESS, MessageDeliveryAttempt::STATUS_SUCCESS],
            $attempts->pluck('status')->all(),
        );
        $this->assertSame(2, $driver->count());
    }

    public function test_failed_attempt_records_error_without_swallowing_exception(): void
    {
        $failure = new class implements MailDriver
        {
            public function key(): string
            {
                return 'failing';
            }

            public function label(): string
            {
                return '失败驱动';
            }

            public function sendHtml(string $to, string $subject, string $html, array $context = []): void
            {
                throw new \RuntimeException('SMTP 连接被拒绝');
            }
        };

        $service = $this->makeService($failure);
        $deliveryKey = 'fail_'.uniqid();
        $to = $deliveryKey.'@example.test';

        try {
            $service->sendEmail($to, '主题', '<p>正文</p>', null, $deliveryKey, 1);
            $this->fail('发送失败必须向调用方抛出异常');
        } catch (\RuntimeException $exception) {
            $this->assertSame('SMTP 连接被拒绝', $exception->getMessage());
        }

        $log = MessageLog::query()->where('delivery_key', $deliveryKey)->first();
        $this->assertInstanceOf(MessageLog::class, $log);
        $this->assertSame('failed', $log->status);

        $attempt = MessageDeliveryAttempt::query()
            ->where('message_log_id', (int) $log->getKey())
            ->first();
        $this->assertInstanceOf(MessageDeliveryAttempt::class, $attempt);
        $this->assertSame(MessageDeliveryAttempt::STATUS_FAILED, $attempt->status);
        $this->assertSame('RuntimeException', $attempt->error_code);
        $this->assertSame('SMTP 连接被拒绝', $attempt->error_msg);
    }

    public function test_cleanup_removes_attempts_with_their_message_logs(): void
    {
        $log = MessageLog::query()->create([
            'channel' => 'sms',
            'recipient' => '13800000000',
            'template_code' => 'test_cleanup',
            'content' => '清理测试',
            'status' => 'success',
            'origin_type' => 'sms_template',
            'origin_id' => 0,
        ]);
        DB::table('message_logs')
            ->where('id', $log->id)
            ->update(['created_at' => now()->subDays(400)]);

        $attempt = MessageDeliveryAttempt::query()->create([
            'message_log_id' => (int) $log->id,
            'attempt_no' => 1,
            'status' => MessageDeliveryAttempt::STATUS_SUCCESS,
            'error_code' => '',
            'error_msg' => null,
            'duration_ms' => 12,
            'created_at' => now()->subDays(400),
            'finished_at' => now()->subDays(400),
        ]);

        $result = app(AdminLogService::class)->cleanup([
            'type' => 'sms',
            'keep_days' => 180,
            'confirm_text' => '立即清理',
        ]);

        $this->assertGreaterThanOrEqual(1, (int) ($result['affected']['sms'] ?? 0));
        $this->assertFalse(MessageLog::query()->whereKey($log->id)->exists());
        $this->assertFalse(
            MessageDeliveryAttempt::query()->whereKey($attempt->id)->exists(),
            '清理 message_logs 时必须同步删除其投递尝试',
        );
    }

    private function makeService(MailDriver $driver): NotificationService
    {
        $key = $driver->key();
        $bindingResolver = new class($key) extends IntegrationDriverBindingResolver
        {
            public function __construct(private readonly string $driverKey) {}

            public function mailContext(?string $driverKey = null): array
            {
                return ['plugin_id' => null, 'driver_key' => $this->driverKey];
            }

            public function mailDriverCandidates(): array
            {
                return [$this->driverKey];
            }

            public function mailDriverKey(): string
            {
                return $this->driverKey;
            }
        };

        return new NotificationService(
            new MailDriverManager([$driver], $bindingResolver),
            $bindingResolver,
        );
    }
}
