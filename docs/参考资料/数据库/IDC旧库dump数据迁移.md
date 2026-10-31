---
status: current
updated: 2026-10-31
owner: backend-data
---

# IDC 旧库 dump 到新库的数据迁移（upsert 保留式）

本文记录把旧版本数据库转储（下文称“旧 dump”）中的数据迁移到**已是当前结构**的新库的固定流程与判定口径。对应脚本 [migrate_idc_dump_upsert.py](../../../backend/scripts/migrate_idc_dump_upsert.py)。

## 文档摘要

- 迁移只做 DML：不建表、不删表、不改字段/索引/约束/外键，目标库结构在执行前后逐字节一致。
- 算法是“按主键与业务唯一键 upsert 补齐”，不是清空重灌：与旧 dump 同 id 的行会被更新，新库多出的行保持原样。
- 可重复执行：同一份 dump 连跑两次，行数与内容均不变化；实测第二次跑完 53 张表 before_count 与 after_count 全部相等。
- 大表按 500 行 / 2 MiB 双阈值分批，每批独立事务提交，不产生长事务与整表锁。
- 唯一需要外部动作的环节是 19 行可空外键：空库靶场上 `finance_ledger_entries` 还没有父行，脚本先把该列置 NULL 并登记，在目标库跑 `php artisan finance:rebuild --execute` 后重跑本脚本即回填。此流程已在 `idc_test` 上实测闭环，回填后 `fk_unresolved = 0`。

## 与“清空重建”流程的区别

| 维度 | 本文的 upsert 脚本 | [本地 IDC 数据迁移流程](本地IDC数据迁移流程.md) 的清空重建脚本 |
| --- | --- | --- |
| 对目标库既有数据 | 保留并逐行合并 | 清空后重建业务数据 |
| 适用场景 | 目标库已有线上数据，需要补齐旧库新增或变更的行 | 本地新装库、需要一份干净的 dump 快照 |
| 结构变化 | 完全不改 | 配合 `install_db.py --reset` 重放 migrations |
| 可否反复执行 | 是，幂等 | 每次都会清空 |

两套脚本并存，按目标库现状选择；**目标库已有业务数据时只能用本文的 upsert 脚本**。

## 前置条件

1. 目标库结构已是最新版本（`php artisan db:upgrade-status` 无待执行迁移）。
2. `backend/.env` 中的 `DB_CONNECTION` 为 `mysql` 或 `mariadb`，且 `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` 指向目标库。脚本只从该文件读凭据，不接受命令行传密码，也不会把密码写进报告或日志。
3. 目标库账号拥有目标库的 `SELECT`/`INSERT`/`UPDATE` 权限即可（脚本不执行任何 DDL）。
4. 已完整备份（见“回退方案与备份范围”）。
5. 本地 Python 3 具备 `pymysql`，dump 文件可读。

## 执行方式

在仓库根目录执行，先做只读演练：

```bat
set PYTHONIOENCODING=utf-8 && python backend\scripts\migrate_idc_dump_upsert.py --describe-structure
set PYTHONIOENCODING=utf-8 && python backend\scripts\migrate_idc_dump_upsert.py --dry-run
```

`--describe-structure` 只比对结构并写 `structure_diff.json`；`--dry-run` 解析并与目标库比对但不写任何行，输出“表级处理结论 / 逐表待写入 / 校验结论”三段报告。

确认无误后正式执行。**写入模式必须显式给 `--database`**：省略时脚本会落到 `backend/.env` 的 `DB_DATABASE`（通常是正式库），因此在写入模式下直接拒绝执行。

```bat
set PYTHONIOENCODING=utf-8 && python backend\scripts\migrate_idc_dump_upsert.py --database idc_test
```

若 `--database` 与 `backend/.env` 的 `DB_DATABASE` 同名（即目标就是正式库），脚本还会再拦一道，必须显式追加 `--allow-production` 才继续：

```bat
set PYTHONIOENCODING=utf-8 && python backend\scripts\migrate_idc_dump_upsert.py --database idc --allow-production
```

两道闸门只对写入模式生效；`--describe-structure`、`--dry-run`、`--verify-only` 三个只读模式可以直接对着 `.env` 的库运行。

闸门在解析 dump **之前**执行：本机解析一次要 20~30 秒，先解析再拒绝只会让误用者白等，也容易误判成"已经在跑了"。

常用参数：

