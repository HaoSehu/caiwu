# staff

**请求方法**：GET  
**请求路径**：`/api/admin/staff`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListAdminStaffRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：ListAdminStaffRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：ListAdminStaffRequest |
| status | integer | 否 | 查询参数；校验规则：nullable\|integer\|in:0,1；来源：ListAdminStaffRequest |
| role_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListAdminStaffRequest |

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
| data.list.username | string | 真实调用返回字段 |
| data.list.nickname | string | 真实调用返回字段 |
| data.list.email | string | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.role_id | integer | 真实调用返回字段 |
| data.list.role | object | 真实调用返回字段 |
| data.list.role.id | integer | 真实调用返回字段 |
| data.list.role.name | string | 真实调用返回字段 |
| data.list.role.label | string | 真实调用返回字段 |
| data.list.role_label | string | 真实调用返回字段 |
| data.list.permissions | array | 真实调用返回字段 |
| data.list.last_login_at | string | 真实调用返回字段 |
| data.list.last_login_ip | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
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
                "id": 3,
                "username": "test@123456",
                "nickname": "test@123456",
                "email": "",
                "status": 1,
                "role_id": 4,
                "role": {
                    "id": 4,
                    "name": "visitor",
                    "label": "访客"
                },
                "role_label": "访客",
                "permissions": [
                    "dashboard.view",
                    "user.list",
                    "user.detail",
                    "verification.list",
                    "order.list",
                    "order.detail",
                    "invoice.list",
                    "invoice.detail",
                    "ticket.list",
                    "product.list",
                    "supplier.list",
                    "supplier.detail",
                    "settings.view",
                    "integration_plugin.view",
                    "schedule.view",
                    "site.view",
                    "log.list",
                    "referral.list",
                    "referral_withdrawal.list",
                    "finance.report",
                    "member_level.list",
                    "content.list",
                    "staff.list",
                    "role.list",
                    "permission.list"
                ],
                "last_login_at": "2026-07-03 15:08:07",
                "last_login_ip": "127.0.0.1",
                "created_at": "2026-07-03 15:08:02",
                "updated_at": "2026-07-03 15:08:07"
            }
        ],
        "total": 2,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240516
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:16  
· 响应状态码：200  
· 调用方式：GET /api/admin/staff  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\AdminStaffController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:staff.list`
