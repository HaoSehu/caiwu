# stats

**请求方法**：GET  
**请求路径**：`/api/admin/dashboard/stats`  
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
| data.counts | object | 真实调用返回字段 |
| data.counts.total_users | integer | 真实调用返回字段 |
| data.counts.total_invoices | integer | 真实调用返回字段 |
| data.counts.active_services | integer | 真实调用返回字段 |
| data.counts.open_tickets | integer | 真实调用返回字段 |
| data.today | object | 真实调用返回字段 |
| data.today.new_users | integer | 真实调用返回字段 |
| data.today.new_invoices | integer | 真实调用返回字段 |
| data.today.income | integer | 真实调用返回字段 |
| data.month | object | 真实调用返回字段 |
| data.month.income | integer | 真实调用返回字段 |
| data.month.new_users | integer | 真实调用返回字段 |
| data.month.new_invoices | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "counts": {
            "total_users": 463,
            "total_invoices": 2163,
            "active_services": 76,
            "open_tickets": 0
        },
        "today": {
            "new_users": 0,
            "new_invoices": 5,
            "income": 1
        },
        "month": {
            "income": 429,
            "new_users": 0,
            "new_invoices": 25
        }
    },
    "timestamp": 1783240486
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:46  
· 响应状态码：200  
· 调用方式：GET /api/admin/dashboard/stats  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\DashboardController@stats`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:dashboard.view`
