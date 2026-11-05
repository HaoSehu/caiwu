<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\VerificationService;
use App\Services\Verification\VerificationDriverManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\Verification\FakeVerificationDriver;
use Tests\TestCase;

/**
 * 「真实姓名 > 自定义」回归：
 * 1. 实名通过时把 nickname 落成真实姓名；
 * 2. 已是已认证状态时再次同步，不得覆盖用户自己改过的昵称；
 * 3. 用户改回自定义昵称后 display_name 跟随昵称，真实姓名不再压过它；
 * 4. 实名解绑不回滚昵称。
 */
class VerifiedUserNicknameTest extends TestCase
{
    use DatabaseTransactions;

    public function test_实名通过时昵称落成真实姓名(): void
    {
        $driver = new FakeVerificationDriver;
        app(VerificationDriverManager::class)->register($driver);

        $user = $this->makeUser('自定义昵称');
        $service = app(VerificationService::class);

        $service->startVerificationSession($user, '张三', '110101199001011234');
        $synced = $service->syncUserStatus($user->refresh(), ['status' => 1, 'msg' => '审核通过'], 'cert-fake-001');

        $this->assertSame('张三', (string) $synced->nickname, '实名通过应把用户名落成真实姓名');
        $this->assertSame('张三', $synced->display_name, '展示名与实名一致');
    }

    public function test_已认证用户再次同步不覆盖自定义昵称(): void
    {
        $driver = new FakeVerificationDriver;
        app(VerificationDriverManager::class)->register($driver);

        $user = $this->makeUser('自定义昵称');
        $service = app(VerificationService::class);

        $service->startVerificationSession($user, '李四', '110101199001011234');
        $service->syncUserStatus($user->refresh(), ['status' => 1, 'msg' => '审核通过'], 'cert-fake-001');

        // 用户在个人中心改回自定义名称
        $user->refresh()->update(['nickname' => '我的昵称']);
        $this->assertSame('我的昵称', $user->display_name);

        // 轮询再次命中同一 certify_id（成功态请求重复）：昵称不得被改回真实姓名
        $resynced = $service->syncUserStatus($user->refresh(), ['status' => 1, 'msg' => '审核通过'], 'cert-fake-001');

        $this->assertSame('我的昵称', (string) $resynced->nickname, '已认证状态下重复同步必须保留用户昵称');
        $this->assertSame('我的昵称', $resynced->display_name);
    }

    public function test_展示名昵称优先真实姓名兜底(): void
    {
        $user = $this->makeUser('空占位');
        $user->forceFill([
            'nickname' => '',
            'real_name' => '王五',
            'verification_status' => 2,
            'is_verified' => 1,
        ])->save();

        $this->assertSame('王五', $user->refresh()->display_name, '昵称为空时回落已实名的真实姓名');
    }

    public function test_未实名时昵称优先于真实姓名(): void
    {
        $user = $this->makeUser('注册昵称');
        $user->forceFill([
            'real_name' => '残留姓名',
            'verification_status' => 0,
            'is_verified' => 0,
        ])->save();

        $this->assertSame('注册昵称', $user->refresh()->display_name, '未实名时展示名必须是昵称');
    }

    public function test_解绑实名不回滚昵称(): void
    {
        $driver = new FakeVerificationDriver;
        app(VerificationDriverManager::class)->register($driver);

        $user = $this->makeUser('自定义昵称');
        $service = app(VerificationService::class);
        $service->startVerificationSession($user, '赵六', '110101199001011234');
        $service->syncUserStatus($user->refresh(), ['status' => 1, 'msg' => '审核通过'], 'cert-fake-001');

        $service->unbind($user->refresh(), null, null, '管理员解绑');

        $this->assertSame('赵六', (string) $user->refresh()->nickname, '解绑后昵称保持实名时落下的值');
    }

    public function test_快照缺少真实姓名时不写空昵称(): void
    {
        $user = $this->makeUser('原昵称');
        $service = app(VerificationService::class);

        // 直接调用同步：快照 real_name 为空（例如历史异常数据）时不得清空昵称
        $synced = $service->syncUserStatus($user, ['status' => 1, 'msg' => '审核通过'], null);

        $this->assertSame('原昵称', (string) $synced->nickname, '没有真实姓名时不得把昵称写成空值');
    }

    private function makeUser(string $nickname): User
    {
        return User::query()->create([
            'email' => 'nickname-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $nickname,
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }
}
