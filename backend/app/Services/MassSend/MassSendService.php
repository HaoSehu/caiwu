<?php

declare(strict_types=1);

namespace App\Services\MassSend;

use App\Exceptions\BusinessException;
use App\Jobs\MassSendDispatchJob;
use App\Models\MassSend;
use App\Models\MassSendTarget;
use App\Models\NotificationTemplate;
use App\Services\User\UserSegmentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * M9 批量群发服务：草稿 CRUD、客群试算、启动落目标、取消、逐用户结果查询。
 *
 * 发送执行链：start() 落 targets 并派发 MassSendDispatchJob →
 * Dispatch 分块派发 MassSendChunkJob（100/片）→ Chunk 逐用户发送与计数。
 */
class MassSendService
{
    public function __construct(
        private readonly UserSegmentService $segments,
    ) {}

    // ─── 查询 ─────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $filters
     */
    public function adminList(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = MassSend::query()->with('template:id,channel,code,name,subject');

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (isset($filters['keyword']) && trim((string) $filters['keyword']) !== '') {
            $query->where('name', 'like', '%'.trim((string) $filters['keyword']).'%');
        }

        return $query->orderByDesc('id')->paginate(max(1, min(100, $perPage)));
    }

    public function adminShow(int $id): MassSend
    {
        $massSend = MassSend::query()->with('template:id,channel,code,name,subject')->find($id);

        if (! $massSend instanceof MassSend) {
            throw new BusinessException('群发任务不存在', 40400, 404);
        }

        return $massSend;
    }

    /**
     * 逐用户发送结果分页。
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateTargets(MassSend $massSend, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = MassSendTarget::query()->where('mass_send_id', $massSend->id);

        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        return $query->orderBy('id')->paginate(max(1, min(100, $perPage)));
    }

    // ─── 试算 ─────────────────────────────────────────────────────────────────

    /**
     * 试算客群命中数（不落库）。纯客群口径：不含 is_marketing 剔除
     * （该剔除发生在 start() 目标生成阶段，依赖任务字段而非 filter 本身）。
     *
     * @param  array<string, mixed>  $filter
     * @return array{total_count: int}
     */
    public function preview(array $filter): array
    {
        return ['total_count' => $this->segments->countHits($filter)];
    }

