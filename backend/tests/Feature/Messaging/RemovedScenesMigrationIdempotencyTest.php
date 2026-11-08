<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 四个场景移除邮件/短信迁移（2026_11_07_000001）的幂等性守卫。
 *
 * 缺陷背景：早期实现先删模板、再按「删除前取到的模板编号」清 settings。
 * 迁移中途失败后重跑时模板已不存在、取不到编号，settings 残留键永久清不掉。
 * 这里锁死「无论模板在不在，settings 清理都能执行」。
 */
class RemovedScenesMigrationIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    private function migration(): object
    {
        /** @var object $migration */
        $migration = require base_path('database/migrations/2026_11_07_000001_remove_email_sms_channels_for_four_scenes.php');

        return $migration;
    }

    public function test_模板已不存在时迁移仍能清理残留设置键(): void
    {
        // 造出「上一次迁移已删模板、但 settings 没清掉」的现场
        DB::table('settings')->insertOrIgnore([
            'group_key' => 'notification',
            'item_key' => 'email_template_subject_100064',
            'item_value' => '残留值',
        ]);

        // 确认模板确实已不存在（迁移已跑过或模板被手工删掉）
        $this->assertSame(
            0,
            DB::table('notification_templates')
                ->where('channel', 'email')
                ->where('code', '100064')
                ->count()
        );

        $this->migration()->up();

        $this->assertSame(
            0,
            DB::table('settings')->where('item_key', 'email_template_subject_100064')->count(),
            '模板不存在时也必须清掉残留设置键'
        );
    }

    public function test_重复执行不报错(): void
    {
        $migration = $this->migration();

        $migration->up();
        $migration->up();

        // 四个场景仍只剩站内消息渠道
        $this->assertSame(
            0,
            DB::table('message_scene_templates')
                ->whereIn('scene_code', ['credit_invoice_created', 'real_name_rejected', 'ticket_reopened', 'service_reinstalled'])
                ->whereIn('channel', ['email', 'sms'])
                ->count()
        );
    }

    public function test_迁移不触碰四个场景的站内消息渠道(): void
    {
        $before = DB::table('message_scene_templates')
            ->whereIn('scene_code', ['credit_invoice_created', 'real_name_rejected', 'ticket_reopened', 'service_reinstalled'])
            ->where('channel', 'message')
            ->pluck('template_code')
            ->all();

        $this->migration()->up();

        $after = DB::table('message_scene_templates')
            ->whereIn('scene_code', ['credit_invoice_created', 'real_name_rejected', 'ticket_reopened', 'service_reinstalled'])
            ->where('channel', 'message')
            ->pluck('template_code')
            ->all();

        $this->assertSame($before, $after, '站内消息关联不得受影响');
    }
}
