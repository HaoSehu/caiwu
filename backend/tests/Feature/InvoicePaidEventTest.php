<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Events\InvoicePaid;
use App\Listeners\SendInvoicePaidClientNotification;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\System\NotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * 支付成功领域事件（B-07）：事件派发 → 监听器 → 客户付款成功通知（模板 100016）。
 *
 * 覆盖三条约束：
 * 1. 编排器在支付收口处派发 InvoicePaid，监听器由目录自动发现挂载（不是死事件）；
 * 2. 同一账单重入/对账重放只发一次（AutomationLog 幂等）；
 * 3. 用户无邮箱时不发信、也不占幂等键，补绑邮箱后还能补发。
 */
class InvoicePaidEventTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return array{0: Invoice, 1: Order, 2: User}
     */
    private function makePaidInvoice(?string $email = null): array
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => $email ?? ('invoice-paid-'.$suffix.'@example.test'),
            'password' => 'Temp@123456',
            'nickname' => 'invoice-paid-'.$suffix,
        ]);

        $order = Order::query()->create([
            'order_no' => 'IPEV'.$suffix,
            'user_id' => (int) $user->id,
            'type' => 'new',
            'status' => OrderStatus::PAID,
            'amount' => '66.00',
            'paid_amount' => '66.00',
            'paid_at' => now(),
            'config_snapshot' => ['product_full_path' => '云服务器/香港/标准型'],
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'IPEV'.$suffix,
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'new',
            'amount' => '66.00',
            'paid_amount' => '66.00',
            'billing_cycle' => 'monthly',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'config_snapshot' => ['product_full_path' => '云服务器/香港/标准型'],
        ]);

        return [$invoice, $order, $user];
    }

    public function test_listener_is_auto_discovered_for_invoice_paid_event(): void
    {
        $registered = Event::getRawListeners()[InvoicePaid::class] ?? [];

        $this->assertNotEmpty($registered, 'InvoicePaid 没有挂载任何监听器，事件派发了也没人听');

        // 目录自动发现的监听器以 "类名@handle" 字符串登记
        $handlers = array_map(
            static fn ($listener): string => is_string($listener) ? $listener : (string) $listener,
            $registered
        );

        $this->assertContains(
            SendInvoicePaidClientNotification::class.'@handle',
            $handlers,
            'SendInvoicePaidClientNotification 未被事件发现机制挂到 InvoicePaid 上'
        );
    }

    public function test_listener_dispatches_payment_success_email_once_per_invoice(): void
    {
        [$invoice] = $this->makePaidInvoice();

        $sent = [];
        $this->mock(NotificationService::class, function ($mock) use (&$sent): void {
            $mock->shouldReceive('sendTemplateEmail')
                ->andReturnUsing(function (string $to, string $template, array $params = []) use (&$sent): void {
                    $sent[] = compact('to', 'template', 'params');
                });
        });

        $listener = app(SendInvoicePaidClientNotification::class);
        $listener->handle(new InvoicePaid($invoice, 'trace-e2e'));

        // 重入一次：同账单同用户应被幂等挡下
        $listener->handle(new InvoicePaid($invoice, 'trace-e2e'));

        $this->assertCount(1, $sent, '同一账单重复进入应只发一次客户付款成功通知');
        $this->assertSame(NotificationService::TEMPLATE_PAYMENT_SUCCESS, $sent[0]['template']);
        $this->assertSame((string) $invoice->user->email, $sent[0]['to']);

        $this->assertDatabaseHas('automation_logs', [
            'task_key' => 'client-payment-notify',
            'action' => 'invoice_paid',
            'object_type' => 'invoice',
            'object_id' => (int) $invoice->id,
        ]);
        $this->assertNotNull(
            AutomationLog::query()
                ->where('task_key', 'client-payment-notify')
                ->where('object_id', (int) $invoice->id)
                ->value('executed_at'),
            '发送成功后应写入 executed_at，避免对账重放重复发信'
        );
    }

    public function test_user_without_email_is_skipped_and_can_be_notified_after_binding(): void
    {
        [$invoice, , $user] = $this->makePaidInvoice('placeholder@example.test');
        $user->forceFill(['email' => ''])->save();
        $invoice->unsetRelation('user');

        $sent = 0;
        $this->mock(NotificationService::class, function ($mock) use (&$sent): void {
            $mock->shouldReceive('sendTemplateEmail')->andReturnUsing(function () use (&$sent): void {
                $sent++;
            });
        });

        $listener = app(SendInvoicePaidClientNotification::class);
        $listener->handle(new InvoicePaid($invoice, 'trace-no-email'));

        $this->assertSame(0, $sent, '无邮箱账号不应发信');
        $this->assertFalse(
            AutomationLog::query()
                ->where('task_key', 'client-payment-notify')
                ->where('object_id', (int) $invoice->id)
                ->exists(),
            '无邮箱时不应占用幂等键，否则补绑邮箱后永远补发不了'
        );

        $user->forceFill(['email' => 'rebind-'.$user->id.'@example.test'])->save();
        $invoice->unsetRelation('user');

        $listener->handle(new InvoicePaid($invoice->fresh(), 'trace-rebind'));

        $this->assertSame(1, $sent, '补绑邮箱后应能补发一次');
    }

    public function test_listener_swallows_failure_and_releases_idempotency_key(): void
    {
        [$invoice] = $this->makePaidInvoice();

        $this->mock(NotificationService::class, function ($mock): void {
            $mock->shouldReceive('sendTemplateEmail')->andThrow(new \RuntimeException('邮件通道不可用'));
        });

        $listener = app(SendInvoicePaidClientNotification::class);

        // 不抛异常：支付编排是同步派发事件，监听器抛出会打断调用方后续的日志与清理
        $listener->handle(new InvoicePaid($invoice, 'trace-mail-down'));

        $this->assertFalse(
            AutomationLog::query()
                ->where('task_key', 'client-payment-notify')
                ->where('object_id', (int) $invoice->id)
                ->exists(),
            '发送失败应释放幂等键，让对账重放能重试'
        );
    }
}
