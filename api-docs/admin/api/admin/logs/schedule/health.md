# health

**请求方法**：GET  
**请求路径**：`/api/admin/logs/schedule/health`  
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
| data | array | 业务数据 |
| data.task_name | string | 真实调用返回字段 |
| data.last_run_at | string | 真实调用返回字段 |
| data.minutes_since_last_run | integer | 真实调用返回字段 |
| data.health | string | 真实调用返回字段 |
| data.total_runs_24h | integer | 真实调用返回字段 |
| data.failed_count_24h | integer | 真实调用返回字段 |
| data.avg_duration_ms | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": [
        {
            "task_name": "优惠券活动发放",
            "last_run_at": "2026-07-05 11:16:04",
            "minutes_since_last_run": 319,
            "health": "critical",
            "total_runs_24h": 273,
            "failed_count_24h": 0,
            "avg_duration_ms": 9
        },
        {
            "task_name": "VNC Relay 守护",
            "last_run_at": "2026-07-05 11:16:04",
            "minutes_since_last_run": 319,
            "health": "critical",
            "total_runs_24h": 277,
            "failed_count_24h": 0,
            "avg_duration_ms": 18
        },
        {
            "task_name": "队列积压消费",
            "last_run_at": "2026-07-05 11:16:06",
            "minutes_since_last_run": 319,
            "health": "critical",
            "total_runs_24h": 270,
            "failed_count_24h": 0,
            "avg_duration_ms": 1030
        },
        {
            "task_name": "推荐奖励释放",
            "last_run_at": "2026-07-05 11:15:00",
            "minutes_since_last_run": 320,
            "health": "critical",
            "total_runs_24h": 60,
            "failed_count_24h": 0,
            "avg_duration_ms": 6
        },
        {
            "task_name": "账单与充值清理",
            "last_run_at": "2026-07-05 11:16:04",
            "minutes_since_last_run": 319,
            "health": "critical",
            "total_runs_24h": 85,
            "failed_count_24h": 0,
            "avg_duration_ms": 154
        },
        {
            "task_name": "上游开通孤儿单补偿告警",
            "last_run_at": "2026-07-05 11:16:04",
            "minutes_since_last_run": 319,
            "health": "critical",
            "total_runs_24h": 61,
            "failed_count_24h": 0,
            "avg_duration_ms": 10
        },
        {
            "task_name": "首页缓存预热",
            "last_run_at": "2026-07-05 11:16:07",
            "minutes_since_last_run": 318,
            "health": "critical",
            "total_runs_24h": 87,
            "failed_count_24h": 0,
            "avg_duration_ms": 1136
        },
        {
            "task_name": "服务生命周期维护",
            "last_run_at": "2026-07-05 11:15:00",
            "minutes_since_last_run": 320,
            "health": "critical",
            "total_runs_24h": 84,
            "failed_count_24h": 0,
            "avg_duration_ms": 233
        },
        {
            "task_name": "接口认证刷新",
            "last_run_at": "2026-07-05 11:15:00",
            "minutes_since_last_run": 320,
            "health": "critical",
            "total_runs_24h": 50,
            "failed_count_24h": 0,
            "avg_duration_ms": 405
        },
        {
            "task_name": "用户产品状态同步",
            "last_run_at": "2026-07-05 11:16:04",
            "minutes_since_last_run": 319,
            "health": "critical",
            "total_runs_24h": 50,
            "failed_count_24h": 0,
            "avg_duration_ms": 86901
        },
        {
            "task_name": "服务自动续费",
            "last_run_at": "2026-07-05 11:00:00",
            "minutes_since_last_run": 335,
            "health": "critical",
            "total_runs_24h": 13,
            "failed_count_24h": 0,
            "avg_duration_ms": 441
        },
        {
            "task_name": "账单自动化维护",
            "last_run_at": "2026-07-05 11:01:14",
            "minutes_since_last_run": 333,
            "health": "critical",
            "total_runs_24h": 14,
            "failed_count_24h": 0,
            "avg_duration_ms": 4615
        },
        {
            "task_name": "账户余额在线对账",
            "last_run_at": "2026-07-05 11:01:14",
            "minutes_since_last_run": 333,
            "health": "critical",
            "total_runs_24h": 14,
            "failed_count_24h": 14,
            "avg_duration_ms": 50
        },
        {
            "task_name": "充值账单补偿",
            "last_run_at": "2026-07-05 11:01:16",
            "minutes_since_last_run": 333,
            "health": "critical",
            "total_runs_24h": 14,
            "failed_count_24h": 0,
            "avg_duration_ms": 16
        },
        {
            "task_name": "上游产品配置同步",
            "last_run_at": "2026-07-05 12:17:37",
            "minutes_since_last_run": 257,
            "health": "critical",
            "total_runs_24h": 2,
            "failed_count_24h": 0,
            "avg_duration_ms": 23066
        },
        {
            "task_name": "工单自动关闭",
            "last_run_at": "2026-07-05 00:02:36",
            "minutes_since_last_run": 992,
            "health": "critical",
            "total_runs_24h": 1,
            "failed_count_24h": 0,
            "avg_duration_ms": 101
        }
    ],
    "timestamp": 1783240506
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:06  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/schedule/health  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@scheduleHealth`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
