---
status: current
updated: 2026-08-23
owner: backend-data
---

# 日志归档与 MySQL 日志维护

本文是生产环境日志归档、MySQL 日志轮转和 Binlog 过期策略的操作真源。

## 一、数据库日志归档

### 1.1 处理范围

`db:archive-logs` 的默认白名单固定处理以下 5 张 InnoDB 日志表（以 `backend/config/log_archive.php` 为当前真源）：

| 表                                | 内容                          |
| --------------------------------- | ----------------------------- |
| `operation_logs`                  | API、后台操作和管理员登录日志（只读遗留表，存量由归档消化） |
| `activity_logs`                   | 系统与业务活动日志            |
| `message_logs`                    | 短信、邮件统一消息日志        |
| `schedule_run_logs`               | Laravel 调度运行日志          |
| `integration_plugin_runtime_logs` | 插件运行日志                  |

以下表被服务层排除，显式 `--table` 也会在启动 pt-archiver 前拒绝：`automation_logs`（自动化幂等状态）、`gateway_logs`（支付网关交互日志，财务/合规确认前不归档）、`schedule_task_runs`（运行台账）、`archive_audit_logs`、财务、账单、支付、支付回调和失败队列。

归档条件固定为：

```sql
created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
```

财务流水、账单、支付、归档审计和业务快照不在此命令的处理范围内。

### 1.2 文件位置与格式

归档目录由后端固定管理：

```text
backend/storage/app/private/log-archives/
└── operation_logs/
    └── operation_logs_20260721.log
```

归档数据文件格式为：

```text
backend/storage/app/private/log-archives/{表名}/{表名}_YYYYMMDD.log
```

文件内容是带表头的 CSV。同一张表同一天重跑时，pt-archiver 追加到原文件且不重复写表头。执行报告和机器可读运行日志固定存放在 `backend/storage/logs/log-archive/`，不与归档数据混放。

超过 180 天的归档数据 `*.log` 会在正式归档结束后自动删除；归档报告 `run_*.json` 与执行日志 `archive-*.log` 也按同一保留期限清理，防止 `storage/logs/log-archive` 自身无界增长。dry-run 不创建归档数据文件，也不执行历史文件清理。

### 1.3 安装与凭据

目标 Linux 服务器必须安装 Percona Toolkit，并先验证：

```bash
/usr/bin/pt-archiver --version
```

创建 `/etc/caiwu/pt-archiver.cnf`：

```ini
[client]
host=127.0.0.1
port=3306
user=caiwu_archiver
password=替换为强密码
default-character-set=utf8mb4
```

```bash
sudo chown root:root /etc/caiwu/pt-archiver.cnf
sudo chmod 600 /etc/caiwu/pt-archiver.cnf
```

归档账号只授予目标库当前白名单 5 张表（`operation_logs`、`activity_logs`、`message_logs`、`schedule_run_logs`、`integration_plugin_runtime_logs`）的 `SELECT`、`DELETE` 权限。`archive_audit_logs`、`schedule_task_runs`、`automation_logs`、`gateway_logs` 以及财务、账单、支付、支付回调和失败队列表均不授予 `DELETE` 权限。不要把密码写入 Crontab、命令参数、项目代码或执行日志。

归档目录不通过 `.env` 或管理员后台配置：归档数据固定写入 `storage_path('app/private/log-archives')`，报告固定写入 `storage_path('logs/log-archive')`。不支持 NAS 挂载路径或外部目录。

管理员后台的“日志归档”设置仅用于保留天数、pt-archiver 可执行文件与凭据文件路径、并发数、批量大小和批次间隔；归档数据目录与报告目录不可编辑。

### 1.4 执行与断点续传

先预检：

```bash
cd /www/wwwroot/caiwu/backend
/usr/bin/php artisan db:archive-logs --dry-run
```

小表试运行：

```bash
/usr/bin/php artisan db:archive-logs --execute --table=message_logs
```

> 注：`gateway_logs`、`automation_logs`、`schedule_task_runs` 等已在排除清单中，显式 `--table` 也会在启动 pt-archiver 前被拒绝，不要按旧文档尝试归档这些表。

正式执行全部表：

```bash
/usr/bin/php artisan db:archive-logs --execute
```

临时调整限流：

```bash
/usr/bin/php artisan db:archive-logs --execute --concurrency=2 --batch-size=500 --sleep-seconds=2
```

程序使用 pt-archiver 的 `--limit`、`--commit-each`、`--sleep`、递增主键扫描和 `--purge`。已提交批次已经写入文件并从原表删除，中断后再次执行会继续处理剩余记录。极端中断可能让最后一个未提交批次在文件中重复，但不会遗漏已经删除的记录；恢复或分析归档时按主键 `id` 去重。

每张表的成功、失败、影响行数、文件大小、SHA-256 和工具退出码会写入：

