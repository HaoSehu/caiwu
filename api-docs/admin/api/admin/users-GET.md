# 用户列表

**请求方法**：GET  
**请求路径**：`/api/admin/users`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListUsersRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：ListUsersRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：ListUsersRequest |
| user_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListUsersRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|in:0,1；来源：ListUsersRequest |
| is_verified | string | 否 | 查询参数；校验规则：nullable\|in:0,1；来源：ListUsersRequest |
| verification_status | string | 否 | 查询参数；校验规则：nullable\|in:0,1,2,3；来源：ListUsersRequest |

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
| data.list | array | 分页列表数据 |
| data.list.id | integer | 真实调用返回字段 |
| data.list.email | string | 真实调用返回字段 |
| data.list.phone | string | 真实调用返回字段 |
| data.list.nickname | string | 真实调用返回字段 |
| data.list.display_name | string | 真实调用返回字段 |
| data.list.company | string | 真实调用返回字段 |
| data.list.qq | string | 真实调用返回字段 |
| data.list.referral_code | string | 真实调用返回字段 |
| data.list.member_level_id | null | 真实调用返回字段 |
| data.list.verification_status | integer | 真实调用返回字段 |
| data.list.real_name | string | 真实调用返回字段 |
| data.list.cash_balance | integer | 真实调用返回字段 |
| data.list.credit_limit | integer | 真实调用返回字段 |
| data.list.referral_frozen_balance | integer | 真实调用返回字段 |
| data.list.referral_available_balance | integer | 真实调用返回字段 |
| data.list.referral_pending_withdrawal_balance | integer | 真实调用返回字段 |
| data.list.referral_withdrawn_balance | integer | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.is_verified | integer | 真实调用返回字段 |
| data.list.opened_product_count | integer | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.total | integer | 总条数 |
| data.page | integer | 当前页码 |
| data.page_size | integer | 每页数量 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "id": 463,
                "email": "zh190193@gmail.com",
                "phone": "000000000463",
                "nickname": "zh190193",
                "display_name": "张丹枫",
                "company": "",
                "qq": "",
                "referral_code": "",
                "member_level_id": null,
                "verification_status": 2,
                "real_name": "张丹枫",
                "cash_balance": 0,
                "credit_limit": 0,
                "referral_frozen_balance": 0,
                "referral_available_balance": 0,
                "referral_pending_withdrawal_balance": 0,
                "referral_withdrawn_balance": 0,
                "status": 1,
                "is_verified": 1,
                "opened_product_count": 1,
                "created_at": "2026-06-27 18:35:59"
            }
        ],
        "total": 463,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240518
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:18  
· 响应状态码：200  
· 调用方式：GET /api/admin/users  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\UserController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.list`
