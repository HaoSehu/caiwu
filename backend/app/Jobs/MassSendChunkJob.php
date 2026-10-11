<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MassSend;
use App\Models\MassSendTarget;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\MassSend\MassMessageRenderer;
use App\Services\Notification\UserNotificationService;
use App\Services\System\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M9 批量群发分片 Job：逐用户渲染模板变量并按通道发送。
 *
 * - 邮件通道走 NotificationService（内部经 MailDriverManager 与发送日志）；
 * - 站内信通道走 UserNotificationService；
 * - is_marketing=1 时发送侧二次校验 users.marketing_alert（targets 生成侧第一道）；
 * - 单用户失败落 error 不阻塞分片；sent/failed 计数原子累加；
 * - 待发清零后置已完成 + finished_at。
 *
 * 失败重试：Job tries=3 兜底队列级重试（如 DB 瞬断）；单用户发送异常由
 * try/catch 捕获记录，不触发整片重试（防已成功用户重复收到）。
 */
class MassSendChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [30, 120];

    public const INBOX_TYPE = 'mass_send';

    public function __construct(
        public int $massSendId,
        /** @var int[] */
        public array $targetIds = [],
    ) {
        $this->onQueue('notification');
        $this->afterCommit();
    }

    public function handle(
        NotificationService $notificationService,
        UserNotificationService $userNotificationService,
    ): void {
        $massSend = MassSend::query()->find($this->massSendId);

        // 已取消/不存在：不再发送，剩余目标保持待发由取消语义解释。
        if (! $massSend instanceof MassSend || $massSend->status !== MassSend::STATUS_SENDING) {
            return;
        }

        if ($this->targetIds === []) {
            return;
        }

        $users = User::query()
            ->whereIn('id', MassSendTarget::query()
                ->where('mass_send_id', $massSend->id)
                ->whereIn('id', $this->targetIds)
                ->pluck('user_id')
                ->all())
            ->get()
            ->keyBy('id');

        $template = $massSend->template_id !== null
            ? NotificationTemplate::query()->find($massSend->template_id)
            : null;

        foreach ($this->targetIds as $targetId) {
            // 原子消费抢占：仅 status=0 可置为发送中，受影响行数=0 表示已被其他
            // 分片（双派发/续派场景）消费，跳过防重发。
            $claimed = MassSendTarget::query()
                ->whereKey((int) $targetId)
                ->where('mass_send_id', $massSend->id)
                ->where('status', MassSendTarget::STATUS_PENDING)
                ->update(['status' => MassSendTarget::STATUS_SENDING]);

            if ($claimed === 0) {
                continue;
            }

            $target = MassSendTarget::query()->findOrFail((int) $targetId);
            $user = $users->get($target->user_id);

            if (! $user instanceof User) {
                $this->markTarget($target, false, '用户不存在或已删除', $massSend);

                continue;
            }

            // 营销合规双保险第二道：marketing_alert=0 的用户不允许收到营销类群发。
            if ((int) $massSend->is_marketing === 1 && (int) ($user->marketing_alert ?? 0) !== 1) {
                $this->markTarget($target, false, '用户已关闭营销通知（marketing_alert=0）', $massSend);

                continue;
            }

            try {
                $variables = MassMessageRenderer::baseVariables(
                    trim((string) $user->nickname),
                    trim((string) $user->email)
                );

                if (in_array((int) $massSend->channel, [MassSend::CHANNEL_EMAIL, MassSend::CHANNEL_BOTH], true)) {
                    $this->sendEmailLeg($massSend, $template, $target, $variables, $notificationService);
                }

                if (in_array((int) $massSend->channel, [MassSend::CHANNEL_INBOX, MassSend::CHANNEL_BOTH], true)) {
                    $this->sendInboxLeg($massSend, $template, $target, $variables, $userNotificationService);
                }

                $this->markTarget($target, true, null, $massSend);
            } catch (Throwable $exception) {
                $this->markTarget($target, false, mb_substr($exception->getMessage(), 0, 250), $massSend);
            }
        }

        $this->maybeFinish($massSend);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function sendEmailLeg(
        MassSend $massSend,
        ?NotificationTemplate $template,
        MassSendTarget $target,
        array $variables,
        NotificationService $notificationService,
    ): void {
        $email = trim((string) ($target->email ?? ''));
        if ($email === '') {
            throw new \RuntimeException('用户邮箱为空，无法发送邮件');
        }

        $subjectTemplate = $template !== null ? (string) $template->subject : (string) ($massSend->subject ?? '');
        $contentTemplate = $template !== null ? (string) $template->content : (string) ($massSend->content ?? '');

        $subject = MassMessageRenderer::render($subjectTemplate, $variables);
        // 邮件正文为 HTML 通道：对变量值做最小转义（防 {{nickname}} 注入标签）；主题为纯文本头不转义。
        $content = MassMessageRenderer::render($contentTemplate, $variables, true);

        if ($subject === '' || $content === '') {
            throw new \RuntimeException('邮件主题或正文渲染结果为空');
        }

        $notificationService->sendEmail($email, $subject, $content);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function sendInboxLeg(
        MassSend $massSend,
        ?NotificationTemplate $template,
        MassSendTarget $target,
        array $variables,
        UserNotificationService $userNotificationService,
    ): void {
        $subjectTemplate = $template !== null ? (string) $template->subject : (string) ($massSend->subject ?? '');
        $contentTemplate = $template !== null ? (string) $template->content : (string) ($massSend->content ?? '');

        $title = MassMessageRenderer::render($subjectTemplate !== '' ? $subjectTemplate : (string) $massSend->name, $variables);
        $content = MassMessageRenderer::render($contentTemplate, $variables);

        $created = $userNotificationService->create(
            (int) $target->user_id,
            self::INBOX_TYPE,
            $title,
            $content !== '' ? $content : null,
            null,
            ['mass_send_id' => (int) $massSend->id],
            (int) $massSend->is_marketing === 1
        );

        if ($created === null) {
            throw new \RuntimeException('站内信写入失败或用户未开放该类通知');
        }
    }

    private function markTarget(MassSendTarget $target, bool $success, ?string $error, MassSend $massSend): void
    {
        // 受原子消费抢占保护的终态回写：仅发送中状态可置成功/失败。
        MassSendTarget::query()
            ->whereKey($target->id)
            ->where('status', MassSendTarget::STATUS_SENDING)
            ->update([
                'status' => $success ? MassSendTarget::STATUS_SENT : MassSendTarget::STATUS_FAILED,
                'error' => $error,
                'sent_at' => $success ? now() : null,
            ]);

        // 计数原子累加（分片间无共享状态，increment 自带 SQL 级原子性）。
        $massSend->increment($success ? 'sent_count' : 'failed_count');
    }

    /**
     * 待发清零 → 已完成 + finished_at（幂等：仅发送中状态可置完成）。
     */
    private function maybeFinish(MassSend $massSend): void
    {
        $pending = MassSendTarget::query()
            ->where('mass_send_id', $massSend->id)
            ->where('status', MassSendTarget::STATUS_PENDING)
            ->count();

        if ($pending === 0) {
            MassSend::query()
                ->whereKey($massSend->id)
                ->where('status', MassSend::STATUS_SENDING)
                ->update([
                    'status' => MassSend::STATUS_FINISHED,
                    'finished_at' => now(),
                ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[批量群发] 分片任务失败', [
            'mass_send_id' => $this->massSendId,
            'targets' => $this->targetIds,
            'message' => $exception->getMessage(),
        ]);
    }
}
