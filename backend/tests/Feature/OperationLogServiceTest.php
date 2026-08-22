<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Services\System\OperationLogService;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationLogServiceTest extends TestCase
{
    public function test_write_persists_activity_log_only_after_dual_write_retirement(): void
    {
        try {
            app(OperationLogService::class)->write(
                userId: null,
                userType: 'guest',
                action: 'GET api/v2/site/config',
                module: 'site',
                targetId: null,
                detail: ['request_id' => 'req-trace-001', 'status' => 200],
                ipAddress: '127.0.0.1',
            );

            $activity = ActivityLog::query()->where('trace_id', 'req-trace-001')->first();

            $this->assertNotNull($activity, 'write() 应写入唯一真源 activity_logs');
            $this->assertSame('access', $activity->stream);
            $this->assertTrue(Str::isUlid((string) $activity->event_id));
            $this->assertSame('GET api/v2/site/config', $activity->action);
            $this->assertSame(200, $activity->context['status'] ?? null);
            // operation_logs 已停写：只读遗留表不得再新增行
            $this->assertDatabaseMissing('operation_logs', [
                'action' => 'GET api/v2/site/config',
                'user_type' => 'guest',
            ]);
        } finally {
            ActivityLog::query()->where('trace_id', 'req-trace-001')->delete();
        }
    }

    public function test_write_maps_admin_login_to_auth_stream(): void
    {
        try {
            app(OperationLogService::class)->write(
                userId: 1,
                userType: 'admin',
                action: 'admin.login',
                module: 'auth',
                detail: [],
            );

            $activity = ActivityLog::query()
                ->where('action', 'admin.login')
                ->whereNotNull('event_id')
                ->orderByDesc('id')
                ->first();

            $this->assertNotNull($activity);
            $this->assertSame('auth', $activity->stream);
        } finally {
            ActivityLog::query()->where('action', 'admin.login')->whereNotNull('event_id')->delete();
        }
    }

    public function test_write_business_action_maps_to_business_stream_with_trace_id(): void
    {
        try {
            app(OperationLogService::class)->write(
                userId: 5,
                userType: 'client',
                action: 'service.renew',
                module: 'service',
                targetId: 9,
                detail: ['trace_id' => 'svc-trace-002'],
            );

            $activity = ActivityLog::query()->where('trace_id', 'svc-trace-002')->first();

            $this->assertNotNull($activity);
            $this->assertSame('business', $activity->stream);
            $this->assertSame('service', $activity->subject_type);
            $this->assertSame(9, $activity->subject_id);
        } finally {
            ActivityLog::query()->where('trace_id', 'svc-trace-002')->delete();
        }
    }
}
