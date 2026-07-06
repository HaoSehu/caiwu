# tasks

**请求方法**：GET  
**请求路径**：`/api/admin/logs/tasks`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：GeneralLogListRequest |
| per_page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：GeneralLogListRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| actor_keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| description_keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| ip_address | string | 否 | 查询参数；校验规则：nullable\|string\|max:45；来源：GeneralLogListRequest |
| level | string | 否 | 查询参数；校验规则：nullable\|in:DEBUG,INFO,NOTICE,WARNING,ERROR,CRITICAL,ALERT,EMERGENCY；来源：GeneralLogListRequest |
| module | string | 否 | 查询参数；校验规则：nullable\|string\|max:60；来源：GeneralLogListRequest |
| method | string | 否 | 查询参数；校验规则：nullable\|in:GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD；来源：GeneralLogListRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|string\|max:20；来源：GeneralLogListRequest |
| task_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:60；来源：GeneralLogListRequest |
| user_type | string | 否 | 查询参数；校验规则：nullable\|in:admin,client,guest；来源：GeneralLogListRequest |
| gateway | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：GeneralLogListRequest |
| gateway_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| driver_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| plugin_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：GeneralLogListRequest |
| trace_id | string | 否 | 查询参数；校验规则：nullable\|string\|max:64；来源：GeneralLogListRequest |
| action | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：GeneralLogListRequest |
| result_status | string | 否 | 查询参数；校验规则：nullable\|in:success,failed,pending,unknown；来源：GeneralLogListRequest |
| actor_type | string | 否 | 查询参数；校验规则：nullable\|in:admin,client,system,sub_account；来源：GeneralLogListRequest |
| subject_type | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：GeneralLogListRequest |
| start_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date；来源：GeneralLogListRequest |
| end_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date；来源：GeneralLogListRequest |

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
| data.current_page | integer | 真实调用返回字段 |
| data.data | array | 真实调用返回字段 |
| data.data.id | string | 真实调用返回字段 |
| data.data.source | string | 真实调用返回字段 |
| data.data.time | string | 真实调用返回字段 |
| data.data.started_at | string | 真实调用返回字段 |
| data.data.finished_at | string | 真实调用返回字段 |
| data.data.task_key | string | 真实调用返回字段 |
| data.data.task_name | string | 真实调用返回字段 |
| data.data.task_title | string | 真实调用返回字段 |
| data.data.status | string | 真实调用返回字段 |
| data.data.level | string | 真实调用返回字段 |
| data.data.duration_ms | integer | 真实调用返回字段 |
| data.data.summary | object | 真实调用返回字段 |
| data.data.summary.failed_products | integer | 真实调用返回字段 |
| data.data.summary.synced_products | integer | 真实调用返回字段 |
| data.data.summary.matched_products | integer | 真实调用返回字段 |
| data.data.summary.skipped_products | integer | 真实调用返回字段 |
| data.data.summary.matched_suppliers | integer | 真实调用返回字段 |
| data.data.error_msg | string | 真实调用返回字段 |
| data.data.message | string | 真实调用返回字段 |
| data.data.raw | string | 真实调用返回字段 |
| data.first_page_url | string | 真实调用返回字段 |
| data.from | integer | 真实调用返回字段 |
| data.last_page | integer | 真实调用返回字段 |
| data.last_page_url | string | 真实调用返回字段 |
| data.links | array | 真实调用返回字段 |
| data.links.url | null | 真实调用返回字段 |
| data.links.label | string | 真实调用返回字段 |
| data.links.page | null | 真实调用返回字段 |
| data.links.active | boolean | 真实调用返回字段 |
| data.next_page_url | string | 真实调用返回字段 |
| data.path | string | 真实调用返回字段 |
| data.per_page | integer | 真实调用返回字段 |
| data.prev_page_url | null | 真实调用返回字段 |
| data.to | integer | 真实调用返回字段 |
| data.total | integer | 总条数 |
| data.summary | array | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": "schedule-67913",
                "source": "schedule_run_logs",
                "time": "2026-07-05 12:17:22",
                "started_at": "2026-07-05 12:17:22",
                "finished_at": "2026-07-05 12:17:37",
                "task_key": "上游产品配置同步",
                "task_name": "上游产品配置同步",
                "task_title": "上游产品配置同步",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 15901,
                "summary": {
                    "failed_products": 0,
                    "synced_products": 124,
                    "matched_products": 124,
                    "skipped_products": 0,
                    "matched_suppliers": 2
                },
                "error_msg": "",
                "message": "{\"failed_products\":0,\"synced_products\":124,\"matched_products\":124,\"skipped_products\":0,\"matched_suppliers\":2}",
                "raw": ""
            },
            {
                "id": "schedule-67912",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:16:06",
                "started_at": "2026-07-05 11:16:06",
                "finished_at": "2026-07-05 11:16:07",
                "task_key": "首页缓存预热",
                "task_name": "首页缓存预热",
                "task_title": "首页缓存预热",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 1603,
                "summary": {
                    "output": "开始预热缓存...\r\n预热首页数据...\r\n首页缓存预热完成\r\n预热产品类型...\r\n产品类型缓存预热完成\r\n预热产品分组...\r\n产品分组缓存预热完成\r\n所有缓存预热完成，耗时 1.6s",
                    "command": "app:warmup-site-cache",
                    "exit_code": 0
                },
                "error_msg": "",
                "message": "{\"output\":\"开始预热缓存...\r\n预热首页数据...\r\n首页缓存预热完成\r\n预热产品类型...\r\n产品类型缓存预热完成\r\n预热产品分组...\r\n产品分组缓存预热完成\r\n所有缓存预热完成，耗时 1.6s\",\"command\":\"app:warmup-site-cache\",\"exit_code\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67911",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:16:05",
                "started_at": "2026-07-05 11:16:05",
                "finished_at": "2026-07-05 11:16:06",
                "task_key": "队列积压消费",
                "task_name": "队列积压消费",
                "task_title": "队列积压消费",
                "status": "success",
                "level": "SUCCESS",
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
                "error_msg": "",
                "message": "{\"command\":\"queue:work\",\"exit_code\":0,\"parameters\":{\"--queue\":\"provision,referral,notification,coupon,default\",\"--sleep\":1,\"--tries\":3,\"--timeout\":1200,\"--max-time\":50,\"--stop-when-empty\":true}}",
                "raw": ""
            },
            {
                "id": "schedule-67910",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:16:04",
                "started_at": "2026-07-05 11:16:04",
                "finished_at": "2026-07-05 11:16:04",
                "task_key": "VNC Relay 守护",
                "task_name": "VNC Relay 守护",
                "task_title": "VNC Relay 守护",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 14,
                "summary": {
                    "output": "VNC Relay 已在运行 (127.0.0.1:8100)，跳过",
                    "command": "vnc:ensure-relay",
                    "exit_code": 0
                },
                "error_msg": "",
                "message": "{\"output\":\"VNC Relay 已在运行 (127.0.0.1:8100)，跳过\",\"command\":\"vnc:ensure-relay\",\"exit_code\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67909",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:16:04",
                "started_at": "2026-07-05 11:16:04",
                "finished_at": "2026-07-05 11:16:04",
                "task_key": "上游开通孤儿单补偿告警",
                "task_name": "上游开通孤儿单补偿告警",
                "task_title": "上游开通孤儿单补偿告警",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 8,
                "summary": {
                    "output": "发现 2 个上游开通失败孤儿单（模式：dry-run 告警）\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| service_id | order_id | user_id | retry_count | provision_error                            |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| 187        | 272      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n| 188        | 273      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n当前为 dry-run 模式，仅告警未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。",
                    "command": "provision:retry-failed",
                    "exit_code": 0
                },
                "error_msg": "",
                "message": "{\"output\":\"发现 2 个上游开通失败孤儿单（模式：dry-run 告警）\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| service_id | order_id | user_id | retry_count | provision_error                            |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| 187        | 272      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n| 188        | 273      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n当前为 dry-run 模式，仅告警未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。\",\"command\":\"provision:retry-failed\",\"exit_code\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67908",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:16:04",
                "started_at": "2026-07-05 11:16:04",
                "finished_at": "2026-07-05 11:16:04",
                "task_key": "账单与充值清理",
                "task_name": "账单与充值清理",
                "task_title": "账单与充值清理",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 19,
                "summary": {
                    "recharges_expired": 0,
                    "invoices_cancelled": 0
                },
                "error_msg": "",
                "message": "{\"recharges_expired\":0,\"invoices_cancelled\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67907",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:16:04",
                "started_at": "2026-07-05 11:16:04",
                "finished_at": "2026-07-05 11:16:04",
                "task_key": "优惠券活动发放",
                "task_name": "优惠券活动发放",
                "task_title": "优惠券活动发放",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 3,
                "summary": {
                    "failed": 0,
                    "matched": 0,
                    "skipped": 0,
                    "triggered": 0,
                    "coupon_ids": []
                },
                "error_msg": "",
                "message": "{\"failed\":0,\"matched\":0,\"skipped\":0,\"triggered\":0,\"coupon_ids\":[]}",
                "raw": ""
            },
            {
                "id": "schedule-67906",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:15:00",
                "started_at": "2026-07-05 11:15:00",
                "finished_at": "2026-07-05 11:16:04",
                "task_key": "用户产品状态同步",
                "task_name": "用户产品状态同步",
                "task_title": "用户产品状态同步",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 64177,
                "summary": {
                    "failed": 0,
                    "synced": 76,
                    "scanned": 76,
                    "skipped": 0
                },
                "error_msg": "",
                "message": "{\"failed\":0,\"synced\":76,\"scanned\":76,\"skipped\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67905",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:15:00",
                "started_at": "2026-07-05 11:15:00",
                "finished_at": "2026-07-05 11:15:00",
                "task_key": "服务生命周期维护",
                "task_name": "服务生命周期维护",
                "task_title": "服务生命周期维护",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 8,
                "summary": {
                    "cancelled": 0,
                    "suspended": 0,
                    "suspend_notified": 0
                },
                "error_msg": "",
                "message": "{\"cancelled\":0,\"suspended\":0,\"suspend_notified\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67904",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:15:00",
                "started_at": "2026-07-05 11:15:00",
                "finished_at": "2026-07-05 11:15:00",
                "task_key": "推荐奖励释放",
                "task_name": "推荐奖励释放",
                "task_title": "推荐奖励释放",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 3,
                "summary": {
                    "released": 0
                },
                "error_msg": "",
                "message": "{\"released\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67903",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:15:00",
                "started_at": "2026-07-05 11:15:00",
                "finished_at": "2026-07-05 11:15:00",
                "task_key": "接口认证刷新",
                "task_name": "接口认证刷新",
                "task_title": "接口认证刷新",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 92,
                "summary": {
                    "failed": 2,
                    "matched": 2,
                    "refreshed": 0
                },
                "error_msg": "",
                "message": "{\"failed\":2,\"matched\":2,\"refreshed\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67902",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:01:16",
                "started_at": "2026-07-05 11:01:16",
                "finished_at": "2026-07-05 11:01:16",
                "task_key": "充值账单补偿",
                "task_name": "充值账单补偿",
                "task_title": "充值账单补偿",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 2,
                "summary": {
                    "output": "[充值补偿] 无需处理的悬空记录",
                    "command": "payment:compensate-recharge-invoices",
                    "exit_code": 0,
                    "parameters": {
                        "--limit": 200
                    }
                },
                "error_msg": "",
                "message": "{\"output\":\"[充值补偿] 无需处理的悬空记录\",\"command\":\"payment:compensate-recharge-invoices\",\"exit_code\":0,\"parameters\":{\"--limit\":200}}",
                "raw": ""
            },
            {
                "id": "schedule-67901",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:01:15",
                "started_at": "2026-07-05 11:01:15",
                "finished_at": "2026-07-05 11:01:16",
                "task_key": "首页缓存预热",
                "task_name": "首页缓存预热",
                "task_title": "首页缓存预热",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 1064,
                "summary": {
                    "output": "开始预热缓存...\r\n预热首页数据...\r\n首页缓存预热完成\r\n预热产品类型...\r\n产品类型缓存预热完成\r\n预热产品分组...\r\n产品分组缓存预热完成\r\n所有缓存预热完成，耗时 1.06s",
                    "command": "app:warmup-site-cache",
                    "exit_code": 0
                },
                "error_msg": "",
                "message": "{\"output\":\"开始预热缓存...\r\n预热首页数据...\r\n首页缓存预热完成\r\n预热产品类型...\r\n产品类型缓存预热完成\r\n预热产品分组...\r\n产品分组缓存预热完成\r\n所有缓存预热完成，耗时 1.06s\",\"command\":\"app:warmup-site-cache\",\"exit_code\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67900",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:01:14",
                "started_at": "2026-07-05 11:01:14",
                "finished_at": "2026-07-05 11:01:15",
                "task_key": "队列积压消费",
                "task_name": "队列积压消费",
                "task_title": "队列积压消费",
                "status": "success",
                "level": "SUCCESS",
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
                "error_msg": "",
                "message": "{\"command\":\"queue:work\",\"exit_code\":0,\"parameters\":{\"--queue\":\"provision,referral,notification,coupon,default\",\"--sleep\":1,\"--tries\":3,\"--timeout\":1200,\"--max-time\":50,\"--stop-when-empty\":true}}",
                "raw": ""
            },
            {
                "id": "schedule-67899",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:01:14",
                "started_at": "2026-07-05 11:01:14",
                "finished_at": "2026-07-05 11:01:14",
                "task_key": "VNC Relay 守护",
                "task_name": "VNC Relay 守护",
                "task_title": "VNC Relay 守护",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 21,
                "summary": {
                    "output": "VNC Relay 已在运行 (127.0.0.1:8100)，跳过",
                    "command": "vnc:ensure-relay",
                    "exit_code": 0
                },
                "error_msg": "",
                "message": "{\"output\":\"VNC Relay 已在运行 (127.0.0.1:8100)，跳过\",\"command\":\"vnc:ensure-relay\",\"exit_code\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67898",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:01:14",
                "started_at": "2026-07-05 11:01:14",
                "finished_at": "2026-07-05 11:01:14",
                "task_key": "上游开通孤儿单补偿告警",
                "task_name": "上游开通孤儿单补偿告警",
                "task_title": "上游开通孤儿单补偿告警",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 5,
                "summary": {
                    "output": "发现 2 个上游开通失败孤儿单（模式：dry-run 告警）\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| service_id | order_id | user_id | retry_count | provision_error                            |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| 187        | 272      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n| 188        | 273      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n当前为 dry-run 模式，仅告警未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。",
                    "command": "provision:retry-failed",
                    "exit_code": 0
                },
                "error_msg": "",
                "message": "{\"output\":\"发现 2 个上游开通失败孤儿单（模式：dry-run 告警）\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| service_id | order_id | user_id | retry_count | provision_error                            |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| 187        | 272      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n| 188        | 273      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n当前为 dry-run 模式，仅告警未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。\",\"command\":\"provision:retry-failed\",\"exit_code\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67897",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:01:14",
                "started_at": "2026-07-05 11:01:14",
                "finished_at": "2026-07-05 11:01:14",
                "task_key": "账户余额在线对账",
                "task_name": "账户余额在线对账",
                "task_title": "账户余额在线对账",
                "status": "failed",
                "level": "ERROR",
                "duration_ms": 33,
                "summary": null,
                "error_msg": "Artisan command [reconcile:account-balance] exited with code 1. Output: 对账完成：差异用户账户数 178\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| user_id | account_type    | latest_balance_after | account_balance | diff   |\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| 2       | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 3       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 5       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 7       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 8       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 10      | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 11      | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 12      | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 14      | cash            | 5.10                 | 3.30            | 1.80   |\r\n| 16",
                "message": "Artisan command [reconcile:account-balance] exited with code 1. Output: 对账完成：差异用户账户数 178\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| user_id | account_type    | latest_balance_after | account_balance | diff   |\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| 2       | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 3       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 5       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 7       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 8       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 10      | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 11      | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 12      | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 14      | cash            | 5.10                 | 3.30            | 1.80   |\r\n| 16",
                "raw": "Artisan command [reconcile:account-balance] exited with code 1. Output: 对账完成：差异用户账户数 178\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| user_id | account_type    | latest_balance_after | account_balance | diff   |\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| 2       | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 3       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 5       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 7       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 8       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 10      | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 11      | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 12      | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 14      | cash            | 5.10                 | 3.30            | 1.80   |\r\n| 16"
            },
            {
                "id": "schedule-67896",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:01:14",
                "started_at": "2026-07-05 11:01:14",
                "finished_at": "2026-07-05 11:01:14",
                "task_key": "账单与充值清理",
                "task_name": "账单与充值清理",
                "task_title": "账单与充值清理",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 29,
                "summary": {
                    "recharges_expired": 0,
                    "invoices_cancelled": 0
                },
                "error_msg": "",
                "message": "{\"recharges_expired\":0,\"invoices_cancelled\":0}",
                "raw": ""
            },
            {
                "id": "schedule-67895",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:01:14",
                "started_at": "2026-07-05 11:01:14",
                "finished_at": "2026-07-05 11:01:14",
                "task_key": "优惠券活动发放",
                "task_name": "优惠券活动发放",
                "task_title": "优惠券活动发放",
                "status": "success",
                "level": "SUCCESS",
                "duration_ms": 2,
                "summary": {
                    "failed": 0,
                    "matched": 0,
                    "skipped": 0,
                    "triggered": 0,
                    "coupon_ids": []
                },
                "error_msg": "",
                "message": "{\"failed\":0,\"matched\":0,\"skipped\":0,\"triggered\":0,\"coupon_ids\":[]}",
                "raw": ""
            },
            {
                "id": "schedule-67894",
                "source": "schedule_run_logs",
                "time": "2026-07-05 11:01:14",
                "started_at": "2026-07-05 11:01:14",
                "finished_at": "2026-07-05 11:01:14",
                "task_key": "账单自动化维护",
                "task_name": "账单自动化维护",
                "task_title": "账单自动化维护",
                "status": "success",
                "level": "SUCCESS",
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
                "error_msg": "",
                "message": "{\"renew_notice_sent\":0,\"invoice_overdue_sent\":0,\"invoice_pre_due_sent\":0,\"renew_orders_created\":0,\"fulfillment_recovered\":0,\"invoices_marked_overdue\":0,\"auto_renew_upcoming_sent\":0}",
                "raw": ""
            }
        ],
        "first_page_url": "/?page=1",
        "from": 1,
        "last_page": 50,
        "last_page_url": "/?page=50",
        "links": [
            {
                "url": null,
                "label": "pagination.previous",
                "page": null,
                "active": false
            },
            {
                "url": "/?page=1",
                "label": "1",
                "page": 1,
                "active": true
            },
            {
                "url": "/?page=2",
                "label": "2",
                "page": 2,
                "active": false
            },
            {
                "url": "/?page=3",
                "label": "3",
                "page": 3,
                "active": false
            },
            {
                "url": "/?page=4",
                "label": "4",
                "page": 4,
                "active": false
            },
            {
                "url": "/?page=5",
                "label": "5",
                "page": 5,
                "active": false
            },
            {
                "url": "/?page=6",
                "label": "6",
                "page": 6,
                "active": false
            },
            {
                "url": "/?page=7",
                "label": "7",
                "page": 7,
                "active": false
            },
            {
                "url": "/?page=8",
                "label": "8",
                "page": 8,
                "active": false
            },
            {
                "url": "/?page=9",
                "label": "9",
                "page": 9,
                "active": false
            },
            {
                "url": "/?page=10",
                "label": "10",
                "page": 10,
                "active": false
            },
            {
                "url": null,
                "label": "...",
                "active": false
            },
            {
                "url": "/?page=49",
                "label": "49",
                "page": 49,
                "active": false
            },
            {
                "url": "/?page=50",
                "label": "50",
                "page": 50,
                "active": false
            },
            {
                "url": "/?page=2",
                "label": "pagination.next",
                "page": 2,
                "active": false
            }
        ],
        "next_page_url": "/?page=2",
        "path": "/",
        "per_page": 20,
        "prev_page_url": null,
        "to": 20,
        "total": 1000,
        "summary": []
    },
    "timestamp": 1783240508
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:08  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/tasks  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@taskLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