| 参数 | 默认 | 用途 |
| --- | --- | --- |
| `--dump` | 仓库根 `idc_2026-10-26_02-28-18_mysql_data_wWKMz.sql` | 指定旧 dump |
| `--env-file` | `backend/.env` | 凭据来源 |
| `--database` | 无（写入模式必填） | 目标库名；只读模式下缺省取 `.env` 的 `DB_DATABASE` |
| `--allow-production` | 关闭 | 目标库与 `.env` 的 `DB_DATABASE` 同名时仍需写入，必须显式追加 |
| `--batch-rows` / `--batch-bytes` | 500 / 2 MiB | 单批上限，任一先到即提交 |
| `--conflict` | `overwrite` | `overwrite` 用旧 dump 覆盖同主键行；`keep-newer` 仅在目标行 `updated_at` 更新时保留目标值 |
| `--on-overflow` | `fail` | 源值超目标列长度时中止；`truncate` 则截断写入并计入 `overflow_handled` |
| `--fk-rounds` | 20 | 外键父行未就绪的行最多补写几轮 |
| `--tables` / `--exclude` | 空 | 只迁或排除指定表（逗号分隔） |
| `--merge-operation-logs` | 关闭 | 把旧库独有 `operation_logs` 并入 `activity_logs` |
| `--sample-size` | 5 | 每表抽样核对行数 |
| `--unique-guard-max-rows` | 50000 | 唯一键撞键检测的分母上限，超限则跳过该表检测 |
| `--verify-only` | 关闭 | 只读复核：比对主键集合与抽样值，不写库、不回填外键 |
| `--report-dir` | 无 | 精确指定报告目录 |
| `--report-root` | 系统临时目录 | `--report-dir` 缺省时在此外建 `idc_migrate_*` |
| `--keep-spool` | 关闭 | 保留解析中间文件（默认结束时删除） |
| `--quiet` | 关闭 | 只输出结论 |

三个模式开关（`--describe-structure`/`--dry-run`/`--verify-only`）互斥，不能同时给。

退出码：`0` 校验通过；`1` 有失败行或校验不通过；`2` 参数或前置条件错误；`3` 未预期异常（已提交批次保持生效）；`130` 手动中断（已提交批次保持生效，可直接重跑续做）。

脚本的临时解析文件写在系统临时目录（`tempfile.mkdtemp(prefix='idc_spool_')`），不落仓库，结束时自动清理；解析中途被截断会直接报错而不是静默少读。

## 新旧结构差异

旧 dump 58 张表，目标库 79 张表，两侧同名 56 张，目标库 114 个外键。

| 类别 | 数量 | 处理 |
| --- | --- | --- |
| 同名表 | 56 | 逐表比对字段后迁移 |
| 仅旧 dump 有 | 2（`operation_logs`、`product_groups`） | `product_groups` 按层级拆入三张分类表；`operation_logs` 默认跳过，可选用 `--merge-operation-logs` 并入 `activity_logs` |
| 仅新库有 | 23 | 不迁移，保持现状（多为新库新增的派生或账本表，如 `finance_*`、`marketing_product_groups`、`system_*`） |
| 两侧都有但字段有差异 | 17 | 见下列丢弃与补齐清单 |

### 旧字段在新结构中没有对应（丢弃）

| 表 | 丢弃字段 | 非空行数 | 原因与影响 |
| --- | --- | --- | --- |
| `integration_plugin_bindings` | `backfill_batch_id` | 9 | 旧库回填批次的临时标记，新库不再有回填批次概念 |
| `product_upstream_bindings` | `backfill_batch_id` | 131 | 同上 |
| `service_connection_snapshots` | `backfill_batch_id` | 149 | 同上 |
| `service_provision_attempts` | `backfill_batch_id` | 199 | 同上 |
| `service_runtime_snapshots` | `backfill_batch_id` | 149 | 同上 |
| `service_upstream_bindings` | `backfill_batch_id` | 149 | 同上 |
| `supplier_plugin_bindings` | `backfill_batch_id` | 2 | 同上 |
| `invoices` | `deleted_at` | 5 | 新库该表无软删除列 |
| `orders` | `deleted_at` | 5 | 同上 |
| `payments` | `deleted_at` | 0 | 同上（dump 中无非空值） |
| `services` | `deleted_at` | 8 | 同上 |

**软删除状态会丢失，需要业务确认**：新库在迁移 `2026_10_25_110100_remove_soft_deletes_from_trade_tables.php` 里把 `services`/`invoices`/`orders`/`payments` 的 `deleted_at` 整列删除（裁决理由是该四表“删除入口为状态机终态”，列无写入方）。旧库这 18 行标记为已删除，迁移后 `deleted_at` 无落点，行会以“未删除”的形态出现在新库里。

处置清单（`deleted_at` 值均为 2026-07-31 16:04~16:07，即同一批清理动作）：

| 表 | 主键 | 旧库 `status` |
| --- | --- | --- |
| `services` | 308、309、311、312、313 | 1 |
| `services` | 327、337 | 0 |
| `services` | 348 | 1 |
| `invoices` | 3015、3017、3020、3026、3031 | 1 |
| `orders` | 3082、3083、3086、3089、3091 | 1 |

