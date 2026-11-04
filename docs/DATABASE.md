---
status: generated
updated: 2026-11-04
owner: backend-data
---

# 当前数据库结构说明

- 生成时间：`2026-11-04 17:34:42 +08:00`
- 数据来源：Laravel 默认连接 `mysql` 直连 MySQL `information_schema` 与业务库 `idc`
- 数据来源环境：`local`（开发环境库）。本地开发环境默认连接开发环境库，不是生产库；需要对照生产结构时，在指向生产库的 `.env` 下重跑本脚本，禁止手工编辑本文。
- 数据库：`idc`
- MySQL 版本：`8.0.29`
- 当前表数量：`86`
- 字段数量：`1191`
- 索引数量：`412`
- 外键约束数量：`114`
- CHECK 约束数量：`0`
- 说明：
  - 本文只导出表结构元数据，不包含任何业务行数据。
  - 行数来自 `information_schema.TABLES.TABLE_ROWS`，InnoDB 下仅作估算。
  - 字段、索引、外键与约束均来自当前实库，不以迁移文件或历史快照推断。
  - 需要更新时在项目根目录执行：`php backend/scripts/export_database_structure.php`。

> **自动生成**：请优先通过脚本重刷本文档，避免手工维护结构信息产生漂移。

## 1. 结构概览

### 1.1 表清单

| 表名 | 类型 | 引擎 | 估算行数 | 数据大小 | 索引大小 | 自增值 | 排序规则 | 表注释 |
| --- | --- | --- | ---: | ---: | ---: | ---: | --- | --- |
| `account_transactions` | BASE TABLE | InnoDB | 708 | 160 KB | 320 KB | 1,804 | utf8mb4_unicode_ci | 账户流水表，记录现金账户、授信账户、推荐奖励账户的每一次余额变化 |
| `activity_logs` | BASE TABLE | InnoDB | 73,767 | 51.59 MB | 25.16 MB | 1,182,904 | utf8mb4_unicode_ci | 操作审计流水表，按事件记录管理员、客户与系统的业务操作及上下文 |
| `admin_login_logs` | BASE TABLE | InnoDB | 0 | 16 KB | 64 KB | 6 | utf8mb4_unicode_ci | 管理员登录尝试审计表，保留昵称、角色快照与失败原因、客户端环境 |
| `admin_user_roles` | BASE TABLE | InnoDB | 2 | 16 KB | 32 KB | 360 | utf8mb4_unicode_ci | 管理员角色关联表，一行一条管理员与角色的绑定 |
| `admin_users` | BASE TABLE | InnoDB | 3 | 16 KB | 48 KB | 314 | utf8mb4_unicode_ci | 管理员账号表，记录登录凭据、MFA 密钥、启用状态与最近登录信息 |
| `archive_audit_logs` | BASE TABLE | InnoDB | 476 | 160 KB | 96 KB | 477 | utf8mb4_unicode_ci | 归档批次审计表，按批次记录归档表名、模式、行数、产出文件与校验值 |
| `archive_items` | BASE TABLE | InnoDB | 0 | 16 KB | 64 KB | — | utf8mb4_unicode_ci | 归档明细表，按批次记录源数据边界、导出删除进度与各阶段时间戳 |
| `automation_logs` | BASE TABLE | InnoDB | 499 | 128 KB | 96 KB | 7,725 | utf8mb4_unicode_ci | 自动化任务执行日志表，记录任务键、动作、操作对象与命中规则 |
| `content_articles` | BASE TABLE | InnoDB | 14 | 96 KB | 112 KB | 25 | utf8mb4_unicode_ci | 公告与帮助文章表，记录正文、分类、发布状态与置顶推荐标记 |
| `content_categories` | BASE TABLE | InnoDB | 4 | 16 KB | 48 KB | 9 | utf8mb4_unicode_ci | 文章分类表，按内容类型维护分类名称、排序与启用状态 |
| `coupon_campaigns` | BASE TABLE | InnoDB | 0 | 16 KB | 48 KB | 1 | utf8mb4_unicode_ci | 发券活动表，按星期与触发时刻周期性向会员发放优惠券 |
| `coupons` | BASE TABLE | InnoDB | 3 | 16 KB | 80 KB | 6 | utf8mb4_unicode_ci | 优惠券主体表，记录发放方式、门槛面额、适用范围与领取使用上限 |
| `failed_jobs` | BASE TABLE | InnoDB | 0 | 16 KB | 16 KB | 1 | utf8mb4_unicode_ci | 框架失败队列作业表，保存执行失败作业的载荷与异常堆栈 |
| `finance_accounts` | BASE TABLE | InnoDB | 420 | 64 KB | 32 KB | 421 | utf8mb4_unicode_ci | 会员现金账户表，按币种记录可用与冻结余额，是账户余额的唯一真源 |
| `finance_consumptions` | BASE TABLE | InnoDB | 126 | 16 KB | 96 KB | 126 | utf8mb4_unicode_ci | 财务消费映射表，记录一笔出账从哪个资金批次扣减及回补追认金额 |
| `finance_funding_lots` | BASE TABLE | InnoDB | 91 | 16 KB | 96 KB | 91 | utf8mb4_unicode_ci | 资金批次表，记录每笔入账形成的可核销来源及其剩余可核销金额 |
| `finance_ledger_entries` | BASE TABLE | InnoDB | 732 | 176 KB | 400 KB | 1,829 | utf8mb4_unicode_ci | 资金台账表，只插不改地记录每次余额变动、变动后余额与来源凭据 |
| `finance_operations` | BASE TABLE | InnoDB | 0 | 16 KB | 64 KB | 1 | utf8mb4_unicode_ci | 财务写操作幂等表，同 scope 与请求键只允许一行，记录执行结果与错误 |
| `finance_reconciliation_diffs` | BASE TABLE | InnoDB | 7 | 16 KB | 32 KB | 7 | utf8mb4_unicode_ci | 财务对账差异表，一条对象一条记录，保存期望值、实际值与处理状态 |
| `finance_refund_allocations` | BASE TABLE | InnoDB | 0 | 16 KB | 64 KB | 1 | utf8mb4_unicode_ci | 退款分配证据表，记录退款进入新资金批次或追认原消费的明细 |
| `finance_refunds` | BASE TABLE | InnoDB | 3 | 16 KB | 96 KB | 4 | utf8mb4_unicode_ci | 退款单表，记录退款金额、资金来源与退款到余额的唯一执行通道 |
| `first_product_groups` | BASE TABLE | InnoDB | 6 | 16 KB | 32 KB | 58 | utf8mb4_unicode_ci | 一级商品分组表，承载商品目录的顶层分类与展示配置 |
| `gateway_logs` | BASE TABLE | InnoDB | 129 | 64 KB | 128 KB | 1,000,221 | utf8mb4_unicode_ci | 支付网关日志表，记录每次网关请求响应的交易号、处理结果与错误信息 |
| `integration_plugin_bindings` | BASE TABLE | InnoDB | 10 | 16 KB | 64 KB | 13 | utf8mb4_unicode_ci | 插件业务绑定表，把插件实例绑定到供应商、商品、服务等业务对象 |
| `integration_plugin_configs` | BASE TABLE | InnoDB | 8 | 16 KB | 16 KB | 11 | utf8mb4_unicode_ci | 插件配置表，保存插件级配置与加密凭据及其可用标记 |
| `integration_plugin_runtime_logs` | BASE TABLE | InnoDB | 71,032 | 84.59 MB | 19.61 MB | 1,119,649 | utf8mb4_unicode_ci | 插件运行时日志表，记录每次插件调用的入参摘要、耗时与结果状态 |
| `integration_plugins` | BASE TABLE | InnoDB | 12 | 64 KB | 48 KB | 25 | utf8mb4_unicode_ci | 插件注册表，记录插件标识、版本、清单指纹、能力清单与安装启用状态 |
| `invoice_items` | BASE TABLE | InnoDB | 3,055 | 1.52 MB | 144 KB | 3,081 | utf8mb4_unicode_ci | 账单明细表，记录账单内每个收费项目和快照信息 |
| `invoices` | BASE TABLE | InnoDB | 3,501 | 2.52 MB | 1.8 MB | 3,299 | utf8mb4_unicode_ci | 账单主表，所有购买、续费、充值、扣款和退款流程以账单为财务入口 |
| `jobs` | BASE TABLE | InnoDB | 0 | 16 KB | 16 KB | 373 | utf8mb4_unicode_ci | 框架队列作业表，保存待执行作业的载荷、重试次数与可见时间 |
| `marketing_product_group_items` | BASE TABLE | InnoDB | 0 | 16 KB | 32 KB | 1 | utf8mb4_unicode_ci | 营销产品组成员表，记录营销组包含的商品 |
| `marketing_product_groups` | BASE TABLE | InnoDB | 0 | 16 KB | 32 KB | — | utf8mb4_unicode_ci | 营销产品组表，为会员等级折扣等营销规则提供商品分组 |
| `media_files` | BASE TABLE | InnoDB | 25 | 16 KB | 64 KB | 99 | utf8mb4_unicode_ci | 媒体文件登记表，记录素材路径、类型、尺寸与所属分组 |
| `member_level_group_discounts` | BASE TABLE | InnoDB | 0 | 16 KB | 32 KB | 1 | utf8mb4_unicode_ci | 会员等级与营销组折扣矩阵表，折扣类型为保留比例时 90 表示九折 |
| `member_levels` | BASE TABLE | InnoDB | 3 | 16 KB | 0 B | 4 | utf8mb4_unicode_ci | 会员等级表，记录等级名称、启用状态与备注 |
| `message_configurations` | BASE TABLE | InnoDB | 0 | 16 KB | 0 B | 1 | utf8mb4_unicode_ci | 消息场景配置表，按受众与场景加密保存接收组与渠道策略 |
| `message_deliveries` | BASE TABLE | InnoDB | 0 | 16 KB | 0 B | 1 | utf8mb4_unicode_ci | 消息投递任务表，承载事务 Outbox 与租约状态机 |
| `message_delivery_attempt_logs` | BASE TABLE | InnoDB | 0 | 16 KB | 0 B | 1 | utf8mb4_unicode_ci | 消息投递逐次尝试表，记录状态、安全错误码与耗时 |
| `message_delivery_attempts` | BASE TABLE | InnoDB | 21 | 16 KB | 32 KB | 25 | utf8mb4_unicode_ci | 消息投递尝试表，同一逻辑投递可有多条尝试，记录结果与耗时 |
| `message_logs` | BASE TABLE | InnoDB | 950 | 16.52 MB | 592 KB | 4,544 | utf8mb4_unicode_ci | 消息发送日志表，记录渠道、收件方、模板、投递状态与来源对象 |
| `message_recipients` | BASE TABLE | InnoDB | 0 | 16 KB | 0 B | 1 | utf8mb4_unicode_ci | 消息接收对象表，记录收件人与站内已读状态 |
| `message_scene_templates` | BASE TABLE | InnoDB | 60 | 16 KB | 32 KB | 61 | utf8mb4_unicode_ci | 消息场景与通知模板关联表，承载一个场景多渠道多模板关系 |
| `messages` | BASE TABLE | InnoDB | 0 | 16 KB | 0 B | 1 | utf8mb4_unicode_ci | 业务消息流水表，记录幂等事件与业务对象引用 |
| `migrations` | BASE TABLE | InnoDB | 191 | 48 KB | 0 B | 314 | utf8mb4_unicode_ci | 框架迁移账本表，记录已执行迁移文件名与批次 |
| `notice_reads` | BASE TABLE | InnoDB | 315 | 48 KB | 32 KB | 547 | utf8mb4_unicode_ci | 公告已读表，记录会员阅读公告的时间 |
| `notification_templates` | BASE TABLE | InnoDB | 77 | 1.52 MB | 48 KB | 139 | utf8mb4_unicode_ci | 通知模板表，按渠道维护模板编码、正文、变量与运营商模板号 |
| `orders` | BASE TABLE | InnoDB | 477 | 1.44 MB | 192 KB | 3,338 | utf8mb4_unicode_ci | 履约工单表，记录购买续费升降配的商品快照、金额、优惠与履约状态 |
| `password_reset_tokens` | BASE TABLE | InnoDB | 0 | 16 KB | 0 B | — | utf8mb4_unicode_ci | 框架密码重置令牌表，按邮箱保存重置令牌与生成时间 |
| `payment_callbacks` | BASE TABLE | InnoDB | 476 | 1.52 MB | 128 KB | 652 | utf8mb4_unicode_ci | 支付回调审计表，保存第三方通知、查询、退款等回调验签结果 |
| `payments` | BASE TABLE | InnoDB | 419 | 1.52 MB | 224 KB | 510 | utf8mb4_unicode_ci | 第三方支付记录表，仅记录真实外部资金流入和退款状态，不记录余额/免费/手工开服 |
| `personal_access_tokens` | BASE TABLE | InnoDB | 11 | 16 KB | 48 KB | 20 | utf8mb4_unicode_ci | Sanctum 访问令牌表，保存 API 令牌、能力清单与最近使用时间 |
| `product_upstream_bindings` | BASE TABLE | InnoDB | 134 | 8.52 MB | 80 KB | 277 | utf8mb4_unicode_ci | 商品上游绑定表，记录商品对应的上游商品、开通策略与同步状态 |
| `products` | BASE TABLE | InnoDB | 134 | 9.52 MB | 48 KB | 386 | utf8mb4_unicode_ci | 商品表，记录可售卖产品的分类、定价、库存、上游绑定和开通策略 |
| `promotion_ambassadors` | BASE TABLE | InnoDB | 0 | 16 KB | 16 KB | 2 | utf8mb4_unicode_ci | 推广大使表，记录新购与续费返利比例及启用状态 |
| `recharge_records` | BASE TABLE | InnoDB | 126 | 64 KB | 144 KB | 126 | utf8mb4_unicode_ci | 充值记录表，记录充值场景、方向、金额与关联订单支付流水 |
| `referral_account_logs` | BASE TABLE | InnoDB | 6 | 16 KB | 64 KB | 6 | utf8mb4_unicode_ci | 推荐账户流水表，记录奖励冻结释放与提现各事件后的余额快照 |
| `referral_rewards` | BASE TABLE | InnoDB | 6 | 16 KB | 112 KB | 12 | utf8mb4_unicode_ci | 推荐奖励表，记录被推荐人订单金额、返利比例、奖励金额与发放状态 |
| `referral_withdrawals` | BASE TABLE | InnoDB | 0 | 16 KB | 48 KB | 1 | utf8mb4_unicode_ci | 推荐提现表，记录提现金额、收款方式、处理状态与打款单号 |
| `refunds` | BASE TABLE | InnoDB | 3 | 16 KB | 96 KB | 3 | utf8mb4_unicode_ci | 旧退款表，仅保留历史数据，退款功能已移除不再产生新行 |
| `roles` | BASE TABLE | InnoDB | 4 | 16 KB | 16 KB | 478 | utf8mb4_unicode_ci | 角色表，记录角色标识、显示名与权限标识数组 |
| `schedule_run_logs` | BASE TABLE | InnoDB | 74,032 | 29.56 MB | 10.06 MB | 249,011 | utf8mb4_unicode_ci | 调度任务执行日志表，记录任务名、执行结果、耗时与失败摘要 |
| `schedule_task_runs` | BASE TABLE | InnoDB | 95,152 | 44.58 MB | 31.17 MB | 98,734 | utf8mb4_unicode_ci | 调度任务运行记录表，按节拍记录每次任务尝试的状态、重试与耗时 |
| `schedule_ticks` | BASE TABLE | InnoDB | 9,016 | 528 KB | 880 KB | 9,007 | utf8mb4_unicode_ci | 调度节拍表，记录每个调度槽位的触发时间与全局当日序号 |
| `second_product_groups` | BASE TABLE | InnoDB | 15 | 16 KB | 32 KB | 44 | utf8mb4_unicode_ci | 二级商品分组表，挂在一级分组下并承载展示配置 |
| `service_connection_snapshots` | BASE TABLE | InnoDB | 194 | 352 KB | 64 KB | 244 | utf8mb4_unicode_ci | 服务连接快照表，记录上游连接地址、端口与加密凭据及探测时间 |
| `service_provision_attempts` | BASE TABLE | InnoDB | 5,395 | 4.52 MB | 1.19 MB | 5,323 | utf8mb4_unicode_ci | 开通尝试表，记录每次开通续费调用的请求应答摘要与错误 |
| `service_runtime_snapshots` | BASE TABLE | InnoDB | 188 | 416 KB | 64 KB | 244 | utf8mb4_unicode_ci | 服务运行态快照表，保存上游同步的状态、资源与指标数据 |
| `service_tracking_records` | BASE TABLE | InnoDB | 0 | 16 KB | 32 KB | — | utf8mb4_unicode_ci | 服务跟踪留言表，记录用户针对服务提交的跟踪内容与图片 |
| `service_upstream_bindings` | BASE TABLE | InnoDB | 208 | 192 KB | 80 KB | 330 | utf8mb4_unicode_ci | 服务上游绑定表，记录服务实例对应的上游服务账号与同步状态 |
| `services` | BASE TABLE | InnoDB | 161 | 1.52 MB | 128 KB | 350 | utf8mb4_unicode_ci | 服务实例表，记录用户已购买产品的生命周期、计费、上游和续费状态 |
| `sessions` | BASE TABLE | InnoDB | 0 | 16 KB | 32 KB | — | utf8mb4_unicode_ci | 框架会话表，保存会话载荷、客户端信息与最后活跃时间 |
| `settings` | BASE TABLE | InnoDB | 115 | 144 KB | 16 KB | 804 | utf8mb4_unicode_ci | 系统设置表，按分组键与条目键保存键值配置 |
| `sms_provider_templates` | BASE TABLE | InnoDB | 0 | 16 KB | 32 KB | — | utf8mb4_unicode_ci | 短信运营商模板映射（插件同步运营商 API 审核状态，发送解析唯一有效态为 approved） |
| `supplier_plugin_bindings` | BASE TABLE | InnoDB | 2 | 16 KB | 48 KB | 3 | utf8mb4_unicode_ci | 供应商插件绑定表，记录供应商接入的插件、环境、优先级与连通状态 |
| `suppliers` | BASE TABLE | InnoDB | 2 | 16 KB | 32 KB | 3 | utf8mb4_unicode_ci | 供应商表，记录供应商编码、联系方式、启用状态与备注 |
| `system_installation` | BASE TABLE | InnoDB | 0 | 16 KB | 0 B | — | utf8mb4_unicode_ci | 系统安装版本账本：单行记录数据库最后成功升级到的代码版本 |
| `system_upgrade_runs` | BASE TABLE | InnoDB | 8 | 16 KB | 32 KB | 8 | utf8mb4_unicode_ci | 系统升级执行记录：每次升级的来源/目标版本、状态、备份与失败摘要 |
| `third_product_groups` | BASE TABLE | InnoDB | 25 | 16 KB | 32 KB | 50 | utf8mb4_unicode_ci | 三级商品分组表，挂在二级分组下并承载展示配置 |
| `ticket_replies` | BASE TABLE | InnoDB | 211 | 48 KB | 32 KB | 212 | utf8mb4_unicode_ci | 工单回复表，记录回复正文、附件、员工标记与引用回复 |
| `tickets` | BASE TABLE | InnoDB | 82 | 16 KB | 80 KB | 89 | utf8mb4_unicode_ci | 工单表，记录会员提交的问题、优先级、处理状态与关闭原因 |
| `user_accounts` | BASE TABLE | InnoDB | 420 | 64 KB | 0 B | — | utf8mb4_unicode_ci | 用户账户余额源表，集中承载现金余额、授信和推荐奖励余额 |
| `user_coupons` | BASE TABLE | InnoDB | 36 | 16 KB | 64 KB | 101 | utf8mb4_unicode_ci | 用户持券表，记录领取方式、持有使用回收状态与占用时间 |
| `user_notifications` | BASE TABLE | InnoDB | 493 | 160 KB | 80 KB | 509 | utf8mb4_unicode_ci | 站内通知表，记录通知类型、标题正文、跳转链接与已读时间 |
| `users` | BASE TABLE | InnoDB | 507 | 160 KB | 192 KB | 508 | utf8mb4_unicode_ci | 会员账号表，记录登录凭据、资料、等级、推荐关系与实名认证状态 |
| `verification_challenges` | BASE TABLE | InnoDB | 0 | 16 KB | 0 B | 1 | utf8mb4_unicode_ci | 验证码挑战表，仅保存 HMAC 摘要不存明文 |
| `verification_histories` | BASE TABLE | InnoDB | 123 | 80 KB | 64 KB | 128 | utf8mb4_unicode_ci | 实名认证历史表，记录每次提交的证件信息、认证状态与完成时间 |

### 1.2 字段类型分布

| 类型 | 字段数 |
| --- | ---: |
| `bigint` | 257 |
| `char` | 9 |
| `date` | 1 |
| `datetime` | 21 |
| `decimal` | 59 |
| `int` | 50 |
| `json` | 68 |
| `longtext` | 11 |
| `mediumtext` | 3 |
| `smallint` | 2 |
| `text` | 21 |
| `timestamp` | 214 |
| `tinyint` | 62 |
| `varchar` | 413 |

