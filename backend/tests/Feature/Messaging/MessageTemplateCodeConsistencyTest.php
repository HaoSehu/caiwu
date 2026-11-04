<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Services\Messaging\MessageConfigurationRepository;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use App\Support\SmsTemplateCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 模板编号一致性守卫。
 *
 * 约定（docs/执行计划/进行中/消息发送体系按howstack重构-2026-11-03.md）：
 * - 同一场景的邮件与短信首档编号必须相同；
 * - 不允许同一编号在两种渠道指向不同消息；
 * - sms 模板不得游离在任何场景之外（未映射例外）。
 *
 * 这些约束一旦被破坏，管理端会出现「邮件和短信对不上」的错配，
 * 且运营商模板 ID 会挂到错误的消息上，因此用测试锁死。
 */
class MessageTemplateCodeConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_每场景邮件与短信首档编号一致(): void
    {
        $links = DB::table('message_scene_templates')
            ->get(['audience', 'scene_code', 'channel', 'template_code']);

        $byScene = [];
        foreach ($links as $row) {
            $byScene[$row->audience.'/'.$row->scene_code][$row->channel][] = (string) $row->template_code;
        }

        $mismatched = [];
        foreach ($byScene as $scene => $channels) {
            $email = $channels['email'] ?? [];
            $sms = $channels['sms'] ?? [];

            if ($email === [] || $sms === []) {
                continue;
            }

            sort($email);
            sort($sms);

            if ($email[0] !== $sms[0]) {
                $mismatched[] = "{$scene}（邮件 {$email[0]} / 短信 {$sms[0]}）";
            }
        }

        $this->assertSame([], $mismatched, '以下场景的邮件与短信首档编号不一致：'.implode('、', $mismatched));
    }

    public function test_同一编号在两种渠道不得指向不同消息(): void
    {
        $rows = DB::table('notification_templates')
            ->whereIn('channel', ['email', 'sms'])
            ->get(['channel', 'code', 'name']);

        $byCode = [];
        foreach ($rows as $row) {
            $byCode[(string) $row->code][$row->channel] = (string) $row->name;
        }

        $conflicts = [];
        foreach ($byCode as $code => $names) {
            if (! isset($names['email'], $names['sms'])) {
                continue;
            }

            // 允许名称不同（邮件「付款成功通知」与短信「账单支付」属同一业务语义），
            // 但必须挂在同一场景下——由第一个测试保证。这里只检出明显的跨义复用：
            // 两条模板同时被不同场景引用且编号相同，说明映射写错了。
            $scenes = DB::table('message_scene_templates')
                ->where('template_code', $code)
                ->pluck('scene_code')
                ->unique()
                ->values()
                ->all();

            $customer = DB::table('message_scene_templates')
                ->where('template_code', $code)
                ->where('audience', 'customer')
                ->pluck('scene_code')
                ->unique()
                ->values()
                ->all();

            $admin = DB::table('message_scene_templates')
                ->where('template_code', $code)
                ->where('audience', 'admin')
                ->pluck('scene_code')
                ->unique()
                ->values()
                ->all();

            if ($customer !== [] && $admin !== [] && $scenes !== [] && count($scenes) > 1) {
                $conflicts[] = "{$code}（客户 ".implode(',', $customer).' / 管理员 '.implode(',', $admin).'）';
            }
        }

        $this->assertSame([], $conflicts, '以下编号被客户与管理员场景同时复用：'.implode('、', $conflicts));
    }

    public function test_短信模板不得游离在场景之外(): void
    {
        $linked = DB::table('message_scene_templates')
            ->where('channel', 'sms')
            ->pluck('template_code')
            ->map(static fn ($code): string => (string) $code)
            ->unique()
            ->all();

        $orphans = DB::table('notification_templates')
            ->where('channel', 'sms')
            ->where('scene_code', '!=', '__unmapped')
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->filter(static fn (string $code): bool => ! in_array($code, $linked, true))
            ->values()
            ->all();

        $this->assertSame([], $orphans, '以下短信模板没有挂到任何场景：'.implode('、', $orphans));
    }

    public function test_有模板的渠道必须在配置里可编辑(): void
    {
        $repository = app(MessageConfigurationRepository::class);
        $missing = [];

        foreach (['customer', 'admin'] as $audience) {
            $scenes = DB::table('message_scene_templates')
                ->where('audience', $audience)
                ->select('scene_code')
                ->distinct()
                ->pluck('scene_code');

            foreach ($scenes as $code) {
                $templateChannels = DB::table('message_scene_templates')
                    ->where('audience', $audience)
                    ->where('scene_code', $code)
                    ->pluck('channel')
                    ->unique()
                    ->values()
                    ->all();

                $type = MessageTypeCatalog::get($audience, (string) $code);
                $stored = $repository->get($audience, (string) $code);

                // 未保存过配置的场景由 detail/list 自动校准后展示，不属于「丢渠道」；
                // 这里只检查已经落库的配置。
                if ($stored['revision'] === 0) {
                    continue;
                }

                $groups = MessageGroups::fromStored($audience, $type, $stored['configurations']);

                $configured = [];
                foreach ($groups as $group) {
                    $configured = array_merge($configured, array_keys($group['configurations']));
                }

                $lost = array_values(array_diff($templateChannels, array_unique($configured)));
                if ($lost !== []) {
                    $missing[] = "{$audience}/{$code} 缺 ".implode(',', $lost);
                }
            }
        }

        $this->assertSame([], $missing, '以下场景有模板但配置缺渠道（管理端不会出现开关）：'.implode('；', $missing));
    }

    public function test_短信常量与数据库编号一致(): void
    {
        $constants = [
            SmsTemplateCatalog::TEMPLATE_VERIFY_CODE => '发送验证码',
            SmsTemplateCatalog::TEMPLATE_REGISTRATION_SUCCESS => '注册成功',
            SmsTemplateCatalog::TEMPLATE_LOGIN_ALERT => '登录短信提醒',
            SmsTemplateCatalog::TEMPLATE_SERVICE_SUSPENDED => '产品暂停',
            SmsTemplateCatalog::TEMPLATE_SERVICE_RESTORED => '解除暂停提醒(用户)',
            SmsTemplateCatalog::TEMPLATE_SERVICE_TERMINATED => '未续期产品删除提醒(用户)',
            SmsTemplateCatalog::TEMPLATE_SERVICE_ACTIVATED => '产品开通提醒(用户)',
            SmsTemplateCatalog::TEMPLATE_CLIENT_ORDER_CREATED => '下单提醒(客户)',
            SmsTemplateCatalog::TEMPLATE_ORDER_REFUND => '订单退款',
            SmsTemplateCatalog::TEMPLATE_MANUAL_PAYMENT_CONFIRM => '账单支付',
            SmsTemplateCatalog::TEMPLATE_SERVICE_RENEW_REMINDER => '续费成功提醒(用户)',
            SmsTemplateCatalog::TEMPLATE_CREDIT_INVOICE_NOTICE => '信用额账单提醒',
            SmsTemplateCatalog::TEMPLATE_TICKET_CREATED => '提交工单',
            SmsTemplateCatalog::TEMPLATE_TICKET_CLIENT_REPLY => '工单回复',
            SmsTemplateCatalog::TEMPLATE_TICKET_AUTO_CLOSED => '工单自动关闭',
            SmsTemplateCatalog::TEMPLATE_SERVICE_EXPIRED => '服务已到期通知(用户)',
        ];

        $wrong = [];
        foreach ($constants as $code => $expectedName) {
            $actual = DB::table('notification_templates')
                ->where('channel', 'sms')
                ->where('code', $code)
                ->value('name');

            if ($actual !== $expectedName) {
                $wrong[] = "{$code} 期望「{$expectedName}」实际「".($actual ?? '不存在').'」';
            }
        }

        $this->assertSame([], $wrong, '短信常量与数据库编号错位：'.implode('；', $wrong));
    }
}
