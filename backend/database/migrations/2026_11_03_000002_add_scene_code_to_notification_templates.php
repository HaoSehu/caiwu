<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 消息发送体系 S1：把通知模板挂到场景。
 *
 * 映射表是显式常量，一行一条 (channel, code) → (audience, scene)，
 * 不按名称猜测。无法归位的模板写 __unmapped：管理端仍可见、保持原行为，
 * 但不会出现在任何固定场景下。
 *
 * scene_code 只是「模板属于哪个场景」的标记，模板本身仍按 (channel, code) 唯一；
 * 一个场景可以有多个渠道模板，也可以有多个同渠道模板（如第 1/2/3 次支付提醒）。
 */
return new class extends Migration
{
    /**
     * `channel:code` => 场景编码列表。
     *
     * 同一模板可同时服务多个场景（如短信 100013 既用于客户「订单创建」也用于管理员「新订单」），
     * 因此 scene_code 之外还需要场景模板关联来承载一对多；单值列只存主场景，完整关系见
     * 下方的 scene_templates 关联写入。
     *
     * @var array<string, list<string>>
     */
    private const SCENES_BY_TEMPLATE = [
        // ── 客户：验证码与安全 ──
        'email:100001' => ['register_verification'],
        'sms:100001' => ['register_verification'],
        'sms:100002' => ['login_alert'],
        'email:100003' => ['login_alert'],
        'email:100004' => ['security_changed'],
        'sms:100033' => ['security_changed'],
        'email:100002' => ['registration_success'],
        'sms:100030' => ['registration_success'],
        'sms:100029' => ['account_bound'],

        // ── 客户：服务生命周期 ──
        'email:100005' => ['service_activated'],
        'sms:100025' => ['service_activated'],
        'email:100009' => ['service_reinstalled'],
        'email:100006' => ['service_suspended'],
        'sms:100006' => ['service_suspended'],
        'sms:100027' => ['service_suspended'],
        'email:100007' => ['service_resumed'],
        'sms:100007' => ['service_resumed'],
        'email:100029' => ['service_suspend_failed'],
        'email:100008' => ['service_terminated'],
        'sms:100026' => ['service_terminated'],
        'email:100030' => ['service_transferred'],

        // ── 客户：订单与账单 ──
        'email:100010' => ['order_created'],
        'sms:100013' => ['order_created', 'admin_order_created'],
        'sms:100022' => ['invoice_payment_reminder'],
        'sms:100023' => ['invoice_payment_reminder'],
        'sms:100024' => ['invoice_payment_reminder'],
        'email:100011' => ['invoice_payment_reminder'],
        'email:100012' => ['invoice_payment_reminder'],
        'email:100013' => ['invoice_payment_reminder'],
        'sms:100004' => ['invoice_payment_reminder'],
        'sms:100005' => ['invoice_payment_reminder'],
        'email:100016' => ['order_paid'],
        'sms:100009' => ['order_paid'],
        'sms:100014' => ['order_paid', 'admin_order_paid'],
        'email:100017' => ['order_refunded'],
        'sms:100021' => ['order_refunded'],
        'email:100014' => ['auto_renew_upcoming'],
        'sms:100003' => ['auto_renew_upcoming'],
        'sms:100020' => ['auto_renew_upcoming'],
        'email:100015' => ['auto_renew_notice'],
        'email:100018' => ['service_due'],
        'email:100019' => ['service_due'],
        'sms:100031' => ['service_due'],
        'email:100020' => ['credit_invoice_created'],
        'sms:100008' => ['credit_invoice_created'],

        // ── 客户：工单 ──
        'email:100023' => ['ticket_opened'],
        'sms:100010' => ['ticket_opened', 'admin_ticket_created'],
        'sms:100028' => ['ticket_opened'],
        'email:100024' => ['ticket_replied'],
        'sms:100012' => ['ticket_replied', 'admin_ticket_replied'],
        'email:100025' => ['ticket_auto_closed'],

        // ── 管理员 ──
        'email:100021' => ['admin_order_created'],
        'email:100022' => ['admin_order_paid'],
        'email:100026' => ['admin_ticket_created'],
        'email:100027' => ['admin_ticket_replied'],
        'email:100028' => ['admin_login_alert'],
    ];

    private const UNMAPPED = '__unmapped';

    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        if (! Schema::hasColumn('notification_templates', 'scene_code')) {
            Schema::table('notification_templates', function (Blueprint $table): void {
                $table->string('scene_code', 64)->nullable()->after('audience')->comment('所属消息场景编码；未映射为 __unmapped');
            });
        }

        if (! Schema::hasIndex('notification_templates', 'notification_templates_scene_index')) {
            Schema::table('notification_templates', function (Blueprint $table): void {
                $table->index(['scene_code', 'channel'], 'notification_templates_scene_index');
            });
        }

        if (! Schema::hasTable('message_scene_templates')) {
            Schema::create('message_scene_templates', function (Blueprint $table): void {
                $table->comment('消息场景与通知模板关联表，承载一个场景多渠道多模板关系');
                $table->id();
                $table->string('audience', 16)->comment('customer 客户、admin 管理员');
                $table->string('scene_code', 64)->comment('消息场景编码');
                $table->string('channel', 20)->comment('模板渠道：email、sms');
                $table->string('template_code', 64)->comment('通知模板编码');
                $table->unsignedInteger('sort_order')->default(0)->comment('同场景同渠道内的候选顺序');
                $table->timestamps();

                $table->unique(['audience', 'scene_code', 'channel', 'template_code'], 'message_scene_templates_unique');
                $table->index(['scene_code', 'channel'], 'message_scene_templates_scene_channel_idx');
            });
        }

        // 先全部置为未映射，再按显式映射覆盖：新增模板不会因为漏登记而落到错误场景。
        DB::table('notification_templates')->update(['scene_code' => self::UNMAPPED]);

        $now = now();
        foreach (self::SCENES_BY_TEMPLATE as $key => $scenes) {
            [$channel, $code] = explode(':', $key, 2);

            DB::table('notification_templates')
                ->where('channel', $channel)
                ->where('code', $code)
                ->update(['scene_code' => $scenes[0]]);

            $audience = DB::table('notification_templates')
                ->where('channel', $channel)
                ->where('code', $code)
                ->value('audience');

            if ($audience === null) {
                continue;
            }

            foreach ($scenes as $index => $scene) {
                DB::table('message_scene_templates')->insertOrIgnore([
                    'audience' => $this->isAdminScene($scene) ? 'admin' : 'customer',
                    'scene_code' => $scene,
                    'channel' => $channel,
                    'template_code' => $code,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /** 管理员侧场景以 admin_ 前缀命名，其余按客户侧处理 */
    private function isAdminScene(string $scene): bool
    {
        return str_starts_with($scene, 'admin_');
    }

    public function down(): void
    {
        Schema::dropIfExists('message_scene_templates');

        if (! Schema::hasTable('notification_templates') || ! Schema::hasColumn('notification_templates', 'scene_code')) {
            return;
        }

        if (Schema::hasIndex('notification_templates', 'notification_templates_scene_index')) {
            Schema::table('notification_templates', function (Blueprint $table): void {
                $table->dropIndex('notification_templates_scene_index');
            });
        }

        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->dropColumn('scene_code');
        });
    }
};
