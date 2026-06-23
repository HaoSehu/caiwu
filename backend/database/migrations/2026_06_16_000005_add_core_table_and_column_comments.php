<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core table comments make the live schema readable to DB tools without
     * changing column type, nullability, defaults, indexes, or constraints.
     */
    private array $comments = [
        'invoices' => [
            '__table' => '账单主表，所有购买、续费、充值、扣款和退款流程以账单为财务入口',
            'id' => '账单自增主键',
            'invoice_no' => '业务账单号，对外展示和支付关联使用',
            'user_id' => '所属用户ID',
            'order_id' => '内部订单/开通投影ID，仅用于流程追踪',
            'product_id' => '关联商品ID，手工账单可为空',
            'product_spec_snapshot' => '账单生成时的商品规格展示快照',
            'product_type_snapshot' => '账单生成时的商品类型快照',
            'service_id' => '关联服务实例ID',
            'coupon_id' => '使用的优惠券模板ID',
            'user_coupon_id' => '使用的用户优惠券ID',
            'coupon_code' => '使用的优惠码快照',
            'type' => '账单类型：normal/new/renew/recharge/deduction/referral_credit/manual/upgrade',
            'amount' => '账单应收金额',
            'discount' => '账单优惠抵扣金额',
            'billing_cycle' => '计费周期：monthly/quarterly/annually/onetime 等',
            'quantity' => '购买数量或计费数量',
            'config_snapshot' => '下单配置快照 JSON',
            'config_pricing_snapshot' => '配置项计价快照 JSON',
            'coupon_snapshot' => '优惠券使用快照 JSON',
            'paid_amount' => '已支付入账金额',
            'status' => '账单状态：0待支付 1已支付 2已取消 3已逾期 5已退款',
            'due_date' => '账单到期日期',
            'paid_at' => '账单支付完成时间',
            'refund_trace_id' => '退款链路追踪号',
            'refund_method' => '退款方式',
            'refund_amount' => '退款金额',
            'refunded_at' => '退款完成时间',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'remark' => '账单备注',
            'operator' => '操作人快照',
            'trace_id' => '链路追踪号',
        ],
        'invoice_items' => [
            '__table' => '账单明细表，记录账单内每个收费项目和快照信息',
            'id' => '账单明细自增主键',
            'invoice_id' => '所属账单ID',
            'item_name' => '明细名称',
            'item_type' => '明细类型：normal/config/addon/discount/refund 等',
            'quantity' => '明细数量',
            'unit_price' => '明细单价',
            'discount_amount' => '明细优惠金额',
            'line_amount' => '明细小计金额',
            'meta_json' => '明细扩展快照 JSON',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ],
        'payments' => [
            '__table' => '第三方支付记录表，仅记录真实外部资金流入和退款状态，不记录余额/免费/手工开服',
            'id' => '支付记录自增主键',
            'payment_no' => '内部支付单号',
            'user_id' => '支付用户ID',
            'order_id' => '内部订单/开通投影ID，仅用于流程追踪',
            'invoice_id' => '关联账单ID',
            'gateway' => '第三方支付网关代码，如 alipay/wechat/stripe',
            'trade_no' => '第三方交易号',
            'amount' => '第三方支付金额',
            'status' => '支付状态：0待支付 1成功 2失败 3已退款',
            'callback_raw' => '最近一次回调原始载荷 JSON',
            'paid_at' => '第三方确认支付时间',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'remark' => '支付备注',
            'operator' => '操作人快照',
            'trace_id' => '链路追踪号',
        ],
        'payment_callbacks' => [
            '__table' => '支付回调审计表，保存第三方通知、查询、退款等回调验签结果',
            'id' => '支付回调自增主键',
            'payment_id' => '关联支付记录ID',
            'callback_type' => '回调类型：notify/query/refund 等',
            'gateway_trade_no' => '第三方交易号',
            'payload_json' => '回调载荷 JSON',
            'is_verified' => '验签结果：0未通过/未验签 1已通过',
            'received_at' => '收到回调时间',
            'remark' => '回调备注或处理说明',
            'operator' => '操作人快照',
            'trace_id' => '链路追踪号',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ],
        'user_accounts' => [
            '__table' => '用户账户余额源表，集中承载现金余额、授信和推荐奖励余额',
            'user_id' => '用户ID，同时作为账户主键',
            'cash_balance' => '现金余额',
            'credit_limit' => '授信额度',
            'referral_frozen_balance' => '冻结中的推荐奖励余额',
            'referral_available_balance' => '可用推荐奖励余额',
            'referral_pending_withdrawal_balance' => '提现审核中的推荐奖励余额',
            'referral_withdrawn_balance' => '已提现推荐奖励累计金额',
            'version' => '乐观锁版本号',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ],
        'account_transactions' => [
            '__table' => '账户流水表，记录现金账户、授信账户、推荐奖励账户的每一次余额变化',
            'id' => '账户流水自增主键',
            'user_id' => '所属用户ID',
            'account_type' => '账户类型：cash/credit/referral 等',
            'event_type' => '流水事件类型：recharge/consume/refund/adjust/reward_frozen/reward_released 等',
            'change_amount' => '本次变动金额，收入为正、支出为负',
            'balance_after' => '本次变动后的账户余额',
            'source_type' => '业务来源类型，如 invoice/payment/referral_withdrawal',
            'source_id' => '业务来源ID',
            'origin_type' => '原始触发对象类型，用于跨域追踪',
            'origin_id' => '原始触发对象ID',
            'remark' => '流水备注',
            'operator' => '操作人快照',
            'trace_id' => '链路追踪号',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ],
        'services' => [
            '__table' => '服务实例表，记录用户已购买产品的生命周期、计费、上游和续费状态',
            'id' => '服务实例自增主键',
            'user_id' => '所属用户ID',
            'product_id' => '关联商品ID',
            'order_id' => '内部订单/开通投影ID，仅用于流程追踪',
            'invoice_id' => '最近一次关联账单ID',
            'name' => '服务自定义名称',
            'domain' => '服务域名或主机名',
            'billing_cycle' => '计费周期',
            'amount' => '服务续费/购买金额',
            'locked_pricing' => '锁定续费定价 JSON，null 表示跟随商品定价',
            'status' => '服务状态：0待开通 1运行中 2已暂停 3已到期 4已取消',
            'provision_data' => '开通和上游实例数据 JSON',
            'expires_at' => '服务到期时间',
            'auto_renew' => '是否自动续费：0关闭 1开启',
            'suspended_reason' => '暂停原因',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'remark' => '服务备注',
            'operator' => '操作人快照',
            'trace_id' => '链路追踪号',
        ],
        'products' => [
            '__table' => '商品表，记录可售卖产品的分类、定价、库存、上游绑定和开通策略',
            'id' => '商品自增主键',
            'product_group_id' => '旧商品分组ID，保留用于历史关联',
            'first_product_group_id' => '一级商品分组ID',
            'second_product_group_id' => '二级商品分组ID',
            'third_product_group_id' => '三级商品分组ID',
            'service_type_code' => '服务类型代码，用于前后端能力分流',
            'product_type' => '商品类型：vps/dedicated/hosting/domain/other',
            'custom_display_name' => '自定义展示名称',
            'remark' => '商品备注',
            'pricing' => '周期价格 JSON，如 monthly/quarterly/annually',
            'setup_fee' => '初装费',
            'config_options' => '可选配置项 JSON',
            'purchase_requires' => '购买限制 JSON，如实名认证、手机号要求',
            'stock' => '库存数量，-1 表示不限',
            'status' => '商品状态：0下架 1上架',
            'sort_order' => '排序值，越小越靠前',
            'provision_module' => '开通模块或上游驱动代码',
            'auto_setup' => '是否自动开通：0手动 1自动',
            'supplier_id' => '供应商接口ID',
            'supplier_product_id' => '供应商侧商品ID',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'deleted_at' => '软删除时间',
        ],
    ];

    private array $previousComments = [
        'invoices' => [
            'type' => 'normal|renew|manual',
            'status' => '0=未付 1=已付 2=已取消 3=逾期',
            'refund_trace_id' => '退款链路追踪号',
            'refund_method' => '退款方式',
            'refund_amount' => '退款金额',
            'refunded_at' => '退款完成时间',
            'remark' => '备注',
            'operator' => '操作人',
            'trace_id' => '链路追踪号',
        ],
        'payments' => [
            'gateway' => 'alipay|wechat|stripe|balance',
            'status' => '0=待支付 1=成功 2=失败 3=已退款',
            'remark' => '备注',
            'operator' => '操作人',
            'trace_id' => '链路追踪号',
        ],
        'services' => [
            'locked_pricing' => '锁定续费定价，null=跟随商品，有值=锁定不受商品调价影响',
            'status' => '0=待开通 1=正常 2=已暂停 3=已到期 4=已取消',
            'remark' => '备注',
            'operator' => '操作人',
            'trace_id' => '链路追踪号',
        ],
        'products' => [
            'product_type' => 'vps|dedicated|hosting|domain|other',
            'pricing' => '{"monthly":99,"quarterly":270,"yearly":999}',
            'config_options' => '可选配置项',
            'purchase_requires' => '购买限制，如 {"require_verification":true,"require_phone":true}',
            'stock' => '-1=不限',
            'status' => '0=下架 1=上架',
            'auto_setup' => '0=手动开通 1=自动开通',
            'supplier_id' => '供应商接口ID',
            'supplier_product_id' => '供应商商品ID',
        ],
    ];

    public function up(): void
    {
        $this->applyComments($this->comments);
    }

    public function down(): void
    {
        $restore = [];

        foreach ($this->comments as $table => $comments) {
            $restore[$table] = ['__table' => ''];

            foreach ($comments as $column => $_comment) {
                if ($column === '__table') {
                    continue;
                }

                $restore[$table][$column] = $this->previousComments[$table][$column] ?? '';
            }
        }

        $this->applyComments($restore);
    }

    private function applyComments(array $commentsByTable): void
    {
        foreach ($commentsByTable as $table => $comments) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (array_key_exists('__table', $comments)) {
                DB::statement(sprintf(
                    'ALTER TABLE %s COMMENT = %s',
                    $this->quoteIdentifier($table),
                    DB::getPdo()->quote((string) $comments['__table'])
                ));
            }

            foreach ($comments as $column => $comment) {
                if ($column === '__table' || ! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $definition = $this->buildColumnDefinition($table, $column, (string) $comment);
                DB::statement(sprintf(
                    'ALTER TABLE %s MODIFY COLUMN %s %s',
                    $this->quoteIdentifier($table),
                    $this->quoteIdentifier($column),
                    $definition
                ));
            }
        }
    }

    private function buildColumnDefinition(string $table, string $column, string $comment): string
    {
        $schema = DB::getDatabaseName();
        $metadata = DB::selectOne(
            <<<'SQL'
            SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            SQL,
            [$schema, $table, $column]
        );

        if (! $metadata) {
            throw new RuntimeException("Column metadata not found for {$table}.{$column}");
        }

        $definition = [$metadata->COLUMN_TYPE];

        if ($metadata->CHARACTER_SET_NAME) {
            $definition[] = 'CHARACTER SET '.$metadata->CHARACTER_SET_NAME;
        }

        if ($metadata->COLLATION_NAME) {
            $definition[] = 'COLLATE '.$metadata->COLLATION_NAME;
        }

        $definition[] = $metadata->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';

        if ($metadata->COLUMN_DEFAULT !== null) {
            $definition[] = 'DEFAULT '.$this->formatDefault($metadata->COLUMN_DEFAULT);
        } elseif ($metadata->IS_NULLABLE === 'YES') {
            $definition[] = 'DEFAULT NULL';
        }

        if ($metadata->EXTRA) {
            $definition[] = strtoupper($metadata->EXTRA);
        }

        $definition[] = 'COMMENT '.DB::getPdo()->quote($comment);

        return implode(' ', $definition);
    }

    private function formatDefault(string $default): string
    {
        $upper = strtoupper($default);

        if (in_array($upper, ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()'], true)) {
            return 'CURRENT_TIMESTAMP';
        }

        return DB::getPdo()->quote($default);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};