    // ─── 草稿 CRUD ────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createDraft(array $payload, int $adminId): MassSend
    {
        $validated = $this->validatePayload($payload);

        return MassSend::create($validated + [
            'status' => MassSend::STATUS_DRAFT,
            'total_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'created_by' => $adminId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateDraft(MassSend $massSend, array $payload): MassSend
    {
        if (! $massSend->isEditable()) {
            throw new BusinessException('仅草稿状态的群发任务可编辑');
        }

        $validated = $this->validatePayload($payload, $massSend);

        $massSend->fill($validated)->save();

        return $massSend->refresh();
    }

    public function deleteDraft(MassSend $massSend): void
    {
        if (! $massSend->isEditable()) {
            throw new BusinessException('仅草稿状态的群发任务可删除');
        }

        $massSend->delete();
    }

    // ─── 启动 / 取消 ──────────────────────────────────────────────────────────

    /**
     * 启动群发：重新试算 → 分块落 targets → total_count → 待发送 → 派发调度 Job。
     */
    public function start(MassSend $massSend): MassSend
    {
        if ($massSend->status !== MassSend::STATUS_DRAFT) {
            throw new BusinessException('仅草稿状态的群发任务可启动发送');
        }

        $filter = $massSend->filter_json;
        $builder = $this->segments->resolveBuilder($filter);

        // 营销类任务剔除 marketing_alert=0 用户（targets 生成侧第一道保险，
        // chunk 发送侧另有二次校验）。余额/产品等条件由 UserSegmentService 白名单承担。
        if ((int) $massSend->is_marketing === 1) {
            $builder->where('marketing_alert', 1);
        }

        // targets 分块写入 + total_count + 状态流转包进同一事务：中途失败整体回滚，
        // 重入 start()（草稿状态未变）可安全重跑，不残留半量 targets。
        // Dispatch Job 注册了 afterCommit，事务提交后才真正入队。
        DB::transaction(function () use ($massSend, $builder): void {
            $pendingRows = [];
            $total = 0;

            foreach ($this->segments->chunkedUsers($builder, 500) as $user) {
                $pendingRows[] = [
                    'mass_send_id' => $massSend->id,
                    'user_id' => $user['id'],
                    'email' => $user['email'] !== '' ? $user['email'] : null,
                    'status' => MassSendTarget::STATUS_PENDING,
                ];
                $total++;

                if (count($pendingRows) >= 500) {
                    MassSendTarget::query()->insert($pendingRows);
                    $pendingRows = [];
                }
            }

            if ($pendingRows !== []) {
                MassSendTarget::query()->insert($pendingRows);
            }

            if ($total === 0) {
                throw new BusinessException('客群试算命中 0 人，请调整筛选条件');
            }

            $massSend->forceFill([
                'total_count' => $total,
                'status' => MassSend::STATUS_PENDING,
            ])->save();
        });

        MassSendDispatchJob::dispatch($massSend->id);

        return $massSend->refresh();
    }

    public function cancel(MassSend $massSend): MassSend
    {
        if (! in_array($massSend->status, [MassSend::STATUS_DRAFT, MassSend::STATUS_PENDING, MassSend::STATUS_SENDING], true)) {
            throw new BusinessException('当前状态不可取消（已完成/已取消）');
        }

        $affected = MassSend::query()
            ->whereKey($massSend->id)
            ->whereIn('status', [MassSend::STATUS_DRAFT, MassSend::STATUS_PENDING, MassSend::STATUS_SENDING])
            ->update([
                'status' => MassSend::STATUS_CANCELLED,
                'finished_at' => now(),
            ]);

        if ($affected === 0) {
            throw new BusinessException('状态已变化，请刷新后重试');
        }

        return $massSend->refresh();
    }

    // ─── 校验 ─────────────────────────────────────────────────────────────────

    /**
     * 草稿载荷校验：模板与自定义内容二选一、channel 与模板 channel 匹配、
     * filter 白名单即时校验（防草稿落库后 start 阶段才炸）。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePayload(array $payload, ?MassSend $existing = null): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new BusinessException('任务名不能为空');
        }

        $channel = (int) ($payload['channel'] ?? 0);
        if (! in_array($channel, [MassSend::CHANNEL_EMAIL, MassSend::CHANNEL_INBOX, MassSend::CHANNEL_BOTH], true)) {
            throw new BusinessException('通道必须为 1邮件/2站内信/3两者');
        }

        $isMarketing = (int) ($payload['is_marketing'] ?? 1);
        if (! in_array($isMarketing, [0, 1], true)) {
            throw new BusinessException('is_marketing 必须为 0 或 1');
        }

        $templateId = isset($payload['template_id']) && $payload['template_id'] !== ''
            ? (int) $payload['template_id'] : null;
        $subject = trim((string) ($payload['subject'] ?? ''));
        $content = (string) ($payload['content'] ?? '');

        $filter = $payload['filter_json'] ?? [];
        if (! is_array($filter)) {
            throw new BusinessException('filter_json 必须为对象');
        }
        // 白名单即时校验（结果不落库，只验证结构合法性）。
        $this->segments->countHits($filter);

        $template = null;
        if ($templateId !== null) {
            $template = NotificationTemplate::query()->find($templateId);

            if (! $template instanceof NotificationTemplate) {
                throw new BusinessException('通知模板不存在');
            }

            if (! in_array($channel, [MassSend::CHANNEL_EMAIL, MassSend::CHANNEL_BOTH], true)) {
                throw new BusinessException('套用邮件模板时通道必须包含邮件（1 或 3）');
            }

            if ((string) $template->channel !== 'email') {
                throw new BusinessException('模板通道与群发通道不匹配（仅支持 email 模板）');
            }
        } elseif ($subject === '' || trim($content) === '') {
            throw new BusinessException('未套模板时必须提供 subject 与 content');
        }

        if ($templateId === null && $channel === MassSend::CHANNEL_EMAIL && $subject === '') {
            throw new BusinessException('邮件通道必须提供主题');
        }

        return [
            'name' => $name,
            'channel' => $channel,
            'is_marketing' => $isMarketing,
            'template_id' => $templateId,
            'subject' => $subject !== '' ? $subject : null,
            'content' => $content !== '' ? $content : null,
            'filter_json' => $filter,
            'scheduled_at' => $this->normalizeScheduledAt($payload['scheduled_at'] ?? null),
        ];
    }

    private function normalizeScheduledAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new BusinessException('scheduled_at 必须为时间字符串');
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            throw new BusinessException('scheduled_at 时间格式不合法');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
