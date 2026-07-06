# 用户详情

**请求方法**：GET  
**请求路径**：`/api/admin/users/{user}`  
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
| user | integer\|string | 是 | 路径参数；来自路由占位 `{user}` |

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
| data.user | object | 真实调用返回字段 |
| data.user.id | integer | 真实调用返回字段 |
| data.user.email | string | 真实调用返回字段 |
| data.user.nickname | string | 真实调用返回字段 |
| data.user.display_name | string | 真实调用返回字段 |
| data.user.phone | string | 真实调用返回字段 |
| data.user.company | string | 真实调用返回字段 |
| data.user.qq | string | 真实调用返回字段 |
| data.user.admin_note | null | 真实调用返回字段 |
| data.user.referral_code | string | 真实调用返回字段 |
| data.user.referrer_user_id | null | 真实调用返回字段 |
| data.user.member_level_id | integer | 真实调用返回字段 |
| data.user.total_sales_amount | string | 真实调用返回字段 |
| data.user.member_level | object | 真实调用返回字段 |
| data.user.member_level.id | integer | 真实调用返回字段 |
| data.user.member_level.name | string | 真实调用返回字段 |
| data.user.member_level.code | string | 真实调用返回字段 |
| data.user.member_level.reward_rate | string | 真实调用返回字段 |
| data.user.cash_balance | string | 真实调用返回字段 |
| data.user.credit_limit | string | 真实调用返回字段 |
| data.user.referral_frozen_balance | string | 真实调用返回字段 |
| data.user.referral_available_balance | string | 真实调用返回字段 |
| data.user.referral_pending_withdrawal_balance | string | 真实调用返回字段 |
| data.user.referral_withdrawn_balance | string | 真实调用返回字段 |
| data.user.active_services_count | integer | 真实调用返回字段 |
| data.user.status | integer | 真实调用返回字段 |
| data.user.is_verified | integer | 真实调用返回字段 |
| data.user.real_name | string | 真实调用返回字段 |
| data.user.id_card_masked | string | 真实调用返回字段 |
| data.user.verification_certify_id | string | 真实调用返回字段 |
| data.user.referred_at | null | 真实调用返回字段 |
| data.user.alipay_real_name | string | 真实调用返回字段 |
| data.user.alipay_account | string | 真实调用返回字段 |
| data.user.last_login_at | string | 真实调用返回字段 |
| data.user.last_login_ip | string | 真实调用返回字段 |
| data.user.created_at | string | 真实调用返回字段 |
| data.stats | object | 真实调用返回字段 |
| data.stats.service_active | integer | 真实调用返回字段 |
| data.stats.service_total | integer | 真实调用返回字段 |
| data.stats.order_total | integer | 真实调用返回字段 |
| data.stats.order_pending | integer | 真实调用返回字段 |
| data.stats.total_income | number | 真实调用返回字段 |
| data.stats.total_expense | number | 真实调用返回字段 |
| data.stats.unpaid_amount | integer | 真实调用返回字段 |
| data.stats.ticket_open | integer | 真实调用返回字段 |
| data.stats.ticket_closed | integer | 真实调用返回字段 |
| data.stats.ticket_total | integer | 真实调用返回字段 |
| data.stats.invoice_unpaid | integer | 真实调用返回字段 |
| data.stats.invoice_paid | integer | 真实调用返回字段 |
| data.stats.direct_referral_count | integer | 真实调用返回字段 |
| data.stats.rewarded_orders_count | integer | 真实调用返回字段 |
| data.stats.total_referral_reward | integer | 真实调用返回字段 |
| data.referral | object | 真实调用返回字段 |
| data.referral.referral_code | string | 真实调用返回字段 |
| data.referral.referrer_user_id | null | 真实调用返回字段 |
| data.referral.member_level | object | 真实调用返回字段 |
| data.referral.member_level.id | integer | 真实调用返回字段 |
| data.referral.member_level.name | string | 真实调用返回字段 |
| data.referral.member_level.code | string | 真实调用返回字段 |
| data.referral.member_level.reward_rate | integer | 真实调用返回字段 |
| data.referral.total_sales_amount | integer | 真实调用返回字段 |
| data.referral.referral_frozen_amount | integer | 真实调用返回字段 |
| data.referral.referral_available_amount | integer | 真实调用返回字段 |
| data.referral.referral_withdrawing_amount | integer | 真实调用返回字段 |
| data.referral.referral_withdrawn_amount | integer | 真实调用返回字段 |
| data.referral.recent_referrals | array | 真实调用返回字段 |
| data.referral.recent_referrals.id | integer | 真实调用返回字段 |
| data.referral.recent_referrals.email | string | 真实调用返回字段 |
| data.referral.recent_referrals.nickname | string | 真实调用返回字段 |
| data.referral.recent_referrals.display_name | string | 真实调用返回字段 |
| data.referral.recent_referrals.created_at | string | 真实调用返回字段 |
| data.referral.recent_referrals.referred_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "user": {
            "id": 1,
            "email": "2908990438@qq.com",
            "nickname": "李维佳",
            "display_name": "李维佳",
            "phone": "19219178808",
            "company": "",
            "qq": "",
            "admin_note": null,
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
            "cash_balance": "99706.30",
            "credit_limit": "0.00",
            "referral_frozen_balance": "0.00",
            "referral_available_balance": "0.00",
            "referral_pending_withdrawal_balance": "0.00",
            "referral_withdrawn_balance": "0.00",
            "active_services_count": 0,
            "status": 1,
            "is_verified": 1,
            "real_name": "李维佳",
            "id_card_masked": "***已脱敏***",
            "verification_certify_id": "***已脱敏***",
            "referred_at": null,
            "alipay_real_name": "",
            "alipay_account": "",
            "last_login_at": "2026-07-05 16:34:43",
            "last_login_ip": "127.0.0.1",
            "created_at": "2025-01-17 06:30:14"
        },
        "stats": {
            "service_active": 2,
            "service_total": 8,
            "order_total": 16,
            "order_pending": 3,
            "total_income": 288.1,
            "total_expense": 344.6,
            "unpaid_amount": 0,
            "ticket_open": 0,
            "ticket_closed": 3,
            "ticket_total": 3,
            "invoice_unpaid": 0,
            "invoice_paid": 80,
            "direct_referral_count": 2,
            "rewarded_orders_count": 0,
            "total_referral_reward": 0
        },
        "referral": {
            "referral_code": "NGUXLK",
            "referrer_user_id": null,
            "member_level": {
                "id": 1,
                "name": "v1",
                "code": "v1",
                "reward_rate": 5
            },
            "total_sales_amount": 0,
            "referral_frozen_amount": 0,
            "referral_available_amount": 0,
            "referral_withdrawing_amount": 0,
            "referral_withdrawn_amount": 0,
            "recent_referrals": [
                {
                    "id": 372,
                    "email": "2621513938@qq.com",
                    "nickname": "阿漂",
                    "display_name": "李远磊",
                    "created_at": "2026-03-27 05:04:29",
                    "referred_at": "2026-03-27 05:04:29"
                },
                {
                    "id": 368,
                    "email": "2195166210@qq.com",
                    "nickname": "EmbeTime",
                    "display_name": "EmbeTime",
                    "created_at": "2026-03-26 06:00:40",
                    "referred_at": "2026-03-26 06:00:40"
                }
            ]
        }
    },
    "timestamp": 1783240518
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:18  
· 响应状态码：200  
· 调用方式：GET /api/admin/users/{user}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\UserController@show`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.detail`
