# summary

**请求方法**：GET  
**请求路径**：`/api/client/balance-logs/summary`  
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
| data.cash_balance | string | 真实调用返回字段 |
| data.total_in | string | 真实调用返回字段 |
| data.total_out | string | 真实调用返回字段 |
| data.total_count | integer | 真实调用返回字段 |
| data.recharge_in | string | 真实调用返回字段 |
| data.invoice_payment_out | string | 真实调用返回字段 |
| data.refund_in | string | 真实调用返回字段 |
| data.manual_adjust_out | string | 真实调用返回字段 |
| data.unpaid_amount | string | 真实调用返回字段 |
| data.unpaid_count | integer | 真实调用返回字段 |
| data.total_invoices | integer | 真实调用返回字段 |
| data.recent_30d_recharge | string | 真实调用返回字段 |
| data.recent_30d_refund | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "cash_balance": "99706.30",
        "total_in": "288.10",
        "total_out": "344.60",
        "total_count": 58,
        "recharge_in": "142.10",
        "invoice_payment_out": "344.60",
        "refund_in": "146.00",
        "manual_adjust_out": "0.00",
        "unpaid_amount": "0.00",
        "unpaid_count": 0,
        "total_invoices": 104,
        "recent_30d_recharge": "5.00",
        "recent_30d_refund": "0.00"
    },
    "timestamp": 1783240523
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:24  
· 响应状态码：200  
· 调用方式：GET /api/client/balance-logs/summary  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\FinanceController@balanceLogsSummary`  
· 请求校验：`App\Http\Requests\Client\Finance\ListBalanceLogsRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