### 1.3 JSON 字段

- `activity_logs.context`
- `admin_users.mfa_recovery_codes`
- `automation_logs.meta`
- `coupon_campaigns.weekdays`
- `coupon_campaigns.billing_cycles`
- `coupon_campaigns.product_ids`
- `coupon_campaigns.requires_product_ids`
- `coupons.billing_cycles`
- `coupons.product_ids`
- `coupons.requires_product_ids`
- `finance_operations.result_payload`
- `finance_reconciliation_diffs.details`
- `finance_refunds.delivery_evidence`
- `gateway_logs.request_data`
- `gateway_logs.response_data`
- `integration_plugin_bindings.config_json`
- `integration_plugin_bindings.has_secret_json`
- `integration_plugin_bindings.runtime_policy_json`
- `integration_plugin_configs.config_json`
- `integration_plugin_configs.has_secret_json`
- `integration_plugin_runtime_logs.request_meta_json`
- `integration_plugin_runtime_logs.response_meta_json`
- `integration_plugins.capabilities_json`
- `integration_plugins.config_schema_json`
- `invoice_items.meta_json`
- `invoices.config_snapshot`
- `invoices.config_pricing_snapshot`
- `invoices.coupon_snapshot`
- `invoices.member_discount_snapshot`
- `message_logs.params_json`
- `notification_templates.variables_json`
- `notification_templates.provider_variables_json`
- `orders.config_snapshot`
- `orders.config_pricing_snapshot`
- `orders.coupon_snapshot`
- `orders.member_discount_snapshot`
- `orders.service_snapshot`
- `payment_callbacks.payload_json`
- `payments.callback_raw`
- `product_upstream_bindings.upstream_product_snapshot_json`
- `product_upstream_bindings.option_schema_json`
- `product_upstream_bindings.provision_policy_json`
- `products.pricing`
- `products.config_options`
- `products.purchase_requires`
- `roles.permissions`
- `schedule_run_logs.summary`
- `schedule_task_runs.summary`
- `service_connection_snapshots.connection_json`
- `service_connection_snapshots.has_secret_json`
- `service_provision_attempts.request_meta_json`
- `service_provision_attempts.response_meta_json`
- `service_runtime_snapshots.resource_json`
- `service_runtime_snapshots.metrics_json`
- `service_runtime_snapshots.snapshot_json`
- `service_tracking_records.images`
- `service_upstream_bindings.runtime_snapshot_json`
- `service_upstream_bindings.connection_snapshot_json`
- `services.locked_pricing`
- `services.renew_coupon_snapshot`
- `services.provision_data`
- `sms_provider_templates.variables_json`
- `sms_provider_templates.raw_json`
- `supplier_plugin_bindings.config_json`
- `supplier_plugin_bindings.has_secret_json`
- `system_upgrade_runs.executed_migrations`
- `ticket_replies.attachments`
- `user_notifications.data`

## 2. 表结构明细

