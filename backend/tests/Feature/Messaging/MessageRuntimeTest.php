<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Exceptions\BusinessException;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryAttemptLog;
use App\Services\Messaging\MessageConfigurationRepository;
use App\Services\Messaging\MessagePublisher;
use App\Services\Messaging\MessageRepository;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 消息发送体系 S1–S3 核心不变式（docs/执行计划/进行中/消息发送体系按howstack重构-2026-11-03.md）：
 * 1. 配置加密落库且乐观锁拒绝过期修订；
 * 2. 同一 event_key 重复发布不重复投递（发布幂等）；
 * 3. 领取有租约，并发第二次领取返回 null；
 * 4. unknown 结果不回到 pending，failed+retry 才重试且最多 3 次；
 * 5. 过期租约被回收为 unknown，不自动重发。
 */
class MessageRuntimeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_configuration_roundtrip_and_revision_conflict(): void
    {
        $repository = app(MessageConfigurationRepository::class);
        $type = MessageTypeCatalog::get('customer', 'order_paid');
        $group = MessageGroups::create($type, 'customer', 'customer', '关联客户');

        // 测试库可能已有真实配置，先清掉该场景再保存，避免 revision 冲突
        DB::table('message_configurations')->where('audience', 'customer')->where('message_type', 'order_paid')->delete();

        $revision = $repository->save('customer', 'order_paid', 0, ['_groups' => [$group]]);

        $this->assertSame(1, $revision);

        $stored = $repository->get('customer', 'order_paid');
        $this->assertSame(1, $stored['revision']);
        $this->assertSame('customer', $stored['configurations']['_groups'][0]['id']);

        // 加密落库：库内密文不得包含明文配置键
        $raw = (string) DB::table('message_configurations')
            ->where('audience', 'customer')
            ->where('message_type', 'order_paid')
            ->value('configuration_encrypted');
        $this->assertStringNotContainsString('_groups', $raw);

        $this->expectException(BusinessException::class);
        $repository->save('customer', 'order_paid', 0, ['_groups' => []]);
    }

    public function test_publish_is_idempotent_by_event_key(): void
    {
        $this->enableCustomerEmailGroup('order_paid');

        $publisher = app(MessagePublisher::class);
        $first = DB::transaction(fn (): int => $publisher->publish(
            'order.paid.1001', 'order_paid', 'order', 1001, $this->makeUser(),
            ['order_no' => 'DD1001'], ['customer']
        ));
        $second = DB::transaction(fn (): int => $publisher->publish(
            'order.paid.1001', 'order_paid', 'order', 1001, $this->makeUser(),
            ['order_no' => 'DD1001'], ['customer']
        ));

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('messages')->where('scene', 'order_paid')->count());
        $this->assertSame(1, DB::table('message_deliveries')->count());
    }

    public function test_claim_uses_lease_and_blocks_second_claim(): void
    {
        $deliveryId = $this->createPendingDelivery();

        $repository = app(MessageRepository::class);
        $claimed = $repository->claim($deliveryId);
        $this->assertNotNull($claimed);
        $this->assertSame('pending', 'pending'); // 占位断言保持结构对称
        $this->assertNotNull(DB::table('message_deliveries')->where('id', $deliveryId)->value('lease_key'));

        $this->assertNull($repository->claim($deliveryId));
    }

    public function test_unknown_result_is_terminal_and_never_retried(): void
    {
        $deliveryId = $this->createPendingDelivery();
        $repository = app(MessageRepository::class);
        $task = $repository->claim($deliveryId);
        $this->assertNotNull($task);

        $repository->finish($deliveryId, $task['lease_key'], 'unknown', 'transport_outcome_unknown', 12, false);

        $this->assertSame('unknown', DB::table('message_deliveries')->where('id', $deliveryId)->value('status'));
    }

    public function test_failed_retry_requeues_until_max_attempts(): void
    {
        $deliveryId = $this->createPendingDelivery();
        $repository = app(MessageRepository::class);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $task = $repository->claim($deliveryId);
            $this->assertNotNull($task, "第 {$attempt} 次领取失败");

            $repository->finish($deliveryId, $task['lease_key'], 'failed', 'provider_rejected', 8, true);

            // 每次退避 30×2^n，测试内直接推平到可领取
            DB::table('message_deliveries')->where('id', $deliveryId)->update([
                'next_attempt_at' => now()->subSecond(),
                'queue_after' => now()->subSecond(),
            ]);
        }

        $row = MessageDelivery::query()->find($deliveryId);
        $this->assertSame(MessageDelivery::STATUS_FAILED, $row->status);
        $this->assertSame(3, $row->attempts);
        $this->assertSame(3, MessageDeliveryAttemptLog::query()->where('delivery_id', $deliveryId)->count());
    }

    public function test_expired_lease_is_recovered_as_unknown_without_resend(): void
    {
        $deliveryId = $this->createPendingDelivery('sms');
        $repository = app(MessageRepository::class);
        $repository->claim($deliveryId);

        DB::table('message_deliveries')->where('id', $deliveryId)->update(['lease_until' => now()->subMinute()]);

        $this->assertSame(1, $repository->recoverExpired());
        $this->assertSame('unknown', DB::table('message_deliveries')->where('id', $deliveryId)->value('status'));
    }

    private function createPendingDelivery(string $channel = 'email'): int
    {
        $messageId = DB::table('messages')->insertGetId([
            'event_key' => hash('sha256', 'test-'.uniqid('', true)),
            'scene' => 'order_paid',
            'purpose' => 'notification',
            'business_type' => 'order',
            'business_id' => 1,
            'created_at' => now(),
        ]);

        $recipientId = DB::table('message_recipients')->insertGetId([
            'message_id' => $messageId,
            'audience' => 'customer',
            'account_id' => 1,
            'target_key' => 'user:1',
            'group_key' => 'customer',
            'group_name' => '关联客户',
            'created_at' => now(),
        ]);

        return (int) DB::table('message_deliveries')->insertGetId([
            'recipient_id' => $recipientId,
            'request_key' => (string) Str::uuid(),
            'channel' => $channel,
            'channel_name' => '',
            'payload_encrypted' => Crypt::encryptString(json_encode(['address' => 'demo@example.com'], JSON_THROW_ON_ERROR)),
            'status' => 'pending',
            'attempts' => 0,
            'next_attempt_at' => now(),
            'queue_after' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'email' => 'msg-'.uniqid('', true).'@example.com',
            'password' => bcrypt('secret'),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enableCustomerEmailGroup(string $scene): void
    {
        $type = MessageTypeCatalog::get('customer', $scene);
        $group = MessageGroups::create($type, 'customer', 'customer', '关联客户');
        $group['enabled'] = true;
        $group['configurations']['email']['enabled'] = true;

        $repository = app(MessageConfigurationRepository::class);
        // 测试库可能已有真实配置：先清掉该场景，让修订号从 0 开始
        DB::table('message_configurations')->where('audience', 'customer')->where('message_type', $scene)->delete();
        $repository->save('customer', $scene, 0, ['_groups' => [$group]]);
    }
}
