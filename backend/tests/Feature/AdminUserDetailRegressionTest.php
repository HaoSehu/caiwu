<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\BalanceLog;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserDetailRegressionTest extends TestCase
{
    public function test_admin_user_detail_and_balance_logs_work_without_account_transactions_table(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $role = Role::query()->create([
            'name' => 'user-detail-regression-'.$suffix,
            'label' => 'User Detail Regression',
            'permissions' => [AdminPermissions::USER_DETAIL],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'user-detail-admin-'.$suffix,
            'password' => 'secret123',
            'role_id' => (int) $role->id,
            'nickname' => 'Regression Admin',
            'email' => 'user-detail-admin-'.$suffix.'@example.com',
            'status' => 1,
        ]);

        $user = User::query()->create([
            'email' => 'user-detail-client-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'User Detail Client',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);

        BalanceLog::query()->create([
            'user_id' => (int) $user->id,
            'event_type' => 'recharge',
            'change_amount' => '88.00',
            'balance_after' => '88.00',
            'reference_id' => 1001,
            'remark' => 'admin user detail regression',
        ]);

        $actualSchema = DB::connection()->getSchemaBuilder();
        $this->resetUserAggregateTableAvailabilityCache();

        Schema::shouldReceive('hasTable')
            ->andReturnUsing(static function (string $table) use ($actualSchema): bool {
                return match ($table) {
                    'account_transactions', 'user_accounts' => false,
                    default => $actualSchema->hasTable($table),
                };
            });

        $token = $admin->createToken('admin-user-detail-regression')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.user.id', (int) $user->id)
            ->assertJsonPath('data.stats.total_income', 88)
            ->assertJsonPath('data.stats.total_expense', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/users/'.$user->id.'/balance-logs?page=1&page_size=15')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.event_type', 'recharge')
            ->assertJsonPath('data.summary.total_income', 88);

        $this->resetUserAggregateTableAvailabilityCache();
    }

    private function resetUserAggregateTableAvailabilityCache(): void
    {
        $reflection = new \ReflectionClass(User::class);

        foreach (['profileTableAvailable', 'accountTableAvailable'] as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue(null, null);
        }
    }
}
