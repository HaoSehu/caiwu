# overview

**请求方法**：GET  
**请求路径**：`/api/admin/logs/cleanup/overview`  
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
| data.database | object | 真实调用返回字段 |
| data.database.sms | integer | 真实调用返回字段 |
| data.database.email | integer | 真实调用返回字段 |
| data.database.api | integer | 真实调用返回字段 |
| data.database.admin_login | integer | 真实调用返回字段 |
| data.database.business_audit | integer | 真实调用返回字段 |
| data.database.schedule_run | integer | 真实调用返回字段 |
| data.file | object | 真实调用返回字段 |
| data.file.path | string | 真实调用返回字段 |
| data.file.exists | boolean | 真实调用返回字段 |
| data.file.size_bytes | integer | 真实调用返回字段 |
| data.file.updated_at | string | 真实调用返回字段 |
| data.file.task_log_count | integer | 真实调用返回字段 |
| data.file.runtime_log_count | integer | 真实调用返回字段 |
| data.file.system_log_count | integer | 真实调用返回字段 |
| data.supported_cleanup_types | array | 真实调用返回字段 |
| data.supported_cleanup_types.value | string | 真实调用返回字段 |
| data.supported_cleanup_types.label | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "database": {
            "sms": 100,
            "email": 1382,
            "api": 119081,
            "admin_login": 289,
            "business_audit": 10869,
            "schedule_run": 67913
        },
        "file": {
            "path": "storage/logs/laravel.log",
            "exists": true,
            "size_bytes": 41761508,
            "updated_at": "2026-07-05 16:34:46",
            "task_log_count": 185,
            "runtime_log_count": 1419,
            "system_log_count": 1419
        },
        "supported_cleanup_types": [
            {
                "value": "sms",
                "label": "短信日志"
            },
            {
                "value": "email",
                "label": "邮件日志"
            },
            {
                "value": "api",
                "label": "API日志"
            },
            {
                "value": "admin_login",
                "label": "管理员登录日志"
            },
            {
                "value": "business_audit",
                "label": "系统日志（业务审计）"
            },
            {
                "value": "schedule_run",
                "label": "调度执行日志"
            },
            {
                "value": "task",
                "label": "自动任务日志"
            },
            {
                "value": "runtime",
                "label": "运行日志"
            },
            {
                "value": "all_db",
                "label": "全部数据库日志"
            },
            {
                "value": "all_file",
                "label": "全部文件日志"
            },
            {
                "value": "all",
                "label": "全部日志"
            }
        ]
    },
    "timestamp": 1783240497
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:57  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/cleanup/overview  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@cleanupOverview`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