对照生产库 `idc`：`invoices`/`orders` 这 10 行都还在且 `status=1`；`services` 的 308~313 在 `idc` 里也在（`status=1`），而 327/337/348 三行是 dump 相对 `idc` 新增的行（`idc` 的 `services` 最大 id 是 324）。

脚本**不做任何自动推断**——把 `status` 改成某个“已取消”取值属于猜测业务含义，且新库终态取值与旧库 `status` 编码的对应关系本脚本无从确认。执行人需按上表逐行确认：要么接受这些行在新库显示为有效（并在业务层补一次删除操作），要么由业务侧给出确定的终态取值后手工 `UPDATE`。

### 新字段在旧结构中没有来源（补齐）

| 表 | 新字段 | 补齐方式 |
| --- | --- | --- |
| `activity_logs` | `context_status`、`context_request_id` | 均为 STORED 生成列，由数据库按行自动计算，脚本不写 |
| `admin_users` | `mfa_secret`、`mfa_enabled_at`、`mfa_recovery_codes` | 可空，保持 NULL（旧库无 MFA 数据，不伪造） |
| `coupons` | `requires_product_ids`、`only_old_client`、`is_discount`、`deleted_at` | 前三个取表默认值，`deleted_at` 保持 NULL |
| `gateway_logs` | `plugin_key` | 保持 NULL（旧日志无插件标识） |
| `invoices` | `fulfillment_pending`、`requires_refund`、`fulfillment_pending_at`、`fulfillment_cleared_at` | 前两个取默认值，后两个保持 NULL |
| `message_logs` | `delivery_key` | 保持 NULL |
| `orders` | `refund_amount`、`refunded_at` | `refund_amount` 取默认 0.00，`refunded_at` 保持 NULL |
| `payments` | `plugin_key`、`plugin_name`、`payment_type`、`option_key`、`option_name` | 全部保持 NULL（旧库无插件维度信息） |
| `services` | `renew_coupon_id`、`renew_coupon_snapshot`、`provision_lease_token`、`provision_lease_until`、`provision_attempts` | 前四个保持 NULL，`provision_attempts` 取默认 0 |
| `verification_histories` | `plugin_id`、`plugin_key`、`plugin_name`、`driver_key` | 全部保持 NULL |

这三类信息（MFA、支付插件维度、开通租约令牌）属于新库新增能力，旧库不存在对应事实，因此一律保持 NULL 或默认值，**不做任何猜测性回填**。

## 字段映射表

除下表列出的差异外，其余同名表按**列名一一对应**直通（脚本的 `identity_spec` 只写源与目标都存在且非生成的列，主键原样保留）。

