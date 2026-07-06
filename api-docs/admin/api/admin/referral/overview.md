# overview

**请求方法**：GET  
**请求路径**：`/api/admin/referral/overview`  
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
| data.summary | object | 真实调用返回字段 |
| data.summary.rewards_total | integer | 真实调用返回字段 |
| data.summary.total_sales_amount | integer | 真实调用返回字段 |
| data.summary.total_reward_amount | number | 真实调用返回字段 |
| data.summary.frozen_amount | integer | 真实调用返回字段 |
| data.summary.released_amount | number | 真实调用返回字段 |
| data.summary.withdrawals_total | integer | 真实调用返回字段 |
| data.summary.withdrawing_amount | integer | 真实调用返回字段 |
| data.summary.withdrawn_amount | integer | 真实调用返回字段 |
| data.summary.rejected_amount | integer | 真实调用返回字段 |
| data.summary.direct_referral_users | integer | 真实调用返回字段 |
| data.top_referrers | array | 真实调用返回字段 |
| data.top_referrers.id | integer | 真实调用返回字段 |
| data.top_referrers.email | string | 真实调用返回字段 |
| data.top_referrers.nickname | string | 真实调用返回字段 |
| data.top_referrers.display_name | string | 真实调用返回字段 |
| data.top_referrers.member_level | object | 真实调用返回字段 |
| data.top_referrers.member_level.id | integer | 真实调用返回字段 |
| data.top_referrers.member_level.name | string | 真实调用返回字段 |
| data.top_referrers.member_level.code | string | 真实调用返回字段 |
| data.top_referrers.member_level.reward_rate | string | 真实调用返回字段 |
| data.top_referrers.total_sales_amount | integer | 真实调用返回字段 |
| data.top_referrers.referral_frozen_amount | integer | 真实调用返回字段 |
| data.top_referrers.referral_available_amount | number | 真实调用返回字段 |
| data.top_referrers.referral_withdrawing_amount | integer | 真实调用返回字段 |
| data.top_referrers.referral_withdrawn_amount | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "summary": {
            "rewards_total": 5,
            "total_sales_amount": 228,
            "total_reward_amount": 17.3,
            "frozen_amount": 0,
            "released_amount": 17.3,
            "withdrawals_total": 0,
            "withdrawing_amount": 0,
            "withdrawn_amount": 0,
            "rejected_amount": 0,
            "direct_referral_users": 6
        },
        "top_referrers": [
            {
                "id": 314,
                "email": "chen3345793710@qq.com",
                "nickname": "余梦似海",
                "display_name": "陈炎培",
                "member_level": {
                    "id": 1,
                    "name": "v1",
                    "code": "v1",
                    "reward_rate": "5.00"
                },
                "total_sales_amount": 228,
                "referral_frozen_amount": 0,
                "referral_available_amount": 17.3,
                "referral_withdrawing_amount": 0,
                "referral_withdrawn_amount": 0
            }
        ]
    },
    "timestamp": 1783240515
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:15  
· 响应状态码：200  
· 调用方式：GET /api/admin/referral/overview  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ReferralController@overview`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:referral.list`
