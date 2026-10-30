<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 给核心业务表补齐列级中文注释（数据库表结构审查 D-13 第一批）。
 *
 * 背景：79 张表都有表注释，但排除 id、created_at、updated_at、deleted_at 后仍有
 * 506 列没有 COLUMN_COMMENT，字段语义只能靠读代码猜。本批先覆盖资金与履约主链路
 * 的 9 张表共 85 列；其余表按执行计划后续批次补齐。
 *
 * 只做 `ALTER TABLE ... MODIFY COLUMN ... COMMENT` 的元数据级操作。MySQL 没有
 * 「只改注释」的语法，MODIFY COLUMN 必须重述整列定义，因此这里**从实库
 * information_schema 现读当前定义**（类型、字符集/排序规则、可空、默认值、
 * AUTO_INCREMENT、ON UPDATE）再拼上新注释：定义的真源永远是实库，不在本文件
 * 复制一份。曾把定义硬编码在文件里的写法，会在列被后续迁移加宽或默认值变更后
 * 用文件里的旧定义把实库静默改回去。
 *
 * 幂等：先比较 information_schema.COLUMNS.COLUMN_COMMENT，已一致则跳过；
 * 表或列不存在则跳过。定义无法在不改变既有语义的前提下重建时（生成列、bit、
 * 非 CURRENT_TIMESTAMP 的表达式默认值）记 warning 并跳过该列——宁可少补注释，
 * 也不能用残缺定义覆盖整列。
 */
