# 浣欓鍙樺姩璁板綍

**请求方法**：GET  
**请求路径**：`/api/client/balance-logs`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListBalanceLogsRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:200；来源：ListBalanceLogsRequest |
| event_type | string | 否 | 查询参数；校验规则：nullable\|in:recharge,consume,refund,adjust,admin_deduct,manual_recharge,manual_deduction,invoice_payment,invoice_refund,system_adjustment,referral_withdraw_approved,referral_credit_cash；来源：ListBalanceLogsRequest |
| type | string | 否 | 查询参数；校验规则：nullable\|in:recharge,consume,refund,adjust,admin_deduct,manual_recharge,manual_deduction,invoice_payment,invoice_refund,system_adjustment,referral_withdraw_approved,referral_credit_cash；来源：ListBalanceLogsRequest |
| start_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：ListBalanceLogsRequest |
| end_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：ListBalanceLogsRequest |
| date_range | string | 否 | 查询参数；校验规则：prohibited；来源：ListBalanceLogsRequest |

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
| data.list.ledger_id | integer | 真实调用返回字段 |
| data.list.id | integer | 真实调用返回字段 |
| data.list.account_type | string | 真实调用返回字段 |
| data.list.event_type | string | 真实调用返回字段 |
| data.list.event_type_label | string | 真实调用返回字段 |
| data.list.event_category | string | 真实调用返回字段 |
| data.list.direction | string | 真实调用返回字段 |
| data.list.amount | string | 真实调用返回字段 |
| data.list.change_amount | string | 真实调用返回字段 |
| data.list.balance_after | string | 真实调用返回字段 |
| data.list.occurred_at | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.remark | string | 真实调用返回字段 |
| data.list.source_type | string | 真实调用返回字段 |
| data.list.source_id | integer | 真实调用返回字段 |
| data.list.origin_type | string | 真实调用返回字段 |
| data.list.origin_id | integer | 真实调用返回字段 |
| data.list.operator | string | 真实调用返回字段 |
| data.list.trace_id | string | 真实调用返回字段 |
| data.list.business_scene | string | 真实调用返回字段 |
| data.list.business_scene_label | string | 真实调用返回字段 |
| data.list.invoice | object | 真实调用返回字段 |
| data.list.invoice.id | integer | 真实调用返回字段 |
| data.list.invoice.invoice_no | string | 真实调用返回字段 |
| data.list.invoice.type | string | 真实调用返回字段 |
| data.list.invoice.type_label | string | 真实调用返回字段 |
| data.list.invoice.business_scene | string | 真实调用返回字段 |
| data.list.invoice.business_scene_label | string | 真实调用返回字段 |
| data.list.invoice.status | integer | 真实调用返回字段 |
| data.list.invoice.status_label | string | 真实调用返回字段 |
| data.list.invoice.amount | string | 真实调用返回字段 |
| data.list.invoice.paid_amount | string | 真实调用返回字段 |
| data.list.payment | object | 真实调用返回字段 |
| data.list.payment.id | integer | 真实调用返回字段 |
| data.list.payment.payment_no | string | 真实调用返回字段 |
| data.list.payment.gateway | string | 真实调用返回字段 |
| data.list.payment.gateway_key | string | 真实调用返回字段 |
| data.list.payment.gateway_label | string | 真实调用返回字段 |
| data.list.payment.status | integer | 真实调用返回字段 |
| data.list.payment.status_label | string | 真实调用返回字段 |
| data.list.payment.trade_no | string | 真实调用返回字段 |
| data.list.payment.amount | string | 真实调用返回字段 |
| data.list.payment.paid_at | string | 真实调用返回字段 |
| data.list.user | object | 真实调用返回字段 |
| data.list.user.id | integer | 真实调用返回字段 |
| data.list.user.email | string | 真实调用返回字段 |
| data.list.user.nickname | string | 真实调用返回字段 |
| data.list.user.display_name | string | 真实调用返回字段 |
| data.list.display | object | 真实调用返回字段 |
| data.list.display.title | string | 真实调用返回字段 |
| data.list.display.subtitle | string | 真实调用返回字段 |
| data.list.display.badge | string | 真实调用返回字段 |
| data.list.display.badge_type | string | 真实调用返回字段 |
| data.list.display.status | integer | 真实调用返回字段 |
| data.list.display.status_label | string | 真实调用返回字段 |
| data.list.display.channel_label | string | 真实调用返回字段 |
| data.list.display.scene_label | string | 真实调用返回字段 |
| data.list.display.business_scene_label | string | 真实调用返回字段 |
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
                "ledger_id": 1650,
                "id": 1650,
                "account_type": "cash",
                "event_type": "recharge",
                "event_type_label": "充值到账",
                "event_category": "balance",
                "direction": "in",
                "amount": "1.00",
                "change_amount": "1.00",
                "balance_after": "99706.30",
                "occurred_at": "2026-07-05 01:55:01",
                "created_at": "2026-07-05 01:55:01",
                "remark": "支付宝充值 PAY20260705015436457LZWFRBLY",
                "source_type": "payment",
                "source_id": 279,
                "origin_type": "payment",
                "origin_id": 279,
                "operator": "",
                "trace_id": "yipay:user:1:20260705015436",
                "business_scene": "recharge",
                "business_scene_label": "充值",
                "invoice": {
                    "id": 2167,
                    "invoice_no": "zd202607050155016637",
                    "type": "recharge",
                    "type_label": "充值",
                    "business_scene": "recharge",
                    "business_scene_label": "充值",
                    "status": 1,
                    "status_label": "已付",
                    "amount": "1.00",
                    "paid_amount": "1.00"
                },
                "payment": {
                    "id": 279,
                    "payment_no": "PAY20260705015436457LZWFRBLY",
                    "gateway": "yipay",
                    "gateway_key": "yipay",
                    "gateway_label": "易支付",
                    "status": 1,
                    "status_label": "成功",
                    "trade_no": "2026070501543695318",
                    "amount": "1.00",
                    "paid_at": "2026-07-05 01:55:01"
                },
                "user": {
                    "id": 1,
                    "email": "2908990438@qq.com",
                    "nickname": "李维佳",
                    "display_name": "李维佳"
                },
                "display": {
                    "title": "充值到账",
                    "subtitle": "支付宝充值 PAY20260705015436457LZWFRBLY",
                    "badge": "充值到账",
                    "badge_type": "success",
                    "status": 1,
                    "status_label": "已付",
                    "channel_label": "易支付",
                    "scene_label": "充值",
                    "business_scene_label": "充值"
                }
            }
        ],
        "total": 58,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240523
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:23  
· 响应状态码：200  
· 调用方式：GET /api/client/balance-logs  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\FinanceController@balanceLogs`  
· 请求校验：`App\Http\Requests\Client\Finance\ListBalanceLogsRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
