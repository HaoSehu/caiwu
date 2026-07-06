# tickets

**请求方法**：GET  
**请求路径**：`/api/admin/tickets`  
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
| page_size | integer | 否 | 查询参数；控制器通过 `$request->input()` 读取；未发现 FormRequest 明确规则 |

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
| data.list | array | 分页列表数据 |
| data.list.id | integer | 真实调用返回字段 |
| data.list.user_id | integer | 真实调用返回字段 |
| data.list.department | string | 真实调用返回字段 |
| data.list.subject | string | 真实调用返回字段 |
| data.list.priority | integer | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.service_id | integer | 真实调用返回字段 |
| data.list.assignee_id | null | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.list.close_reason | string | 真实调用返回字段 |
| data.list.user | object | 真实调用返回字段 |
| data.list.user.id | integer | 真实调用返回字段 |
| data.list.user.email | string | 真实调用返回字段 |
| data.list.user.nickname | string | 真实调用返回字段 |
| data.list.assignee | null | 真实调用返回字段 |
| data.list.close_reason_label | string | 真实调用返回字段 |
| data.total | integer | 总条数 |
| data.page | integer | 当前页码 |
| data.page_size | integer | 每页数量 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "id": 49,
                "user_id": 379,
                "department": "support",
                "subject": "为什么续费不了",
                "priority": 4,
                "status": 3,
                "service_id": 61,
                "assignee_id": null,
                "created_at": "2026-07-01T02:10:04.000000Z",
                "updated_at": "2026-07-04T16:02:36.000000Z",
                "close_reason": "auto",
                "user": {
                    "id": 379,
                    "email": "185772235@qq.com",
                    "nickname": ""
                },
                "assignee": null,
                "close_reason_label": "自动关闭"
            }
        ],
        "total": 49,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240517
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:17  
· 响应状态码：200  
· 调用方式：GET /api/admin/tickets  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\TicketController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:ticket.list`
