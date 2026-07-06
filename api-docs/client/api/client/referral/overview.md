# overview

**请求方法**：GET  
**请求路径**：`/api/client/referral/overview`  
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
| data.referral_code | string | 真实调用返回字段 |
| data.register_path | string | 真实调用返回字段 |
| data.referral_link | string | 真实调用返回字段 |
| data.reward_rate | string | 真实调用返回字段 |
| data.reward_freeze_days | integer | 真实调用返回字段 |
| data.withdraw_min_amount | string | 真实调用返回字段 |
| data.current_member_level | object | 真实调用返回字段 |
| data.current_member_level.id | integer | 真实调用返回字段 |
| data.current_member_level.name | string | 真实调用返回字段 |
| data.current_member_level.code | string | 真实调用返回字段 |
| data.current_member_level.reward_rate | string | 真实调用返回字段 |
| data.next_member_level | object | 真实调用返回字段 |
| data.next_member_level.id | integer | 真实调用返回字段 |
| data.next_member_level.name | string | 真实调用返回字段 |
| data.next_member_level.code | string | 真实调用返回字段 |
| data.next_member_level.reward_rate | string | 真实调用返回字段 |
| data.next_member_level.sales_amount_min | string | 真实调用返回字段 |
| data.next_member_level.distance_amount | string | 真实调用返回字段 |
| data.member_levels | array | 真实调用返回字段 |
| data.member_levels.id | integer | 真实调用返回字段 |
| data.member_levels.name | string | 真实调用返回字段 |
| data.member_levels.code | string | 真实调用返回字段 |
| data.member_levels.reward_rate | string | 真实调用返回字段 |
| data.member_levels.sales_amount_min | string | 真实调用返回字段 |
| data.member_levels.sales_amount_max | string | 真实调用返回字段 |
| data.total_sales_amount | string | 真实调用返回字段 |
| data.referral_frozen_amount | string | 真实调用返回字段 |
| data.referral_available_amount | string | 真实调用返回字段 |
| data.referral_withdrawing_amount | string | 真实调用返回字段 |
| data.referral_withdrawn_amount | string | 真实调用返回字段 |
| data.direct_referral_count | integer | 真实调用返回字段 |
| data.rewarded_orders_count | integer | 真实调用返回字段 |
| data.total_reward_amount | string | 真实调用返回字段 |
| data.recent_referrals | array | 真实调用返回字段 |
| data.recent_referrals.id | integer | 真实调用返回字段 |
| data.recent_referrals.email | string | 真实调用返回字段 |
| data.recent_referrals.nickname | string | 真实调用返回字段 |
| data.recent_referrals.display_name | string | 真实调用返回字段 |
| data.recent_referrals.created_at | string | 真实调用返回字段 |
| data.recent_referrals.referred_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "referral_code": "NGUXLK",
        "register_path": "/client/register?ref=NGUXLK",
        "referral_link": "http://127.0.0.1:5175/client/register?ref=NGUXLK",
        "reward_rate": "5.00",
        "reward_freeze_days": 4,
        "withdraw_min_amount": "20.00",
        "current_member_level": {
            "id": 1,
            "name": "v1",
            "code": "v1",
            "reward_rate": "5.00"
        },
        "next_member_level": {
            "id": 2,
            "name": "v2",
            "code": "v2",
            "reward_rate": "10.00",
            "sales_amount_min": "301.00",
            "distance_amount": "301.00"
        },
        "member_levels": [
            {
                "id": 1,
                "name": "v1",
                "code": "v1",
                "reward_rate": "5.00",
                "sales_amount_min": "0.00",
                "sales_amount_max": "300.00"
            },
            {
                "id": 2,
                "name": "v2",
                "code": "v2",
                "reward_rate": "10.00",
                "sales_amount_min": "301.00",
                "sales_amount_max": "800.00"
            },
            {
                "id": 3,
                "name": "v3",
                "code": "v3",
                "reward_rate": "15.00",
                "sales_amount_min": "801.00",
                "sales_amount_max": null
            }
        ],
        "total_sales_amount": "0.00",
        "referral_frozen_amount": "0.00",
        "referral_available_amount": "0.00",
        "referral_withdrawing_amount": "0.00",
        "referral_withdrawn_amount": "0.00",
        "direct_referral_count": 2,
        "rewarded_orders_count": 0,
        "total_reward_amount": "0.00",
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
    },
    "timestamp": 1783240528
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:28  
· 响应状态码：200  
· 调用方式：GET /api/client/referral/overview  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ReferralController@overview`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
