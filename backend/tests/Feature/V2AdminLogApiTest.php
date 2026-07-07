<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\NotificationLog;
use App\Models\OperationLog;
use App\Models\Role;
use App\Services\System\AdminLogService;
use App\Support\AdminPermissions;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class V2AdminLogApiTest extends TestCase
{
    public function test_admin_log_list_requires_permission_rejects_legacy_pagination_and_returns_excerpts(): void
    {
        $apiLog = $this->createApiLog();

        $this->getJson('/api/v2/admin/logs/api')
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);

        Sanctum::actingAs($this->createAdmin([]));

        $this->getJson('/api/v2/admin/logs/api')
            ->assertForbidden()
            ->assertJsonPath('code', 40300);

        Sanctum::actingAs($this->createAdmin([AdminPermissions::LOG_LIST]));

        $this->getJson('/api/v2/admin/logs/api?per_page=20')
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['per_page']]]);

        $this->getJson('/api/v2/admin/logs/api?pageSize=20')
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['pageSize']]]);

        $this->getJson('/api/v2/admin/logs/not-a-channel')
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200);

        $this->getJson('/api/v2/admin/logs/api?include_summary=maybe')
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['include_summary']]]);

        $response = $this->getJson('/api/v2/admin/logs/api?'.http_build_query([
            'method' => 'GET',
            'module' => 'v2-api-log-test',
            'page' => 1,
            'page_size' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.list.0.id', $apiLog->id)
            ->assertJsonPath('data.list.0.channel', 'api')
            ->assertJsonMissingPath('data.list.0.detail')
            ->assertJsonMissingPath('data.list.0.context')
            ->assertJsonMissingPath('data.list.0.params')
            ->assertJsonMissingPath('data.list.0.raw');

        $this->assertSame($this->logPageWhitelist(), array_keys($response->json('data')));
        $this->assertArrayHasKey('total', $response->json('data.summary'));
        $this->assertNoSensitiveKeys($response->json());
        $this->assertLessThan(100 * 1024, strlen((string) $response->getContent()));

        $lightweightResponse = $this->getJson('/api/v2/admin/logs/api?'.http_build_query([
            'method' => 'GET',
            'module' => 'v2-api-log-test',
            'page' => 1,
            'page_size' => 10,
            'include_summary' => 0,
        ]))
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.list.0.id', $apiLog->id);

        $this->assertSame([], $lightweightResponse->json('data.summary'));
    }

    public function test_admin_log_detail_returns_full_context_without_sensitive_keys(): void
    {
        $apiLog = $this->createApiLog();

        Sanctum::actingAs($this->createAdmin([AdminPermissions::LOG_LIST]));

        $this->getJson('/api/v2/admin/logs/api/'.$apiLog->id.'?page=1')
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['page']]]);

        $response = $this->getJson('/api/v2/admin/logs/api/'.$apiLog->id)
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.log.id', (string) $apiLog->id)
            ->assertJsonPath('data.log.channel', 'api')
            ->assertJsonPath('data.log.message', 'GET /api/v2/client/orders')
            ->assertJsonMissingPath('data.log.context.password')
            ->assertJsonMissingPath('data.log.context.api_key');

        $this->assertSame($this->logDetailWhitelist(), array_keys($response->json('data.log')));
        $this->assertNoSensitiveKeys($response->json());
        $this->assertLessThan(100 * 1024, strlen((string) $response->getContent()));
    }

    public function test_admin_log_list_can_skip_summary_for_page_loads(): void
    {
        $this->mock(AdminLogService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getApiLogs')
                ->once()
                ->with([], 1, 20, false)
                ->andReturn([
                    'data' => [],
                    'total' => 0,
                    'current_page' => 1,
                    'per_page' => 20,
                    'summary' => ['total' => 99],
                ]);
        });

        Sanctum::actingAs($this->createAdmin([AdminPermissions::LOG_LIST]));

        $this->getJson('/api/v2/admin/logs/api?include_summary=0')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.list', [])
            ->assertJsonPath('data.summary', []);
    }

    public function test_notification_log_list_summary_and_detail_are_safely_projected(): void
    {
        $smsLog = NotificationLog::query()->create([
            'channel' => 'sms',
            'recipient' => '13800138000',
            'template_code' => 'LOGIN_CODE',
            'content' => '验证码 123456，用于登录确认。'.str_repeat('内容', 80),
            'params_json' => [
                'code' => '123456',
                'password' => 'must-not-leak',
                'api_key' => 'must-not-leak',
            ],
            'provider' => 'aliyun',
            'request_id' => 'REQ-V2-SMS-'.bin2hex(random_bytes(3)),
            'status' => 'success',
            'error_msg' => 'secret=must-not-leak',
            'sent_at' => now(),
            'trace_id' => 'trace-v2-sms-'.bin2hex(random_bytes(3)),
        ]);

        Sanctum::actingAs($this->createAdmin([AdminPermissions::LOG_LIST]));

        $listResponse = $this->getJson('/api/v2/admin/logs/sms?'.http_build_query([
            'status' => 'success',
            'page' => 1,
            'page_size' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.list.0.id', $smsLog->id)
            ->assertJsonPath('data.list.0.channel', 'sms')
            ->assertJsonMissingPath('data.list.0.content')
            ->assertJsonMissingPath('data.list.0.params')
            ->assertJsonMissingPath('data.list.0.params_json');

        $this->assertNoSensitiveKeys($listResponse->json());
        $this->assertLessThan(100 * 1024, strlen((string) $listResponse->getContent()));

        $this->getJson('/api/v2/admin/log-summaries/sms?page=1&pageSize=10')
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['page', 'pageSize']]]);

        $summaryResponse = $this->getJson('/api/v2/admin/log-summaries/sms?status=success')
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertGreaterThanOrEqual(1, (int) $summaryResponse->json('data.total'));

        $detailResponse = $this->getJson('/api/v2/admin/logs/sms/'.$smsLog->id)
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.log.channel', 'sms')
            ->assertJsonPath('data.log.fields.phone', '[REDACTED]')
            ->assertJsonMissingPath('data.log.context.params.password')
            ->assertJsonMissingPath('data.log.context.params.api_key');

        $this->assertStringContainsString('验证码 123456', (string) $detailResponse->json('data.log.message'));
        $this->assertNoSensitiveKeys($detailResponse->json());
        $this->assertLessThan(100 * 1024, strlen((string) $detailResponse->getContent()));
    }

    private function createApiLog(): OperationLog
    {
        return OperationLog::query()->create([
            'user_id' => 1,
            'user_type' => 'admin',
            'action' => 'GET /api/v2/client/orders',
            'module' => 'v2-api-log-test',
            'subject_id' => 1001,
            'context' => [
                'status' => 200,
                'request_id' => 'REQ-V2-LOG-'.bin2hex(random_bytes(3)),
                'params' => [
                    'page' => 1,
                    'password' => 'must-not-leak',
                    'api_key' => 'must-not-leak',
                ],
                'user_agent' => 'Feature Test',
            ],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);
    }

    private function createAdmin(array $permissions): AdminUser
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'v2-logs-'.$suffix,
            'label' => 'V2 Logs',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'v2-logs-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'V2 Logs',
            'email' => 'v2-logs-'.$suffix.'@example.com',
            'status' => 1,
        ]);
    }

    /**
     * @return list<string>
     */
    private function logPageWhitelist(): array
    {
        return [
            'list',
            'total',
            'page',
            'page_size',
            'summary',
        ];
    }

    /**
     * @return list<string>
     */
    private function logDetailWhitelist(): array
    {
        return [
            'id',
            'channel',
            'source',
            'fields',
            'message',
            'context',
            'created_at',
        ];
    }

    private function assertNoSensitiveKeys(mixed $payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                foreach (['password', 'secret', 'api_key', 'raw_response', 'third_party_response'] as $needle) {
                    $this->assertStringNotContainsString($needle, strtolower($key));
                }
            }

            $this->assertNoSensitiveKeys($value);
        }
    }
}
