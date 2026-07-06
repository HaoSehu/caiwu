# schedule

**请求方法**：GET  
**请求路径**：`/api/admin/logs/schedule`  
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
| data.items | array | 真实调用返回字段 |
| data.items.id | integer | 真实调用返回字段 |
| data.items.task_name | string | 真实调用返回字段 |
| data.items.status | string | 真实调用返回字段 |
| data.items.duration_ms | integer | 真实调用返回字段 |
| data.items.summary | object | 真实调用返回字段 |
| data.items.summary.failed_products | integer | 真实调用返回字段 |
| data.items.summary.synced_products | integer | 真实调用返回字段 |
| data.items.summary.matched_products | integer | 真实调用返回字段 |
| data.items.summary.skipped_products | integer | 真实调用返回字段 |
| data.items.summary.matched_suppliers | integer | 真实调用返回字段 |
| data.items.error_msg | null | 真实调用返回字段 |
| data.items.started_at | string | 真实调用返回字段 |
| data.items.finished_at | string | 真实调用返回字段 |
| data.items.created_at | string | 真实调用返回字段 |
| data.items.updated_at | string | 真实调用返回字段 |
| data.total | integer | 总条数 |
| data.page | integer | 当前页码 |
| data.per_page | integer | 真实调用返回字段 |
| data.last_page | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "items": [
            {
                "id": 67913,
                "task_name": "上游产品配置同步",
                "status": "success",
                "duration_ms": 15901,
                "summary": {
                    "failed_products": 0,
                    "synced_products": 124,
                    "matched_products": 124,
                    "skipped_products": 0,
                    "matched_suppliers": 2
                },
                "error_msg": null,
                "started_at": "2026-07-05T04:17:22.000000Z",
                "finished_at": "2026-07-05T04:17:37.000000Z",
                "created_at": "2026-07-05T04:17:37.000000Z",
                "updated_at": "2026-07-05T04:17:37.000000Z"
            },
            {
                "id": 67912,
                "task_name": "首页缓存预热",
                "status": "success",
                "duration_ms": 1603,
                "summary": {
                    "output": "开始预热缓存...\r\n预热首页数据...\r\n首页缓存预热完成\r\n预热产品类型...\r\n产品类型缓存预热完成\r\n预热产品分组...\r\n产品分组缓存预热完成\r\n所有缓存预热完成，耗时 1.6s",
                    "command": "app:warmup-site-cache",
                    "exit_code": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:16:06.000000Z",
                "finished_at": "2026-07-05T03:16:07.000000Z",
                "created_at": "2026-07-05T03:16:07.000000Z",
                "updated_at": "2026-07-05T03:16:07.000000Z"
            },
            {
                "id": 67911,
                "task_name": "队列积压消费",
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
                "error_msg": null,
                "started_at": "2026-07-05T03:16:05.000000Z",
                "finished_at": "2026-07-05T03:16:06.000000Z",
                "created_at": "2026-07-05T03:16:06.000000Z",
                "updated_at": "2026-07-05T03:16:06.000000Z"
            },
            {
                "id": 67910,
                "task_name": "VNC Relay 守护",
                "status": "success",
                "duration_ms": 14,
                "summary": {
                    "output": "VNC Relay 已在运行 (127.0.0.1:8100)，跳过",
                    "command": "vnc:ensure-relay",
                    "exit_code": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:16:04.000000Z",
                "finished_at": "2026-07-05T03:16:04.000000Z",
                "created_at": "2026-07-05T03:16:04.000000Z",
                "updated_at": "2026-07-05T03:16:04.000000Z"
            },
            {
                "id": 67909,
                "task_name": "上游开通孤儿单补偿告警",
                "status": "success",
                "duration_ms": 8,
                "summary": {
                    "output": "发现 2 个上游开通失败孤儿单（模式：dry-run 告警）\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| service_id | order_id | user_id | retry_count | provision_error                            |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| 187        | 272      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n| 188        | 273      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n当前为 dry-run 模式，仅告警未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。",
                    "command": "provision:retry-failed",
                    "exit_code": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:16:04.000000Z",
                "finished_at": "2026-07-05T03:16:04.000000Z",
                "created_at": "2026-07-05T03:16:04.000000Z",
                "updated_at": "2026-07-05T03:16:04.000000Z"
            },
            {
                "id": 67908,
                "task_name": "账单与充值清理",
                "status": "success",
                "duration_ms": 19,
                "summary": {
                    "recharges_expired": 0,
                    "invoices_cancelled": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:16:04.000000Z",
                "finished_at": "2026-07-05T03:16:04.000000Z",
                "created_at": "2026-07-05T03:16:04.000000Z",
                "updated_at": "2026-07-05T03:16:04.000000Z"
            },
            {
                "id": 67907,
                "task_name": "优惠券活动发放",
                "status": "success",
                "duration_ms": 3,
                "summary": {
                    "failed": 0,
                    "matched": 0,
                    "skipped": 0,
                    "triggered": 0,
                    "coupon_ids": []
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:16:04.000000Z",
                "finished_at": "2026-07-05T03:16:04.000000Z",
                "created_at": "2026-07-05T03:16:04.000000Z",
                "updated_at": "2026-07-05T03:16:04.000000Z"
            },
            {
                "id": 67906,
                "task_name": "用户产品状态同步",
                "status": "success",
                "duration_ms": 64177,
                "summary": {
                    "failed": 0,
                    "synced": 76,
                    "scanned": 76,
                    "skipped": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:15:00.000000Z",
                "finished_at": "2026-07-05T03:16:04.000000Z",
                "created_at": "2026-07-05T03:16:04.000000Z",
                "updated_at": "2026-07-05T03:16:04.000000Z"
            },
            {
                "id": 67905,
                "task_name": "服务生命周期维护",
                "status": "success",
                "duration_ms": 8,
                "summary": {
                    "cancelled": 0,
                    "suspended": 0,
                    "suspend_notified": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:15:00.000000Z",
                "finished_at": "2026-07-05T03:15:00.000000Z",
                "created_at": "2026-07-05T03:15:00.000000Z",
                "updated_at": "2026-07-05T03:15:00.000000Z"
            },
            {
                "id": 67904,
                "task_name": "推荐奖励释放",
                "status": "success",
                "duration_ms": 3,
                "summary": {
                    "released": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:15:00.000000Z",
                "finished_at": "2026-07-05T03:15:00.000000Z",
                "created_at": "2026-07-05T03:15:00.000000Z",
                "updated_at": "2026-07-05T03:15:00.000000Z"
            },
            {
                "id": 67903,
                "task_name": "接口认证刷新",
                "status": "success",
                "duration_ms": 92,
                "summary": {
                    "failed": 2,
                    "matched": 2,
                    "refreshed": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:15:00.000000Z",
                "finished_at": "2026-07-05T03:15:00.000000Z",
                "created_at": "2026-07-05T03:15:00.000000Z",
                "updated_at": "2026-07-05T03:15:00.000000Z"
            },
            {
                "id": 67902,
                "task_name": "充值账单补偿",
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
                "error_msg": null,
                "started_at": "2026-07-05T03:01:16.000000Z",
                "finished_at": "2026-07-05T03:01:16.000000Z",
                "created_at": "2026-07-05T03:01:16.000000Z",
                "updated_at": "2026-07-05T03:01:16.000000Z"
            },
            {
                "id": 67901,
                "task_name": "首页缓存预热",
                "status": "success",
                "duration_ms": 1064,
                "summary": {
                    "output": "开始预热缓存...\r\n预热首页数据...\r\n首页缓存预热完成\r\n预热产品类型...\r\n产品类型缓存预热完成\r\n预热产品分组...\r\n产品分组缓存预热完成\r\n所有缓存预热完成，耗时 1.06s",
                    "command": "app:warmup-site-cache",
                    "exit_code": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:01:15.000000Z",
                "finished_at": "2026-07-05T03:01:16.000000Z",
                "created_at": "2026-07-05T03:01:16.000000Z",
                "updated_at": "2026-07-05T03:01:16.000000Z"
            },
            {
                "id": 67900,
                "task_name": "队列积压消费",
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
                "error_msg": null,
                "started_at": "2026-07-05T03:01:14.000000Z",
                "finished_at": "2026-07-05T03:01:15.000000Z",
                "created_at": "2026-07-05T03:01:15.000000Z",
                "updated_at": "2026-07-05T03:01:15.000000Z"
            },
            {
                "id": 67899,
                "task_name": "VNC Relay 守护",
                "status": "success",
                "duration_ms": 21,
                "summary": {
                    "output": "VNC Relay 已在运行 (127.0.0.1:8100)，跳过",
                    "command": "vnc:ensure-relay",
                    "exit_code": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:01:14.000000Z",
                "finished_at": "2026-07-05T03:01:14.000000Z",
                "created_at": "2026-07-05T03:01:14.000000Z",
                "updated_at": "2026-07-05T03:01:14.000000Z"
            },
            {
                "id": 67898,
                "task_name": "上游开通孤儿单补偿告警",
                "status": "success",
                "duration_ms": 5,
                "summary": {
                    "output": "发现 2 个上游开通失败孤儿单（模式：dry-run 告警）\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| service_id | order_id | user_id | retry_count | provision_error                            |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n| 187        | 272      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n| 188        | 273      | 1       | 0           | 上游购物车结算失败，上游业务接口暂时不可用 |\r\n+------------+----------+---------+-------------+--------------------------------------------+\r\n当前为 dry-run 模式，仅告警未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。",
                    "command": "provision:retry-failed",
                    "exit_code": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:01:14.000000Z",
                "finished_at": "2026-07-05T03:01:14.000000Z",
                "created_at": "2026-07-05T03:01:14.000000Z",
                "updated_at": "2026-07-05T03:01:14.000000Z"
            },
            {
                "id": 67897,
                "task_name": "账户余额在线对账",
                "status": "failed",
                "duration_ms": 33,
                "summary": null,
                "error_msg": "Artisan command [reconcile:account-balance] exited with code 1. Output: 对账完成：差异用户账户数 178\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| user_id | account_type    | latest_balance_after | account_balance | diff   |\r\n+---------+-----------------+----------------------+-----------------+--------+\r\n| 2       | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 3       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 5       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 7       | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 8       | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 10      | cash            | 0.10                 | 0.00            | 0.10   |\r\n| 11      | cash            | 0.20                 | 0.00            | 0.20   |\r\n| 12      | cash            | 1.10                 | 0.90            | 0.20   |\r\n| 14      | cash            | 5.10                 | 3.30            | 1.80   |\r\n| 16      ",
                "started_at": "2026-07-05T03:01:14.000000Z",
                "finished_at": "2026-07-05T03:01:14.000000Z",
                "created_at": "2026-07-05T03:01:14.000000Z",
                "updated_at": "2026-07-05T03:01:14.000000Z"
            },
            {
                "id": 67896,
                "task_name": "账单与充值清理",
                "status": "success",
                "duration_ms": 29,
                "summary": {
                    "recharges_expired": 0,
                    "invoices_cancelled": 0
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:01:14.000000Z",
                "finished_at": "2026-07-05T03:01:14.000000Z",
                "created_at": "2026-07-05T03:01:14.000000Z",
                "updated_at": "2026-07-05T03:01:14.000000Z"
            },
            {
                "id": 67895,
                "task_name": "优惠券活动发放",
                "status": "success",
                "duration_ms": 2,
                "summary": {
                    "failed": 0,
                    "matched": 0,
                    "skipped": 0,
                    "triggered": 0,
                    "coupon_ids": []
                },
                "error_msg": null,
                "started_at": "2026-07-05T03:01:14.000000Z",
                "finished_at": "2026-07-05T03:01:14.000000Z",
                "created_at": "2026-07-05T03:01:14.000000Z",
                "updated_at": "2026-07-05T03:01:14.000000Z"
            },
            {
                "id": 67894,
                "task_name": "账单自动化维护",
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
                "error_msg": null,
                "started_at": "2026-07-05T03:01:14.000000Z",
                "finished_at": "2026-07-05T03:01:14.000000Z",
                "created_at": "2026-07-05T03:01:14.000000Z",
                "updated_at": "2026-07-05T03:01:14.000000Z"
            }
        ],
        "total": 67913,
        "page": 1,
        "per_page": 20,
        "last_page": 3396
    },
    "timestamp": 1783240506
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:06  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/schedule  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@scheduleLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
