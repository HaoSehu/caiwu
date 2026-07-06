# recharges

**请求方法**：GET  
**请求路径**：`/api/admin/finance/recharges`  
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
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:80；来源：RechargeListRequest |
| status | integer | 否 | 查询参数；校验规则：nullable\|integer；来源：RechargeListRequest |
| start_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：RechargeListRequest |
| end_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：RechargeListRequest |
| date_range | string | 否 | 查询参数；校验规则：prohibited；来源：RechargeListRequest |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：RechargeListRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：RechargeListRequest |

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
| data.list.payment_no | string | 真实调用返回字段 |
| data.list.gateway | string | 真实调用返回字段 |
| data.list.gateway_key | string | 真实调用返回字段 |
| data.list.gateway_label | string | 真实调用返回字段 |
| data.list.trade_no | string | 真实调用返回字段 |
| data.list.user | object | 真实调用返回字段 |
| data.list.user.id | integer | 真实调用返回字段 |
| data.list.user.email | string | 真实调用返回字段 |
| data.list.user.nickname | string | 真实调用返回字段 |
| data.list.user.phone | string | 真实调用返回字段 |
| data.list.invoice_id | null | 真实调用返回字段 |
| data.list.invoice_no | string | 真实调用返回字段 |
| data.list.invoice | null | 真实调用返回字段 |
| data.list.order | null | 真实调用返回字段 |
| data.list.amount | string | 真实调用返回字段 |
| data.list.paid_amount | string | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.payment | object | 真实调用返回字段 |
| data.list.payment.id | integer | 真实调用返回字段 |
| data.list.payment.payment_no | string | 真实调用返回字段 |
| data.list.payment.gateway | string | 真实调用返回字段 |
| data.list.payment.gateway_key | string | 真实调用返回字段 |
| data.list.payment.gateway_label | string | 真实调用返回字段 |
| data.list.payment.trade_no | string | 真实调用返回字段 |
| data.list.payment.amount | string | 真实调用返回字段 |
| data.list.payment.status | integer | 真实调用返回字段 |
| data.list.payment.paid_at | null | 真实调用返回字段 |
| data.list.payment.trace_id | string | 真实调用返回字段 |
| data.list.paid_at | null | 真实调用返回字段 |
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
                "id": 275,
                "payment_no": "PAY20260705004944473U1GDP3IP",
                "gateway": "alipay",
                "gateway_key": "alipay",
                "gateway_label": "支付宝",
                "trade_no": "",
                "user": {
                    "id": 1,
                    "email": "2908990438@qq.com",
                    "nickname": "李维佳",
                    "phone": "19219178808"
                },
                "invoice_id": null,
                "invoice_no": "",
                "invoice": null,
                "order": null,
                "amount": "20.00",
                "paid_amount": "0.00",
                "status": 0,
                "status_label": "待支付",
                "payment": {
                    "id": 275,
                    "payment_no": "PAY20260705004944473U1GDP3IP",
                    "gateway": "alipay",
                    "gateway_key": "alipay",
                    "gateway_label": "支付宝",
                    "trade_no": "",
                    "amount": "20.00",
                    "status": 0,
                    "paid_at": null,
                    "trace_id": "alipay:user:1:20260705004944"
                },
                "paid_at": null,
                "created_at": "2026-07-05 00:49:44"
            }
        ],
        "total": 208,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240487
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:47  
· 响应状态码：200  
· 调用方式：GET /api/admin/finance/recharges  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\FinanceMenuController@recharges`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:invoice.list`
