<?php

declare(strict_types=1);

namespace App\Services\Care;

use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Jobs\CareSendChunkJob;
use App\Models\CarePlan;
use App\Models\CareSendLog;
use App\Models\CareTaskRun;
use App\Models\User;
use App\Services\User\UserSegmentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * M12 客户关怀计划自动化服务：按日计算目标 → 建 run → 落防重日志 → 分片派发发送。
 *
 * 与 BillingAutomationService 的去重口径（重要）：
 * - BillingAutomationService 已有服务续费提醒（renew_notice，7/3/1 天里程碑）与
 *   账单到期/逾期提醒。本服务的"到期提醒"计划与其并存会双发。
 * - 上线必须二选一：建议关闭 BillingAutomationService 的 renew_notice
 *   （settings: automation.renew_notice_enabled=false）后再启用关怀的到期类型，
 *   或不使用关怀的到期类型。同一服务同月由 care_send_logs 唯一键兜底不重复
 *   发送关怀侧，但跨服务双通道无法去重。
 *
 * 防重：unique(care_plan_id, user_id, cycle_key) + insertOrIgnore；
 * cycle_key 口径——生日 YYYY-MM-DD、到期 svc:{service_id}:{YYYY-MM}、
 * 召回/自定义 YYYY-MM / YYYY-MM-DD。
 */
class CarePlanAutomationService
{
    /** 发送分片大小 */
    private const CHUNK_SIZE = 100;

    public function __construct(
        private readonly UserSegmentService $segments,
    ) {}

    /**
     * 执行指定日期的全部（或单个）启用计划。
     *
     * @return array{dry_run: bool, date: string, plans: array<int, array<string, mixed>>}
     */
    public function runForDate(Carbon $date, ?int $planId = null, bool $dryRun = false): array
    {
        $query = CarePlan::query()->where('is_enabled', 1);

        if ($planId !== null) {
            $query->whereKey($planId);
        }

        $plans = $query->orderBy('id')->get();
        $summaries = [];

        foreach ($plans as $plan) {
            try {
                $summaries[] = $dryRun
                    ? $this->dryRunPlan($plan, $date)
                    : $this->executePlan($plan, $date);
            } catch (\Throwable $exception) {
                Log::error('[客户关怀] 计划执行失败', [
                    'care_plan_id' => $plan->id,
                    'planned_date' => $date->toDateString(),
                    'message' => $exception->getMessage(),
                ]);

                $summaries[] = [
                    'care_plan_id' => (int) $plan->id,
                    'name' => (string) $plan->name,
                    'error' => $exception->getMessage(),
                    'total_count' => 0,
                    'sent_count' => 0,
                    'failed_count' => 0,
                    'skipped_count' => 0,
                ];
            }
        }

        return [
            'dry_run' => $dryRun,
            'date' => $date->toDateString(),
            'plans' => $summaries,
        ];
    }

    /**
     * 手动触发入口（支持 dry_run）。
     *
     * @return array<string, mixed>
     */
    public function trigger(int $planId, bool $dryRun = false): array
    {
        $plan = CarePlan::query()->find($planId);

        if (! $plan instanceof CarePlan) {
            throw new BusinessException('关怀计划不存在', 40400, 404);
        }

        if ($dryRun) {
            return $this->dryRunPlan($plan, now());
        }

        if ((int) $plan->is_enabled !== 1) {
            throw new BusinessException('关怀计划未启用，请先启用再触发');
        }

        return $this->executePlan($plan, now());
    }