return new class extends Migration
{
    /**
     * 列注释清单，每项为 [表名, 列名, 注释]。
     *
     * 刻意为「元组列表」而非「表 => 列 => 注释」的关联数组：后者会让
     * `'password' => '…'` 这类行被 backend/scripts/sensitive_file_scan.mjs 的
     * 凭据字面量规则（phpArrayAssignment）判成硬编码口令，每次提交都误报。
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const COLUMN_COMMENTS = [
        // 会员账号表
        ['users', 'email', '登录邮箱，全局唯一（软删时释放）'],
        ['users', 'password', '登录密码哈希（框架 hashed 转换）'],
        ['users', 'nickname', '显示昵称，空串=未设置'],
        ['users', 'phone', '手机号，全局唯一（软删时释放）'],
        ['users', 'company', '公司名称，空串=未填写'],
        ['users', 'qq', 'QQ 号，空串=未填写'],
        ['users', 'alipay_real_name', '支付宝收款人姓名（退款打款用）'],
        ['users', 'alipay_account', '支付宝收款账号（退款打款用）'],
        ['users', 'referral_code', '推荐码，全局唯一（软删时释放）'],
        ['users', 'referrer_user_id', '推荐人用户ID，空=无推荐关系'],
        ['users', 'member_level_id', '会员等级ID，空=未分级'],
        ['users', 'total_sales_amount', '该用户累计消费金额，推荐业绩口径'],
        ['users', 'referred_at', '推荐关系建立时间'],
        ['users', 'id_card', '证件号，加密存储（LegacyEncrypted）'],
        ['users', 'last_login_ip', '最近一次登录IP'],
        ['users', 'last_login_at', '最近一次登录时间'],
        ['users', 'admin_note', '管理员备注，空串归一为 null'],
        // 管理员账号表
        ['admin_users', 'username', '管理员登录账号，全局唯一'],
        ['admin_users', 'password', '登录密码哈希（框架 hashed 转换）'],
        ['admin_users', 'role_id', '所属管理员角色ID'],
        ['admin_users', 'nickname', '显示昵称'],
        ['admin_users', 'last_login_at', '最近一次登录成功时间'],
        ['admin_users', 'last_login_ip', '最近一次登录IP'],
        ['admin_users', 'email', '联系邮箱'],
        // 履约工单表
        ['orders', 'order_no', '业务订单号，对外展示与对账使用'],
        ['orders', 'user_id', '下单用户ID'],
        ['orders', 'product_id', '关联商品ID'],
        ['orders', 'product_spec_snapshot', '下单时的商品规格展示快照'],
        ['orders', 'product_type_snapshot', '下单时的商品类型快照'],
        ['orders', 'service_id', '关联服务实例ID'],
        ['orders', 'coupon_id', '使用的优惠券模板ID'],
        ['orders', 'user_coupon_id', '使用的用户优惠券ID'],
        ['orders', 'coupon_code', '使用的优惠码快照'],
        ['orders', 'amount', '订单应收金额'],
        ['orders', 'currency', '币种，固定 CNY'],
        ['orders', 'discount', '订单优惠抵扣金额'],
        ['orders', 'paid_amount', '已支付入账金额'],
        ['orders', 'billing_cycle', '计费周期：monthly/quarterly/semiannually/annually/biennially/triennially/onetime'],
        ['orders', 'quantity', '购买数量'],
        ['orders', 'config_snapshot', '下单配置快照 JSON'],
        ['orders', 'config_pricing_snapshot', '配置项计价快照 JSON'],
        ['orders', 'coupon_snapshot', '优惠券使用快照 JSON'],
        ['orders', 'paid_at', '支付完成时间'],
        // 账单表
        ['invoices', 'origin_invoice_id', '原账单ID：退款或调整红字账单指向被冲正的账单'],
        ['invoices', 'currency', '币种，固定 CNY'],
        ['invoices', 'fulfillment_pending', '已付待履约标记：1=已付款但开通未完成'],
        ['invoices', 'fulfillment_pending_at', '已付待履约标记写入时间，供超时扫描使用'],
        ['invoices', 'fulfillment_cleared_at', '待履约标记清除时间，空=尚未清除'],
        ['invoices', 'requires_refund', '人工退款标记：1=自动履约已放弃，转人工退款'],
        ['invoices', 'due_date', '账单到期日（付款期限）'],
        // 支付记录表
        ['payments', 'plugin_id', '来源支付插件ID'],
        ['payments', 'gateway_key', '支付网关标识快照'],
        ['payments', 'currency', '币种，固定 CNY'],
        // 支付回调记录表
        ['payment_callbacks', 'plugin_id', '来源支付插件ID'],
        ['payment_callbacks', 'gateway_key', '支付网关标识快照'],
        // 充值记录表
        ['recharge_records', 'record_no', '充值流水号，对外展示'],
        ['recharge_records', 'user_id', '充值所属用户ID'],
        ['recharge_records', 'order_id', '关联订单ID'],
        ['recharge_records', 'invoice_id', '关联账单ID'],
        ['recharge_records', 'payment_id', '关联支付记录ID'],
        ['recharge_records', 'account_transaction_id', '关联账户流水ID'],
        ['recharge_records', 'refund_id', '关联退款记录ID'],
        ['recharge_records', 'origin_recharge_record_id', '原充值记录ID：退款红字充值指向被冲正的记录'],
        ['recharge_records', 'amount', '充值金额'],
        ['recharge_records', 'currency', '币种，固定 CNY'],
        ['recharge_records', 'remark', '充值备注'],
        ['recharge_records', 'operator_type', '操作人类型：admin/member/client/system/legacy'],
        ['recharge_records', 'operator_id', '操作人ID，空=系统自动'],
        ['recharge_records', 'operator_name', '操作人名称快照'],
        ['recharge_records', 'trace_id', '链路追踪号'],
        // 退款单表
        ['refunds', 'refund_no', '退款单号，对外展示'],
        ['refunds', 'user_id', '退款所属用户ID'],
        ['refunds', 'invoice_id', '被退款的账单ID'],
        ['refunds', 'refund_invoice_id', '退款红字账单ID'],
        ['refunds', 'payment_id', '关联支付记录ID'],
        ['refunds', 'amount', '退款金额'],
        ['refunds', 'currency', '币种，固定 CNY'],
        ['refunds', 'reason', '退款原因'],
        ['refunds', 'gateway_refund_no', '第三方退款单号'],
        ['refunds', 'operator_type', '操作人类型：admin/member/client/system/legacy'],
        ['refunds', 'operator_id', '操作人ID，空=系统自动'],
        ['refunds', 'operator_name', '操作人名称快照'],
        ['refunds', 'trace_id', '链路追踪号'],
        ['refunds', 'refunded_at', '退款完成时间'],
        // 账户流水表
        ['account_transactions', 'currency', '币种，固定 CNY'],
    ];

    public function up(): void
    {
        foreach (self::COLUMN_COMMENTS as [$table, $column, $comment]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $live = $this->liveColumn($table, $column);

            // 列不存在（历史库结构落后于 schema）或注释已一致时跳过，避免整批 ALTER 中断
            if ($live === null || (string) $live->COLUMN_COMMENT === $comment) {
                continue;
            }

            $definition = $this->definitionFromLive($live);

            if ($definition === null) {
                Log::warning('[列注释迁移] 列定义无法安全重建，已跳过该列', [
                    'table' => $table,
                    'column' => $column,
                    'data_type' => (string) $live->DATA_TYPE,
                    'extra' => (string) $live->EXTRA,
                ]);

                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY COLUMN `%s` %s COMMENT %s',
                $table,
                $column,
                $definition,
                DB::getPdo()->quote($comment)
            ));
        }
    }

    /**
     * 注释属说明性元数据，回滚不还原旧注释：旧注释本就是空值，还原会让字段语义再次丢失。
     */
    public function down(): void
    {
        //
    }

    /**
     * 读取实库当前列定义与注释；列不存在时返回 null。
     */
    private function liveColumn(string $table, string $column): ?object
    {
        return DB::selectOne(
            'SELECT COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',
            [$table, $column]
        );
    }

    /**
     * 用「实库当前定义 + 目标注释」拼出 MODIFY COLUMN 需要的列定义。
     *
     * 返回 null 表示该列无法在不改变既有定义的前提下重建，调用方必须跳过。
     */
    private function definitionFromLive(object $column): ?string
    {
        $dataType = strtolower((string) $column->DATA_TYPE);
        $extra = (string) $column->EXTRA;
        $extraUpper = strtoupper($extra);

        // 生成列的表达式不在 COLUMN_DEFAULT 里，bit 的默认值写法无法稳定还原：一律跳过
        if (str_contains($extraUpper, 'GENERATED') || $dataType === 'bit') {
            return null;
        }

        $default = $column->COLUMN_DEFAULT;
        $isExpressionDefault = str_contains($extraUpper, 'DEFAULT_GENERATED');

        // 表达式默认值只认 CURRENT_TIMESTAMP 家族，其余表达式照抄会写坏定义
        if ($default !== null && $isExpressionDefault
            && preg_match('/^CURRENT_TIMESTAMP(\(\d+\))?$/i', (string) $default) !== 1
        ) {
            return null;
        }

        $definition = (string) $column->COLUMN_TYPE;

        if ($column->CHARACTER_SET_NAME !== null && $column->CHARACTER_SET_NAME !== '') {
            $definition .= ' CHARACTER SET '.$column->CHARACTER_SET_NAME.' COLLATE '.$column->COLLATION_NAME;
        }

        $definition .= $column->IS_NULLABLE === 'YES' ? ' NULL' : ' NOT NULL';

        if ($default !== null) {
            $definition .= ' DEFAULT '.($isExpressionDefault || $this->isNumericType($dataType)
                ? (string) $default
                : DB::getPdo()->quote((string) $default));
        }

        if (str_contains($extraUpper, 'AUTO_INCREMENT')) {
            $definition .= ' AUTO_INCREMENT';
        }

        if (preg_match('/ON UPDATE (CURRENT_TIMESTAMP(?:\(\d+\))?)/i', $extra, $matches) === 1) {
            $definition .= ' ON UPDATE '.$matches[1];
        }

        return $definition;
    }

    private function isNumericType(string $dataType): bool
    {
        return in_array($dataType, [
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint',
            'decimal', 'numeric', 'float', 'double', 'real', 'year',
        ], true);
    }
};
