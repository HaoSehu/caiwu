# {id}

**请求方法**：GET  
**请求路径**：`/api/client/tickets/{id}`  
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
| id | integer\|string | 是 | 路径参数；来自路由占位 `{id}` |

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
| data.id | integer | 真实调用返回字段 |
| data.user_id | integer | 真实调用返回字段 |
| data.department | string | 真实调用返回字段 |
| data.subject | string | 真实调用返回字段 |
| data.priority | integer | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.service_id | integer | 真实调用返回字段 |
| data.assignee_id | null | 真实调用返回字段 |
| data.close_reason | null | 真实调用返回字段 |
| data.close_reason_label | null | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| data.updated_at | string | 真实调用返回字段 |
| data.user | object | 真实调用返回字段 |
| data.user.id | integer | 真实调用返回字段 |
| data.user.email | string | 真实调用返回字段 |
| data.user.nickname | string | 真实调用返回字段 |
| data.user.display_name | string | 真实调用返回字段 |
| data.service | object | 真实调用返回字段 |
| data.service.id | integer | 真实调用返回字段 |
| data.service.name | string | 真实调用返回字段 |
| data.service.display_name | string | 真实调用返回字段 |
| data.service.domain | string | 真实调用返回字段 |
| data.service.status | integer | 真实调用返回字段 |
| data.service.billing_cycle | string | 真实调用返回字段 |
| data.service.amount | string | 真实调用返回字段 |
| data.service.expires_at | string | 真实调用返回字段 |
| data.service.connection | object | 真实调用返回字段 |
| data.service.connection.dedicated_ip | string | 真实调用返回字段 |
| data.service.connection.internal_ip | string | 真实调用返回字段 |
| data.service.connection.username | string | 真实调用返回字段 |
| data.service.connection.password | string | 真实调用返回字段 |
| data.service.connection.has_password | string | 真实调用返回字段 |
| data.service.connection.port | integer | 真实调用返回字段 |
| data.service.specs | array | 真实调用返回字段 |
| data.service.specs.key | string | 真实调用返回字段 |
| data.service.specs.label | string | 真实调用返回字段 |
| data.service.specs.value | string | 真实调用返回字段 |
| data.assignee | null | 真实调用返回字段 |
| data.replies | array | 真实调用返回字段 |
| data.replies.id | integer | 真实调用返回字段 |
| data.replies.ticket_id | integer | 真实调用返回字段 |
| data.replies.user_id | integer | 真实调用返回字段 |
| data.replies.content | string | 真实调用返回字段 |
| data.replies.is_staff | integer | 真实调用返回字段 |
| data.replies.sender_name | string | 真实调用返回字段 |
| data.replies.attachments | array | 真实调用返回字段 |
| data.replies.recalled | boolean | 真实调用返回字段 |
| data.replies.recalled_at | null | 真实调用返回字段 |
| data.replies.quote | null | 真实调用返回字段 |
| data.replies.created_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 10,
        "user_id": 1,
        "department": "support",
        "subject": "2222",
        "priority": 2,
        "status": 3,
        "service_id": 88,
        "assignee_id": null,
        "close_reason": null,
        "close_reason_label": null,
        "created_at": "2026-04-19 18:20:24",
        "updated_at": "2026-04-19 19:02:11",
        "user": {
            "id": 1,
            "email": "2908990438@qq.com",
            "nickname": "李维佳",
            "display_name": "李维佳"
        },
        "service": {
            "id": 88,
            "name": "美国1区精品网 2H2G",
            "display_name": "gscs / 美国1区精品网 2H2G",
            "domain": "ser707625720719",
            "status": 4,
            "billing_cycle": "monthly",
            "amount": "20.00",
            "expires_at": "2026-04-19 13:30:03",
            "connection": {
                "dedicated_ip": "",
                "internal_ip": "",
                "username": "root",
                "password": "***已脱敏***",
                "has_password": "***已脱敏***",
                "port": 0
            },
            "specs": [
                {
                    "key": "area",
                    "label": "区域",
                    "value": "美国"
                },
                {
                    "key": "os",
                    "label": "操作系统",
                    "value": "Ubuntu-16.04-x64"
                },
                {
                    "key": "cpu",
                    "label": "CPU",
                    "value": "2核"
                },
                {
                    "key": "memory",
                    "label": "内存",
                    "value": "2G"
                },
                {
                    "key": "bw",
                    "label": "带宽",
                    "value": "50Mbps"
                },
                {
                    "key": "ip_num",
                    "label": "IP数量",
                    "value": "1"
                },
                {
                    "key": "data_disk_size",
                    "label": "数据盘",
                    "value": "50G"
                }
            ]
        },
        "assignee": null,
        "replies": [
            {
                "id": 10,
                "ticket_id": 10,
                "user_id": 1,
                "content": "2222",
                "is_staff": 0,
                "sender_name": "李维佳",
                "attachments": [],
                "recalled": false,
                "recalled_at": null,
                "quote": null,
                "created_at": "2026-04-19 18:20:24"
            },
            {
                "id": 11,
                "ticket_id": 10,
                "user_id": 1,
                "content": "11",
                "is_staff": 0,
                "sender_name": "李维佳",
                "attachments": [],
                "recalled": false,
                "recalled_at": null,
                "quote": null,
                "created_at": "2026-04-19 18:21:16"
            },
            {
                "id": 12,
                "ticket_id": 10,
                "user_id": 1,
                "content": "",
                "is_staff": 1,
                "sender_name": "管理员",
                "attachments": [
                    {
                        "id": "f45abe3af8cb6fe675d91952ecaaa85c3eb63ff5cd1822d97a5b51706e68c016",
                        "name": "ticket-admin-1-182143-cq7xsfsojxoi.png",
                        "path": "f45abe3af8cb6fe675d91952ecaaa85c3eb63ff5cd1822d97a5b51706e68c016",
                        "url": null,
                        "deleted": true,
                        "type": "image"
                    }
                ],
                "recalled": false,
                "recalled_at": null,
                "quote": null,
                "created_at": "2026-04-19 18:21:44"
            },
            {
                "id": 13,
                "ticket_id": 10,
                "user_id": 1,
                "content": "",
                "is_staff": 0,
                "sender_name": "李维佳",
                "attachments": [
                    {
                        "id": "f0ed0d36d27d23eb00aaefda626f8b1bbd7ed01b3751ff6375293c08fa7a4017",
                        "name": "ticket-client-1-185426-rcmf1npd3tqq.png",
                        "path": "f0ed0d36d27d23eb00aaefda626f8b1bbd7ed01b3751ff6375293c08fa7a4017",
                        "url": null,
                        "deleted": true,
                        "type": "image"
                    }
                ],
                "recalled": false,
                "recalled_at": null,
                "quote": null,
                "created_at": "2026-04-19 18:54:28"
            },
            {
                "id": 14,
                "ticket_id": 10,
                "user_id": 1,
                "content": "",
                "is_staff": 0,
                "sender_name": "李维佳",
                "attachments": [
                    {
                        "id": "16267f8cd45543e9a4d9393b0c72b6ce38b2a79968a1673c998100dc4d0647c4",
                        "name": "ticket-client-1-185810-yuxqaepf2yni.png",
                        "path": "16267f8cd45543e9a4d9393b0c72b6ce38b2a79968a1673c998100dc4d0647c4",
                        "url": null,
                        "deleted": true,
                        "type": "image"
                    }
                ],
                "recalled": false,
                "recalled_at": null,
                "quote": null,
                "created_at": "2026-04-19 18:58:11"
            }
        ]
    },
    "timestamp": 1783240540
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:40  
· 响应状态码：200  
· 调用方式：GET /api/client/tickets/{id}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\TicketController@show`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器/服务/资源可静态确认 data 字段`  
· 中间件：`api, auth:sanctum, ensure.client`
