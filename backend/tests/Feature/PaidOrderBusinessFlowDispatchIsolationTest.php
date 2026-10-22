<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Jobs\ProcessPaidOrderFulfillmentJob;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\InvoicePaidOrchestrator;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use Illuminate\Bus\UniqueLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * 支付后业务流异常隔离：履约与返佣互不依赖，任何一方失败都不得吞掉另一方。
 *
 * 背景（2026-10-13 审查 P1-1）：afterResponse 重构后履约先于返佣执行且不吞异常，
 * Laravel Application::terminate() 对回调循环无 try/catch——履约抛异常会跳过其后
 * 注册的返佣回调。修复后：控制台路径返佣先派发（失败吞异常记日志），履约异常
 * 保持向调用方上抛；HTTP 路径两段业务流各自注册独立 terminate 回调并吞异常。
 *
 * 注：phpunit 运行于 console 上下文（runningInConsole()=true），本测试以
 * queue.default=sync（队列不可用回退路径）真执行两段业务流，覆盖控制台隔离语义；
 * HTTP terminate 路径的隔离由 terminateSafely 的 try/catch 结构保证，不在此模拟。
 */
class PaidOrderBusinessFlowDispatchIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_console_fulfillment_failure_propagates_and_referral_still_runs(): void
    {
        config(['queue.default' => 'sync']);

        [$order, $invoice] = $this->makePaidOrderStack();

        $this->mock(InvoicePaidOrchestrator::class, function ($mock) use ($order): void {
            // 返佣先派发：履约抛异常也不影响其执行
            $mock->shouldReceive('processPaidOrderReferralRewardById')
                ->once()
                ->with((int) $order->id, 'trace-isolation-1')
                ->andReturnNull();
            $mock->shouldReceive('processPaidOrderFulfillmentById')
                ->once()
                ->with((int) $order->id)
                ->andThrow(new BusinessException('支付后履约未完成，等待后续重试'));
        });

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('支付后履约未完成，等待后续重试');

        app(PaidOrderBusinessFlowDispatcher::class)->dispatchPaidInvoice($invoice, 'trace-isolation-1');
    }

    public function test_console_referral_sync_failure_does_not_block_fulfillment(): void
    {
        // 队列不可用 → 返佣走进程内同步回退；返佣失败必须吞掉，不得阻断履约
        config(['queue.default' => 'sync']);

        [$order, $invoice] = $this->makePaidOrderStack();

        $this->mock(InvoicePaidOrchestrator::class, function ($mock): void {
            $mock->shouldReceive('processPaidOrderReferralRewardById')
                ->once()
                ->andThrow(new \RuntimeException('返佣入账失败模拟'));
            $mock->shouldReceive('processPaidOrderFulfillmentById')
                ->once()
                ->andReturnNull();
        });

        app(PaidOrderBusinessFlowDispatcher::class)->dispatchPaidInvoice($invoice, 'trace-isolation-2');

        // 无异常抛出即代表返佣失败被隔离、履约正常执行（mock once 已验证调用）
        $this->assertTrue(true);
    }

    public function test_console_normal_path_runs_both_flows_in_order(): void
    {
        config(['queue.default' => 'sync']);

        [$order, $invoice] = $this->makePaidOrderStack();

        $calls = [];
        $this->mock(InvoicePaidOrchestrator::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('processPaidOrderReferralRewardById')
                ->once()
                ->andReturnUsing(function () use (&$calls): void {
                    $calls[] = 'referral';
                });
            $mock->shouldReceive('processPaidOrderFulfillmentById')
                ->once()
                ->andReturnUsing(function () use (&$calls): void {
                    $calls[] = 'fulfillment';
                });
        });

        app(PaidOrderBusinessFlowDispatcher::class)->dispatchPaidInvoice($invoice, 'trace-isolation-3');

        // 返佣先执行、履约后执行：履约（同步长链路）失败时的回归保护口径
        $this->assertSame(['referral', 'fulfillment'], $calls);
    }

    public function test_http_terminating_callbacks_are_isolated_and_swallow_failures(): void
    {
        // HTTP 路径：两段业务流各自注册 terminate 回调并各自 try/catch；
        // 直接驱动注册的回调，验证前一个抛异常不阻断后一个。
        $dispatcher = app(PaidOrderBusinessFlowDispatcher::class);
        $app = app();

        $property = new \ReflectionProperty($app, 'terminatingCallbacks');
        $property->setAccessible(true);
        $before = count((array) $property->getValue($app));

        $method = new \ReflectionMethod($dispatcher, 'terminateSafely');
        $method->setAccessible(true);

        $executed = [];
        $method->invoke($dispatcher, '履约', 1, 'trace-terminate', function () use (&$executed): void {
            $executed[] = 'fulfillment';
            throw new \RuntimeException('履约失败模拟');
        });
        $method->invoke($dispatcher, '返佣', 1, 'trace-terminate', function () use (&$executed): void {
            $executed[] = 'referral';
        });

        $registered = array_slice((array) $property->getValue($app), $before);
        $this->assertCount(2, $registered, '履约与返佣必须各自注册独立 terminate 回调');

        foreach ($registered as $callback) {
            $callback();
        }

        $this->assertSame(['fulfillment', 'referral'], $executed, '前一个回调抛异常不得阻断后续业务流');
    }

    public function test_http_fulfillment_failure_is_retried_through_queue(): void
    {
        // HTTP 路径：响应后同步履约失败，不能只留一行日志。用户已经拿到「支付成功」，
        // 这单必须进队列继续重试，否则就是付了钱永远不开通。
        config(['queue.default' => 'database']);

        $dispatcher = app(PaidOrderBusinessFlowDispatcher::class);
        $app = app();

        $property = new \ReflectionProperty($app, 'terminatingCallbacks');
        $property->setAccessible(true);
        $before = count((array) $property->getValue($app));

        $method = new \ReflectionMethod($dispatcher, 'terminateSafely');
        $method->setAccessible(true);
        $method->invoke($dispatcher, '履约', 9527, 'trace-queue-retry', function (): void {
            throw new \RuntimeException('履约同步执行失败模拟');
        }, true);

        $registered = array_slice((array) $property->getValue($app), $before);
        $this->assertCount(1, $registered);

        $registered[0]();

        $job = DB::table('jobs')->where('payload', 'like', '%ProcessPaidOrderFulfillmentJob%')->first();
        $this->assertNotNull($job, '同步履约失败后必须把履约任务派发到队列');
        $this->assertSame('provision', (string) $job->queue);
    }

    public function test_http_fulfillment_failure_with_queue_unavailable_only_logs(): void
    {
        // 队列不可用（sync）时没有可靠异步通道，不能硬派发，也不能让 terminate 回调抛异常。
        config(['queue.default' => 'sync']);

        $dispatcher = app(PaidOrderBusinessFlowDispatcher::class);
        $app = app();

        $property = new \ReflectionProperty($app, 'terminatingCallbacks');
        $property->setAccessible(true);
        $before = count((array) $property->getValue($app));

        $method = new \ReflectionMethod($dispatcher, 'terminateSafely');
        $method->setAccessible(true);
        $method->invoke($dispatcher, '履约', 9528, 'trace-no-queue', function (): void {
            throw new \RuntimeException('履约同步执行失败模拟');
        }, true);

        $registered = array_slice((array) $property->getValue($app), $before);
        $registered[0]();

        $this->assertSame(0, DB::table('jobs')->where('payload', 'like', '%ProcessPaidOrderFulfillmentJob%')->count());
    }

    public function test_http_fulfillment_failure_skips_dispatch_when_unique_lock_is_held(): void
    {
        // 队列里已有同订单任务时，ShouldBeUnique 会在 PendingDispatch 析构里静默丢弃重复派发，
        // 不抛异常。兜底路径必须自己探测到这一点，否则日志会写「已转入队列重试」的假证据。
        config(['queue.default' => 'database']);

        $orderId = 9529;
        $lock = Cache::lock(UniqueLock::getKey(new ProcessPaidOrderFulfillmentJob($orderId)), 1500);
        $this->assertTrue($lock->get(), '前置条件：必须占住同订单的唯一锁');

        try {
            Log::spy();

            $dispatcher = app(PaidOrderBusinessFlowDispatcher::class);
            $app = app();

            $property = new \ReflectionProperty($app, 'terminatingCallbacks');
            $property->setAccessible(true);
            $before = count((array) $property->getValue($app));

            $method = new \ReflectionMethod($dispatcher, 'terminateSafely');
            $method->setAccessible(true);
            $method->invoke($dispatcher, '履约', $orderId, 'trace-unique-lock', function (): void {
                throw new \RuntimeException('履约同步执行失败模拟');
            }, true);

            $registered = array_slice((array) $property->getValue($app), $before);
            $registered[0]();

            $this->assertSame(
                0,
                DB::table('jobs')->where('payload', 'like', '%ProcessPaidOrderFulfillmentJob%')->count(),
                '唯一锁被占时不得重复派发注定会被丢弃的任务',
            );
            Log::shouldHaveReceived('warning')->withArgs(
                static fn (string $message, array $context = []): bool => str_contains($message, '同订单履约任务已在队列中'),
            );
            // 全程只允许出现这一条 warning：若还有「已转入队列重试」就是假证据
            Log::shouldHaveReceived('warning')->once();
        } finally {
            $lock->release();
        }
    }

    /**
     * 构造已支付的 new 订单+账单堆栈（触发返佣派发条件：order.type=new）。
     *
     * @return array{0: Order, 1: Invoice}
     */
    private function makePaidOrderStack(): array
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => "dispatch-isolation-{$suffix}@example.test",
            'password' => 'Temp@123456',
            'nickname' => 'dispatch-isolation-'.$suffix,
        ]);

        $order = Order::query()->create([
            'order_no' => 'DISP'.$suffix,
            'user_id' => (int) $user->id,
            'type' => 'new',
            'status' => 1,
            'amount' => '66.00',
            'paid_amount' => '66.00',
            'paid_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'DISPI'.$suffix,
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'new',
            'amount' => '66.00',
            'paid_amount' => '66.00',
            'status' => 2,
            'paid_at' => now(),
        ]);

        return [$order->refresh(), $invoice->refresh()];
    }
}
