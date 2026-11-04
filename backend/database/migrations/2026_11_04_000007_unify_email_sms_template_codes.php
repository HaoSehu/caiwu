<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 彻底统一邮件与短信模板编号，并按模板名重建场景关联。
 *
 * 背景：邮件模板按业务顺序编号（100001–100030），短信沿用更早 ZJMF 的编号，
 * 同一个号在两边指向完全不同的消息（如 100021 邮件=管理员新订单、短信=订单退款）。
 *
 * 做法：**以模板名为唯一锚点**——每个短信模板按名字确定它该属于哪个场景、
 * 该用哪个编号（与同场景邮件模板的首档一致），一次性重写编号与关联。
 * 这样迁移天然幂等：无论当前数据被改过几次，结果都由「名字 → 目标编号」决定。
 *
 * 扩展号段 100049+ 用于一对多场景的补充档位（一个场景多档提醒时，首档对齐邮件号）。
 */
return new class extends Migration
{
    /**
     * 短信模板名 => 目标编号。
     *
     * 目标编号以同场景邮件模板为准；一对多场景的补充档位用 100049+。
     *
     * @var array<string, string>
     */
    private const SMS_CODE_BY_NAME = [
        // 验证码与安全
        '发送验证码' => '100001',
        '注册成功' => '100002',
        '登录短信提醒' => '100003',
        '账号绑定提示（用户）' => '100004',
        '成功绑定提醒(客户)' => '100048',
        '实名认证通过提醒（用户）' => '100044',
        // 服务生命周期
        '产品开通提醒(用户)' => '100005',
        '产品暂停' => '100006',
        '未实名暂停产品' => '100049',
        '解除暂停提醒(用户)' => '100007',
        '未续期产品删除提醒(用户)' => '100008',
        '系统重装成功' => '100009',
        '解除停用失败' => '100029',
        '实例归属变更' => '100030',
        '续费成功提醒(用户)' => '100018',
        '服务已到期通知(用户)' => '100046',
        // 订单与账单
        '下单提醒(客户)' => '100010',
        '未支付账单' => '100011',
        '第3次逾期提醒' => '100012',
        '账单支付逾期' => '100013',
        '第2次逾期提醒' => '100060',
        '第1次逾期提醒' => '100061',
        '账单支付' => '100016',
        '订单支付提醒(客户)' => '100050',
        '订单退款' => '100017',
        '账单未付款提醒' => '100014',
        '自动生成续费账单提醒' => '100051',
        '自动续费通知' => '100015',
        '信用额账单提醒' => '100020',
        // 工单
        '提交工单' => '100023',
        '工单已开通提醒(客户)' => '100062',
        '工单回复' => '100024',
        '工单自动关闭' => '100025',
        // 管理员
        '新订单提醒(管理员)' => '100021',
        '订单支付提醒(管理员)' => '100022',
        '新工单提醒(管理员)' => '100026',
        '工单回复提醒(管理员)' => '100027',
        '管理员登录提示' => '100028',
        // 后续迁移新增的模板：保持各自分配的编号，显式登记避免依赖「保持原号」兜底
        '实名认证未通过(用户)' => '100045',
        '工单待处理提醒(用户)' => '100063',
        '工单重新开启(用户)' => '100064',
    ];

    /**
     * 场景 => [渠道 => [模板名...]]，关联表按此重建（只重建短信侧）。
     *
     * @var array<string, list<string>>
     */
    private const SMS_BY_SCENE = [
        'customer/register_verification' => ['发送验证码'],
        'customer/registration_success' => ['注册成功'],
        'customer/login_alert' => ['登录短信提醒'],
        'customer/security_changed' => ['账号绑定提示（用户）'],
        'customer/account_bound' => ['成功绑定提醒(客户)'],
        'customer/real_name_approved' => ['实名认证通过提醒（用户）'],
        'customer/service_activated' => ['产品开通提醒(用户)'],
        'customer/service_suspended' => ['产品暂停', '未实名暂停产品'],
        'customer/service_resumed' => ['解除暂停提醒(用户)'],
        'customer/service_terminated' => ['未续期产品删除提醒(用户)'],
        'customer/service_reinstalled' => ['系统重装成功'],
        'customer/service_suspend_failed' => ['解除停用失败'],
        'customer/service_transferred' => ['实例归属变更'],
        'customer/service_due' => ['续费成功提醒(用户)'],
        'customer/service_expired' => ['服务已到期通知(用户)'],
        'customer/order_created' => ['下单提醒(客户)'],
        'customer/invoice_payment_reminder' => ['未支付账单', '第3次逾期提醒', '账单支付逾期', '第2次逾期提醒', '第1次逾期提醒'],
        'customer/order_paid' => ['账单支付', '订单支付提醒(客户)'],
        'customer/order_refunded' => ['订单退款'],
        'customer/auto_renew_upcoming' => ['账单未付款提醒', '自动生成续费账单提醒'],
        'customer/auto_renew_notice' => ['自动续费通知'],
        'customer/credit_invoice_created' => ['信用额账单提醒'],
        'customer/ticket_opened' => ['提交工单', '工单已开通提醒(客户)'],
        'customer/ticket_replied' => ['工单回复'],
        'customer/ticket_auto_closed' => ['工单自动关闭'],
        'admin/admin_order_created' => ['新订单提醒(管理员)'],
        'admin/admin_order_paid' => ['订单支付提醒(管理员)'],
        'admin/admin_ticket_created' => ['新工单提醒(管理员)'],
        'admin/admin_ticket_replied' => ['工单回复提醒(管理员)'],
        'admin/admin_login_alert' => ['管理员登录提示'],
    ];

    private const TEMP_BASE = 900000;

    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $this->renumberSmsTemplates();
        $this->rebuildSmsSceneLinks();
    }

    /**
     * 按名字把每条短信模板重排到目标编号。整表先进临时号段再落位，避免连锁覆盖。
     */
    private function renumberSmsTemplates(): void
    {
        $rows = DB::table('notification_templates')
            ->where('channel', 'sms')
            ->get(['id', 'code', 'name']);

        if ($rows->isEmpty()) {
            return;
        }

        // 目标编号必须唯一：两个模板重名（管理员可改名）会算出同一个 code，
        // 落在同一行唯一键上会让整个迁移崩在临时号段，所有短信模板失效。
        // 提前检测并中止，把问题暴露在部署前而不是部署中。
        $targets = [];
        foreach ($rows as $row) {
            $target = self::SMS_CODE_BY_NAME[(string) $row->name] ?? (string) $row->code;
            if (isset($targets[$target])) {
                throw new RuntimeException(sprintf(
                    '短信模板编号冲突：模板「%s」与「%s」都会映射到 %s，请先修正重名后重跑迁移',
                    $targets[$target],
                    (string) $row->name,
                    $target
                ));
            }
            $targets[$target] = (string) $row->name;
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $index => $row) {
                DB::table('notification_templates')
                    ->where('id', $row->id)
                    ->update(['code' => (string) (self::TEMP_BASE + $index), 'updated_at' => now()]);
            }

            foreach ($rows as $row) {
                $target = self::SMS_CODE_BY_NAME[(string) $row->name] ?? (string) $row->code;

                DB::table('notification_templates')
                    ->where('id', $row->id)
                    ->update(['code' => $target, 'updated_at' => now()]);
            }
        });
    }

    /**
     * 按场景重建短信关联（邮件关联不动）。
     */
    private function rebuildSmsSceneLinks(): void
    {
        if (! Schema::hasTable('message_scene_templates')) {
            return;
        }

        $now = now();

        foreach (self::SMS_BY_SCENE as $key => $names) {
            [$audience, $scene] = explode('/', $key, 2);

            DB::table('message_scene_templates')
                ->where('audience', $audience)
                ->where('scene_code', $scene)
                ->where('channel', 'sms')
                ->delete();

            foreach ($names as $index => $name) {
                $code = DB::table('notification_templates')
                    ->where('channel', 'sms')
                    ->where('name', $name)
                    ->value('code');

                if ($code === null) {
                    continue;
                }

                DB::table('message_scene_templates')->insertOrIgnore([
                    'audience' => $audience,
                    'scene_code' => $scene,
                    'channel' => 'sms',
                    'template_code' => (string) $code,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // 编号统一与关联重建是前向数据修正，不提供回滚。
    }
};
