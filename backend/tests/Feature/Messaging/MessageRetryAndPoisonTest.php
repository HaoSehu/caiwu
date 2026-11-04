<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Jobs\DeliverMessageJob;
use App\Models\MessageDelivery;
use App\Services\Messaging\DispatchDelivery;
use App\Services\Messaging\MessageDispatcher;
use App\Services\Messaging\MessageRepository;
use App\Services\Messaging\MessageTransport;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 二次代码审查发现的缺陷回归。
 *
 * 1. 传输层的可重试失败必须真正标记 retry（否则整套重试是死代码）；
 * 2. 无法解密的投递必须判死，不能被反复重投；
 * 3. account_id=0 的收件人不得生成站内消息目标（否则连累邮件被判失败）；
 * 4. 租约时长必须显著大于任务 timeout。
 */
class MessageRetryAndPoisonTest extends TestCase
{
    use DatabaseTransactions;

    public function test_可重试失败带上_retry_标志(): void
    {
        $transport = app(MessageTransport::class);

        // 无可用邮件驱动 → channel_not_configured，属于可重试的瞬时故障
        $result = $transport->send('email', 'test-key-1', [
            'address' => 'nobody@example.test',
            'title' => '标题',
            'body' => '正文',
        ]);

        if ($result->status === 'failed' && $result->error === 'channel_not_configured') {
            $this->assertTrue($result->retry, '通道未配置属于瞬时故障，应标记可重试');
        }

        // 收件人非法属数据问题，明确不可重试
        $invalid = $transport->send('email', 'test-key-2', [
            'address' => 'not-an-email',
            'title' => '标题',
            'body' => '正文',
        ]);
        $this->assertSame('failed', $invalid->status);
        $this->assertFalse($invalid->retry, '收件人非法是数据问题，不应重试');
    }

    public function test_无法解密的投递被判为终态不再重投(): void
    {
        $deliveryId = $this->makePendingDelivery();

        // 破坏密文，模拟 APP_KEY 轮换
        DB::table('message_deliveries')->where('id', $deliveryId)->update([
            'payload_encrypted' => 'corrupted-ciphertext',
        ]);

        app(DispatchDelivery::class)->consume($deliveryId);

        $row = MessageDelivery::query()->find($deliveryId);
        $this->assertSame(MessageDelivery::STATUS_FAILED, $row->status, '解密失败必须判为终态');
        $this->assertSame('payload_undecryptable', $row->error_code);
    }

    public function test_无账号的收件人不会生成站内消息目标(): void
    {
        $dispatcher = app(MessageDispatcher::class);

        $method = new \ReflectionMethod($dispatcher, 'expandTargets');
        $method->setAccessible(true);

        $type = MessageTypeCatalog::get('customer', 'registration_success');
        $group = MessageGroups::create($type, 'customer', 'customer', '关联客户');
        $group['enabled'] = true;
        foreach (array_keys($group['configurations']) as $channel) {
            $group['configurations'][$channel]['enabled'] = true;
        }

        $targets = $method->invoke(
            $dispatcher,
            'customer',
            $type,
            ['_groups' => [$group]],
            [[
                'audience' => 'customer',
                'account_id' => 0,
                'target_key' => 'email:someone@example.test',
                'email' => 'someone@example.test',
                'mobile' => '',
                'name' => '某人',
            ]],
            ['site_name' => '站点', 'customer_name' => '某人', 'display_name' => '某人', 'email' => '', 'registered_at' => '']
        );

        $channels = array_column($targets, 'channel');
        $this->assertNotContains('message', $channels, 'account_id=0 时不应生成站内消息目标');
        $this->assertContains('email', $channels, '邮件目标仍应正常生成');
    }

    public function test_租约时长显著大于任务超时(): void
    {
        $lease = (new \ReflectionClassConstant(MessageRepository::class, 'LEASE_SECONDS'))->getValue();
        $timeout = (new DeliverMessageJob(1))->timeout;

        $this->assertGreaterThan(
            $timeout,
            $lease,
            "租约 {$lease}s 必须大于任务超时 {$timeout}s，否则发送中的任务会被误判为过期"
        );
    }

    private function makePendingDelivery(): int
    {
        $messageId = DB::table('messages')->insertGetId([
            'event_key' => hash('sha256', 'poison-'.uniqid('', true)),
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
            'channel' => 'email',
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
}
