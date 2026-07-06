# 获取当前用户信息

**请求方法**：GET  
**请求路径**：`/api/client/auth/info`  
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
| 无 | - | 否 | 无请求参数 |

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
| data.email | string | 真实调用返回字段 |
| data.nickname | string | 真实调用返回字段 |
| data.display_name | string | 真实调用返回字段 |
| data.phone | string | 真实调用返回字段 |
| data.cash_balance | string | 真实调用返回字段 |
| data.credit_limit | string | 真实调用返回字段 |
| data.referral_frozen_balance | string | 真实调用返回字段 |
| data.referral_available_balance | string | 真实调用返回字段 |
| data.referral_pending_withdrawal_balance | string | 真实调用返回字段 |
| data.referral_withdrawn_balance | string | 真实调用返回字段 |
| data.referral_code | string | 真实调用返回字段 |
| data.referrer_user_id | null | 真实调用返回字段 |
| data.member_level_id | integer | 真实调用返回字段 |
| data.total_sales_amount | string | 真实调用返回字段 |
| data.member_level | object | 真实调用返回字段 |
| data.member_level.id | integer | 真实调用返回字段 |
| data.member_level.name | string | 真实调用返回字段 |
| data.member_level.code | string | 真实调用返回字段 |
| data.member_level.reward_rate | string | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.is_verified | integer | 真实调用返回字段 |
| data.real_name | string | 真实调用返回字段 |
| data.id_card_masked | string | 真实调用返回字段 |
| data.verification_status | integer | 真实调用返回字段 |
| data.verification_message | string | 真实调用返回字段 |
| data.verification_certify_id | string | 真实调用返回字段 |
| data.login_email_alert | integer | 真实调用返回字段 |
| data.login_notify | integer | 真实调用返回字段 |
| data.login_location_alert | integer | 真实调用返回字段 |
| data.password_change_alert | string | 真实调用返回字段 |
| data.phone_change_alert | integer | 真实调用返回字段 |
| data.email_change_alert | integer | 真实调用返回字段 |
| data.marketing_alert | integer | 真实调用返回字段 |
| data.alipay_account | object | 真实调用返回字段 |
| data.alipay_account.real_name | string | 真实调用返回字段 |
| data.alipay_account.account | string | 真实调用返回字段 |
| data.alipay_account.is_bound | boolean | 真实调用返回字段 |
| data.last_login_at | string | 真实调用返回字段 |
| data.last_login_ip | string | 真实调用返回字段 |
| data.verified_at | string | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 1,
        "email": "2908990438@qq.com",
        "nickname": "李维佳",
        "display_name": "李维佳",
        "phone": "19219178808",
        "cash_balance": "99706.30",
        "credit_limit": "0.00",
        "referral_frozen_balance": "0.00",
        "referral_available_balance": "0.00",
        "referral_pending_withdrawal_balance": "0.00",
        "referral_withdrawn_balance": "0.00",
        "referral_code": "NGUXLK",
        "referrer_user_id": null,
        "member_level_id": 1,
        "total_sales_amount": "0.00",
        "member_level": {
            "id": 1,
            "name": "v1",
            "code": "v1",
            "reward_rate": "5.00"
        },
        "status": 1,
        "is_verified": 1,
        "real_name": "李维佳",
        "id_card_masked": "***已脱敏***",
        "verification_status": 2,
        "verification_message": "审核通过",
        "verification_certify_id": "***已脱敏***",
        "login_email_alert": 1,
        "login_notify": 1,
        "login_location_alert": 1,
        "password_change_alert": "***已脱敏***",
        "phone_change_alert": 1,
        "email_change_alert": 1,
        "marketing_alert": 0,
        "alipay_account": {
            "real_name": "",
            "account": "",
            "is_bound": false
        },
        "last_login_at": "2026-07-05 16:34:43",
        "last_login_ip": "127.0.0.1",
        "verified_at": "2026-07-04 23:49:47",
        "created_at": "2025-01-17 06:30:14"
    },
    "timestamp": 1783240522
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:22  
· 响应状态码：200  
· 调用方式：GET /api/client/auth/info  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\AuthController@info`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器 success([...]) 数组字段`  
· 中间件：`api, auth:sanctum, ensure.client`
