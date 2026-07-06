# {role}

**请求方法**：GET  
**请求路径**：`/api/admin/roles/{role}`  
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
| role | integer\|string | 是 | 路径参数；来自路由占位 `{role}` |

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
| data.name | string | 真实调用返回字段 |
| data.label | string | 真实调用返回字段 |
| data.permissions | array | 真实调用返回字段 |
| data.stored_permissions | array | 真实调用返回字段 |
| data.admin_count | integer | 真实调用返回字段 |
| data.is_builtin | boolean | 真实调用返回字段 |
| data.is_locked | boolean | 真实调用返回字段 |
| data.can_edit_permissions | boolean | 真实调用返回字段 |
| data.can_delete | boolean | 真实调用返回字段 |
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
        "name": "super_admin",
        "label": "超级管理员",
        "permissions": [
            "*"
        ],
        "stored_permissions": [
            "*"
        ],
        "admin_count": 1,
        "is_builtin": true,
        "is_locked": true,
        "can_edit_permissions": false,
        "can_delete": false,
        "created_at": "2026-03-25 14:34:30",
        "updated_at": "2026-07-05 16:35:15"
    },
    "timestamp": 1783240515
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:15  
· 响应状态码：200  
· 调用方式：GET /api/admin/roles/{role}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\AdminRoleController@show`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:role.list`
