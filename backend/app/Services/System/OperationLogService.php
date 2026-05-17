<?php

namespace App\Services\System;

use App\Models\OperationLog;
use App\Models\Service;

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
        OperationLog::query()->create([
            'user_id' => $userId,
            'user_type' => $userType,
            'action' => $action,
            'module' => $module,
            'target_id' => $targetId,
            'detail' => $detail,
            'ip_address' => $ipAddress,
        ]);
    }

    public function writeServiceConsoleLog(
        Service $service,
        string $action,
        array $detail = [],
        array $context = [],
    ): void {
        $service->loadMissing([
            'product:id,product_type,product_group_id,config_options,purchase_requires',
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
