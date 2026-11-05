<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 自动续费站内模板「已完成」→「账单已生成」。
 *
 * BillingAutomationService::createRenewOrders 只生成续费账单（UNPAID），
 * 真正扣款由 AutoRenewService 异步执行且可能因余额不足失败；原模板写「已完成
 * 自动续费」会让用户误以为已扣款。与邮件模板 100015 的「已生成账单，请确保
 * 余额充足」口径保持一致。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        DB::table('notification_templates')
            ->where('channel', 'message')
            ->where('code', '100015')
            ->update([
                'subject' => '自动续费账单已生成',
                'content' => '您的服务「{{service_name}}」的自动续费账单已生成，账单号 {{invoice_no}}，金额 {{amount}} 元，请确保账户余额充足以便自动扣款。',
            ]);
    }

    public function down(): void
    {
        // 文案修正不回滚。
    }
};
