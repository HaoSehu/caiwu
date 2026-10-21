<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Constants\PaymentGatewayCode;
use App\Models\ActivityLog;
use App\Models\AdminLoginLog;
use App\Models\AdminUser;
use App\Models\GatewayLog;
use App\Models\IntegrationPluginRuntimeLog;
use App\Models\MessageLog;
use App\Models\Role;
use App\Models\ScheduleRunLog;
use App\Models\User;
use App\Services\Automation\Heartbeat\HeartbeatTaskRegistry;
use App\Services\System\Concerns\HandlesAdminLogCleanup;
use App\Support\AdminPrivacy;
use App\Support\ApiAccessLogFile;
use App\Support\LikeEscape;
use App\Support\SchemaMetadataCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AdminLogService
{
    use HandlesAdminLogCleanup;

    private const HTTP_ACTION_REGEXP = '^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD) ';

    /** @var list<string> */
    private const HTTP_ACTION_PREFIXES = ['GET ', 'POST ', 'PUT ', 'PATCH ', 'DELETE ', 'OPTIONS ', 'HEAD '];

    private const LIST_SUMMARY_CACHE_TTL_SECONDS = 30;

    private const FILE_LOG_CACHE_TTL_SECONDS = 60;

    private const CLEANUP_OVERVIEW_CACHE_TTL_SECONDS = 60;

    private const CLEANUP_OVERVIEW_CACHE_VERSION_KEY = 'admin_logs:cleanup_overview:version';

    private const FILE_LOG_SUMMARY_CACHE_TTL_SECONDS = 60;

    // 合并 activity_logs 与文件窗口时最多载入的数据库候选行数；
    // 防止恶意/误传超大 page 触发全表结果集进入 PHP 内存。
    public const API_CANDIDATE_MAX_ROWS = 10000;

    /**
     * V9-02 冻结边界（最后一条存量 schedule_run_logs.created_at）的请求内记忆化。
     * false = 尚未计算；null = 无存量行（表缺失或为空）。
     */
    private Carbon|false|null $scheduleRunLogFreezeBoundaryMemo = false;

    /** 核心静态任务元数据：title 与 log_keywords 同时收录，供日志展示与文件日志反查。 */
    private const TASK_META = [
        'refresh-hosting-panel-auth' => [
            'title' => '接口认证刷新',
            'log_keywords' => ['JWT刷新', '接口认证刷新', 'refresh-hosting-panel-auth'],
        ],
        'service-auto-renew' => [
            'title' => '服务自动续费',
            'log_keywords' => ['自动续费执行完成', '[自动续费]', 'service-auto-renew'],
        ],
        'referral-release-rewards' => [
            'title' => '推荐奖励释放',
            'log_keywords' => ['推荐奖励释放执行完成', '推荐奖励', 'referral-release-rewards'],
        ],
        'service-lifecycle-maintenance' => [
            'title' => '服务生命周期维护',
            'log_keywords' => ['服务生命周期维护执行完成', 'service-lifecycle-maintenance'],
        ],
        'service-status-sync' => [
            'title' => '用户产品状态同步',
            'log_keywords' => ['用户产品状态同步执行完成', 'service-status-sync'],
        ],
        'billing-maintenance' => [
            'title' => '账单自动化维护',
            'log_keywords' => ['账单自动化维护执行完成', 'billing-maintenance'],
        ],
        'product-upstream-config-sync' => [
            'title' => '上游产品配置同步',
            'log_keywords' => ['上游产品配置同步执行完成', 'product-upstream-config-sync'],
        ],
        'coupon-campaign-dispatch' => [
            'title' => '优惠券活动发放',
            'log_keywords' => ['优惠券活动自动发放执行完成', 'coupon-campaign-dispatch'],
        ],
        'ticket-auto-close' => [
            'title' => '工单自动关闭',
            'log_keywords' => ['工单自动关闭执行完成', 'ticket-auto-close'],
        ],
        'order-cleanup' => [
            'title' => '账单与充值清理',
            'log_keywords' => ['账单与充值清理执行完成', '订单与充值清理执行完成', 'order-cleanup'],
        ],
    ];

    public function getSmsLogs(array $filters, int $perPage, int $page = 1): array
    {
        $query = $this->buildSmsLogQuery($filters);
        if ($query === null) {
            return $this->buildPaginatorPayload($this->emptyPaginator($perPage));
        }

        $logs = (clone $query)->orderByDesc('created_at')->orderByDesc('id')->paginate(
            $perPage,
            ['*'],
            'page',
            max(1, $page),
        );
        $logs->setCollection($logs->getCollection()->map(function ($log) {
            $item = $log->toArray();
            $item['params_json'] = $this->normalizeNotificationParams($item['params_json'] ?? []);
            $item['params'] = $item['params_json'] ?? [];
            unset($item['params_json']);

            return $item;
        }));

        return $this->buildPaginatorPayload($logs);
    }

    public function getSmsLogsSummary(array $filters): array
    {
        return Cache::remember(
            $this->buildListSummaryCacheKey('sms', $filters),
            now()->addSeconds(self::LIST_SUMMARY_CACHE_TTL_SECONDS),
            function () use ($filters) {
                $query = $this->buildNotificationSummaryQuery('sms', $filters);
                if ($query === null) {
                    return [
                        'total' => 0,
                        'success' => 0,
                        'failed' => 0,
                        'pending' => 0,
                    ];
                }

                $summary = $query
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("COALESCE(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END), 0) as success")
                    ->selectRaw("COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed")
                    ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending")
                    ->first();

                return [
                    'total' => (int) ($summary?->total ?? 0),
                    'success' => (int) ($summary?->success ?? 0),
                    'failed' => (int) ($summary?->failed ?? 0),
                    'pending' => (int) ($summary?->pending ?? 0),
                ];
            }
        );
    }

    public function getEmailLogs(array $filters, int $perPage, int $page = 1): array
    {
        $query = $this->buildEmailLogQuery($filters);
        if ($query === null) {
            return $this->buildPaginatorPayload($this->emptyPaginator($perPage));
        }

        $logs = (clone $query)->orderByDesc('created_at')->orderByDesc('id')->paginate(
            $perPage,
            ['*'],
            'page',
            max(1, $page),
        );
        $logs->setCollection($logs->getCollection()->map(function ($log) {
            return $log->toArray();
        }));

        return $this->buildPaginatorPayload($logs);
    }

    public function getEmailLogsSummary(array $filters): array
    {
        return Cache::remember(
            $this->buildListSummaryCacheKey('email', $filters),
            now()->addSeconds(self::LIST_SUMMARY_CACHE_TTL_SECONDS),
            function () use ($filters) {
                $query = $this->buildNotificationSummaryQuery('email', $filters);
                if ($query === null) {
                    return [
                        'total' => 0,
                        'success' => 0,
                        'failed' => 0,
                        'pending' => 0,
                    ];
                }

                $summary = $query
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("COALESCE(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END), 0) as success")
                    ->selectRaw("COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed")
                    ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending")
                    ->first();

                return [
                    'total' => (int) ($summary?->total ?? 0),
                    'success' => (int) ($summary?->success ?? 0),
                    'failed' => (int) ($summary?->failed ?? 0),
                    'pending' => (int) ($summary?->pending ?? 0),
                ];
            }
        );
    }

    public function getApiLogs(array $filters, int $page, int $perPage, bool $withSummary = true): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $query = ActivityLog::query();
        $this->applyHttpActionFilter($query);

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('action', 'like', LikeEscape::contains($keyword))
                    ->orWhere('module', 'like', LikeEscape::contains($keyword))
                    ->orWhere('ip_address', 'like', LikeEscape::contains($keyword))
                    ->orWhere('context_request_id', 'like', LikeEscape::contains($keyword));
            });
        }

        if (! empty($filters['ip_address'])) {
            $query->where('ip_address', 'like', LikeEscape::contains(trim((string) $filters['ip_address'])));
        }

        // trace_id 是管理端 API 日志筛选契约的一部分。新请求写入
        // activity_logs.trace_id，旧行/旧 schema 则把 request_id 放在 JSON
        // context 中；两种形态都必须在数据库侧先收窄候选集，否则启用
        // 文件日志合并后会把不相关的访问记录一起返回。
        if (! empty($filters['trace_id'])) {
            $traceId = trim((string) $filters['trace_id']);
            if (SchemaMetadataCache::hasColumn('activity_logs', 'trace_id')) {
                // 迁移后的新行把链路 ID 写入独立列；迁移前的历史行仍可能
                // 只在 context.request_id 中保存。两种形态必须一起匹配，
                // 否则同一个 trace_id 在切换窗口内会被分页结果漏掉。
                $query->where(function ($builder) use ($traceId): void {
                    $likeTraceId = LikeEscape::contains($traceId);
                    $builder->where('trace_id', 'like', $likeTraceId)
                        // 生成列 activity_logs.context_request_id 与 JSON_EXTRACT 同值，
                        // 但可走索引；历史行同样被覆盖（生成列建列时已回填）。
                        ->orWhere('context_request_id', 'like', $likeTraceId);
                });
            } else {
                $query->where('context_request_id', 'like', LikeEscape::contains($traceId));
            }
        }

        if (! empty($filters['module'])) {
            $query->where('module', trim((string) $filters['module']));
        }

        if (! empty($filters['method'])) {
            $query->where('action', 'like', LikeEscape::escape(trim((string) $filters['method'])).' %');
        }

        if (! empty($filters['status'])) {
            $query->where('context_status', (int) $filters['status']);
        }

        if (! empty($filters['user_type'])) {
            $query->where('actor_type', trim((string) $filters['user_type']));
        }

        $this->applyDateFilter($query, $filters);

        $dbTotal = (int) (clone $query)->count();
        $fileRows = $this->filterApiFileRows(
            collect(ApiAccessLogFile::readRecent(
                entryLimit: self::API_CANDIDATE_MAX_ROWS,
            )),
            $filters,
        )->map(fn (array $item): array => $this->normalizeApiLogRow($this->mapApiFileRow($item)));

        // 文件窗口中的行会参与全局排序；把它们计入候选窗口，避免分页时
        // 只加载 page*perPage 条数据库行而在合并后留下不可见的空洞。两侧
        // 都受同一个硬上限约束，不能因深页或大文件窗口无限扩大查询结果。
        $candidateLimit = $this->apiCandidateLimit($dbTotal, $page, $perPage, $fileRows->count());
        $dbLogs = $candidateLimit > 0
            ? (clone $query)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($candidateLimit)
                ->get()
            : collect();
        $dbRows = $this->mapActivityLogRows($dbLogs)
            ->map(fn (array $item): array => $this->normalizeApiLogRow($item));

        // 新旧写入路径切换期间，同一请求可能同时存在 activity_logs 与
        // api-json 文件。request_id/trace_id 是跨来源的稳定关联键，展示和
        // total 都只保留一条，避免管理员看到重复访问事件。
        // 只依据已经载入候选窗口的数据库行去重。若重新从完整查询中按
        // request_id 扫描，可能命中一个落在候选窗口之外的旧行；此时文件行
        // 会被删掉，但对应数据库行又不会进入当前页，造成可见日志缺失，且
        // 还会为最多 10000 个文件 ID 构造超大的 IN 查询。
        $dbRequestIds = $dbRows
            ->flatMap(static function (array $row): array {
                $ids = [trim((string) ($row['request_id'] ?? ''))];
                $detail = is_array($row['detail'] ?? null) ? $row['detail'] : [];
                $ids[] = trim((string) ($detail['request_id'] ?? ''));

                return $ids;
            })
            ->filter()
            ->unique()
            ->flip();
        $fileOnlyRows = $fileRows->reject(
            static fn (array $row): bool => ($requestId = trim((string) ($row['request_id'] ?? ''))) !== ''
                && isset($dbRequestIds[$requestId]),
        )->values();
        $mergedRows = $this->sortApiLogRows($dbRows->merge($fileOnlyRows));
        $rows = $mergedRows->forPage($page, $perPage)->values();
        // 只从总数中扣除实际被丢弃的文件行。一个 request_id 可能对应多条
        // activity_logs（例如重试/错误镜像），不能把所有匹配数据库行都当成
        // 被去重，否则 total 会小于当前合并结果的真实行数。
        $total = max(0, $dbTotal + $fileOnlyRows->count());
        $logs = new LengthAwarePaginator($rows, $total, $perPage, $page);

        if (! $withSummary) {
            return $this->buildPaginatorPayload($logs);
        }

        $fileSignature = md5(json_encode(
            $fileOnlyRows->map(fn (array $row): array => [
                'id' => $row['id'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'status' => $row['status'] ?? null,
                'user_type' => $row['user_type'] ?? null,
            ])->values()->all(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        $summary = Cache::remember(
            $this->buildListSummaryCacheKey('api', $filters).':'.$dbTotal.':'.$fileSignature,
            now()->addSeconds(self::LIST_SUMMARY_CACHE_TTL_SECONDS),
            function () use ($query) {
                return (clone $query)
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("COALESCE(SUM(CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(context, '$.status')) AS UNSIGNED) >= 500 THEN 1 ELSE 0 END), 0) as errors")
                    ->selectRaw("COALESCE(SUM(CASE WHEN actor_type = 'admin' THEN 1 ELSE 0 END), 0) as admin_count")
                    ->first();
            }
        );

        return $this->buildPaginatorPayload($logs, [
            'total' => $total,
            'errors' => (int) ($summary?->errors ?? 0) + $fileOnlyRows->filter(
                static fn (array $row): bool => (int) ($row['status'] ?? 0) >= 500
            )->count(),
            'admin_count' => (int) ($summary?->admin_count ?? 0) + $fileOnlyRows->filter(
                static fn (array $row): bool => (string) ($row['user_type'] ?? '') === 'admin'
            )->count(),
            'file_window_count' => $fileOnlyRows->count(),
            'source' => $fileOnlyRows->isNotEmpty() ? 'activity_logs+api_json_window' : 'activity_logs',
        ]);
    }

    public function getTaskLogs(array $filters, int $page, int $perPage): array
    {
        if ($this->legacyScheduleRunLogsCanStandalone($filters)) {
            $logs = $this->buildScheduleRunTaskLogQuery($filters)
                ->orderByDesc('started_at')
                ->paginate($perPage, $this->scheduleRunLogListColumns(), 'page', $page);
            $logs->setCollection(
                $logs->getCollection()->map(fn (ScheduleRunLog $log): array => $this->mapScheduleRunTaskLogEntry($log))
            );

            return $this->buildPaginatorPayload($logs);
        }

        $entries = $this->buildTaskLogEntries($filters);

        $paginator = $this->paginateCollection($entries, $page, $perPage);

        return $this->buildPaginatorPayload($paginator);
    }

    public function getTaskLogsSummary(array $filters): array
    {
        return Cache::remember(
            $this->buildFileLogSummaryCacheKey('task', $filters),
            now()->addSeconds(self::FILE_LOG_SUMMARY_CACHE_TTL_SECONDS),
            function () use ($filters) {
                if ($this->legacyScheduleRunLogsCanStandalone($filters)) {
                    $summary = $this->buildScheduleRunTaskLogQuery($filters)
                        ?->selectRaw('COUNT(*) as total')
                        ->selectRaw('COUNT(DISTINCT task_name) as tasks')
                        ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('failed', 'error') THEN 1 ELSE 0 END), 0) as errors")
                        ->first();

                    return [
                        'total' => (int) ($summary?->total ?? 0),
                        'tasks' => (int) ($summary?->tasks ?? 0),
                        'errors' => (int) ($summary?->errors ?? 0),
                    ];
                }

                $entries = $this->buildTaskLogEntries($filters);

                return [
                    'total' => $entries->count(),
                    'tasks' => $entries->pluck('task_key')->filter()->unique()->count(),
                    'errors' => $entries->whereIn('level', ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])->count(),
                ];
            }
        );
    }

    public function getRuntimeLogs(array $filters, int $page, int $perPage): array
    {
        if (
            SchemaMetadataCache::hasTable('integration_plugin_runtime_logs')
            && ($this->hasStructuredRuntimeFilter($filters) || $this->readLaravelLogEntries() === [])
        ) {
            $logs = $this->buildPluginRuntimeLogQuery($filters)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage, $this->pluginRuntimeLogListColumns(), 'page', $page);
            $logs->setCollection(
                $logs->getCollection()->map(fn (IntegrationPluginRuntimeLog $log): array => $this->mapPluginRuntimeLogEntry($log))
            );

            return $this->buildPaginatorPayload($logs);
        }

        $entries = $this->buildRuntimeLogEntries($filters);

        $paginator = $this->paginateCollection($entries, $page, $perPage);

        return $this->buildPaginatorPayload($paginator);
    }

    /**
     * Locate a runtime entry originating from the Laravel log file.
     *
     * @return array<string, mixed>|null
     */
    public function findRuntimeFileLog(string $id): ?array
    {
        foreach ($this->readLaravelLogEntries() as $entry) {
            if ((string) ($entry['id'] ?? '') === $id && empty($entry['task_key'])) {
                return $entry;
            }
        }

        return null;
    }

    public function getRuntimeLogsSummary(array $filters): array
    {
        return Cache::remember(
            $this->buildFileLogSummaryCacheKey('runtime', $filters),
            now()->addSeconds(self::FILE_LOG_SUMMARY_CACHE_TTL_SECONDS),
            function () use ($filters) {
                if (
                    SchemaMetadataCache::hasTable('integration_plugin_runtime_logs')
                    && ($this->hasStructuredRuntimeFilter($filters) || $this->readLaravelLogEntries() === [])
                ) {
                    $summary = $this->buildPluginRuntimeLogQuery($filters)
                        ->selectRaw('COUNT(*) as total')
                        ->selectRaw("COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as errors")
                        ->first();
                    $total = (int) ($summary?->total ?? 0);
                    $errors = (int) ($summary?->errors ?? 0);

                    return [
                        'total' => $total,
                        'errors' => $errors,
                        'warnings' => 0,
                        'infos' => $total - $errors,
                    ];
                }

                $entries = $this->buildRuntimeLogEntries($filters);

                return [
                    'total' => $entries->count(),
                    'errors' => $entries->whereIn('level', ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])->count(),
                    'warnings' => $entries->where('level', 'WARNING')->count(),
                    'infos' => $entries->where('level', 'INFO')->count(),
                ];
            }
        );
    }

    public function getAdminLoginLogs(array $filters, int $page, int $perPage, bool $withSummary = true): array
    {
        // 新表 admin_login_logs 是登录审计唯一在线真源（成功与失败都落库）。
        // 升级前只有 activity_logs 的 admin.login 行：仅当新表为空时降级读取历史，
        // 保证未登录过的存量环境仍能看到既有登录记录。
        if (SchemaMetadataCache::hasTable('admin_login_logs')
            && AdminLoginLog::query()->exists()) {
            return $this->getAdminLoginLogsFromTable($filters, $page, $perPage, $withSummary);
        }

        // 仅当 activity_logs 表中从未有过任何 admin.login 记录（全新部署）时，才降级到
        // admin_users 快照。若表内有历史记录但当前过滤条件下为空，应返回空页而非降级，
        // 否则前端会看到与实际日志不一致的"最后一次登录"快照数据。
        $hasAnyLoginRecord = ActivityLog::query()
            ->where('module', 'auth')
            ->where('action', 'admin.login')
            ->exists();

        if (! $hasAnyLoginRecord) {
            return $this->getAdminLoginLogsFromSnapshot($filters, $page, $perPage, $withSummary);
        }

        $query = ActivityLog::query()
            ->where('module', 'auth')
            ->where('action', 'admin.login');

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('ip_address', 'like', LikeEscape::contains($keyword))
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(context, '$.admin_username')) like ?", [LikeEscape::contains($keyword)])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(context, '$.admin_nickname')) like ?", [LikeEscape::contains($keyword)])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(context, '$.role_name')) like ?", [LikeEscape::contains($keyword)]);
            });
        }

        $this->applyDateFilter($query, $filters);

        // 历史 activity_logs 只在登录成功时写入，不存在失败记录：
        // 筛选「失败」直接返回空页，避免把成功记录当成失败展示。
        $legacyStatusFilter = strtolower(trim((string) ($filters['status'] ?? '')));
        if (in_array($legacyStatusFilter, ['failed', '0'], true)) {
            return $this->buildPaginatorPayload(
                $this->emptyPaginator($perPage),
                $withSummary ? ['total' => 0, 'success' => 0, 'failed' => 0, 'mode' => 'activity_log'] : []
            );
        }

        $logs = (clone $query)->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        $rows = $this->mapActivityLogRows($logs->getCollection())->map(function (array $item) {
            $detail = is_array($item['detail'] ?? null) ? $item['detail'] : [];
            $item['admin_username'] = trim((string) ($detail['admin_username'] ?? $item['actor_name'] ?? ''));
            $item['admin_nickname'] = trim((string) ($detail['admin_nickname'] ?? ''));
            $item['role_name'] = trim((string) ($detail['role_name'] ?? $item['role_name'] ?? ''));
            // 历史行只有成功登录，统一标记 success，保证状态列与筛选一致。
            $item['status'] = 'success';
            $item['failure_reason'] = '';
            $item['browser'] = '';
            $item['operating_system'] = '';
            $item['source'] = 'activity_log';

            return $item;
        });
        $logs->setCollection($rows);

        return $this->buildPaginatorPayload(
            $logs,
            $withSummary
                ? [
                    'total' => $logs->total(),
                    'success' => $logs->total(),
                    'failed' => 0,
                    'mode' => 'activity_log',
                ]
                : []
        );
    }

    /**
     * 读取独立登录日志表（对齐 howstack-bos 登录审计模型）。
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function getAdminLoginLogsFromTable(array $filters, int $page, int $perPage, bool $withSummary = true): array
    {
        $query = AdminLoginLog::query();

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('username', 'like', LikeEscape::contains($keyword))
                    ->orWhere('nickname', 'like', LikeEscape::contains($keyword))
                    ->orWhere('role_name', 'like', LikeEscape::contains($keyword))
                    ->orWhere('client_ip', 'like', LikeEscape::contains($keyword))
                    ->orWhere('browser', 'like', LikeEscape::contains($keyword))
                    ->orWhere('operating_system', 'like', LikeEscape::contains($keyword));
            });
        }

        $statusFilter = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($statusFilter !== '') {
            $status = match ($statusFilter) {
                'success', '1' => AdminLoginLog::STATUS_SUCCESS,
                'failed', '0' => AdminLoginLog::STATUS_FAILED,
                default => null,
            };
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        if (! empty($filters['start_date'])) {
            $query->where('logged_at', '>=', Carbon::parse((string) $filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('logged_at', '<=', Carbon::parse((string) $filters['end_date'])->endOfDay());
        }

        $logs = (clone $query)
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $logs->setCollection($this->mapAdminLoginLogRows($logs->getCollection()));

        return $this->buildPaginatorPayload(
            $logs,
            $withSummary
                ? [
                    'total' => $logs->total(),
                    'success' => (clone $query)->where('status', AdminLoginLog::STATUS_SUCCESS)->count(),
                    'failed' => (clone $query)->where('status', AdminLoginLog::STATUS_FAILED)->count(),
                    'mode' => 'admin_login_log',
                ]
                : []
        );
    }

    /**
     * @param  Collection<int, AdminLoginLog>  $logs
     */
    private function mapAdminLoginLogRows(Collection $logs): Collection
    {
        return $logs->map(function (AdminLoginLog $log) {
            $username = trim((string) $log->username);
            $nickname = trim((string) $log->nickname);
            $succeeded = $log->status === AdminLoginLog::STATUS_SUCCESS;
            $reason = trim((string) $log->failure_reason);

            return [
                'id' => (int) $log->id,
                'user_id' => $log->admin_user_id,
                'user_type' => 'admin',
                'actor_name' => $nickname !== '' ? $nickname : $username,
                'admin_username' => $username,
                'admin_nickname' => $nickname,
                'role_name' => trim((string) $log->role_name),
                'ip_address' => trim((string) $log->client_ip),
                'status' => $succeeded ? 'success' : 'failed',
                'failure_reason' => $reason,
                'browser' => trim((string) $log->browser),
                'operating_system' => trim((string) $log->operating_system),
                'detail' => [
                    'source' => 'admin_login_logs',
                    'failure_reason' => $reason,
                    'browser' => (string) $log->browser,
                    'operating_system' => (string) $log->operating_system,
                ],
                'source' => 'admin_login_log',
                'created_at' => $this->dateText($log->getAttribute('logged_at')),
            ];
        });
    }

    private function dateText(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    private function getAdminLoginLogsFromSnapshot(array $filters, int $page, int $perPage, bool $withSummary = true): array
    {
        $query = AdminUser::query()
            ->with('role:id,name,label')
            ->whereNotNull('last_login_at')
            ->when(! empty($filters['keyword']), function ($builder) use ($filters) {
                $keyword = trim((string) $filters['keyword']);
                $builder->where(function ($q) use ($keyword) {
                    $q->where('username', 'like', LikeEscape::contains($keyword))
                        ->orWhere('nickname', 'like', LikeEscape::contains($keyword))
                        ->orWhere('last_login_ip', 'like', LikeEscape::contains($keyword));
                });
            });

        if (! empty($filters['start_date'])) {
            $query->where('last_login_at', '>=', Carbon::parse((string) $filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('last_login_at', '<=', Carbon::parse((string) $filters['end_date'])->endOfDay());
        }

        $admins = $query->orderByDesc('last_login_at')->paginate($perPage, ['*'], 'page', $page);
        $admins->setCollection($admins->getCollection()->map(function (AdminUser $admin) {
            return [
                'id' => (int) $admin->id,
                'user_id' => (int) $admin->id,
                'user_type' => 'admin',
                'actor_name' => trim((string) ($admin->nickname ?: $admin->username)),
                'admin_username' => trim((string) $admin->username),
                'admin_nickname' => trim((string) ($admin->nickname ?? '')),
                'role_name' => trim((string) ($admin->role?->label ?: $admin->role?->name ?: '')),
                'ip_address' => trim((string) ($admin->last_login_ip ?? '')),
                // 快照代表最近一次成功登录；无失败记录。
                'status' => 'success',
                'failure_reason' => '',
                'browser' => '',
                'operating_system' => '',
                'created_at' => $admin->last_login_at?->format('Y-m-d H:i:s'),
                'detail' => ['source' => 'admin_users.last_login_at'],
                'source' => 'admin_snapshot',
            ];
        }));

        return $this->buildPaginatorPayload(
            $admins,
            $withSummary
                ? [
                    'total' => $admins->total(),
                    'mode' => 'admin_snapshot',
                ]
                : []
        );
    }

    private function baseApiLogQuery()
    {
        return ActivityLog::query()->whereRaw('action REGEXP ?', [self::HTTP_ACTION_REGEXP]);
    }

    private function buildPaginatorPayload(LengthAwarePaginator $paginator, array $summary = []): array
    {
        return array_merge($paginator->toArray(), [
            'summary' => $summary,
        ]);
    }

    // 管理端短信/邮件日志查询返回完整 content、params 与收件人，不做脱敏（项目红线：管理员需真实审计信息）
    private function buildSmsLogQuery(array $filters): ?Builder
    {
        if (! SchemaMetadataCache::hasTable('message_logs')) {
            return null;
        }

        $tableName = 'message_logs';
        $query = MessageLog::query()
            ->where('channel', 'sms')
            ->selectRaw('id, recipient as phone, template_code, content, params_json, status, provider, request_id, error_msg, sent_at, created_at, updated_at, origin_type');
        $recipientColumn = 'recipient';

        $this->addOptionalSelectColumns($query, $tableName, ['plugin_id', 'driver_key', 'trace_id']);
        $this->applyPluginLogFilters($query, $tableName, $filters, 'driver_key');

        if (! empty($filters['phone'])) {
            $query->where($recipientColumn, 'like', LikeEscape::contains(trim((string) $filters['phone'])));
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword, $tableName) {
                $builder->where('template_code', 'like', LikeEscape::contains($keyword))
                    ->orWhere('request_id', 'like', LikeEscape::contains($keyword));

                $this->applyOptionalKeywordColumns($builder, $tableName, $keyword, ['driver_key', 'trace_id']);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function buildEmailLogQuery(array $filters): ?Builder
    {
        if (! SchemaMetadataCache::hasTable('message_logs')) {
            return null;
        }

        $tableName = 'message_logs';
        $query = MessageLog::query()
            ->where('channel', 'email')
            ->selectRaw('id, template_code, recipient as to_email, subject, content, status, error_msg, sent_at, created_at, updated_at');
        $recipientColumn = 'recipient';

        $this->addOptionalSelectColumns($query, $tableName, ['plugin_id', 'driver_key', 'trace_id']);
        $this->applyPluginLogFilters($query, $tableName, $filters, 'driver_key');

        if (! empty($filters['email'])) {
            $query->where($recipientColumn, 'like', LikeEscape::contains(trim((string) $filters['email'])));
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) ($filters['keyword'] ?? ''));
            $query->where(function ($builder) use ($keyword, $tableName) {
                $builder->where('template_code', 'like', LikeEscape::contains($keyword))
                    ->orWhere('subject', 'like', LikeEscape::contains($keyword))
                    ->orWhere('content', 'like', LikeEscape::contains($keyword));

                $this->applyOptionalKeywordColumns($builder, $tableName, $keyword, ['driver_key', 'trace_id']);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function buildNotificationSummaryQuery(string $channel, array $filters): ?Builder
    {
        if (! SchemaMetadataCache::hasTable('message_logs')) {
            return null;
        }

        $tableName = 'message_logs';
        $query = MessageLog::query()->where('channel', $channel);
        $recipientColumn = 'recipient';
        $hasRequestId = true;

        $this->applyPluginLogFilters($query, $tableName, $filters, 'driver_key');

        if ($channel === 'sms' && ! empty($filters['phone'])) {
            $query->where($recipientColumn, 'like', LikeEscape::contains(trim((string) $filters['phone'])));
        }

        if ($channel === 'email' && ! empty($filters['email'])) {
            $query->where($recipientColumn, 'like', LikeEscape::contains(trim((string) $filters['email'])));
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($channel, $hasRequestId, $keyword, $tableName) {
                if ($channel === 'sms') {
                    $builder->where('template_code', 'like', LikeEscape::contains($keyword));

                    if ($hasRequestId) {
                        $builder->orWhere('request_id', 'like', LikeEscape::contains($keyword));
                    }

                    $this->applyOptionalKeywordColumns($builder, $tableName, $keyword, ['driver_key', 'trace_id']);

                    return;
                }

                $builder->where('content', 'like', LikeEscape::contains($keyword))
                    ->orWhere('template_code', 'like', LikeEscape::contains($keyword))
                    ->orWhere('subject', 'like', LikeEscape::contains($keyword));

                if ($hasRequestId) {
                    $builder->orWhere('request_id', 'like', LikeEscape::contains($keyword));
                }

                $this->applyOptionalKeywordColumns($builder, $tableName, $keyword, ['driver_key', 'trace_id']);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function buildListSummaryCacheKey(string $type, array $filters): string
    {
        ksort($filters);

        return 'admin_logs:summary:'.$type.':'.md5(json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addOptionalSelectColumns(Builder $query, string $tableName, array $columns): void
    {
        foreach ($columns as $column) {
            if ($this->tableHasColumn($tableName, $column)) {
                $query->addSelect($column);
            }
        }
    }

    private function applyPluginLogFilters(Builder $query, string $tableName, array $filters, ?string $businessKeyColumn = null): void
    {
        if (! empty($filters['plugin_id']) && $this->tableHasColumn($tableName, 'plugin_id')) {
            $query->where('plugin_id', (int) $filters['plugin_id']);
        }

        if ($businessKeyColumn !== null && ! empty($filters[$businessKeyColumn]) && $this->tableHasColumn($tableName, $businessKeyColumn)) {
            $query->where($businessKeyColumn, trim((string) $filters[$businessKeyColumn]));
        }

        if (! empty($filters['trace_id']) && $this->tableHasColumn($tableName, 'trace_id')) {
            $query->where('trace_id', 'like', LikeEscape::contains(trim((string) $filters['trace_id'])));
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function applyOptionalKeywordColumns(Builder $query, string $tableName, string $keyword, array $columns): void
    {
        foreach ($columns as $column) {
            if ($this->tableHasColumn($tableName, $column)) {
                $query->orWhere($column, 'like', LikeEscape::contains($keyword));
            }
        }
    }

    private function tableHasColumn(string $tableName, string $column): bool
    {
        return SchemaMetadataCache::hasColumn($tableName, $column);
    }

    private function applyHttpActionFilter(Builder $query): void
    {
        $query->where(function (Builder $builder): void {
            foreach (self::HTTP_ACTION_PREFIXES as $prefix) {
                $builder->orWhere('action', 'like', $prefix.'%');
            }
        });
    }

    private function normalizeNotificationParams(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function buildTaskLogEntries(array $filters): Collection
    {
        $scheduleEntries = $this->buildScheduleRunTaskLogEntries($filters);

        // V9-02 收敛（2026-10-10）：schedule_run_logs 已停写（见 ScheduleRunLogService::record）。
        // 存量行与 activity_logs(module=cron) 审计镜像在历史窗口内是同次执行的重复记录
        // （镜像写入位于 run_logs 之后，最多相差数秒），可能只写了一半。
        // 因此以「最后一条存量行 + 60 秒」为冻结边界：
        //   - 边界前的镜像行一律以存量行为准，从列表中剔除，避免任务重复展示；
        //   - 边界后的镜像行是本表停写后的真实新执行，必须纳入。
        // 60 秒容忍窗口用于吸收「先写 run_logs、后写镜像」的秒级写入偏差。
        $freezeBoundary = $this->scheduleRunLogFreezeBoundary();
        $cronActivityEntries = $this->buildCronActivityTaskLogEntries($filters)
            ->filter(function (array $entry) use ($freezeBoundary): bool {
                if ($freezeBoundary === null) {
                    return true;
                }

                $entryTime = strtotime((string) ($entry['time'] ?? '')) ?: 0;

                return $entryTime > $freezeBoundary->copy()->addSeconds(60)->getTimestamp();
            });

        // 三源条目均为普通数组；scheduleEntries 在无存量行时是空 Eloquent 集合
        // （Eloquent\Collection::map 对空集合不转基础集合），直接 merge 会对数组条目
        // 调用 getKey() 致命，必须统一转基础集合后再合并。
        return collect($scheduleEntries)
            ->merge(collect($cronActivityEntries))
            ->merge(collect($this->buildFileTaskLogEntries($filters)))
            ->sortByDesc(fn (array $item) => (string) ($item['time'] ?? $item['created_at'] ?? ''))
            ->values();
    }

    /**
     * V9-02：存量 schedule_run_logs 能否独立代表任务日志（走直连分页快路径）。
     *
     * 仅当同时满足：存在存量行、过滤窗口内无文件日志条目、且全表无晚于冻结边界
     * 的审计镜像行。任一不满足即回退多源合并（存量行 + 冻结后镜像 + 文件日志）。
     */
    private function legacyScheduleRunLogsCanStandalone(array $filters): bool
    {
        $boundary = $this->scheduleRunLogFreezeBoundary();
        if ($boundary === null) {
            return false;
        }

        if ($this->buildFileTaskLogEntries($filters)->isNotEmpty()) {
            return false;
        }

        return ! $this->hasCronMirrorAfterFreeze($boundary);
    }

    /**
     * 冻结边界 = 最后一条存量 schedule_run_logs.created_at；表缺失或为空时返回 null。
     * 请求内记忆化，避免同一请求多次 MAX 查询。
     */
    private function scheduleRunLogFreezeBoundary(): ?Carbon
    {
        if ($this->scheduleRunLogFreezeBoundaryMemo !== false) {
            return $this->scheduleRunLogFreezeBoundaryMemo;
        }

        if (! SchemaMetadataCache::hasTable('schedule_run_logs')) {
            return $this->scheduleRunLogFreezeBoundaryMemo = null;
        }

        $latest = ScheduleRunLog::query()->max('created_at');

        return $this->scheduleRunLogFreezeBoundaryMemo = $latest !== null ? Carbon::parse((string) $latest) : null;
    }

    /**
     * V9-02：是否存在晚于冻结边界的审计镜像行（即停写后的新执行）。
     */
    private function hasCronMirrorAfterFreeze(Carbon $boundary): bool
    {
        if (! SchemaMetadataCache::hasTable('activity_logs')) {
            return false;
        }

        return ActivityLog::query()
            ->where('module', 'cron')
            ->where('created_at', '>', $boundary->copy()->addSeconds(60))
            ->exists();
    }

    private function buildFileTaskLogEntries(array $filters): Collection
    {
        return collect($this->readLaravelLogEntries())
            ->filter(fn (array $item) => ! empty($item['task_key']))
            ->filter(function (array $item) use ($filters) {
                if (! empty($filters['task_key']) && (string) $item['task_key'] !== trim((string) $filters['task_key'])) {
                    return false;
                }

                if (! empty($filters['level']) && strtoupper((string) $item['level']) !== strtoupper((string) $filters['level'])) {
                    return false;
                }

                if (! empty($filters['status']) && $this->statusFromLogLevel((string) ($item['level'] ?? '')) !== trim((string) $filters['status'])) {
                    return false;
                }

                if (! empty($filters['keyword'])) {
                    $keyword = trim((string) $filters['keyword']);
                    if (! str_contains((string) $item['message'], $keyword) && ! str_contains((string) $item['task_title'], $keyword)) {
                        return false;
                    }
                }

                return $this->matchLogDateRange((string) $item['time'], $filters);
            })
            ->map(function (array $item) {
                $item['source'] = 'laravel_log';
                $item['status'] = $this->statusFromLogLevel((string) ($item['level'] ?? ''));
                $item['summary'] = null;
                $item['error_msg'] = in_array(strtoupper((string) ($item['level'] ?? '')), ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true)
                    ? ($item['raw'] ?? $item['message'] ?? null)
                    : null;

                return $item;
            })
            ->values();
    }

    private function buildScheduleRunTaskLogEntries(array $filters): Collection
    {
        $query = $this->buildScheduleRunTaskLogQuery($filters);
        if ($query === null) {
            return collect();
        }

        return $query
            ->orderByDesc('started_at')
            ->limit(1000)
            ->get($this->scheduleRunLogListColumns())
            ->map(fn (ScheduleRunLog $log): array => $this->mapScheduleRunTaskLogEntry($log))
            ->values();
    }

    private function buildScheduleRunTaskLogQuery(array $filters): ?Builder
    {
        if (! SchemaMetadataCache::hasTable('schedule_run_logs')) {
            return null;
        }

        $query = ScheduleRunLog::query();

        if (! empty($filters['task_key'])) {
            $query->where('task_name', trim((string) $filters['task_key']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', trim((string) $filters['status']));
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('task_name', 'like', LikeEscape::contains($keyword))
                    ->orWhere('error_msg', 'like', LikeEscape::contains($keyword));
            });
        }

        if (! empty($filters['start_date'])) {
            $query->where('started_at', '>=', Carbon::parse((string) $filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('started_at', '<=', Carbon::parse((string) $filters['end_date'])->endOfDay());
        }

        return $query;
    }

    /** @return list<string> */
    private function scheduleRunLogListColumns(): array
    {
        return [
            'id',
            'task_name',
            'status',
            'duration_ms',
            'error_msg',
            'started_at',
            'finished_at',
            'created_at',
        ];
    }

    /** @return array<string, mixed> */
    private function mapScheduleRunTaskLogEntry(ScheduleRunLog $log): array
    {
        $taskKey = trim((string) $log->task_name);
        $errorMessage = trim((string) ($log->error_msg ?? ''));
        $message = $errorMessage !== ''
            ? $errorMessage
            : $this->taskSummaryText(null, trim((string) $log->status));

        return [
            'id' => 'schedule-'.$log->id,
            'source' => 'schedule_run_logs',
            'time' => $log->started_at?->format('Y-m-d H:i:s') ?: $log->created_at?->format('Y-m-d H:i:s'),
            'started_at' => $log->started_at?->format('Y-m-d H:i:s'),
            'finished_at' => $log->finished_at?->format('Y-m-d H:i:s'),
            'task_key' => $taskKey,
            'task_name' => $taskKey,
            'task_title' => $this->taskTitle($taskKey),
            'status' => trim((string) $log->status),
            'level' => $this->levelFromTaskStatus((string) $log->status),
            'duration_ms' => (int) $log->duration_ms,
            'summary' => null,
            'error_msg' => $errorMessage,
            'message' => $message,
            'raw' => $errorMessage,
        ];
    }

    private function buildCronActivityTaskLogEntries(array $filters): Collection
    {
        if (! SchemaMetadataCache::hasTable('activity_logs')) {
            return collect();
        }

        $query = ActivityLog::query()->where('module', 'cron');

        if (! empty($filters['task_key'])) {
            $taskKey = trim((string) $filters['task_key']);
            $query->where(function ($builder) use ($taskKey) {
                $builder->where('description', 'like', LikeEscape::contains($taskKey))
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(context, '$.task_key')) = ?", [$taskKey]);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('action', trim((string) $filters['status']));
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('description', 'like', LikeEscape::contains($keyword))
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(context, '$.task_key')) like ?", [LikeEscape::contains($keyword)])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(context, '$.error_message')) like ?", [LikeEscape::contains($keyword)]);
            });
        }

        $this->applyDateFilter($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->map(function (ActivityLog $log) {
                $context = is_array($log->context) ? $log->context : [];
                $taskKey = trim((string) ($context['task_key'] ?? ''));
                $status = trim((string) $log->action);

                return [
                    'id' => 'activity-'.$log->id,
                    'source' => 'activity_logs',
                    'time' => $log->created_at?->format('Y-m-d H:i:s'),
                    'started_at' => null,
                    'finished_at' => $log->created_at?->format('Y-m-d H:i:s'),
                    'task_key' => $taskKey,
                    'task_name' => $taskKey ?: trim((string) $log->subject_type),
                    'task_title' => $taskKey !== '' ? $this->taskTitle($taskKey) : trim((string) $log->subject_type),
                    'status' => $status,
                    'level' => $this->levelFromTaskStatus($status),
                    'duration_ms' => isset($context['duration_ms']) ? (int) $context['duration_ms'] : null,
                    'summary' => $context['summary'] ?? null,
                    'error_msg' => $context['error_message'] ?? null,
                    'message' => trim((string) $log->description),
                    'raw' => trim((string) $log->description),
                ];
            })
            ->values();
    }

    private function buildRuntimeLogEntries(array $filters): Collection
    {
        $fileEntries = $this->hasStructuredRuntimeFilter($filters)
            ? collect()
            : collect($this->readLaravelLogEntries())
                ->filter(fn (array $item) => empty($item['task_key']));

        return $fileEntries
            ->merge($this->buildPluginRuntimeLogEntries($filters))
            ->filter(function (array $item) use ($filters) {
                if (! empty($filters['level']) && strtoupper((string) $item['level']) !== strtoupper((string) $filters['level'])) {
                    return false;
                }

                if (! empty($filters['keyword'])) {
                    $keyword = trim((string) $filters['keyword']);
                    if (! str_contains((string) $item['message'], $keyword)) {
                        return false;
                    }
                }

                return $this->matchLogDateRange((string) $item['time'], $filters);
            })
            ->sortByDesc(fn (array $item) => strtotime((string) ($item['time'] ?? '')) ?: 0)
            ->values();
    }

    private function buildPluginRuntimeLogEntries(array $filters): Collection
    {
        if (! SchemaMetadataCache::hasTable('integration_plugin_runtime_logs')) {
            return collect();
        }

        return $this->buildPluginRuntimeLogQuery($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(1600)
            ->get($this->pluginRuntimeLogListColumns())
            ->map(fn (IntegrationPluginRuntimeLog $log): array => $this->mapPluginRuntimeLogEntry($log));
    }

    private function buildPluginRuntimeLogQuery(array $filters): Builder
    {
        $query = IntegrationPluginRuntimeLog::query();

        if (! empty($filters['plugin_id'])) {
            $query->where('plugin_id', (int) $filters['plugin_id']);
        }

        if (! empty($filters['gateway_key'])) {
            $query->where('plugin_key', trim((string) $filters['gateway_key']));
        }

        if (! empty($filters['driver_key'])) {
            $query->where('plugin_key', trim((string) $filters['driver_key']));
        }

        if (! empty($filters['trace_id'])) {
            $query->where('trace_id', 'like', LikeEscape::contains(trim((string) $filters['trace_id'])));
        }

        if (! empty($filters['status'])) {
            $query->where('status', trim((string) $filters['status']));
        }

        if (! empty($filters['level'])) {
            $level = strtoupper(trim((string) $filters['level']));
            if ($level === 'ERROR') {
                $query->where('status', 'failed');
            } elseif ($level === 'INFO') {
                $query->where('status', '<>', 'failed');
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('plugin_key', 'like', LikeEscape::contains($keyword))
                    ->orWhere('slug', 'like', LikeEscape::contains($keyword))
                    ->orWhere('action', 'like', LikeEscape::contains($keyword))
                    ->orWhere('trace_id', 'like', LikeEscape::contains($keyword))
                    ->orWhere('error_message', 'like', LikeEscape::contains($keyword));
            });
        }

        $this->applyDateFilter($query, $filters);

        return $query;
    }

    /** @return list<string> */
    private function pluginRuntimeLogListColumns(): array
    {
        return [
            'id',
            'trace_id',
            'domain',
            'plugin_id',
            'plugin_key',
            'slug',
            'action',
            'status',
            'duration_ms',
            'error_code',
            'error_message',
            'created_at',
        ];
    }

    private function mapPluginRuntimeLogEntry(IntegrationPluginRuntimeLog $log): array
    {
        $status = trim((string) ($log->status ?? ''));
        $pluginLabel = trim((string) ($log->plugin_key ?: $log->slug));
        $action = trim((string) ($log->action ?? ''));
        $error = trim((string) ($log->error_message ?? ''));
        $message = trim($pluginLabel.' '.$action);

        if ($error !== '') {
            $message = trim($message.' - '.$error);
        }

        return [
            'id' => 'plugin-runtime-'.$log->id,
            'source' => 'integration_plugin_runtime_logs',
            'time' => $log->created_at?->format('Y-m-d H:i:s'),
            'level' => strtolower($status) === 'failed' ? 'ERROR' : 'INFO',
            'message' => $message !== '' ? $message : 'plugin runtime',
            'raw' => $message,
            'status' => $status,
            'trace_id' => trim((string) ($log->trace_id ?? '')),
            'domain' => trim((string) ($log->domain ?? '')),
            'plugin_id' => $log->plugin_id,
            'plugin_key' => trim((string) ($log->plugin_key ?? '')),
            'slug' => trim((string) ($log->slug ?? '')),
            'action' => $action,
            'duration_ms' => $log->duration_ms,
            'error_msg' => $error,
        ];
    }

    private function hasStructuredRuntimeFilter(array $filters): bool
    {
        foreach (['plugin_id', 'gateway_key', 'driver_key', 'trace_id'] as $key) {
            if (! empty($filters[$key])) {
                return true;
            }
        }

        return false;
    }

    private function levelFromTaskStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success' => 'SUCCESS',
            'skipped' => 'NOTICE',
            'failed' => 'ERROR',
            default => 'INFO',
        };
    }

    private function statusFromLogLevel(string $level): string
    {
        return in_array(strtoupper($level), ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true)
            ? 'failed'
            : 'success';
    }

    private function taskSummaryText(?array $summary, string $status): string
    {
        if ($summary === null || $summary === []) {
            return $status === '' ? '任务执行完成' : '任务状态：'.$status;
        }

        $json = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '任务执行完成' : $json;
    }

    private function buildFileLogSummaryCacheKey(string $type, array $filters): string
    {
        $logPath = $this->resolveActiveLaravelLogPath();
        $fileModifiedAt = is_file($logPath) ? (int) filemtime($logPath) : 0;
        $fileSize = is_file($logPath) ? (int) filesize($logPath) : 0;
        ksort($filters);

        return 'admin_logs:file_summary:'.$type.':'.md5($logPath).':'.$fileModifiedAt.':'.$fileSize.':'.md5(
            json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function paginateCollection(Collection $items, int $page, int $perPage): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $total = $items->count();
        $data = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($data, $total, $perPage, $page);
    }

    /**
     * 把 activity_logs 行映射为 api / admin-logins channel 的既有列表行结构
     * （id/user_id/user_type/actor_name/role_name/action/module/target_id/detail/ip_address/created_at），
     * 字段语义与旧 mapOperationLogs 保持一致，前端响应结构不变。
     */
    private function mapActivityLogRows(Collection $logs): Collection
    {
        $privacy = AdminPrivacy::current();
        $adminIds = $logs->where('actor_type', 'admin')->pluck('actor_id')->filter()->unique()->values();
        $clientIds = $logs->where('actor_type', 'client')->pluck('actor_id')->filter()->unique()->values();

        $admins = $adminIds->isEmpty()
            ? collect()
            : AdminUser::query()->whereIn('id', $adminIds)->get(['id', 'username', 'nickname', 'role_id'])->keyBy('id');

        $roleIds = $admins->pluck('role_id')->filter()->unique()->values();
        $roles = $roleIds->isEmpty()
            ? collect()
            : Role::query()->whereIn('id', $roleIds)->get(['id', 'name', 'label'])->keyBy('id');

        $clients = $clientIds->isEmpty()
            ? collect()
            : User::query()
                ->withReadAggregates()
                ->whereIn('id', $clientIds)
                ->get([
                    'id',
                    'email',
                    'phone',
                    'nickname',
                    'real_name',
                    'verification_status',
                    'is_verified',
                ])
                ->keyBy('id');

        return $logs->map(function (ActivityLog $log) use ($admins, $roles, $clients, $privacy) {
            $detail = is_array($log->context) ? $log->context : [];
            $actorName = trim((string) ($log->actor_name ?? ''));
            $roleName = '';

            if ($log->actor_type === 'admin') {
                $admin = $admins->get($log->actor_id);
                if ($admin) {
                    $actorName = trim((string) ($admin->nickname ?: $admin->username));
                    $role = $roles->get($admin->role_id);
                    $roleName = trim((string) ($role?->label ?: $role?->name ?: ''));
                }
            } elseif ($log->actor_type === 'client') {
                $client = $clients->get($log->actor_id);
                if ($client instanceof User) {
                    $actorName = $privacy->displayName($client->display_name, $client->email, $client->phone, $client->real_name);
                }
            }

            return [
                'id' => (int) $log->id,
                'user_id' => $log->actor_id !== null ? (int) $log->actor_id : null,
                'user_type' => trim((string) ($log->actor_type ?? '')),
                'actor_name' => $actorName,
                'role_name' => $roleName,
                'action' => trim((string) $log->action),
                'module' => trim((string) ($log->module ?? '')),
                'target_id' => $log->subject_id !== null ? (int) $log->subject_id : null,
                'detail' => $privacy->payload($detail),
                'ip_address' => trim((string) ($log->ip_address ?? '')),
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                'request_id' => trim((string) ($log->trace_id ?? '')),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterApiFileRows(Collection $rows, array $filters): Collection
    {
        return $rows->filter(function (array $row) use ($filters): bool {
            $createdAt = trim((string) ($row['created_at'] ?? ''));
            if ($createdAt === '') {
                return false;
            }

            try {
                Carbon::parse($createdAt);
            } catch (\Throwable) {
                // 无法确定时间的文件行不能参与日期筛选和全局排序，避免
                // 把损坏/不完整日志伪装成有效结果返回。
                return false;
            }

            if (! empty($filters['keyword'])) {
                $keyword = trim((string) $filters['keyword']);
                $haystack = implode(' ', [
                    (string) ($row['action'] ?? ''),
                    (string) ($row['module'] ?? ''),
                    (string) ($row['ip_address'] ?? ''),
                    (string) ($row['request_id'] ?? ''),
                ]);
                if (stripos($haystack, $keyword) === false) {
                    return false;
                }
            }

            if (! empty($filters['module']) && (string) ($row['module'] ?? '') !== trim((string) $filters['module'])) {
                return false;
            }

            if (! empty($filters['method']) && ! str_starts_with((string) ($row['action'] ?? ''), trim((string) $filters['method']).' ')) {
                return false;
            }

            if (! empty($filters['status']) && (int) ($row['status'] ?? 0) !== (int) $filters['status']) {
                return false;
            }

            if (! empty($filters['user_type']) && (string) ($row['user_type'] ?? '') !== trim((string) $filters['user_type'])) {
                return false;
            }

            if (! empty($filters['ip_address']) && ! str_contains(
                (string) ($row['ip_address'] ?? ''),
                trim((string) $filters['ip_address'])
            )) {
                return false;
            }

            if (! empty($filters['trace_id']) && ! str_contains(
                (string) ($row['request_id'] ?? ''),
                trim((string) $filters['trace_id'])
            )) {
                return false;
            }

            return $this->matchLogDateRange((string) ($row['created_at'] ?? ''), $filters);
        })->values();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapApiFileRow(array $item): array
    {
        $privacy = AdminPrivacy::current();
        $detail = is_array($item['detail'] ?? null) ? $item['detail'] : [];

        return [
            'id' => (string) ($item['id'] ?? ''),
            'source' => 'api_json',
            'user_id' => $item['user_id'] ?? null,
            'user_type' => trim((string) ($item['user_type'] ?? 'guest')) ?: 'guest',
            'actor_name' => trim((string) ($item['actor_name'] ?? '')),
            'role_name' => trim((string) ($item['role_name'] ?? '')),
            'action' => trim((string) ($item['action'] ?? '')),
            'module' => trim((string) ($item['module'] ?? '')),
            'target_id' => $item['target_id'] ?? null,
            'detail' => $privacy->payload($detail),
            'ip_address' => trim((string) ($item['ip_address'] ?? '')),
            'created_at' => $item['created_at'] ?? null,
        ];
    }

    /**
     * 将数据库与文件来源统一为 API 列表行结构。
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeApiLogRow(array $item): array
    {
        [$method, $path] = $this->splitHttpAction((string) ($item['action'] ?? ''));
        $detail = is_array($item['detail'] ?? null) ? $item['detail'] : [];

        $item['method'] = $method;
        $item['path'] = $path;
        $item['status'] = isset($detail['status']) ? (int) $detail['status'] : null;
        $item['request_id'] = trim((string) ($detail['request_id'] ?? $item['request_id'] ?? ''));
        $item['params'] = $detail['params'] ?? [];
        $item['user_agent'] = trim((string) ($detail['user_agent'] ?? ''));

        return $item;
    }

    private function apiCandidateLimit(int $dbTotal, int $page, int $perPage, int $fileRowCount = 0): int
    {
        if ($dbTotal <= 0) {
            return 0;
        }

        $safePageLimit = intdiv(PHP_INT_MAX, $perPage);
        $pageRows = $page > $safePageLimit
            ? self::API_CANDIDATE_MAX_ROWS
            : $page * $perPage;
        $fileRows = max(0, min($fileRowCount, self::API_CANDIDATE_MAX_ROWS));
        $requested = $pageRows > self::API_CANDIDATE_MAX_ROWS - $fileRows
            ? self::API_CANDIDATE_MAX_ROWS
            : $pageRows + $fileRows;

        return min($dbTotal, $requested, self::API_CANDIDATE_MAX_ROWS);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortApiLogRows(Collection $rows): Collection
    {
        return $rows->sort(static function (array $left, array $right): int {
            $timeOrder = strcmp(
                (string) ($right['created_at'] ?? ''),
                (string) ($left['created_at'] ?? '')
            );

            if ($timeOrder !== 0) {
                return $timeOrder;
            }

            $leftId = trim((string) ($left['id'] ?? ''));
            $rightId = trim((string) ($right['id'] ?? ''));

            // 数据库 ID 是整数，不能用字典序比较（"9" 会排在 "10" 前）。
            // 采用去前导零后的长度/字典序比较，避免把 unsigned BIGINT 强转
            // 为 PHP int 时发生溢出；文件 ID 保留字符串比较。
            if (ctype_digit($leftId) && ctype_digit($rightId)) {
                $leftNumeric = ltrim($leftId, '0') ?: '0';
                $rightNumeric = ltrim($rightId, '0') ?: '0';

                $lengthOrder = strlen($rightNumeric) <=> strlen($leftNumeric);
                if ($lengthOrder !== 0) {
                    return $lengthOrder;
                }

                return strcmp($rightNumeric, $leftNumeric);
            }

            return strcmp($rightId, $leftId);
        })->values();
    }

    private function applyDateFilter($query, array $filters): void
    {
        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse((string) $filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse((string) $filters['end_date'])->endOfDay());
        }
    }

    private function splitHttpAction(string $action): array
    {
        if (! preg_match('/^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\s+(.+)$/', trim($action), $matches)) {
            return ['', $action];
        }

        return [trim((string) $matches[1]), trim((string) $matches[2])];
    }

    private function readLaravelLogEntries(int $lineLimit = 1600): array
    {
        $logPath = $this->resolveActiveLaravelLogPath();

        if (! is_file($logPath)) {
            return [];
        }

        $fileModifiedAt = (int) filemtime($logPath);
        $fileSize = (int) filesize($logPath);
        $cacheKey = 'admin_logs:file_scan:'.md5($logPath).":{$lineLimit}:{$fileModifiedAt}:{$fileSize}";

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::FILE_LOG_CACHE_TTL_SECONDS),
            function () use ($logPath, $lineLimit) {
                return collect($this->readLastLines($logPath, $lineLimit))
                    ->reverse()
                    ->map(fn (array $item) => $this->parseLaravelLogLine((string) $item['content'], (int) $item['line_no']))
                    ->filter()
                    ->values()
                    ->all();
            }
        );
    }

    /**
     * 解析当前生效的 Laravel 文件日志路径。
     *
     * 生产默认 LOG_CHANNEL=daily，Monolog 的 RotatingFileHandler 实际写入
     * laravel-YYYY-MM-DD.log；旧实现硬编码 laravel.log，导致日志页的文件兜底源
     * 在生产环境读不到当天日志。这里按 logging 配置展开 daily/stack channel，
     * 取最近写入的现存文件；均不存在时回退单文件路径。
     */
    private function resolveActiveLaravelLogPath(): string
    {
        $existing = array_values(array_filter(
            $this->configuredLaravelLogPaths(),
            static fn (string $path): bool => is_file($path),
        ));

        if ($existing !== []) {
            usort(
                $existing,
                static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a),
            );

            return $existing[0];
        }

        return storage_path('logs/laravel.log');
    }

    /**
     * @return list<string>
     */
    private function configuredLaravelLogPaths(): array
    {
        $channels = (array) config('logging.channels', []);
        $paths = [];

        $resolve = function (string $name, int $depth) use (&$resolve, $channels, &$paths): void {
            if ($depth > 3) {
                return;
            }

            $channel = (array) ($channels[$name] ?? []);
            $driver = (string) ($channel['driver'] ?? '');

            if ($driver === 'stack') {
                foreach ((array) ($channel['channels'] ?? []) as $nested) {
                    if (is_string($nested)) {
                        $resolve($nested, $depth + 1);
                    }
                }

                return;
            }

            $path = $channel['path'] ?? null;
            if (! is_string($path) || $path === '') {
                return;
            }

            if ($driver === 'daily') {
                $paths[] = (string) preg_replace('/\.log$/', '-'.date('Y-m-d').'.log', $path);
                foreach (glob((string) preg_replace('/\.log$/', '-*.log', $path)) ?: [] as $file) {
                    $paths[] = $file;
                }
            }

            $paths[] = $path;
        };

        $resolve((string) config('logging.default', 'single'), 0);

        return array_values(array_unique($paths));
    }

    /**
     * 返回文件尾部窗口内最后 $limit 行非空行，元素为 `['line_no' => 物理行号, 'content' => 行内容]`。
     *
     * 物理行号用于生成日志 ID，保证文件持续追加时既有日志行的 ID 不因窗口滑动而漂移，
     * 否则列表返回的 ID 在详情请求时可能已无法复现（行序号随追加变化）。
     *
     * @return list<array{line_no: int, content: string}>
     */
    private function readLastLines(string $path, int $limit): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $startLine = max($lastLine - $limit, 0);
        $lines = [];

        $file->seek($startLine);

        while (! $file->eof()) {
            $line = rtrim((string) $file->current(), "\r\n");
            $lineNo = (int) $file->key();
            if ($line !== '') {
                $lines[] = ['line_no' => $lineNo, 'content' => $line];
            }
            $file->next();
        }

        return $lines;
    }

    private function parseLaravelLogLine(string $line, int $lineNo): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (! preg_match('/^\[(?<time>[^\]]+)\]\s+\w+\.(?<level>[A-Z]+):\s+(?<message>.+)$/u', $line, $matches)) {
            return null;
        }

        $message = trim((string) $matches['message']);
        $taskKey = $this->resolveTaskKeyFromMessage($message);

        // 文件条目本身没有结构化列；行尾 JSON context 常带 provider_key/plugin_key/domain，
        // 提取出来回填列表的「插件 key / 来源」列，避免把适配器日志误读为空来源
        $context = $this->extractLogContext((string) $matches['message']);
        $logSource = trim((string) ($context['provider_key'] ?? $context['plugin_key'] ?? $context['slug'] ?? ''));

        return [
            'id' => md5($line.'|'.$lineNo),
            'time' => trim((string) $matches['time']),
            'level' => trim((string) $matches['level']),
            'message' => preg_replace('/\s+\{.*$/u', '', $message) ?: $message,
            'raw' => $message,
            'task_key' => $taskKey,
            'task_title' => $taskKey ? $this->taskTitle($taskKey) : '',
            'plugin_key' => $logSource,
            'log_origin' => isset($context['provider_key']) ? 'provider' : (isset($context['domain']) ? 'plugin' : ($logSource !== '' ? 'component' : '')),
        ];
    }

    /**
     * 解析 Laravel 日志行尾的 JSON context（`message {"k":"v"}`）。
     *
     * @return array<string, mixed>
     */
    private function extractLogContext(string $message): array
    {
        if (! preg_match('/\s+(\{.*\})\s*$/s', $message, $matches)) {
            return [];
        }

        $decoded = json_decode($matches[1], true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveTaskKeyFromMessage(string $message): ?string
    {
        foreach ($this->taskKeywordIndex() as $taskKey => $keywords) {
            foreach ((array) $keywords as $keyword) {
                if ($keyword !== '' && str_contains($message, $keyword)) {
                    return $taskKey;
                }
            }
        }

        return null;
    }

    /**
     * 核心静态任务元数据与心跳注册表动态任务标题的兜底链；
     * 注册表不可用时回退静态表，静态表同样未收录时退回原始 task key。
     */
    private function taskTitle(string $taskKey): string
    {
        $entry = self::TASK_META[$taskKey] ?? null;
        if (is_array($entry)) {
            return trim((string) $entry['title']);
        }

        try {
            $title = trim((string) app(HeartbeatTaskRegistry::class)->get($taskKey)->title());
            if ($title !== '') {
                return $title;
            }
        } catch (\Throwable) {
            // 心跳注册表不可用时退到下一级兜底
        }

        return $taskKey;
    }

    /**
     * task key → 日志关键词索引：静态核心条目优先，插件心跳任务以
     * `[title(), key()]` 动态补充，保证文件日志反查不依赖硬编码清单。
     *
     * @return array<string, list<string>>
     */
    private function taskKeywordIndex(): array
    {
        $index = [];

        foreach (self::TASK_META as $key => $meta) {
            $index[$key] = array_values(array_filter(
                array_map('strval', $meta['log_keywords']),
                static fn (string $keyword): bool => trim($keyword) !== '',
            ));
        }

        try {
            foreach (app(HeartbeatTaskRegistry::class)->enabledTasks() as $task) {
                $key = trim((string) $task->key());
                if ($key === '' || isset($index[$key])) {
                    continue;
                }
                $index[$key] = array_values(array_filter([
                    trim((string) $task->title()),
                    $key,
                ], static fn (string $keyword): bool => $keyword !== ''));
            }
        } catch (\Throwable) {
            // 心跳注册表不可用时仅保留静态核心条目
        }

        return $index;
    }

    private function matchLogDateRange(string $time, array $filters): bool
    {
        try {
            $date = Carbon::parse($time);
        } catch (\Throwable) {
            return true;
        }

        if (! empty($filters['start_date']) && $date->lt(Carbon::parse((string) $filters['start_date'])->startOfDay())) {
            return false;
        }

        if (! empty($filters['end_date']) && $date->gt(Carbon::parse((string) $filters['end_date'])->endOfDay())) {
            return false;
        }

        return true;
    }

    public function getGatewayLogs(array $filters, int $page, int $perPage, bool $withSummary = true): array
    {
        if (! SchemaMetadataCache::hasTable('gateway_logs')) {
            return $this->buildPaginatorPayload($this->emptyPaginator($perPage));
        }

        $query = GatewayLog::query();
        $this->applyPluginLogFilters($query, 'gateway_logs', $filters, 'gateway_key');

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('out_trade_no', 'like', LikeEscape::contains($keyword))
                    ->orWhere('trade_no', 'like', LikeEscape::contains($keyword))
                    ->orWhere('gateway', 'like', LikeEscape::contains($keyword))
                    ->orWhere('error_msg', 'like', LikeEscape::contains($keyword));

                $this->applyOptionalKeywordColumns($builder, 'gateway_logs', $keyword, ['gateway_key', 'trace_id']);
            });
        }

        if (! empty($filters['gateway'])) {
            $query->where('gateway_key', PaymentGatewayCode::normalize((string) $filters['gateway']));
        }

        if (! empty($filters['action'])) {
            $query->where('action', trim((string) $filters['action']));
        }

        if (! empty($filters['result_status'])) {
            $query->where('result_status', trim((string) $filters['result_status']));
        }

        $this->applyDateFilter($query, $filters);

        $logs = (clone $query)->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        if (! $withSummary) {
            return $this->buildPaginatorPayload($logs);
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN result_status = 'success' THEN 1 ELSE 0 END), 0) as success")
            ->selectRaw("COALESCE(SUM(CASE WHEN result_status = 'failed' THEN 1 ELSE 0 END), 0) as failed")
            ->first();

        return $this->buildPaginatorPayload($logs, [
            'total' => (int) ($summary?->total ?? 0),
            'success' => (int) ($summary?->success ?? 0),
            'failed' => (int) ($summary?->failed ?? 0),
        ]);
    }

    public function getActivityLogs(array $filters, int $page, int $perPage, bool $withSummary = true): array
    {
        // activity_logs 是唯一在线真源：表不存在或无数据时直接返回空页，
        // 不再回退 operation_logs（隐式切源会让同一页面混用两套数据）。
        if (! SchemaMetadataCache::hasTable('activity_logs')) {
            return $this->buildPaginatorPayload($this->emptyPaginator($perPage));
        }

        $query = ActivityLog::query();
        $this->applyActivityLogFilters($query, $filters);

        $logs = (clone $query)->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        $privacy = AdminPrivacy::current();
        $logs->setCollection($logs->getCollection()->map(function (ActivityLog $log) use ($privacy) {
            $item = $log->toArray();
            $item['source'] = 'activity_log';
            $item['context'] = $privacy->payload($item['context'] ?? []);
            $item['ip_address'] = trim((string) ($item['ip_address'] ?? ''));
            if (($item['actor_type'] ?? '') === 'client') {
                $item['actor_name'] = $privacy->displayName($item['actor_name'] ?? '');
            }

            return $item;
        }));

        if (! $withSummary) {
            return $this->buildPaginatorPayload($logs);
        }

        $summary = $this->getActivityLogsSummary($filters);

        return $this->buildPaginatorPayload($logs, [
            'total' => (int) ($summary['total'] ?? 0),
            'modules' => (int) ($summary['modules'] ?? 0),
            'source' => 'activity_logs',
        ]);
    }

    public function getActivityLogsSummary(array $filters): array
    {
        return Cache::remember(
            $this->buildListSummaryCacheKey('activity', $filters),
            now()->addSeconds(self::LIST_SUMMARY_CACHE_TTL_SECONDS),
            function () use ($filters): array {
                if (! SchemaMetadataCache::hasTable('activity_logs')) {
                    return [
                        'total' => 0,
                        'modules' => 0,
                        'source' => 'none',
                    ];
                }

                $query = ActivityLog::query();
                $this->applyActivityLogFilters($query, $filters);

                $summary = (clone $query)
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw('COUNT(DISTINCT module) as modules')
                    ->first();

                return [
                    'total' => (int) ($summary?->total ?? 0),
                    'modules' => (int) ($summary?->modules ?? 0),
                    'source' => 'activity_logs',
                ];
            }
        );
    }

    private function applyActivityLogFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('description', 'like', LikeEscape::contains($keyword))
                    ->orWhere('actor_name', 'like', LikeEscape::contains($keyword))
                    ->orWhere('module', 'like', LikeEscape::contains($keyword))
                    ->orWhere('ip_address', 'like', LikeEscape::contains($keyword));

                if (ctype_digit($keyword)) {
                    $builder->orWhere('actor_id', (int) $keyword)
                        ->orWhere('subject_id', (int) $keyword);
                }
            });
        }

        if (! empty($filters['actor_keyword'])) {
            $keyword = trim((string) $filters['actor_keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('actor_name', 'like', LikeEscape::contains($keyword));

                if (ctype_digit($keyword)) {
                    $builder->orWhere('actor_id', (int) $keyword);
                }
            });
        }

        if (! empty($filters['description_keyword'])) {
            $keyword = trim((string) $filters['description_keyword']);
            $query->where('description', 'like', LikeEscape::contains($keyword));
        }

        if (! empty($filters['ip_address'])) {
            $query->where('ip_address', 'like', LikeEscape::contains(trim((string) $filters['ip_address'])));
        }

        if (! empty($filters['module'])) {
            $query->where('module', trim((string) $filters['module']));
        }

        if (! empty($filters['actor_type'])) {
            $query->where('actor_type', trim((string) $filters['actor_type']));
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', trim((string) $filters['subject_type']));
        }

        $this->applyDateFilter($query, $filters);
    }
}
