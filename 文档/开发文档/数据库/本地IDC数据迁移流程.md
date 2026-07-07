# 本地 IDC 数据迁移流程

本文记录本地数据库从 MySQL dump 重置、初始化、迁移数据的固定流程。

## 一键脚本

迁移指定 dump：

```bat
python backend\scripts\reset_init_and_migrate_idc_dump.py --dump C:\path\to\idc_dump.sql
```

如果脚本支持默认 dump，也必须先确认该 dump 文件在当前工作区真实存在；不要在文档中固定引用已清理的本地 dump 文件名。

仅初始化当前库结构，不导入旧 dump：

```bat
python backend\scripts\install_db.py --reset
```

只检查不写库：

```bat
python backend\scripts\reset_init_and_migrate_idc_dump.py --dump C:\path\to\idc_dump.sql --dry-run
```

迁移记录默认写入：

```text
控制台输出或脚本指定的本地日志位置
```

一次性迁移日志不进入长期文档树；如需保留结论，应提炼为本文件中的兼容规则。

## 执行顺序

1. `backend/scripts/install_db.py --reset`
   - 删除并重建 `.env` 指向的本地数据库。
   - 使用当前项目 schema 初始化表结构。
   - 执行当前迁移。
   - 初始化默认 `settings`、通知模板和默认管理员。
   - schema baseline 只承载表结构和 `migrations` 状态，不承载业务行数据；默认运行数据由本脚本显式补齐。

2. `backend/scripts/migrate_legacy_dump.py`
   - 将旧 dump 导入临时库；如果当前账号无建库权限，则退回到同库临时前缀表。
   - 同库临时前缀表模式会移除旧 dump 表结构里的外键约束，避免和当前库同名外键冲突；最终仍以迁移后的关键引用检查为准。
   - 按当前目标表字段映射复制数据，不把旧 dump 的表结构恢复到目标库。
   - 每张表复制前会再次清空对应目标表，避免本地后端运行期间写入日志导致主键冲突。
   - 清空运行态表 `jobs`、`password_reset_tokens`、`personal_access_tokens`、`sessions`，不迁移旧运行态数据。
   - 保留 `migrations` 表，保证当前项目迁移状态不被旧库覆盖。
   - 过滤 `settings` 中 `codex_runtime`、`codex_service` 临时配置。
   - 迁移完成后执行关键引用检查，并默认清理临时库或临时表。

## 已知兼容规则

- 旧 dump 缺少当前新增日志表 `activity_logs`、`gateway_logs`、`schedule_run_logs` 时，初始化后的目标表保持空表。
- `products.name` 等旧字段不会被恢复。
- `orders`、`invoices` 的商品规格快照会优先映射 `product_spec_snapshot`，缺失时兼容旧的 `product_name_snapshot`。

## 迁移后检查

- 核心表行数符合预期：`users`、`products`、`invoices`、`services`、`payments`、`operation_logs`。
- 运行态表保持清空：`jobs`、`sessions`、`password_reset_tokens`、`personal_access_tokens`。
- 临时库或临时前缀表已清理。
- 当前结构与 `文档/开发文档/数据库/当前数据库结构.md` 一致。
- 前后端能使用迁移后的管理员、用户、商品和服务数据完成最小冒烟。

## 刷新当前结构基线

当前库结构或数据迁移完成后，需要同步刷新空库初始化 baseline 和数据库结构快照：

```bat
php backend\scripts\export_schema_baseline.php
php backend\scripts\export_database_structure.php
```

`export_schema_baseline.php` 只导出表结构和 `migrations` 记录；`install_db.py` 会在导入 baseline 后补默认配置、通知模板和管理员。
