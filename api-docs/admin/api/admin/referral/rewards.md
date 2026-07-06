# rewards

**请求方法**：GET  
**请求路径**：`/api/admin/referral/rewards`  
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
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：IndexRequest |
| status | integer | 否 | 查询参数；校验规则：nullable\|integer\|in:0,1,2；来源：IndexRequest |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：IndexRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：IndexRequest |

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
| data.list.status | integer | 真实调用返回字段 |
| data.list.order_amount | string | 真实调用返回字段 |
| data.list.reward_rate | string | 真实调用返回字段 |
| data.list.reward_amount | string | 真实调用返回字段 |
| data.list.available_at | string | 真实调用返回字段 |
| data.list.released_at | string | 真实调用返回字段 |
| data.list.rewarded_at | string | 真实调用返回字段 |
| data.list.remark | string | 真实调用返回字段 |
| data.list.referrer | object | 真实调用返回字段 |
| data.list.referrer.id | integer | 真实调用返回字段 |
| data.list.referrer.email | string | 真实调用返回字段 |
| data.list.referrer.nickname | string | 真实调用返回字段 |
| data.list.referrer.display_name | string | 真实调用返回字段 |
| data.list.referred_user | object | 真实调用返回字段 |
| data.list.referred_user.id | integer | 真实调用返回字段 |
| data.list.referred_user.email | string | 真实调用返回字段 |
| data.list.referred_user.nickname | string | 真实调用返回字段 |
| data.list.referred_user.display_name | string | 真实调用返回字段 |
| data.list.order | object | 真实调用返回字段 |
| data.list.order.id | integer | 真实调用返回字段 |
| data.list.order.order_no | string | 真实调用返回字段 |
| data.list.order.product_display_name | string | 真实调用返回字段 |
| data.list.product | object | 真实调用返回字段 |
| data.list.product.id | integer | 真实调用返回字段 |
| data.list.product.name | string | 真实调用返回字段 |
| data.list.product.display_name | string | 真实调用返回字段 |
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
                "id": 5,
                "status": 1,
                "order_amount": "55.00",
                "reward_rate": "5.00",
                "reward_amount": "2.75",
                "available_at": "2026-06-10 17:08:02",
                "released_at": "2026-06-10 17:10:01",
                "rewarded_at": "2026-06-06 17:08:02",
                "remark": "冻结期结束，奖励已转为可提现",
                "referrer": {
                    "id": 314,
                    "email": "chen3345793710@qq.com",
                    "nickname": "余梦似海",
                    "display_name": "陈炎培"
                },
                "referred_user": {
                    "id": 385,
                    "email": "placeholder-385@dev.local",
                    "nickname": "bzkj",
                    "display_name": "白润之"
                },
                "order": {
                    "id": 253,
                    "order_no": "dd202606061707053360",
                    "product_display_name": "gscs-8vcpu-8gib"
                },
                "product": {
                    "id": 55,
                    "name": "gscs",
                    "display_name": "gscs-8vcpu-8gib"
                }
            }
        ],
        "total": 5,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240515
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:15  
· 响应状态码：200  
· 调用方式：GET /api/admin/referral/rewards  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ReferralRewardController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:referral.list`
