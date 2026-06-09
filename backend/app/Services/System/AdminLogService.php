<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\EmailLog;
use App\Models\GatewayLog;
use App\Models\NotificationLog;
use App\Models\OperationLog;
use App\Models\Role;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\System\Concerns\HandlesAdminLogCleanup;
use App\Support\SensitiveDataSanitizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AdminLogService
{
    use HandlesAdminLogCleanup;

    private const HTTP_ACTION_REGEXP = '^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD) ';

    private const LIST_SUMMARY_CACHE_TTL_SECONDS = 30;

    private const FILE_LOG_CACHE_TTL_SECONDS = 20;

    private const CLEANUP_OVERVIEW_CACHE_TTL_SECONDS = 20;

    private const FILE_LOG_SUMMARY_CACHE_TTL_SECONDS = 20;

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
        'sync-processing-order-status' => [
            'title' => '账单状态同步（兼容）',
            'log_keywords' => ['处理中订单状态同步执行完成', 'sync-processing-order-status', 'orders:sync-processing-status'],
        ],
        'queue-backlog-drain' => [
            'title' => '队列积压消费',
            'log_keywords' => ['队列积压消费', 'queue:work'],
        ],
    ];

    public function getSmsLogs(array $filters, int $perPage): array
    {
        $query = $this->buildSmsLogQuery($filters);
        if ($query === null) {
            return $this->buildPaginatorPayload($this->emptyPaginator($perPage));
        }

        $logs = (clone $query)->orderByDesc('created_at')->paginate($perPage);
        $logs->setCollection($logs->getCollection()->map(function ($log) {
            $item = $log->toArray();
            $item['params_json'] = $this->normalizeNotificationParams($item['params_json'] ?? []);
            $item = $this->sanitizeSmsLogItem($item);
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

    public function getEmailLogs(array $filters, int $perPage): array
    {
        $query = $this->buildEmailLogQuery($filters);
        if ($query === null) {
            return $this->buildPaginatorPayload($this->emptyPaginator($perPage));
        }

        $logs = (clone $query)->orderByDesc('created_at')->paginate($perPage);

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

    public function getApiLogs(array $filters, int $page, int $perPage): array
    {
        $query = OperationLog::query()
            ->whereRaw('action REGEXP ?', [self::HTTP_ACTION_REGEXP]);

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('action', 'like', "%{$keyword}%")
                    ->orWhere('module', 'like', "%{$keyword}%")
                    ->orWhere('ip_address', 'like', "%{$keyword}%")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(context, '$.request_id')) like ?", ["%{$keyword}%"]);
            });
        }

        if (! empty($filters['module'])) {
            $query->where('module', trim((string) $filters['module']));
        }

        if (! empty($filters['method'])) {
            $query->where('action', 'like', trim((string) $filters['method']).' %');
        }

        if (! empty($filters['status'])) {
            $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(context, '$.status')) AS UNSIGNED) = ?", [(int) $filters['status']]);
        }

        if (! empty($filters['user_type'])) {
            $query->where('user_type', trim((string) $filters['user_type']));
        }

        $this->applyDateFilter($query, $filters);

        $logs = (clone $query)->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);
        $rows = $this->mapOperationLogs($logs->getCollection(), false)->map(function (array $item) {
            [$method, $path] = $this->splitHttpAction((string) ($item['action'] ?? ''));
            $detail = SensitiveDataSanitizer::sanitize(
                is_array($item['detail'] ?? null) ? $item['detail'] : []
            );

            $item['method'] = $method;
            $item['path'] = $path;
            $item['status'] = isset($detail['status']) ? (int) $detail['status'] : null;
            $item['request_id'] = trim((string) ($detail['request_id'] ?? ''));
            $item['params'] = $detail['params'] ?? [];
            $item['user_agent'] = trim((string) ($detail['user_agent'] ?? ''));

            return $item;
        });
        $logs->setCollection($rows);

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(context, '$.status')) AS UNSIGNED) >= 500 THEN 1 ELSE 0 END), 0) as errors")
            ->selectRaw("COALESCE(SUM(CASE WHEN user_type = 'admin' THEN 1 ELSE 0 END), 0) as admin_count")
            ->first();

        return $this->buildPaginatorPayload($logs, [
            'total' => (int) ($summary?->total ?? 0),
            'errors' => (int) ($summary?->errors ?? 0),
            'admin_count' => (int) ($summary?->admin_count ?? 0),
        ]);
    }

    public function getTaskLogs(array $filters, int $page, int $perPage): array
    {
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
                $entries = $this->buildTaskLogEntries($filters);

                return [
                    'total' => $entries->count(),
                    'tasks' => $entries->pluck('task_key')->filter()->unique()->count(),
                    'errors' => $entries->whereIn('level', ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])->count(),
                ];
            }
        );
    }

    public function getSystemLogs(array $filters, int $page, int $perPage): array
    {
        $entries = $this->buildSystemLogEntries($filters);

        $paginator = $this->paginateCollection($entries, $page, $perPage);

        return $this->buildPaginatorPayload($paginator);
    }

    public function getSystemLogsSummary(array $filters): array
    {
        return Cache::remember(
            $this->buildFileLogSummaryCacheKey('system', $filters),
            now()->addSeconds(self::FILE_LOG_SUMMARY_CACHE_TTL_SECONDS),
            function () use ($filters) {
                $entries = $this->buildSystemLogEntries($filters);

                return [
                    'total' => $entries->count(),
                    'errors' => $entries->whereIn('level', ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])->count(),
                    'warnings' => $entries->where('level', 'WARNING')->count(),
                    'infos' => $entries->where('level', 'INFO')->count(),
                ];
            }
        );
    }

    public function getAdminLoginLogs(array $filters, int $page, int $perPage): array
    {
        $query = OperationLog::query()
            ->where('module', 'auth')
            ->where('action', 'admin.login');

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('ip_address', 'like', "%{$keyword}%")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(context, '$.admin_username')) like ?", ["%{$keyword}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(context, '$.admin_nickname')) like ?", ["%{$keyword}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(context, '$.role_name')) like ?", ["%{$keyword}%"]);
            });
        }

        $this->applyDateFilter($query, $filters);

        $logs = (clone $query)->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        if ($logs->total() > 0) {
            $rows = $this->mapOperationLogs($logs->getCollection(), false)->map(function (array $item) {
                $detail = is_array($item['detail'] ?? null) ? $item['detail'] : [];
                $item['admin_username'] = trim((string) ($detail['admin_username'] ?? $item['actor_name'] ?? ''));
                $item['admin_nickname'] = trim((string) ($detail['admin_nickname'] ?? ''));
                $item['role_name'] = trim((string) ($detail['role_name'] ?? ''));
                $item['source'] = 'operation_log';

                return $item;
            });
            $logs->setCollection($rows);

            return $this->buildPaginatorPayload($logs, [
                'total' => $logs->total(),
                'mode' => 'operation_log',
            ]);
        }

        $fallback = AdminUser::query()
            ->with('role:id,name,label')
            ->whereNotNull('last_login_at')
            ->when(! empty($filters['keyword']), function ($builder) use ($filters) {
                $keyword = trim((string) $filters['keyword']);
                $builder->where(function ($query) use ($keyword) {
                    $query->where('username', 'like', "%{$keyword}%")
                        ->orWhere('nickname', 'like', "%{$keyword}%")
                        ->orWhere('last_login_ip', 'like', "%{$keyword}%");
                });
            });

        if (! empty($filters['start_date'])) {
            $fallback->where('last_login_at', '>=', Carbon::parse((string) $filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $fallback->where('last_login_at', '<=', Carbon::parse((string) $filters['end_date'])->endOfDay());
        }

        $admins = $fallback->orderByDesc('last_login_at')->paginate($perPage, ['*'], 'page', $page);
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
                'created_at' => $admin->last_login_at?->format('Y-m-d H:i:s'),
                'detail' => [
                    'source' => 'admin_users.last_login_at',
                ],
                'source' => 'admin_snapshot',
            ];
        }));

        return $this->buildPaginatorPayload($admins, [
            'total' => $admins->total(),
            'mode' => 'admin_snapshot',
        ]);
    }

    private function baseApiLogQuery()
    {
        return OperationLog::query()->whereRaw('action REGEXP ?', [self::HTTP_ACTION_REGEXP]);
    }

    private function baseAdminLoginLogQuery()
    {
        return OperationLog::query()
            ->where('module', 'auth')
            ->where('action', 'admin.login');
    }

    private function buildPaginatorPayload(LengthAwarePaginator $paginator, array $summary = []): array
    {
        return array_merge($paginator->toArray(), [
            'summary' => $summary,
        ]);
    }

    private function buildSmsLogQuery(array $filters): ?Builder
    {
        if (Schema::hasTable('notification_logs')) {
            $query = NotificationLog::query()
                ->where('channel', 'sms')
                ->selectRaw('id, recipient as phone, template_code, content, params_json, status, provider, request_id, error_msg, sent_at, created_at, updated_at, origin_type');
            $recipientColumn = 'recipient';
        } elseif (Schema::hasTable('sms_logs')) {
            $query = SmsLog::query()
                ->selectRaw("id, phone, template_code, content, params as params_json, status, provider, request_id, error_msg, sent_at, created_at, updated_at, 'sms_log' as origin_type");
            $recipientColumn = 'phone';
        } else {
            return null;
        }

        if (! empty($filters['phone'])) {
            $query->where($recipientColumn, 'like', '%'.trim((string) $filters['phone']).'%');
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('template_code', 'like', "%{$keyword}%")
                    ->orWhere('request_id', 'like', "%{$keyword}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function buildEmailLogQuery(array $filters): ?Builder
    {
        if (Schema::hasTable('notification_logs')) {
            $query = NotificationLog::query()
                ->where('channel', 'email')
                ->selectRaw('id, template_code, recipient as to_email, subject, content, status, error_msg, sent_at, created_at, updated_at');
            $recipientColumn = 'recipient';
        } elseif (Schema::hasTable('email_logs')) {
            $query = EmailLog::query()
                ->selectRaw('id, template_code, to_email, subject, content, status, error_msg, sent_at, created_at, updated_at');
            $recipientColumn = 'to_email';
        } else {
            return null;
        }

        if (! empty($filters['email'])) {
            $query->where($recipientColumn, 'like', '%'.trim((string) $filters['email']).'%');
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) ($filters['keyword'] ?? ''));
            $query->where(function ($builder) use ($keyword) {
                $builder->where('template_code', 'like', "%{$keyword}%")
                    ->orWhere('subject', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function buildNotificationSummaryQuery(string $channel, array $filters): ?Builder
    {
        if (Schema::hasTable('notification_logs')) {
            $query = NotificationLog::query()->where('channel', $channel);
            $recipientColumn = 'recipient';
            $hasRequestId = true;
        } elseif ($channel === 'sms' && Schema::hasTable('sms_logs')) {
            $query = SmsLog::query();
            $recipientColumn = 'phone';
            $hasRequestId = true;
        } elseif ($channel === 'email' && Schema::hasTable('email_logs')) {
            $query = EmailLog::query();
            $recipientColumn = 'to_email';
            $hasRequestId = false;
        } else {
            return null;
        }

        if ($channel === 'sms' && ! empty($filters['phone'])) {
            $query->where($recipientColumn, 'like', '%'.trim((string) $filters['phone']).'%');
        }

        if ($channel === 'email' && ! empty($filters['email'])) {
            $query->where($recipientColumn, 'like', '%'.trim((string) $filters['email']).'%');
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($channel, $hasRequestId, $keyword) {
                if ($channel === 'sms') {
                    $builder->where('template_code', 'like', "%{$keyword}%");

                    if ($hasRequestId) {
                        $builder->orWhere('request_id', 'like', "%{$keyword}%");
                    }

                    return;
                }

                $builder->where('content', 'like', "%{$keyword}%")
                    ->orWhere('template_code', 'like', "%{$keyword}%")
                    ->orWhere('subject', 'like', "%{$keyword}%");

                if ($hasRequestId) {
                    $builder->orWhere('request_id', 'like', "%{$keyword}%");
                }
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

    private function sanitizeSmsLogItem(array $item): array
    {
        $item['phone'] = $this->maskPhone((string) ($item['phone'] ?? ''));

        if ($this->shouldRedactSmsLog($item)) {
            $item['content'] = '短信验证码已发送（内容已脱敏）';
            $item['params_json'] = $this->sanitizeSmsParams((array) ($item['params_json'] ?? []));
        }

        return $item;
    }

    private function shouldRedactSmsLog(array $item): bool
    {
        $originType = trim((string) ($item['origin_type'] ?? ''));
        $templateCode = trim((string) ($item['template_code'] ?? ''));

        return $originType === 'sms_verify' || $templateCode === '100001';
    }

    private function sanitizeSmsParams(array $params): array
    {
        if ($params === []) {
            return ['code' => '***'];
        }

        $params['code'] = '***';

        return $params;
    }

    private function maskPhone(string $phone): string
    {
        $normalized = trim($phone);
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) <= 7) {
            return $normalized;
        }

        return mb_substr($normalized, 0, 3).'****'.mb_substr($normalized, -4);
    }

    private function buildTaskLogEntries(array $filters): Collection
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

                if (! empty($filters['keyword'])) {
                    $keyword = trim((string) $filters['keyword']);
                    if (! str_contains((string) $item['message'], $keyword) && ! str_contains((string) $item['task_title'], $keyword)) {
                        return false;
                    }
                }

                return $this->matchLogDateRange((string) $item['time'], $filters);
            })
            ->values();
    }

    private function buildSystemLogEntries(array $filters): Collection
    {
        return collect($this->readLaravelLogEntries())
            ->filter(fn (array $item) => empty($item['task_key']))
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
            ->values();
    }

    private function buildFileLogSummaryCacheKey(string $type, array $filters): string
    {
        $logPath = storage_path('logs/laravel.log');
        $fileModifiedAt = is_file($logPath) ? (int) filemtime($logPath) : 0;
        $fileSize = is_file($logPath) ? (int) filesize($logPath) : 0;
        ksort($filters);

        return 'admin_logs:file_summary:'.$type.':'.$fileModifiedAt.':'.$fileSize.':'.md5(
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

    private function mapOperationLogs(Collection $logs, bool $includeMessageFields): Collection
    {
        $adminIds = $logs->where('user_type', 'admin')->pluck('user_id')->filter()->unique()->values();
        $clientIds = $logs->where('user_type', 'client')->pluck('user_id')->filter()->unique()->values();

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

        return $logs->map(function (OperationLog $log) use ($admins, $roles, $clients, $includeMessageFields) {
            $detail = is_array($log->detail) ? $log->detail : [];
            $actorName = '';
            $roleName = '';

            if ($log->user_type === 'admin') {
                $admin = $admins->get($log->user_id);
                if ($admin) {
                    $actorName = trim((string) ($admin->nickname ?: $admin->username));
                    $role = $roles->get($admin->role_id);
                    $roleName = trim((string) ($role?->label ?: $role?->name ?: ''));
                }
            } elseif ($log->user_type === 'client') {
                $client = $clients->get($log->user_id);
                if ($client instanceof User) {
                    $actorName = trim((string) $client->display_name);
                }
            }

            $item = [
                'id' => (int) $log->id,
                'user_id' => $log->user_id ? (int) $log->user_id : null,
                'user_type' => trim((string) ($log->user_type ?? '')),
                'actor_name' => $actorName,
                'role_name' => $roleName,
                'action' => trim((string) $log->action),
                'module' => trim((string) ($log->module ?? '')),
                'target_id' => $log->target_id ? (int) $log->target_id : null,
                'detail' => $detail,
                'ip_address' => trim((string) ($log->ip_address ?? '')),
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            ];

            if ($includeMessageFields) {
                $item['title'] = trim((string) ($detail['title'] ?? $detail['subject'] ?? ''));
                $item['content'] = trim((string) ($detail['content'] ?? $detail['message'] ?? ''));
                $item['target'] = trim((string) ($detail['target'] ?? $detail['to'] ?? ''));
                $item['status'] = trim((string) ($detail['status'] ?? 'success'));
            }

            return $item;
        });
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
        $logPath = storage_path('logs/laravel.log');

        if (! is_file($logPath)) {
            return [];
        }

        $fileModifiedAt = (int) filemtime($logPath);
        $fileSize = (int) filesize($logPath);
        $cacheKey = "admin_logs:file_scan:{$lineLimit}:{$fileModifiedAt}:{$fileSize}";

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::FILE_LOG_CACHE_TTL_SECONDS),
            function () use ($logPath, $lineLimit) {
                return collect($this->readLastLines($logPath, $lineLimit))
                    ->reverse()
                    ->map(fn (string $line, int $index) => $this->parseLaravelLogLine($line, $index))
                    ->filter()
                    ->values()
                    ->all();
            }
        );
    }

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
            if ($line !== '') {
                $lines[] = $line;
            }
            $file->next();
        }

        return $lines;
    }

    private function parseLaravelLogLine(string $line, int $index): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (! preg_match('/^\[(?<time>[^\]]+)\]\s+\w+\.(?<level>[A-Z]+):\s+(?<message>.+)$/u', $line, $matches)) {
            return null;
        }

        $message = SensitiveDataSanitizer::sanitizeText(trim((string) $matches['message']));
        $taskKey = $this->resolveTaskKeyFromMessage($message);

        return [
            'id' => md5($line.'|'.$index),
            'time' => trim((string) $matches['time']),
            'level' => trim((string) $matches['level']),
            'message' => preg_replace('/\s+\{.*$/u', '', $message) ?: $message,
            'raw' => $message,
            'task_key' => $taskKey,
            'task_title' => $taskKey ? (self::TASK_META[$taskKey]['title'] ?? $taskKey) : '',
        ];
    }

    private function resolveTaskKeyFromMessage(string $message): ?string
    {
        foreach (self::TASK_META as $taskKey => $meta) {
            foreach ((array) ($meta['log_keywords'] ?? []) as $keyword) {
                if ($keyword !== '' && str_contains($message, $keyword)) {
                    return $taskKey;
                }
            }
        }

        return null;
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

    public function getGatewayLogs(array $filters, int $page, int $perPage): array
    {
        if (! Schema::hasTable('gateway_logs')) {
            return $this->buildPaginatorPayload($this->emptyPaginator($perPage));
        }

        $query = GatewayLog::query();

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('out_trade_no', 'like', "%{$keyword}%")
                    ->orWhere('trade_no', 'like', "%{$keyword}%")
                    ->orWhere('gateway', 'like', "%{$keyword}%")
                    ->orWhere('error_msg', 'like', "%{$keyword}%");
            });
        }

        if (! empty($filters['gateway'])) {
            $query->where('gateway', trim((string) $filters['gateway']));
        }

        if (! empty($filters['action'])) {
            $query->where('action', trim((string) $filters['action']));
        }

        if (! empty($filters['result_status'])) {
            $query->where('result_status', trim((string) $filters['result_status']));
        }

        $this->applyDateFilter($query, $filters);

        $logs = (clone $query)->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

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

    public function getActivityLogs(array $filters, int $page, int $perPage): array
    {
        if (! Schema::hasTable('activity_logs')) {
            return $this->buildPaginatorPayload($this->emptyPaginator($perPage));
        }

        $query = ActivityLog::query();

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function ($builder) use ($keyword) {
                $builder->where('description', 'like', "%{$keyword}%")
                    ->orWhere('actor_name', 'like', "%{$keyword}%")
                    ->orWhere('module', 'like', "%{$keyword}%");
            });
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

        $logs = (clone $query)->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(DISTINCT module) as modules")
            ->first();

        return $this->buildPaginatorPayload($logs, [
            'total' => (int) ($summary?->total ?? 0),
            'modules' => (int) ($summary?->modules ?? 0),
        ]);
    }
}
