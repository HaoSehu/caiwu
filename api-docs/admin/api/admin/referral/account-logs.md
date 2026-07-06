# account-logs

**请求方法**：GET  
**请求路径**：`/api/admin/referral/account-logs`  
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
| event_type | string | 否 | 查询参数；校验规则：nullable\|string\|max:30；来源：IndexRequest |
| type | string | 否 | 查询参数；校验规则：nullable\|string\|max:30；来源：IndexRequest |
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
| data.list.event_type | string | 真实调用返回字段 |
| data.list.type | string | 真实调用返回字段 |
| data.list.change_amount | string | 真实调用返回字段 |
| data.list.amount | string | 真实调用返回字段 |
| data.list.frozen_balance | string | 真实调用返回字段 |
| data.list.frozen_amount | string | 真实调用返回字段 |
| data.list.available_balance | string | 真实调用返回字段 |
| data.list.available_amount | string | 真实调用返回字段 |
| data.list.pending_withdrawal_balance | string | 真实调用返回字段 |
| data.list.withdrawing_amount | string | 真实调用返回字段 |
| data.list.withdrawn_balance | string | 真实调用返回字段 |
| data.list.withdrawn_amount | string | 真实调用返回字段 |
| data.list.remark | string | 真实调用返回字段 |
| data.list.operator | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.user | object | 真实调用返回字段 |
| data.list.user.id | integer | 真实调用返回字段 |
| data.list.user.email | string | 真实调用返回字段 |
| data.list.user.nickname | string | 真实调用返回字段 |
| data.list.user.display_name | string | 真实调用返回字段 |
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
                "id": 605,
                "event_type": "reward_released",
                "type": "reward_released",
                "change_amount": "2.75",
                "amount": "2.75",
                "frozen_balance": "0.00",
                "frozen_amount": "0.00",
                "available_balance": "17.30",
                "available_amount": "17.30",
                "pending_withdrawal_balance": "0.00",
                "withdrawing_amount": "0.00",
                "withdrawn_balance": "0.00",
                "withdrawn_amount": "0.00",
                "remark": "冻结期结束，奖励已转为可提现",
                "operator": "system",
                "created_at": "2026-06-10 17:10:01",
                "user": {
                    "id": 314,
                    "email": "chen3345793710@qq.com",
                    "nickname": "余梦似海",
                    "display_name": "陈炎培"
                }
            }
        ],
        "total": 10,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240515
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:15  
· 响应状态码：200  
· 调用方式：GET /api/admin/referral/account-logs  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ReferralAccountLogController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:referral.list`