| 旧 dump 表 | 行数 | 丢弃字段 | 补齐字段 | 目标表与说明 |
| --- | --- | --- | --- | --- |
| schedule_task_runs | 91678 | — | — | 直通同名表，按主键 upsert |
| activity_logs | 78645 | — | context_status、context_request_id | 直通同名表，按主键 upsert |
| integration_plugin_runtime_logs | 70900 | — | — | 直通同名表，按主键 upsert |
| schedule_run_logs | 69595 | — | — | 直通同名表，按主键 upsert |
| schedule_ticks | 8456 | — | — | 直通同名表，按主键 upsert |
| service_provision_attempts | 4318 | backfill_batch_id | — | 直通同名表，按主键 upsert |
| invoices | 3065 | deleted_at | fulfillment_pending、fulfillment_pending_at、fulfillment_cleared_at、requires_refund | 直通同名表，按主键 upsert |
| invoice_items | 3037 | — | — | 直通同名表，按主键 upsert |
| operation_logs | 1848 | — | — | 跳过（旧库独有日志；加 --merge-operation-logs 才并入 activity_logs） |
| message_logs | 1029 | — | delivery_key | 直通同名表，按主键 upsert |
| account_transactions | 708 | — | — | 直通同名表，按主键 upsert |
| personal_access_tokens | 619 | — | — | 跳过（会话令牌属凭据） |
| orders | 536 | deleted_at | refund_amount、refunded_at | 直通同名表，按主键 upsert |
| users | 507 | — | — | 直通同名表，按主键 upsert |
| user_notifications | 479 | — | — | 直通同名表，按主键 upsert |
| payment_callbacks | 478 | — | — | 直通同名表，按主键 upsert |
| automation_logs | 476 | — | — | 直通同名表，按主键 upsert |
| payments | 451 | deleted_at | plugin_key、plugin_name、payment_type、option_key、option_name | 直通同名表，按主键 upsert |
| archive_audit_logs | 446 | — | — | 直通同名表，按主键 upsert |
| user_accounts | 420 | — | — | 直通同名表，按主键 upsert |
| notice_reads | 315 | — | — | 直通同名表，按主键 upsert |
| services | 220 | deleted_at | renew_coupon_id、renew_coupon_snapshot、provision_lease_token、provision_lease_until、provision_attempts | 直通同名表，按主键 upsert |
| service_connection_snapshots | 213 | backfill_batch_id | — | 直通同名表，按主键 upsert |
| service_runtime_snapshots | 213 | backfill_batch_id | — | 直通同名表，按主键 upsert |
| ticket_replies | 211 | — | — | 直通同名表，按主键 upsert |
| migrations | 209 | — | — | 跳过（旧库结构版本账本，写入会让 Laravel 迁移状态错乱） |
| service_upstream_bindings | 208 | backfill_batch_id | — | 直通同名表，按主键 upsert |
| product_upstream_bindings | 138 | backfill_batch_id | — | 直通同名表，按主键 upsert |
| products | 138 | — | — | 直通同名表，按主键 upsert |
| gateway_logs | 133 | — | plugin_key | 直通同名表，按主键 upsert |
| recharge_records | 125 | — | — | 直通同名表，按主键 upsert |
| verification_histories | 123 | — | plugin_id、plugin_key、plugin_name、driver_key | 直通同名表，按主键 upsert |
| settings | 114 | — | — | 直通同名表，按主键 upsert |
| tickets | 82 | — | — | 直通同名表，按主键 upsert |
| notification_templates | 56 | — | — | 直通同名表，按主键 upsert |
| product_groups | 43 | — | — | 拆分为 first/second_product_groups/third_product_groups |
| user_coupons | 36 | — | — | 直通同名表，按主键 upsert |
| third_product_groups | 25 | — | — | 直通同名表，按主键 upsert |
| media_files | 20 | — | — | 直通同名表，按主键 upsert |
| second_product_groups | 15 | — | — | 直通同名表，按主键 upsert |
| content_articles | 14 | — | — | 直通同名表，按主键 upsert |
| integration_plugins | 12 | — | — | 直通同名表，按主键 upsert |
| integration_plugin_bindings | 10 | backfill_batch_id | — | 直通同名表，按主键 upsert |
| failed_jobs | 9 | — | — | 跳过（队列失败调试数据，无业务含义） |
| integration_plugin_configs | 8 | — | — | 直通同名表，按主键 upsert |
| first_product_groups | 6 | — | — | 直通同名表，按主键 upsert |
| referral_account_logs | 6 | — | — | 直通同名表，按主键 upsert |
| referral_rewards | 6 | — | — | 直通同名表，按主键 upsert |
| content_categories | 4 | — | — | 直通同名表，按主键 upsert |
| roles | 4 | — | — | 直通同名表，按主键 upsert |
| admin_users | 3 | — | mfa_secret、mfa_enabled_at、mfa_recovery_codes | 直通同名表，按主键 upsert |
| coupons | 3 | — | requires_product_ids、only_old_client、is_discount、deleted_at | 直通同名表，按主键 upsert |
| member_levels | 3 | — | — | 直通同名表，按主键 upsert |
| refunds | 3 | — | — | 直通同名表，按主键 upsert |
| admin_user_roles | 2 | — | — | 直通同名表，按主键 upsert |
| supplier_plugin_bindings | 2 | backfill_batch_id | — | 直通同名表，按主键 upsert |
| suppliers | 2 | — | — | 直通同名表，按主键 upsert |
| promotion_ambassadors | 1 | — | — | 直通同名表，按主键 upsert |

### `product_groups` 的拆分映射

旧 `product_groups` 43 行（`level` 1/2/3 = 6/15/22 行）按层级拆入三张表：

| 旧字段 | `first_product_groups` | `second_product_groups` | `third_product_groups` |
| --- | --- | --- | --- |
| `id` | `id` | `id` | `id` |
| `name` | `name` | `name` | `name` |
| `parent_id` | —（first 无父级） | `first_product_group_id` | `second_product_group_id` |
| `level` | 用于分流目标表，不再落库 | 同左 | 同左 |
| `code`/`product_type`/`icon`/banner | `code`/`product_type`/`icon`/`banner_image` | 无 `code`/`product_type`/`icon` | 无 `code`/`product_type`/`icon`/`banner_image` |
| `legacy_product_type`/`legacy_product_group_id` | 丢弃 | 丢弃 | 丢弃 |

实测：旧 dump 同时导出了三张同名分类表（6/15/25 行），其 id 完整覆盖 `product_groups` 三个层级，且重叠处 `name` 全一致。脚本识别到这种覆盖后**改为同名表直通**，不再二次拆分，避免同一行被写两次。若将来 dump 只含 `product_groups` 而缺三张同名表，拆分逻辑会自动接管。

### `operation_logs` 的可选并入

