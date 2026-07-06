# {staff}

**请求方法**：GET  
**请求路径**：`/api/admin/staff/{staff}`  
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
| staff | integer\|string | 是 | 路径参数；来自路由占位 `{staff}` |

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
| data.username | string | 真实调用返回字段 |
| data.nickname | string | 真实调用返回字段 |
| data.email | string | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.role_id | integer | 真实调用返回字段 |
| data.role | object | 真实调用返回字段 |
| data.role.id | integer | 真实调用返回字段 |
| data.role.name | string | 真实调用返回字段 |
| data.role.label | string | 真实调用返回字段 |
| data.role_label | string | 真实调用返回字段 |
| data.permissions | array | 真实调用返回字段 |
| data.last_login_at | string | 真实调用返回字段 |
| data.last_login_ip | string | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| data.updated_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 1,
        "username": "cerbo",
        "nickname": "管理员",
        "email": "2908990438@qq.com",
        "status": 1,
        "role_id": 1,
        "role": {
            "id": 1,
            "name": "super_admin",
            "label": "超级管理员"
        },
        "role_label": "超级管理员",
        "permissions": [
            "*"
        ],
        "last_login_at": "2026-07-05 16:08:01",
        "last_login_ip": "127.0.0.1",
        "created_at": "2025-01-16 17:56:07",
        "updated_at": "2026-07-05 16:08:01"
    },
    "timestamp": 1783240517
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:17  
· 响应状态码：200  
· 调用方式：GET /api/admin/staff/{staff}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\AdminStaffController@show`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:staff.list`
