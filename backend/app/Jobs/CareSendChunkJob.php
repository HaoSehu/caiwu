<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CarePlan;
use App\Models\CareSendLog;
use App\Models\CareTaskRun;
use App\Models\User;
use App\Services\MassSend\MassMessageRenderer;
use App\Services\Notification\UserNotificationService;
use App\Services\System\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * M12 关怀计划分片发送 Job（复用 M9 分片模式）。
 *
 * 载荷为 care_send_logs 主键 + 每目标变量快照；逐用户渲染 {{nickname}}/{{email}}
 * 及类型变量（{{service_name}}/{{expires_at}}/{{days_left}} 等），邮件走
 * NotificationService、站内信走 UserNotificationService（关怀类非营销，
 * 不受 marketing_alert 限制）。防重由 unique(care_plan_id,user_id,cycle_key)
 * + insertOrIgnore 保证，本 Job 只处理 pending 日志。
 */
class CareSendChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [30, 120];

    public const INBOX_TYPE = 'care_plan';

    /**
     * @param  int[]  $sendLogIds
     * @param  array<int, array<string, string>>  $variablesByLogId  log_id → 模板变量
     */
    public function __construct(
        public int $carePlanId,
        public int $runId,
        public array $sendLogIds = [],
        public array $variablesByLogId = [],
    ) {
        $this->onQueue('notification');
        $this->afterCommit();
    }

    public function handle(
        NotificationService $notificationService,
        UserNotificationService $userNotificationService,
    ): void {
        $plan = CarePlan::query()->find($this->carePlanId);
        $run = CareTaskRun::query()->find($this->runId);

        if (! $plan instanceof CarePlan || ! $run instanceof CareTaskRun) {
            return;
        }

        $logs = CareSendLog::query()
            ->where('care_plan_id', $plan->id)
            ->whereIn('id', $this->sendLogIds)
            ->where('status', CareSendLog::STATUS_PENDING)
            ->get();

        if ($logs->isEmpty()) {
            $this->maybeFinish($run);

            return;
        }

        $users = User::query()
            ->whereIn('id', $logs->pluck('user_id')->all())
            ->get()
            ->keyBy('id');

        $template = $plan->template_id !== null ? $plan->template : null;

        foreach ($logs as $log) {
            $user = $users->get($log->user_id);

            if (! $user instanceof User) {
                $this->markLog($log, false, '用户不存在或已删除', $run);

                continue;
            }

            try {
                $variables = MassMessageRenderer::baseVariables(
                    trim((string) $user->nickname),
                    trim((string) $user->email),
                    is_array($this->variablesByLogId[$log->id] ?? null) ? $this->variablesByLogId[$log->id] : []
                );

                if (in_array((int) $plan->channel, [CarePlan::CHANNEL_EMAIL, CarePlan::CHANNEL_BOTH], true)) {
                    $email = trim((string) $user->email);

                    if ($email === '') {
                        throw new \RuntimeException('用户邮箱为空，无法发送邮件');
                    }

                    $subject = MassMessageRenderer::render((string) ($template->subject ?? $plan->subject ?? $plan->name), $variables);
                    // 邮件正文为 HTML 通道：对变量值做最小转义（防 {{nickname}} 注入标签）；主题为纯文本头不转义。
                    $content = MassMessageRenderer::render((string) ($template->content ?? $plan->content ?? ''), $variables, true);

                    if ($subject === '' || $content === '') {
                        throw new \RuntimeException('关怀邮件主题或正文渲染结果为空');
                    }

                    $notificationService->sendEmail($email, $subject, $content);
                }

                if (in_array((int) $plan->channel, [CarePlan::CHANNEL_INBOX, CarePlan::CHANNEL_BOTH], true)) {
                    $title = MassMessageRenderer::render((string) ($template->subject ?? $plan->subject ?? $plan->name), $variables);
                    $content = MassMessageRenderer::render((string) ($template->content ?? $plan->content ?? ''), $variables);

                    $created = $userNotificationService->create(
                        (int) $user->id,
                        self::INBOX_TYPE,
                        $title,
                        $content !== '' ? $content : null,
                        null,
                        ['care_plan_id' => (int) $plan->id, 'cycle_key' => (string) $log->cycle_key],
                        false
                    );

                    if ($created === null) {
                        throw new \RuntimeException('站内信写入失败');
                    }
                }

                $this->markLog($log, true, null, $run);
            } catch (Throwable $exception) {
                $this->markLog($log, false, mb_substr($exception->getMessage(), 0, 250), $run);
            }
        }

        $this->maybeFinish($run);
    }

    private function markLog(CareSendLog $log, bool $success, ?string $error, CareTaskRun $run): void
    {
        CareSendLog::query()
            ->whereKey($log->id)
            ->where('status', CareSendLog::STATUS_PENDING)
            ->update([
                'status' => $success ? CareSendLog::STATUS_SENT : CareSendLog::STATUS_FAILED,
                'error' => $error,
                'sent_at' => $success ? now() : null,
            ]);

        $run->increment($success ? 'sent_count' : 'failed_count');
    }

    /**
     * 本 run 待发清零 → 置成功/部分失败 + finished_at（幂等：仅待执行状态可收口；
     * 以 run_id 归属判定，历史 run 的遗留 pending 不阻塞当前 run 收口）。
     */
    private function maybeFinish(CareTaskRun $run): void
    {
        $runPending = CareSendLog::query()
            ->where('run_id', $run->id)
            ->where('status', CareSendLog::STATUS_PENDING)
            ->count();

        if ($runPending > 0) {
            return;
        }

        $failed = (int) $run->failed_count;

        CareTaskRun::query()
            ->whereKey($run->id)
            ->where('status', CareTaskRun::STATUS_PENDING)
            ->update([
                'status' => $failed > 0 ? CareTaskRun::STATUS_PARTIAL_FAILED : CareTaskRun::STATUS_SUCCESS,
                'finished_at' => now(),
            ]);
    }
}