- `archive_audit_logs`：数据库内的归档审计；
- `backend/storage/logs/log-archive/run_*.json`：单次完整执行报告；
- `backend/storage/logs/log-archive/archive-YYYY-MM-DD.log`：逐事件 JSON 日志。

### 1.5 平台定时任务

日志归档注册为平台核心定时任务 `log-archive`，Cron 表达式为 `0 2 * * *`。Laravel 的唯一调度入口仍是每分钟执行的 `schedule:run`，它触发 `scheduler:heartbeat`；心跳把到期归档任务投递到默认队列，并由既有任务运行记录保存结果。

任务调用协议由 `config/log_archive.php` 的 `protocol` 控制（`LOG_ARCHIVE_PROTOCOL` 环境变量）：

- `v1`（默认）：调用 `db:archive-logs --execute --json`（旧版 pt-archiver 导出 + `--purge`），最长运行 1 小时，任务锁 61 分钟；
- `v2`：调用 `db:archive-logs-v2 --execute --purge --json`（两阶段：暂存导出 `.part` → 流式校验 → 原子发布 CSV + manifest → 全部成功后才清除源数据）。

该任务不可从管理端手动触发；人工操作必须使用本节的命令并遵循预检流程。V1→V2 切换必须按第五节演练并确认后执行，不得直接修改生产配置。

生产只保留每分钟一次的 `schedule:run`。切换后必须移除原来直接执行 `db:archive-logs --execute` 的每日 02:00 Crontab，不能让两种触发方式同时运行。

## 二、MySQL 错误日志与慢查询日志轮转

先确认实际日志路径：

```sql
SHOW VARIABLES WHERE Variable_name IN ('log_error', 'slow_query_log', 'slow_query_log_file');
```

将查询出的错误日志和慢查询日志绝对路径填入 `/etc/logrotate.d/caiwu-mysql`：

```text
/实际路径/mysql-error.log /实际路径/mysql-slow.log {
    su mysql mysql
    daily
    rotate 30
    compress
    dateext
    missingok
    notifempty
    create 0640 mysql mysql
    sharedscripts
    postrotate
        /usr/bin/mysqladmin --defaults-extra-file=/root/.my.cnf flush-logs > /dev/null 2>&1 || true
    endscript
}
```

`/root/.my.cnf` 必须由 root 持有、权限为 `0600`，并使用具备 `RELOAD` 权限的运维账号；不要复用低权限归档账号。

验证配置：

```bash
sudo logrotate -d /etc/logrotate.d/caiwu-mysql
# 确认调试输出无错误后，在维护窗口验证一次实际轮转
sudo logrotate -f /etc/logrotate.d/caiwu-mysql
```

## 三、Binlog 过期策略

当前实库为 MySQL 8.0，执行：

```sql
SET PERSIST binlog_expire_logs_seconds = 2592000;
SHOW GLOBAL VARIABLES LIKE 'binlog_expire_logs_seconds';
```

MySQL 5.7 使用以下配置，不要与 MySQL 8.0 参数同时配置：

```ini
[mysqld]
expire_logs_days=30
```

也可以先在线执行 `SET GLOBAL expire_logs_days = 30`，但仍需写入 `my.cnf` 并在重启后再次验证。

## 四、空间口径与上线检查

物理删除会让 InnoDB 表空间内部可复用，但独立 `.ibd` 文件不保证立即缩小。本任务不自动执行 `OPTIMIZE TABLE`；确需向操作系统归还空间时，使用管理端现有的智能优化能力并安排独立维护窗口。

上线前依次确认：

1. pt-archiver 版本、凭据文件权限和归档目录可写；
2. 全表 dry-run 的影响行数符合预期；
3. `message_logs` 小表试运行后，CSV 表头、行数、数据库剩余记录和审计日志一致；
4. 确认每分钟 `schedule:run`、默认队列消费和 `log-archive` 定时任务均正常，并观察首轮完整报告；
5. `logrotate -d` 无错误，Binlog 过期变量为 2592000 秒。

## 五、P0 容量盘点（只读）

生产只读基线使用以下命令，全部不产生写入：

```bash
cd /www/wwwroot/caiwu/backend
/usr/bin/php artisan db:logs:capacity --json        # 每表行数/数据/索引字节、日志表合计、storage/logs 与归档目录、磁盘可用
/usr/bin/php artisan db:archive-logs:health --json  # 候选积压、最近成功/失败批次、报告与归档文件规模
/usr/bin/php artisan db:archive-logs-v2:list --json # V2 归档批次物与可恢复性（尚无批次时为空属正常）
```

同时核对归档运行证据（datetime 需人工复核）：

```sql
SELECT status, COUNT(*) FROM archive_audit_logs GROUP BY status;
SELECT status, MAX(finished_at) FROM archive_audit_logs GROUP BY status;
```

保留每次盘点输出（容量基线 + 健康状态 + 审计聚合），作为后续 P3 空间回收的对比基准。确认保留政策（在线 30 天 / 归档文件 180 天 / Laravel 文件 14 天 / 备份 14 天）是否满足业务与合规要求后再进入下一步；未确认前不调整保留天数。

