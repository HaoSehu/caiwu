<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\AdminUser;
use App\Models\Role;
use App\Services\Messaging\MessageConfigurationRepository;
use App\Services\Messaging\MessageSceneQueryService;
use App\Services\Messaging\MessageTransport;
use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 代码审查发现的缺陷回归。
 *
 * 覆盖四项：
 * 1. 站内消息必须真正写入 user_notifications，不能只把投递记为成功；
 * 2. 批量配置必须原子，中途失败不得留下部分更新；
 * 3. 消息配置列表不得产生 N+1（场景数增长时查询次数保持常数）；
 * 4. 模板编号映射不得出现目标冲突。
 */
class MessageDeliveryIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_站内消息真正写入用户通知表(): void
    {
        $user = DB::table('users')->insertGetId([
            'email' => 'inbox-'.uniqid('', true).'@example.test',
            'password' => bcrypt('secret'),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = DB::table('user_notifications')->where('user_id', $user)->count();

        $transport = app(MessageTransport::class);
        $result = $transport->deliverInbox([
            'account_id' => $user,
            'scene' => 'order_paid',
            'title' => '订单支付成功',
            'body' => '您的订单已支付。',
        ]);

        $this->assertSame('delivered', $result->status, '站内消息应返回 delivered');
        $this->assertSame(
            $before + 1,
            DB::table('user_notifications')->where('user_id', $user)->count(),
            '站内消息必须真正写入 user_notifications，不能只标记投递成功'
        );
    }

    public function test_站内消息缺少收件人时明确失败(): void
    {
        $transport = app(MessageTransport::class);

        $missingUser = $transport->deliverInbox(['title' => '标题', 'body' => '正文']);
        $this->assertSame('failed', $missingUser->status, '缺 account_id 不能当作送达');

        $missingTitle = $transport->deliverInbox(['account_id' => 1, 'title' => '']);
        $this->assertSame('failed', $missingTitle->status, '空标题不能当作送达');
    }

    public function test_批量配置失败时整批回滚(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::SETTINGS_MANAGE]));

        DB::table('message_configurations')
            ->where('audience', 'customer')
            ->where('message_type', 'order_paid')
            ->delete();

        $before = app(MessageConfigurationRepository::class)->get('customer', 'order_paid')['revision'];

        // 第二条使用不存在的场景，必然抛异常 → 整批应回滚，第一条也不得保存
        $response = $this->postJson('/api/v2/admin/message-scenes/batch', [
            'audience' => 'customer',
            'channel' => 'email',
            'enabled' => true,
            'items' => [
                ['code' => 'order_paid', 'revision' => $before],
                ['code' => 'scene_that_does_not_exist', 'revision' => 0],
            ],
        ]);

        $this->assertGreaterThanOrEqual(400, $response->status(), '非法场景应使整批失败');
        $this->assertSame(
            $before,
            app(MessageConfigurationRepository::class)->get('customer', 'order_paid')['revision'],
            '批量失败后第一条不应被保存（整批回滚）'
        );
    }

    public function test_消息配置列表查询次数不随场景数增长(): void
    {
        $service = app(MessageSceneQueryService::class);

        DB::enableQueryLog();
        $rows = $service->list('customer');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertNotEmpty($rows);
        // 修复前为 350 次（每场景重复查模板渠道与插件能力）
        $this->assertLessThan(
            20,
            $count,
            "消息配置列表产生了 {$count} 次查询，疑似 N+1 回归"
        );
    }

    public function test_模板编号映射无目标冲突(): void
    {
        $reflection = new \ReflectionClass(Migration::class);

        // 读取迁移里的映射常量，确认所有目标编号互不相同
        $source = file_get_contents(database_path('migrations/2026_11_04_000007_unify_email_sms_template_codes.php'));
        $this->assertNotFalse($source);

        preg_match('/private const SMS_CODE_BY_NAME = \[(.*?)\n    \];/s', $source, $matches);
        $this->assertNotEmpty($matches[1] ?? '', '未能从迁移中解析出编号映射表');

        preg_match_all("/'([^']+)' => '(\d+)'/", $matches[1], $pairs, PREG_SET_ORDER);

        $targets = [];
        $conflicts = [];
        foreach ($pairs as [, $name, $code]) {
            if (isset($targets[$code])) {
                $conflicts[] = "{$code}（{$targets[$code]} / {$name}）";
            }
            $targets[$code] = $name;
        }

        $this->assertSame([], $conflicts, '模板编号映射存在重复目标：'.implode('、', $conflicts));
        $this->assertGreaterThan(30, count($pairs), '映射表条目过少，疑似被截断');
    }

    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '消息投递完整性测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '消息投递完整性测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
