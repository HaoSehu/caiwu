# idc 当前数据库表结构

> 来源：实库 `idc` 的无数据 DDL 导出。本文不包含任何业务记录。

共 `60` 张表。

## 表目录

- [`account_transactions`](#account-transactions)
- [`activity_logs`](#activity-logs)
- [`admin_user_roles`](#admin-user-roles)
- [`admin_users`](#admin-users)
- [`agent_applications`](#agent-applications)
- [`archive_audit_logs`](#archive-audit-logs)
- [`automation_logs`](#automation-logs)
- [`content_articles`](#content-articles)
- [`content_categories`](#content-categories)
- [`coupon_campaigns`](#coupon-campaigns)
- [`coupons`](#coupons)
- [`failed_jobs`](#failed-jobs)
- [`first_product_groups`](#first-product-groups)
- [`gateway_logs`](#gateway-logs)
- [`integration_plugin_bindings`](#integration-plugin-bindings)
- [`integration_plugin_configs`](#integration-plugin-configs)
- [`integration_plugin_runtime_logs`](#integration-plugin-runtime-logs)
- [`integration_plugins`](#integration-plugins)
- [`invoice_items`](#invoice-items)
- [`invoices`](#invoices)
- [`jobs`](#jobs)
- [`media_files`](#media-files)
- [`member_levels`](#member-levels)
- [`message_logs`](#message-logs)
- [`migrations`](#migrations)
- [`notice_reads`](#notice-reads)
- [`notification_templates`](#notification-templates)
- [`operation_logs`](#operation-logs)
- [`orders`](#orders)
- [`password_reset_tokens`](#password-reset-tokens)
- [`payment_callbacks`](#payment-callbacks)
- [`payments`](#payments)
- [`personal_access_tokens`](#personal-access-tokens)
- [`product_upstream_bindings`](#product-upstream-bindings)
- [`products`](#products)
- [`referral_account_logs`](#referral-account-logs)
- [`referral_rewards`](#referral-rewards)
- [`referral_withdrawals`](#referral-withdrawals)
- [`roles`](#roles)
- [`schedule_run_logs`](#schedule-run-logs)
- [`schedule_task_runs`](#schedule-task-runs)
- [`schedule_ticks`](#schedule-ticks)
- [`second_product_groups`](#second-product-groups)
- [`service_connection_snapshots`](#service-connection-snapshots)
- [`service_provision_attempts`](#service-provision-attempts)
- [`service_runtime_snapshots`](#service-runtime-snapshots)
- [`service_upstream_bindings`](#service-upstream-bindings)
- [`services`](#services)
- [`sessions`](#sessions)
- [`settings`](#settings)
- [`supplier_plugin_bindings`](#supplier-plugin-bindings)
- [`suppliers`](#suppliers)
- [`third_product_groups`](#third-product-groups)
- [`ticket_replies`](#ticket-replies)
- [`tickets`](#tickets)
- [`user_accounts`](#user-accounts)
- [`user_coupons`](#user-coupons)
- [`user_notifications`](#user-notifications)
- [`users`](#users)
- [`verification_histories`](#verification-histories)

## `account_transactions`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  | 账户流水自增主键 |
| `user_id` | `bigint unsigned` | 否 |  | 所属用户ID |
| `account_type` | `varchar(30)` | 否 |  | 账户类型：cash/credit/referral 等 |
| `event_type` | `varchar(30)` | 否 |  | 流水事件类型：recharge/consume/refund/adjust/reward_frozen/reward_released 等 |
| `change_amount` | `decimal(12,2)` | 否 | `'0.00'` | 本次变动金额，收入为正、支出为负 |
| `balance_after` | `decimal(12,2)` | 否 | `'0.00'` | 本次变动后的账户余额 |
| `source_type` | `varchar(30)` | 是 | `NULL` | 业务来源类型，如 invoice/payment/referral_withdrawal |
| `source_id` | `bigint unsigned` | 是 | `NULL` | 业务来源ID |
| `origin_type` | `varchar(30)` | 是 | `NULL` | 原始触发对象类型，用于跨域追踪 |
| `origin_id` | `bigint unsigned` | 是 | `NULL` | 原始触发对象ID |
| `remark` | `varchar(255)` | 是 | `NULL` | 流水备注 |
| `operator` | `varchar(50)` | 是 | `NULL` | 操作人快照 |
| `trace_id` | `varchar(64)` | 是 | `NULL` | 链路追踪号 |
| `created_at` | `timestamp` | 是 | `NULL` | 创建时间 |
| `updated_at` | `timestamp` | 是 | `NULL` | 更新时间 |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `account_transactions_user_account_created_idx` | KEY | `user_id,account_type,created_at,id` |
| `account_transactions_user_event_created_idx` | KEY | `user_id,event_type,created_at` |
| `account_transactions_origin_idx` | KEY | `origin_type,origin_id` |
| `account_transactions_trace_id_idx` | KEY | `trace_id` |
| `account_transactions_created_at_idx` | KEY | `created_at` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_account_transactions_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |

## `activity_logs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `actor_type` | `varchar(20)` | 否 | `'system'` | 操作者类型: admin, client, system, sub_account |
| `actor_id` | `bigint unsigned` | 是 | `NULL` | 操作者ID |
| `actor_name` | `varchar(100)` | 否 | `''` | 操作者名称快照 |
| `module` | `varchar(50)` | 否 |  | 模块: invoice, order, service, user, product, ticket, coupon, system |
| `action` | `varchar(100)` | 否 |  | 动作描述: create, pay, refund, suspend, terminate 等 |
| `description` | `text` | 否 |  | 可读描述 |
| `subject_type` | `varchar(50)` | 是 | `NULL` | 关联对象类型: invoice, service, order, user, ticket |
| `subject_id` | `bigint unsigned` | 是 | `NULL` | 关联对象ID |
| `context` | `json` | 是 | `NULL` | 附加结构化上下文 |
| `ip_address` | `varchar(45)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `activity_logs_module_action_index` | KEY | `module,action` |
| `activity_logs_subject_type_subject_id_index` | KEY | `subject_type,subject_id` |
| `activity_logs_created_at_index` | KEY | `created_at` |
| `activity_logs_actor_id_index` | KEY | `actor_id` |

## `admin_user_roles`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `admin_user_id` | `bigint unsigned` | 否 |  |  |
| `role_id` | `bigint unsigned` | 否 |  |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `admin_user_roles_admin_role_unique` | UNIQUE KEY | `admin_user_id,role_id` |
| `admin_user_roles_role_id_idx` | KEY | `role_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_admin_user_roles_admin_user_id` | `admin_user_id` | `admin_users` (`id`) | CASCADE | 默认 |
| `fk_stage2_admin_user_roles_role_id` | `role_id` | `roles` (`id`) | RESTRICT | 默认 |

## `admin_users`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `username` | `varchar(50)` | 否 |  |  |
| `password` | `varchar(255)` | 否 |  |  |
| `role_id` | `bigint unsigned` | 否 |  |  |
| `nickname` | `varchar(50)` | 是 | `NULL` |  |
| `status` | `tinyint` | 否 | `'1'` | 0=禁用 1=正常 |
| `last_login_at` | `timestamp` | 是 | `NULL` |  |
| `last_login_ip` | `varchar(45)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |
| `email` | `varchar(100)` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `admin_users_username_unique` | UNIQUE KEY | `username` |
| `admin_users_role_id_index` | KEY | `role_id` |
| `admin_users_email_index` | KEY | `email` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_admin_users_role_id` | `role_id` | `roles` (`id`) | RESTRICT | 默认 |

## `agent_applications`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `user_id` | `bigint unsigned` | 否 |  |  |
| `contact_name` | `varchar(50)` | 否 | `''` | 联系人 |
| `contact_phone` | `varchar(30)` | 否 | `''` | 联系手机 |
| `contact_qq` | `varchar(30)` | 否 | `''` | QQ号 |
| `company_name` | `varchar(120)` | 否 | `''` | 公司名称 |
| `reason` | `varchar(500)` | 否 | `''` | 申请说明 |
| `status` | `varchar(20)` | 否 | `'pending'` | 状态: pending/approved/rejected |
| `api_key` | `varchar(64)` | 是 | `NULL` | API密钥 |
| `admin_note` | `varchar(500)` | 否 | `''` | 管理员备注 |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `agent_applications_user_id_foreign` | KEY | `user_id` |
| `agent_applications_status_index` | KEY | `status` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `agent_applications_user_id_foreign` | `user_id` | `users` (`id`) | CASCADE | 默认 |

## `archive_audit_logs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `batch_id` | `varchar(64)` | 否 |  |  |
| `table_name` | `varchar(64)` | 否 |  |  |
| `mode` | `varchar(30)` | 否 |  |  |
| `row_count` | `int unsigned` | 否 | `'0'` |  |
| `file_path` | `varchar(500)` | 是 | `NULL` |  |
| `file_size` | `bigint unsigned` | 是 | `NULL` |  |
| `checksum_sha256` | `char(64)` | 是 | `NULL` |  |
| `status` | `varchar(30)` | 否 |  |  |
| `error_message` | `varchar(500)` | 是 | `NULL` |  |
| `started_at` | `timestamp` | 是 | `NULL` |  |
| `finished_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `archive_batch_idx` | KEY | `batch_id` |
| `archive_table_status_idx` | KEY | `table_name,status,created_at` |

## `automation_logs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `task_key` | `varchar(80)` | 否 |  |  |
| `action` | `varchar(80)` | 否 |  |  |
| `object_type` | `varchar(40)` | 否 |  |  |
| `object_id` | `bigint unsigned` | 否 |  |  |
| `rule_key` | `varchar(191)` | 否 | `''` |  |
| `meta` | `json` | 是 | `NULL` |  |
| `executed_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `automation_logs_unique_rule` | UNIQUE KEY | `task_key,action,object_type,object_id,rule_key` |
| `automation_logs_object_idx` | KEY | `object_type,object_id` |
| `automation_logs_task_key_index` | KEY | `task_key` |

## `content_articles`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `content_type` | `varchar(20)` | 否 |  | notice\|help |
| `category_id` | `bigint unsigned` | 是 | `NULL` |  |
| `title` | `varchar(200)` | 否 |  |  |
| `slug` | `varchar(220)` | 否 |  |  |
| `summary` | `varchar(500)` | 是 | `NULL` |  |
| `content` | `longtext` | 否 |  |  |
| `category_name` | `varchar(60)` | 是 | `NULL` |  |
| `keywords` | `varchar(255)` | 是 | `NULL` |  |
| `cover_image` | `varchar(500)` | 是 | `NULL` |  |
| `status` | `tinyint` | 否 | `'0'` | 0=草稿 1=已发布 2=已下线 |
| `is_pinned` | `tinyint` | 否 | `'0'` |  |
| `is_recommended` | `tinyint` | 否 | `'0'` |  |
| `sort_order` | `int` | 否 | `'0'` |  |
| `view_count` | `int unsigned` | 否 | `'0'` |  |
| `require_reread_at` | `timestamp` | 是 | `NULL` |  |
| `publish_at` | `timestamp` | 是 | `NULL` |  |
| `last_published_at` | `timestamp` | 是 | `NULL` |  |
| `created_by` | `bigint unsigned` | 是 | `NULL` |  |
| `updated_by` | `bigint unsigned` | 是 | `NULL` |  |
| `operator` | `varchar(50)` | 是 | `NULL` |  |
| `remark` | `varchar(255)` | 是 | `NULL` |  |
| `trace_id` | `varchar(64)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |
| `deleted_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `content_articles_slug_unique` | UNIQUE KEY | `slug` |
| `idx_content_type_status_publish` | KEY | `content_type,status,publish_at` |
| `idx_content_type_pin_sort` | KEY | `content_type,is_pinned,sort_order,id` |
| `idx_content_type_recommend` | KEY | `content_type,is_recommended,publish_at` |
| `idx_content_category_type` | KEY | `category_name,content_type` |
| `content_articles_created_by_index` | KEY | `created_by` |
| `content_articles_updated_by_index` | KEY | `updated_by` |
| `idx_content_article_type_category` | KEY | `content_type,category_id` |
| `idx_article_published` | KEY | `status,publish_at,is_pinned` |
| `idx_article_category_published` | KEY | `category_id,status,publish_at` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_content_articles_category_id` | `category_id` | `content_categories` (`id`) | SET NULL | 默认 |

## `content_categories`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `content_type` | `varchar(20)` | 否 |  | notice\|help |
| `name` | `varchar(80)` | 否 |  |  |
| `slug` | `varchar(120)` | 否 |  |  |
| `description` | `varchar(255)` | 是 | `NULL` |  |
| `status` | `tinyint` | 否 | `'1'` | 0=禁用 1=启用 |
| `sort_order` | `int` | 否 | `'0'` |  |
| `created_by` | `bigint unsigned` | 是 | `NULL` |  |
| `updated_by` | `bigint unsigned` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `uniq_content_category_type_name` | UNIQUE KEY | `content_type,name` |
| `uniq_content_category_type_slug` | UNIQUE KEY | `content_type,slug` |
| `idx_content_category_type_status_sort` | KEY | `content_type,status,sort_order` |

## `coupon_campaigns`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `name` | `varchar(120)` | 否 |  |  |
| `description` | `varchar(255)` | 是 | `NULL` |  |
| `weekdays` | `json` | 是 | `NULL` |  |
| `trigger_time` | `varchar(8)` | 否 |  |  |
| `issue_quantity` | `int unsigned` | 否 | `'1'` |  |
| `valid_duration_hours` | `int unsigned` | 是 | `NULL` |  |
| `discount_scope` | `varchar(20)` | 否 | `'first_month'` |  |
| `discount_type` | `varchar(20)` | 否 |  |  |
| `discount_value` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `min_amount` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `max_discount_amount` | `decimal(12,2)` | 是 | `NULL` |  |
| `billing_cycles` | `json` | 是 | `NULL` |  |
| `product_ids` | `json` | 是 | `NULL` |  |
| `first_order_only` | `tinyint(1)` | 否 | `'0'` |  |
| `per_user_limit` | `int unsigned` | 是 | `NULL` |  |
| `status` | `tinyint` | 否 | `'1'` |  |
| `sort_order` | `int unsigned` | 否 | `'0'` |  |
| `last_dispatched_at` | `timestamp` | 是 | `NULL` |  |
| `last_coupon_id` | `bigint unsigned` | 是 | `NULL` |  |
| `remark` | `varchar(255)` | 是 | `NULL` |  |
| `operator` | `varchar(100)` | 是 | `NULL` |  |
| `trace_id` | `varchar(100)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `coupon_campaigns_status_sort_idx` | KEY | `status,sort_order` |
| `coupon_campaigns_trigger_status_idx` | KEY | `trigger_time,status` |
| `idx_stage2_coupon_campaigns_last_coupon_id` | KEY | `last_coupon_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_coupon_campaigns_last_coupon_id` | `last_coupon_id` | `coupons` (`id`) | SET NULL | 默认 |

## `coupons`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `coupon_campaign_id` | `bigint unsigned` | 是 | `NULL` |  |
| `name` | `varchar(120)` | 否 |  |  |
| `code` | `varchar(50)` | 否 |  |  |
| `description` | `varchar(255)` | 是 | `NULL` |  |
| `distribution_type` | `varchar(20)` | 否 | `'public'` |  |
| `discount_scope` | `varchar(20)` | 否 | `'first_month'` |  |
| `discount_type` | `varchar(20)` | 否 |  |  |
| `discount_value` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `min_amount` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `max_discount_amount` | `decimal(12,2)` | 是 | `NULL` |  |
| `billing_cycles` | `json` | 是 | `NULL` |  |
| `product_ids` | `json` | 是 | `NULL` |  |
| `first_order_only` | `tinyint(1)` | 否 | `'0'` |  |
| `total_usage_limit` | `int unsigned` | 是 | `NULL` |  |
| `per_user_limit` | `int unsigned` | 是 | `NULL` |  |
| `used_count` | `int unsigned` | 否 | `'0'` |  |
| `status` | `tinyint` | 否 | `'1'` |  |
| `sort_order` | `int unsigned` | 否 | `'0'` |  |
| `starts_at` | `timestamp` | 是 | `NULL` |  |
| `expires_at` | `timestamp` | 是 | `NULL` |  |
| `remark` | `varchar(255)` | 是 | `NULL` |  |
| `operator` | `varchar(100)` | 是 | `NULL` |  |
| `trace_id` | `varchar(100)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `coupons_code_unique` | UNIQUE KEY | `code` |
| `coupons_campaign_status_idx` | KEY | `coupon_campaign_id,status` |
| `coupons_status_sort_idx` | KEY | `status,sort_order` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_coupons_coupon_campaign_id` | `coupon_campaign_id` | `coupon_campaigns` (`id`) | SET NULL | 默认 |

## `failed_jobs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `uuid` | `varchar(255)` | 否 |  |  |
| `connection` | `text` | 否 |  |  |
| `queue` | `text` | 否 |  |  |
| `payload` | `longtext` | 否 |  |  |
| `exception` | `longtext` | 否 |  |  |
| `failed_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `failed_jobs_uuid_unique` | UNIQUE KEY | `uuid` |

## `first_product_groups`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `code` | `varchar(50)` | 是 | `NULL` | 业务编码：vps/dedicated/domain/… |
| `product_type` | `varchar(50)` | 是 | `NULL` | 商品类型：cloud_server/game_cloud/… |
| `name` | `varchar(100)` | 否 |  | 名称 |
| `slug` | `varchar(100)` | 是 | `NULL` | URL标识 |
| `description` | `varchar(255)` | 是 | `NULL` | 分组说明 |
| `icon` | `varchar(100)` | 是 | `NULL` | 图标 |
| `banner_image` | `varchar(255)` | 是 | `NULL` | 横幅图 |
| `sort_order` | `int` | 否 | `'0'` | 排序 |
| `is_visible` | `tinyint unsigned` | 否 | `'1'` | 前台可见 |
| `is_system` | `tinyint unsigned` | 否 | `'0'` | 系统内置 |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `first_product_groups_slug_unique` | UNIQUE KEY | `slug` |
| `first_product_groups_code_unique` | UNIQUE KEY | `code` |

## `gateway_logs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `plugin_id` | `bigint unsigned` | 是 | `NULL` |  |
| `gateway_key` | `varchar(120)` | 是 | `NULL` |  |
| `gateway` | `varchar(50)` | 否 |  | 网关标识: alipay_f2f, wechat_native 等 |
| `action` | `varchar(50)` | 否 |  | 操作: precreate, notify, query, refund |
| `out_trade_no` | `varchar(128)` | 是 | `NULL` | 商户订单号 |
| `trade_no` | `varchar(128)` | 是 | `NULL` | 第三方交易号 |
| `invoice_id` | `bigint unsigned` | 是 | `NULL` | 关联账单ID |
| `trace_id` | `varchar(64)` | 是 | `NULL` |  |
| `request_data` | `json` | 是 | `NULL` | 请求数据(脱敏后) |
| `response_data` | `json` | 是 | `NULL` | 响应数据 |
| `result_status` | `varchar(20)` | 否 | `'unknown'` | 结果: success, failed, pending, unknown |
| `error_msg` | `text` | 是 |  | 错误信息 |
| `ip_address` | `varchar(45)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `gateway_logs_gateway_action_index` | KEY | `gateway,action` |
| `gateway_logs_created_at_index` | KEY | `created_at` |
| `gateway_logs_out_trade_no_index` | KEY | `out_trade_no` |
| `gateway_logs_invoice_id_index` | KEY | `invoice_id` |
| `gateway_logs_plugin_created_idx` | KEY | `plugin_id,created_at` |
| `gateway_logs_gateway_key_idx` | KEY | `gateway_key,created_at` |
| `gateway_logs_trace_idx` | KEY | `trace_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_gateway_logs_invoice_id` | `invoice_id` | `invoices` (`id`) | SET NULL | 默认 |
| `gateway_logs_plugin_fk` | `plugin_id` | `integration_plugins` (`id`) | SET NULL | 默认 |

## `integration_plugin_bindings`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `domain` | `varchar(32)` | 否 |  |  |
| `plugin_id` | `bigint unsigned` | 否 |  |  |
| `binding_type` | `varchar(50)` | 否 |  | global/supplier/product/service/payment/notification/custom |
| `bindable_type` | `varchar(120)` | 否 | `'global'` |  |
| `bindable_id` | `bigint unsigned` | 否 | `'0'` |  |
| `binding_key` | `varchar(120)` | 否 |  | 同一对象下的绑定名 |
| `provider_key` | `varchar(120)` | 是 | `NULL` | 外部协议标识快照 |
| `priority` | `int` | 否 | `'0'` |  |
| `status` | `tinyint unsigned` | 否 | `'1'` |  |
| `config_json` | `json` | 是 | `NULL` |  |
| `secret_json` | `longtext` | 是 |  |  |
| `has_secret_json` | `json` | 是 | `NULL` |  |
| `runtime_policy_json` | `json` | 是 | `NULL` |  |
| `created_by` | `bigint unsigned` | 是 | `NULL` |  |
| `updated_by` | `bigint unsigned` | 是 | `NULL` |  |
| `backfill_batch_id` | `varchar(64)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `plugin_bindings_unique` | UNIQUE KEY | `domain,binding_type,bindable_type,bindable_id,binding_key` |
| `plugin_bindings_plugin_status_idx` | KEY | `plugin_id,status` |
| `plugin_bindings_domain_provider_status_idx` | KEY | `domain,provider_key,status` |
| `plugin_bindings_bindable_idx` | KEY | `bindable_type,bindable_id,domain` |
| `plugin_bindings_backfill_batch_idx` | KEY | `backfill_batch_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `integration_plugin_bindings_plugin_id_foreign` | `plugin_id` | `integration_plugins` (`id`) | RESTRICT | 默认 |

## `integration_plugin_configs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `plugin_id` | `bigint unsigned` | 否 |  |  |
| `config_json` | `json` | 是 | `NULL` |  |
| `secret_json` | `longtext` | 是 |  |  |
| `has_secret_json` | `json` | 是 | `NULL` |  |
| `updated_by` | `bigint unsigned` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `integration_plugin_configs_plugin_unique` | UNIQUE KEY | `plugin_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `integration_plugin_configs_plugin_id_foreign` | `plugin_id` | `integration_plugins` (`id`) | CASCADE | 默认 |

## `integration_plugin_runtime_logs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `trace_id` | `varchar(64)` | 是 | `NULL` |  |
| `domain` | `varchar(32)` | 否 |  |  |
| `plugin_id` | `bigint unsigned` | 是 | `NULL` |  |
| `plugin_key` | `varchar(120)` | 否 |  |  |
| `slug` | `varchar(120)` | 否 |  |  |
| `action` | `varchar(120)` | 否 |  |  |
| `binding_id` | `bigint unsigned` | 是 | `NULL` |  |
| `bindable_type` | `varchar(120)` | 是 | `NULL` |  |
| `bindable_id` | `bigint unsigned` | 是 | `NULL` |  |
| `actor_type` | `varchar(50)` | 是 | `NULL` |  |
| `actor_id` | `bigint unsigned` | 是 | `NULL` |  |
| `status` | `varchar(30)` | 否 |  |  |
| `duration_ms` | `int unsigned` | 是 | `NULL` |  |
| `error_code` | `varchar(80)` | 是 | `NULL` |  |
| `error_message` | `varchar(500)` | 是 | `NULL` |  |
| `request_meta_json` | `json` | 是 | `NULL` |  |
| `response_meta_json` | `json` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `plugin_runtime_trace_idx` | KEY | `trace_id` |
| `plugin_runtime_plugin_created_idx` | KEY | `plugin_id,created_at` |
| `plugin_runtime_domain_action_created_idx` | KEY | `domain,action,created_at` |
| `plugin_runtime_status_created_idx` | KEY | `status,created_at` |
| `plugin_runtime_bindable_idx` | KEY | `bindable_type,bindable_id,created_at` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `integration_plugin_runtime_logs_plugin_id_foreign` | `plugin_id` | `integration_plugins` (`id`) | SET NULL | 默认 |

## `integration_plugins`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `domain` | `varchar(32)` | 否 |  |  |
| `slug` | `varchar(120)` | 否 |  |  |
| `plugin_key` | `varchar(120)` | 否 |  |  |
| `name` | `varchar(120)` | 否 |  |  |
| `version` | `varchar(32)` | 否 | `'1.0.0'` |  |
| `provider_class` | `varchar(255)` | 是 | `NULL` |  |
| `entry_class` | `varchar(255)` | 否 |  |  |
| `capabilities_json` | `json` | 是 | `NULL` |  |
| `config_schema_json` | `json` | 是 | `NULL` |  |
| `status` | `tinyint unsigned` | 否 | `'0'` |  |
| `installed_at` | `timestamp` | 是 | `NULL` |  |
| `enabled_at` | `timestamp` | 是 | `NULL` |  |
| `disabled_at` | `timestamp` | 是 | `NULL` |  |
| `installed_by` | `bigint unsigned` | 是 | `NULL` |  |
| `enabled_by` | `bigint unsigned` | 是 | `NULL` |  |
| `source_hash` | `varchar(128)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `integration_plugins_domain_slug_unique` | UNIQUE KEY | `domain,slug` |
| `integration_plugins_domain_key_unique` | UNIQUE KEY | `domain,plugin_key` |
| `integration_plugins_domain_status_index` | KEY | `domain,status` |

## `invoice_items`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  | 账单明细自增主键 |
| `invoice_id` | `bigint unsigned` | 否 |  | 所属账单ID |
| `item_name` | `varchar(200)` | 否 |  | 明细名称 |
| `item_type` | `varchar(30)` | 否 | `'normal'` | 明细类型：normal/config/addon/discount/refund 等 |
| `quantity` | `int unsigned` | 否 | `'1'` | 明细数量 |
| `unit_price` | `decimal(12,2)` | 否 | `'0.00'` | 明细单价 |
| `discount_amount` | `decimal(12,2)` | 否 | `'0.00'` | 明细优惠金额 |
| `line_amount` | `decimal(12,2)` | 否 | `'0.00'` | 明细小计金额 |
| `meta_json` | `json` | 是 | `NULL` | 明细扩展快照 JSON |
| `created_at` | `timestamp` | 是 | `NULL` | 创建时间 |
| `updated_at` | `timestamp` | 是 | `NULL` | 更新时间 |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `invoice_items_invoice_id_index` | KEY | `invoice_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_invoice_items_invoice_id` | `invoice_id` | `invoices` (`id`) | CASCADE | 默认 |

## `invoices`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  | 账单自增主键 |
| `invoice_no` | `varchar(32)` | 否 |  | 业务账单号，对外展示和支付关联使用 |
| `user_id` | `bigint unsigned` | 否 |  | 所属用户ID |
| `order_id` | `bigint unsigned` | 是 | `NULL` | 内部订单/开通投影ID，仅用于流程追踪 |
| `product_id` | `bigint unsigned` | 是 | `NULL` | 关联商品ID，手工账单可为空 |
| `product_spec_snapshot` | `varchar(255)` | 是 | `NULL` | 账单生成时的商品规格展示快照 |
| `product_type_snapshot` | `varchar(100)` | 是 | `NULL` | 账单生成时的商品类型快照 |
| `service_id` | `bigint unsigned` | 是 | `NULL` | 关联服务实例ID |
| `coupon_id` | `bigint unsigned` | 是 | `NULL` | 使用的优惠券模板ID |
| `user_coupon_id` | `bigint unsigned` | 是 | `NULL` | 使用的用户优惠券ID |
| `coupon_code` | `varchar(100)` | 是 | `NULL` | 使用的优惠码快照 |
| `type` | `varchar(20)` | 否 | `'normal'` | 账单类型：normal/new/renew/recharge/deduction/referral_credit/manual/upgrade |
| `amount` | `decimal(12,2)` | 否 |  | 账单应收金额 |
| `discount` | `decimal(12,2)` | 否 | `'0.00'` | 账单优惠抵扣金额 |
| `billing_cycle` | `varchar(30)` | 是 | `NULL` | 计费周期：monthly/quarterly/annually/onetime 等 |
| `quantity` | `int unsigned` | 否 | `'1'` | 购买数量或计费数量 |
| `config_snapshot` | `json` | 是 | `NULL` | 下单配置快照 JSON |
| `config_pricing_snapshot` | `json` | 是 | `NULL` | 配置项计价快照 JSON |
| `coupon_snapshot` | `json` | 是 | `NULL` | 优惠券使用快照 JSON |
| `paid_amount` | `decimal(12,2)` | 否 | `'0.00'` | 已支付入账金额 |
| `status` | `tinyint` | 否 | `'0'` | 账单状态：0待支付 1已支付 2已取消 3已逾期 5已退款 |
| `due_date` | `date` | 是 | `NULL` |  |
| `paid_at` | `timestamp` | 是 | `NULL` | 账单支付完成时间 |
| `deleted_at` | `timestamp` | 是 | `NULL` |  |
| `refund_trace_id` | `varchar(64)` | 是 | `NULL` | 退款链路追踪号 |
| `refund_method` | `varchar(32)` | 是 | `NULL` | 退款方式 |
| `refund_amount` | `decimal(12,2)` | 是 | `NULL` | 退款金额 |
| `refunded_at` | `timestamp` | 是 | `NULL` | 退款完成时间 |
| `created_at` | `timestamp` | 是 | `NULL` | 创建时间 |
| `updated_at` | `timestamp` | 是 | `NULL` | 更新时间 |
| `remark` | `varchar(255)` | 是 | `NULL` | 账单备注 |
| `operator` | `varchar(50)` | 是 | `NULL` | 操作人快照 |
| `trace_id` | `varchar(64)` | 是 | `NULL` | 链路追踪号 |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `invoices_invoice_no_unique` | UNIQUE KEY | `invoice_no` |
| `invoices_user_id_index` | KEY | `user_id` |
| `invoices_status_due_date_index` | KEY | `status,due_date` |
| `invoices_user_status_id_idx` | KEY | `user_id,status,id` |
| `invoices_status_paid_at_idx` | KEY | `status,paid_at` |
| `invoices_order_id_idx` | KEY | `order_id` |
| `invoices_trace_id_idx` | KEY | `trace_id` |
| `invoices_product_id_idx` | KEY | `product_id` |
| `invoices_service_id_idx` | KEY | `service_id` |
| `invoices_user_status_created_idx` | KEY | `user_id,status,created_at` |
| `fk_invoices_user_coupon_id` | KEY | `user_coupon_id` |
| `idx_stage2_invoices_coupon_id` | KEY | `coupon_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_invoices_order_id` | `order_id` | `orders` (`id`) | SET NULL | 默认 |
| `fk_invoices_product_id` | `product_id` | `products` (`id`) | RESTRICT | 默认 |
| `fk_invoices_user_coupon_id` | `user_coupon_id` | `user_coupons` (`id`) | SET NULL | 默认 |
| `fk_invoices_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |
| `fk_stage2_invoices_coupon_id` | `coupon_id` | `coupons` (`id`) | SET NULL | 默认 |
| `fk_stage2_invoices_service_id` | `service_id` | `services` (`id`) | SET NULL | 默认 |

## `jobs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `queue` | `varchar(255)` | 否 |  |  |
| `payload` | `longtext` | 否 |  |  |
| `attempts` | `tinyint unsigned` | 否 |  |  |
| `reserved_at` | `int unsigned` | 是 | `NULL` |  |
| `available_at` | `int unsigned` | 否 |  |  |
| `created_at` | `int unsigned` | 否 |  |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `jobs_queue_index` | KEY | `queue` |

## `media_files`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `filename` | `varchar(255)` | 否 |  |  |
| `path` | `varchar(500)` | 否 |  | 相对路径，如 /uploads/content/20260419/cover_xxx.jpg |
| `url` | `varchar(500)` | 否 |  | 完整访问 URL |
| `mime_type` | `varchar(100)` | 是 | `NULL` |  |
| `size` | `bigint unsigned` | 否 | `'0'` | 文件大小(字节) |
| `width` | `int unsigned` | 是 | `NULL` | 图片宽度 |
| `height` | `int unsigned` | 是 | `NULL` | 图片高度 |
| `group` | `varchar(50)` | 否 | `'content'` | 分组: content, avatar, brand 等 |
| `uploaded_by` | `bigint unsigned` | 否 | `'0'` | 上传管理员ID |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `media_files_group_index` | KEY | `group` |
| `media_files_uploaded_by_index` | KEY | `uploaded_by` |
| `media_files_created_at_index` | KEY | `created_at` |

## `member_levels`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `name` | `varchar(50)` | 否 |  |  |
| `code` | `varchar(30)` | 否 |  |  |
| `sales_amount_min` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `sales_amount_max` | `decimal(12,2)` | 是 | `NULL` |  |
| `reward_rate` | `decimal(5,2)` | 否 | `'0.00'` |  |
| `status` | `tinyint` | 否 | `'1'` | 0=禁用 1=启用 |
| `sort_order` | `int` | 否 | `'0'` |  |
| `remark` | `varchar(255)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `member_levels_code_unique` | UNIQUE KEY | `code` |
| `idx_member_level_status_sort` | KEY | `status,sort_order` |
| `idx_member_level_sales_range` | KEY | `sales_amount_min,sales_amount_max` |

## `message_logs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  | 消息日志ID |
| `plugin_id` | `bigint unsigned` | 是 | `NULL` | 插件ID |
| `driver_key` | `varchar(120)` | 是 | `NULL` | 驱动标识 |
| `trace_id` | `varchar(64)` | 是 | `NULL` | 链路追踪ID |
| `channel` | `varchar(20)` | 否 |  | 消息渠道：email/sms |
| `recipient` | `varchar(255)` | 否 |  | 接收人邮箱或手机号 |
| `template_code` | `varchar(120)` | 是 | `NULL` | 业务模板编码或供应商模板ID |
| `subject` | `varchar(255)` | 是 | `NULL` | 邮件主题 |
| `content` | `mediumtext` | 否 |  | 发送内容快照 |
| `params_json` | `json` | 是 | `NULL` | 渲染参数快照 |
| `provider` | `varchar(120)` | 是 | `NULL` | 供应商或驱动 |
| `request_id` | `varchar(100)` | 是 | `NULL` | 供应商请求ID |
| `status` | `varchar(20)` | 否 | `'pending'` | 发送状态 |
| `error_msg` | `text` | 是 |  | 失败原因 |
| `sent_at` | `timestamp` | 是 | `NULL` | 发送完成时间 |
| `origin_type` | `varchar(50)` | 是 | `NULL` | 来源类型快照 |
| `origin_id` | `bigint unsigned` | 是 | `NULL` | 来源ID快照 |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `message_logs_channel_created_at_idx` | KEY | `channel,created_at` |
| `message_logs_recipient_created_at_idx` | KEY | `recipient,created_at` |
| `message_logs_driver_created_idx` | KEY | `driver_key,created_at` |
| `message_logs_plugin_created_idx` | KEY | `plugin_id,created_at` |
| `message_logs_channel_driver_idx` | KEY | `channel,driver_key,created_at` |
| `message_logs_origin_idx` | KEY | `origin_type,origin_id` |
| `message_logs_trace_idx` | KEY | `trace_id` |
| `message_logs_request_id_idx` | KEY | `request_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `message_logs_plugin_fk` | `plugin_id` | `integration_plugins` (`id`) | SET NULL | 默认 |

## `migrations`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `int unsigned` | 否 |  |  |
| `migration` | `varchar(255)` | 否 |  |  |
| `batch` | `int` | 否 |  |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |

## `notice_reads`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `user_id` | `bigint unsigned` | 否 |  |  |
| `article_id` | `bigint unsigned` | 否 |  |  |
| `read_at` | `timestamp` | 否 |  |  |
| `created_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `notice_reads_user_id_article_id_unique` | UNIQUE KEY | `user_id,article_id` |
| `notice_reads_user_id_index` | KEY | `user_id` |
| `notice_reads_article_id_index` | KEY | `article_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_notice_reads_article_id` | `article_id` | `content_articles` (`id`) | CASCADE | 默认 |
| `fk_stage2_notice_reads_user_id` | `user_id` | `users` (`id`) | CASCADE | 默认 |

## `notification_templates`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `channel` | `varchar(20)` | 否 |  |  |
| `code` | `varchar(64)` | 否 |  |  |
| `name` | `varchar(120)` | 否 |  |  |
| `description` | `varchar(500)` | 否 | `''` |  |
| `audience` | `varchar(20)` | 否 | `'user'` |  |
| `subject` | `varchar(255)` | 是 | `NULL` |  |
| `content` | `longtext` | 否 |  |  |
| `variables_json` | `json` | 是 | `NULL` |  |
| `provider_variables_json` | `json` | 是 | `NULL` |  |
| `provider_template_id` | `varchar(120)` | 是 | `NULL` |  |
| `is_enabled` | `tinyint(1)` | 否 | `'1'` |  |
| `is_custom` | `tinyint(1)` | 否 | `'0'` |  |
| `sort_order` | `int unsigned` | 否 | `'0'` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `notification_templates_channel_code_unique` | UNIQUE KEY | `channel,code` |
| `notification_templates_channel_audience_enabled_index` | KEY | `channel,audience,is_enabled` |

## `operation_logs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `user_id` | `bigint unsigned` | 是 | `NULL` |  |
| `user_type` | `varchar(10)` | 是 | `NULL` | admin\|client |
| `action` | `varchar(100)` | 否 |  |  |
| `module` | `varchar(50)` | 是 | `NULL` |  |
| `subject_id` | `bigint unsigned` | 是 | `NULL` |  |
| `context` | `json` | 是 | `NULL` |  |
| `ip_address` | `varchar(45)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `operation_logs_user_id_user_type_index` | KEY | `user_id,user_type` |
| `operation_logs_module_created_at_index` | KEY | `module,created_at` |
| `operation_logs_user_type_created_at_idx` | KEY | `user_id,user_type,created_at` |
| `operation_logs_module_subject_created_idx` | KEY | `module,subject_id,created_at,id` |
| `operation_logs_created_at_idx` | KEY | `created_at` |
| `operation_logs_user_created_at_idx` | KEY | `user_id,created_at` |

## `orders`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `order_no` | `varchar(32)` | 否 |  |  |
| `user_id` | `bigint unsigned` | 否 |  |  |
| `product_id` | `bigint unsigned` | 是 | `NULL` |  |
| `product_spec_snapshot` | `varchar(200)` | 是 | `NULL` |  |
| `product_type_snapshot` | `varchar(50)` | 是 | `NULL` |  |
| `service_id` | `bigint unsigned` | 是 | `NULL` |  |
| `type` | `varchar(20)` | 否 |  | new\|renew\|upgrade\|downgrade |
| `coupon_id` | `bigint unsigned` | 是 | `NULL` |  |
| `user_coupon_id` | `bigint unsigned` | 是 | `NULL` |  |
| `coupon_code` | `varchar(50)` | 是 | `NULL` |  |
| `amount` | `decimal(12,2)` | 否 |  |  |
| `discount` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `paid_amount` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `billing_cycle` | `varchar(20)` | 是 | `NULL` |  |
| `quantity` | `int unsigned` | 否 | `'1'` |  |
| `config_snapshot` | `json` | 是 | `NULL` |  |
| `config_pricing_snapshot` | `json` | 是 | `NULL` |  |
| `coupon_snapshot` | `json` | 是 | `NULL` |  |
| `status` | `tinyint` | 否 | `'0'` | 0=待付款 1=已付款 2=开通中 3=已完成 4=已取消 5=已退款 |
| `paid_at` | `timestamp` | 是 | `NULL` |  |
| `deleted_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |
| `remark` | `varchar(255)` | 是 | `NULL` | 备注 |
| `operator` | `varchar(50)` | 是 | `NULL` | 操作人 |
| `trace_id` | `varchar(64)` | 是 | `NULL` | 链路追踪号 |
| `projection_type` | `varchar(32)` | 否 | `'provisioning'` | 内部投影类型：provisioning=开通投影 |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `orders_order_no_unique` | UNIQUE KEY | `order_no` |
| `orders_user_id_index` | KEY | `user_id` |
| `orders_user_status_id_idx` | KEY | `user_id,status,id` |
| `orders_created_at_idx` | KEY | `created_at` |
| `orders_status_type_created_at_idx` | KEY | `status,type,created_at,id` |
| `orders_coupon_id_idx` | KEY | `coupon_id` |
| `orders_user_coupon_id_idx` | KEY | `user_coupon_id` |
| `orders_trace_id_idx` | KEY | `trace_id` |
| `orders_product_id_idx` | KEY | `product_id` |
| `orders_service_status_id_idx` | KEY | `service_id,status,id` |
| `orders_projection_type_idx` | KEY | `projection_type` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_orders_coupon_id` | `coupon_id` | `coupons` (`id`) | SET NULL | 默认 |
| `fk_stage2_orders_product_id` | `product_id` | `products` (`id`) | RESTRICT | 默认 |
| `fk_stage2_orders_service_id` | `service_id` | `services` (`id`) | SET NULL | 默认 |
| `fk_stage2_orders_user_coupon_id` | `user_coupon_id` | `user_coupons` (`id`) | SET NULL | 默认 |
| `fk_stage2_orders_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |

## `password_reset_tokens`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `email` | `varchar(255)` | 否 |  |  |
| `token` | `varchar(255)` | 否 |  |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `email` |

## `payment_callbacks`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  | 支付回调自增主键 |
| `payment_id` | `bigint unsigned` | 否 |  | 关联支付记录ID |
| `plugin_id` | `bigint unsigned` | 是 | `NULL` |  |
| `gateway_key` | `varchar(120)` | 是 | `NULL` |  |
| `callback_type` | `varchar(20)` | 否 |  | 回调类型：notify/query/refund 等 |
| `gateway_trade_no` | `varchar(100)` | 是 | `NULL` | 第三方交易号 |
| `payload_json` | `json` | 是 | `NULL` | 回调载荷 JSON |
| `is_verified` | `tinyint` | 否 | `'0'` | 验签结果：0未通过/未验签 1已通过 |
| `received_at` | `timestamp` | 是 | `NULL` | 收到回调时间 |
| `remark` | `varchar(255)` | 是 | `NULL` | 回调备注或处理说明 |
| `operator` | `varchar(50)` | 是 | `NULL` | 操作人快照 |
| `trace_id` | `varchar(64)` | 是 | `NULL` | 链路追踪号 |
| `created_at` | `timestamp` | 是 | `NULL` | 创建时间 |
| `updated_at` | `timestamp` | 是 | `NULL` | 更新时间 |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `payment_callbacks_payment_type_unique` | UNIQUE KEY | `payment_id,callback_type` |
| `payment_callbacks_verified_received_idx` | KEY | `is_verified,received_at` |
| `payment_callbacks_gateway_trade_no_idx` | KEY | `gateway_trade_no` |
| `payment_callbacks_trace_id_idx` | KEY | `trace_id` |
| `payment_callbacks_plugin_received_idx` | KEY | `plugin_id,received_at` |
| `payment_callbacks_gateway_key_idx` | KEY | `gateway_key,received_at` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_payment_callbacks_payment_id` | `payment_id` | `payments` (`id`) | CASCADE | 默认 |
| `payment_callbacks_plugin_fk` | `plugin_id` | `integration_plugins` (`id`) | SET NULL | 默认 |

## `payments`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  | 支付记录自增主键 |
| `payment_no` | `varchar(32)` | 否 |  | 内部支付单号 |
| `user_id` | `bigint unsigned` | 否 |  | 支付用户ID |
| `order_id` | `bigint unsigned` | 是 | `NULL` | 内部订单/开通投影ID，仅用于流程追踪 |
| `invoice_id` | `bigint unsigned` | 是 | `NULL` | 关联账单ID |
| `plugin_id` | `bigint unsigned` | 是 | `NULL` |  |
| `gateway_key` | `varchar(120)` | 是 | `NULL` |  |
| `trade_no` | `varchar(100)` | 是 | `NULL` | 第三方交易号 |
| `amount` | `decimal(12,2)` | 否 |  | 第三方支付金额 |
| `status` | `tinyint` | 否 | `'0'` | 支付状态：0待支付 1成功 2失败 3已退款 |
| `callback_raw` | `json` | 是 | `NULL` | 最近一次回调原始载荷 JSON |
| `paid_at` | `timestamp` | 是 | `NULL` | 第三方确认支付时间 |
| `deleted_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` | 创建时间 |
| `updated_at` | `timestamp` | 是 | `NULL` | 更新时间 |
| `remark` | `varchar(255)` | 是 | `NULL` | 支付备注 |
| `operator` | `varchar(50)` | 是 | `NULL` | 操作人快照 |
| `trace_id` | `varchar(64)` | 是 | `NULL` | 链路追踪号 |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `payments_payment_no_unique` | UNIQUE KEY | `payment_no` |
| `payments_plugin_trade_unique` | UNIQUE KEY | `plugin_id,gateway_key,trade_no` |
| `payments_trade_no_index` | KEY | `trade_no` |
| `payments_invoice_gateway_status_id_idx` | KEY | `invoice_id,status,id` |
| `payments_invoice_status_created_at_idx` | KEY | `invoice_id,status,created_at,id` |
| `payments_status_paid_at_idx` | KEY | `status,paid_at` |
| `payments_user_status_created_idx` | KEY | `user_id,status,created_at` |
| `payments_trace_id_idx` | KEY | `trace_id` |
| `payments_order_status_idx` | KEY | `order_id,status` |
| `payments_plugin_status_paid_idx` | KEY | `plugin_id,status,paid_at` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_payments_invoice_id` | `invoice_id` | `invoices` (`id`) | RESTRICT | 默认 |
| `fk_payments_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |
| `fk_stage2_payments_order_id` | `order_id` | `orders` (`id`) | SET NULL | 默认 |
| `payments_plugin_fk` | `plugin_id` | `integration_plugins` (`id`) | RESTRICT | 默认 |

## `personal_access_tokens`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `tokenable_type` | `varchar(255)` | 否 |  |  |
| `tokenable_id` | `bigint unsigned` | 否 |  |  |
| `name` | `text` | 否 |  |  |
| `token` | `varchar(64)` | 否 |  |  |
| `abilities` | `text` | 是 |  |  |
| `last_used_at` | `timestamp` | 是 | `NULL` |  |
| `expires_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `personal_access_tokens_token_unique` | UNIQUE KEY | `token` |
| `personal_access_tokens_tokenable_type_tokenable_id_index` | KEY | `tokenable_type,tokenable_id` |
| `personal_access_tokens_expires_at_index` | KEY | `expires_at` |

## `product_upstream_bindings`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `product_id` | `bigint unsigned` | 否 |  |  |
| `supplier_plugin_binding_id` | `bigint unsigned` | 否 |  |  |
| `plugin_id` | `bigint unsigned` | 否 |  |  |
| `provider_key` | `varchar(120)` | 否 |  |  |
| `upstream_product_id` | `varchar(120)` | 否 |  |  |
| `upstream_product_snapshot_json` | `json` | 是 | `NULL` |  |
| `option_schema_json` | `json` | 是 | `NULL` |  |
| `provision_policy_json` | `json` | 是 | `NULL` |  |
| `auto_setup` | `tinyint(1)` | 否 | `'0'` |  |
| `status` | `tinyint unsigned` | 否 | `'1'` |  |
| `last_synced_at` | `timestamp` | 是 | `NULL` |  |
| `last_sync_error` | `varchar(500)` | 是 | `NULL` |  |
| `backfill_batch_id` | `varchar(64)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `product_upstream_unique` | UNIQUE KEY | `product_id,supplier_plugin_binding_id,upstream_product_id` |
| `product_upstream_bindings_supplier_plugin_binding_id_foreign` | KEY | `supplier_plugin_binding_id` |
| `product_upstream_product_status_idx` | KEY | `product_id,status` |
| `product_upstream_provider_status_idx` | KEY | `provider_key,status` |
| `product_upstream_plugin_status_idx` | KEY | `plugin_id,status` |
| `product_upstream_backfill_batch_idx` | KEY | `backfill_batch_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `product_upstream_bindings_plugin_id_foreign` | `plugin_id` | `integration_plugins` (`id`) | RESTRICT | 默认 |
| `product_upstream_bindings_product_id_foreign` | `product_id` | `products` (`id`) | RESTRICT | 默认 |
| `product_upstream_bindings_supplier_plugin_binding_id_foreign` | `supplier_plugin_binding_id` | `supplier_plugin_bindings` (`id`) | RESTRICT | 默认 |

## `products`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  | 商品自增主键 |
| `product_group_id` | `bigint unsigned` | 是 | `NULL` | 当前所属商品分组ID |
| `service_type_code` | `varchar(50)` | 是 | `NULL` | 服务类型代码，用于前后端能力分流 |
| `product_type` | `varchar(30)` | 否 |  | 商品类型：vps/dedicated/hosting/domain/other |
| `custom_display_name` | `varchar(190)` | 是 | `NULL` | 自定义展示名称 |
| `remark` | `varchar(255)` | 是 | `NULL` | 商品备注 |
| `pricing` | `json` | 否 |  | 周期价格 JSON，如 monthly/quarterly/annually |
| `setup_fee` | `decimal(12,2)` | 否 | `'0.00'` | 初装费 |
| `config_options` | `json` | 是 | `NULL` | 可选配置项 JSON |
| `purchase_requires` | `json` | 是 | `NULL` | 购买限制 JSON，如实名认证、手机号要求 |
| `stock` | `int` | 否 | `'-1'` | 库存数量，-1 表示不限 |
| `status` | `tinyint` | 否 | `'1'` | 商品状态：0下架 1上架 |
| `sort_order` | `int` | 否 | `'0'` | 排序值，越小越靠前 |
| `auto_setup` | `tinyint` | 否 | `'0'` | 是否自动开通：0手动 1自动 |
| `created_at` | `timestamp` | 是 | `NULL` | 创建时间 |
| `updated_at` | `timestamp` | 是 | `NULL` | 更新时间 |
| `deleted_at` | `timestamp` | 是 | `NULL` | 软删除时间 |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `products_type_status_index` | KEY | `product_type,status` |
| `idx_product_status_groups` | KEY | `status` |
| `products_group_status_sort_id_idx` | KEY | `product_group_id,status,sort_order,id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `products_product_group_fk` | `product_group_id` | `third_product_groups` (`id`) | RESTRICT | 默认 |

## `referral_account_logs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `user_id` | `bigint unsigned` | 否 |  |  |
| `event_type` | `varchar(30)` | 否 |  | reward_frozen\|reward_released\|withdraw_apply\|withdraw_approved\|withdraw_rejected |
| `change_amount` | `decimal(12,2)` | 否 |  | 正=增加 负=减少 |
| `frozen_balance` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `available_balance` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `pending_withdrawal_balance` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `withdrawn_balance` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `remark` | `varchar(255)` | 否 | `''` |  |
| `reference_id` | `bigint unsigned` | 是 | `NULL` |  |
| `reference_type` | `varchar(30)` | 是 | `NULL` |  |
| `operator` | `varchar(50)` | 是 | `NULL` |  |
| `trace_id` | `varchar(64)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `idx_referral_account_user_type` | KEY | `user_id,event_type` |
| `idx_referral_account_related` | KEY | `reference_type,reference_id` |
| `referral_account_logs_created_at_index` | KEY | `created_at` |
| `idx_referral_account_user_created_idx` | KEY | `user_id,created_at,id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_referral_account_logs_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |

## `referral_rewards`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `referrer_user_id` | `bigint unsigned` | 否 |  |  |
| `referred_user_id` | `bigint unsigned` | 否 |  |  |
| `order_id` | `bigint unsigned` | 否 |  |  |
| `invoice_id` | `bigint unsigned` | 是 | `NULL` |  |
| `product_id` | `bigint unsigned` | 是 | `NULL` |  |
| `order_amount` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `reward_rate` | `decimal(5,2)` | 否 | `'0.00'` |  |
| `reward_amount` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `available_at` | `timestamp` | 是 | `NULL` |  |
| `released_at` | `timestamp` | 是 | `NULL` |  |
| `status` | `tinyint` | 否 | `'0'` | 0=冻结中 1=已发放 2=已回退 |
| `operator` | `varchar(50)` | 是 | `NULL` |  |
| `remark` | `varchar(255)` | 是 | `NULL` |  |
| `trace_id` | `varchar(64)` | 是 | `NULL` |  |
| `rewarded_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `referral_rewards_order_id_unique` | UNIQUE KEY | `order_id` |
| `idx_referral_reward_referrer_status` | KEY | `referrer_user_id,status` |
| `idx_referral_reward_referred_status` | KEY | `referred_user_id,status` |
| `referral_rewards_product_id_index` | KEY | `product_id` |
| `referral_rewards_rewarded_at_index` | KEY | `rewarded_at` |
| `referral_rewards_invoice_id_idx` | KEY | `invoice_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_referral_rewards_invoice_id` | `invoice_id` | `invoices` (`id`) | SET NULL | 默认 |
| `fk_stage2_referral_rewards_order_id` | `order_id` | `orders` (`id`) | RESTRICT | 默认 |
| `fk_stage2_referral_rewards_product_id` | `product_id` | `products` (`id`) | SET NULL | 默认 |
| `fk_stage2_referral_rewards_referred_user_id` | `referred_user_id` | `users` (`id`) | RESTRICT | 默认 |
| `fk_stage2_referral_rewards_referrer_user_id` | `referrer_user_id` | `users` (`id`) | RESTRICT | 默认 |

## `referral_withdrawals`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `user_id` | `bigint unsigned` | 否 |  |  |
| `amount` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `method` | `varchar(20)` | 否 | `'alipay'` | balance\|alipay |
| `account_name` | `varchar(80)` | 否 | `''` |  |
| `account_no` | `varchar(120)` | 否 | `''` |  |
| `status` | `tinyint` | 否 | `'0'` | 0=待处理 1=已通过 2=已拒绝 |
| `remark` | `varchar(255)` | 是 | `NULL` |  |
| `operator` | `varchar(50)` | 是 | `NULL` |  |
| `trace_id` | `varchar(64)` | 否 |  |  |
| `processed_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `referral_withdrawals_trace_id_unique` | UNIQUE KEY | `trace_id` |
| `idx_referral_withdraw_user_status` | KEY | `user_id,status` |
| `idx_referral_withdraw_status_created` | KEY | `status,created_at` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_referral_withdrawals_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |

## `roles`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `name` | `varchar(50)` | 否 |  |  |
| `label` | `varchar(100)` | 是 | `NULL` |  |
| `permissions` | `json` | 否 |  | 权限标识数组 |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `roles_name_unique` | UNIQUE KEY | `name` |

## `schedule_run_logs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `task_name` | `varchar(100)` | 否 |  | 任务名称 |
| `status` | `varchar(20)` | 否 | `'success'` | 执行状态: success, failed, skipped |
| `duration_ms` | `int unsigned` | 否 | `'0'` | 执行耗时(毫秒) |
| `summary` | `json` | 是 | `NULL` | 执行摘要数据 |
| `error_msg` | `text` | 是 |  | 错误信息 |
| `started_at` | `timestamp` | 是 | `NULL` | 开始时间 |
| `finished_at` | `timestamp` | 是 | `NULL` | 结束时间 |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `schedule_run_logs_task_name_index` | KEY | `task_name` |
| `schedule_run_logs_status_index` | KEY | `status` |
| `schedule_run_logs_created_at_index` | KEY | `created_at` |
| `schedule_run_logs_task_name_created_at_index` | KEY | `task_name,created_at` |

## `schedule_task_runs`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `schedule_tick_id` | `bigint unsigned` | 是 | `NULL` |  |
| `task_key` | `varchar(120)` | 否 |  |  |
| `task_name` | `varchar(160)` | 否 |  |  |
| `rule_description` | `varchar(160)` | 是 | `NULL` |  |
| `source` | `varchar(40)` | 否 | `'heartbeat'` |  |
| `queue` | `varchar(80)` | 是 | `NULL` |  |
| `status` | `varchar(30)` | 否 | `'queued'` |  |
| `duration_ms` | `int unsigned` | 是 | `NULL` |  |
| `summary` | `json` | 是 | `NULL` |  |
| `error_msg` | `text` | 是 |  |  |
| `queued_at` | `timestamp` | 是 | `NULL` |  |
| `started_at` | `timestamp` | 是 | `NULL` |  |
| `finished_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `schedule_task_runs_tick_task_source_unique` | UNIQUE KEY | `schedule_tick_id,task_key,source` |
| `schedule_task_runs_task_key_created_at_index` | KEY | `task_key,created_at` |
| `schedule_task_runs_status_created_at_index` | KEY | `status,created_at` |
| `schedule_task_runs_source_created_at_index` | KEY | `source,created_at` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `schedule_task_runs_schedule_tick_id_foreign` | `schedule_tick_id` | `schedule_ticks` (`id`) | CASCADE | 默认 |

## `schedule_ticks`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `slot_started_at` | `timestamp` | 否 |  |  |
| `global_number` | `bigint unsigned` | 否 |  |  |
| `daily_index` | `tinyint unsigned` | 否 |  |  |
| `triggered_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `schedule_ticks_slot_started_at_unique` | UNIQUE KEY | `slot_started_at` |
| `schedule_ticks_global_number_unique` | UNIQUE KEY | `global_number` |
| `schedule_ticks_daily_index_index` | KEY | `daily_index` |

## `second_product_groups`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `first_product_group_id` | `bigint unsigned` | 否 |  | → first_product_groups.id |
| `name` | `varchar(100)` | 否 |  | 名称 |
| `slug` | `varchar(100)` | 是 | `NULL` | URL标识 |
| `description` | `varchar(255)` | 是 | `NULL` | 分组说明 |
| `banner_image` | `varchar(255)` | 是 | `NULL` | 横幅图 |
| `sort_order` | `int` | 否 | `'0'` | 排序 |
| `is_visible` | `tinyint unsigned` | 否 | `'1'` | 前台可见 |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `uq_second_first_slug` | UNIQUE KEY | `first_product_group_id,slug` |
| `idx_second_first_visible_sort` | KEY | `first_product_group_id,is_visible,sort_order` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_second_first_group` | `first_product_group_id` | `first_product_groups` (`id`) | RESTRICT | 默认 |

## `service_connection_snapshots`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `service_id` | `bigint unsigned` | 否 |  |  |
| `service_upstream_binding_id` | `bigint unsigned` | 是 | `NULL` |  |
| `plugin_id` | `bigint unsigned` | 是 | `NULL` |  |
| `provider_key` | `varchar(120)` | 是 | `NULL` |  |
| `connection_type` | `varchar(60)` | 否 | `'default'` |  |
| `hostname` | `varchar(255)` | 是 | `NULL` |  |
| `ip_address` | `varchar(120)` | 是 | `NULL` |  |
| `port` | `int unsigned` | 是 | `NULL` |  |
| `connection_json` | `json` | 是 | `NULL` |  |
| `secret_json` | `longtext` | 是 |  |  |
| `has_secret_json` | `json` | 是 | `NULL` |  |
| `checked_at` | `timestamp` | 是 | `NULL` |  |
| `backfill_batch_id` | `varchar(64)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `service_connection_service_type_unique` | UNIQUE KEY | `service_id,connection_type` |
| `service_connection_snapshots_service_upstream_binding_id_foreign` | KEY | `service_upstream_binding_id` |
| `service_connection_plugin_checked_idx` | KEY | `plugin_id,checked_at` |
| `service_connection_provider_type_idx` | KEY | `provider_key,connection_type` |
| `service_connection_backfill_batch_idx` | KEY | `backfill_batch_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `service_connection_snapshots_plugin_id_foreign` | `plugin_id` | `integration_plugins` (`id`) | SET NULL | 默认 |
| `service_connection_snapshots_service_id_foreign` | `service_id` | `services` (`id`) | CASCADE | 默认 |
| `service_connection_snapshots_service_upstream_binding_id_foreign` | `service_upstream_binding_id` | `service_upstream_bindings` (`id`) | SET NULL | 默认 |

## `service_provision_attempts`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `service_id` | `bigint unsigned` | 是 | `NULL` |  |
| `service_upstream_binding_id` | `bigint unsigned` | 是 | `NULL` |  |
| `plugin_id` | `bigint unsigned` | 是 | `NULL` |  |
| `provider_key` | `varchar(120)` | 是 | `NULL` |  |
| `action` | `varchar(80)` | 否 |  |  |
| `attempt_status` | `varchar(30)` | 否 |  |  |
| `trace_id` | `varchar(64)` | 是 | `NULL` |  |
| `request_meta_json` | `json` | 是 | `NULL` |  |
| `response_meta_json` | `json` | 是 | `NULL` |  |
| `error_code` | `varchar(80)` | 是 | `NULL` |  |
| `error_message` | `varchar(500)` | 是 | `NULL` |  |
| `attempted_at` | `timestamp` | 是 | `NULL` |  |
| `backfill_batch_id` | `varchar(64)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `service_provision_attempts_service_upstream_binding_id_foreign` | KEY | `service_upstream_binding_id` |
| `service_attempt_service_action_idx` | KEY | `service_id,action,attempted_at` |
| `service_attempt_plugin_status_idx` | KEY | `plugin_id,attempt_status,attempted_at` |
| `service_attempt_trace_idx` | KEY | `trace_id` |
| `service_attempt_backfill_batch_idx` | KEY | `backfill_batch_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `service_provision_attempts_plugin_id_foreign` | `plugin_id` | `integration_plugins` (`id`) | SET NULL | 默认 |
| `service_provision_attempts_service_id_foreign` | `service_id` | `services` (`id`) | SET NULL | 默认 |
| `service_provision_attempts_service_upstream_binding_id_foreign` | `service_upstream_binding_id` | `service_upstream_bindings` (`id`) | SET NULL | 默认 |

## `service_runtime_snapshots`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `service_id` | `bigint unsigned` | 否 |  |  |
| `service_upstream_binding_id` | `bigint unsigned` | 是 | `NULL` |  |
| `plugin_id` | `bigint unsigned` | 是 | `NULL` |  |
| `provider_key` | `varchar(120)` | 是 | `NULL` |  |
| `status_key` | `varchar(60)` | 是 | `NULL` |  |
| `status_text` | `varchar(120)` | 是 | `NULL` |  |
| `resource_json` | `json` | 是 | `NULL` |  |
| `metrics_json` | `json` | 是 | `NULL` |  |
| `snapshot_json` | `json` | 是 | `NULL` |  |
| `synced_at` | `timestamp` | 是 | `NULL` |  |
| `backfill_batch_id` | `varchar(64)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `service_runtime_service_unique` | UNIQUE KEY | `service_id` |
| `service_runtime_snapshots_service_upstream_binding_id_foreign` | KEY | `service_upstream_binding_id` |
| `service_runtime_plugin_synced_idx` | KEY | `plugin_id,synced_at` |
| `service_runtime_provider_status_idx` | KEY | `provider_key,status_key` |
| `service_runtime_backfill_batch_idx` | KEY | `backfill_batch_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `service_runtime_snapshots_plugin_id_foreign` | `plugin_id` | `integration_plugins` (`id`) | SET NULL | 默认 |
| `service_runtime_snapshots_service_id_foreign` | `service_id` | `services` (`id`) | CASCADE | 默认 |
| `service_runtime_snapshots_service_upstream_binding_id_foreign` | `service_upstream_binding_id` | `service_upstream_bindings` (`id`) | SET NULL | 默认 |

## `service_upstream_bindings`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `service_id` | `bigint unsigned` | 否 |  |  |
| `product_upstream_binding_id` | `bigint unsigned` | 是 | `NULL` |  |
| `supplier_plugin_binding_id` | `bigint unsigned` | 是 | `NULL` |  |
| `plugin_id` | `bigint unsigned` | 否 |  |  |
| `provider_key` | `varchar(120)` | 否 |  |  |
| `upstream_service_id` | `varchar(120)` | 否 |  |  |
| `upstream_account_id` | `varchar(120)` | 是 | `NULL` |  |
| `runtime_snapshot_json` | `json` | 是 | `NULL` |  |
| `connection_snapshot_json` | `json` | 是 | `NULL` |  |
| `status_snapshot` | `varchar(60)` | 是 | `NULL` |  |
| `last_synced_at` | `timestamp` | 是 | `NULL` |  |
| `last_sync_error` | `varchar(500)` | 是 | `NULL` |  |
| `backfill_batch_id` | `varchar(64)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `service_upstream_unique` | UNIQUE KEY | `service_id,plugin_id,upstream_service_id` |
| `service_upstream_bindings_product_upstream_binding_id_foreign` | KEY | `product_upstream_binding_id` |
| `service_upstream_bindings_supplier_plugin_binding_id_foreign` | KEY | `supplier_plugin_binding_id` |
| `service_upstream_service_idx` | KEY | `service_id` |
| `service_upstream_provider_status_idx` | KEY | `provider_key,status_snapshot` |
| `service_upstream_plugin_sync_idx` | KEY | `plugin_id,last_synced_at` |
| `service_upstream_backfill_batch_idx` | KEY | `backfill_batch_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `service_upstream_bindings_plugin_id_foreign` | `plugin_id` | `integration_plugins` (`id`) | RESTRICT | 默认 |
| `service_upstream_bindings_product_upstream_binding_id_foreign` | `product_upstream_binding_id` | `product_upstream_bindings` (`id`) | SET NULL | 默认 |
| `service_upstream_bindings_service_id_foreign` | `service_id` | `services` (`id`) | RESTRICT | 默认 |
| `service_upstream_bindings_supplier_plugin_binding_id_foreign` | `supplier_plugin_binding_id` | `supplier_plugin_bindings` (`id`) | SET NULL | 默认 |

## `services`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  | 服务实例自增主键 |
| `user_id` | `bigint unsigned` | 否 |  | 所属用户ID |
| `product_id` | `bigint unsigned` | 否 |  | 关联商品ID |
| `order_id` | `bigint unsigned` | 是 | `NULL` | 内部订单/开通投影ID，仅用于流程追踪 |
| `invoice_id` | `bigint unsigned` | 是 | `NULL` | 最近一次关联账单ID |
| `name` | `varchar(200)` | 否 | `''` | 服务自定义名称 |
| `domain` | `varchar(200)` | 否 | `''` | 服务域名或主机名 |
| `billing_cycle` | `varchar(20)` | 否 |  | 计费周期 |
| `amount` | `decimal(12,2)` | 否 |  | 服务续费/购买金额 |
| `locked_pricing` | `json` | 是 | `NULL` | 锁定续费定价 JSON，null 表示跟随商品定价 |
| `status` | `tinyint` | 否 | `'0'` | 服务状态：0待开通 1运行中 2已暂停 3已到期 4已取消 |
| `provision_data` | `json` | 是 | `NULL` | 开通和上游实例数据 JSON |
| `expires_at` | `timestamp` | 是 | `NULL` | 服务到期时间 |
| `auto_renew` | `tinyint` | 否 | `'1'` | 是否自动续费：0关闭 1开启 |
| `suspended_reason` | `varchar(200)` | 是 | `NULL` | 暂停原因 |
| `created_at` | `timestamp` | 是 | `NULL` | 创建时间 |
| `updated_at` | `timestamp` | 是 | `NULL` | 更新时间 |
| `remark` | `varchar(255)` | 是 | `NULL` | 服务备注 |
| `operator` | `varchar(50)` | 是 | `NULL` | 操作人快照 |
| `trace_id` | `varchar(64)` | 是 | `NULL` | 链路追踪号 |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `services_user_id_index` | KEY | `user_id` |
| `services_expires_at_index` | KEY | `expires_at` |
| `services_user_status_id_idx` | KEY | `user_id,status,id` |
| `services_status_expires_at_id_idx` | KEY | `status,expires_at,id` |
| `services_trace_id_idx` | KEY | `trace_id` |
| `services_product_id_idx` | KEY | `product_id` |
| `services_order_id_idx` | KEY | `order_id` |
| `services_invoice_id_idx` | KEY | `invoice_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_services_invoice_id` | `invoice_id` | `invoices` (`id`) | SET NULL | 默认 |
| `fk_services_product_id` | `product_id` | `products` (`id`) | RESTRICT | 默认 |
| `fk_services_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |
| `fk_stage2_services_order_id` | `order_id` | `orders` (`id`) | SET NULL | 默认 |

## `sessions`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `varchar(255)` | 否 |  |  |
| `user_id` | `bigint unsigned` | 是 | `NULL` |  |
| `ip_address` | `varchar(45)` | 是 | `NULL` |  |
| `user_agent` | `text` | 是 |  |  |
| `payload` | `longtext` | 否 |  |  |
| `last_activity` | `int` | 否 |  |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `sessions_user_id_index` | KEY | `user_id` |
| `sessions_last_activity_index` | KEY | `last_activity` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_sessions_user_id` | `user_id` | `users` (`id`) | CASCADE | 默认 |

## `settings`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `group_key` | `varchar(50)` | 否 |  |  |
| `item_key` | `varchar(100)` | 否 |  |  |
| `item_value` | `text` | 是 |  |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `settings_group_key_unique` | UNIQUE KEY | `group_key,item_key` |

## `supplier_plugin_bindings`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `supplier_id` | `bigint unsigned` | 否 |  |  |
| `plugin_id` | `bigint unsigned` | 否 |  |  |
| `provider_key` | `varchar(120)` | 否 |  |  |
| `environment` | `varchar(30)` | 否 | `'production'` |  |
| `status` | `tinyint unsigned` | 否 | `'1'` |  |
| `priority` | `int` | 否 | `'0'` |  |
| `base_url` | `varchar(255)` | 是 | `NULL` |  |
| `account_name` | `varchar(120)` | 是 | `NULL` |  |
| `config_json` | `json` | 是 | `NULL` |  |
| `secret_json` | `longtext` | 是 |  |  |
| `has_secret_json` | `json` | 是 | `NULL` |  |
| `last_checked_at` | `timestamp` | 是 | `NULL` |  |
| `last_check_status` | `varchar(30)` | 是 | `NULL` |  |
| `last_check_error` | `varchar(500)` | 是 | `NULL` |  |
| `created_by` | `bigint unsigned` | 是 | `NULL` |  |
| `updated_by` | `bigint unsigned` | 是 | `NULL` |  |
| `backfill_batch_id` | `varchar(64)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `supplier_plugin_unique` | UNIQUE KEY | `supplier_id,plugin_id,environment` |
| `supplier_plugin_provider_status_idx` | KEY | `provider_key,status` |
| `supplier_plugin_plugin_status_idx` | KEY | `plugin_id,status` |
| `supplier_plugin_backfill_batch_idx` | KEY | `backfill_batch_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `supplier_plugin_bindings_plugin_id_foreign` | `plugin_id` | `integration_plugins` (`id`) | RESTRICT | 默认 |
| `supplier_plugin_bindings_supplier_id_foreign` | `supplier_id` | `suppliers` (`id`) | RESTRICT | 默认 |

## `suppliers`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `name` | `varchar(120)` | 否 |  |  |
| `code` | `varchar(50)` | 否 |  |  |
| `contact_name` | `varchar(60)` | 是 | `NULL` |  |
| `contact_phone` | `varchar(30)` | 是 | `NULL` |  |
| `contact_email` | `varchar(100)` | 是 | `NULL` |  |
| `website` | `varchar(255)` | 是 | `NULL` |  |
| `status` | `tinyint` | 否 | `'1'` | 0=停用 1=启用 |
| `sort_order` | `int` | 否 | `'0'` |  |
| `notes` | `text` | 是 |  |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `suppliers_code_unique` | UNIQUE KEY | `code` |
| `suppliers_status_sort_order_index` | KEY | `status,sort_order` |

## `third_product_groups`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `second_product_group_id` | `bigint unsigned` | 否 |  | → second_product_groups.id |
| `name` | `varchar(100)` | 否 |  | 名称 |
| `slug` | `varchar(100)` | 是 | `NULL` | URL标识 |
| `description` | `varchar(255)` | 是 | `NULL` | 分组说明 |
| `sort_order` | `int` | 否 | `'0'` | 排序 |
| `is_visible` | `tinyint unsigned` | 否 | `'1'` | 前台可见 |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `uq_third_second_slug` | UNIQUE KEY | `second_product_group_id,slug` |
| `idx_third_second_visible_sort` | KEY | `second_product_group_id,is_visible,sort_order` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_third_second_group` | `second_product_group_id` | `second_product_groups` (`id`) | RESTRICT | 默认 |

## `ticket_replies`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `ticket_id` | `bigint unsigned` | 否 |  |  |
| `user_id` | `bigint unsigned` | 否 |  |  |
| `content` | `text` | 否 |  |  |
| `is_staff` | `tinyint` | 否 | `'0'` |  |
| `attachments` | `json` | 是 | `NULL` |  |
| `quote_reply_id` | `bigint unsigned` | 是 | `NULL` |  |
| `recalled_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `ticket_replies_ticket_created_id_idx` | KEY | `ticket_id,created_at,id` |
| `idx_stage2_ticket_replies_quote_reply_id` | KEY | `quote_reply_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_ticket_replies_quote_reply_id` | `quote_reply_id` | `ticket_replies` (`id`) | SET NULL | 默认 |
| `fk_ticket_replies_ticket_id` | `ticket_id` | `tickets` (`id`) | CASCADE | 默认 |

## `tickets`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `user_id` | `bigint unsigned` | 否 |  |  |
| `department` | `varchar(30)` | 否 | `'support'` |  |
| `subject` | `varchar(200)` | 否 |  |  |
| `priority` | `tinyint` | 否 | `'1'` | 1=低 2=中 3=高 4=紧急 |
| `status` | `tinyint` | 否 | `'0'` | 0=开启 1=客户回复 2=员工回复 3=已关闭 |
| `service_id` | `bigint unsigned` | 是 | `NULL` |  |
| `assignee_id` | `bigint unsigned` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |
| `close_reason` | `varchar(20)` | 是 | `NULL` | admin, client, auto |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `tickets_user_id_index` | KEY | `user_id` |
| `tickets_user_status_updated_at_idx` | KEY | `user_id,status,updated_at` |
| `tickets_status_updated_at_idx` | KEY | `status,updated_at` |
| `tickets_user_updated_at_idx` | KEY | `user_id,updated_at,id` |
| `tickets_service_id_idx` | KEY | `service_id` |
| `idx_stage2_tickets_assignee_id` | KEY | `assignee_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_tickets_assignee_id` | `assignee_id` | `admin_users` (`id`) | SET NULL | 默认 |
| `fk_stage2_tickets_service_id` | `service_id` | `services` (`id`) | SET NULL | 默认 |
| `fk_tickets_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |

## `user_accounts`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `user_id` | `bigint unsigned` | 否 |  | 用户ID，同时作为账户主键 |
| `cash_balance` | `decimal(12,2)` | 否 | `'0.00'` | 现金余额 |
| `credit_limit` | `decimal(12,2)` | 否 | `'0.00'` | 授信额度 |
| `referral_frozen_balance` | `decimal(12,2)` | 否 | `'0.00'` | 冻结中的推荐奖励余额 |
| `referral_available_balance` | `decimal(12,2)` | 否 | `'0.00'` | 可用推荐奖励余额 |
| `referral_pending_withdrawal_balance` | `decimal(12,2)` | 否 | `'0.00'` | 提现审核中的推荐奖励余额 |
| `referral_withdrawn_balance` | `decimal(12,2)` | 否 | `'0.00'` | 已提现推荐奖励累计金额 |
| `version` | `int unsigned` | 否 | `'0'` | 乐观锁版本号 |
| `created_at` | `timestamp` | 是 | `NULL` | 创建时间 |
| `updated_at` | `timestamp` | 是 | `NULL` | 更新时间 |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `user_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_user_accounts_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |

## `user_coupons`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `uid` | `varchar(32)` | 是 | `NULL` |  |
| `coupon_id` | `bigint unsigned` | 否 |  |  |
| `user_id` | `bigint unsigned` | 否 |  |  |
| `receive_type` | `varchar(20)` | 否 | `'claim'` |  |
| `status` | `tinyint` | 否 | `'1'` |  |
| `claimed_at` | `timestamp` | 是 | `NULL` |  |
| `used_at` | `timestamp` | 是 | `NULL` |  |
| `revoked_at` | `timestamp` | 是 | `NULL` |  |
| `reserved_until` | `timestamp` | 是 | `NULL` |  |
| `granted_at` | `timestamp` | 是 | `NULL` |  |
| `last_used_at` | `timestamp` | 是 | `NULL` |  |
| `remark` | `varchar(255)` | 是 | `NULL` |  |
| `operator` | `varchar(100)` | 是 | `NULL` |  |
| `trace_id` | `varchar(100)` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `user_coupons_coupon_user_unique` | UNIQUE KEY | `coupon_id,user_id` |
| `user_coupons_uid_unique` | UNIQUE KEY | `uid` |
| `user_coupons_user_status_idx` | KEY | `user_id,status` |
| `user_coupons_coupon_status_idx` | KEY | `coupon_id,status` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_user_coupons_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |
| `fk_user_coupons_coupon_id` | `coupon_id` | `coupons` (`id`) | RESTRICT | 默认 |

## `user_notifications`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `user_id` | `bigint unsigned` | 否 |  |  |
| `type` | `varchar(50)` | 否 |  | 消息类型：order_paid/service_renew_reminder/service_expire_reminder 等 |
| `title` | `varchar(191)` | 否 |  |  |
| `content` | `text` | 是 |  |  |
| `link` | `varchar(255)` | 是 | `NULL` | 点击跳转的前端路由 |
| `data` | `json` | 是 | `NULL` | 附加业务数据 |
| `read_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `user_notifications_user_id_read_at_index` | KEY | `user_id,read_at` |
| `user_notifications_user_id_created_at_index` | KEY | `user_id,created_at` |
| `user_notifications_type_index` | KEY | `type` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_user_notifications_user_id` | `user_id` | `users` (`id`) | CASCADE | 默认 |

## `users`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `email` | `varchar(100)` | 是 | `NULL` |  |
| `password` | `varchar(255)` | 否 |  |  |
| `nickname` | `varchar(50)` | 否 | `''` |  |
| `phone` | `varchar(20)` | 是 | `NULL` |  |
| `company` | `varchar(100)` | 否 | `''` |  |
| `qq` | `varchar(30)` | 否 | `''` |  |
| `alipay_real_name` | `varchar(80)` | 否 | `''` |  |
| `alipay_account` | `varchar(20)` | 否 | `''` |  |
| `referral_code` | `varchar(24)` | 是 | `NULL` |  |
| `referrer_user_id` | `bigint unsigned` | 是 | `NULL` |  |
| `member_level_id` | `bigint unsigned` | 是 | `NULL` |  |
| `total_sales_amount` | `decimal(12,2)` | 否 | `'0.00'` |  |
| `referred_at` | `timestamp` | 是 | `NULL` |  |
| `status` | `tinyint` | 否 | `'1'` | 0=禁用 1=正常 |
| `login_email_alert` | `tinyint` | 否 | `'1'` | 登录邮件提醒 0关闭 1开启 |
| `login_notify` | `tinyint(1)` | 否 | `'1'` | 账号登录提醒 0关闭 1开启 |
| `login_location_alert` | `tinyint(1)` | 否 | `'1'` | 异地登录提醒 0关闭 1开启 |
| `password_change_alert` | `tinyint(1)` | 否 | `'1'` | 密码变更提醒 0关闭 1开启 |
| `phone_change_alert` | `tinyint(1)` | 否 | `'1'` | 手机号变更提醒 0关闭 1开启 |
| `email_change_alert` | `tinyint(1)` | 否 | `'1'` | 邮箱变更提醒 0关闭 1开启 |
| `marketing_alert` | `tinyint(1)` | 否 | `'0'` | 营销提醒接收 0关闭 1开启 |
| `is_verified` | `tinyint` | 否 | `'0'` | 0=未认证 1=已认证 |
| `real_name` | `varchar(50)` | 否 | `''` | 真实姓名 |
| `id_card` | `varchar(512)` | 否 | `''` |  |
| `verification_status` | `tinyint` | 否 | `'0'` | 0=未认证 1=认证中 2=已认证 3=认证失败 |
| `verification_message` | `varchar(255)` | 否 | `''` | 实名认证状态描述 |
| `verification_certify_id` | `varchar(100)` | 是 | `NULL` | 实名认证平台 certify_id |
| `verified_at` | `timestamp` | 是 | `NULL` | 实名认证通过时间 |
| `last_login_ip` | `varchar(45)` | 是 | `NULL` |  |
| `last_login_at` | `timestamp` | 是 | `NULL` |  |
| `admin_note` | `text` | 是 |  |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |
| `deleted_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `users_phone_unique` | UNIQUE KEY | `phone` |
| `users_email_unique` | UNIQUE KEY | `email` |
| `users_referral_code_unique` | UNIQUE KEY | `referral_code` |
| `users_status_id_idx` | KEY | `status,id` |
| `users_verification_mix_idx` | KEY | `is_verified,verification_status,id` |
| `users_verification_status_id_idx` | KEY | `verification_status,id` |
| `users_created_at_idx` | KEY | `created_at` |
| `users_verification_certify_id_idx` | KEY | `verification_certify_id` |
| `users_login_email_alert_index` | KEY | `login_email_alert` |
| `users_referrer_user_id_index` | KEY | `referrer_user_id` |
| `users_member_level_id_index` | KEY | `member_level_id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_users_member_level_id` | `member_level_id` | `member_levels` (`id`) | SET NULL | 默认 |
| `fk_stage2_users_referrer_user_id` | `referrer_user_id` | `users` (`id`) | SET NULL | 默认 |

## `verification_histories`

引擎：`InnoDB`

### 字段

| 字段 | 类型 | 可空 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` | 否 |  |  |
| `user_id` | `bigint unsigned` | 否 |  |  |
| `real_name` | `varchar(50)` | 否 | `''` |  |
| `id_card` | `varchar(512)` | 否 | `''` |  |
| `verification_status` | `tinyint` | 否 | `'1'` | 1=认证中 2=已认证 3=认证失败 |
| `verification_message` | `varchar(255)` | 否 | `''` |  |
| `verification_certify_id` | `varchar(100)` | 是 | `NULL` |  |
| `verification_biz_code` | `varchar(30)` | 否 | `'FACE'` |  |
| `verification_type` | `varchar(20)` | 否 | `'personal'` |  |
| `submitted_at` | `timestamp` | 否 | `CURRENT_TIMESTAMP` |  |
| `completed_at` | `timestamp` | 是 | `NULL` |  |
| `created_at` | `timestamp` | 是 | `NULL` |  |
| `updated_at` | `timestamp` | 是 | `NULL` |  |

### 索引

| 名称 | 类型 | 字段 |
| --- | --- | --- |
| `PRIMARY` | PRIMARY KEY | `id` |
| `verification_histories_user_id_submitted_at_index` | KEY | `user_id,submitted_at` |
| `verification_histories_verification_certify_id_index` | KEY | `verification_certify_id` |
| `verification_histories_user_id_id_idx` | KEY | `user_id,id` |

### 外键

| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |
| --- | --- | --- | --- | --- |
| `fk_stage2_verification_histories_user_id` | `user_id` | `users` (`id`) | RESTRICT | 默认 |
