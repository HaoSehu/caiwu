# 管理员登录

**请求方法**：POST  
**请求路径**：`/api/admin/login`  
**调试状态**：⚠️ 异常

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| username | string | 是 | 请求体参数；校验规则：required\|string；来源：LoginRequest |
| password | string | 是 | 请求体参数；校验规则：required\|string\|min:6；来源：LoginRequest |

### 请求示例（完整 JSON）
```json
{
    "username": "cerbo",
    "password": "***redacted***"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.token | string | 管理员 Sanctum Token |
| data.admin.id | integer | 管理员 ID |
| data.admin.username | string | 管理员用户名 |
| data.admin.nickname | string | 管理员昵称 |
| data.admin.email | string | 管理员邮箱 |
| data.admin.role | string | 角色名称 |
| data.admin.permissions | array | 权限码列表 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "登录成功",
    "data": {
        "token": "***已脱敏***",
        "admin": {
            "id": 1,
            "username": "cerbo",
            "nickname": "管理员",
            "email": "admin@example.com",
            "role": "超级管理员",
            "permissions": [
                "dashboard.view"
            ]
        }
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:30:05  
· 响应状态码：401  
· 调用方式：POST /api/admin/login  
· 验证方式：真实调用；使用测试管理员账号 `cerbo` 登录，本地库返回用户名或密码错误  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
正常成功结构来自 `AuthService::adminLogin()`。本次真实调用异常是本地管理员账号密码校验失败，非参数结构缺失。

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\AuthController@login`  
· 请求校验：`App\Http\Requests\Admin\Auth\LoginRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；AuthService::adminLogin() 服务返回数组字段`  
· 中间件：`api, throttle:5,1`