`operation_logs` 1848 行是旧库独有表。加 `--merge-operation-logs` 后映射到 `activity_logs`：`event_id` 取 `sha1('operation_logs:' + id)` 保证重复执行不产生新行；`stream` 固定 `operation`；`actor_type` 由 `user_type` 映射（`admin`/`administrator` → `admin`，其余 → `user`）；`updated_at` 取 `created_at`。默认关闭，因为它属于日志流，是否并入是业务口径问题。

## 类型、长度与编码处理策略

### 字符集

dump 字节级确认是 UTF-8，目标库统一 `utf8mb4`。脚本连接串用 `charset=utf8mb4`，不做任何转码。

### 时区

dump 头部为 `SET TIME_ZONE='+00:00'`，即 dump 中的时间字面量是 UTC 时刻；目标库 `timestamp` 列存的也是同一时刻。脚本连接后执行 `SET SESSION time_zone='+00:00'`，**按字面量原样写入，不做任何加减**。`datetime` 列（如 `services.provision_lease_until`）同样按字面量写。实测校验：dump `user_accounts.user_id=1` 的 `created_at` 字面量 `2025-01-16 14:30:14` 与目标库在 `+00:00` 会话下读出的值一致。

### 长度收紧（实测有余量，无需截断）

| 表.列 | 旧长度 | 新长度 | dump 实际最大长度 |
| --- | --- | --- | --- |
| `archive_audit_logs.status` | 30 | 24 | 9 |
| `coupons.trace_id` | 100 | 64 | 36 |
| `user_coupons.trace_id` | 100 | 64 | 36 |
| `schedule_task_runs.status` | 30 | 24 | 7 |
| `coupon_campaigns.trace_id` | 100 | 64 | 0（该表 dump 无行） |

脚本仍内置长度守卫：超限时默认 `--on-overflow fail` 直接中止并报出表、列与主键，明确拒绝静默截断；确认可以截断时才显式加 `--on-overflow truncate`，截断行数计入 `overflow_handled`。

### 长度放宽

`message_logs.status`（20→24）、`payments.trade_no`（100→128）、`refunds.refund_no`（32→40）、`schedule_run_logs.status`（20→24）、`users.alipay_account`（20→254，且由 NOT NULL 放宽为可空）均无风险，直接写入。

### 空值与非空约束

- 源值为空（NULL 或空串）时：目标列可空 → 写 NULL；目标列 NOT NULL 且有默认值或自增 → 写 SQL 的 DEFAULT 关键字（计入 `default_filled`）；既 NOT NULL 又无默认值 → 中止并报错，不静默填空串或 0。
- 数值与时间列的空串按 NULL 处理；字符与 JSON 列的空串保留原义（空串与 NULL 语义不同）。
- 零日期（`0000-00-00`）在 `date`/`datetime`/`timestamp` 上转为 NULL（计入 `zero_date_to_null`）。实测 dump 中该计数为 0。
- 会话 `sql_mode` 设为 `STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,NO_AUTO_VALUE_ON_ZERO`：任何越界、精度丢失、非空违约都会直接报错，而不是被 MySQL 静默改写为警告值；`NO_AUTO_VALUE_ON_ZERO` 保证 dump 里值为 0 的自增主键不会被改写成新分配的自增值。

### 唯一键冲突

写入统一用 `INSERT ... ON DUPLICATE KEY UPDATE`，冲突裁决口径：

- 命中主键：更新该行除主键外的全部源列（`--conflict overwrite`，默认）。
- 命中的是业务唯一键而非主键（例如登记了同 email 不同 id 的用户）：SQL 语义上会更新那条既有行，**源行本身不会插入**。脚本对此类撞键逐行检测并登记为 `unique_conflict`（表、唯一键、源主键、既有行主键），同时让退出码变 1，绝不静默放过。当前 dump 对目标库实测撞键 0 条。
- 含 `NULL` 的唯一键不参与撞键判定：InnoDB 里 `NULL` 互不相等，这类行不可能撞键，检测时必须跳过（否则会产生大量假告警）。
- `--conflict keep-newer` 时，仅当目标行存在 `updated_at` 且比源行新才保留目标值，否则覆盖。
- 对没有业务唯一键的表（`gateway_logs`、`message_logs`、`media_files`、`verification_histories`、`tickets`、`ticket_replies`），幂等性由主键 `id` 保证。

## 外键处理

目标库 114 个外键，其中 4 个表内自引用（`ticket_replies.quote_reply_id`、`users.referrer_user_id`、`invoices.origin_invoice_id`、`recharge_records.origin_recharge_record_id`），3 组双向环（`coupon_campaigns` ↔ `coupons`、`invoices` ↔ `services`、`orders` ↔ `services`）。

处理顺序：

