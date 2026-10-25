<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 补齐缺失的表级中文注释（数据库表结构审查与优化方案 B5 / L2）。
 *
 * 背景：全库 79 张表中 68 张没有 TABLE_COMMENT，其余 11 张的注释风格是
 * 「XX表，动词短语说明职责」。本迁移只做 `ALTER TABLE ... COMMENT`，属元数据级
 * 操作（MySQL 8 INSTANT），不重建表、不改数据、不动索引。
 *
 * 幂等：先读 information_schema.TABLES 比较当前注释，一致则跳过；表不存在则跳过。
 */
return new class extends Migration
{
    private const COMMENTS = [
        'activity_logs' => '操作审计流水表，按事件记录管理员、客户与系统的业务操作及上下文',
        'admin_login_logs' => '管理员登录尝试审计表，保留昵称、角色快照与失败原因、客户端环境',
        'admin_user_roles' => '管理员角色关联表，一行一条管理员与角色的绑定',
        'admin_users' => '管理员账号表，记录登录凭据、MFA 密钥、启用状态与最近登录信息',
        'archive_audit_logs' => '归档批次审计表，按批次记录归档表名、模式、行数、产出文件与校验值',
        'archive_items' => '归档明细表，按批次记录源数据边界、导出删除进度与各阶段时间戳',
        'automation_logs' => '自动化任务执行日志表，记录任务键、动作、操作对象与命中规则',
        'content_articles' => '公告与帮助文章表，记录正文、分类、发布状态与置顶推荐标记',
        'content_categories' => '文章分类表，按内容类型维护分类名称、排序与启用状态',
        'coupon_campaigns' => '发券活动表，按星期与触发时刻周期性向会员发放优惠券',
        'coupons' => '优惠券主体表，记录发放方式、门槛面额、适用范围与领取使用上限',
        'failed_jobs' => '框架失败队列作业表，保存执行失败作业的载荷与异常堆栈',
        'finance_accounts' => '会员现金账户表，按币种记录可用与冻结余额，是账户余额的唯一真源',
        'finance_consumptions' => '财务消费映射表，记录一笔出账从哪个资金批次扣减及回补追认金额',
        'finance_funding_lots' => '资金批次表，记录每笔入账形成的可核销来源及其剩余可核销金额',
        'finance_ledger_entries' => '资金台账表，只插不改地记录每次余额变动、变动后余额与来源凭据',
        'finance_operations' => '财务写操作幂等表，同 scope 与请求键只允许一行，记录执行结果与错误',
        'finance_reconciliation_diffs' => '财务对账差异表，一条对象一条记录，保存期望值、实际值与处理状态',
        'finance_refund_allocations' => '退款分配证据表，记录退款进入新资金批次或追认原消费的明细',
        'finance_refunds' => '退款单表，记录退款金额、资金来源与退款到余额的唯一执行通道',
        'first_product_groups' => '一级商品分组表，承载商品目录的顶层分类与展示配置',
        'gateway_logs' => '支付网关日志表，记录每次网关请求响应的交易号、处理结果与错误信息',
        'integration_plugin_bindings' => '插件业务绑定表，把插件实例绑定到供应商、商品、服务等业务对象',
        'integration_plugin_configs' => '插件配置表，保存插件级配置与加密凭据及其可用标记',
        'integration_plugin_runtime_logs' => '插件运行时日志表，记录每次插件调用的入参摘要、耗时与结果状态',
        'integration_plugins' => '插件注册表，记录插件标识、版本、清单指纹、能力清单与安装启用状态',
        'jobs' => '框架队列作业表，保存待执行作业的载荷、重试次数与可见时间',
        'marketing_product_group_items' => '营销产品组成员表，记录营销组包含的商品',
        'marketing_product_groups' => '营销产品组表，为会员等级折扣等营销规则提供商品分组',
        'media_files' => '媒体文件登记表，记录素材路径、类型、尺寸与所属分组',
        'member_level_group_discounts' => '会员等级与营销组折扣矩阵表，折扣类型为保留比例时 90 表示九折',
        'member_levels' => '会员等级表，记录等级名称、启用状态与备注',
        'message_delivery_attempts' => '消息投递尝试表，同一逻辑投递可有多条尝试，记录结果与耗时',
        'message_logs' => '消息发送日志表，记录渠道、收件方、模板、投递状态与来源对象',
        'migrations' => '框架迁移账本表，记录已执行迁移文件名与批次',
        'notice_reads' => '公告已读表，记录会员阅读公告的时间',
        'notification_templates' => '通知模板表，按渠道维护模板编码、正文、变量与运营商模板号',
        'orders' => '履约工单表，记录购买续费升降配的商品快照、金额、优惠与履约状态',
        'password_reset_tokens' => '框架密码重置令牌表，按邮箱保存重置令牌与生成时间',
        'personal_access_tokens' => 'Sanctum 访问令牌表，保存 API 令牌、能力清单与最近使用时间',
        'product_upstream_bindings' => '商品上游绑定表，记录商品对应的上游商品、开通策略与同步状态',
        'promotion_ambassadors' => '推广大使表，记录新购与续费返利比例及启用状态',
        'recharge_records' => '充值记录表，记录充值场景、方向、金额与关联订单支付流水',
        'referral_account_logs' => '推荐账户流水表，记录奖励冻结释放与提现各事件后的余额快照',
        'referral_rewards' => '推荐奖励表，记录被推荐人订单金额、返利比例、奖励金额与发放状态',
        'referral_withdrawals' => '推荐提现表，记录提现金额、收款方式、处理状态与打款单号',
        'refunds' => '旧退款表，仅保留历史数据，退款功能已移除不再产生新行',
        'roles' => '角色表，记录角色标识、显示名与权限标识数组',
        'schedule_run_logs' => '调度任务执行日志表，记录任务名、执行结果、耗时与失败摘要',
        'schedule_task_runs' => '调度任务运行记录表，按节拍记录每次任务尝试的状态、重试与耗时',
        'schedule_ticks' => '调度节拍表，记录每个调度槽位的触发时间与全局当日序号',
        'second_product_groups' => '二级商品分组表，挂在一级分组下并承载展示配置',
        'service_connection_snapshots' => '服务连接快照表，记录上游连接地址、端口与加密凭据及探测时间',
        'service_provision_attempts' => '开通尝试表，记录每次开通续费调用的请求应答摘要与错误',
        'service_runtime_snapshots' => '服务运行态快照表，保存上游同步的状态、资源与指标数据',
        'service_tracking_records' => '服务跟踪留言表，记录用户针对服务提交的跟踪内容与图片',
        'service_upstream_bindings' => '服务上游绑定表，记录服务实例对应的上游服务账号与同步状态',
        'sessions' => '框架会话表，保存会话载荷、客户端信息与最后活跃时间',
        'settings' => '系统设置表，按分组键与条目键保存键值配置',
        'supplier_plugin_bindings' => '供应商插件绑定表，记录供应商接入的插件、环境、优先级与连通状态',
        'suppliers' => '供应商表，记录供应商编码、联系方式、启用状态与备注',
        'third_product_groups' => '三级商品分组表，挂在二级分组下并承载展示配置',
        'ticket_replies' => '工单回复表，记录回复正文、附件、员工标记与引用回复',
        'tickets' => '工单表，记录会员提交的问题、优先级、处理状态与关闭原因',
        'user_coupons' => '用户持券表，记录领取方式、持有使用回收状态与占用时间',
        'user_notifications' => '站内通知表，记录通知类型、标题正文、跳转链接与已读时间',
        'users' => '会员账号表，记录登录凭据、资料、等级、推荐关系与实名认证状态',
        'verification_histories' => '实名认证历史表，记录每次提交的证件信息、认证状态与完成时间',
    ];

    public function up(): void
    {
        foreach (self::COMMENTS as $table => $comment) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $current = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('TABLE_COMMENT');

            if ((string) $current === $comment) {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE `%s` COMMENT = %s', $table, DB::getPdo()->quote($comment)));
        }
    }

    public function down(): void
    {
        // 注释属于说明性元数据，回滚时保留，避免丢失业务说明。
    }
};
