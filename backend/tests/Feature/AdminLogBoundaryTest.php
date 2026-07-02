<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\GatewayLog;
use App\Models\OperationLog;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminPermissions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLogBoundaryTest extends TestCase
{
    public function test_gateway_log_route_applies_gateway_action_and_result_filters(): void
    {
        $admin = $this->createAdminUser([AdminPermissions::LOG_LIST]);
        Sanctum::actingAs($admin);

        $suffix = bin2hex(random_bytes(4));

        GatewayLog::query()->create([
            'gateway' => 'alipay',
            'action' => 'refund',
            'out_trade_no' => 'boundary-'.$suffix.'-refund',
            'trade_no' => 'trade-'.$suffix.'-refund',
            'result_status' => 'failed',
            'error_msg' => 'boundary-'.$suffix,
        ]);

        GatewayLog::query()->create([
            'gateway' => 'alipay',
            'action' => 'precreate',
            'out_trade_no' => 'boundary-'.$suffix.'-precreate',
            'trade_no' => 'trade-'.$suffix.'-precreate',
            'result_status' => 'success',
            'error_msg' => null,
        ]);

        $this->getJson("/api/admin/logs/gateway?keyword={$suffix}&gateway=alipay&action=refund&result_status=failed")
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.gateway', 'alipay')
            ->assertJsonPath('data.data.0.action', 'refund')
            ->assertJsonPath('data.data.0.result_status', 'failed');
    }

    public function test_activity_log_route_falls_back_to_business_operation_logs_with_actor_filter(): void
    {
        $admin = $this->createAdminUser([AdminPermissions::LOG_LIST]);
        Sanctum::actingAs($admin);

        $suffix = bin2hex(random_bytes(4));

        OperationLog::query()->create([
            'user_id' => (int) $admin->id,
            'user_type' => 'admin',
            'action' => 'order.boundary.'.$suffix,
            'module' => 'order',
            'target_id' => 1001,
            'detail' => [
                'title' => '订单边界测试 '.$suffix,
                'message' => '管理员处理订单 '.$suffix,
            ],
            'ip_address' => '127.0.0.1',
        ]);

        OperationLog::query()->create([
            'user_id' => null,
            'user_type' => 'client',
            'action' => 'order.boundary.'.$suffix.'.client',
            'module' => 'order',
            'target_id' => 1002,
            'detail' => [
                'title' => '客户订单边界测试 '.$suffix,
                'message' => '客户处理订单 '.$suffix,
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $this->getJson("/api/admin/logs/activity?keyword={$suffix}&actor_type=admin&subject_type=order")
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.actor_type', 'admin')
            ->assertJsonPath('data.data.0.module', 'order')
            ->assertJsonPath('data.data.0.subject_type', 'order')
            ->assertJsonPath('data.data.0.source', 'operation_log');
    }

    public function test_activity_log_keyword_filters_business_operation_logs_by_user_identity(): void
    {
        $admin = $this->createAdminUser([AdminPermissions::LOG_LIST]);
        Sanctum::actingAs($admin);

        $suffix = bin2hex(random_bytes(4));
        $client = User::query()->create([
            'email' => 'activity-user-'.$suffix.'@example.com',
            'password' => 'secret123',
            'phone' => '139'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'nickname' => '活动用户'.$suffix,
            'status' => 1,
        ]);

        OperationLog::query()->create([
            'user_id' => (int) $client->id,
            'user_type' => 'client',
            'action' => 'service.user-filter.client',
            'module' => 'service',
            'target_id' => 2001,
            'detail' => [
                'title' => '客户服务操作',
            ],
            'ip_address' => '127.0.0.1',
        ]);

        OperationLog::query()->create([
            'user_id' => (int) $admin->id,
            'user_type' => 'admin',
            'action' => 'service.user-filter.admin',
            'module' => 'service',
            'target_id' => 2002,
            'detail' => [
                'title' => '管理员服务操作',
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $this->getJson('/api/admin/logs/activity?keyword='.rawurlencode((string) $client->email))
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.actor_type', 'client')
            ->assertJsonPath('data.data.0.actor_id', (int) $client->id)
            ->assertJsonPath('data.data.0.source', 'operation_log');
    }

    private function createAdminUser(array $permissions): AdminUser
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'log-boundary-role-'.$suffix,
            'label' => '日志边界角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'log-boundary-admin-'.$suffix,
            'password' => 'secret123',
            'nickname' => '日志边界管理员',
            'role_id' => (int) $role->id,
            'status' => 1,
        ]);
    }
}