### 2.1 `account_transactions`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`708`
- 数据大小：`160 KB`
- 索引大小：`320 KB`
- 自增值：`1804`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：账户流水表，记录现金账户、授信账户、推荐奖励账户的每一次余额变化

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | 账户流水自增主键 |
| 2 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 所属用户ID |
| 3 | `account_type` | `varchar(30)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 账户类型：cash/credit/referral 等 |
| 4 | `event_type` | `varchar(30)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 流水事件类型：recharge/consume/refund/adjust/reward_frozen/reward_released 等 |
| 5 | `change_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 本次变动金额，收入为正、支出为负 |
| 6 | `currency` | `varchar(3)` | 否 | `CNY` | — | — | utf8mb4 | utf8mb4_unicode_ci | 币种，固定 CNY |
| 7 | `balance_after` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 本次变动后的账户余额 |
| 8 | `source_type` | `varchar(30)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 业务来源类型，如 invoice/payment/referral_withdrawal |
| 9 | `source_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 业务来源ID |
| 10 | `origin_type` | `varchar(30)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 原始触发对象类型，用于跨域追踪 |
| 11 | `origin_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 原始触发对象ID |
| 12 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 流水备注 |
| 13 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作人快照 |
| 14 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |
| 15 | `created_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | 创建时间 |
| 16 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 更新时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `account_transactions_created_at_idx` | 否 | `BTREE` | `created_at` | 694 | — |
| `account_transactions_origin_idx` | 否 | `BTREE` | `origin_type`, `origin_id` | 699 | — |
| `account_transactions_source_idx` | 否 | `BTREE` | `source_type`, `source_id` | 661 | — |
| `account_transactions_trace_id_idx` | 否 | `BTREE` | `trace_id` | 627 | — |
| `account_transactions_user_account_created_idx` | 否 | `BTREE` | `user_id`, `account_type`, `created_at`, `id` | 708 | — |
| `account_transactions_user_event_created_idx` | 否 | `BTREE` | `user_id`, `event_type`, `created_at` | 701 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 708 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_account_transactions_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.2 `activity_logs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`73,767`
- 数据大小：`51.59 MB`
- 索引大小：`25.16 MB`
- 自增值：`1182904`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：操作审计流水表，按事件记录管理员、客户与系统的业务操作及上下文

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `event_id` | `varchar(40)` | 是 | `NULL` | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `stream` | `varchar(20)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `actor_type` | `varchar(20)` | 否 | `system` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作者类型: admin, client, system, sub_account |
| 5 | `actor_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 操作者ID |
| 6 | `actor_name` | `varchar(100)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作者名称快照 |
| 7 | `module` | `varchar(50)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 模块: invoice, order, service, user, product, ticket, coupon, system |
| 8 | `action` | `varchar(100)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 动作描述: create, pay, refund, suspend, terminate 等 |
| 9 | `description` | `text` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 可读描述 |
| 10 | `subject_type` | `varchar(50)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 关联对象类型: invoice, service, order, user, ticket |
| 11 | `subject_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 关联对象ID |
| 12 | `context` | `json` | 是 | `NULL` | — | — | — | — | 附加结构化上下文 |
| 13 | `ip_address` | `varchar(45)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 14 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 15 | `created_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |
| 16 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 17 | `context_status` | `smallint unsigned` | 是 | `NULL` | MUL | STORED GENERATED; generated: (case when regexp_like(json_unquote(json_extract(`context`,_utf8mb4\'$.status\')),_utf8mb4\'^[0-9]{1,5}$\') then cast(json_unquote(json_extract(`context`,_utf8mb4\'$.status\')) as unsigned) else NULL end) | — | — | 从 context.status 解析的 HTTP 状态码（生成列，供索引过滤） |
| 18 | `context_request_id` | `varchar(64)` | 是 | `NULL` | MUL | STORED GENERATED; generated: left(json_unquote(json_extract(`context`,_utf8mb4\'$.request_id\')),64) | utf8mb4 | utf8mb4_unicode_ci | 从 context.request_id 解析的链路标识（生成列，截断 64） |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `activity_logs_actor_id_index` | 否 | `BTREE` | `actor_id` | 71 | — |
| `activity_logs_context_request_id_idx` | 否 | `BTREE` | `context_request_id` | 10,206 | — |
| `activity_logs_context_status_idx` | 否 | `BTREE` | `context_status` | 4 | — |
| `activity_logs_created_at_index` | 否 | `BTREE` | `created_at` | 35,442 | — |
| `activity_logs_event_id_unique` | 是 | `BTREE` | `event_id` | 73,772 | — |
| `activity_logs_module_action_index` | 否 | `BTREE` | `module`, `action` | 498 | — |
| `activity_logs_module_subject_id_index` | 否 | `BTREE` | `module`, `subject_id` | 198 | — |
| `activity_logs_stream_created_at_index` | 否 | `BTREE` | `stream`, `created_at` | 36,435 | — |
| `activity_logs_subject_type_subject_id_index` | 否 | `BTREE` | `subject_type`, `subject_id` | 197 | — |
| `activity_logs_trace_id_index` | 否 | `BTREE` | `trace_id` | 8,937 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 73,767 | — |

#### 外键约束

无数据库级外键约束。

### 2.3 `admin_login_logs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`64 KB`
- 自增值：`6`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：管理员登录尝试审计表，保留昵称、角色快照与失败原因、客户端环境

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `admin_user_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 管理员ID；未知账号为 NULL |
| 3 | `username` | `varchar(64)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 登录时输入的账号快照 |
| 4 | `nickname` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 昵称快照；未知账号存当时输入的账号 |
| 5 | `role_name` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 角色名称快照 |
| 6 | `status` | `tinyint unsigned` | 否 | `0` | MUL | — | — | — | 登录结果: 1成功, 0失败 |
| 7 | `failure_reason` | `varchar(32)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 失败原因: invalid_credentials, account_disabled |
| 8 | `client_ip` | `varchar(45)` | 否 | 空字符串 | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 客户端IP |
| 9 | `browser` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 浏览器归纳结果 |
| 10 | `operating_system` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作系统归纳结果 |
| 11 | `logged_at` | `datetime` | 否 | — | MUL | — | — | — | 登录尝试时间 |
| 12 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 13 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `admin_login_logs_admin_user_logged_at_index` | 否 | `BTREE` | `admin_user_id`, `logged_at` | 0 | — |
| `admin_login_logs_client_ip_logged_at_index` | 否 | `BTREE` | `client_ip`, `logged_at` | 0 | — |
| `admin_login_logs_logged_at_id_index` | 否 | `BTREE` | `logged_at`, `id` | 0 | — |
| `admin_login_logs_status_logged_at_index` | 否 | `BTREE` | `status`, `logged_at` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.4 `admin_user_roles`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`2`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`360`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：管理员角色关联表，一行一条管理员与角色的绑定

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `admin_user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `role_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `admin_user_roles_admin_role_unique` | 是 | `BTREE` | `admin_user_id`, `role_id` | 2 | — |
| `admin_user_roles_role_id_idx` | 否 | `BTREE` | `role_id` | 2 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 2 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_admin_user_roles_admin_user_id` | `admin_user_id` | `admin_users` | `id` | `NO ACTION` | `CASCADE` |
| `fk_stage2_admin_user_roles_role_id` | `role_id` | `roles` | `id` | `NO ACTION` | `RESTRICT` |

### 2.5 `admin_users`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`3`
- 数据大小：`16 KB`
- 索引大小：`48 KB`
- 自增值：`314`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：管理员账号表，记录登录凭据、MFA 密钥、启用状态与最近登录信息

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `username` | `varchar(50)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 管理员登录账号，全局唯一 |
| 3 | `password` | `varchar(255)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 登录密码哈希（框架 hashed 转换） |
| 4 | `mfa_secret` | `varchar(512)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 双因素认证 TOTP 密钥密文，未绑定为 NULL |
| 5 | `mfa_enabled_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 双因素认证启用时间，NULL 表示未启用 |
| 6 | `mfa_recovery_codes` | `json` | 是 | `NULL` | — | — | — | — | 恢复码哈希数组（JSON），仅存 Hash 结果 |
| 7 | `role_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 所属管理员角色ID |
| 8 | `nickname` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 显示昵称 |
| 9 | `status` | `tinyint` | 否 | `1` | — | — | — | — | 0=禁用 1=正常 |
| 10 | `last_login_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 最近一次登录成功时间 |
| 11 | `last_login_ip` | `varchar(45)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 最近一次登录IP |
| 12 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 13 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 14 | `email` | `varchar(100)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 联系邮箱 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `admin_users_email_index` | 否 | `BTREE` | `email` | 3 | — |
| `admin_users_role_id_index` | 否 | `BTREE` | `role_id` | 2 | — |
| `admin_users_username_unique` | 是 | `BTREE` | `username` | 3 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 3 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_admin_users_role_id` | `role_id` | `roles` | `id` | `NO ACTION` | `RESTRICT` |

### 2.6 `archive_audit_logs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`476`
- 数据大小：`160 KB`
- 索引大小：`96 KB`
- 自增值：`477`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：归档批次审计表，按批次记录归档表名、模式、行数、产出文件与校验值

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `batch_id` | `varchar(64)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `table_name` | `varchar(64)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `mode` | `varchar(30)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `row_count` | `int unsigned` | 否 | `0` | — | — | — | — | — |
| 6 | `file_path` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `file_size` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 8 | `checksum_sha256` | `char(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `status` | `varchar(24)` | 否 | `running` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `error_message` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 11 | `started_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 12 | `finished_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 13 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `archive_batch_idx` | 否 | `BTREE` | `batch_id` | 77 | — |
| `archive_table_status_idx` | 否 | `BTREE` | `table_name`, `status`, `created_at` | 476 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 476 | — |

#### 外键约束

无数据库级外键约束。

### 2.7 `archive_items`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`64 KB`
- 自增值：—
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：归档明细表，按批次记录源数据边界、导出删除进度与各阶段时间戳

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `batch_id` | `varchar(64)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `table_name` | `varchar(64)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `status` | `varchar(24)` | 否 | `planned` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `cutoff_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 6 | `id_min` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 7 | `id_max` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 8 | `expected_rows` | `bigint unsigned` | 否 | `0` | — | — | — | — | — |
| 9 | `exported_rows` | `bigint unsigned` | 否 | `0` | — | — | — | — | — |
| 10 | `deleted_rows` | `bigint unsigned` | 否 | `0` | — | — | — | — | — |
| 11 | `part_path` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 12 | `published_path` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 13 | `manifest_path` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 14 | `checksum_sha256` | `char(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 15 | `file_size` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 16 | `error_message` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 17 | `started_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 18 | `verified_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 19 | `published_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 20 | `purged_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 21 | `created_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |
| 22 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `archive_items_batch_idx` | 否 | `BTREE` | `batch_id` | 0 | — |
| `archive_items_created_idx` | 否 | `BTREE` | `created_at` | 0 | — |
| `archive_items_status_idx` | 否 | `BTREE` | `status` | 0 | — |
| `archive_items_table_status_idx` | 否 | `BTREE` | `table_name`, `status` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.8 `automation_logs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`499`
- 数据大小：`128 KB`
- 索引大小：`96 KB`
- 自增值：`7725`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：自动化任务执行日志表，记录任务键、动作、操作对象与命中规则

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `task_key` | `varchar(80)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `action` | `varchar(80)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `object_type` | `varchar(40)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `object_id` | `bigint unsigned` | 否 | — | — | — | — | — | — |
| 6 | `rule_key` | `varchar(191)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `meta` | `json` | 是 | `NULL` | — | — | — | — | — |
| 8 | `executed_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 9 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 10 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `automation_logs_object_idx` | 否 | `BTREE` | `object_type`, `object_id` | 207 | — |
| `automation_logs_unique_rule` | 是 | `BTREE` | `task_key`, `action`, `object_type`, `object_id`, `rule_key` | 499 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 499 | — |

#### 外键约束

无数据库级外键约束。

### 2.9 `content_articles`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`14`
- 数据大小：`96 KB`
- 索引大小：`112 KB`
- 自增值：`25`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：公告与帮助文章表，记录正文、分类、发布状态与置顶推荐标记

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `content_type` | `varchar(20)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | notice&#124;help |
| 3 | `category_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 4 | `title` | `varchar(200)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `slug` | `varchar(220)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `summary` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `excerpt` | `varchar(300)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 摘要缓存：summary 为空时由正文预渲染固化 |
| 8 | `content` | `longtext` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `category_name` | `varchar(60)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `keywords` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 11 | `cover_image` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 12 | `status` | `tinyint` | 否 | `0` | — | — | — | — | 0=草稿 1=已发布 2=已下线 |
| 13 | `is_pinned` | `tinyint` | 否 | `0` | — | — | — | — | — |
| 14 | `is_recommended` | `tinyint` | 否 | `0` | — | — | — | — | — |
| 15 | `sort_order` | `int` | 否 | `0` | — | — | — | — | — |
| 16 | `view_count` | `int unsigned` | 否 | `0` | — | — | — | — | — |
| 17 | `require_reread_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 18 | `publish_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 19 | `last_published_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 20 | `created_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 21 | `updated_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 22 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 23 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 24 | `trace_id` | `varchar(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 25 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 26 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 27 | `deleted_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `content_articles_deleted_at_idx` | 否 | `BTREE` | `deleted_at` | 1 | — |
| `content_articles_slug_unique` | 是 | `BTREE` | `slug` | 14 | — |
| `idx_article_category_published` | 否 | `BTREE` | `category_id`, `status`, `publish_at` | 14 | — |
| `idx_content_article_type_category` | 否 | `BTREE` | `content_type`, `category_id` | 4 | — |
| `idx_content_type_pin_sort` | 否 | `BTREE` | `content_type`, `is_pinned`, `sort_order`, `id` | 14 | — |
| `idx_content_type_recommend` | 否 | `BTREE` | `content_type`, `is_recommended`, `publish_at` | 14 | — |
| `idx_content_type_status_publish` | 否 | `BTREE` | `content_type`, `status`, `publish_at` | 14 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 14 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_content_articles_category_id` | `category_id` | `content_categories` | `id` | `NO ACTION` | `SET NULL` |

### 2.10 `content_categories`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`4`
- 数据大小：`16 KB`
- 索引大小：`48 KB`
- 自增值：`9`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：文章分类表，按内容类型维护分类名称、排序与启用状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `content_type` | `varchar(20)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | notice&#124;help |
| 3 | `name` | `varchar(80)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `slug` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `description` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `status` | `tinyint` | 否 | `1` | — | — | — | — | 0=禁用 1=启用 |
| 7 | `sort_order` | `int` | 否 | `0` | — | — | — | — | — |
| 8 | `created_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 9 | `updated_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 10 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 11 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `idx_content_category_type_status_sort` | 否 | `BTREE` | `content_type`, `status`, `sort_order` | 2 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 4 | — |
| `uniq_content_category_type_name` | 是 | `BTREE` | `content_type`, `name` | 4 | — |
| `uniq_content_category_type_slug` | 是 | `BTREE` | `content_type`, `slug` | 4 | — |

#### 外键约束

无数据库级外键约束。

### 2.11 `coupon_campaigns`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`48 KB`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：发券活动表，按星期与触发时刻周期性向会员发放优惠券

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `name` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `description` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `weekdays` | `json` | 是 | `NULL` | — | — | — | — | — |
| 5 | `trigger_time` | `varchar(8)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `issue_quantity` | `int unsigned` | 否 | `1` | — | — | — | — | — |
| 7 | `valid_duration_hours` | `int unsigned` | 是 | `NULL` | — | — | — | — | — |
| 8 | `discount_scope` | `varchar(20)` | 否 | `first_month` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `discount_type` | `varchar(20)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `discount_value` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 11 | `min_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 12 | `max_discount_amount` | `decimal(12,2)` | 是 | `NULL` | — | — | — | — | — |
| 13 | `billing_cycles` | `json` | 是 | `NULL` | — | — | — | — | — |
| 14 | `product_ids` | `json` | 是 | `NULL` | — | — | — | — | — |
| 15 | `requires_product_ids` | `json` | 是 | `NULL` | — | — | — | — | 前置持有条件：用户名下须持有任一指定产品的运行中服务才可领/用 |
| 16 | `first_order_only` | `tinyint(1)` | 否 | `0` | — | — | — | — | — |
| 17 | `only_old_client` | `tinyint unsigned` | 否 | `0` | — | — | — | — | 仅老客：0=不限 1=仅已有成交订单的用户可领/用（与首单券互斥） |
| 18 | `is_discount` | `tinyint unsigned` | 否 | `1` | — | — | — | — | 与会员折扣叠加开关：1=可叠加（默认） 0=互斥（本单有会员折扣减免时券不应用） |
| 19 | `per_user_limit` | `int unsigned` | 是 | `NULL` | — | — | — | — | — |
| 20 | `status` | `tinyint` | 否 | `1` | MUL | — | — | — | 状态：0=禁用 1=启用 |
| 21 | `sort_order` | `int unsigned` | 否 | `0` | — | — | — | — | — |
| 22 | `last_dispatched_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 23 | `last_coupon_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 24 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 25 | `operator` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 26 | `trace_id` | `varchar(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 27 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 28 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `coupon_campaigns_status_sort_idx` | 否 | `BTREE` | `status`, `sort_order` | 0 | — |
| `coupon_campaigns_trigger_status_idx` | 否 | `BTREE` | `trigger_time`, `status` | 0 | — |
| `idx_stage2_coupon_campaigns_last_coupon_id` | 否 | `BTREE` | `last_coupon_id` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_coupon_campaigns_last_coupon_id` | `last_coupon_id` | `coupons` | `id` | `NO ACTION` | `SET NULL` |

### 2.12 `coupons`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`3`
- 数据大小：`16 KB`
- 索引大小：`80 KB`
- 自增值：`6`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：优惠券主体表，记录发放方式、门槛面额、适用范围与领取使用上限

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `coupon_campaign_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 3 | `name` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `code` | `varchar(50)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `description` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `distribution_type` | `varchar(20)` | 否 | `public` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `discount_scope` | `varchar(20)` | 否 | `first_month` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `discount_type` | `varchar(20)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `discount_value` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 10 | `min_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 11 | `max_discount_amount` | `decimal(12,2)` | 是 | `NULL` | — | — | — | — | — |
| 12 | `billing_cycles` | `json` | 是 | `NULL` | — | — | — | — | — |
| 13 | `product_ids` | `json` | 是 | `NULL` | — | — | — | — | — |
| 14 | `requires_product_ids` | `json` | 是 | `NULL` | — | — | — | — | 前置持有条件：用户名下须持有任一指定产品的运行中服务才可领/用 |
| 15 | `first_order_only` | `tinyint(1)` | 否 | `0` | — | — | — | — | — |
| 16 | `only_old_client` | `tinyint unsigned` | 否 | `0` | — | — | — | — | 仅老客：0=不限 1=仅已有成交订单的用户可领/用（与首单券互斥） |
| 17 | `is_discount` | `tinyint unsigned` | 否 | `1` | — | — | — | — | 与会员折扣叠加开关：1=可叠加（默认） 0=互斥（本单有会员折扣减免时券不应用） |
| 18 | `total_usage_limit` | `int unsigned` | 是 | `NULL` | — | — | — | — | — |
| 19 | `per_user_limit` | `int unsigned` | 是 | `NULL` | — | — | — | — | — |
| 20 | `used_count` | `int unsigned` | 否 | `0` | — | — | — | — | — |
| 21 | `status` | `tinyint` | 否 | `1` | MUL | — | — | — | 状态：0=禁用 1=启用 |
| 22 | `sort_order` | `int unsigned` | 否 | `0` | — | — | — | — | — |
| 23 | `starts_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 24 | `expires_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 25 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 26 | `operator` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 27 | `trace_id` | `varchar(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 28 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 29 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 30 | `deleted_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `coupons_campaign_status_idx` | 否 | `BTREE` | `coupon_campaign_id`, `status` | 1 | — |
| `coupons_code_unique` | 是 | `BTREE` | `code` | 3 | — |
| `coupons_deleted_status_idx` | 否 | `BTREE` | `deleted_at`, `status` | 1 | — |
| `coupons_status_expires_idx` | 否 | `BTREE` | `status`, `expires_at` | 2 | — |
| `coupons_status_sort_idx` | 否 | `BTREE` | `status`, `sort_order` | 1 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 3 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_coupons_coupon_campaign_id` | `coupon_campaign_id` | `coupon_campaigns` | `id` | `NO ACTION` | `SET NULL` |

### 2.13 `failed_jobs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`16 KB`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：框架失败队列作业表，保存执行失败作业的载荷与异常堆栈

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `uuid` | `varchar(255)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `connection` | `text` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `queue` | `text` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `payload` | `longtext` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `exception` | `longtext` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `failed_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` | — | DEFAULT_GENERATED | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `failed_jobs_uuid_unique` | 是 | `BTREE` | `uuid` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.14 `finance_accounts`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`420`
- 数据大小：`64 KB`
- 索引大小：`32 KB`
- 自增值：`421`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：会员现金账户表，按币种记录可用与冻结余额，是账户余额的唯一真源

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 会员 users.id |
| 3 | `currency` | `char(3)` | 否 | `CNY` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 币种，当前仅 CNY |
| 4 | `available_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 可用余额，单位元；允许负值以保留历史异常 |
| 5 | `frozen_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 冻结余额，单位元；提现阶段当前未启用 |
| 6 | `version` | `int unsigned` | 否 | `0` | — | — | — | — | 乐观锁版本号，每次余额变更 +1 |
| 7 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 8 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `finance_accounts_currency_index` | 否 | `BTREE` | `currency` | 1 | — |
| `finance_accounts_user_currency_unique` | 是 | `BTREE` | `user_id`, `currency` | 420 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 420 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `finance_accounts_user_id_foreign` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.15 `finance_consumptions`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`126`
- 数据大小：`16 KB`
- 索引大小：`96 KB`
- 自增值：`126`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：财务消费映射表，记录一笔出账从哪个资金批次扣减及回补追认金额

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `account_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | finance_accounts.id |
| 3 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 会员 users.id |
| 4 | `lot_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 被消费的资金批次 finance_funding_lots.id |
| 5 | `entry_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 出账台账 finance_ledger_entries.id |
| 6 | `source_type` | `varchar(30)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 消费来源类型：invoice/manual_adjustment/verification/referral_withdrawal |
| 7 | `source_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 消费来源单据主键 |
| 8 | `amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 从该批次扣除的金额，单位元 |
| 9 | `restored_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 已恢复金额，单位元；恢复时批次 remaining 同步回补 |
| 10 | `refunded_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 已退款追认金额，单位元；仅登记，不回补批次 |
| 11 | `restored_entry_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 最近一次恢复对应的台账行 |
| 12 | `created_at` | `datetime` | 是 | `NULL` | — | — | — | — | 创建时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `finance_consumptions_account_id_foreign` | 否 | `BTREE` | `account_id` | 35 | — |
| `finance_consumptions_entry_lot_unique` | 是 | `BTREE` | `entry_id`, `lot_id` | 126 | — |
| `finance_consumptions_lot_index` | 否 | `BTREE` | `lot_id`, `id` | 126 | — |
| `finance_consumptions_restored_entry_id_foreign` | 否 | `BTREE` | `restored_entry_id` | 1 | — |
| `finance_consumptions_source_index` | 否 | `BTREE` | `source_type`, `source_id` | 109 | — |
| `finance_consumptions_user_index` | 否 | `BTREE` | `user_id`, `id` | 126 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 126 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `finance_consumptions_account_id_foreign` | `account_id` | `finance_accounts` | `id` | `NO ACTION` | `RESTRICT` |
| `finance_consumptions_entry_id_foreign` | `entry_id` | `finance_ledger_entries` | `id` | `NO ACTION` | `RESTRICT` |
| `finance_consumptions_lot_id_foreign` | `lot_id` | `finance_funding_lots` | `id` | `NO ACTION` | `RESTRICT` |
| `finance_consumptions_restored_entry_id_foreign` | `restored_entry_id` | `finance_ledger_entries` | `id` | `NO ACTION` | `SET NULL` |

### 2.16 `finance_funding_lots`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`91`
- 数据大小：`16 KB`
- 索引大小：`96 KB`
- 自增值：`91`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：资金批次表，记录每笔入账形成的可核销来源及其剩余可核销金额

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `lot_no` | `varchar(40)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 批次号：FL+时间+随机 |
| 3 | `account_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | finance_accounts.id |
| 4 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 会员 users.id，冗余便于按用户回放 |
| 5 | `entry_id` | `bigint unsigned` | 否 | — | UNI | — | — | — | 创建该批次的入账台账 finance_ledger_entries.id |
| 6 | `source_type` | `varchar(30)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 资金来源类型：payment/refund/manual_adjustment/referral_withdrawal/migration |
| 7 | `source_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 资金来源单据主键 |
| 8 | `origin_type` | `varchar(30)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 跨域溯源来源类型 |
| 9 | `origin_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 跨域溯源来源主键 |
| 10 | `amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 批次原始入账金额，单位元；仅校准批次可为负 |
| 11 | `remaining_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 剩余可核销金额，单位元；FIFO 只取大于 0 |
| 12 | `data_quality` | `varchar(24)` | 否 | `normal` | — | — | utf8mb4 | utf8mb4_unicode_ci | 数据质量：normal/replayed/calibration/refund |
| 13 | `created_at` | `datetime` | 是 | `NULL` | — | — | — | — | 批次创建时间，历史回放取原台账时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `finance_funding_lots_account_fifo_index` | 否 | `BTREE` | `account_id`, `id` | 91 | — |
| `finance_funding_lots_account_remaining_index` | 否 | `BTREE` | `account_id`, `remaining_amount` | 71 | — |
| `finance_funding_lots_entry_id_unique` | 是 | `BTREE` | `entry_id` | 91 | — |
| `finance_funding_lots_lot_no_unique` | 是 | `BTREE` | `lot_no` | 91 | — |
| `finance_funding_lots_source_index` | 否 | `BTREE` | `source_type`, `source_id` | 50 | — |
| `finance_funding_lots_user_index` | 否 | `BTREE` | `user_id`, `id` | 91 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 91 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `finance_funding_lots_account_id_foreign` | `account_id` | `finance_accounts` | `id` | `NO ACTION` | `RESTRICT` |
| `finance_funding_lots_entry_id_foreign` | `entry_id` | `finance_ledger_entries` | `id` | `NO ACTION` | `RESTRICT` |
| `finance_funding_lots_user_id_foreign` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.17 `finance_ledger_entries`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`732`
- 数据大小：`176 KB`
- 索引大小：`400 KB`
- 自增值：`1829`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：资金台账表，只插不改地记录每次余额变动、变动后余额与来源凭据

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `entry_no` | `varchar(40)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 流水序号：历史行 LG{id}，新行 FL+时间+随机 |
| 3 | `account_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | finance_accounts.id；返利等非现金子账户为空 |
| 4 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 会员 users.id，冗余便于按用户查询 |
| 5 | `account_type` | `varchar(30)` | 否 | `cash` | — | — | utf8mb4 | utf8mb4_unicode_ci | 账户类型：cash=现金，referral_*=返利子账户 |
| 6 | `event_type` | `varchar(30)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 资金事件类型，见 FinanceLedgerEventType |
| 7 | `change_amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 变动金额，单位元；收入为正、支出为负 |
| 8 | `balance_after` | `decimal(12,2)` | 否 | — | — | — | — | — | 变动后可用余额快照，单位元 |
| 9 | `currency` | `char(3)` | 否 | `CNY` | — | — | utf8mb4 | utf8mb4_unicode_ci | 币种，当前仅 CNY |
| 10 | `funding_lot_id` | `bigint unsigned` | 是 | `NULL` | UNI | — | — | — | 入账行对应的资金批次 finance_funding_lots.id |
| 11 | `source_type` | `varchar(30)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 业务来源类型：invoice/payment/manual_adjustment/referral_withdrawal/verification |
| 12 | `source_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 业务来源单据主键 |
| 13 | `origin_type` | `varchar(30)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 跨域溯源来源类型 |
| 14 | `origin_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 跨域溯源来源主键 |
| 15 | `operator_type` | `varchar(30)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作者类型：admin/system/member/legacy |
| 16 | `operator_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 操作者主键；legacy 历史行为空 |
| 17 | `operator_name` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作者名称快照 |
| 18 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 备注，展示用 |
| 19 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |
| 20 | `created_at` | `datetime` | 是 | `NULL` | MUL | — | — | — | 发生时间；历史行沿用原时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `finance_ledger_entries_account_id_foreign` | 否 | `BTREE` | `account_id` | 216 | — |
| `finance_ledger_entries_created_at_index` | 否 | `BTREE` | `created_at` | 695 | — |
| `finance_ledger_entries_entry_no_unique` | 是 | `BTREE` | `entry_no` | 732 | — |
| `finance_ledger_entries_funding_lot_unique` | 是 | `BTREE` | `funding_lot_id` | 92 | — |
| `finance_ledger_entries_origin_index` | 否 | `BTREE` | `origin_type`, `origin_id` | 700 | — |
| `finance_ledger_entries_source_index` | 否 | `BTREE` | `source_type`, `source_id` | 662 | — |
| `finance_ledger_entries_trace_index` | 否 | `BTREE` | `trace_id` | 627 | — |
| `finance_ledger_entries_user_event_time_index` | 否 | `BTREE` | `user_id`, `event_type`, `created_at` | 725 | — |
| `finance_ledger_entries_user_type_time_index` | 否 | `BTREE` | `user_id`, `account_type`, `created_at` | 718 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 732 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `finance_ledger_entries_account_id_foreign` | `account_id` | `finance_accounts` | `id` | `NO ACTION` | `RESTRICT` |
| `finance_ledger_entries_user_id_foreign` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.18 `finance_operations`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`64 KB`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：财务写操作幂等表，同 scope 与请求键只允许一行，记录执行结果与错误

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `scope` | `varchar(40)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 操作域：checkout/invoice_pay/recharge/balance_adjust/invoice_refund |
| 3 | `request_key` | `varchar(128)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 客户端幂等键（X-Idempotency-Key） |
| 4 | `request_hash` | `char(64)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 规范化请求 SHA256；同键不同内容拒绝 |
| 5 | `actor_type` | `varchar(24)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 发起者类型：admin/member/system |
| 6 | `actor_id` | `bigint unsigned` | 否 | — | — | — | — | — | 发起者主键 |
| 7 | `resource_type` | `varchar(40)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 结果资源类型：invoice/payment/recharge/refund |
| 8 | `resource_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 结果资源主键 |
| 9 | `status` | `varchar(16)` | 否 | `processing` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 状态：processing/succeeded/failed |
| 10 | `result_payload` | `json` | 是 | `NULL` | — | — | — | — | 成功结果快照，用于同键重放 |
| 11 | `error_code` | `varchar(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 失败错误码 |
| 12 | `error_msg` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 失败摘要 |
| 13 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |
| 14 | `started_at` | `datetime` | 否 | — | — | — | — | — | 开始处理时间 |
| 15 | `finished_at` | `datetime` | 是 | `NULL` | — | — | — | — | 结束时间 |
| 16 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 17 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `finance_operations_resource_index` | 否 | `BTREE` | `resource_type`, `resource_id` | 0 | — |
| `finance_operations_scope_actor_key_unique` | 是 | `BTREE` | `scope`, `actor_type`, `actor_id`, `request_key` | 0 | — |
| `finance_operations_status_time_index` | 否 | `BTREE` | `status`, `started_at` | 0 | — |
| `finance_operations_trace_index` | 否 | `BTREE` | `trace_id` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.19 `finance_reconciliation_diffs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`7`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`7`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：财务对账差异表，一条对象一条记录，保存期望值、实际值与处理状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `check_type` | `varchar(40)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 检查项：unallocated_debit/balance_mismatch/duplicate_receipt |
| 3 | `scope_type` | `varchar(40)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 差异对象类型：ledger_entry/account/refund |
| 4 | `scope_id` | `bigint unsigned` | 否 | — | — | — | — | — | 差异对象主键 |
| 5 | `expected_value` | `decimal(12,2)` | 是 | `NULL` | — | — | — | — | 应有值，单位元 |
| 6 | `actual_value` | `decimal(12,2)` | 是 | `NULL` | — | — | — | — | 实际值，单位元 |
| 7 | `diff_value` | `decimal(12,2)` | 是 | `NULL` | — | — | — | — | 差额，单位元；负数表示缺口 |
| 8 | `details` | `json` | 是 | `NULL` | — | — | — | — | 差异上下文快照 |
| 9 | `status` | `varchar(16)` | 否 | `open` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 状态：open/resolved/ignored |
| 10 | `detected_at` | `datetime` | 否 | — | — | — | — | — | 首次发现时间 |
| 11 | `resolved_at` | `datetime` | 是 | `NULL` | — | — | — | — | 处理完成时间 |
| 12 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 13 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `finance_recon_diffs_object_unique` | 是 | `BTREE` | `check_type`, `scope_type`, `scope_id` | 7 | — |
| `finance_recon_diffs_status_time_index` | 否 | `BTREE` | `status`, `detected_at` | 1 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 7 | — |

#### 外键约束

无数据库级外键约束。

### 2.20 `finance_refund_allocations`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`64 KB`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：退款分配证据表，记录退款进入新资金批次或追认原消费的明细

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `allocation_key` | `varchar(160)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 幂等键：refund_id:type:lot_id:consumption_id |
| 3 | `refund_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | finance_refunds.id |
| 4 | `allocation_type` | `varchar(24)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 分配类型：new_lot=退款新批次，consumption_mark=追认原消费 |
| 5 | `lot_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联资金批次 finance_funding_lots.id |
| 6 | `consumption_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联消费映射 finance_consumptions.id |
| 7 | `amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 分配金额，单位元 |
| 8 | `created_at` | `datetime` | 是 | `NULL` | — | — | — | — | 创建时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `finance_refund_allocations_consumption_index` | 否 | `BTREE` | `consumption_id` | 0 | — |
| `finance_refund_allocations_key_unique` | 是 | `BTREE` | `allocation_key` | 0 | — |
| `finance_refund_allocations_lot_index` | 否 | `BTREE` | `lot_id` | 0 | — |
| `finance_refund_allocations_refund_index` | 否 | `BTREE` | `refund_id` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `finance_refund_allocations_consumption_id_foreign` | `consumption_id` | `finance_consumptions` | `id` | `NO ACTION` | `SET NULL` |
| `finance_refund_allocations_lot_id_foreign` | `lot_id` | `finance_funding_lots` | `id` | `NO ACTION` | `SET NULL` |
| `finance_refund_allocations_refund_id_foreign` | `refund_id` | `finance_refunds` | `id` | `NO ACTION` | `RESTRICT` |

### 2.21 `finance_refunds`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`3`
- 数据大小：`16 KB`
- 索引大小：`96 KB`
- 自增值：`4`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：退款单表，记录退款金额、资金来源与退款到余额的唯一执行通道

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `refund_no` | `varchar(40)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 退款单号：RF+时间+随机 |
| 3 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 会员 users.id |
| 4 | `invoice_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 被退款账单 invoices.id |
| 5 | `refund_invoice_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 退款生成的红字账单 invoices.id |
| 6 | `payment_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 原支付单 payments.id；余额支付为空 |
| 7 | `plugin_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 原支付单的支付插件ID快照，余额退款为空 |
| 8 | `plugin_key` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 原支付单的支付插件标识快照 |
| 9 | `plugin_name` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 原支付单的支付插件名称快照 |
| 10 | `gateway_key` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 原支付单的网关标识快照 |
| 11 | `payment_type` | `varchar(20)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 原支付单的网关内支付类型快照 |
| 12 | `amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 退款金额，单位元 |
| 13 | `currency` | `char(3)` | 否 | `CNY` | — | — | utf8mb4 | utf8mb4_unicode_ci | 币种，当前仅 CNY |
| 14 | `status` | `tinyint unsigned` | 否 | `1` | — | — | — | — | 状态：0=处理中，1=已完成，2=失败 |
| 15 | `funds_moved` | `tinyint unsigned` | 否 | `1` | — | — | — | — | 是否真实动账：1=已退回余额，0=历史纯标记行 |
| 16 | `data_origin` | `varchar(24)` | 否 | `native` | — | — | utf8mb4 | utf8mb4_unicode_ci | 来源：native/legacy_balance/legacy_manual_mark |
| 17 | `reason` | `varchar(255)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 退款原因 |
| 18 | `delivery_evidence` | `json` | 是 | `NULL` | — | — | — | — | 交付状态核查证据快照，仅余额退款写入 |
| 19 | `operator_type` | `varchar(30)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作者类型：admin/system/member/legacy |
| 20 | `operator_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 操作者主键 |
| 21 | `operator_name` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作者名称快照 |
| 22 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |
| 23 | `refunded_at` | `datetime` | 是 | `NULL` | — | — | — | — | 退款完成时间 |
| 24 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 25 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `finance_refunds_invoice_status_index` | 否 | `BTREE` | `invoice_id`, `status`, `id` | 3 | — |
| `finance_refunds_payment_id_foreign` | 否 | `BTREE` | `payment_id` | 3 | — |
| `finance_refunds_refund_invoice_id_foreign` | 否 | `BTREE` | `refund_invoice_id` | 1 | — |
| `finance_refunds_refund_no_unique` | 是 | `BTREE` | `refund_no` | 3 | — |
| `finance_refunds_trace_index` | 否 | `BTREE` | `trace_id` | 3 | — |
| `finance_refunds_user_time_index` | 否 | `BTREE` | `user_id`, `created_at` | 2 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 3 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `finance_refunds_invoice_id_foreign` | `invoice_id` | `invoices` | `id` | `NO ACTION` | `RESTRICT` |
| `finance_refunds_payment_id_foreign` | `payment_id` | `payments` | `id` | `NO ACTION` | `SET NULL` |
| `finance_refunds_refund_invoice_id_foreign` | `refund_invoice_id` | `invoices` | `id` | `NO ACTION` | `SET NULL` |
| `finance_refunds_user_id_foreign` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.22 `first_product_groups`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`6`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`58`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：一级商品分组表，承载商品目录的顶层分类与展示配置

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `code` | `varchar(50)` | 是 | `NULL` | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `product_type` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `name` | `varchar(100)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `slug` | `varchar(100)` | 是 | `NULL` | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `description` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `icon` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `banner_image` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `sort_order` | `int` | 否 | `0` | — | — | — | — | — |
| 10 | `is_visible` | `tinyint unsigned` | 否 | `1` | — | — | — | — | — |
| 11 | `is_system` | `tinyint unsigned` | 否 | `0` | — | — | — | — | — |
| 12 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 13 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `first_product_groups_code_unique` | 是 | `BTREE` | `code` | 6 | — |
| `first_product_groups_slug_unique` | 是 | `BTREE` | `slug` | 6 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 6 | — |

#### 外键约束

无数据库级外键约束。

### 2.23 `gateway_logs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`129`
- 数据大小：`64 KB`
- 索引大小：`128 KB`
- 自增值：`1000221`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：支付网关日志表，记录每次网关请求响应的交易号、处理结果与错误信息

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `plugin_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 3 | `plugin_key` | `varchar(120)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 网关请求时的支付插件标识快照；gateway 列仍保留插件上报的原始网关串以便排障 |
| 4 | `gateway_key` | `varchar(120)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `gateway` | `varchar(50)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 网关标识: alipay_f2f, wechat_native 等 |
| 6 | `action` | `varchar(50)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作: precreate, notify, query, refund |
| 7 | `out_trade_no` | `varchar(128)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 商户订单号 |
| 8 | `trade_no` | `varchar(128)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 第三方交易号 |
| 9 | `invoice_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联账单ID |
| 10 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 11 | `request_data` | `json` | 是 | `NULL` | — | — | — | — | 请求数据(脱敏后) |
| 12 | `response_data` | `json` | 是 | `NULL` | — | — | — | — | 响应数据 |
| 13 | `detail_key` | `varchar(180)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 请求/响应明细所在 gateway-json 日志文件的定位键 |
| 14 | `result_status` | `varchar(20)` | 否 | `unknown` | — | — | utf8mb4 | utf8mb4_unicode_ci | 结果: success, failed, pending, unknown |
| 15 | `error_msg` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 错误信息 |
| 16 | `ip_address` | `varchar(45)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 17 | `created_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |
| 18 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `gateway_logs_created_at_index` | 否 | `BTREE` | `created_at` | 129 | — |
| `gateway_logs_gateway_action_index` | 否 | `BTREE` | `gateway`, `action` | 1 | — |
| `gateway_logs_gateway_key_idx` | 否 | `BTREE` | `gateway_key`, `created_at` | 129 | — |
| `gateway_logs_invoice_id_index` | 否 | `BTREE` | `invoice_id` | 1 | — |
| `gateway_logs_out_trade_no_index` | 否 | `BTREE` | `out_trade_no` | 121 | — |
| `gateway_logs_plugin_created_idx` | 否 | `BTREE` | `plugin_id`, `created_at` | 129 | — |
| `gateway_logs_plugin_key_created_idx` | 否 | `BTREE` | `plugin_key`, `created_at` | 129 | — |
| `gateway_logs_trace_idx` | 否 | `BTREE` | `trace_id` | 121 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 129 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_gateway_logs_invoice_id` | `invoice_id` | `invoices` | `id` | `NO ACTION` | `SET NULL` |
| `gateway_logs_plugin_fk` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `SET NULL` |

### 2.24 `integration_plugin_bindings`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`10`
- 数据大小：`16 KB`
- 索引大小：`64 KB`
- 自增值：`13`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：插件业务绑定表，把插件实例绑定到供应商、商品、服务等业务对象

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `domain` | `varchar(32)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `plugin_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 4 | `binding_type` | `varchar(50)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | global/supplier/product/service/payment/notification/custom |
| 5 | `bindable_type` | `varchar(120)` | 否 | `global` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `bindable_id` | `bigint unsigned` | 否 | `0` | — | — | — | — | — |
| 7 | `binding_key` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 同一对象下的绑定名 |
| 8 | `provider_key` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 外部协议标识快照 |
| 9 | `priority` | `int` | 否 | `0` | — | — | — | — | — |
| 10 | `status` | `tinyint unsigned` | 否 | `1` | — | — | — | — | — |
| 11 | `config_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 12 | `secret_json` | `longtext` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 13 | `has_secret_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 14 | `runtime_policy_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 15 | `created_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 16 | `updated_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 17 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 18 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `plugin_bindings_bindable_idx` | 否 | `BTREE` | `bindable_type`, `bindable_id`, `domain` | 9 | — |
| `plugin_bindings_domain_provider_status_idx` | 否 | `BTREE` | `domain`, `provider_key`, `status` | 6 | — |
| `plugin_bindings_plugin_status_idx` | 否 | `BTREE` | `plugin_id`, `status` | 6 | — |
| `plugin_bindings_unique` | 是 | `BTREE` | `domain`, `binding_type`, `bindable_type`, `bindable_id`, `binding_key` | 10 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 10 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `integration_plugin_bindings_plugin_id_foreign` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `RESTRICT` |

### 2.25 `integration_plugin_configs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`8`
- 数据大小：`16 KB`
- 索引大小：`16 KB`
- 自增值：`11`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：插件配置表，保存插件级配置与加密凭据及其可用标记

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `plugin_id` | `bigint unsigned` | 否 | — | UNI | — | — | — | — |
| 3 | `config_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 4 | `secret_json` | `longtext` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `has_secret_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 6 | `updated_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 7 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 8 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `integration_plugin_configs_plugin_unique` | 是 | `BTREE` | `plugin_id` | 8 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 8 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `integration_plugin_configs_plugin_id_foreign` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `CASCADE` |

### 2.26 `integration_plugin_runtime_logs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`71,032`
- 数据大小：`84.59 MB`
- 索引大小：`19.61 MB`
- 自增值：`1119649`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：插件运行时日志表，记录每次插件调用的入参摘要、耗时与结果状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `domain` | `varchar(32)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `plugin_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 5 | `plugin_key` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `slug` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `action` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `binding_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 9 | `bindable_type` | `varchar(120)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `bindable_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 11 | `actor_type` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 12 | `actor_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 13 | `status` | `varchar(30)` | 否 | `success` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 14 | `duration_ms` | `int unsigned` | 是 | `NULL` | — | — | — | — | — |
| 15 | `error_code` | `varchar(80)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 16 | `error_message` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 17 | `request_meta_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 18 | `response_meta_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 19 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `plugin_runtime_bindable_idx` | 否 | `BTREE` | `bindable_type`, `bindable_id`, `created_at` | 54,886 | — |
| `plugin_runtime_domain_action_created_idx` | 否 | `BTREE` | `domain`, `action`, `created_at` | 68,264 | — |
| `plugin_runtime_plugin_created_idx` | 否 | `BTREE` | `plugin_id`, `created_at` | 53,183 | — |
| `plugin_runtime_status_created_idx` | 否 | `BTREE` | `status`, `created_at` | 54,828 | — |
| `plugin_runtime_trace_idx` | 否 | `BTREE` | `trace_id` | 71,033 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 71,032 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `integration_plugin_runtime_logs_plugin_id_foreign` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `SET NULL` |

### 2.27 `integration_plugins`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`12`
- 数据大小：`64 KB`
- 索引大小：`48 KB`
- 自增值：`25`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：插件注册表，记录插件标识、版本、清单指纹、能力清单与安装启用状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `domain` | `varchar(32)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `slug` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `plugin_key` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `name` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `version` | `varchar(32)` | 否 | `1.0.0` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `manifest_hash` | `varchar(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `provider_class` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `entry_class` | `varchar(255)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `capabilities_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 11 | `config_schema_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 12 | `status` | `tinyint unsigned` | 否 | `0` | — | — | — | — | — |
| 13 | `installed_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 14 | `enabled_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 15 | `disabled_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 16 | `installed_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 17 | `enabled_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 18 | `source_hash` | `varchar(128)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 19 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 20 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `integration_plugins_domain_key_unique` | 是 | `BTREE` | `domain`, `plugin_key` | 12 | — |
| `integration_plugins_domain_slug_unique` | 是 | `BTREE` | `domain`, `slug` | 12 | — |
| `integration_plugins_domain_status_index` | 否 | `BTREE` | `domain`, `status` | 11 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 12 | — |

#### 外键约束

无数据库级外键约束。

### 2.28 `invoice_items`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`3,055`
- 数据大小：`1.52 MB`
- 索引大小：`144 KB`
- 自增值：`3081`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：账单明细表，记录账单内每个收费项目和快照信息

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | 账单明细自增主键 |
| 2 | `invoice_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 所属账单ID |
| 3 | `item_name` | `varchar(200)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 明细名称 |
| 4 | `item_type` | `varchar(30)` | 否 | `normal` | — | — | utf8mb4 | utf8mb4_unicode_ci | 明细类型：normal/config/addon/discount/refund 等 |
| 5 | `quantity` | `int unsigned` | 否 | `1` | — | — | — | — | 明细数量 |
| 6 | `unit_price` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 明细单价 |
| 7 | `discount_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 明细优惠金额 |
| 8 | `line_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 明细小计金额 |
| 9 | `meta_json` | `json` | 是 | `NULL` | — | — | — | — | 明细扩展快照 JSON |
| 10 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 创建时间 |
| 11 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 更新时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `invoice_items_invoice_id_index` | 否 | `BTREE` | `invoice_id` | 3,047 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 3,055 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_invoice_items_invoice_id` | `invoice_id` | `invoices` | `id` | `NO ACTION` | `CASCADE` |

### 2.29 `invoices`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`3,501`
- 数据大小：`2.52 MB`
- 索引大小：`1.8 MB`
- 自增值：`3299`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：账单主表，所有购买、续费、充值、扣款和退款流程以账单为财务入口

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | 账单自增主键 |
| 2 | `invoice_no` | `varchar(32)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 业务账单号，对外展示和支付关联使用 |
| 3 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 所属用户ID |
| 4 | `order_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 内部订单/开通投影ID，仅用于流程追踪 |
| 5 | `origin_invoice_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 原账单ID：退款或调整红字账单指向被冲正的账单 |
| 6 | `product_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联商品ID，手工账单可为空 |
| 7 | `product_spec_snapshot` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 账单生成时的商品规格展示快照 |
| 8 | `product_type_snapshot` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 账单生成时的商品类型快照 |
| 9 | `service_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联服务实例ID |
| 10 | `coupon_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 使用的优惠券模板ID |
| 11 | `user_coupon_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 使用的用户优惠券ID |
| 12 | `coupon_code` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 使用的优惠码快照 |
| 13 | `type` | `varchar(20)` | 否 | `normal` | — | — | utf8mb4 | utf8mb4_unicode_ci | 账单类型：normal/new/renew/recharge/deduction/referral_credit/manual/upgrade |
| 14 | `amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 账单应收金额 |
| 15 | `currency` | `varchar(3)` | 否 | `CNY` | — | — | utf8mb4 | utf8mb4_unicode_ci | 币种，固定 CNY |
| 16 | `discount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 账单优惠抵扣金额 |
| 17 | `member_discount_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 会员等级×营销组折扣减免金额 |
| 18 | `billing_cycle` | `varchar(30)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 计费周期：monthly/quarterly/annually/onetime 等 |
| 19 | `quantity` | `int unsigned` | 否 | `1` | — | — | — | — | 购买数量或计费数量 |
| 20 | `config_snapshot` | `json` | 是 | `NULL` | — | — | — | — | 下单配置快照 JSON |
| 21 | `config_pricing_snapshot` | `json` | 是 | `NULL` | — | — | — | — | 配置项计价快照 JSON |
| 22 | `fulfillment_pending` | `tinyint(1)` | 否 | `0` | MUL | — | — | — | 已付待履约标记：1=已付款但开通未完成 |
| 23 | `fulfillment_pending_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 已付待履约标记写入时间，供超时扫描使用 |
| 24 | `fulfillment_cleared_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 待履约标记清除时间，空=尚未清除 |
| 25 | `requires_refund` | `tinyint(1)` | 否 | `0` | MUL | — | — | — | 人工退款标记：1=自动履约已放弃，转人工退款 |
| 26 | `coupon_snapshot` | `json` | 是 | `NULL` | — | — | — | — | 优惠券使用快照 JSON |
| 27 | `member_discount_snapshot` | `json` | 是 | `NULL` | — | — | — | — | 会员折扣命中快照 JSON |
| 28 | `paid_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 已支付入账金额 |
| 29 | `status` | `tinyint` | 否 | `0` | MUL | — | — | — | 账单状态：0待支付 1已支付 4已取消 5已退款 |
| 30 | `due_date` | `date` | 是 | `NULL` | — | — | — | — | 账单到期日（付款期限） |
| 31 | `paid_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 账单支付完成时间 |
| 32 | `refund_trace_id` | `varchar(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 退款链路追踪号 |
| 33 | `refund_method` | `varchar(32)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 退款方式 |
| 34 | `refund_amount` | `decimal(12,2)` | 是 | `NULL` | — | — | — | — | 累计已退款金额（含部分退款标记，逐次累加；全额退款时等于可退基数） |
| 35 | `refunded_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 退款完成时间 |
| 36 | `created_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | 创建时间 |
| 37 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 更新时间 |
| 38 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 账单备注 |
| 39 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作人快照 |
| 40 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `fk_invoices_user_coupon_id` | 否 | `BTREE` | `user_coupon_id` | 3 | — |
| `idx_stage2_invoices_coupon_id` | 否 | `BTREE` | `coupon_id` | 3 | — |
| `invoices_created_at_idx` | 否 | `BTREE` | `created_at` | 2,707 | — |
| `invoices_fulfillment_pending_idx` | 否 | `BTREE` | `fulfillment_pending`, `fulfillment_pending_at` | 1 | — |
| `invoices_invoice_no_unique` | 是 | `BTREE` | `invoice_no` | 3,075 | — |
| `invoices_order_id_idx` | 否 | `BTREE` | `order_id` | 538 | — |
| `invoices_origin_invoice_id_foreign` | 否 | `BTREE` | `origin_invoice_id` | 1 | — |
| `invoices_product_id_idx` | 否 | `BTREE` | `product_id` | 60 | — |
| `invoices_requires_refund_idx` | 否 | `BTREE` | `requires_refund`, `id` | 3,075 | — |
| `invoices_service_id_idx` | 否 | `BTREE` | `service_id` | 177 | — |
| `invoices_service_status_id_idx` | 否 | `BTREE` | `service_id`, `status`, `id` | 3,076 | — |
| `invoices_status_due_date_index` | 否 | `BTREE` | `status`, `due_date` | 866 | — |
| `invoices_status_paid_at_idx` | 否 | `BTREE` | `status`, `paid_at` | 1,118 | — |
| `invoices_trace_id_idx` | 否 | `BTREE` | `trace_id` | 2,453 | — |
| `invoices_user_status_created_idx` | 否 | `BTREE` | `user_id`, `status`, `created_at` | 2,926 | — |
| `invoices_user_status_id_idx` | 否 | `BTREE` | `user_id`, `status`, `id` | 3,075 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 3,501 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_invoices_order_id` | `order_id` | `orders` | `id` | `NO ACTION` | `SET NULL` |
| `fk_invoices_product_id` | `product_id` | `products` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_invoices_user_coupon_id` | `user_coupon_id` | `user_coupons` | `id` | `NO ACTION` | `SET NULL` |
| `fk_invoices_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_stage2_invoices_coupon_id` | `coupon_id` | `coupons` | `id` | `NO ACTION` | `SET NULL` |
| `fk_stage2_invoices_service_id` | `service_id` | `services` | `id` | `NO ACTION` | `SET NULL` |
| `invoices_origin_invoice_id_foreign` | `origin_invoice_id` | `invoices` | `id` | `NO ACTION` | `SET NULL` |

### 2.30 `jobs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`16 KB`
- 自增值：`373`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：框架队列作业表，保存待执行作业的载荷、重试次数与可见时间

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `queue` | `varchar(255)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `payload` | `longtext` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `attempts` | `tinyint unsigned` | 否 | — | — | — | — | — | — |
| 5 | `reserved_at` | `int unsigned` | 是 | `NULL` | — | — | — | — | — |
| 6 | `available_at` | `int unsigned` | 否 | — | — | — | — | — | — |
| 7 | `created_at` | `int unsigned` | 否 | — | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `jobs_queue_index` | 否 | `BTREE` | `queue` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.31 `marketing_product_group_items`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：营销产品组成员表，记录营销组包含的商品

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `marketing_product_group_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `product_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 4 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 5 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `marketing_product_group_items_group_product_unique` | 是 | `BTREE` | `marketing_product_group_id`, `product_id` | 0 | — |
| `marketing_product_group_items_product_idx` | 否 | `BTREE` | `product_id` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_mpgi_group` | `marketing_product_group_id` | `marketing_product_groups` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_mpgi_product` | `product_id` | `products` | `id` | `NO ACTION` | `RESTRICT` |

### 2.32 `marketing_product_groups`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：—
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：营销产品组表，为会员等级折扣等营销规则提供商品分组

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `name` | `varchar(50)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 营销组名称 |
| 3 | `sort_order` | `int unsigned` | 否 | `0` | MUL | — | — | — | 排序值，越小越靠前 |
| 4 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 5 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `marketing_product_groups_name_unique` | 是 | `BTREE` | `name` | 0 | — |
| `marketing_product_groups_sort_idx` | 否 | `BTREE` | `sort_order`, `id` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.33 `media_files`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`25`
- 数据大小：`16 KB`
- 索引大小：`64 KB`
- 自增值：`99`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：媒体文件登记表，记录素材路径、类型、尺寸与所属分组

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `filename` | `varchar(255)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `path` | `varchar(500)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 相对路径，如 /uploads/content/20260419/cover_xxx.jpg |
| 4 | `url` | `varchar(500)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 完整访问 URL |
| 5 | `mime_type` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `size` | `bigint unsigned` | 否 | `0` | — | — | — | — | 文件大小(字节) |
| 7 | `width` | `int unsigned` | 是 | `NULL` | — | — | — | — | 图片宽度 |
| 8 | `height` | `int unsigned` | 是 | `NULL` | — | — | — | — | 图片高度 |
| 9 | `group` | `varchar(50)` | 否 | `content` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 分组: content, avatar, brand 等 |
| 10 | `disk` | `varchar(24)` | 否 | `local` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 存储驱动标识：local 或远程存储插件 slug |
| 11 | `object_key` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 远程对象键（完整键，含 media/ 前缀段）；本地行为 NULL |
| 12 | `uploaded_by` | `bigint unsigned` | 否 | `0` | MUL | — | — | — | 上传管理员ID |
| 13 | `created_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |
| 14 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `media_files_created_at_index` | 否 | `BTREE` | `created_at` | 7 | — |
| `media_files_disk_index` | 否 | `BTREE` | `disk` | 2 | — |
| `media_files_group_index` | 否 | `BTREE` | `group` | 2 | — |
| `media_files_uploaded_by_index` | 否 | `BTREE` | `uploaded_by` | 1 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 25 | — |

#### 外键约束

无数据库级外键约束。

### 2.34 `member_level_group_discounts`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：会员等级与营销组折扣矩阵表，折扣类型为保留比例时 90 表示九折

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `member_level_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 关联 member_levels.id |
| 3 | `marketing_product_group_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 关联 marketing_product_groups.id |
| 4 | `discount_type` | `tinyint` | 否 | — | — | — | — | — | 1=百分比(折后保留,bates语义) 2=固定金额减免 |
| 5 | `discount_value` | `decimal(12,2)` | 否 | `100.00` | — | — | — | — | 百分比时0-100；固定金额时减免额 |
| 6 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 7 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `member_level_group_discounts_group_idx` | 否 | `BTREE` | `marketing_product_group_id` | 0 | — |
| `member_level_group_discounts_level_group_unique` | 是 | `BTREE` | `member_level_id`, `marketing_product_group_id` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_mlgd_group` | `marketing_product_group_id` | `marketing_product_groups` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_mlgd_level` | `member_level_id` | `member_levels` | `id` | `NO ACTION` | `RESTRICT` |

### 2.35 `member_levels`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`3`
- 数据大小：`16 KB`
- 索引大小：`0 B`
- 自增值：`4`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：会员等级表，记录等级名称、启用状态与备注

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `name` | `varchar(50)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `status` | `tinyint` | 否 | `1` | — | — | — | — | 0=禁用 1=启用 |
| 4 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 6 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 3 | — |

#### 外键约束

无数据库级外键约束。

### 2.36 `message_configurations`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`0 B`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：消息场景配置表，按受众与场景加密保存接收组与渠道策略

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `audience` | `varchar(16)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 接收对象：customer 客户、admin 管理员 |
| 3 | `message_type` | `varchar(64)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 核心声明的消息场景编码，不允许后台自由新增 |
| 4 | `configuration_encrypted` | `mediumtext` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 该场景全部接收组与渠道配置的加密密文，禁止回显 |
| 5 | `revision` | `int unsigned` | 否 | `1` | — | — | — | — | 整条配置的乐观锁修订号，从 1 递增 |
| 6 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 7 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `message_configurations_audience_type_unique` | 是 | `BTREE` | `audience`, `message_type` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.37 `message_deliveries`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`0 B`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：消息投递任务表，承载事务 Outbox 与租约状态机

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `recipient_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 消息接收对象主键 |
| 3 | `request_key` | `char(36)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 固定发送请求 UUID；重试保持不变 |
| 4 | `channel` | `varchar(24)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 发送渠道编码 |
| 5 | `channel_name` | `varchar(128)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 发送通道名称快照 |
| 6 | `payload_encrypted` | `mediumtext` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 收件地址与模板渲染结果的加密快照，禁止写入日志或队列 |
| 7 | `status` | `varchar(24)` | 否 | `pending` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | pending、processing、accepted、delivered、failed、unknown |
| 8 | `attempts` | `int unsigned` | 否 | `0` | — | — | — | — | 实际领取次数 |
| 9 | `next_attempt_at` | `datetime` | 否 | — | — | — | — | — | 允许再次投递的时间 |
| 10 | `queue_after` | `datetime` | 否 | — | — | — | — | — | 队列补投租约到期时间 |
| 11 | `lease_key` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 发送进程领取令牌 |
| 12 | `lease_until` | `timestamp` | 是 | `NULL` | — | — | — | — | 领取租约截止时间 |
| 13 | `error_code` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 不含厂商响应正文的安全错误编码 |
| 14 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 15 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `message_deliveries_due_idx` | 否 | `BTREE` | `status`, `next_attempt_at`, `queue_after` | 0 | — |
| `message_deliveries_recipient_channel_unique` | 是 | `BTREE` | `recipient_id`, `channel` | 0 | — |
| `message_deliveries_request_key_unique` | 是 | `BTREE` | `request_key` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.38 `message_delivery_attempt_logs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`0 B`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：消息投递逐次尝试表，记录状态、安全错误码与耗时

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `delivery_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 投递任务主键 |
| 3 | `attempt_no` | `int unsigned` | 否 | — | — | — | — | — | 任务尝试序号 |
| 4 | `status` | `varchar(24)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 本次发送状态 |
| 5 | `error_code` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 安全错误编码 |
| 6 | `duration_ms` | `int unsigned` | 否 | `0` | — | — | — | — | 发送耗时毫秒 |
| 7 | `created_at` | `datetime` | 否 | — | — | — | — | — | 领取开始时间 |
| 8 | `finished_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 尝试结束时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `message_delivery_attempt_logs_time_idx` | 否 | `BTREE` | `delivery_id`, `created_at` | 0 | — |
| `message_delivery_attempt_logs_unique` | 是 | `BTREE` | `delivery_id`, `attempt_no` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.39 `message_delivery_attempts`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`21`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`25`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：消息投递尝试表，同一逻辑投递可有多条尝试，记录结果与耗时

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `message_log_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | message_logs.id |
| 3 | `attempt_no` | `int unsigned` | 否 | — | — | — | — | — | 同一投递内的尝试序号，从 1 开始 |
| 4 | `status` | `varchar(24)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 尝试结果: success, failed |
| 5 | `error_code` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 异常类名，不含厂商响应正文 |
| 6 | `error_msg` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 失败摘要，最多 500 字 |
| 7 | `duration_ms` | `int unsigned` | 否 | `0` | — | — | — | — | 本次尝试耗时毫秒 |
| 8 | `created_at` | `datetime` | 否 | — | — | — | — | — | 尝试开始时间 |
| 9 | `finished_at` | `datetime` | 是 | `NULL` | — | — | — | — | 尝试结束时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `message_delivery_attempts_log_time_index` | 否 | `BTREE` | `message_log_id`, `created_at` | 21 | — |
| `message_delivery_attempts_unique` | 是 | `BTREE` | `message_log_id`, `attempt_no` | 21 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 21 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `message_delivery_attempts_message_log_id_fk` | `message_log_id` | `message_logs` | `id` | `NO ACTION` | `CASCADE` |

### 2.40 `message_logs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`950`
- 数据大小：`16.52 MB`
- 索引大小：`592 KB`
- 自增值：`4544`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：消息发送日志表，记录渠道、收件方、模板、投递状态与来源对象

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | 消息日志ID |
| 2 | `plugin_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 插件ID |
| 3 | `driver_key` | `varchar(120)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 驱动标识 |
| 4 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪ID |
| 5 | `delivery_key` | `varchar(36)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 跨队列重试稳定的投递键；非队列渠道为 NULL |
| 6 | `channel` | `varchar(20)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 消息渠道：email/sms |
| 7 | `recipient` | `varchar(255)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 接收人邮箱或手机号 |
| 8 | `template_code` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 业务模板编码或供应商模板ID |
| 9 | `subject` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 邮件主题 |
| 10 | `content` | `mediumtext` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 发送内容快照 |
| 11 | `params_json` | `json` | 是 | `NULL` | — | — | — | — | 渲染参数快照 |
| 12 | `provider` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 供应商或驱动 |
| 13 | `request_id` | `varchar(100)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 供应商请求ID |
| 14 | `status` | `varchar(24)` | 否 | `pending` | — | — | utf8mb4 | utf8mb4_unicode_ci | 发送状态 |
| 15 | `error_msg` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 失败原因 |
| 16 | `sent_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 发送完成时间 |
| 17 | `origin_type` | `varchar(50)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 来源类型快照 |
| 18 | `origin_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 来源ID快照 |
| 19 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 20 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `message_logs_channel_driver_idx` | 否 | `BTREE` | `channel`, `driver_key`, `created_at` | 951 | — |
| `message_logs_delivery_key_index` | 否 | `BTREE` | `delivery_key` | 1 | — |
| `message_logs_driver_created_idx` | 否 | `BTREE` | `driver_key`, `created_at` | 951 | — |
| `message_logs_origin_idx` | 否 | `BTREE` | `origin_type`, `origin_id` | 2 | — |
| `message_logs_plugin_created_idx` | 否 | `BTREE` | `plugin_id`, `created_at` | 951 | — |
| `message_logs_recipient_created_at_idx` | 否 | `BTREE` | `recipient`, `created_at` | 951 | — |
| `message_logs_request_id_idx` | 否 | `BTREE` | `request_id` | 42 | — |
| `message_logs_trace_idx` | 否 | `BTREE` | `trace_id` | 951 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 950 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `message_logs_plugin_fk` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `SET NULL` |

### 2.41 `message_recipients`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`0 B`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：消息接收对象表，记录收件人与站内已读状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `message_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 逻辑消息主键 |
| 3 | `audience` | `varchar(16)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | customer 客户、admin 管理员、robot 群机器人 |
| 4 | `account_id` | `bigint unsigned` | 否 | `0` | — | — | — | — | 客户或管理员主键；群机器人为 0 |
| 5 | `target_key` | `varchar(64)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 账户或机器人渠道唯一标识 |
| 6 | `group_key` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 来源接收组标识 |
| 7 | `group_name` | `varchar(128)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 来源接收组名称快照 |
| 8 | `read_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 站内消息阅读时间；发送成功不等于已读 |
| 9 | `created_at` | `datetime` | 否 | — | — | — | — | — | 接收对象生成时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `message_recipients_audience_account_idx` | 否 | `BTREE` | `audience`, `account_id`, `read_at` | 0 | — |
| `message_recipients_message_target_unique` | 是 | `BTREE` | `message_id`, `audience`, `target_key` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.42 `message_scene_templates`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`60`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`61`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：消息场景与通知模板关联表，承载一个场景多渠道多模板关系

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `audience` | `varchar(16)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | customer 客户、admin 管理员 |
| 3 | `scene_code` | `varchar(64)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 消息场景编码 |
| 4 | `channel` | `varchar(20)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 模板渠道：email、sms |
| 5 | `template_code` | `varchar(64)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 通知模板编码 |
| 6 | `sort_order` | `int unsigned` | 否 | `0` | — | — | — | — | 同场景同渠道内的候选顺序 |
| 7 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 8 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `message_scene_templates_scene_channel_idx` | 否 | `BTREE` | `scene_code`, `channel` | 49 | — |
| `message_scene_templates_unique` | 是 | `BTREE` | `audience`, `scene_code`, `channel`, `template_code` | 60 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 60 | — |

#### 外键约束

无数据库级外键约束。

### 2.43 `messages`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`0 B`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：业务消息流水表，记录幂等事件与业务对象引用

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `event_key` | `varchar(64)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 业务事件幂等摘要，同一事件重复发布只落一条 |
| 3 | `scene` | `varchar(64)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 固定消息场景编码 |
| 4 | `purpose` | `varchar(24)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 用途：notification、system、verification |
| 5 | `business_type` | `varchar(64)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 业务对象类型 |
| 6 | `business_id` | `bigint unsigned` | 否 | — | — | — | — | — | 关联业务对象主键 |
| 7 | `created_at` | `datetime` | 否 | — | — | — | — | — | 事件记录时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `messages_event_key_unique` | 是 | `BTREE` | `event_key` | 0 | — |
| `messages_scene_created_idx` | 否 | `BTREE` | `scene`, `created_at` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.44 `migrations`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`191`
- 数据大小：`48 KB`
- 索引大小：`0 B`
- 自增值：`314`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：框架迁移账本表，记录已执行迁移文件名与批次

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `int unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `migration` | `varchar(255)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `batch` | `int` | 否 | — | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 191 | — |

#### 外键约束

无数据库级外键约束。

### 2.45 `notice_reads`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`315`
- 数据大小：`48 KB`
- 索引大小：`32 KB`
- 自增值：`547`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：公告已读表，记录会员阅读公告的时间

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `article_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 4 | `read_at` | `timestamp` | 否 | — | — | — | — | — | — |
| 5 | `created_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` | — | DEFAULT_GENERATED | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `notice_reads_article_id_index` | 否 | `BTREE` | `article_id` | 10 | — |
| `notice_reads_user_id_article_id_unique` | 是 | `BTREE` | `user_id`, `article_id` | 315 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 315 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_notice_reads_article_id` | `article_id` | `content_articles` | `id` | `NO ACTION` | `CASCADE` |
| `fk_stage2_notice_reads_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `CASCADE` |

### 2.46 `notification_templates`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`77`
- 数据大小：`1.52 MB`
- 索引大小：`48 KB`
- 自增值：`139`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：通知模板表，按渠道维护模板编码、正文、变量与运营商模板号

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `channel` | `varchar(20)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `code` | `varchar(64)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `name` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `description` | `varchar(500)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `audience` | `varchar(20)` | 否 | `user` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `scene_code` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 所属消息场景编码；未映射为 __unmapped |
| 8 | `subject` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `content` | `longtext` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `variables_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 11 | `provider_variables_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 12 | `provider_template_id` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 13 | `is_enabled` | `tinyint(1)` | 否 | `1` | — | — | — | — | — |
| 14 | `is_custom` | `tinyint(1)` | 否 | `0` | — | — | — | — | — |
| 15 | `sort_order` | `int unsigned` | 否 | `0` | — | — | — | — | — |
| 16 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 17 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `notification_templates_channel_audience_enabled_index` | 否 | `BTREE` | `channel`, `audience`, `is_enabled` | 5 | — |
| `notification_templates_channel_code_unique` | 是 | `BTREE` | `channel`, `code` | 95 | — |
| `notification_templates_scene_index` | 否 | `BTREE` | `scene_code`, `channel` | 46 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 95 | — |

#### 外键约束

无数据库级外键约束。

### 2.47 `orders`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`477`
- 数据大小：`1.44 MB`
- 索引大小：`192 KB`
- 自增值：`3338`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：履约工单表，记录购买续费升降配的商品快照、金额、优惠与履约状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `order_no` | `varchar(32)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 业务订单号，对外展示与对账使用 |
| 3 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 下单用户ID |
| 4 | `product_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联商品ID |
| 5 | `product_spec_snapshot` | `varchar(200)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 下单时的商品规格展示快照 |
| 6 | `product_type_snapshot` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 下单时的商品类型快照 |
| 7 | `service_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联服务实例ID |
| 8 | `type` | `varchar(20)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | new&#124;renew&#124;upgrade&#124;downgrade |
| 9 | `coupon_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 使用的优惠券模板ID |
| 10 | `user_coupon_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 使用的用户优惠券ID |
| 11 | `coupon_code` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 使用的优惠码快照 |
| 12 | `amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 订单应收金额 |
| 13 | `currency` | `varchar(3)` | 否 | `CNY` | — | — | utf8mb4 | utf8mb4_unicode_ci | 币种，固定 CNY |
| 14 | `discount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 订单优惠抵扣金额 |
| 15 | `member_discount_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 会员等级×营销组折扣减免金额 |
| 16 | `paid_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 已支付入账金额 |
| 17 | `refund_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 累计已退款金额（账单退款标记投影，由 TradeLifecycleService 唯一写入） |
| 18 | `billing_cycle` | `varchar(20)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 计费周期：monthly/quarterly/semiannually/annually/biennially/triennially/onetime |
| 19 | `quantity` | `int unsigned` | 否 | `1` | — | — | — | — | 购买数量 |
| 20 | `config_snapshot` | `json` | 是 | `NULL` | — | — | — | — | 下单配置快照 JSON |
| 21 | `config_pricing_snapshot` | `json` | 是 | `NULL` | — | — | — | — | 配置项计价快照 JSON |
| 22 | `coupon_snapshot` | `json` | 是 | `NULL` | — | — | — | — | 优惠券使用快照 JSON |
| 23 | `member_discount_snapshot` | `json` | 是 | `NULL` | — | — | — | — | 会员折扣命中快照 JSON |
| 24 | `service_snapshot` | `json` | 是 | `NULL` | — | — | — | — | 服务实例快照：{instance_id, hostname} |
| 25 | `status` | `tinyint` | 否 | `0` | MUL | — | — | — | 订单状态：0待支付 1已支付 4已取消 5已退款 |
| 26 | `paid_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 支付完成时间 |
| 27 | `refunded_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 最近一次退款标记时间 |
| 28 | `created_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |
| 29 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 30 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 备注 |
| 31 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作人 |
| 32 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |
| 33 | `projection_type` | `varchar(32)` | 否 | `provisioning` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 内部投影类型：provisioning=开通投影 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `orders_coupon_id_idx` | 否 | `BTREE` | `coupon_id` | 3 | — |
| `orders_created_at_idx` | 否 | `BTREE` | `created_at` | 477 | — |
| `orders_order_no_unique` | 是 | `BTREE` | `order_no` | 477 | — |
| `orders_product_id_idx` | 否 | `BTREE` | `product_id` | 58 | — |
| `orders_projection_type_idx` | 否 | `BTREE` | `projection_type` | 1 | — |
| `orders_service_status_id_idx` | 否 | `BTREE` | `service_id`, `status`, `id` | 477 | — |
| `orders_status_type_created_at_idx` | 否 | `BTREE` | `status`, `type`, `created_at`, `id` | 477 | — |
| `orders_trace_id_idx` | 否 | `BTREE` | `trace_id` | 222 | — |
| `orders_user_coupon_id_idx` | 否 | `BTREE` | `user_coupon_id` | 3 | — |
| `orders_user_status_id_idx` | 否 | `BTREE` | `user_id`, `status`, `id` | 477 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 477 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_orders_coupon_id` | `coupon_id` | `coupons` | `id` | `NO ACTION` | `SET NULL` |
| `fk_stage2_orders_product_id` | `product_id` | `products` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_stage2_orders_service_id` | `service_id` | `services` | `id` | `NO ACTION` | `SET NULL` |
| `fk_stage2_orders_user_coupon_id` | `user_coupon_id` | `user_coupons` | `id` | `NO ACTION` | `SET NULL` |
| `fk_stage2_orders_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.48 `password_reset_tokens`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`0 B`
- 自增值：—
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：框架密码重置令牌表，按邮箱保存重置令牌与生成时间

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `email` | `varchar(255)` | 否 | — | PRI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 2 | `token` | `varchar(255)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `email` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.49 `payment_callbacks`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`476`
- 数据大小：`1.52 MB`
- 索引大小：`128 KB`
- 自增值：`652`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：支付回调审计表，保存第三方通知、查询、退款等回调验签结果

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | 支付回调自增主键 |
| 2 | `payment_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 关联支付记录ID |
| 3 | `plugin_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 来源支付插件ID |
| 4 | `gateway_key` | `varchar(120)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 支付网关标识快照 |
| 5 | `callback_type` | `varchar(20)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 回调类型：notify/query/refund 等 |
| 6 | `gateway_trade_no` | `varchar(100)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 第三方交易号 |
| 7 | `payload_json` | `json` | 是 | `NULL` | — | — | — | — | 回调载荷 JSON |
| 8 | `is_verified` | `tinyint` | 否 | `0` | MUL | — | — | — | 验签结果：0未通过/未验签 1已通过 |
| 9 | `received_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 收到回调时间 |
| 10 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 回调备注或处理说明 |
| 11 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作人快照 |
| 12 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |
| 13 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 创建时间 |
| 14 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 更新时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `payment_callbacks_gateway_key_idx` | 否 | `BTREE` | `gateway_key`, `received_at` | 447 | — |
| `payment_callbacks_gateway_trade_no_idx` | 否 | `BTREE` | `gateway_trade_no` | 322 | — |
| `payment_callbacks_payment_type_unique` | 是 | `BTREE` | `payment_id`, `callback_type` | 476 | — |
| `payment_callbacks_plugin_received_idx` | 否 | `BTREE` | `plugin_id`, `received_at` | 447 | — |
| `payment_callbacks_trace_id_idx` | 否 | `BTREE` | `trace_id` | 254 | — |
| `payment_callbacks_verified_received_idx` | 否 | `BTREE` | `is_verified`, `received_at` | 474 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 476 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_payment_callbacks_payment_id` | `payment_id` | `payments` | `id` | `NO ACTION` | `CASCADE` |
| `payment_callbacks_plugin_fk` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `SET NULL` |

### 2.50 `payments`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`419`
- 数据大小：`1.52 MB`
- 索引大小：`224 KB`
- 自增值：`510`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：第三方支付记录表，仅记录真实外部资金流入和退款状态，不记录余额/免费/手工开服

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | 支付记录自增主键 |
| 2 | `payment_no` | `varchar(32)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 内部支付单号 |
| 3 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 支付用户ID |
| 4 | `order_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 内部订单/开通投影ID，仅用于流程追踪 |
| 5 | `invoice_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联账单ID |
| 6 | `plugin_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 来源支付插件ID |
| 7 | `plugin_key` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 来源支付插件标识快照，卸载插件后仍可追溯 |
| 8 | `plugin_name` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 来源支付插件名称快照，用于展示与对账 |
| 9 | `gateway_key` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 支付网关标识快照 |
| 10 | `payment_type` | `varchar(20)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 网关内支付类型快照，如 wxpay/alipay，区分同插件下的多条通道 |
| 11 | `option_key` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 下单所选通道标识快照，如 yipay:wxpay |
| 12 | `option_name` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 下单所选通道名称快照，如 易支付 - 微信 |
| 13 | `trade_no` | `varchar(128)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 第三方交易号 |
| 14 | `amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 第三方支付金额 |
| 15 | `currency` | `varchar(3)` | 否 | `CNY` | — | — | utf8mb4 | utf8mb4_unicode_ci | 币种，固定 CNY |
| 16 | `status` | `tinyint` | 否 | `0` | MUL | — | — | — | 支付状态：0待支付 1已支付 4已取消 5已退款 |
| 17 | `callback_raw` | `json` | 是 | `NULL` | — | — | — | — | 最近一次回调原始载荷 JSON |
| 18 | `paid_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 第三方确认支付时间 |
| 19 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 创建时间 |
| 20 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 更新时间 |
| 21 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 支付备注 |
| 22 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作人快照 |
| 23 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `payments_invoice_gateway_status_id_idx` | 否 | `BTREE` | `invoice_id`, `status`, `id` | 419 | — |
| `payments_invoice_status_created_at_idx` | 否 | `BTREE` | `invoice_id`, `status`, `created_at`, `id` | 419 | — |
| `payments_order_status_idx` | 否 | `BTREE` | `order_id`, `status` | 281 | — |
| `payments_payment_no_unique` | 是 | `BTREE` | `payment_no` | 419 | — |
| `payments_plugin_status_paid_idx` | 否 | `BTREE` | `plugin_id`, `status`, `paid_at` | 371 | — |
| `payments_plugin_trade_unique` | 是 | `BTREE` | `plugin_id`, `gateway_key`, `trade_no` | 323 | — |
| `payments_status_paid_at_idx` | 否 | `BTREE` | `status`, `paid_at` | 370 | — |
| `payments_trace_id_idx` | 否 | `BTREE` | `trace_id` | 262 | — |
| `payments_trade_no_index` | 否 | `BTREE` | `trade_no` | 322 | — |
| `payments_user_status_created_idx` | 否 | `BTREE` | `user_id`, `status`, `created_at` | 419 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 419 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_payments_invoice_id` | `invoice_id` | `invoices` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_payments_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_stage2_payments_order_id` | `order_id` | `orders` | `id` | `NO ACTION` | `SET NULL` |
| `payments_plugin_fk` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `SET NULL` |

### 2.51 `personal_access_tokens`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`11`
- 数据大小：`16 KB`
- 索引大小：`48 KB`
- 自增值：`20`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：Sanctum 访问令牌表，保存 API 令牌、能力清单与最近使用时间

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `tokenable_type` | `varchar(255)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `tokenable_id` | `bigint unsigned` | 否 | — | — | — | — | — | — |
| 4 | `name` | `text` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `token` | `varchar(64)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `abilities` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `last_used_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 8 | `expires_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |
| 9 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 10 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `personal_access_tokens_expires_at_index` | 否 | `BTREE` | `expires_at` | 1 | — |
| `personal_access_tokens_token_unique` | 是 | `BTREE` | `token` | 11 | — |
| `personal_access_tokens_tokenable_type_tokenable_id_index` | 否 | `BTREE` | `tokenable_type`, `tokenable_id` | 2 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 11 | — |

#### 外键约束

无数据库级外键约束。

### 2.52 `product_upstream_bindings`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`134`
- 数据大小：`8.52 MB`
- 索引大小：`80 KB`
- 自增值：`277`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：商品上游绑定表，记录商品对应的上游商品、开通策略与同步状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `product_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `supplier_plugin_binding_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 4 | `plugin_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 5 | `provider_key` | `varchar(120)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `upstream_product_id` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `upstream_product_snapshot_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 8 | `option_schema_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 9 | `provision_policy_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 10 | `auto_setup` | `tinyint(1)` | 否 | `0` | — | — | — | — | — |
| 11 | `status` | `tinyint unsigned` | 否 | `1` | — | — | — | — | — |
| 12 | `last_synced_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 13 | `last_sync_error` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 14 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 15 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 134 | — |
| `product_upstream_bindings_supplier_plugin_binding_id_foreign` | 否 | `BTREE` | `supplier_plugin_binding_id` | 2 | — |
| `product_upstream_plugin_status_idx` | 否 | `BTREE` | `plugin_id`, `status` | 2 | — |
| `product_upstream_product_status_idx` | 否 | `BTREE` | `product_id`, `status` | 134 | — |
| `product_upstream_provider_status_idx` | 否 | `BTREE` | `provider_key`, `status` | 2 | — |
| `product_upstream_unique` | 是 | `BTREE` | `product_id`, `supplier_plugin_binding_id`, `upstream_product_id` | 134 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `product_upstream_bindings_plugin_id_foreign` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `RESTRICT` |
| `product_upstream_bindings_product_id_foreign` | `product_id` | `products` | `id` | `NO ACTION` | `RESTRICT` |
| `product_upstream_bindings_supplier_plugin_binding_id_foreign` | `supplier_plugin_binding_id` | `supplier_plugin_bindings` | `id` | `NO ACTION` | `RESTRICT` |

### 2.53 `products`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`134`
- 数据大小：`9.52 MB`
- 索引大小：`48 KB`
- 自增值：`386`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：商品表，记录可售卖产品的分类、定价、库存、上游绑定和开通策略

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | 商品自增主键 |
| 2 | `product_group_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 当前所属商品分组ID |
| 3 | `service_type_code` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 服务类型代码，用于前后端能力分流 |
| 4 | `product_type` | `varchar(30)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 商品类型：vps/dedicated/hosting/domain/other |
| 5 | `console_template` | `varchar(32)` | 否 | `compute` | — | — | utf8mb4 | utf8mb4_unicode_ci | 用户控制台模板：compute 或 port_mapping |
| 6 | `custom_display_name` | `varchar(190)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 自定义展示名称 |
| 7 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 商品备注 |
| 8 | `pricing` | `json` | 否 | — | — | — | — | — | 周期价格 JSON，如 monthly/quarterly/annually |
| 9 | `setup_fee` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 初装费 |
| 10 | `config_options` | `json` | 是 | `NULL` | — | — | — | — | 可选配置项 JSON |
| 11 | `purchase_requires` | `json` | 是 | `NULL` | — | — | — | — | 购买限制 JSON，如实名认证、手机号要求 |
| 12 | `stock` | `int` | 否 | `-1` | — | — | — | — | 库存数量，-1 表示不限 |
| 13 | `status` | `tinyint` | 否 | `1` | — | — | — | — | 商品状态：0下架 1上架 |
| 14 | `sort_order` | `int` | 否 | `0` | — | — | — | — | 排序值，越小越靠前 |
| 15 | `auto_setup` | `tinyint` | 否 | `0` | — | — | — | — | 是否自动开通：0手动 1自动 |
| 16 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 创建时间 |
| 17 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 更新时间 |
| 18 | `deleted_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | 软删除时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 134 | — |
| `products_deleted_at_idx` | 否 | `BTREE` | `deleted_at` | 8 | — |
| `products_group_status_sort_id_idx` | 否 | `BTREE` | `product_group_id`, `status`, `sort_order`, `id` | 134 | — |
| `products_type_status_index` | 否 | `BTREE` | `product_type`, `status` | 9 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `products_product_group_fk` | `product_group_id` | `third_product_groups` | `id` | `NO ACTION` | `RESTRICT` |

### 2.54 `promotion_ambassadors`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`16 KB`
- 自增值：`2`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：推广大使表，记录新购与续费返利比例及启用状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `name` | `varchar(50)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 大使名称 |
| 3 | `reward_rate` | `decimal(5,2)` | 否 | `0.00` | — | — | — | — | 返利比例（%）0-100 |
| 4 | `status` | `tinyint` | 否 | `1` | MUL | — | — | — | 0=禁用 1=启用 |
| 5 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 备注 |
| 6 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 7 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 8 | `renewal_reward_rate` | `decimal(5,2)` | 否 | `0.00` | — | — | — | — | 续费返利比例（%）0-100 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `idx_promotion_ambassador_status` | 否 | `BTREE` | `status` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.55 `recharge_records`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`126`
- 数据大小：`64 KB`
- 索引大小：`144 KB`
- 自增值：`126`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：充值记录表，记录充值场景、方向、金额与关联订单支付流水

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `record_no` | `varchar(32)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 充值流水号，对外展示 |
| 3 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 充值所属用户ID |
| 4 | `order_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联订单ID |
| 5 | `invoice_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联账单ID |
| 6 | `payment_id` | `bigint unsigned` | 是 | `NULL` | UNI | — | — | — | 关联支付记录ID |
| 7 | `account_transaction_id` | `bigint unsigned` | 是 | `NULL` | UNI | — | — | — | 关联账户流水ID |
| 8 | `refund_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联退款记录ID |
| 9 | `origin_recharge_record_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 原充值记录ID：退款红字充值指向被冲正的记录 |
| 10 | `scene` | `varchar(30)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 业务场景：recharge=支付充值 admin_recharge=管理员充值 refund=退款 等 |
| 11 | `direction` | `varchar(8)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 资金方向：in=入账 out=出账 |
| 12 | `amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 充值金额 |
| 13 | `currency` | `varchar(3)` | 否 | `CNY` | — | — | utf8mb4 | utf8mb4_unicode_ci | 币种，固定 CNY |
| 14 | `entry_type` | `varchar(30)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 入账类型：third_party_payment/manual_recharge/account_recharge/refund_offset |
| 15 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 充值备注 |
| 16 | `operator_type` | `varchar(30)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作人类型：admin/member/client/system/legacy |
| 17 | `operator_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 操作人ID，空=系统自动 |
| 18 | `operator_name` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作人名称快照 |
| 19 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |
| 20 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 21 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 126 | — |
| `recharge_records_account_transaction_id_unique` | 是 | `BTREE` | `account_transaction_id` | 16 | — |
| `recharge_records_invoice_id_id_index` | 否 | `BTREE` | `invoice_id`, `id` | 126 | — |
| `recharge_records_order_id_id_index` | 否 | `BTREE` | `order_id`, `id` | 126 | — |
| `recharge_records_origin_recharge_record_id_id_index` | 否 | `BTREE` | `origin_recharge_record_id`, `id` | 126 | — |
| `recharge_records_payment_id_unique` | 是 | `BTREE` | `payment_id` | 117 | — |
| `recharge_records_record_no_unique` | 是 | `BTREE` | `record_no` | 126 | — |
| `recharge_records_refund_id_foreign` | 否 | `BTREE` | `refund_id` | 1 | — |
| `recharge_records_trace_id_index` | 否 | `BTREE` | `trace_id` | 126 | — |
| `recharge_records_user_id_created_at_index` | 否 | `BTREE` | `user_id`, `created_at` | 123 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `recharge_records_account_transaction_id_foreign` | `account_transaction_id` | `finance_ledger_entries` | `id` | `NO ACTION` | `SET NULL` |
| `recharge_records_invoice_id_foreign` | `invoice_id` | `invoices` | `id` | `NO ACTION` | `SET NULL` |
| `recharge_records_order_id_foreign` | `order_id` | `orders` | `id` | `NO ACTION` | `SET NULL` |
| `recharge_records_origin_recharge_record_id_foreign` | `origin_recharge_record_id` | `recharge_records` | `id` | `NO ACTION` | `SET NULL` |
| `recharge_records_payment_id_foreign` | `payment_id` | `payments` | `id` | `NO ACTION` | `SET NULL` |
| `recharge_records_refund_id_foreign` | `refund_id` | `finance_refunds` | `id` | `NO ACTION` | `SET NULL` |
| `recharge_records_user_id_foreign` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.56 `referral_account_logs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`6`
- 数据大小：`16 KB`
- 索引大小：`64 KB`
- 自增值：`6`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：推荐账户流水表，记录奖励冻结释放与提现各事件后的余额快照

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `event_type` | `varchar(30)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | reward_frozen&#124;reward_released&#124;withdraw_apply&#124;withdraw_approved&#124;withdraw_rejected |
| 4 | `change_amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 正=增加 负=减少 |
| 5 | `frozen_balance` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 6 | `available_balance` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 7 | `pending_withdrawal_balance` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 8 | `withdrawn_balance` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 9 | `remark` | `varchar(255)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `reference_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 11 | `reference_type` | `varchar(30)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 12 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 13 | `trace_id` | `varchar(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 14 | `created_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` | MUL | DEFAULT_GENERATED | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `idx_referral_account_related` | 否 | `BTREE` | `reference_type`, `reference_id` | 6 | — |
| `idx_referral_account_user_created_idx` | 否 | `BTREE` | `user_id`, `created_at`, `id` | 6 | — |
| `idx_referral_account_user_type` | 否 | `BTREE` | `user_id`, `event_type` | 2 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 6 | — |
| `referral_account_logs_created_at_index` | 否 | `BTREE` | `created_at` | 1 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_referral_account_logs_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.57 `referral_rewards`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`6`
- 数据大小：`16 KB`
- 索引大小：`112 KB`
- 自增值：`12`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：推荐奖励表，记录被推荐人订单金额、返利比例、奖励金额与发放状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `referrer_user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `referred_user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 4 | `order_id` | `bigint unsigned` | 是 | `NULL` | UNI | — | — | — | — |
| 5 | `invoice_id` | `bigint unsigned` | 是 | `NULL` | UNI | — | — | — | — |
| 6 | `product_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 7 | `order_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 8 | `reward_rate` | `decimal(5,2)` | 否 | `0.00` | — | — | — | — | — |
| 9 | `reward_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 10 | `available_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 11 | `released_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 12 | `status` | `tinyint` | 否 | `0` | MUL | — | — | — | 0=冻结中 1=已发放 2=已回退 |
| 13 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 14 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 15 | `trace_id` | `varchar(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 16 | `rewarded_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |
| 17 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 18 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `idx_referral_reward_referred_status` | 否 | `BTREE` | `referred_user_id`, `status` | 2 | — |
| `idx_referral_reward_referrer_status` | 否 | `BTREE` | `referrer_user_id`, `status` | 1 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 6 | — |
| `referral_rewards_invoice_id_unique` | 是 | `BTREE` | `invoice_id` | 4 | — |
| `referral_rewards_order_id_unique` | 是 | `BTREE` | `order_id` | 6 | — |
| `referral_rewards_product_id_index` | 否 | `BTREE` | `product_id` | 5 | — |
| `referral_rewards_rewarded_at_index` | 否 | `BTREE` | `rewarded_at` | 6 | — |
| `referral_rewards_status_available_idx` | 否 | `BTREE` | `status`, `available_at` | 6 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_referral_rewards_invoice_id` | `invoice_id` | `invoices` | `id` | `NO ACTION` | `SET NULL` |
| `fk_stage2_referral_rewards_order_id` | `order_id` | `orders` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_stage2_referral_rewards_product_id` | `product_id` | `products` | `id` | `NO ACTION` | `SET NULL` |
| `fk_stage2_referral_rewards_referred_user_id` | `referred_user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_stage2_referral_rewards_referrer_user_id` | `referrer_user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.58 `referral_withdrawals`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`48 KB`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：推荐提现表，记录提现金额、收款方式、处理状态与打款单号

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | — |
| 4 | `method` | `varchar(20)` | 否 | `alipay` | — | — | utf8mb4 | utf8mb4_unicode_ci | balance&#124;alipay |
| 5 | `account_name` | `varchar(80)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `account_no` | `varchar(120)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `status` | `tinyint` | 否 | `0` | MUL | — | — | — | 0=待处理 1=已通过 2=已拒绝 |
| 8 | `payment_no` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 11 | `paid_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 12 | `trace_id` | `varchar(64)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 13 | `processed_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 14 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 15 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `idx_referral_withdraw_status_created` | 否 | `BTREE` | `status`, `created_at` | 0 | — |
| `idx_referral_withdraw_user_status` | 否 | `BTREE` | `user_id`, `status` | 0 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |
| `referral_withdrawals_trace_id_unique` | 是 | `BTREE` | `trace_id` | 0 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_referral_withdrawals_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.59 `refunds`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`3`
- 数据大小：`16 KB`
- 索引大小：`96 KB`
- 自增值：`3`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：旧退款表，仅保留历史数据，退款功能已移除不再产生新行

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `refund_no` | `varchar(40)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 退款单号，对外展示 |
| 3 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 退款所属用户ID |
| 4 | `invoice_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 被退款的账单ID |
| 5 | `refund_invoice_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 退款红字账单ID |
| 6 | `payment_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 关联支付记录ID |
| 7 | `amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 退款金额 |
| 8 | `status` | `tinyint unsigned` | 否 | `1` | — | — | — | — | 退款状态：0=进行中 1=已完成 2=失败 |
| 9 | `refund_method` | `varchar(32)` | 否 | `balance` | — | — | utf8mb4 | utf8mb4_unicode_ci | 退款方式：balance=退回余额（真退款） manual_mark=线下补标记（纯标记不动资金） |
| 10 | `currency` | `varchar(3)` | 否 | `CNY` | — | — | utf8mb4 | utf8mb4_unicode_ci | 币种，固定 CNY |
| 11 | `reason` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 退款原因 |
| 12 | `gateway_refund_no` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 第三方退款单号 |
| 13 | `operator_type` | `varchar(30)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作人类型：admin/member/client/system/legacy |
| 14 | `operator_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 操作人ID，空=系统自动 |
| 15 | `operator_name` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作人名称快照 |
| 16 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |
| 17 | `refunded_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 退款完成时间 |
| 18 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 19 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 3 | — |
| `refunds_invoice_id_status_id_index` | 否 | `BTREE` | `invoice_id`, `status`, `id` | 3 | — |
| `refunds_payment_id_foreign` | 否 | `BTREE` | `payment_id` | 3 | — |
| `refunds_refund_invoice_id_foreign` | 否 | `BTREE` | `refund_invoice_id` | 1 | — |
| `refunds_refund_no_unique` | 是 | `BTREE` | `refund_no` | 3 | — |
| `refunds_trace_id_index` | 否 | `BTREE` | `trace_id` | 3 | — |
| `refunds_user_id_created_at_index` | 否 | `BTREE` | `user_id`, `created_at` | 2 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `refunds_invoice_id_foreign` | `invoice_id` | `invoices` | `id` | `NO ACTION` | `RESTRICT` |
| `refunds_payment_id_foreign` | `payment_id` | `payments` | `id` | `NO ACTION` | `SET NULL` |
| `refunds_refund_invoice_id_foreign` | `refund_invoice_id` | `invoices` | `id` | `NO ACTION` | `SET NULL` |
| `refunds_user_id_foreign` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.60 `roles`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`4`
- 数据大小：`16 KB`
- 索引大小：`16 KB`
- 自增值：`478`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：角色表，记录角色标识、显示名与权限标识数组

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `name` | `varchar(50)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `label` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `permissions` | `json` | 否 | — | — | — | — | — | 权限标识数组 |
| 5 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 6 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 4 | — |
| `roles_name_unique` | 是 | `BTREE` | `name` | 4 | — |

#### 外键约束

无数据库级外键约束。

### 2.61 `schedule_run_logs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`74,032`
- 数据大小：`29.56 MB`
- 索引大小：`10.06 MB`
- 自增值：`249011`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：调度任务执行日志表，记录任务名、执行结果、耗时与失败摘要

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `task_name` | `varchar(100)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 任务名称 |
| 3 | `status` | `varchar(24)` | 否 | `success` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 执行状态: success, failed, skipped |
| 4 | `duration_ms` | `int unsigned` | 否 | `0` | — | — | — | — | 执行耗时(毫秒) |
| 5 | `summary` | `json` | 是 | `NULL` | — | — | — | — | 执行摘要数据 |
| 6 | `error_msg` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 错误信息 |
| 7 | `started_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | 开始时间 |
| 8 | `finished_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 结束时间 |
| 9 | `created_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |
| 10 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 74,032 | — |
| `schedule_run_logs_created_at_index` | 否 | `BTREE` | `created_at` | 28,918 | — |
| `schedule_run_logs_started_at_id_index` | 否 | `BTREE` | `started_at`, `id` | 74,389 | — |
| `schedule_run_logs_status_created_at_index` | 否 | `BTREE` | `status`, `created_at` | 29,369 | — |
| `schedule_run_logs_task_name_created_at_index` | 否 | `BTREE` | `task_name`, `created_at` | 73,774 | — |

#### 外键约束

无数据库级外键约束。

### 2.62 `schedule_task_runs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`95,152`
- 数据大小：`44.58 MB`
- 索引大小：`31.17 MB`
- 自增值：`98734`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：调度任务运行记录表，按节拍记录每次任务尝试的状态、重试与耗时

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `parent_run_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 3 | `schedule_tick_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 4 | `task_key` | `varchar(120)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `task_name` | `varchar(160)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `rule_description` | `varchar(160)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `source` | `varchar(40)` | 否 | `heartbeat` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `queue` | `varchar(80)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `status` | `varchar(24)` | 否 | `queued` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `attempt` | `smallint unsigned` | 否 | `1` | — | — | — | — | — |
| 11 | `duration_ms` | `int unsigned` | 是 | `NULL` | — | — | — | — | — |
| 12 | `summary` | `json` | 是 | `NULL` | — | — | — | — | — |
| 13 | `error_msg` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 14 | `queued_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 15 | `started_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 16 | `finished_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 17 | `manual_retry_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 18 | `manual_retry_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 19 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 20 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 95,152 | — |
| `schedule_task_runs_active_lookup_index` | 否 | `BTREE` | `task_key`, `status`, `queued_at` | 95,248 | — |
| `schedule_task_runs_parent_created_at_index` | 否 | `BTREE` | `parent_run_id`, `created_at` | 11,884 | — |
| `schedule_task_runs_source_created_at_index` | 否 | `BTREE` | `source`, `created_at` | 12,171 | — |
| `schedule_task_runs_status_created_at_index` | 否 | `BTREE` | `status`, `created_at` | 11,583 | — |
| `schedule_task_runs_task_key_created_at_index` | 否 | `BTREE` | `task_key`, `created_at` | 88,863 | — |
| `schedule_task_runs_tick_task_source_unique` | 是 | `BTREE` | `schedule_tick_id`, `task_key`, `source` | 78,624 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `schedule_task_runs_schedule_tick_id_foreign` | `schedule_tick_id` | `schedule_ticks` | `id` | `NO ACTION` | `SET NULL` |

### 2.63 `schedule_ticks`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`9,016`
- 数据大小：`528 KB`
- 索引大小：`880 KB`
- 自增值：`9007`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：调度节拍表，记录每个调度槽位的触发时间与全局当日序号

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `slot_started_at` | `timestamp` | 否 | — | UNI | — | — | — | — |
| 3 | `global_number` | `bigint unsigned` | 否 | — | UNI | — | — | — | — |
| 4 | `daily_index` | `tinyint unsigned` | 否 | — | MUL | — | — | — | — |
| 5 | `triggered_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |
| 6 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 7 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 9,016 | — |
| `schedule_ticks_daily_index_index` | 否 | `BTREE` | `daily_index` | 96 | — |
| `schedule_ticks_global_number_unique` | 是 | `BTREE` | `global_number` | 8,991 | — |
| `schedule_ticks_slot_started_at_unique` | 是 | `BTREE` | `slot_started_at` | 8,991 | — |
| `schedule_ticks_triggered_at_index` | 否 | `BTREE` | `triggered_at` | 8,991 | — |

#### 外键约束

无数据库级外键约束。

### 2.64 `second_product_groups`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`15`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`44`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：二级商品分组表，挂在一级分组下并承载展示配置

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `first_product_group_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `name` | `varchar(100)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `slug` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `description` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `banner_image` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `sort_order` | `int` | 否 | `0` | — | — | — | — | — |
| 8 | `is_visible` | `tinyint unsigned` | 否 | `1` | — | — | — | — | — |
| 9 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 10 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `idx_second_first_visible_sort` | 否 | `BTREE` | `first_product_group_id`, `is_visible`, `sort_order` | 14 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 15 | — |
| `uq_second_first_slug` | 是 | `BTREE` | `first_product_group_id`, `slug` | 15 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_second_first_group` | `first_product_group_id` | `first_product_groups` | `id` | `NO ACTION` | `RESTRICT` |

### 2.65 `service_connection_snapshots`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`194`
- 数据大小：`352 KB`
- 索引大小：`64 KB`
- 自增值：`244`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：服务连接快照表，记录上游连接地址、端口与加密凭据及探测时间

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `service_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `service_upstream_binding_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 4 | `plugin_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 5 | `provider_key` | `varchar(120)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `connection_type` | `varchar(60)` | 否 | `default` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `hostname` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `ip_address` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `port` | `int unsigned` | 是 | `NULL` | — | — | — | — | — |
| 10 | `connection_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 11 | `secret_json` | `longtext` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 12 | `has_secret_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 13 | `checked_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 14 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 15 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 194 | — |
| `service_connection_plugin_checked_idx` | 否 | `BTREE` | `plugin_id`, `checked_at` | 150 | — |
| `service_connection_provider_type_idx` | 否 | `BTREE` | `provider_key`, `connection_type` | 1 | — |
| `service_connection_service_type_unique` | 是 | `BTREE` | `service_id`, `connection_type` | 194 | — |
| `service_connection_snapshots_service_upstream_binding_id_foreign` | 否 | `BTREE` | `service_upstream_binding_id` | 194 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `service_connection_snapshots_plugin_id_foreign` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `SET NULL` |
| `service_connection_snapshots_service_id_foreign` | `service_id` | `services` | `id` | `NO ACTION` | `CASCADE` |
| `service_connection_snapshots_service_upstream_binding_id_foreign` | `service_upstream_binding_id` | `service_upstream_bindings` | `id` | `NO ACTION` | `SET NULL` |

### 2.66 `service_provision_attempts`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`5,395`
- 数据大小：`4.52 MB`
- 索引大小：`1.19 MB`
- 自增值：`5323`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：开通尝试表，记录每次开通续费调用的请求应答摘要与错误

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `service_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 3 | `service_upstream_binding_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 4 | `plugin_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 5 | `provider_key` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `action` | `varchar(80)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `attempt_status` | `varchar(30)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `request_meta_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 10 | `response_meta_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 11 | `error_code` | `varchar(80)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 12 | `error_message` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 13 | `attempted_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 14 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 15 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 5,395 | — |
| `service_attempt_plugin_status_idx` | 否 | `BTREE` | `plugin_id`, `attempt_status`, `attempted_at` | 2,608 | — |
| `service_attempt_service_action_idx` | 否 | `BTREE` | `service_id`, `action`, `attempted_at` | 5,123 | — |
| `service_attempt_trace_idx` | 否 | `BTREE` | `trace_id` | 167 | — |
| `service_provision_attempts_service_upstream_binding_id_foreign` | 否 | `BTREE` | `service_upstream_binding_id` | 195 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `service_provision_attempts_plugin_id_foreign` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `SET NULL` |
| `service_provision_attempts_service_id_foreign` | `service_id` | `services` | `id` | `NO ACTION` | `SET NULL` |
| `service_provision_attempts_service_upstream_binding_id_foreign` | `service_upstream_binding_id` | `service_upstream_bindings` | `id` | `NO ACTION` | `SET NULL` |

### 2.67 `service_runtime_snapshots`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`188`
- 数据大小：`416 KB`
- 索引大小：`64 KB`
- 自增值：`244`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：服务运行态快照表，保存上游同步的状态、资源与指标数据

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `service_id` | `bigint unsigned` | 否 | — | UNI | — | — | — | — |
| 3 | `service_upstream_binding_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 4 | `plugin_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 5 | `provider_key` | `varchar(120)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `status_key` | `varchar(60)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `status_text` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `resource_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 9 | `metrics_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 10 | `snapshot_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 11 | `synced_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 12 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 13 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 188 | — |
| `service_runtime_plugin_synced_idx` | 否 | `BTREE` | `plugin_id`, `synced_at` | 146 | — |
| `service_runtime_provider_status_idx` | 否 | `BTREE` | `provider_key`, `status_key` | 5 | — |
| `service_runtime_service_unique` | 是 | `BTREE` | `service_id` | 188 | — |
| `service_runtime_snapshots_service_upstream_binding_id_foreign` | 否 | `BTREE` | `service_upstream_binding_id` | 188 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `service_runtime_snapshots_plugin_id_foreign` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `SET NULL` |
| `service_runtime_snapshots_service_id_foreign` | `service_id` | `services` | `id` | `NO ACTION` | `CASCADE` |
| `service_runtime_snapshots_service_upstream_binding_id_foreign` | `service_upstream_binding_id` | `service_upstream_bindings` | `id` | `NO ACTION` | `SET NULL` |

### 2.68 `service_tracking_records`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：—
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：服务跟踪留言表，记录用户针对服务提交的跟踪内容与图片

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `service_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 4 | `tracked_at` | `datetime` | 否 | — | — | — | — | — | 跟踪时间 |
| 5 | `content` | `text` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 留言内容 |
| 6 | `images` | `json` | 是 | `NULL` | — | — | — | — | 截图列表 |
| 7 | `operator_id` | `bigint unsigned` | 否 | `0` | — | — | — | — | 操作管理员ID |
| 8 | `operator_name` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作管理员名称快照 |
| 9 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 10 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |
| `service_tracking_service_time_idx` | 否 | `BTREE` | `service_id`, `tracked_at` | 0 | — |
| `service_tracking_user_idx` | 否 | `BTREE` | `user_id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.69 `service_upstream_bindings`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`208`
- 数据大小：`192 KB`
- 索引大小：`80 KB`
- 自增值：`330`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：服务上游绑定表，记录服务实例对应的上游服务账号与同步状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `service_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `product_upstream_binding_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 4 | `supplier_plugin_binding_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 5 | `plugin_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 6 | `provider_key` | `varchar(120)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `upstream_service_id` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `upstream_account_id` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `runtime_snapshot_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 10 | `connection_snapshot_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 11 | `status_snapshot` | `varchar(60)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 12 | `last_synced_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 13 | `last_sync_error` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 14 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 15 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 208 | — |
| `service_upstream_bindings_product_upstream_binding_id_foreign` | 否 | `BTREE` | `product_upstream_binding_id` | 53 | — |
| `service_upstream_bindings_supplier_plugin_binding_id_foreign` | 否 | `BTREE` | `supplier_plugin_binding_id` | 2 | — |
| `service_upstream_plugin_sync_idx` | 否 | `BTREE` | `plugin_id`, `last_synced_at` | 145 | — |
| `service_upstream_provider_status_idx` | 否 | `BTREE` | `provider_key`, `status_snapshot` | 5 | — |
| `service_upstream_unique` | 是 | `BTREE` | `service_id`, `plugin_id`, `upstream_service_id` | 208 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `service_upstream_bindings_plugin_id_foreign` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `RESTRICT` |
| `service_upstream_bindings_product_upstream_binding_id_foreign` | `product_upstream_binding_id` | `product_upstream_bindings` | `id` | `NO ACTION` | `SET NULL` |
| `service_upstream_bindings_service_id_foreign` | `service_id` | `services` | `id` | `NO ACTION` | `RESTRICT` |
| `service_upstream_bindings_supplier_plugin_binding_id_foreign` | `supplier_plugin_binding_id` | `supplier_plugin_bindings` | `id` | `NO ACTION` | `SET NULL` |

### 2.70 `services`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`161`
- 数据大小：`1.52 MB`
- 索引大小：`128 KB`
- 自增值：`350`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：服务实例表，记录用户已购买产品的生命周期、计费、上游和续费状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | 服务实例自增主键 |
| 2 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 所属用户ID |
| 3 | `product_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | 关联商品ID |
| 4 | `order_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 内部订单/开通投影ID，仅用于流程追踪 |
| 5 | `invoice_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 最近一次关联账单ID |
| 6 | `name` | `varchar(200)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 服务自定义名称 |
| 7 | `domain` | `varchar(200)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 服务域名或主机名 |
| 8 | `billing_cycle` | `varchar(20)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 计费周期 |
| 9 | `amount` | `decimal(12,2)` | 否 | — | — | — | — | — | 服务续费/购买金额 |
| 10 | `locked_pricing` | `json` | 是 | `NULL` | — | — | — | — | 锁定续费定价 JSON，null 表示跟随商品定价 |
| 11 | `renew_coupon_id` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | 续费持续优惠绑定的优惠券 ID（溯源，运行时以快照为准） |
| 12 | `renew_coupon_snapshot` | `json` | 是 | `NULL` | — | — | — | — | 续费持续优惠参数快照（discount_type/discount_value/max_discount_amount 等，续费自动折算依据） |
| 13 | `status` | `tinyint` | 否 | `0` | MUL | — | — | — | 服务状态：0开通中 1运行中 2已暂停 3已删除 4异常 |
| 14 | `provision_data` | `json` | 是 | `NULL` | — | — | — | — | 开通和上游实例数据 JSON |
| 15 | `expires_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | 服务到期时间 |
| 16 | `auto_renew` | `tinyint` | 否 | `0` | — | — | — | — | 是否自动续费：0关闭 1开启 |
| 17 | `suspended_reason` | `varchar(200)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 暂停原因 |
| 18 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 创建时间 |
| 19 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 更新时间 |
| 20 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 服务备注 |
| 21 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作人快照 |
| 22 | `trace_id` | `varchar(64)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 链路追踪号 |
| 23 | `provision_lease_token` | `char(48)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 开通租约令牌，非空表示有执行者正在开通 |
| 24 | `provision_lease_until` | `datetime` | 是 | `NULL` | MUL | — | — | — | 开通租约到期时间，过期允许他人抢占 |
| 25 | `provision_attempts` | `int unsigned` | 否 | `0` | — | — | — | — | 累计开通尝试次数 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 161 | — |
| `services_expires_at_index` | 否 | `BTREE` | `expires_at` | 161 | — |
| `services_invoice_id_idx` | 否 | `BTREE` | `invoice_id` | 76 | — |
| `services_order_id_idx` | 否 | `BTREE` | `order_id` | 161 | — |
| `services_product_id_idx` | 否 | `BTREE` | `product_id` | 54 | — |
| `services_provision_lease_until_index` | 否 | `BTREE` | `provision_lease_until` | 1 | — |
| `services_status_expires_at_id_idx` | 否 | `BTREE` | `status`, `expires_at`, `id` | 161 | — |
| `services_trace_id_idx` | 否 | `BTREE` | `trace_id` | 114 | — |
| `services_user_status_id_idx` | 否 | `BTREE` | `user_id`, `status`, `id` | 161 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_services_invoice_id` | `invoice_id` | `invoices` | `id` | `NO ACTION` | `SET NULL` |
| `fk_services_product_id` | `product_id` | `products` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_services_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_stage2_services_order_id` | `order_id` | `orders` | `id` | `NO ACTION` | `SET NULL` |

### 2.71 `sessions`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：—
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：框架会话表，保存会话载荷、客户端信息与最后活跃时间

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `varchar(255)` | 否 | — | PRI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 2 | `user_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 3 | `ip_address` | `varchar(45)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `user_agent` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `payload` | `longtext` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `last_activity` | `int` | 否 | — | MUL | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |
| `sessions_last_activity_index` | 否 | `BTREE` | `last_activity` | 0 | — |
| `sessions_user_id_index` | 否 | `BTREE` | `user_id` | 0 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_sessions_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `CASCADE` |

### 2.72 `settings`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`115`
- 数据大小：`144 KB`
- 索引大小：`16 KB`
- 自增值：`804`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：系统设置表，按分组键与条目键保存键值配置

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `group_key` | `varchar(50)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `item_key` | `varchar(100)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `item_value` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 115 | — |
| `settings_group_key_unique` | 是 | `BTREE` | `group_key`, `item_key` | 115 | — |

#### 外键约束

无数据库级外键约束。

### 2.73 `sms_provider_templates`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：—
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：短信运营商模板映射（插件同步运营商 API 审核状态，发送解析唯一有效态为 approved）

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `plugin_key` | `varchar(64)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 短信插件 key（字符串关联，不外键；卸载插件不硬删本表） |
| 3 | `title` | `varchar(120)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 模板名称（提交运营商审核时作为 TemplateName，对齐 ZJMF message_template.title） |
| 4 | `business_code` | `varchar(32)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 映射的 notification_templates.code（channel=sms）；NULL=未挂接业务码 |
| 5 | `provider_template_id` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 运营商原生模板 ID（SMS_xxx / 数字码）；draft 未提交模板为 NULL |
| 6 | `provider_sign` | `varchar(64)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 短信签名（部分运营商模板与签名成对提交） |
| 7 | `content` | `longtext` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 运营商侧模板原文（${var} 占位语法，与本地 {{var}} 语法不同） |
| 8 | `remark` | `varchar(500)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 申请备注（提交运营商审核时作为 Remark，对齐 ZJMF message_template.remark） |
| 9 | `variables_json` | `json` | 是 | `NULL` | — | — | — | — | 运营商模板变量名列表（发送解析时与渲染参数求交集校验） |
| 10 | `status` | `varchar(20)` | 否 | `draft` | — | — | utf8mb4 | utf8mb4_unicode_ci | 生命周期：draft=未提交（本地草稿） pending=审核中 approved=通过（解析唯一有效态） rejected=驳回 deleted=运营商侧已删除 |
| 11 | `reject_reason` | `varchar(500)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 审核驳回原因（管理端回显） |
| 12 | `raw_json` | `json` | 是 | `NULL` | — | — | — | — | 运营商原始响应摘要（脱敏后，仅审计用） |
| 13 | `synced_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 最近一次同步时间（approved 多条时按此取最新，并列按 id） |
| 14 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 15 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |
| `sms_provider_templates_lookup_index` | 否 | `BTREE` | `plugin_key`, `business_code`, `status` | 0 | — |
| `sms_provider_templates_plugin_template_unique` | 是 | `BTREE` | `plugin_key`, `provider_template_id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.74 `supplier_plugin_bindings`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`2`
- 数据大小：`16 KB`
- 索引大小：`48 KB`
- 自增值：`3`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：供应商插件绑定表，记录供应商接入的插件、环境、优先级与连通状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `supplier_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `plugin_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 4 | `provider_key` | `varchar(120)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `environment` | `varchar(30)` | 否 | `production` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `status` | `tinyint unsigned` | 否 | `1` | — | — | — | — | — |
| 7 | `priority` | `int` | 否 | `0` | — | — | — | — | — |
| 8 | `base_url` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `account_name` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `config_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 11 | `secret_json` | `longtext` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 12 | `has_secret_json` | `json` | 是 | `NULL` | — | — | — | — | — |
| 13 | `last_checked_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 14 | `last_check_status` | `varchar(30)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 15 | `last_check_error` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 16 | `created_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 17 | `updated_by` | `bigint unsigned` | 是 | `NULL` | — | — | — | — | — |
| 18 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 19 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 2 | — |
| `supplier_plugin_plugin_status_idx` | 否 | `BTREE` | `plugin_id`, `status` | 1 | — |
| `supplier_plugin_provider_status_idx` | 否 | `BTREE` | `provider_key`, `status` | 1 | — |
| `supplier_plugin_unique` | 是 | `BTREE` | `supplier_id`, `plugin_id`, `environment` | 2 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `supplier_plugin_bindings_plugin_id_foreign` | `plugin_id` | `integration_plugins` | `id` | `NO ACTION` | `RESTRICT` |
| `supplier_plugin_bindings_supplier_id_foreign` | `supplier_id` | `suppliers` | `id` | `NO ACTION` | `RESTRICT` |

### 2.75 `suppliers`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`2`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`3`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：供应商表，记录供应商编码、联系方式、启用状态与备注

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `name` | `varchar(120)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `code` | `varchar(50)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `contact_name` | `varchar(60)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `contact_phone` | `varchar(30)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `contact_email` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `website` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `status` | `tinyint` | 否 | `1` | MUL | — | — | — | 0=停用 1=启用 |
| 9 | `sort_order` | `int` | 否 | `0` | — | — | — | — | — |
| 10 | `notes` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 11 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 12 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 2 | — |
| `suppliers_code_unique` | 是 | `BTREE` | `code` | 2 | — |
| `suppliers_status_sort_order_index` | 否 | `BTREE` | `status`, `sort_order` | 1 | — |

#### 外键约束

无数据库级外键约束。

### 2.76 `system_installation`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`0 B`
- 自增值：—
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：系统安装版本账本：单行记录数据库最后成功升级到的代码版本

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `tinyint unsigned` | 否 | — | PRI | — | — | — | 安装记录主键，固定为 1 |
| 2 | `app_version` | `varchar(32)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 数据库最后成功升级到的代码版本号 |
| 3 | `adopted_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 首次采纳既有数据库的时间，空表示未采纳 |
| 4 | `upgraded_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 最近一次成功升级完成时间 |
| 5 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 6 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.77 `system_upgrade_runs`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`8`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`8`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：系统升级执行记录：每次升级的来源/目标版本、状态、备份与失败摘要

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | 升级记录自增主键 |
| 2 | `from_version` | `varchar(32)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 来源代码版本；首次采纳时为 NULL |
| 3 | `to_version` | `varchar(32)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 目标代码版本 |
| 4 | `status` | `varchar(20)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 执行状态：running 执行中 / completed 成功 / failed 未产生结构变更的失败 / recovery_required 已产生结构变更需人工恢复 / adopted 首次采纳 |
| 5 | `pending_count` | `int unsigned` | 否 | `0` | — | — | — | — | 本次开始时的待执行迁移数量 |
| 6 | `executed_count` | `int unsigned` | 否 | `0` | — | — | — | — | 本次实际执行的迁移数量 |
| 7 | `executed_migrations` | `json` | 是 | `NULL` | — | — | — | — | 本次执行的迁移文件名列表（JSON 数组） |
| 8 | `backup_path` | `varchar(500)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 升级前数据库备份文件的绝对路径；跳过备份时为 NULL |
| 9 | `error_message` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 失败摘要，最多保留 1000 字符 |
| 10 | `operator` | `varchar(50)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 执行升级的命令行操作者，系统任务记为 SYSTEM |
| 11 | `client_ip` | `varchar(45)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 操作者 IP；命令行执行时为 NULL |
| 12 | `started_at` | `timestamp` | 否 | — | MUL | — | — | — | 升级开始时间 |
| 13 | `finished_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 升级结束时间，未结束时为 NULL |
| 14 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 15 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 8 | — |
| `system_upgrade_runs_started_idx` | 否 | `BTREE` | `started_at` | 8 | — |
| `system_upgrade_runs_status_started_idx` | 否 | `BTREE` | `status`, `started_at` | 8 | — |

#### 外键约束

无数据库级外键约束。

### 2.78 `third_product_groups`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`25`
- 数据大小：`16 KB`
- 索引大小：`32 KB`
- 自增值：`50`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：三级商品分组表，挂在二级分组下并承载展示配置

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `second_product_group_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `name` | `varchar(100)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `slug` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `description` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `sort_order` | `int` | 否 | `0` | — | — | — | — | — |
| 7 | `is_visible` | `tinyint unsigned` | 否 | `1` | — | — | — | — | — |
| 8 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 9 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `idx_third_second_visible_sort` | 否 | `BTREE` | `second_product_group_id`, `is_visible`, `sort_order` | 18 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 25 | — |
| `uq_third_second_slug` | 是 | `BTREE` | `second_product_group_id`, `slug` | 25 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_third_second_group` | `second_product_group_id` | `second_product_groups` | `id` | `NO ACTION` | `RESTRICT` |

### 2.79 `ticket_replies`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`211`
- 数据大小：`48 KB`
- 索引大小：`32 KB`
- 自增值：`212`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：工单回复表，记录回复正文、附件、员工标记与引用回复

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `ticket_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `user_id` | `bigint unsigned` | 否 | — | — | — | — | — | — |
| 4 | `content` | `text` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `is_staff` | `tinyint` | 否 | `0` | — | — | — | — | — |
| 6 | `attachments` | `json` | 是 | `NULL` | — | — | — | — | — |
| 7 | `quote_reply_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 8 | `recalled_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 9 | `created_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` | — | DEFAULT_GENERATED | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `idx_stage2_ticket_replies_quote_reply_id` | 否 | `BTREE` | `quote_reply_id` | 2 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 211 | — |
| `ticket_replies_ticket_created_id_idx` | 否 | `BTREE` | `ticket_id`, `created_at`, `id` | 211 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_ticket_replies_quote_reply_id` | `quote_reply_id` | `ticket_replies` | `id` | `NO ACTION` | `SET NULL` |
| `fk_ticket_replies_ticket_id` | `ticket_id` | `tickets` | `id` | `NO ACTION` | `CASCADE` |

### 2.80 `tickets`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`82`
- 数据大小：`16 KB`
- 索引大小：`80 KB`
- 自增值：`89`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：工单表，记录会员提交的问题、优先级、处理状态与关闭原因

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `department` | `varchar(30)` | 否 | `support` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `subject` | `varchar(200)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `priority` | `tinyint` | 否 | `1` | — | — | — | — | 1=低 2=中 3=高 4=紧急 |
| 6 | `status` | `tinyint` | 否 | `0` | MUL | — | — | — | 0=开启 1=客户回复 2=员工回复 3=已关闭 |
| 7 | `service_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 8 | `assignee_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | — |
| 9 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 10 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 11 | `close_reason` | `varchar(20)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | admin, client, auto |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `idx_stage2_tickets_assignee_id` | 否 | `BTREE` | `assignee_id` | 2 | — |
| `PRIMARY` | 是 | `BTREE` | `id` | 82 | — |
| `tickets_service_id_idx` | 否 | `BTREE` | `service_id` | 39 | — |
| `tickets_status_updated_at_idx` | 否 | `BTREE` | `status`, `updated_at` | 80 | — |
| `tickets_user_status_updated_at_idx` | 否 | `BTREE` | `user_id`, `status`, `updated_at` | 82 | — |
| `tickets_user_updated_at_idx` | 否 | `BTREE` | `user_id`, `updated_at`, `id` | 82 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_tickets_assignee_id` | `assignee_id` | `admin_users` | `id` | `NO ACTION` | `SET NULL` |
| `fk_stage2_tickets_service_id` | `service_id` | `services` | `id` | `NO ACTION` | `SET NULL` |
| `fk_tickets_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.81 `user_accounts`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`420`
- 数据大小：`64 KB`
- 索引大小：`0 B`
- 自增值：—
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：用户账户余额源表，集中承载现金余额、授信和推荐奖励余额

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `user_id` | `bigint unsigned` | 否 | — | PRI | — | — | — | 用户ID，同时作为账户主键 |
| 2 | `cash_balance` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 现金余额 |
| 3 | `credit_limit` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 授信额度 |
| 4 | `referral_frozen_balance` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 冻结中的推荐奖励余额 |
| 5 | `referral_available_balance` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 可用推荐奖励余额 |
| 6 | `referral_pending_withdrawal_balance` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 提现审核中的推荐奖励余额 |
| 7 | `referral_withdrawn_balance` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 已提现推荐奖励累计金额 |
| 8 | `version` | `int unsigned` | 否 | `0` | — | — | — | — | 乐观锁版本号 |
| 9 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 创建时间 |
| 10 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 更新时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `user_id` | 420 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_user_accounts_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

### 2.82 `user_coupons`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`36`
- 数据大小：`16 KB`
- 索引大小：`64 KB`
- 自增值：`101`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：用户持券表，记录领取方式、持有使用回收状态与占用时间

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `uid` | `varchar(32)` | 是 | `NULL` | UNI | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 3 | `coupon_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 4 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 5 | `receive_type` | `varchar(20)` | 否 | `claim` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `status` | `tinyint` | 否 | `1` | — | — | — | — | 优惠券状态：1=持有 2=已使用 3=已回收 |
| 7 | `claimed_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 8 | `used_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 9 | `revoked_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 10 | `reserved_until` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 11 | `granted_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 12 | `last_used_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 13 | `remark` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 14 | `operator` | `varchar(100)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 15 | `trace_id` | `varchar(64)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 16 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 17 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 36 | — |
| `user_coupons_coupon_status_idx` | 否 | `BTREE` | `coupon_id`, `status` | 4 | — |
| `user_coupons_coupon_user_unique` | 是 | `BTREE` | `coupon_id`, `user_id` | 36 | — |
| `user_coupons_uid_unique` | 是 | `BTREE` | `uid` | 36 | — |
| `user_coupons_user_status_idx` | 否 | `BTREE` | `user_id`, `status` | 34 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_user_coupons_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |
| `fk_user_coupons_coupon_id` | `coupon_id` | `coupons` | `id` | `NO ACTION` | `RESTRICT` |

### 2.83 `user_notifications`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`493`
- 数据大小：`160 KB`
- 索引大小：`80 KB`
- 自增值：`509`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：站内通知表，记录通知类型、标题正文、跳转链接与已读时间

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `type` | `varchar(50)` | 否 | — | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 消息类型：order_paid/service_renew_reminder/service_expire_reminder 等 |
| 4 | `title` | `varchar(191)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `content` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 6 | `link` | `varchar(255)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 点击跳转的前端路由 |
| 7 | `data` | `json` | 是 | `NULL` | — | — | — | — | 附加业务数据 |
| 8 | `read_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 9 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 10 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 493 | — |
| `user_notifications_type_index` | 否 | `BTREE` | `type` | 9 | — |
| `user_notifications_user_id_created_at_index` | 否 | `BTREE` | `user_id`, `created_at` | 492 | — |
| `user_notifications_user_id_read_at_index` | 否 | `BTREE` | `user_id`, `read_at` | 120 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_user_notifications_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `CASCADE` |

### 2.84 `users`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`507`
- 数据大小：`160 KB`
- 索引大小：`192 KB`
- 自增值：`508`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：会员账号表，记录登录凭据、资料、等级、推荐关系与实名认证状态

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `email` | `varchar(100)` | 是 | `NULL` | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 登录邮箱，全局唯一（软删时释放） |
| 3 | `password` | `varchar(255)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 登录密码哈希（框架 hashed 转换） |
| 4 | `nickname` | `varchar(50)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 显示昵称，空串=未设置 |
| 5 | `phone` | `varchar(20)` | 是 | `NULL` | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 手机号，全局唯一（软删时释放） |
| 6 | `company` | `varchar(100)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 公司名称，空串=未填写 |
| 7 | `qq` | `varchar(30)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | QQ 号，空串=未填写 |
| 8 | `alipay_real_name` | `varchar(80)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 支付宝收款人姓名（退款打款用） |
| 9 | `alipay_account` | `varchar(254)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 支付宝收款账号（退款打款用） |
| 10 | `referral_code` | `varchar(24)` | 是 | `NULL` | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 推荐码，全局唯一（软删时释放） |
| 11 | `referrer_user_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 推荐人用户ID，空=无推荐关系 |
| 12 | `member_level_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 会员等级ID，空=未分级 |
| 13 | `promotion_ambassador_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 推广大使档位，空=未指派 |
| 14 | `total_sales_amount` | `decimal(12,2)` | 否 | `0.00` | — | — | — | — | 该用户累计消费金额，推荐业绩口径 |
| 15 | `referred_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 推荐关系建立时间 |
| 16 | `status` | `tinyint` | 否 | `1` | MUL | — | — | — | 0=禁用 1=正常 |
| 17 | `login_email_alert` | `tinyint` | 否 | `1` | — | — | — | — | 登录邮件提醒 0关闭 1开启 |
| 18 | `login_notify` | `tinyint(1)` | 否 | `1` | — | — | — | — | 账号登录提醒 0关闭 1开启 |
| 19 | `login_location_alert` | `tinyint(1)` | 否 | `1` | — | — | — | — | 异地登录提醒 0关闭 1开启 |
| 20 | `password_change_alert` | `tinyint(1)` | 否 | `1` | — | — | — | — | 密码变更提醒 0关闭 1开启 |
| 21 | `phone_change_alert` | `tinyint(1)` | 否 | `1` | — | — | — | — | 手机号变更提醒 0关闭 1开启 |
| 22 | `email_change_alert` | `tinyint(1)` | 否 | `1` | — | — | — | — | 邮箱变更提醒 0关闭 1开启 |
| 23 | `marketing_alert` | `tinyint(1)` | 否 | `0` | — | — | — | — | 营销提醒接收 0关闭 1开启 |
| 24 | `is_verified` | `tinyint` | 否 | `0` | MUL | — | — | — | 0=未认证 1=已认证 |
| 25 | `real_name` | `varchar(50)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 真实姓名 |
| 26 | `id_card` | `varchar(512)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 证件号，加密存储（LegacyEncrypted） |
| 27 | `verification_status` | `tinyint` | 否 | `0` | MUL | — | — | — | 0=未认证 1=认证中 2=已认证 3=认证失败 |
| 28 | `verification_message` | `varchar(255)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | 实名认证状态描述 |
| 29 | `verification_certify_id` | `varchar(100)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | 实名认证平台 certify_id |
| 30 | `verified_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 实名认证通过时间 |
| 31 | `last_login_ip` | `varchar(45)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 最近一次登录IP |
| 32 | `last_login_at` | `timestamp` | 是 | `NULL` | — | — | — | — | 最近一次登录时间 |
| 33 | `admin_note` | `text` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 管理员备注，空串归一为 null |
| 34 | `created_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |
| 35 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 36 | `deleted_at` | `timestamp` | 是 | `NULL` | MUL | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 507 | — |
| `users_created_at_idx` | 否 | `BTREE` | `created_at` | 507 | — |
| `users_deleted_at_idx` | 否 | `BTREE` | `deleted_at` | 1 | — |
| `users_email_unique` | 是 | `BTREE` | `email` | 347 | — |
| `users_member_level_id_index` | 否 | `BTREE` | `member_level_id` | 2 | — |
| `users_phone_unique` | 是 | `BTREE` | `phone` | 190 | — |
| `users_promotion_ambassador_id_index` | 否 | `BTREE` | `promotion_ambassador_id` | 1 | — |
| `users_referral_code_unique` | 是 | `BTREE` | `referral_code` | 178 | — |
| `users_referrer_referred_idx` | 否 | `BTREE` | `referrer_user_id`, `referred_at` | 7 | — |
| `users_status_id_idx` | 否 | `BTREE` | `status`, `id` | 507 | — |
| `users_verification_certify_id_idx` | 否 | `BTREE` | `verification_certify_id` | 126 | — |
| `users_verification_mix_idx` | 否 | `BTREE` | `is_verified`, `verification_status`, `id` | 507 | — |
| `users_verification_status_id_idx` | 否 | `BTREE` | `verification_status`, `id` | 507 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_users_member_level_id` | `member_level_id` | `member_levels` | `id` | `NO ACTION` | `SET NULL` |
| `fk_stage2_users_referrer_user_id` | `referrer_user_id` | `users` | `id` | `NO ACTION` | `SET NULL` |
| `users_promotion_ambassador_id_foreign` | `promotion_ambassador_id` | `promotion_ambassadors` | `id` | `NO ACTION` | `SET NULL` |

### 2.85 `verification_challenges`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`0`
- 数据大小：`16 KB`
- 索引大小：`0 B`
- 自增值：`1`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：验证码挑战表，仅保存 HMAC 摘要不存明文

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `challenge_id` | `char(32)` | 否 | — | UNI | — | utf8mb4 | utf8mb4_unicode_ci | 挑战标识，随机 16 字节十六进制 |
| 3 | `purpose` | `varchar(32)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 用途：register、login、password_reset |
| 4 | `method` | `varchar(16)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 发送方式：sms、email |
| 5 | `target_hash` | `varchar(64)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 目标账号的 HMAC-SHA256 摘要，不存明文 |
| 6 | `binding_hash` | `varchar(64)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | 会话绑定值的 HMAC-SHA256 摘要 |
| 7 | `code_hash` | `varchar(64)` | 否 | — | — | — | utf8mb4 | utf8mb4_unicode_ci | challenge_id 与验证码拼接后的 HMAC-SHA256 摘要 |
| 8 | `status` | `varchar(16)` | 否 | `pending` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | pending、active、consumed、expired |
| 9 | `attempts` | `int unsigned` | 否 | `0` | — | — | — | — | 已校验次数 |
| 10 | `expires_at` | `datetime` | 否 | — | — | — | — | — | 过期时间 |
| 11 | `created_at` | `datetime` | 否 | — | — | — | — | — | 创建时间 |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 0 | — |
| `verification_challenges_challenge_unique` | 是 | `BTREE` | `challenge_id` | 0 | — |
| `verification_challenges_status_expiry_idx` | 否 | `BTREE` | `status`, `expires_at` | 0 | — |

#### 外键约束

无数据库级外键约束。

### 2.86 `verification_histories`

- 类型：`BASE TABLE`
- 引擎：`InnoDB`
- 估算行数：`123`
- 数据大小：`80 KB`
- 索引大小：`64 KB`
- 自增值：`128`
- 排序规则：`utf8mb4_unicode_ci`
- 表注释：实名认证历史表，记录每次提交的证件信息、认证状态与完成时间

#### 字段

| 序号 | 字段 | 类型 | 可空 | 默认值 | 键 | 额外 | 字符集 | 排序规则 | 注释 |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `id` | `bigint unsigned` | 否 | — | PRI | auto_increment | — | — | — |
| 2 | `user_id` | `bigint unsigned` | 否 | — | MUL | — | — | — | — |
| 3 | `real_name` | `varchar(50)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 4 | `id_card` | `varchar(512)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 5 | `verification_status` | `tinyint` | 否 | `1` | — | — | — | — | 1=认证中 2=已认证 3=认证失败 |
| 6 | `verification_message` | `varchar(255)` | 否 | 空字符串 | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 7 | `verification_certify_id` | `varchar(100)` | 是 | `NULL` | MUL | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 8 | `verification_biz_code` | `varchar(30)` | 否 | `FACE` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 9 | `verification_type` | `varchar(20)` | 否 | `personal` | — | — | utf8mb4 | utf8mb4_unicode_ci | — |
| 10 | `plugin_id` | `bigint unsigned` | 是 | `NULL` | MUL | — | — | — | 本次认证使用的实名认证插件ID快照 |
| 11 | `plugin_key` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 本次认证使用的实名认证插件标识快照 |
| 12 | `plugin_name` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 本次认证使用的实名认证插件名称快照，如 百度智能云人脸实名认证 |
| 13 | `driver_key` | `varchar(120)` | 是 | `NULL` | — | — | utf8mb4 | utf8mb4_unicode_ci | 本次认证使用的驱动标识快照，渠道排障用 |
| 14 | `submitted_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` | — | DEFAULT_GENERATED | — | — | — |
| 15 | `completed_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 16 | `created_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |
| 17 | `updated_at` | `timestamp` | 是 | `NULL` | — | — | — | — | — |

#### 索引

| 索引名 | 唯一 | 类型 | 字段 | 基数 | 注释 |
| --- | --- | --- | --- | ---: | --- |
| `PRIMARY` | 是 | `BTREE` | `id` | 123 | — |
| `verification_histories_plugin_submitted_idx` | 否 | `BTREE` | `plugin_id`, `submitted_at` | 31 | — |
| `verification_histories_user_id_id_idx` | 否 | `BTREE` | `user_id`, `id` | 123 | — |
| `verification_histories_user_id_submitted_at_index` | 否 | `BTREE` | `user_id`, `submitted_at` | 90 | — |
| `verification_histories_verification_certify_id_index` | 否 | `BTREE` | `verification_certify_id` | 118 | — |

#### 外键约束

| 约束名 | 字段 | 引用表 | 引用字段 | 更新规则 | 删除规则 |
| --- | --- | --- | --- | --- | --- |
| `fk_stage2_verification_histories_user_id` | `user_id` | `users` | `id` | `NO ACTION` | `RESTRICT` |

<!-- BEGIN:MANUAL -->
## 3. 订单/账单职责与记录契约（手工维护章节，重刷脚本保留本区间）

> 来源：方案 2「职责重划」（见 `docs/执行计划/进行中/订单与账单记录内容统一方案-2026-10-06.md`）。
> 本章节为业务语义约定，不随 `information_schema` 重刷；结构变更由上方章节自动更新。

### 3.1 职责划分（目标态）

- **`invoices` = 资金域唯一真源**：`amount` / `discount` / `member_discount_amount`、券四件套（`coupon_id` + `user_coupon_id` + `coupon_code` + `coupon_snapshot`）、`paid_amount` / `paid_at` / `status`、`config_pricing_snapshot`、`member_discount_snapshot`。资金计算（推荐奖励基数、开通定价基数、退款可退额、用户端金额统计）一律读账单。
- **`orders` = 履约工单**：service 关联、`config_snapshot`（开通载荷）、履约准入（`status=PAID` 是履约分派、推荐奖励、库存占用、开通重试、退款资格的准入信号）。其金额/券列**保留为「创建时资金快照 + 生命周期投影」**：不删列、不改 `status` 枚举。
- **单一写者**：订单的状态与金额投影只允许经 `TradeLifecycleService`（`markInvoicePaid` / `cancelTrade` / `markInvoiceRefunded` / `decrementPaidProjection`）写入；任何业务路径不得直接 forceFill 订单状态/`paid_amount`。
- **读取准则**：资金决策读账单；订单域展示/计数（订单列表、订单数）读投影。

### 3.2 关键列语义

| 列 | 语义 |
| --- | --- |
| `orders.amount` | 创建时应付价快照；生命周期内由状态机投影为账单累计实收（`paid_amount` 同步），不作为资金计算依据 |
| `orders.discount` / `member_discount_amount` | 创建时优惠快照，只读展示；目录价还原一律经 `Money::catalogAmountOf(Invoice)` |
| `invoices.amount` | 应付价（目录价 = amount + discount + member_discount_amount） |
| `invoices.paid_amount` | 累计实收；部分支付递减/入账都经状态机 |
| `invoices.type` | 值域封闭：`new/renew/upgrade/recharge/deduction/referral_credit/refund/manual`；`normal` 仅作读取兼容（`InvoiceType::normalize`），禁止新写入 |

### 3.3 `config_snapshot` / `config_pricing_snapshot` 按 type 语义

`config_snapshot` 是**类型私有载荷**，跨 type 异义属设计而非缺陷：

| type | `config_snapshot` | `config_pricing_snapshot` |
| --- | --- | --- |
| new（新购） | 商品配置选择（开通载荷的一部分） | 目录价/折扣/会员折扣计价快照 |
| renew（续费） | 续费元数据 + 履约状态（`RENEW_FULFILLMENT_STATUS_KEY` 在此，不可迁走） | 续费路径补写同口径计价快照 |
| upgrade（升级/流量包） | 升级配置选择 | 必填，含 `meta.kind`（`host_upgrade` / 流量包 kind），跨 kind 互不取消的过滤依据 |

### 3.4 upgrade / 流量包账单的 `product_id` 语义（W10）

升级与流量包账单的 `invoices.product_id` 记录**源商品**（升级前的原商品 / 流量包挂靠的基础商品）；目标商品/加购商品信息在 `config_pricing_snapshot` 的 meta 中。续费定价、履约回写 `services.amount` 等以服务当前绑定商品为准，不得反推账单 `product_id` 为目标商品。

### 3.5 记录契约（不变式摘要）

购买类路径创建账单/订单必须满足：I1 金额守恒（`amount + discount + member_discount_amount = 目录价`）；I2 折扣分列；I3 券四件套；I4 快照组按 type 必填；I5 `invoice.order_id` 单向绑定；I6 type 值域封闭；I7 `trace_id` 同源同值。契约由 `tests/Feature/InvoiceRecordContractTest.php` 与 `tests/Feature/TradeLifecycleEquivalenceTest.php` 锁定。

### 3.6 结构命名与删除语义规范（2026-10-25 表结构审查新增，同日按裁决收敛删除语义）

本节约束**新增对象**；存量偏差见「执行计划/进行中/数据库表结构审查与优化方案-2026-10-25」。其中表注释缺失（B5）与同域同义列类型漂移（B6）已于 2026-10-25 第四轮批量收敛，现状见下方「已收敛项」；其余存量偏差（索引/外键命名、快照列宽度、datetime 与 timestamp 两家族、currency 的 varchar/char 选择）不回改，仅约束新增。

**索引**：二级索引 `{表名}_{列组合}_index`，唯一 `{表名}_{列组合}_unique`；不使用 `idx_`/`_idx` 前后缀、不使用表名缩写别名（如 `plugin_runtime_`）、不用 `fk_` 前缀命名二级索引。新增索引前先做前缀冗余自检：单列索引不得与更长组合索引的最左前缀重复（同唯一性下）。

**外键**：统一 Laravel 默认 `{表名}_{列名}_foreign`；存量 `fk_*`、`*_fk` 不回改。

**状态列**：交易域（orders/invoices/payments/services 等）统一 `tinyint` + 类常量值域；运维/日志域（archive、message、schedule、finance 运维族）统一 `varchar(24)`，同域长度必须一致（归档、调度、消息三域已于 2026-10-25 第四轮统一为 24）；同域 signed/unsigned 不得混用。

**时间列**：Laravel timestamps 与框架表用 `timestamp`；业务事件时刻列（`*_at` 业务语义）用 `datetime`，同域保持一致。`trace_id` 统一 `varchar(64)`（I7 同源同值），券域 coupons/coupon_campaigns/user_coupons 已于 2026-10-25 第四轮由 100 收敛到 64。

**已收敛项（2026-10-25 第四轮，迁移 2026_10_25_120000 / 2026_10_25_120100）**：
- 表注释：79 张表全部具备中文 `TABLE_COMMENT`，句式「XX表，动词短语说明职责」；新增表建表时必须写表注释。
- 同义列：归档域 status 24、调度域 status 24、消息域 status 24；券域 trace_id 64；`refunds` 与 `finance_refunds` 的 `refund_no` 统一 `varchar(40)`；`payments` 与 `gateway_logs` 的 `trade_no` 统一 `varchar(128)`。

**删除语义（2026-10-25 评审裁决：先硬删）**：删除即真删，禁止为交易核心表恢复软删除。`services`/`invoices`/`orders`/`payments` 的 `deleted_at` 列与 SoftDeletes 已移除：`services` 管理端删除走硬删事务（清空 order/ticket 关联、删除上游绑定）；`invoices`/`orders`/`payments` 无删除入口，生命周期终态一律走状态机（如 `ServiceStatus::TERMINATED`、`InvoiceStatus::CANCELLED`），且 `payments` 遵守「不物理删除」铁律。历史终态记录只以状态过滤，不存在 `deleted_at` 维度。

<!-- END:MANUAL -->