1. 按表级依赖做拓扑排序；成环时按名称打破，这是唯一的“非确定”步骤。
2. 写入时对**可空且单列**的外键做前置检查：父行已存在 → 直接写真实值；父行暂不存在 → 该列先写 NULL，原值进入待回填队列（计入 `fk_deferred`）。
3. 全部表写完后分轮补写父行缺失的行（`--fk-rounds`，默认 20），最后按主键批量 `UPDATE` 回填所有被临时置空的外键列（计入 `fk_resolved`）。
4. 多轮之后父行仍不存在的，列保持 NULL 并登记 `fk_unresolved`（行数、主键、列名、原值），行本身**必须保留**——不丢数据。
5. 不可空的外键列不参与延迟写：依赖拓扑顺序保证父表先完成。

**脚本全程不关闭 `FOREIGN_KEY_CHECKS`**：靠延迟写与回填满足约束，而不是绕过约束。

### 已知残留：`recharge_records.account_transaction_id` 19 行

- 迁移 `2026_10_20_000013_repoint_recharge_record_foreign_keys.php` 已把该外键从 `account_transactions` 改指 `finance_ledger_entries`，并在新表内断言可解析。
- `finance_ledger_entries` 是新库派生表，由 `php artisan finance:rebuild` 从 `account_transactions` 复制生成（保留原主键）。
- 空库靶场上该表为 0 行，因此 dump 中 19 行非空的 `account_transaction_id` 没有父行；这 19 个值 100% 存在于 `account_transactions`。
- 处置：该列可空，脚本置 NULL 并登记 `fk_unresolved = 19`。**在目标库执行 `php artisan finance:rebuild --execute` 后重跑本脚本即可回填**（脚本幂等，重跑只补这一列）。

已在 `idc_test` 上实测闭环（这是本流程唯一需要手工发起的步骤）：

| 步骤 | 命令 | 实测结果 |
| --- | --- | --- |
| 1 迁移 | `--database idc_test` | `total_fk_unresolved = 19`，行全部写入 |
| 2 建派生表 | `DB_DATABASE=idc_test php artisan finance:rebuild --execute` | 台账 708 行、账户 420、退款 3；G1~G7 七个闸门全 PASS（G7 明确“充值凭证外键引用可解析，台账缺失 0”） |
| 3 重跑回填 | `--database idc_test` | `total_fk_unresolved = 0`；`recharge_records` 19 行非空、19 个不同值、孤儿 0 |
| 4 幂等复跑 | 再跑一次 | 53 表 `after_count` 与上一轮完全一致，无重复行 |

`finance:rebuild` 在 `--execute` 前会先跑 dry-run 并检查新表是否已有数据（有数据需 `--fresh`）；它是 Laravel 命令，只在目标库侧运行，与本脚本无依赖关系，也可以提前单独执行。

### 抽样核对与"按策略置 NULL"的区别

抽样比对的是"源值 vs 目标现值"，因此必须区分两类 NULL：

- **按策略置 NULL**：可空外键的父行不在目标库，脚本主动留空（就是上面 19 行）。这是既定结果，抽样跳过。
- **说不清原因的 NULL**：一律计为 `sample_mismatch`，让退出码变 1。

判定依据是这份外键的父表现在到底有没有这个值（现查现比），而不是读 `fk_unresolved` 明细——明细只保留前 200 条，且 `--verify-only` 根本不产生明细。这样两种模式对同一份数据给出的结论一致。

## 幂等与分批

- 幂等：主键与唯一键 upsert，天然可重跑。实测同库连跑两次，53 张表 `before_count` 与 `after_count` 全部等于源行数。
- 分批：`--batch-rows 500` 与 `--batch-bytes 2 MiB` 双阈值，任一先到即提交；每批一个显式事务（`START TRANSACTION` → `execute` → `commit`），批失败整体回滚后再用 `SAVEPOINT` 逐行重试定位。
- 不锁大表：不执行 `LOCK TABLES`/`TRUNCATE`/`ALTER`，不做全表扫描式更新；单批事务时长与 500 行写入相当。
- 中断：Ctrl+C 退出码 130，已提交批次保持生效，重跑从断点数据继续（upsert 覆盖）。

## 迁移结果校验

### 1. 报告内建校验

`migration_report.json` 每表给出 `source_rows`、`sent_rows`、`failed_rows`、`before_count`、`after_count`、`expected_after`、`missing_keys`、`samples`、`sample_mismatch`、`unique_conflict`、`source_row_mismatch`。判定口径：

- `failed_rows == 0`；
- `missing_keys == 0`（dump 的主键集合是否全部出现在目标表）；
- `after_count == expected_after`，其中 `expected_after` 是目标原主键集合与 dump 主键集合的并集大小；
- `sample_mismatch` 为空（每表按 `--sample-size` 随机抽样，覆盖全部非主键列，按主键回读并逐列规范化比对）；
- `unique_conflict == 0`（没有源行因撞业务唯一键而改写了别的行）；
- `source_row_mismatch` 为空（该表解析出的源行数与实际进入迁移的行数一致，用于发现解析期丢行）。

