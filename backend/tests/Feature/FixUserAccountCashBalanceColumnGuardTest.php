<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAccount;
use App\Services\User\AccountService;
use App\Services\User\UserAccountProjectionService;
use App\Support\SchemaMetadataCache;
use Illuminate\Database\Schema\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * M-71 回归：T+7 删列（000015）后两个默认账户插入点不得再携带 user_accounts.cash_balance。
 *
 * 缺陷背景：AccountService::defaultAttributes()（firstOrCreate 新建账户）与
 * UserAccountProjectionService::buildAccountPayload()（account:backfill-user-accounts
 * 的 insertOrIgnore）曾无条件写 cash_balance。000015 把这两个文件列入白名单并声称
 * 「已有列存在性判断」，但两文件当时没有任何 hasColumn 守卫；删列成功后新建用户、
 * 返利首次触达、账户回填全部 SQLSTATE 42S22。
 *
 * 修复后用 Schema 局部 mock 模拟「列存在 / 列不存在」两种现场，断言两个方法
 * 返回的键集合：列存在时含 cash_balance 且默认 '0.00'，列不存在时不含该键。
 */
class FixUserAccountCashBalanceColumnGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // SchemaMetadataCache 是进程级静态缓存，跨用例残留；先清空再用 mock 造现场。
        SchemaMetadataCache::flush();
    }

    protected function tearDown(): void
    {
        SchemaMetadataCache::flush();

        parent::tearDown();
    }

    #[Test]
    public function default_attributes_include_cash_balance_when_column_exists(): void
    {
        $this->fakeUserAccountsColumns($this->fullColumns());

        $attributes = $this->defaultAttributes();

        $this->assertArrayHasKey('cash_balance', $attributes);
        $this->assertSame('0.00', $attributes['cash_balance']);
        $this->assertSame('0.00', $attributes['credit_limit']);
        $this->assertSame(0, $attributes['version']);
    }

    #[Test]
    public function default_attributes_omit_cash_balance_after_column_drop(): void
    {
        $this->fakeUserAccountsColumns($this->columnsWithoutCashBalance());

        $attributes = $this->defaultAttributes();

        $this->assertArrayNotHasKey('cash_balance', $attributes, '删列后仍携带 cash_balance 会 SQLSTATE 42S22');
        $this->assertSame('0.00', $attributes['credit_limit']);
        $this->assertSame('0.00', $attributes['referral_available_balance']);
        $this->assertSame(0, $attributes['version']);
    }

    #[Test]
    public function build_account_payload_includes_cash_balance_when_column_exists(): void
    {
        $this->fakeUserAccountsColumns($this->fullColumns());

        $payload = $this->buildAccountPayload(7);

        $this->assertArrayHasKey('cash_balance', $payload);
        $this->assertSame('0.00', $payload['cash_balance']);
        $this->assertSame(7, $payload['user_id']);
        $this->assertArrayHasKey('created_at', $payload);
    }

    #[Test]
    public function build_account_payload_omits_cash_balance_after_column_drop(): void
    {
        $this->fakeUserAccountsColumns($this->columnsWithoutCashBalance());

        $payload = $this->buildAccountPayload(7);

        $this->assertArrayNotHasKey('cash_balance', $payload, '删列后 insertOrIgnore 仍携带该键会 SQLSTATE 42S22');
        $this->assertSame(7, $payload['user_id']);
        $this->assertSame('0.00', $payload['referral_frozen_balance']);
    }

    #[Test]
    public function ensure_account_inserts_default_row_without_cash_balance_key(): void
    {
        // 端到端：模拟删列后新建用户（UserService::create → ensureAccount 的主路径），
        // 插入不得携带 cash_balance：热备期列有默认 0.00，删列后行内无该属性。
        $user = $this->makeUser();
        $this->fakeUserAccountsColumns($this->columnsWithoutCashBalance());

        $account = app(AccountService::class)->ensureAccount($user);

        $this->assertInstanceOf(UserAccount::class, $account);
        $this->assertSame((int) $user->id, (int) $account->user_id);

        $persisted = UserAccount::query()->find((int) $user->id);
        $this->assertNotNull($persisted, 'ensureAccount 未落库账户行');

        // 落库值按测试库真实结构断言：热备期列存在则取默认 0.00，已删列则属性不存在。
        $columnExists = DB::connection()->getSchemaBuilder()->hasColumn('user_accounts', 'cash_balance');
        if ($columnExists) {
            $this->assertSame('0.00', (string) $persisted->cash_balance, '列默认值应为 0.00');
        } else {
            $this->assertArrayNotHasKey('cash_balance', $persisted->getAttributes(), '删列后账户行不应携带 cash_balance 属性');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultAttributes(): array
    {
        $method = new ReflectionMethod(AccountService::class, 'defaultAttributes');
        $method->setAccessible(true);

        return $method->invoke(new AccountService);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAccountPayload(int $userId): array
    {
        $method = new ReflectionMethod(UserAccountProjectionService::class, 'buildAccountPayload');
        $method->setAccessible(true);

        return $method->invoke(
            new UserAccountProjectionService,
            (object) ['id' => $userId],
            '2026-11-09 00:00:00',
        );
    }

    /**
     * @return list<string>
     */
    private function fullColumns(): array
    {
        return [
            'user_id',
            'cash_balance',
            'credit_limit',
            'referral_frozen_balance',
            'referral_available_balance',
            'referral_pending_withdrawal_balance',
            'referral_withdrawn_balance',
            'version',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * @return list<string>
     */
    private function columnsWithoutCashBalance(): array
    {
        return array_values(array_diff($this->fullColumns(), ['cash_balance']));
    }

    /**
     * 用局部 mock 替换 Schema facade：只改 user_accounts 的探测结果，其余调用走真实实现。
     * 不用 shouldReceive()->with() 的严格 mock：请求生命周期内其他 Schema 探测（模型事件、
     * 缓存等）会被 with 约束误伤。
     *
     * @param  list<string>  $columns
     */
    private function fakeUserAccountsColumns(array $columns): void
    {
        SchemaMetadataCache::flush();

        $real = Schema::getFacadeRoot();
        $this->assertInstanceOf(Builder::class, $real, 'Schema facade 根对象应为 Schema Builder');

        /** @var Mockery\MockInterface&Builder $mock */
        $mock = Mockery::mock($real)->makePartial();
        $mock->shouldReceive('hasTable')->andReturnUsing(
            static fn (string $table): bool => $table === 'user_accounts' ? true : (bool) $real->hasTable($table)
        );
        $mock->shouldReceive('getColumnListing')->andReturnUsing(
            static fn (string $table): array => $table === 'user_accounts' ? $columns : $real->getColumnListing($table)
        );

        Schema::swap($mock);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'cash-guard-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'cash-guard-tester',
            'total_sales_amount' => 0,
        ]);
    }
}