## 六、V2 协议演练与生产切换

V2 与 v1 的关键差异：pt-archiver **只导出不删除**，源数据只有在「导出 → 流式校验（表头/行数/ID 边界/SHA-256）→ 原子发布 CSV 与 manifest」全部成功后，才允许显式清除。

### 6.1 隔离环境演练（Linux，必须）

1. 安装 Percona Toolkit，准备拷贝的 `pt-archiver.cnf`（0600），复制一份生产 `idc` 结构的数据（或最小白名单表）；
2. ```bash
   /usr/bin/php artisan db:archive-logs-v2                 # overview：核对 eligible 行数与 ID 边界
   /usr/bin/php artisan db:archive-logs-v2 --execute --table=message_logs --json   # 暂存+校验+发布，不删除
   /usr/bin/php artisan db:archive-logs-v2:list --json      # 批次状态应为 published、restorable=可恢复
   /usr/bin/php artisan db:archive-logs-v2:search --table=message_logs --start-date=2025-01-01 --end-date=2025-02-01 --json      # 冷检索命中演练数据
   /usr/bin/php artisan db:archive-logs-v2 --purge --batch-id=<上一步批次> --json  # 显式清除源数据
   /usr/bin/php artisan db:archive-logs-v2:list --json      # 状态应为 purged、restorable=可恢复（文件仍在）
   /usr/bin/php artisan db:archive-logs-v2 --restore-dry-run --batch-id=<批次> --json  # 恢复校验通过
   ```
3. 校验点：`.part` 已消失、CSV 与 manifest 存在、源表行数按预期减少、`archive_items` 状态流转 `planned→staging→verified→published→purging→purged` 一致；
4. 断点演练：导出中断后重跑同批次，`.part` 复用且不重复导出（`staging` 分支幂等）；清除中断后重跑 `--purge` 只删剩余行。

### 6.2 生产灰度（小表开始）

1. 保持 `protocol=v1` 不变，先在小表执行完整 V2 闭环（不经过心跳）：
   ```bash
   /usr/bin/php artisan db:archive-logs-v2 --execute --purge --table=message_logs --json
   ```
2. 核对：`archive_items` 状态、CSV/manifest 文件、源表行数、`db:archive-logs-v2:list` 可恢复性；
3. 连续 7 天由心跳 v1 观察 `log-archive` 与 `log-archive-health` 任务台账、`db:logs:capacity` 快照趋势正常。

### 6.3 切换协议

```bash
# 变更单中确认：v1 心跳最近 7 天无失败批次、V2 小表闭环演练 ≥2 轮通过
echo 'LOG_ARCHIVE_PROTOCOL=v2' >> /www/wwwroot/caiwu/backend/.env
cd /www/wwwroot/caiwu/backend && /usr/bin/php artisan config:cache
```

切换后至少观察 7 天：`log-archive` 任务 summary 应显示 `db:archive-logs-v2` 且 `exit_code=0`；`archive_items` 每日产生 published/purged 批次；`archive_audit_logs` 不再新增 v1 批次。任一异常按 6.4 回滚。

### 6.4 回滚

```bash
# 从 .env 移除或改为 v1；立即生效需再次 config:cache
sed -i '/LOG_ARCHIVE_PROTOCOL/d' /www/wwwroot/caiwu/backend/.env
cd /www/wwwroot/caiwu/backend && /usr/bin/php artisan config:cache
```

已发布的 V2 归档物与 manifest 保留，不删除；已进入 purging 的批次用 `--purge --batch-id` 收敛到终态后再回滚，不得在回滚后再次执行旧版 `--purge` 指向同一批次。

## 七、P3 空间回收流程

物理删除让 InnoDB 表空间内部可复用，但独立 `.ibd` 不保证立即缩小；向操作系统归还空间需要 `OPTIMIZE TABLE`，必须安排在独立维护窗口。

1. **窗口前**：执行 `db:logs:capacity --json` 保存基线（重点记录 `activity_logs`、`operation_logs`、`schedule_run_logs`、`integration_plugin_runtime_logs` 的 data/index 字节与整盘可用空间）；
2. **窗口内**（仅限已批准表，按表逐个执行并观察）：使用管理端智能优化或
   ```sql
   OPTIMIZE TABLE activity_logs, operation_logs, schedule_run_logs, integration_plugin_runtime_logs;
   ```
3. **窗口后**：再次执行 `db:logs:capacity --json`，对比每表 data/index 字节、库总字节与磁盘可用空间，输出回收报告；
4. 归档稳定一个完整周期（含 v1→v2 切换后的 7 天观察）后，再处置 `operation_logs` 只读遗留存量与旧格式归档文件；
5. 整个 P3 阶段不自动执行 `OPTIMIZE TABLE`，不进应用代码。