`print_summary` 任一项不满足即退出码 1。另有一类不算失败、但必须登记的待处理项：`fk_unresolved`（可空外键父行缺失，列已置 NULL 但行已写入）。它不会让退出码变 1，却会在结论行里明示“有 N 项已登记的待处理事项”，避免把待回填当成全部完成。

报告只登记定位列（主键、`*_no`、`email`、`code`、`uuid`、`username`），**不落整行源数据**，防止 `users`/`user_accounts` 的密码哈希进入报告文件。

### 2. 关键表行数比对

迁移后直接比对（`COUNT(*)` 应等于旧 dump 行数，前提是该表迁移前为空）：

| 表 | 旧 dump 行数 | 表 | 旧 dump 行数 |
| --- | --- | --- | --- |
| `schedule_task_runs` | 91678 | `payments` | 451 |
| `activity_logs` | 78645 | `archive_audit_logs` | 446 |
| `integration_plugin_runtime_logs` | 70900 | `user_accounts` | 420 |
| `schedule_run_logs` | 69595 | `services` | 220 |
| `schedule_ticks` | 8456 | `service_provision_attempts` | 4318 |
| `invoices` | 3065 | `ticket_replies` | 211 |
| `invoice_items` | 3037 | `tickets` | 82 |
| `orders` | 536 | `users` | 507 |
| `message_logs` | 1029 | `products` | 138 |
| `account_transactions` | 708 | `recharge_records` | 125 |

若目标库迁移前已有数据，比对口径改为“主键集合包含关系”：dump 的每个主键都能在目标表查到，且 `after_count == expected_after`。

参考 SQL（把表名换成待核表）：

```sql
SELECT COUNT(*) AS total FROM schedule_task_runs;
SELECT COUNT(*) FROM orders WHERE id BETWEEN 1 AND 3091;
```

### 3. 抽样核对方法

脚本已内建抽样（`--sample-size`）。人工复核建议按三类抽：

1. **金额与状态**：`orders`/`invoices`/`payments` 各抽 5 行，比对金额、`status`、`paid_at`。
2. **关联完整性**：如下 SQL 应为 0；同理核 `invoice_items.invoice_id`、`payments.invoice_id`、`user_accounts.user_id`。

   ```sql
   SELECT COUNT(*) FROM invoices i LEFT JOIN orders o ON o.id = i.order_id
   WHERE i.order_id IS NOT NULL AND o.id IS NULL;
   ```

3. **时间字段**：`SET time_zone='+00:00'; SELECT created_at FROM users WHERE id=1;` 应与 dump 字面量一致；再切回 `+08:00` 确认展示正确。

### 4. 结构未被修改的证明

执行前后各取一次 `information_schema` 快照（`COLUMNS`/`STATISTICS`/`KEY_COLUMN_USAGE`），逐字节比较。实测 `idc_test`：列 1123/1123、索引 615/615、外键 114/114 完全一致。

脚本内不含任何 `ALTER`/`DROP`/`CREATE`/`TRUNCATE`/`LOCK`/`FOREIGN_KEY_CHECKS` 执行语句（仅注释与解析 dump 的正则中出现这些关键字）。

**靶场曾被外部操作改动过，已核对并复原**：实测期间 `idc_test.migrations` 表被一个非本脚本的会话删除（binlog 显示 03:40:13 一条 `DROP TABLE \`migrations\``，同一会话随后还手工 `UPDATE services SET status`，均不是本脚本行为；本脚本从不产生 DDL，也不写 `migrations` 表）。已按目标库的原始 DDL 重建为空表，表数回到 79，重建后的快照与基线逐字节一致（1123/615/114）。真实迁移前请确认目标库没有其他人在同时操作。

## 回退方案与备份范围

### 执行前必须备份

1. **目标库全量逻辑备份**：`bash backend/scripts/backup_mysql.sh`（从 `backend/.env` 读凭据，输出 `storage/backups/mysql/<库名>_<时间戳>.sql.gz`，用 mktemp 传密码不进进程列表，默认保留 14 天）。
2. **结构快照**：`mysqldump --no-data` 或前述 `information_schema` 快照 JSON，用于事后证明结构未被改动。
3. **行数基线**：迁移前对每张目标表记 `COUNT(*)`（报告里的 `before_count` 也可交叉验证）。

备份必须覆盖脚本将写入的 **53 张表**；由于 `finance_*` 等表虽不由脚本写入但可能被 `finance:rebuild` 影响，建议直接整库备份而不是按表备份。

### 回退路径

