# toggle-status

**请求方法**：POST  
**请求路径**：`/api/admin/staff/{staff}/toggle-status`  
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
| staff | integer\|string | 是 | 路径参数；来自路由占位 `{staff}` |

### 请求示例（完整 JSON）
```json
{}
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
    "message": "员工状态已更新",
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
· 控制器动作：`App\Http\Controllers\Admin\AdminStaffController@toggleStatus`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:staff.manage`
