# 创建用户

**请求方法**：POST  
**请求路径**：`/api/admin/users`  
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
| email | string(email) | 是 | 请求体参数；校验规则：required\|email\|max:100；来源：StoreUserRequest |
| password | string | 是 | 请求体参数；校验规则：required\|string\|min:6；来源：StoreUserRequest |
| nickname | string | 否 | 请求体参数；校验规则：nullable\|string\|max:50；来源：StoreUserRequest |
| phone | string | 否 | 请求体参数；校验规则：nullable\|string\|max:20\|closure:自定义校验\|unique:users,phone,NULL,id；来源：StoreUserRequest |
| status | string | 否 | 请求体参数；校验规则：nullable\|in:0,1；来源：StoreUserRequest |
| credit_limit | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0；来源：StoreUserRequest |

### 请求示例（完整 JSON）
```json
{
    "email": "user@example.com",
    "password": "password123",
    "nickname": "string",
    "phone": "13800138000",
    "status": "1",
    "credit_limit": "10.00"
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
| data.email | string | 业务字段；由源码静态提取 |
| data.nickname | string | 业务字段；由源码静态提取 |
| data.display_name | string | 业务字段；由源码静态提取 |
| data.phone | string | 业务字段；由源码静态提取 |
| data.company | string | 业务字段；由源码静态提取 |
| data.qq | string | 业务字段；由源码静态提取 |
| data.admin_note | string | 业务字段；由源码静态提取 |
| data.referral_code | string | 业务字段；由源码静态提取 |
| data.referrer_user_id | integer | 业务字段；由源码静态提取 |
| data.member_level_id | integer | 业务字段；由源码静态提取 |
| data.total_sales_amount | string(decimal) | 业务字段；由源码静态提取 |
| data.member_level | object | 业务字段；由源码静态提取 |
| data.cash_balance | string(decimal) | 业务字段；由源码静态提取 |
| data.credit_limit | string | 业务字段；由源码静态提取 |
| data.referral_frozen_balance | string(decimal) | 业务字段；由源码静态提取 |
| data.referral_available_balance | string(decimal) | 业务字段；由源码静态提取 |
| data.referral_pending_withdrawal_balance | string(decimal) | 业务字段；由源码静态提取 |
| data.referral_withdrawn_balance | string(decimal) | 业务字段；由源码静态提取 |
| data.active_services_count | string | 业务字段；由源码静态提取 |
| data.status | integer | 业务字段；由源码静态提取 |
| data.is_verified | boolean | 业务字段；由源码静态提取 |
| data.real_name | string | 业务字段；由源码静态提取 |
| data.id_card_masked | string | 业务字段；由源码静态提取 |
| data.verification_certify_id | integer | 业务字段；由源码静态提取 |
| data.referred_at | string(datetime) | 业务字段；由源码静态提取 |
| data.alipay_real_name | string | 业务字段；由源码静态提取 |
| data.alipay_account | object | 业务字段；由源码静态提取 |
| data.last_login_at | string(datetime) | 业务字段；由源码静态提取 |
| data.last_login_ip | string | 业务字段；由源码静态提取 |
| data.created_at | string(datetime) | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "创建成功",
    "data": {
        "id": 1,
        "email": "string",
        "nickname": "string",
        "display_name": "string",
        "phone": "string",
        "company": "string",
        "qq": "string",
        "admin_note": "string",
        "referral_code": "string",
        "referrer_user_id": 1,
        "member_level_id": 1,
        "total_sales_amount": "0.00",
        "member_level": [],
        "cash_balance": "0.00",
        "credit_limit": "string",
        "referral_frozen_balance": "0.00",
        "referral_available_balance": "0.00",
        "referral_pending_withdrawal_balance": "0.00",
        "referral_withdrawn_balance": "0.00",
        "active_services_count": "string",
        "status": [],
        "is_verified": true,
        "real_name": "string",
        "id_card_masked": "string",
        "verification_certify_id": 1,
        "referred_at": "2026-07-05 12:00:00",
        "alipay_real_name": "string",
        "alipay_account": [],
        "last_login_at": "2026-07-05 12:00:00",
        "last_login_ip": "string",
        "created_at": "2026-07-05 12:00:00"
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
· 控制器动作：`App\Http\Controllers\Admin\UserController@store`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.manage`