| 情形 | 处置 |
| --- | --- |
| 中途失败或结论不正确 | 停止后续操作，用备份整库恢复 |
| 只想撤销本次写入 | 脚本不记录变更前的旧值，**无法行级回滚**，只能整库恢复。这正是必须先备份的原因 |
| 只补跑一部分表 | 直接重跑并加 `--tables a,b,c`，upsert 不会重复 |
| 外键 19 行待回填 | 先 `php artisan finance:rebuild --execute`，再重跑本脚本；无需回退（实测回填后 `fk_unresolved = 0`） |
| 发现某列映射错误 | 修正脚本映射规则后重跑（overwrite 会用 dump 值重新覆盖该列） |

整库恢复命令：

```bat
gunzip -c storage/backups/mysql/<库名>_<时间戳>.sql.gz | mysql -u<user> -p <库名>
```

**不要用 TRUNCATE 加重灌的方式“回退”**：那会把目标库中 dump 不存在的新行一起清掉，属于不可逆破坏。

## 转换兜底路径的造数验证

当前 dump 上 `default_filled`、`zero_date_to_null`、`overflow_handled` 全为 0（14 份历史报告一致），三条兜底路径不会被真实数据触发。已用一份两行的合成 dump（`--tables member_levels`）单独造数验证，实测结果：

| 路径 | 造数方式 | `--on-overflow fail` | `--on-overflow truncate` |
| --- | --- | --- | --- |
| 目标列 `NOT NULL` 且源为 NULL | `status` 给 NULL | 写 `DEFAULT`，计 `default_filled=1` | 同左，`default_filled=2` |
| 零日期转 NULL | `created_at='0000-00-00 00:00:00'` | 行因长度错整体未写，未触发 | 写 NULL，计 `zero_date_to_null=1` |
| 超长截断/中止 | `name` 给 52 字符（上限 50） | 中止该行，`failed_rows=1`，报 `member_levels.name 长度超限：52 > 50`，退出码 1，同批另一行正常写入 | 截为 50 字符，计 `overflow_handled=1`，退出码 0 |

验证同时修掉了一个统计缺陷：抽样核对会把源行再转换一遍，原先记到真实 `TableStats` 上，导致这三项计数**按抽样次数重复累加**（造数用例里 `default_filled` 报 4、实际 2；`overflow_handled` 报 2、实际 1）。现在抽样改用独立的 scratch 统计对象，报告数字只反映真实写入路径。修复后重跑全量，53 张表与幂等复跑结论均未变化。

换结构差异更大的 dump 前，这三条路径已可视为验证过；若改动 `_coerce`/`_char_limit`，仍应重跑本节用例。

## 已实测结论（空库靶场 idc_test）

- 迁移 53 张表，`sent_rows` 合计 337698，等于 dump 总行数 340426 减去跳过的 5 张表 2728 行。
- `failed_rows`、`missing_keys`、`sample_mismatch`、`unique_conflict`、`source_row_mismatch` 全为 0。
- 同库连跑两次：53 张表 `before_count == source_rows == after_count == sent_rows`，无新增重复行。
- 结构快照前后一致（列 1123、索引 615、外键 114，逐字节相同）。
- 跑完 `finance:rebuild --execute` 后重跑本脚本，`total_fk_unresolved` 由 19 降为 0，`recharge_records` 的 19 行外键全部回填，孤儿外键 0。
- 单轮全量耗时约 68~115 秒（含 27~38 秒解析 dump，随机器负载波动）；`--verify-only` 约 55~60 秒。
- 另有一次独立的交叉校验：绕过脚本自身的 `dump_keys`，用另一份解析结果对 62 张表直接执行 `COUNT(*)` 比对（全部一致），并对 8 张核心表各抽 41 行逐列比对（全部一致）。

## 未覆盖风险

1. `invoices`/`orders`/`services` 共 18 行的软删除状态在迁移后丢失（逐行主键见上文“丢弃字段”的处置清单），脚本不做自动推断，需业务侧确认处置口径。
2. 已识别但未修的已知限制：`--conflict keep-newer` 依赖 `updated_at` 非空比较，源或目标该列为 `NULL` 时会退化为覆盖；同一目标表存在多个 spec 时（`--merge-operation-logs` 场景下 `activity_logs`）两份统计会互相覆盖，故该参数与 `--tables` 同时使用时需分开执行两次。
3. `finance_ledger_entries` 等新库派生表不由本脚本生成；`recharge_records` 的 19 行外键依赖 `php artisan finance:rebuild --execute` 才能回填（回填步骤已在 `idc_test` 上实测通过）。
4. 若生产库在 dump 之后又写入了同主键的更新行，默认 `--conflict overwrite` 会用 dump 的旧值覆盖；这类库应先评估是否改用 `--conflict keep-newer`。
5. 全量验证只在 `idc_test`（空库）跑过；生产库因存在 dump 之外的新行，行数比对口径应改用“主键集合包含关系 + `expected_after`”。
