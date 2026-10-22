<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Jobs\SendPaidInvoiceAdminNotificationJob;
use App\Models\AdminUser;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\AdminOrderNotificationService;
use App\Services\Mail\MailDriverManager;
use App\Services\System\NotificationService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 管理员账单通知的失败语义：逐个收件人收集失败，循环结束后聚合成一条
 * RuntimeException 抛出，让队列任务看到异常并重试；同时保留 per-admin
 * 幂等（AutomationLog::hasRecord 按 admin:{id} 判重），重试只补发失败者。
 *
 * 历史缺陷：catch 里只写 Log::warning 就吞掉异常，Job 永远"成功"，
 * SMTP 抖动期间所有管理员都收不到信且没有任何重试。
 */
class AdminOrderNotificationFailureTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 造一个可控的 NotificationService 替身：按收件人邮箱决定成功或失败，
     * 并记录真实发出过的邮件。生产实现依赖邮件通道与模板表，
     * 这里只验证通知服务的调度语义，不重复测邮件驱动。
     */
    private function makeNotificationService(array $failingEmails = []): NotificationService
    {
        $service = new class(new MailDriverManager([])) extends NotificationService
        {
            /** @var list<array{email: string, template: string}> */
            public array $sent = [];

            /** @var list<string> */
            public array $failingEmails = [];

            public function sendTemplateEmail(
                string $to,
                string $templateCode,
                array $params = [],
                ?string $deliveryKey = null,
                int $attemptNo = 1,
            ): void {
                if (in_array($to, $this->failingEmails, true)) {
                    throw new \RuntimeException('邮件通知未启用');
                }

                $this->sent[] = ['email' => $to, 'template' => $templateCode];
            }
        };

        $service->failingEmails = $failingEmails;

        return $service;
    }

    private function makeAdmin(string $email, string $nickname): AdminUser
    {
        $token = bin2hex(random_bytes(4));

        $role = Role::query()->create([
            'name' => 'notify_role_'.$token,
            'label' => '账单通知测试角色',
            'permissions' => [AdminPermissions::ALL],
        ]);

        return AdminUser::query()->create([
            'username' => 'notify_admin_'.$token,
            'password' => 'secret123',
            'nickname' => $nickname,
            'email' => $email,
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }

    /**
     * @return array{0: Invoice, 1: Order, 2: User}
     */
    private function makeInvoice(int $status = InvoiceStatus::PAID): array
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'notify-user-'.$suffix.'@example.test',
            'password' => 'Temp@123456',
            'nickname' => 'notify-user-'.$suffix,
        ]);

        $order = Order::query()->create([
            'order_no' => 'NTNO'.$suffix,
            'user_id' => (int) $user->id,
            'type' => 'new',
            'status' => OrderStatus::PAID,
            'amount' => '88.00',
            'paid_amount' => '88.00',
            'paid_at' => now(),
            'config_snapshot' => ['product_full_path' => '云服务器/香港/标准型'],
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'NT'.$suffix,
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'new',
            'amount' => '88.00',
            'paid_amount' => '88.00',
            'billing_cycle' => 'monthly',
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::PAID ? now() : null,
            'config_snapshot' => ['product_full_path' => '云服务器/香港/标准型'],
        ]);

        return [$invoice, $order, $user];
    }

    private function notifyPaid(NotificationService $notificationService, int $invoiceId): void
    {
        (new AdminOrderNotificationService($notificationService))->notifyInvoicePaidNow($invoiceId);
    }

    public function test_single_admin_failure_throws_aggregated_exception(): void
    {
        $admin = $this->makeAdmin('single-fail@example.test', '单发失败管理员');
        $notificationService = $this->makeNotificationService(['single-fail@example.test']);
        [$invoice] = $this->makeInvoice();

        try {
            $this->notifyPaid($notificationService, (int) $invoice->id);
            $this->fail('单管理员通知失败时不得静默返回，必须抛异常让队列重试');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('付款通知发送失败 1 位管理员', $exception->getMessage());
            $this->assertStringContainsString('admin#'.(int) $admin->id, $exception->getMessage());
            $this->assertStringContainsString('single-fail@example.test', $exception->getMessage());
            $this->assertStringContainsString('邮件通知未启用', $exception->getMessage());
        }

        $this->assertSame([], $notificationService->sent);
    }

    public function test_partial_failure_still_attempts_everyone_then_throws_aggregate(): void
    {
        $first = $this->makeAdmin('partial-1@example.test', '部分失败管理员一');
        $second = $this->makeAdmin('partial-2@example.test', '部分失败管理员二');
        $third = $this->makeAdmin('partial-ok@example.test', '部分成功管理员');

        $notificationService = $this->makeNotificationService([
            'partial-1@example.test',
            'partial-2@example.test',
        ]);
        [$invoice] = $this->makeInvoice();

        try {
            $this->notifyPaid($notificationService, (int) $invoice->id);
            $this->fail('多管理员部分失败时必须抛聚合异常');
        } catch (\RuntimeException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('付款通知发送失败 2 位管理员', $message);
            $this->assertStringContainsString('admin#'.(int) $first->id, $message);
            $this->assertStringContainsString('admin#'.(int) $second->id, $message);
            $this->assertStringNotContainsString('admin#'.(int) $third->id, $message);
        }

        // 失败不得提前退出：第三位管理员仍要收到，否则重试永远不会补到他。
        $this->assertSame(['partial-ok@example.test'], array_column($notificationService->sent, 'email'));
        $this->assertSame(
            [NotificationService::TEMPLATE_ADMIN_ORDER_PAID],
            array_values(array_unique(array_column($notificationService->sent, 'template'))),
        );
    }

    public function test_all_success_does_not_throw_and_records_delivery(): void
    {
        $this->makeAdmin('all-ok-1@example.test', '全部成功管理员一');
        $this->makeAdmin('all-ok-2@example.test', '全部成功管理员二');

        $notificationService = $this->makeNotificationService();
        [$invoice] = $this->makeInvoice();

        $this->notifyPaid($notificationService, (int) $invoice->id);

        $emails = array_column($notificationService->sent, 'email');
        sort($emails);
        $this->assertSame(['all-ok-1@example.test', 'all-ok-2@example.test'], $emails);
    }

    public function test_retry_only_resends_to_admins_without_delivery_record(): void
    {
        $failing = $this->makeAdmin('retry-fail@example.test', '重试待补发管理员');
        $succeeded = $this->makeAdmin('retry-ok@example.test', '重试已发管理员');

        $notificationService = $this->makeNotificationService(['retry-fail@example.test']);
        [$invoice] = $this->makeInvoice();

        try {
            $this->notifyPaid($notificationService, (int) $invoice->id);
            $this->fail('首次通知部分失败时必须抛异常');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('retry-fail@example.test', $exception->getMessage());
        }

        $this->assertSame(['retry-ok@example.test'], array_column($notificationService->sent, 'email'));

        // 重试：失败者恢复，已成功者必须被 AutomationLog 幂等跳过。
        $notificationService->failingEmails = [];
        $this->notifyPaid($notificationService, (int) $invoice->id);

        $this->assertSame(
            ['retry-ok@example.test', 'retry-fail@example.test'],
            array_column($notificationService->sent, 'email'),
        );
        $this->assertTrue(AutomationLog::hasRecord(
            'admin-order-notification',
            'invoice_paid',
            'invoice',
            (int) $invoice->id,
            'admin:'.(int) $failing->id,
        ));
        $this->assertTrue(AutomationLog::hasRecord(
            'admin-order-notification',
            'invoice_paid',
            'invoice',
            (int) $invoice->id,
            'admin:'.(int) $succeeded->id,
        ));
    }

    public function test_invoice_created_branch_throws_aggregate_too(): void
    {
        $admin = $this->makeAdmin('created-fail@example.test', '新订单失败管理员');
        $notificationService = $this->makeNotificationService(['created-fail@example.test']);
        [$invoice] = $this->makeInvoice(InvoiceStatus::UNPAID);

        try {
            (new AdminOrderNotificationService($notificationService))->notifyInvoiceCreatedNow((int) $invoice->id);
            $this->fail('新订单通知失败时不得静默返回');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('新订单通知发送失败 1 位管理员', $exception->getMessage());
            $this->assertStringContainsString('admin#'.(int) $admin->id, $exception->getMessage());
        }
    }

    /**
     * 计划要求的重试链：Job 必须配置 3 次尝试与 [60,300,600] 退避，
     * 并把服务层异常向上冒泡，否则框架不会重试。
     */
    public function test_job_retry_chain_is_wired_and_propagates_failure(): void
    {
        $job = new SendPaidInvoiceAdminNotificationJob(12345);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 600], $job->backoff);
        $this->assertSame('notification', $job->queue);

        $this->makeAdmin('job-fail@example.test', '队列失败管理员');
        $notificationService = $this->makeNotificationService(['job-fail@example.test']);
        [$invoice] = $this->makeInvoice();

        $job = new SendPaidInvoiceAdminNotificationJob((int) $invoice->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('付款通知发送失败 1 位管理员');

        $job->handle(new AdminOrderNotificationService($notificationService));
    }
}
