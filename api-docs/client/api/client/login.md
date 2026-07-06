# 客户登录

**请求方法**：POST  
**请求路径**：`/api/client/login`  
**调试状态**：✅ 通过

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| account | string | 是 | 请求体参数；校验规则：required\|string\|max:100\|closure:自定义校验；来源：LoginRequest |
| password | string | 是 | 请求体参数；校验规则：required\|string\|min:6；来源：LoginRequest |

### 请求示例（完整 JSON）
```json
{
    "account": "2***@qq.com",
    "password": "***redacted***"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.token | string | 真实调用返回字段 |
| data.user | object | 真实调用返回字段 |
| data.user.id | integer | 真实调用返回字段 |
| data.user.email | string | 真实调用返回字段 |
| data.user.phone | string | 真实调用返回字段 |
| data.user.nickname | string | 真实调用返回字段 |
| data.user.display_name | string | 真实调用返回字段 |
| data.user.login_email_alert | integer | 真实调用返回字段 |
| data.user.login_notify | integer | 真实调用返回字段 |
| data.user.login_location_alert | integer | 真实调用返回字段 |
| data.user.password_change_alert | string | 真实调用返回字段 |
| data.user.phone_change_alert | integer | 真实调用返回字段 |
| data.user.email_change_alert | integer | 真实调用返回字段 |
| data.user.marketing_alert | integer | 真实调用返回字段 |
| data.user.cash_balance | string | 真实调用返回字段 |
| data.user.credit_limit | string | 真实调用返回字段 |
| data.user.referral_frozen_balance | string | 真实调用返回字段 |
| data.user.referral_available_balance | string | 真实调用返回字段 |
| data.user.referral_pending_withdrawal_balance | string | 真实调用返回字段 |
| data.user.referral_withdrawn_balance | string | 真实调用返回字段 |
| data.user.last_login_at | string | 真实调用返回字段 |
| data.user.last_login_ip | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "登录成功",
    "data": {
        "token": "***已脱敏***",
        "user": {
            "id": 1,
            "email": "2908990438@qq.com",
            "phone": "19219178808",
            "nickname": "李维佳",
            "display_name": "李维佳",
            "login_email_alert": 1,
            "login_notify": 1,
            "login_location_alert": 1,
            "password_change_alert": "***已脱敏***",
            "phone_change_alert": 1,
            "email_change_alert": 1,
            "marketing_alert": 0,
            "cash_balance": "99706.30",
            "credit_limit": "0.00",
            "referral_frozen_balance": "0.00",
            "referral_available_balance": "0.00",
            "referral_pending_withdrawal_balance": "0.00",
            "referral_withdrawn_balance": "0.00",
            "last_login_at": "2026-07-05 16:34:43",
            "last_login_ip": "127.0.0.1"
        }
    },
    "timestamp": 1783240483
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:43  
· 响应状态码：200  
· 调用方式：POST /api/client/login  
· 验证方式：真实调用；登录接口用于获取本轮调试 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\AuthController@login`  
· 请求校验：`App\Http\Requests\Client\Auth\LoginRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；AuthService::clientLogin() 服务返回数组字段`  
· 中间件：`api, throttle:5,1,client-auth-login`
