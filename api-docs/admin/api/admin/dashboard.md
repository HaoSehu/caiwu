# dashboard

**请求方法**：GET  
**请求路径**：`/api/admin/dashboard`  
**调试状态**：⚠️ 异常

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
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.counts | object | 统计数量 |
| data.counts.total_users | integer | 用户总数 |
| data.counts.total_invoices | integer | 账单总数 |
| data.counts.active_services | integer | 运行中服务数 |
| data.counts.open_tickets | integer | 未关闭工单数 |
| data.today | object | 今日统计 |
| data.today.new_users | integer | 今日新增用户数 |
| data.today.new_invoices | integer | 今日新增账单数 |
| data.today.income | number | 今日已付收入 |
| data.month | object | 本月统计 |
| data.month.income | number | 本月已付收入 |
| data.month.new_users | integer | 本月新增用户数 |
| data.month.new_invoices | integer | 本月新增账单数 |
| data.recent_invoices | array | 最近账单列表 |
| data.recent_invoices[].id | integer | 账单 ID |
| data.recent_invoices[].invoice_no | string | 账单编号 |
| data.recent_invoices[].amount | string | 账单金额 |
| data.recent_invoices[].status | integer | 账单状态码 |
| data.recent_invoices[].status_label | string | 账单状态文案 |
| data.recent_invoices[].type | string | 账单类型 |
| data.recent_invoices[].created_at | string|null | 创建时间 |
| data.recent_invoices[].user.nickname | string | 用户昵称 |
| data.recent_invoices[].user.email | string | 用户邮箱；按后台隐私策略脱敏 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "counts": {
            "total_users": 0,
            "total_invoices": 0,
            "active_services": 0,
            "open_tickets": 0
        },
        "today": {
            "new_users": 0,
            "new_invoices": 0,
            "income": 0
        },
        "month": {
            "income": 0,
            "new_users": 0,
            "new_invoices": 0
        },
        "recent_invoices": [
            {
                "id": 1,
                "invoice_no": "INV202607050001",
                "amount": "100.00",
                "status": 1,
                "status_label": "已支付",
                "type": "new",
                "created_at": "2026-07-05 16:00:00",
                "user": {
                    "nickname": "张三",
                    "email": "z***@example.com"
                }
            }
        ]
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:46  
· 响应状态码：500  
· 调用方式：GET /api/admin/dashboard  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
正常成功结构来自 `DashboardService::overview()`。本次真实调用异常是 `DashboardService::buildRecentInvoices()` 闭包未捕获 `$privacy` 导致的 500，文档已按源码补齐成功响应结构。

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\DashboardController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:dashboard.view`
