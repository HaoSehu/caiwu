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
use App\Services\Messaging\MessageDispatcher;
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
     * 造一个可控的 MessageDispatcher 替身：按首个收件人邮箱决定成功或失败，
     * 并记录发出过的收件人。生产实现依赖场景配置与传输层，
     * 这里只验证通知服务的调度语义，不重复测发送链路。
     */
    private function makeDispatcher(array $failingEmails = []): MessageDispatcher
    {
        $dispatcher = new class extends MessageDispatcher
        {
            /** @var list<array{email: string, scene: string}> */
            public array $sent = [];

            /** @var list<string> */
            public array $failingEmails = [];

            public function __construct() {}

            public function sendNow(
                string $eventKey,
                string $scene,
                string $businessType,
                int $businessId,
                array $recipients,
                array $variables,
                string $audience = 'customer',
                string $templateCode = '',
            ): bool {
                $email = (string) ($recipients[0]['email'] ?? '');
                if (in_array($email, $this->failingEmails, true)) {
                    throw new \RuntimeException('邮件通知未启用');
                }

                $this->sent[] = ['email' => $email, 'scene' => $scene];

                return true;
            }
        };

        $dispatcher->failingEmails = $failingEmails;

        return $dispatcher;
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

    private function notifyPaid(MessageDispatcher $dispatcher, int $invoiceId): void
    {
        (new AdminOrderNotificationService($dispatcher))->notifyInvoicePaidNow($invoiceId);
    }

    /**
     * 只取本用例自己造的管理员邮箱（@example.test 域）。
     *
     * resolveRecipients() 按「有订单/账单权限的在用管理员」取全表收件人，
     * 本机测试库里可能已有真实管理员账号，它们同样会收到通知。
     * 断言收件人时必须只看本用例创建的那几个，否则用例结果取决于库里有多少历史账号。
     *
     * @return list<string>
     */
    private function sentTestEmails(MessageDispatcher $dispatcher): array
    {
        $emails = array_values(array_filter(
            array_column($dispatcher->sent, 'email'),
            static fn (string $email): bool => str_ends_with($email, '@example.test'),
        ));
        sort($emails);

        return $emails;
    }

    public function test_single_admin_failure_throws_aggregated_exception(): void
    {
        $admin = $this->makeAdmin('single-fail@example.test', '单发失败管理员');
        $dispatcher = $this->makeDispatcher(['single-fail@example.test']);
        [$invoice] = $this->makeInvoice();

        try {
            $this->notifyPaid($dispatcher, (int) $invoice->id);
            $this->fail('单管理员通知失败时不得静默返回，必须抛异常让队列重试');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('付款通知发送失败 1 位管理员', $exception->getMessage());
            $this->assertStringContainsString('admin#'.(int) $admin->id, $exception->getMessage());
            $this->assertStringContainsString('single-fail@example.test', $exception->getMessage());
            $this->assertStringContainsString('邮件通知未启用', $exception->getMessage());
        }

        // 只看本用例自己造的管理员：库里若已有真实管理员账号，它们同样会被通知到且不会失败。
        $this->assertSame([], $this->sentTestEmails($dispatcher));
    }

    public function test_partial_failure_still_attempts_everyone_then_throws_aggregate(): void
    {
        $first = $this->makeAdmin('partial-1@example.test', '部分失败管理员一');
        $second = $this->makeAdmin('partial-2@example.test', '部分失败管理员二');
        $third = $this->makeAdmin('partial-ok@example.test', '部分成功管理员');

        $dispatcher = $this->makeDispatcher([
            'partial-1@example.test',
            'partial-2@example.test',
        ]);
        [$invoice] = $this->makeInvoice();

        try {
            $this->notifyPaid($dispatcher, (int) $invoice->id);
            $this->fail('多管理员部分失败时必须抛聚合异常');
        } catch (\RuntimeException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('付款通知发送失败 2 位管理员', $message);
            $this->assertStringContainsString('admin#'.(int) $first->id, $message);
            $this->assertStringContainsString('admin#'.(int) $second->id, $message);
            $this->assertStringNotContainsString('admin#'.(int) $third->id, $message);
        }

        // 失败不得提前退出：第三位管理员仍要收到，否则重试永远不会补到他。
        $this->assertSame(['partial-ok@example.test'], $this->sentTestEmails($dispatcher));
        $this->assertSame(
            ['admin_order_paid'],
            array_values(array_unique(array_column($dispatcher->sent, 'scene'))),
        );
    }

    public function test_all_success_does_not_throw_and_records_delivery(): void
    {
        $this->makeAdmin('all-ok-1@example.test', '全部成功管理员一');
        $this->makeAdmin('all-ok-2@example.test', '全部成功管理员二');

        $dispatcher = $this->makeDispatcher();
        [$invoice] = $this->makeInvoice();

        $this->notifyPaid($dispatcher, (int) $invoice->id);

        $this->assertSame(['all-ok-1@example.test', 'all-ok-2@example.test'], $this->sentTestEmails($dispatcher));
    }

    public function test_retry_only_resends_to_admins_without_delivery_record(): void
    {
        $failing = $this->makeAdmin('retry-fail@example.test', '重试待补发管理员');
        $succeeded = $this->makeAdmin('retry-ok@example.test', '重试已发管理员');

        $dispatcher = $this->makeDispatcher(['retry-fail@example.test']);
        [$invoice] = $this->makeInvoice();

        try {
            $this->notifyPaid($dispatcher, (int) $invoice->id);
            $this->fail('首次通知部分失败时必须抛异常');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('retry-fail@example.test', $exception->getMessage());
        }

        $this->assertSame(['retry-ok@example.test'], $this->sentTestEmails($dispatcher));

        // 重试：失败者恢复，已成功者必须被 AutomationLog 幂等跳过。
        $dispatcher->failingEmails = [];
        $this->notifyPaid($dispatcher, (int) $invoice->id);

        $this->assertSame(
            ['retry-fail@example.test', 'retry-ok@example.test'],
            $this->sentTestEmails($dispatcher),
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

    /**
     * 全员邮箱非法时必须抛异常，不能静默返回。
     *
     * 静默返回会让队列任务显示成功，管理员永远收不到账单通知且无人知晓；
     * 抛异常让 Job 进 failed_jobs，至少能被告警和人工发现。
     */
    public function test_all_invalid_emails_throws_instead_of_silently_skipping(): void
    {
        $this->makeAdmin('test@123456', '唯一管理员且邮箱非法');

        $dispatcher = $this->makeDispatcher();
        [$invoice] = $this->makeInvoice();

        try {
            $this->notifyPaid($dispatcher, (int) $invoice->id);
            $this->fail('全员邮箱非法时必须抛异常，不得静默返回');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('无可用收件人', $exception->getMessage());
            $this->assertStringContainsString('test@123456', $exception->getMessage());
            $this->assertStringContainsString('邮箱不可用', $exception->getMessage());
        }

        $this->assertSame([], $dispatcher->sent, '不得向任何人发送');
    }

    /**
     * 有权限但没填邮箱的管理员：属于配置缺失，同样要抛（否则永远收不到）。
     */
    public function test_privileged_admin_without_email_also_throws(): void
    {
        $this->makeAdmin('', '有权限但没邮箱的管理员');

        $dispatcher = $this->makeDispatcher();
        [$invoice] = $this->makeInvoice();

        try {
            $this->notifyPaid($dispatcher, (int) $invoice->id);
            $this->fail('有权限管理员全员无可用邮箱时必须抛异常');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('无可用收件人', $exception->getMessage());
            $this->assertStringContainsString('(未填写邮箱)', $exception->getMessage());
        }

        $this->assertSame([], $dispatcher->sent);
    }

    /**
     * 没有有订单权限的管理员时不抛异常：这是正常配置状态，不该无限重试。
     */
    public function test_no_privileged_admin_does_not_throw(): void
    {
        // 只造一个无订单权限的管理员
        $role = Role::query()->create([
            'name' => 'no_order_perm_'.bin2hex(random_bytes(4)),
            'label' => '无订单权限角色',
            'permissions' => [],
        ]);
        AdminUser::query()->create([
            'username' => 'no_perm_'.bin2hex(random_bytes(4)),
            'password' => 'secret123',
            'nickname' => '无权限管理员',
            'email' => 'no-perm@example.test',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);

        $dispatcher = $this->makeDispatcher();
        [$invoice] = $this->makeInvoice();

        $this->notifyPaid($dispatcher, (int) $invoice->id);

        $this->assertSame([], $this->sentTestEmails($dispatcher));
    }

    public function test_invoice_created_branch_throws_aggregate_too(): void
    {
        $admin = $this->makeAdmin('created-fail@example.test', '新订单失败管理员');
        $dispatcher = $this->makeDispatcher(['created-fail@example.test']);
        [$invoice] = $this->makeInvoice(InvoiceStatus::UNPAID);

        try {
            (new AdminOrderNotificationService($dispatcher))->notifyInvoiceCreatedNow((int) $invoice->id);
            $this->fail('新订单通知失败时不得静默返回');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('新订单通知发送失败 1 位管理员', $exception->getMessage());
            $this->assertStringContainsString('admin#'.(int) $admin->id, $exception->getMessage());
        }
    }

    /**
     * 历史脏数据：管理员邮箱是 `test@123456` 这类非邮箱格式。
     *
     * 修复前它会被当作正常收件人送进传输层，得到 recipient_invalid 后
     * 计入 failures，整批抛出 RuntimeException，Job 反复重试到耗尽；
     * 而其他管理员早就收到了，只有重试白跑。
     * 修复后非法邮箱在收件人解析阶段就被排除，且必须留 warning 让人去改。
     */
    public function test_invalid_admin_email_is_skipped_and_does_not_fail_the_batch(): void
    {
        $this->makeAdmin('test@123456', '邮箱格式错误管理员');
        $this->makeAdmin('valid-beside-invalid@example.test', '正常管理员');

        $dispatcher = $this->makeDispatcher();
        [$invoice] = $this->makeInvoice();

        // 不得抛异常：非法邮箱不是发送失败，不该触发队列重试
        $this->notifyPaid($dispatcher, (int) $invoice->id);

        $this->assertSame(
            ['valid-beside-invalid@example.test'],
            $this->sentTestEmails($dispatcher),
            '非法邮箱必须被跳过，正常管理员必须收到'
        );
        $this->assertNotContains('test@123456', array_column($dispatcher->sent, 'email'));
    }

    /**
     * 非法邮箱也不得占用幂等记录：否则管理员修好邮箱后，同一张账单永远补发不到他。
     *
     * 必须同时存在一个合法收件人：全部非法时整批会抛异常（见
     * test_all_invalid_emails_throws_instead_of_silently_skipping），
     * 走不到「部分跳过 + 部分发送」这条路径。
     */
    public function test_invalid_admin_email_does_not_consume_idempotency_record(): void
    {
        $invalid = $this->makeAdmin('test@123456', '邮箱格式错误管理员');
        $this->makeAdmin('idempotent-ok@example.test', '正常管理员');

        $dispatcher = $this->makeDispatcher();
        [$invoice] = $this->makeInvoice();

        $this->notifyPaid($dispatcher, (int) $invoice->id);

        $this->assertSame(['idempotent-ok@example.test'], $this->sentTestEmails($dispatcher), '正常管理员必须收到');

        $this->assertFalse(
            AutomationLog::hasRecord(
                'admin-order-notification',
                'invoice_paid',
                'invoice',
                (int) $invoice->id,
                'admin:'.(int) $invalid->id,
            ),
            '被跳过的非法邮箱不得写入发送记录，修好邮箱后应能补发'
        );
    }

    /**
     * 新订单分支同样要跳过非法邮箱，不能只修付款分支。
     */
    public function test_invalid_admin_email_is_skipped_on_invoice_created_branch(): void
    {
        $this->makeAdmin('test@123456', '邮箱格式错误管理员');
        $this->makeAdmin('created-valid@example.test', '新订单正常管理员');

        $dispatcher = $this->makeDispatcher();
        [$invoice] = $this->makeInvoice(InvoiceStatus::UNPAID);

        (new AdminOrderNotificationService($dispatcher))->notifyInvoiceCreatedNow((int) $invoice->id);

        $this->assertSame(['created-valid@example.test'], $this->sentTestEmails($dispatcher));
        $this->assertNotContains('test@123456', array_column($dispatcher->sent, 'email'));
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
        $dispatcher = $this->makeDispatcher(['job-fail@example.test']);
        [$invoice] = $this->makeInvoice();

        $job = new SendPaidInvoiceAdminNotificationJob((int) $invoice->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('付款通知发送失败 1 位管理员');

        $job->handle(new AdminOrderNotificationService($dispatcher));
    }
}
