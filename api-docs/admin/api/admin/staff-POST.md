# staff

**请求方法**：POST  
**请求路径**：`/api/admin/staff`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| username | string | 是 | 请求体参数；校验规则：required\|string\|max:50\|regex:/^[A-Za-z0-9_.@-]+$/\|unique:admin_users,username；来源：StoreAdminStaffRequest |
| password | string | 是 | 请求体参数；校验规则：required\|string\|min:8\|max:64；来源：StoreAdminStaffRequest |
| nickname | string | 否 | 请求体参数；校验规则：nullable\|string\|max:50；来源：StoreAdminStaffRequest |
| email | string(email) | 否 | 请求体参数；校验规则：nullable\|email\|max:100\|unique:admin_users,email；来源：StoreAdminStaffRequest |
| role_id | integer | 是 | 请求体参数；校验规则：required\|integer\|exists:roles,id；来源：StoreAdminStaffRequest |
| status | integer | 否 | 请求体参数；校验规则：nullable\|integer\|in:0,1；来源：StoreAdminStaffRequest |

### 请求示例（完整 JSON）
```json
{
    "username": "string",
    "password": "password123",
    "nickname": "string",
    "email": "user@example.com",
    "role_id": 1,
    "status": "1"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.id | integer | 业务字段；由源码静态提取 |
| data.username | string | 业务字段；由源码静态提取 |
| data.nickname | string | 业务字段；由源码静态提取 |
| data.email | string | 业务字段；由源码静态提取 |
| data.status | integer | 业务字段；由源码静态提取 |
| data.role_id | integer | 业务字段；由源码静态提取 |
| data.role | string | 业务字段；由源码静态提取 |
| data.role_label | string | 业务字段；由源码静态提取 |
| data.permissions | array | 业务字段；由源码静态提取 |
| data.last_login_at | string(datetime) | 业务字段；由源码静态提取 |
| data.last_login_ip | string | 业务字段；由源码静态提取 |
| data.created_at | string(datetime) | 业务字段；由源码静态提取 |
| data.updated_at | string(datetime) | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "员工创建成功",
    "data": {
        "id": 1,
        "username": "string",
        "nickname": "string",
        "email": "string",
        "status": [],
        "role_id": 1,
        "role": "string",
        "role_label": "string",
        "permissions": [],
        "last_login_at": "2026-07-05 12:00:00",
        "last_login_ip": "string",
        "created_at": "2026-07-05 12:00:00",
        "updated_at": "2026-07-05 12:00:00"
    },
    "timestamp": 1760000000
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：接口为写操作、删除操作、支付/退款/开通/服务控制/通知发送/上游动作之一，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\AdminStaffController@store`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:staff.manage`
