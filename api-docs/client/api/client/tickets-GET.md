# tickets

**请求方法**：GET  
**请求路径**：`/api/client/tickets`  
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
| data.list.replies | array | 真实调用返回字段 |
| data.list.replies.id | integer | 真实调用返回字段 |
| data.list.replies.ticket_id | integer | 真实调用返回字段 |
| data.list.replies.user_id | integer | 真实调用返回字段 |
| data.list.replies.content | string | 真实调用返回字段 |
| data.list.replies.is_staff | integer | 真实调用返回字段 |
| data.list.replies.attachments | array | 真实调用返回字段 |
| data.list.replies.quote_reply_id | null | 真实调用返回字段 |
| data.list.replies.recalled_at | null | 真实调用返回字段 |
| data.list.replies.created_at | string | 真实调用返回字段 |
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
                "id": 35,
                "user_id": 1,
                "department": "support",
                "subject": "dasgfFD",
                "priority": 2,
                "status": 3,
                "service_id": 89,
                "assignee_id": null,
                "created_at": "2026-05-29T03:15:03.000000Z",
                "updated_at": "2026-06-09T05:36:09.000000Z",
                "close_reason": "admin",
                "replies": [
                    {
                        "id": 105,
                        "ticket_id": 35,
                        "user_id": 1,
                        "content": "1111",
                        "is_staff": 0,
                        "attachments": [],
                        "quote_reply_id": null,
                        "recalled_at": null,
                        "created_at": "2026-05-29T03:17:04.000000Z"
                    }
                ]
            }
        ],
        "total": 3,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240539
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:39  
· 响应状态码：200  
· 调用方式：GET /api/client/tickets  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\TicketController@index`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器返回分页结构`  
· 中间件：`api, auth:sanctum, ensure.client`
