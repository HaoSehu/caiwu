<?php

namespace App\Services\System;

use App\Models\Service;
use App\Support\ActivityLogStream;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OperationLogService
{
    public function write(
        ?int $userId,
        string $userType,
        string $action,
        string $module,
        ?int $targetId = null,
        array $detail = [],
        ?string $ipAddress = null,
    ): void {
        // activity_logs 是唯一在线真源（双写已下线）；operation_logs 转为只读遗留表，
        // 存量由 30 天归档自然消化
        $this->writeActivityLog(
            userId: $userId,
            userType: $userType,
            action: $action,
            module: $module,
            targetId: $targetId,
            detail: $detail,
            ipAddress: $ipAddress,
        );
    }

    public function writeServiceConsoleLog(
        Service $service,
        string $action,
        array $detail = [],
        array $context = [],
    ): void {
        $service->loadMissing([
            'product:id,product_type,service_type_code,product_group_id,config_options,purchase_requires',
            'order:id,order_no',
        ]);

        $actorType = trim((string) ($context['actor_type'] ?? 'client')) ?: 'client';
        $actorUserId = isset($context['actor_user_id']) ? (int) ($context['actor_user_id'] ?? 0) : 0;

        if ($actorUserId <= 0 && $actorType === 'client') {
            $actorUserId = (int) $service->user_id;
        }

        $payload = $this->filterDetail(array_merge([
            'service_id' => (int) $service->id,
            'service_name' => trim((string) $service->name),
            'product_name' => trim((string) ($service->product?->name ?? '')),
            'order_no' => trim((string) ($service->order?->order_no ?? '')),
            'actor_type' => $actorType,
            'actor_name' => trim((string) ($context['actor_name'] ?? '')),
            'trace_id' => trim((string) ($context['trace_id'] ?? '')),
        ], $detail));

        $ipAddress = trim((string) ($context['ip_address'] ?? ''));

        $this->write(
            userId: $actorUserId > 0 ? $actorUserId : null,
            userType: $actorType,
            action: $action,
            module: 'service',
            targetId: (int) $service->id,
            detail: $payload,
            ipAddress: $ipAddress !== '' ? $ipAddress : null,
        );
    }

    /**
     * 写入 activity_logs（唯一在线真源）。
     * 统一经 ActivityLogService::record 落库并生成 event_id；写入失败时记录告警并
     * 递增失败计数，便于容量/审计监控发现，但不阻断主流程（日志失败不影响业务事务）。
     */
    private function writeActivityLog(
        ?int $userId,
        string $userType,
        string $action,
        string $module,
        ?int $targetId,
        array $detail,
        ?string $ipAddress,
    ): void {
        try {
            $actorName = trim((string) ($detail['actor_name'] ?? ''));
            if ($actorName === '') {
                $actorName = match ($userType) {
                    'admin' => 'admin',
                    'client' => 'client',
                    default => 'system',
                };
            }

            $description = $action;
            if ($module !== '' && $module !== $action) {
                $description = "[{$module}] {$action}";
            }
            if ($targetId !== null) {
                $description .= " #{$targetId}";
            }

            app(ActivityLogService::class)->record([
                'stream' => ActivityLogStream::resolve($module, $action),
                'actor_type' => $userType,
                'actor_id' => $userId,
                'actor_name' => $actorName,
                'module' => $module,
                'action' => $action,
                'description' => $description,
                'subject_type' => $module ?: null,
                'subject_id' => $targetId,
                'context' => $detail !== [] ? $detail : null,
                'ip_address' => $ipAddress,
                'trace_id' => $this->resolveDetailTraceId($detail),
            ]);
        } catch (\Throwable $e) {
            // 失败计数与告警本身也是旁路能力，必须彼此隔离；Redis/缓存故障
            // 不能从这里再次抛出并把原业务请求判定为失败。
            try {
                Cache::increment(CacheKey::activityLogWriteFailures());
            } catch (\Throwable) {
                // 计数失败不影响主流程，也不覆盖原始日志异常。
            }

            try {
                Log::error(
                    'activity_log write failed',
                    ['error' => $e->getMessage(), 'action' => $action, 'module' => $module]
                );
            } catch (\Throwable) {
                // 日志后端故障同样不能阻断业务。
            }
        }
    }

    /**
     * 从 detail 透出链路 ID：API 请求日志用 request_id，业务操作日志用 trace_id。
     *
     * @param  array<string, mixed>  $detail
     */
    private function resolveDetailTraceId(array $detail): ?string
    {
        foreach (['request_id', 'trace_id'] as $key) {
            $value = trim((string) ($detail[$key] ?? ''));

            if ($value !== '') {
                return substr($value, 0, 64);
            }
        }

        return null;
    }

    private function filterDetail(array $detail): array
    {
        return collect($detail)
            ->reject(function ($value) {
                if (is_bool($value)) {
                    return false;
                }

                if (is_array($value)) {
                    return $value === [];
                }

                return $value === null || (is_string($value) && trim($value) === '');
            })
            ->all();
    }
}