    // ─── 执行 ─────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function executePlan(CarePlan $plan, Carbon $date): array
    {
        $skippedCount = 0;
        $targets = $this->resolveTargets($plan, $date, $skippedCount);

        // 幂等锚点：同计划同目标日期只建一次 run；重触发复用，逐目标由唯一键防重。
        $run = CareTaskRun::query()->firstOrCreate(
            [
                'care_plan_id' => $plan->id,
                'planned_date' => $date->toDateString(),
            ],
            [
                'status' => CareTaskRun::STATUS_PENDING,
                'total_count' => 0,
                'sent_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
                'started_at' => now(),
            ]
        );

        $plan->forceFill(['last_planned_date' => $date->toDateString()])->save();

        $rows = [];
        $insertedCount = 0;

        foreach ($targets as $target) {
            $rows[] = [
                'care_plan_id' => $plan->id,
                'run_id' => $run->id,
                'user_id' => $target['user_id'],
                'cycle_key' => $target['cycle_key'],
                'service_id' => $target['service_id'],
                'status' => CareSendLog::STATUS_PENDING,
            ];

            if (count($rows) >= 500) {
                $insertedCount += $this->insertLogs($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $insertedCount += $this->insertLogs($rows);
        }

        // 防重后本 run 实际持有的待发日志（重触发时此前已存在的周期键不会重复插入）。
        $logIds = CareSendLog::query()
            ->where('run_id', $run->id)
            ->where('status', CareSendLog::STATUS_PENDING)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        // total_count 累加（仅本次实际新插入数）：重触发复用 run 时不得覆盖既有计数。
        $run->forceFill([
            'total_count' => (int) $run->total_count + $insertedCount,
            'skipped_count' => $skippedCount,
        ])->save();

        // 分片派发（100/片）；发送结果由 CareSendChunkJob 回写并收口 run。
        foreach (array_chunk($logIds, self::CHUNK_SIZE) as $chunkIds) {
            CareSendChunkJob::dispatch(
                $plan->id,
                $run->id,
                array_map(intval(...), $chunkIds),
                $this->variablesForLogs($plan, $chunkIds, $date)
            );
        }

        return [
            'care_plan_id' => (int) $plan->id,
            'name' => (string) $plan->name,
            'type' => (int) $plan->type,
            'run_id' => (int) $run->id,
            'total_count' => $insertedCount,
            'sent_count' => 0,
            'failed_count' => 0,
            'skipped_count' => $skippedCount,
        ];
    }

    /**
     * dry-run：仅试算目标数，不建 run、不落日志、不发送。
     *
     * @return array<string, mixed>
     */
    private function dryRunPlan(CarePlan $plan, Carbon $date): array
    {
        $skippedCount = 0;
        $targets = $this->resolveTargets($plan, $date, $skippedCount);

        return [
            'care_plan_id' => (int) $plan->id,
            'name' => (string) $plan->name,
            'type' => (int) $plan->type,
            'dry_run' => true,
            'total_count' => count($targets),
            'sent_count' => 0,
            'failed_count' => 0,
            'skipped_count' => $skippedCount,
        ];
    }

    /**
     * 防重落日志：insertOrIgnore 命中 unique(care_plan_id,user_id,cycle_key) 的行
     * 自动跳过（同周期不重发）；返回实际插入行数。
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertLogs(array $rows): int
    {
        return (int) CareSendLog::query()->insertOrIgnore($rows);
    }

    /**
     * 按 log 重查变量（Job 侧无法直接携带全量快照时的回查查询）。
     *
     * @param  int[]  $logIds
     * @return array<int, array<string, string>>
     */
    private function variablesForLogs(CarePlan $plan, array $logIds, Carbon $date): array
    {
        // 变量快照已随 insertLogs 计算但未持久化；分片 Job 以基础变量 +
        // 类型变量重建（service_id 已落日志，可回查服务名与到期时间）。
        $logs = CareSendLog::query()->whereIn('id', $logIds)->get();
        $variables = [];

        foreach ($logs as $log) {
            $variables[(int) $log->id] = $this->typeVariables($plan, (int) $log->user_id, (int) ($log->service_id ?? 0), $date);
        }

        return $variables;
    }

    // ─── 目标计算 ─────────────────────────────────────────────────────────────

    /**
     * 计算计划目标。
     *
     * @param  int  $skippedCount  输出参数：跳过数（如未填生日）
     * @return array<int, array{user_id: int, email: string, cycle_key: string, service_id: int|null, variables: array<string, string>}>
     */
    private function resolveTargets(CarePlan $plan, Carbon $date, int &$skippedCount = 0): array
    {
        $skippedCount = 0;
        $filter = is_array($plan->filter_json) ? $plan->filter_json : [];
        $baseBuilder = $this->segments->resolveBuilder($filter);

        return match ((int) $plan->type) {
            CarePlan::TYPE_BIRTHDAY => $this->birthdayTargets($plan, $baseBuilder, $date, $skippedCount),
            CarePlan::TYPE_EXPIRE => $this->expireTargets($plan, $baseBuilder, $date),
            CarePlan::TYPE_RECALL => $this->recallTargets($plan, $baseBuilder, $date),
            CarePlan::TYPE_CUSTOM => $this->customTargets($plan, $baseBuilder, $date),
            default => throw new BusinessException('未知的关怀计划类型：'.$plan->type),
        };
    }

    /**
     * 生日祝福：birth_date 月日 = 目标日期（$date + offset）月日。
     *
     * 闰年简化：目标 2/29 在平年按 2/28 匹配（与多数业务系统一致，避免平年漏发）。
     * 未填 birth_date 的命中用户计入 skipped，不发送。
     *
     * @param  Builder<User>  $baseBuilder
     * @param  int  $skippedCount  输出参数：跳过数（如未填生日）
     * @return array<int, array{user_id: int, email: string, cycle_key: string, service_id: int|null, variables: array<string, string>}>
     */
    private function birthdayTargets(CarePlan $plan, Builder $baseBuilder, Carbon $date, int &$skippedCount): array
    {
        $targetDate = $date->copy()->addDays((int) $plan->offset_days);
        $month = (int) $targetDate->format('n');
        $day = (int) $targetDate->format('j');

        // 闰年简化：平年日历不存在 2/29，2/29 出生的用户在平年 2/28 一并祝福（跳年不漏发）。
        // 闰年 2/29 正常命中，不提前匹配，避免双发。
        $alsoMatchLeapDay = $month === 2 && $day === 28
            && ! checkdate(2, 29, (int) $targetDate->format('Y'));

        $skippedCount = (int) (clone $baseBuilder)->whereNull('birth_date')->count();

        $matched = (clone $baseBuilder)
            ->whereNotNull('birth_date')
            ->where(function ($query) use ($month, $day, $alsoMatchLeapDay): void {
                $query->whereRaw('MONTH(birth_date) = ? AND DAY(birth_date) = ?', [$month, $day]);
                if ($alsoMatchLeapDay) {
                    $query->orWhereRaw('MONTH(birth_date) = 2 AND DAY(birth_date) = 29');
                }
            })
            ->get(['id', 'email', 'nickname']);

        $targets = [];

        foreach ($matched as $user) {
            $targets[] = [
                'user_id' => (int) $user->id,
                'email' => trim((string) $user->email),
                'cycle_key' => $date->format('Y-m-d'),
                'service_id' => null,
                'variables' => [
                    'birthday_date' => $targetDate->format('Y-m-d'),
                ],
            ];
        }

        return $targets;
    }

    /**
     * 到期提醒：服务 expires_at 日期 = $date + offset。
     *
     * 口径：ACTIVE + SUSPENDED 服务（与 BillingAutomationService::sendRenewNotices 一致，
     * 已取消/终止不再关怀）。cycle_key 按月粒度（svc:{id}:{YYYY-MM}）防同月重发。
     * 与 BillingAutomationService 的双发去重见类注释。
     *
     * @param  Builder<User>  $baseBuilder
     * @return array<int, array{user_id: int, email: string, cycle_key: string, service_id: int|null, variables: array<string, string>}>
     */
    private function expireTargets(CarePlan $plan, Builder $baseBuilder, Carbon $date): array
    {
        $targetDate = $date->copy()->addDays((int) $plan->offset_days)->startOfDay();

        $services = DB::table('services')
            ->join('users', 'users.id', '=', 'services.user_id')
            ->whereNull('services.deleted_at')
            ->whereIn('services.status', [ServiceStatus::ACTIVE, ServiceStatus::SUSPENDED])
            ->whereRaw('DATE(services.expires_at) = ?', [$targetDate->toDateString()])
            ->orderBy('services.id')
            ->get([
                'services.id as service_id',
                'services.user_id',
                'services.name as service_name',
                'services.expires_at',
                'users.email',
                'users.nickname',
            ]);

        if ($services->isEmpty()) {
            return [];
        }

        // 叠加客群过滤：对候选用户集合做 segment 过滤（到期日匹配的服务数量有限，先查后滤）。
        $userIds = $services->pluck('user_id')->unique()->values()->all();
        $allowedUserIds = (clone $baseBuilder)->whereIn('id', $userIds)->pluck('id')->all();
        $allowed = array_fill_keys(array_map(intval(...), $allowedUserIds), true);

        $targets = [];

        foreach ($services as $service) {
            if (! isset($allowed[(int) $service->user_id])) {
                continue;
            }

            $expiresAt = $service->expires_at !== null ? Carbon::parse($service->expires_at) : $targetDate;

            $targets[] = [
                'user_id' => (int) $service->user_id,
                'email' => trim((string) $service->email),
                'cycle_key' => 'svc:'.$service->service_id.':'.$date->format('Y-m'),
                'service_id' => (int) $service->service_id,
                'variables' => [
                    'service_name' => (string) $service->service_name,
                    'expires_at' => $expiresAt->format('Y-m-d H:i'),
                    'days_left' => (string) (int) $plan->offset_days,
                ],
            ];
        }

        return $targets;
    }

    /**
     * 流失召回：last_login_at < $date − offset（从未登录视为满足）且名下无 active 服务。
     * cycle_key 按月粒度，同月只召回一次。
     *
     * @param  Builder<User>  $baseBuilder
     * @return array<int, array{user_id: int, email: string, cycle_key: string, service_id: int|null, variables: array<string, string>}>
     */
    private function recallTargets(CarePlan $plan, Builder $baseBuilder, Carbon $date): array
    {
        $silentBefore = $date->copy()->subDays((int) $plan->offset_days)->endOfDay();

        $builder = (clone $baseBuilder)
            ->where(function ($query) use ($silentBefore): void {
                $query->whereNull('last_login_at')->orWhere('last_login_at', '<', $silentBefore);
            })
            // 名下无 active 服务（未软删）。
            ->whereRaw('NOT EXISTS (SELECT 1 FROM services WHERE services.user_id = users.id '
                .'AND services.status = ? AND services.deleted_at IS NULL)', [ServiceStatus::ACTIVE]);

        $targets = [];

        foreach ($this->segments->chunkedUsers($builder, 500) as $user) {
            $targets[] = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'cycle_key' => $date->format('Y-m'),
                'service_id' => null,
                'variables' => [
                    'silent_days' => (string) (int) $plan->offset_days,
                ],
            ];
        }

        return $targets;
    }

    /**
     * 自定义：纯客群广播，按天防重（cycle_key = 计划日期）。
     *
     * @param  Builder<User>  $baseBuilder
     * @return array<int, array{user_id: int, email: string, cycle_key: string, service_id: int|null, variables: array<string, string>}>
     */
    private function customTargets(CarePlan $plan, Builder $baseBuilder, Carbon $date): array
    {
        $targets = [];

        foreach ($this->segments->chunkedUsers($baseBuilder, 500) as $user) {
            $targets[] = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'cycle_key' => $date->format('Y-m-d'),
                'service_id' => null,
                'variables' => [],
            ];
        }

        return $targets;
    }

    /**
     * 分片 Job 的类型变量回查（以日志 service_id 维度重建）。
     *
     * @return array<string, string>
     */
    private function typeVariables(CarePlan $plan, int $userId, int $serviceId, Carbon $date): array
    {
        if ((int) $plan->type === CarePlan::TYPE_EXPIRE && $serviceId > 0) {
            $service = DB::table('services')->where('id', $serviceId)->first(['name', 'expires_at']);

            if ($service !== null) {
                return [
                    'service_name' => (string) $service->name,
                    'expires_at' => $service->expires_at !== null ? Carbon::parse($service->expires_at)->format('Y-m-d H:i') : '',
                    'days_left' => (string) (int) $plan->offset_days,
                ];
            }
        }

        return match ((int) $plan->type) {
            // 生日日期按本次执行的目标日期派生（执行日 + offset），与 birthdayTargets 同源，
            // 不得用 now()——回补历史日期执行时变量会漂移。
            CarePlan::TYPE_BIRTHDAY => ['birthday_date' => $date->copy()->addDays((int) $plan->offset_days)->format('Y-m-d')],
            CarePlan::TYPE_RECALL => ['silent_days' => (string) (int) $plan->offset_days],
            default => [],
        };
    }
}
