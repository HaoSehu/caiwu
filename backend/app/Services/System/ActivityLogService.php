<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Support\Str;

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

        ActivityLog::query()->create([
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

    public function logForUser(
        int $userId,
        string $module,
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $context = [],
    ): void {
        $user = User::query()->find($userId);
        $actorName = $user ? trim((string) ($user->nickname ?: $user->email ?: '')) : '';

        ActivityLog::query()->create([
            'actor_type' => 'client',
            'actor_id' => $userId,
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
        ActivityLog::query()->create([
            'event_id' => (string) Str::ulid(),
            'stream' => $stream,
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
            'trace_id' => $this->contextTraceId($context),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextTraceId(array $context): ?string
    {
        $value = trim((string) ($context['trace_id'] ?? ''));

        return $value !== '' ? substr($value, 0, 64) : null;
    }

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
