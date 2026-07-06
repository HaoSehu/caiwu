# summary

**请求方法**：GET  
**请求路径**：`/api/admin/finance/ledger/summary`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：FinanceLedgerListRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：FinanceLedgerListRequest |
| tab | string | 否 | 查询参数；校验规则：nullable\|in:"all","invoices","balance","recharge","adjustment"；来源：FinanceLedgerListRequest |
| event_type | string | 否 | 查询参数；校验规则：nullable\|in:"invoice_payment","invoice_refund","recharge","manual_recharge","manual_deduction","referral_credit_cash","system_adjustment"；来源：FinanceLedgerListRequest |
| direction | string | 否 | 查询参数；校验规则：nullable\|in:"in","out"；来源：FinanceLedgerListRequest |
| status | integer | 否 | 查询参数；校验规则：nullable\|integer；来源：FinanceLedgerListRequest |
| user_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：FinanceLedgerListRequest |
| invoice_no | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：FinanceLedgerListRequest |
| payment_no | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：FinanceLedgerListRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：FinanceLedgerListRequest |
| start_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：FinanceLedgerListRequest |
| end_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：FinanceLedgerListRequest |
| date_range | string | 否 | 查询参数；校验规则：prohibited；来源：FinanceLedgerListRequest |

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
        "cash_balance": "0.00",
        "total_in": "17912.72",
        "total_out": "4984.40",
        "total_count": 691,
        "recharge_in": "16832.52",
        "invoice_payment_out": "4744.40",
        "refund_in": "206.00",
        "manual_adjust_out": "240.00",
        "unpaid_amount": "0.00",
        "unpaid_count": 0,
        "total_invoices": 2163,
        "recent_30d_recharge": "1025.00",
        "recent_30d_refund": "0.00"
    },
    "timestamp": 1783240487
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:47  
· 响应状态码：200  
· 调用方式：GET /api/admin/finance/ledger/summary  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\FinanceLedgerController@summary`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:invoice.list`
