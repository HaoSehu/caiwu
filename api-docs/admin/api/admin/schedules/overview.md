# overview

**请求方法**：GET  
**请求路径**：`/api/admin/schedules/overview`  
**调试状态**：✅ 通过

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| 无 | - | 否 | 无请求参数 |

### 请求示例（完整 JSON）
```json
{}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.environment | object | 真实调用返回字段 |
| data.environment.app_env | string | 真实调用返回字段 |
| data.environment.app_timezone | string | 真实调用返回字段 |
| data.environment.php_binary | string | 真实调用返回字段 |
| data.environment.artisan_path | string | 真实调用返回字段 |
| data.environment.schedule_source | string | 真实调用返回字段 |
| data.environment.queue_driver | string | 真实调用返回字段 |
| data.environment.jobs_table_ready | boolean | 真实调用返回字段 |
| data.environment.failed_jobs_table_ready | boolean | 真实调用返回字段 |
| data.environment.pending_jobs | integer | 真实调用返回字段 |
| data.environment.failed_jobs | integer | 真实调用返回字段 |
| data.environment.queue_runtime_mode | string | 真实调用返回字段 |
| data.environment.schedule_mutex | object | 真实调用返回字段 |
| data.environment.schedule_mutex.enabled | boolean | 真实调用返回字段 |
| data.environment.schedule_mutex.degraded | boolean | 真实调用返回字段 |
| data.environment.schedule_mutex.mode | string | 真实调用返回字段 |
| data.environment.schedule_mutex.reason | string | 真实调用返回字段 |
| data.environment.schedule_mutex.cache_store | string | 真实调用返回字段 |
| data.environment.schedule_mutex.os_family | string | 真实调用返回字段 |
| data.environment.automation_config | object | 真实调用返回字段 |
| data.environment.automation_config.status | string | 真实调用返回字段 |
| data.environment.automation_config.fallback_reason | string | 真实调用返回字段 |
| data.commands | array | 真实调用返回字段 |
| data.commands.key | string | 真实调用返回字段 |
| data.commands.title | string | 真实调用返回字段 |
| data.commands.description | string | 真实调用返回字段 |
| data.commands.command | string | 真实调用返回字段 |
| data.tasks | array | 真实调用返回字段 |
| data.tasks.key | string | 真实调用返回字段 |
| data.tasks.title | string | 真实调用返回字段 |
| data.tasks.category | string | 真实调用返回字段 |
| data.tasks.description | string | 真实调用返回字段 |
| data.tasks.manual_triggerable | boolean | 真实调用返回字段 |
| data.tasks.expression | string | 真实调用返回字段 |
| data.tasks.summary | string | 真实调用返回字段 |
| data.tasks.timezone | string | 真实调用返回字段 |
| data.tasks.next_run_at | string | 真实调用返回字段 |
| data.tasks.without_overlapping | boolean | 真实调用返回字段 |
| data.tasks.run_in_background | boolean | 真实调用返回字段 |
| data.tasks.overlap_expires_minutes | integer | 真实调用返回字段 |
| data.tasks.last_log | object | 真实调用返回字段 |
| data.tasks.last_log.time | string | 真实调用返回字段 |
| data.tasks.last_log.level | string | 真实调用返回字段 |
| data.tasks.last_log.message | string | 真实调用返回字段 |
| data.tasks.last_log.task_key | string | 真实调用返回字段 |
| data.tasks.last_log.status | string | 真实调用返回字段 |
| data.tasks.last_log.duration_ms | integer | 真实调用返回字段 |
| data.tasks.last_log.summary | object | 真实调用返回字段 |
| data.tasks.last_log.summary.output | string | 真实调用返回字段 |
| data.tasks.last_log.summary.command | string | 真实调用返回字段 |
| data.tasks.last_log.summary.exit_code | integer | 真实调用返回字段 |
| data.tasks.last_log.error_msg | null | 真实调用返回字段 |
| data.recent_logs | array | 真实调用返回字段 |
| data.recent_logs.time | string | 真实调用返回字段 |
| data.recent_logs.level | string | 真实调用返回字段 |
| data.recent_logs.message | string | 真实调用返回字段 |
| data.recent_logs.task_key | null | 真实调用返回字段 |
| data.recent_logs.status | string | 真实调用返回字段 |
| data.recent_logs.duration_ms | integer | 真实调用返回字段 |
| data.recent_logs.summary | object | 真实调用返回字段 |
| data.recent_logs.summary.failed_products | integer | 真实调用返回字段 |
| data.recent_logs.summary.synced_products | integer | 真实调用返回字段 |
| data.recent_logs.summary.matched_products | integer | 真实调用返回字段 |
| data.recent_logs.summary.skipped_products | integer | 真实调用返回字段 |
| data.recent_logs.summary.matched_suppliers | integer | 真实调用返回字段 |
| data.recent_logs.error_msg | null | 真实调用返回字段 |
| data.settings_snapshot | array | 真实调用返回字段 |
| data.settings_snapshot.label | string | 真实调用返回字段 |
| data.settings_snapshot.value | string | 真实调用返回字段 |
| data.settings_snapshot.note | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "environment": {
            "app_env": "local",
            "app_timezone": "Asia/Shanghai",
            "php_binary": "D:\BtSoft\php\83\php.exe",
            "artisan_path": "C:\Users\Admin\Desktop\caiwu\backend\artisan",
            "schedule_source": "C:\Users\Admin\Desktop\caiwu\backend\routes/console.php",
            "queue_driver": "database",
            "jobs_table_ready": true,
            "failed_jobs_table_ready": true,
            "pending_jobs": 0,
            "failed_jobs": 0,
            "queue_runtime_mode": "database_queue_heartbeat_drained",
            "schedule_mutex": {
                "enabled": true,
                "degraded": false,
                "mode": "without_overlapping",
                "reason": "",
                "cache_store": "redis",
                "os_family": "Windows"
            },
            "automation_config": {
                "status": "loaded",
                "fallback_reason": ""
            }
        },
        "commands": [
            {
                "key": "schedule_run",
                "title": "调度入口",
                "description": "宝塔生产环境请仅保留这一条，每 1 分钟运行一次；Laravel Schedule 内只有 15 分钟心跳源。",
                "command": "\"D:\BtSoft\php\83\php.exe\" \"C:\Users\Admin\Desktop\caiwu\backend\artisan\" schedule:run"
            },
            {
                "key": "scheduler_heartbeat",
                "title": "心跳命令",
                "description": "由 schedule:run 自动触发；排查时可手动执行一次心跳。",
                "command": "\"D:\BtSoft\php\83\php.exe\" \"C:\Users\Admin\Desktop\caiwu\backend\artisan\" scheduler:heartbeat"
            },
            {
                "key": "schedule_work",
                "title": "本地调度 Worker",
                "description": "本地开发环境可常驻运行以下命令，无需额外系统 Cron。",
                "command": "\"D:\BtSoft\php\83\php.exe\" \"C:\Users\Admin\Desktop\caiwu\backend\artisan\" schedule:work"
            },
            {
                "key": "queue_work",
                "title": "队列 Worker（可选）",
                "description": "仅在你需要更低延迟时再单独常驻运行；宝塔单计划任务方案下不是必需。",
                "command": "\"D:\BtSoft\php\83\php.exe\" \"C:\Users\Admin\Desktop\caiwu\backend\artisan\" queue:work --queue=provision,referral,notification,coupon,default --sleep=1 --tries=3 --timeout=1200"
            }
        ],
        "tasks": [
            {
                "key": "provision-retry-failed",
                "title": "上游开通孤儿单补偿告警",
                "category": "上游开通",
                "description": "默认 dry-run 扫描上游开通失败孤儿单并写日志告警。",
                "manual_triggerable": true,
                "expression": "每次心跳",
                "summary": "每次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 15,
                "last_log": {
                    "time": "2026-07-05 11:16:04",
                    "level": "SUCCESS",
                    "message": "上游开通孤儿单补偿告警",
                    "task_key": "provision-retry-failed",
                    "status": "success",
                    "duration_ms": 8,
                    "summary": {
                        "output": "发现 2 个上游开通失败孤儿单（模式：dry-run 告警）\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| service_id | order_id | user_id | retry_count | provision_error                            |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| 187        | 272      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n| 188        | 273      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n当前为 dry-run 模式，仅告警未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。",
                        "command": "provision:retry-failed",
                        "exit_code": 0
                    },
                    "error_msg": null
                }
            },
            {
                "key": "refresh-hosting-panel-auth",
                "title": "接口认证刷新",
                "category": "供应商接口",
                "description": "定时刷新主机面板接口认证会话，减少上游登录态过期导致的请求失败。",
                "manual_triggerable": true,
                "expression": "每次心跳",
                "summary": "每次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 10,
                "last_log": {
                    "time": "2026-07-05 11:15:00",
                    "level": "SUCCESS",
                    "message": "接口认证刷新",
                    "task_key": "refresh-hosting-panel-auth",
                    "status": "success",
                    "duration_ms": 92,
                    "summary": {
                        "failed": 2,
                        "matched": 2,
                        "refreshed": 0
                    },
                    "error_msg": null
                }
            },
            {
                "key": "product-upstream-config-sync",
                "title": "上游产品配置同步",
                "category": "商品同步",
                "description": "每 24 小时拉取已绑定上游商品的配置项，并自动保存到本地商品配置，不同步商品定价。",
                "manual_triggerable": true,
                "expression": "0 0 * * *",
                "summary": "0 0 * * *",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-06 00:00:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 180,
                "last_log": {
                    "time": "2026-07-05 12:17:37",
                    "level": "SUCCESS",
                    "message": "上游产品配置同步",
                    "task_key": "product-upstream-config-sync",
                    "status": "success",
                    "duration_ms": 15901,
                    "summary": {
                        "failed_products": 0,
                        "synced_products": 124,
                        "matched_products": 124,
                        "skipped_products": 0,
                        "matched_suppliers": 2
                    },
                    "error_msg": null
                }
            },
            {
                "key": "ticket-auto-close",
                "title": "工单自动关闭",
                "category": "工单管理",
                "description": "关闭超过阈值且长期无客户回复的工单。",
                "manual_triggerable": true,
                "expression": "0 0 * * *",
                "summary": "0 0 * * *",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-06 00:00:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 20,
                "last_log": {
                    "time": "2026-07-05 00:02:36",
                    "level": "SUCCESS",
                    "message": "工单自动关闭",
                    "task_key": "ticket-auto-close",
                    "status": "success",
                    "duration_ms": 101,
                    "summary": {
                        "closed": 2,
                        "pending_reminded": 0
                    },
                    "error_msg": null
                }
            },
            {
                "key": "referral-release-rewards",
                "title": "推荐奖励释放",
                "category": "推荐奖励",
                "description": "把已过冻结期的推荐奖励转入可提现余额，并记录推荐账户流水。",
                "manual_triggerable": true,
                "expression": "每次心跳",
                "summary": "每次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 20,
                "last_log": {
                    "time": "2026-07-05 11:15:00",
                    "level": "SUCCESS",
                    "message": "推荐奖励释放",
                    "task_key": "referral-release-rewards",
                    "status": "success",
                    "duration_ms": 3,
                    "summary": {
                        "released": 0
                    },
                    "error_msg": null
                }
            },
            {
                "key": "db-archive-operation-logs",
                "title": "operation_logs 归档",
                "category": "日志维护",
                "description": "每月 1 日 04:00 归档 operation_logs，默认保留最近 90 天。",
                "manual_triggerable": true,
                "expression": "0 4 1 * *",
                "summary": "0 4 1 * *",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-08-01 04:00:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 60,
                "last_log": null
            },
            {
                "key": "schedule-run-logs-prune-weekly",
                "title": "schedule_run_logs 清理",
                "category": "日志维护",
                "description": "每周日 03:00 清理 30 天前的定时任务运行日志。",
                "manual_triggerable": true,
                "expression": "0 3 * * 0",
                "summary": "0 3 * * 0",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-12 03:00:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 15,
                "last_log": null
            },
            {
                "key": "db-archive-logs-dry-run",
                "title": "日志归档预检",
                "category": "日志维护",
                "description": "每月 1 日 03:30 预检可归档日志数量，生成 dry-run 报告。",
                "manual_triggerable": true,
                "expression": "30 3 1 * *",
                "summary": "30 3 1 * *",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-08-01 03:30:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 60,
                "last_log": {
                    "time": "2026-07-01 03:30:20",
                    "level": "SUCCESS",
                    "message": "日志归档预检",
                    "task_key": "db-archive-logs-dry-run",
                    "status": "success",
                    "duration_ms": 42,
                    "summary": {
                        "tables": {
                            "sms_logs": {
                                "cutoff": "2026-01-02 03:30:20",
                                "eligible_rows": 0
                            },
                            "email_logs": {
                                "cutoff": "2026-01-02 03:30:20",
                                "eligible_rows": 0
                            },
                            "operation_logs": {
                                "cutoff": "2026-04-02 03:30:20",
                                "eligible_rows": 200
                            },
                            "automation_logs": {
                                "cutoff": "2026-01-02 03:30:20",
                                "eligible_rows": 0
                            },
                            "notification_logs": {
                                "cutoff": "2026-01-02 03:30:20",
                                "eligible_rows": 0
                            }
                        },
                        "exit_code": 0,
                        "report_path": "/www/wwwroot/backend/storage/app/private/log-archives/2026-07/dry-run_20260701_033020/manifest.json",
                        "eligible_rows": 200
                    },
                    "error_msg": null
                }
            },
            {
                "key": "service-status-sync",
                "title": "用户产品状态同步",
                "category": "服务状态",
                "description": "定时拉取上游实例详情与运行状态，并同步回本地用户服务状态。",
                "manual_triggerable": true,
                "expression": "每次心跳",
                "summary": "每次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 30,
                "last_log": {
                    "time": "2026-07-05 11:16:04",
                    "level": "SUCCESS",
                    "message": "用户产品状态同步",
                    "task_key": "service-status-sync",
                    "status": "success",
                    "duration_ms": 64177,
                    "summary": {
                        "failed": 0,
                        "synced": 76,
                        "scanned": 76,
                        "skipped": 0
                    },
                    "error_msg": null
                }
            },
            {
                "key": "service-lifecycle-maintenance",
                "title": "服务生命周期维护",
                "category": "服务生命周期",
                "description": "处理服务到期暂停、暂停通知和到期后自动取消。",
                "manual_triggerable": true,
                "expression": "每次心跳",
                "summary": "每次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 15,
                "last_log": {
                    "time": "2026-07-05 11:15:00",
                    "level": "SUCCESS",
                    "message": "服务生命周期维护",
                    "task_key": "service-lifecycle-maintenance",
                    "status": "success",
                    "duration_ms": 8,
                    "summary": {
                        "cancelled": 0,
                        "suspended": 0,
                        "suspend_notified": 0
                    },
                    "error_msg": null
                }
            },
            {
                "key": "service-auto-renew",
                "title": "服务自动续费",
                "category": "服务续费",
                "description": "扫描开启自动续费的服务，余额充足时自动创建续费账单并完成支付处理。",
                "manual_triggerable": true,
                "expression": "每 4 次心跳",
                "summary": "每 4 次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 4,
                "last_log": {
                    "time": "2026-07-05 11:00:00",
                    "level": "SUCCESS",
                    "message": "服务自动续费",
                    "task_key": "service-auto-renew",
                    "status": "success",
                    "duration_ms": 12,
                    "summary": {
                        "paid": 0,
                        "failed": 0,
                        "blocked": 0,
                        "matched": 0,
                        "pending": 0,
                        "skipped": 0,
                        "recovered": 0
                    },
                    "error_msg": null
                }
            },
            {
                "key": "site-cache-warmup",
                "title": "首页缓存预热",
                "category": "站点缓存",
                "description": "刷新首页与产品目录缓存，保持站点缓存热度。",
                "manual_triggerable": true,
                "expression": "每次心跳",
                "summary": "每次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 15,
                "last_log": {
                    "time": "2026-07-05 11:16:07",
                    "level": "SUCCESS",
                    "message": "首页缓存预热",
                    "task_key": "site-cache-warmup",
                    "status": "success",
                    "duration_ms": 1603,
                    "summary": {
                        "output": "开始预热缓存...\r\n预热首页数据...\r\n首页缓存预热完成\r\n预热产品类型...\r\n产品类型缓存预热完成\r\n预热产品分组...\r\n产品分组缓存预热完成\r\n所有缓存预热完成，耗时 1.6s",
                        "command": "app:warmup-site-cache",
                        "exit_code": 0
                    },
                    "error_msg": null
                }
            },
            {
                "key": "coupon-campaign-dispatch",
                "title": "优惠券活动发放",
                "category": "营销活动",
                "description": "按活动配置的星期与时间自动生成一批公开优惠券，例如每周五 18:00 发放周五特惠。",
                "manual_triggerable": true,
                "expression": "每次心跳",
                "summary": "每次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 15,
                "last_log": {
                    "time": "2026-07-05 11:16:04",
                    "level": "SUCCESS",
                    "message": "优惠券活动发放",
                    "task_key": "coupon-campaign-dispatch",
                    "status": "success",
                    "duration_ms": 3,
                    "summary": {
                        "failed": 0,
                        "matched": 0,
                        "skipped": 0,
                        "triggered": 0,
                        "coupon_ids": []
                    },
                    "error_msg": null
                }
            },
            {
                "key": "reconcile-account-balance",
                "title": "账户余额在线对账",
                "category": "财务对账",
                "description": "定时执行账户余额在线对账，及时发现余额投影异常。",
                "manual_triggerable": true,
                "expression": "每 4 次心跳",
                "summary": "每 4 次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 30,
                "last_log": {
                    "time": "2026-07-05 11:01:14",
                    "level": "FAILED",
                    "message": "账户余额在线对账",
                    "task_key": "reconcile-account-balance",
                    "status": "failed",
                    "duration_ms": 33,
                    "summary": null,
                    "error_msg": "Artisan command [reconcile:account-balance] exited with code 1. Output: 对账完成：差异用户账户数 178\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| user_id | account_type    | latest_balance_after | account_balance | diff   |\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| 2       | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 3       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 5       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 7       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 8       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 10      | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 11      | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 12      | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 14      | cash            | 5.10                 | 3.30            | 1.80   |\r\n| 16      "
                }
            },
            {
                "key": "compensate-recharge-invoices",
                "title": "充值账单补偿",
                "category": "财务补偿",
                "description": "扫描成功但缺失账单关联的充值支付记录，补建充值账单。",
                "manual_triggerable": true,
                "expression": "每 4 次心跳",
                "summary": "每 4 次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 30,
                "last_log": {
                    "time": "2026-07-05 11:01:16",
                    "level": "SUCCESS",
                    "message": "充值账单补偿",
                    "task_key": "compensate-recharge-invoices",
                    "status": "success",
                    "duration_ms": 2,
                    "summary": {
                        "output": "[充值补偿] 无需处理的悬空记录",
                        "command": "payment:compensate-recharge-invoices",
                        "exit_code": 0,
                        "parameters": {
                            "--limit": 200
                        }
                    },
                    "error_msg": null
                }
            },
            {
                "key": "billing-maintenance",
                "title": "账单自动化维护",
                "category": "账单提醒",
                "description": "处理续费提醒、自动生成账单、账单到期提醒和逾期标记。",
                "manual_triggerable": true,
                "expression": "0 * * * *",
                "summary": "0 * * * *",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 17:00:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 30,
                "last_log": {
                    "time": "2026-07-05 11:01:14",
                    "level": "SUCCESS",
                    "message": "账单自动化维护",
                    "task_key": "billing-maintenance",
                    "status": "success",
                    "duration_ms": 64,
                    "summary": {
                        "renew_notice_sent": 0,
                        "invoice_overdue_sent": 0,
                        "invoice_pre_due_sent": 0,
                        "renew_orders_created": 0,
                        "fulfillment_recovered": 0,
                        "invoices_marked_overdue": 0,
                        "auto_renew_upcoming_sent": 0
                    },
                    "error_msg": null
                }
            },
            {
                "key": "order-cleanup",
                "title": "账单与充值清理",
                "category": "账单清理",
                "description": "自动取消超时未付款账单，并失效超时未付款充值单。",
                "manual_triggerable": true,
                "expression": "每次心跳",
                "summary": "每次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 15,
                "last_log": {
                    "time": "2026-07-05 11:16:04",
                    "level": "SUCCESS",
                    "message": "账单与充值清理",
                    "task_key": "order-cleanup",
                    "status": "success",
                    "duration_ms": 19,
                    "summary": {
                        "recharges_expired": 0,
                        "invoices_cancelled": 0
                    },
                    "error_msg": null
                }
            },
            {
                "key": "vnc-ensure-relay",
                "title": "VNC Relay 守护",
                "category": "运行时守护",
                "description": "检测并自动拉起 VNC WebSocket 中转服务。",
                "manual_triggerable": true,
                "expression": "每次心跳",
                "summary": "每次心跳",
                "timezone": "Asia/Shanghai",
                "next_run_at": "2026-07-05 16:45:00",
                "without_overlapping": true,
                "run_in_background": false,
                "overlap_expires_minutes": 15,
                "last_log": {
                    "time": "2026-07-05 11:16:04",
                    "level": "SUCCESS",
                    "message": "VNC Relay 守护",
                    "task_key": "vnc-ensure-relay",
                    "status": "success",
                    "duration_ms": 14,
                    "summary": {
                        "output": "VNC Relay 已在运行 (127.0.0.1:8100)，跳过",
                        "command": "vnc:ensure-relay",
                        "exit_code": 0
                    },
                    "error_msg": null
                }
            }
        ],
        "recent_logs": [
            {
                "time": "2026-07-05 12:17:37",
                "level": "SUCCESS",
                "message": "上游产品配置同步",
                "task_key": null,
                "status": "success",
                "duration_ms": 15901,
                "summary": {
                    "failed_products": 0,
                    "synced_products": 124,
                    "matched_products": 124,
                    "skipped_products": 0,
                    "matched_suppliers": 2
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:16:07",
                "level": "SUCCESS",
                "message": "首页缓存预热",
                "task_key": null,
                "status": "success",
                "duration_ms": 1603,
                "summary": {
                    "output": "开始预热缓存...\r\n预热首页数据...\r\n首页缓存预热完成\r\n预热产品类型...\r\n产品类型缓存预热完成\r\n预热产品分组...\r\n产品分组缓存预热完成\r\n所有缓存预热完成，耗时 1.6s",
                    "command": "app:warmup-site-cache",
                    "exit_code": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:16:06",
                "level": "SUCCESS",
                "message": "队列积压消费",
                "task_key": null,
                "status": "success",
                "duration_ms": 1029,
                "summary": {
                    "command": "queue:work",
                    "exit_code": 0,
                    "parameters": {
                        "--queue": "provision,referral,notification,coupon,default",
                        "--sleep": 1,
                        "--tries": 3,
                        "--timeout": 1200,
                        "--max-time": 50,
                        "--stop-when-empty": true
                    }
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:16:04",
                "level": "SUCCESS",
                "message": "VNC Relay 守护",
                "task_key": null,
                "status": "success",
                "duration_ms": 14,
                "summary": {
                    "output": "VNC Relay 已在运行 (127.0.0.1:8100)，跳过",
                    "command": "vnc:ensure-relay",
                    "exit_code": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:16:04",
                "level": "SUCCESS",
                "message": "上游开通孤儿单补偿告警",
                "task_key": null,
                "status": "success",
                "duration_ms": 8,
                "summary": {
                    "output": "发现 2 个上游开通失败孤儿单（模式：dry-run 告警）\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| service_id | order_id | user_id | retry_count | provision_error                            |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| 187        | 272      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n| 188        | 273      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n当前为 dry-run 模式，仅告警未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。",
                    "command": "provision:retry-failed",
                    "exit_code": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:16:04",
                "level": "SUCCESS",
                "message": "账单与充值清理",
                "task_key": null,
                "status": "success",
                "duration_ms": 19,
                "summary": {
                    "recharges_expired": 0,
                    "invoices_cancelled": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:16:04",
                "level": "SUCCESS",
                "message": "优惠券活动发放",
                "task_key": null,
                "status": "success",
                "duration_ms": 3,
                "summary": {
                    "failed": 0,
                    "matched": 0,
                    "skipped": 0,
                    "triggered": 0,
                    "coupon_ids": []
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:16:04",
                "level": "SUCCESS",
                "message": "用户产品状态同步",
                "task_key": null,
                "status": "success",
                "duration_ms": 64177,
                "summary": {
                    "failed": 0,
                    "synced": 76,
                    "scanned": 76,
                    "skipped": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:15:00",
                "level": "SUCCESS",
                "message": "服务生命周期维护",
                "task_key": null,
                "status": "success",
                "duration_ms": 8,
                "summary": {
                    "cancelled": 0,
                    "suspended": 0,
                    "suspend_notified": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:15:00",
                "level": "SUCCESS",
                "message": "推荐奖励释放",
                "task_key": null,
                "status": "success",
                "duration_ms": 3,
                "summary": {
                    "released": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:15:00",
                "level": "SUCCESS",
                "message": "接口认证刷新",
                "task_key": null,
                "status": "success",
                "duration_ms": 92,
                "summary": {
                    "failed": 2,
                    "matched": 2,
                    "refreshed": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:01:16",
                "level": "SUCCESS",
                "message": "充值账单补偿",
                "task_key": null,
                "status": "success",
                "duration_ms": 2,
                "summary": {
                    "output": "[充值补偿] 无需处理的悬空记录",
                    "command": "payment:compensate-recharge-invoices",
                    "exit_code": 0,
                    "parameters": {
                        "--limit": 200
                    }
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:01:16",
                "level": "SUCCESS",
                "message": "首页缓存预热",
                "task_key": null,
                "status": "success",
                "duration_ms": 1064,
                "summary": {
                    "output": "开始预热缓存...\r\n预热首页数据...\r\n首页缓存预热完成\r\n预热产品类型...\r\n产品类型缓存预热完成\r\n预热产品分组...\r\n产品分组缓存预热完成\r\n所有缓存预热完成，耗时 1.06s",
                    "command": "app:warmup-site-cache",
                    "exit_code": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:01:15",
                "level": "SUCCESS",
                "message": "队列积压消费",
                "task_key": null,
                "status": "success",
                "duration_ms": 1018,
                "summary": {
                    "command": "queue:work",
                    "exit_code": 0,
                    "parameters": {
                        "--queue": "provision,referral,notification,coupon,default",
                        "--sleep": 1,
                        "--tries": 3,
                        "--timeout": 1200,
                        "--max-time": 50,
                        "--stop-when-empty": true
                    }
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:01:14",
                "level": "SUCCESS",
                "message": "VNC Relay 守护",
                "task_key": null,
                "status": "success",
                "duration_ms": 21,
                "summary": {
                    "output": "VNC Relay 已在运行 (127.0.0.1:8100)，跳过",
                    "command": "vnc:ensure-relay",
                    "exit_code": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:01:14",
                "level": "SUCCESS",
                "message": "上游开通孤儿单补偿告警",
                "task_key": null,
                "status": "success",
                "duration_ms": 5,
                "summary": {
                    "output": "发现 2 个上游开通失败孤儿单（模式：dry-run 告警）\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| service_id | order_id | user_id | retry_count | provision_error                            |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| 187        | 272      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n| 188        | 273      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n当前为 dry-run 模式，仅告警未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。",
                    "command": "provision:retry-failed",
                    "exit_code": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:01:14",
                "level": "FAILED",
                "message": "账户余额在线对账",
                "task_key": null,
                "status": "failed",
                "duration_ms": 33,
                "summary": null,
                "error_msg": "Artisan command [reconcile:account-balance] exited with code 1. Output: 对账完成：差异用户账户数 178\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| user_id | account_type    | latest_balance_after | account_balance | diff   |\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| 2       | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 3       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 5       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 7       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 8       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 10      | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 11      | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 12      | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 14      | cash            | 5.10                 | 3.30            | 1.80   |\r\n| 16      "
            },
            {
                "time": "2026-07-05 11:01:14",
                "level": "SUCCESS",
                "message": "账单与充值清理",
                "task_key": null,
                "status": "success",
                "duration_ms": 29,
                "summary": {
                    "recharges_expired": 0,
                    "invoices_cancelled": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:01:14",
                "level": "SUCCESS",
                "message": "优惠券活动发放",
                "task_key": null,
                "status": "success",
                "duration_ms": 2,
                "summary": {
                    "failed": 0,
                    "matched": 0,
                    "skipped": 0,
                    "triggered": 0,
                    "coupon_ids": []
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:01:14",
                "level": "SUCCESS",
                "message": "账单自动化维护",
                "task_key": null,
                "status": "success",
                "duration_ms": 64,
                "summary": {
                    "renew_notice_sent": 0,
                    "invoice_overdue_sent": 0,
                    "invoice_pre_due_sent": 0,
                    "renew_orders_created": 0,
                    "fulfillment_recovered": 0,
                    "invoices_marked_overdue": 0,
                    "auto_renew_upcoming_sent": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:01:13",
                "level": "SUCCESS",
                "message": "用户产品状态同步",
                "task_key": null,
                "status": "success",
                "duration_ms": 73028,
                "summary": {
                    "failed": 0,
                    "synced": 76,
                    "scanned": 76,
                    "skipped": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:00:00",
                "level": "SUCCESS",
                "message": "服务生命周期维护",
                "task_key": null,
                "status": "success",
                "duration_ms": 3,
                "summary": {
                    "cancelled": 0,
                    "suspended": 0,
                    "suspend_notified": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:00:00",
                "level": "SUCCESS",
                "message": "推荐奖励释放",
                "task_key": null,
                "status": "success",
                "duration_ms": 3,
                "summary": {
                    "released": 0
                },
                "error_msg": null
            },
            {
                "time": "2026-07-05 11:00:00",
                "level": "SUCCESS",
                "message": "服务自动续费",
                "task_key": null,
                "status": "success",
                "duration_ms": 12,
                "summary": {
                    "paid": 0,
                    "failed": 0,
                    "blocked": 0,
                    "matched": 0,
                    "pending": 0,
                    "skipped": 0,
                    "recovered": 0
                },
                "error_msg": null
            }
        ],
        "settings_snapshot": [
            {
                "label": "到期自动暂停",
                "value": "已开启",
                "note": "到期后 1 天暂停，任务周期：每 15 分钟"
            },
            {
                "label": "续费提醒",
                "value": "已开启",
                "note": "提醒天数：7 / 3 / 1 天前，任务周期：每小时第 00 分钟"
            },
            {
                "label": "工单自动关闭",
                "value": "已开启",
                "note": "员工回复后 72 小时自动关闭，任务周期：每天 00:00"
            },
            {
                "label": "未付款账单清理",
                "value": "已开启",
                "note": "未付款账单保留 1 小时，任务周期：每 15 分钟"
            }
        ]
    },
    "timestamp": 1783240516
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:16  
· 响应状态码：200  
· 调用方式：GET /api/admin/schedules/overview  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ScheduleTaskController@overview`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:schedule.view`
