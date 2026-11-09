<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\RechargeRecord;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理端余额调整幂等重放回归（M-93 / P1 AUF-01）：
 *
 * 同键同体重放命中的是 finance_operations.result_payload（json 解码后的数组），
 * 而首次执行返回的是 Eloquent 模型。重放路径若对数组做 ->id 访问会抛
 * 「Attempt to read property on array」→ 500——此时资金已入账，管理员会误判失败
 * 并换新幂等键二次提交，造成同一笔调整二次入账。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixAdminUserReportsRechargeReplayTest extends TestCase
{
    use DatabaseTransactions;

    public function test_same_key_and_payload_replay_returns_200_with_same_document_ids(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_RECHARGE]));
        $user = $this->makeUser();

        $body = [
            'amount' => '25.50',
            'remark' => '同键同体重放回归',
            'idempotency_key' => 'idem-replay-'.uniqid(),
        ];

        $first = $this->postJson("/api/v2/admin/users/{$user->id}/recharges", $body)
            ->assertOk()
            ->assertJsonPath('code', 0);

        $invoiceId = (int) $first->json('data.detail.documents.invoice_id');
        $transactionId = (int) $first->json('data.detail.documents.account_transaction_id');
        $rechargeRecordId = (int) $first->json('data.detail.documents.recharge_record_id');

        $this->assertGreaterThan(0, $invoiceId);
        $this->assertGreaterThan(0, $transactionId);
        $this->assertGreaterThan(0, $rechargeRecordId);

        // 重放：payload 来自 json 解码后的数组，修复前这里 500。
        $second = $this->postJson("/api/v2/admin/users/{$user->id}/recharges", $body)
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertSame($invoiceId, (int) $second->json('data.detail.documents.invoice_id'));
        $this->assertSame($transactionId, (int) $second->json('data.detail.documents.account_transaction_id'));
        $this->assertSame($rechargeRecordId, (int) $second->json('data.detail.documents.recharge_record_id'));

        // 资金只入账一次
        $this->assertSame(1, RechargeRecord::query()->where('user_id', (int) $user->id)->count());
        $this->assertSame('25.50', $user->fresh()->balance);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'recharge-replay-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'recharge-replay-tester',
            'total_sales_amount' => 0,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '余额重放测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '余额重放测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
