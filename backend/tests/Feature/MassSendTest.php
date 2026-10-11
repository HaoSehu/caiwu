<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Jobs\MassSendChunkJob;
use App\Jobs\MassSendDispatchJob;
use App\Models\AdminUser;
use App\Models\MassSend;
use App\Models\MassSendTarget;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\MassSend\MassSendService;
use App\Services\Notification\UserNotificationService;
use App\Services\System\NotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * M9 批量群发契约测试。
 *
 * 覆盖：创建校验（模板与自定义内容二选一、模板通道匹配、filter 白名单）、
 * preview 试算不落库、marketing_alert 剔除（targets 生成 + chunk 双保险）、
 * chunk 发送成功/失败与计数进度、取消。
 * 队列用 Queue::fake 阻断真实发送；chunk 直接同步调用验证状态机。
 */
class MassSendTest extends TestCase
{
    use DatabaseTransactions;

    private MassSendService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MassSendService::class);
        Queue::fake();
    }

    // ---------------------------------------------------------------- 创建校验

    public function test_create_requires_template_or_custom_content(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('subject 与 content');

        $this->service->createDraft([
            'name' => '空内容任务'.uniqid(),
            'channel' => MassSend::CHANNEL_EMAIL,
            'filter_json' => ['status' => 1],
        ], $this->makeAdminId());
    }

    public function test_create_rejects_template_channel_mismatch(): void
    {
        $template = NotificationTemplate::query()->create([
            'channel' => 'sms',
            'code' => 'ms-'.uniqid(),
            'name' => '短信模板',
            'content' => 'hello {{nickname}}',
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('通道不匹配');

        $this->service->createDraft([
            'name' => '通道错配'.uniqid(),
            'channel' => MassSend::CHANNEL_EMAIL,
            'template_id' => $template->id,
            'filter_json' => [],
        ], $this->makeAdminId());
    }

    public function test_create_rejects_unknown_filter_key(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->createDraft([
            'name' => '非法条件'.uniqid(),
            'channel' => MassSend::CHANNEL_INBOX,
            'subject' => '标题',
            'content' => '正文',
            'filter_json' => ['hacker' => true],
        ], $this->makeAdminId());
    }

    public function test_create_accepts_email_template_for_email_channel(): void
    {
        $template = NotificationTemplate::query()->create([
            'channel' => 'email',
            'code' => 'ms-'.uniqid(),
            'name' => '邮件模板',
            'subject' => 'Dear {{nickname}}',
            'content' => 'hello {{email}}',
        ]);

        $massSend = $this->service->createDraft([
            'name' => '模板任务'.uniqid(),
            'channel' => MassSend::CHANNEL_EMAIL,
            'template_id' => $template->id,
            'filter_json' => ['status' => 1],
        ], $this->makeAdminId());

        $this->assertSame(MassSend::STATUS_DRAFT, (int) $massSend->status);
        $this->assertSame($template->id, (int) $massSend->template_id);
    }

    // ---------------------------------------------------------------- 试算

    public function test_preview_counts_hits_without_persisting(): void
    {
        $this->makeUser(['status' => 1]);

        $result = $this->service->preview(['status' => 1]);

        $this->assertGreaterThanOrEqual(1, $result['total_count']);
        // 试算不落库：无任何任务被创建。
        $this->assertSame(0, MassSend::query()->count());
    }

    // ---------------------------------------------------------------- 启动与营销剔除

    public function test_start_writes_targets_and_total_count(): void
    {
        $optedIn = $this->makeUser(['marketing_alert' => 1]);
        $optedOut = $this->makeUser(['marketing_alert' => 0]);

        $massSend = $this->service->createDraft([
            'name' => '营销群发'.uniqid(),
            'channel' => MassSend::CHANNEL_INBOX,
            'is_marketing' => 1,
            'subject' => '活动通知',
            'content' => 'Hi {{nickname}}',
            'filter_json' => [],
        ], $this->makeAdminId());

        // 营销类：targets 生成剔除 marketing_alert=0。
        $started = $this->service->start($massSend);

        $this->assertSame(MassSend::STATUS_PENDING, (int) $started->status);
        $this->assertSame(1, (int) $started->total_count);

        $targetUserIds = MassSendTarget::query()
            ->where('mass_send_id', $massSend->id)
            ->pluck('user_id')
            ->all();

        $this->assertContains($optedIn->id, $targetUserIds);
        $this->assertNotContains($optedOut->id, $targetUserIds);

        Queue::assertPushed(MassSendDispatchJob::class);
    }

    public function test_start_rejects_zero_hit_segment(): void
    {
        $massSend = $this->service->createDraft([
            'name' => '零命中'.uniqid(),
            'channel' => MassSend::CHANNEL_INBOX,
            'subject' => '标题',
            'content' => '正文',
            'filter_json' => ['status' => 99],
        ], $this->makeAdminId());

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('命中 0 人');

        $this->service->start($massSend);
    }

    // ---------------------------------------------------------------- chunk 发送与进度

    public function test_chunk_marks_success_and_finishes_task(): void
    {
        $user = $this->makeUser(['marketing_alert' => 1]);

        $massSend = MassSend::create([
            'name' => '发送中任务'.uniqid(),
            'channel' => MassSend::CHANNEL_INBOX,
            'is_marketing' => 1,
            'subject' => '标题 {{nickname}}',
            'content' => '正文',
            'filter_json' => [],
            'total_count' => 1,
            'status' => MassSend::STATUS_SENDING,
            'started_at' => now(),
            'created_by' => $this->makeAdminId(),
        ]);

        $target = MassSendTarget::create([
            'mass_send_id' => $massSend->id,
            'user_id' => $user->id,
            'status' => MassSendTarget::STATUS_PENDING,
        ]);

        // 站内信通道直写 user_notifications，不依赖邮件驱动；同步执行 chunk。
        (new MassSendChunkJob((int) $massSend->id, [(int) $target->id]))
            ->handle(app(NotificationService::class), app(UserNotificationService::class));

        $target->refresh();
        $massSend->refresh();

        $this->assertSame(MassSendTarget::STATUS_SENT, (int) $target->status);
        $this->assertNotNull($target->sent_at);
        $this->assertSame(1, (int) $massSend->sent_count);
        $this->assertSame(0, (int) $massSend->failed_count);
        $this->assertSame(MassSend::STATUS_FINISHED, (int) $massSend->status);
        $this->assertNotNull($massSend->finished_at);
    }

    public function test_chunk_double_insurance_blocks_marketing_opt_out(): void
    {
        // targets 生成侧漏网的 marketing_alert=0 用户，chunk 发送侧必须拦截。
        $optedOut = $this->makeUser(['marketing_alert' => 0]);

        $massSend = MassSend::create([
            'name' => '双保险'.uniqid(),
            'channel' => MassSend::CHANNEL_INBOX,
            'is_marketing' => 1,
            'subject' => '标题',
            'content' => '正文',
            'filter_json' => [],
            'total_count' => 1,
            'status' => MassSend::STATUS_SENDING,
            'created_by' => $this->makeAdminId(),
        ]);

        $target = MassSendTarget::create([
            'mass_send_id' => $massSend->id,
            'user_id' => $optedOut->id,
            'status' => MassSendTarget::STATUS_PENDING,
        ]);

        (new MassSendChunkJob((int) $massSend->id, [(int) $target->id]))
            ->handle(app(NotificationService::class), app(UserNotificationService::class));

        $target->refresh();
        $massSend->refresh();

        $this->assertSame(MassSendTarget::STATUS_FAILED, (int) $target->status);
        $this->assertStringContainsString('marketing_alert=0', (string) $target->error);
        $this->assertSame(1, (int) $massSend->failed_count);
        $this->assertSame(MassSend::STATUS_FINISHED, (int) $massSend->status);
    }

    public function test_chunk_failure_does_not_block_sibling_targets(): void
    {
        $good = $this->makeUser(['marketing_alert' => 1]);
        // 邮箱为空 + 邮件通道 → 该用户发送失败，不阻塞同片其他用户。
        $broken = $this->makeUser(['marketing_alert' => 1]);
        User::query()->whereKey($broken->id)->update(['email' => '']);
        // 查询构造器不回写内存实例，必须 refresh 后再快照邮箱，否则失败注入不生效。
        $broken->refresh();

        $massSend = MassSend::create([
            'name' => '部分失败'.uniqid(),
            'channel' => MassSend::CHANNEL_EMAIL,
            'is_marketing' => 0,
            'subject' => '标题',
            'content' => '正文',
            'filter_json' => [],
            'total_count' => 2,
            'status' => MassSend::STATUS_SENDING,
            'created_by' => $this->makeAdminId(),
        ]);

        $targets = collect([$good, $broken])->map(fn (User $user) => MassSendTarget::create([
            'mass_send_id' => $massSend->id,
            'user_id' => $user->id,
            'email' => trim((string) $user->email) ?: null,
            'status' => MassSendTarget::STATUS_PENDING,
        ]));

        // 邮件发送基础设施（notification.email_enabled 开关/驱动）不属于本用例关注点，
        // 用 mock 让合法邮箱发送成功；broken 用户在进入发送前被「邮箱为空」拦截，
        // 以此验证分片内单用户失败不阻塞兄弟目标。
        $notificationService = $this->createMock(NotificationService::class);

        (new MassSendChunkJob((int) $massSend->id, $targets->pluck('id')->map(fn ($id) => (int) $id)->all()))
            ->handle($notificationService, app(UserNotificationService::class));

        $massSend->refresh();

        $this->assertSame(1, (int) $massSend->sent_count);
        $this->assertSame(1, (int) $massSend->failed_count);
        $this->assertSame(MassSend::STATUS_FINISHED, (int) $massSend->status);
    }

    // ---------------------------------------------------------------- 取消

    public function test_cancel_sets_status_and_finished_at(): void
    {
        $massSend = $this->service->createDraft([
            'name' => '取消我'.uniqid(),
            'channel' => MassSend::CHANNEL_INBOX,
            'subject' => '标题',
            'content' => '正文',
            'filter_json' => [],
        ], $this->makeAdminId());

        $cancelled = $this->service->cancel($massSend);

        $this->assertSame(MassSend::STATUS_CANCELLED, (int) $cancelled->status);
        $this->assertNotNull($cancelled->finished_at);
    }

    public function test_finished_task_cannot_be_cancelled(): void
    {
        $massSend = MassSend::create([
            'name' => '已完成'.uniqid(),
            'channel' => MassSend::CHANNEL_INBOX,
            'is_marketing' => 0,
            'subject' => '标题',
            'content' => '正文',
            'filter_json' => [],
            'status' => MassSend::STATUS_FINISHED,
            'created_by' => $this->makeAdminId(),
        ]);

        $this->expectException(BusinessException::class);

        $this->service->cancel($massSend);
    }

    public function test_dispatch_job_claims_only_due_pending_sends(): void
    {
        $user = $this->makeUser(['marketing_alert' => 1]);

        // scheduled_at 在未来：不应被抢占。
        $future = MassSend::create([
            'name' => '定时未到'.uniqid(),
            'channel' => MassSend::CHANNEL_INBOX,
            'is_marketing' => 0,
            'subject' => '标题',
            'content' => '正文',
            'filter_json' => [],
            'total_count' => 1,
            'status' => MassSend::STATUS_PENDING,
            'scheduled_at' => now()->addHour(),
            'created_by' => $this->makeAdminId(),
        ]);
        MassSendTarget::create(['mass_send_id' => $future->id, 'user_id' => $user->id, 'status' => 0]);

        (new MassSendDispatchJob((int) $future->id))->handle();

        $future->refresh();
        $this->assertSame(MassSend::STATUS_PENDING, (int) $future->status);
    }

    private function makeUser(array $overrides): User
    {
        return User::query()->create($overrides + [
            'email' => 'mass-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'mass-tester',
            'status' => 1,
            'total_sales_amount' => 0,
        ]);
    }

    /**
     * mass_sends.created_by FK → admin_users：idc_test 无 id=1，需先造父行管理员。
     */
    private function makeAdminId(): int
    {
        $role = Role::query()->create([
            'name' => 'mass_send_role_'.uniqid(),
            'label' => '群发测试角色',
            'permissions' => [],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'mass_send_admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '群发测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);

        return (int) $admin->id;
    }
}
