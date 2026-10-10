<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\User;
use App\Support\ActivityLogStream;
use App\Support\PayloadLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * activity_logs 唯一在线真源的统一写入入口。
 *
 * 所有业务/系统事件（含 OperationLogService 的访问审计映射）最终都必须经过
 * record() 落库，保证 event_id、stream、trace_id 语义一致，避免旁路绕过。
 */
class ActivityLogService
{
    public function log(
        string $module,
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $context = [],
    ): void {
        [$actorType, $actorId, $actorName] = $this->resolveActor();

        $this->record([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'context' => $context !== [] ? $context : null,
            'ip_address' => request()?->ip(),
        ]);
    }

    public function logSystem(
        string $module,
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $context = [],
        ?string $stream = null,
    ): void {
        $this->record([
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_name' => 'System',
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'context' => $context !== [] ? $context : null,
            'ip_address' => null,
            'stream' => $stream,
            'trace_id' => $this->contextTraceId($context),
        ]);
    }

    /**
     * 与 log() 相同（当前请求的操作者），但审计写入失败不得阻断业务：仅记录告警。
     */
    public function logSafely(
        string $module,
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $context = [],
    ): void {
        try {
            $this->log($module, $action, $description, $subjectType, $subjectId, $context);
        } catch (Throwable $exception) {
            $this->warnWriteFailure($module, $action, $exception);
        }
    }

    /**
     * 与 logSystem() 相同（系统动作），但审计写入失败不得阻断业务：仅记录告警。
     */
    public function logSystemSafely(
        string $module,
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $context = [],
        ?string $stream = null,
    ): void {
        try {
            $this->logSystem($module, $action, $description, $subjectType, $subjectId, $context, $stream);
        } catch (Throwable $exception) {
            $this->warnWriteFailure($module, $action, $exception);
        }
    }

    private function warnWriteFailure(string $module, string $action, Throwable $exception): void
    {
        Log::warning('[审计] 活动日志写入失败，已跳过本条记录', [
            'module' => $module,
            'action' => $action,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }

    /**
     * 唯一落库入口：统一生成 event_id、解析 stream，并截断 trace_id。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): void
    {
        $attributes['event_id'] = trim((string) ($attributes['event_id'] ?? '')) !== ''
            ? (string) $attributes['event_id']
            : (string) Str::ulid();

        if (trim((string) ($attributes['stream'] ?? '')) === '') {
            $attributes['stream'] = ActivityLogStream::resolve(
                (string) ($attributes['module'] ?? ''),
                (string) ($attributes['action'] ?? ''),
            );
        }

        $traceId = trim((string) ($attributes['trace_id'] ?? ''));
        $attributes['trace_id'] = $traceId !== ''
            ? substr($traceId, 0, 64)
            : $this->contextTraceId(
                is_array($attributes['context'] ?? null) ? $attributes['context'] : [],
            );

        // 防止大报文把单行 activity_logs 撑到兆级；只截超长叶子，
        // 保留 request_id/status/trace_id 等结构化筛选字段，不整体降级。
        if (is_array($attributes['context'] ?? null)) {
            $attributes['context'] = PayloadLimiter::truncateLeaves(
                $attributes['context'],
                PayloadLimiter::DEFAULT_LEAF_MAX_BYTES,
            );
        }

        ActivityLog::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextTraceId(array $context): ?string
    {
        $value = trim((string) ($context['trace_id'] ?? ''));

        return $value !== '' ? substr($value, 0, 64) : null;
    }

    /**
     * @return array{0: string, 1: int|null, 2: string}
     */
    private function resolveActor(): array
    {
        $user = request()?->user();

        if ($user instanceof AdminUser) {
            return ['admin', (int) $user->id, trim((string) ($user->nickname ?: $user->username))];
        }

        if ($user instanceof User) {
            return ['client', (int) $user->id, trim((string) ($user->nickname ?: $user->email ?: ''))];
        }

        return ['system', null, 'System'];
    }
}
