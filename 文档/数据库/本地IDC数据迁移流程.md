# 本地 IDC 数据迁移流程

本文记录本地数据库从 MySQL dump 重置、初始化、迁移数据的固定流程。

## 一键脚本

默认迁移仓库根目录下的 `idc_2026-06-07_15-31-26_mysql_data_R5FN2.sql`：

```bat
python backend\scripts\reset_init_and_migrate_idc_dump.py
```

迁移其他 dump：

```bat
python backend\scripts\reset_init_and_migrate_idc_dump.py --dump C:\path\to\idc_dump.sql
```

只检查不写库：

```bat
python backend\scripts\reset_init_and_migrate_idc_dump.py --dump C:\path\to\idc_dump.sql --dry-run
```

迁移记录默认写入：

```text
文档\数据库\迁移记录\idc-local-migration-YYYYMMDDHHMMSS.log
```

## 执行顺序

1. `backend/scripts/install_db.py --reset`
   - 删除并重建 `.env` 指向的本地数据库。
   - 使用当前项目 schema 初始化表结构。
   - 执行当前迁移并初始化默认管理员。

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

## 本次 dump

```text
C:\Users\USER125536\Desktop\caiwu\idc_2026-06-09_12-16-17_mysql_data_lvxMt.sql
```

## 本次执行结果

- 执行时间：2026-06-09 12:22
- 成功日志：`文档\数据库\迁移记录\idc-local-migration-20260609122244.log`
- 核心行数：`users=453`、`products=143`、`invoices=1844`、`services=138`、`payments=234`、`operation_logs=94929`
- 运行态表：`jobs=0`、`sessions=0`
- 临时表残留：`legacy_temp_tables=0`
- 新增日志表：`activity_logs=0`、`gateway_logs=0`、`schedule_run_logs=0`
- 字段检查：`products.custom_display_name` 存在，`products.name` 不存在
