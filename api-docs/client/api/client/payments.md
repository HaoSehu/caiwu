# payments

**请求方法**：GET  
**请求路径**：`/api/client/payments`  
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
| status | integer | 否 | 查询参数；校验规则：nullable\|integer；来源：IndexRequest |
| type | string | 否 | 查询参数；校验规则：nullable\|string\|in:"alipay","yipay","wechat","stripe"；来源：IndexRequest |
| gateway | string | 否 | 查询参数；校验规则：nullable\|string\|in:"alipay","yipay","wechat","stripe"；来源：IndexRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:80；来源：IndexRequest |
| start_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：IndexRequest |
| end_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：IndexRequest |
| date_range | string | 否 | 查询参数；校验规则：prohibited；来源：IndexRequest |
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
| data.list.payment_no | string | 真实调用返回字段 |
| data.list.trade_no | string | 真实调用返回字段 |
| data.list.gateway | string | 真实调用返回字段 |
| data.list.gateway_key | string | 真实调用返回字段 |
| data.list.gateway_label | string | 真实调用返回字段 |
| data.list.amount | string | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.invoice_id | integer | 真实调用返回字段 |
| data.list.invoice_no | string | 真实调用返回字段 |
| data.list.invoice_type | string | 真实调用返回字段 |
| data.list.invoice_status | null | 真实调用返回字段 |
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
                "id": 280,
                "payment_no": "PAY202607050220195423AMV9TWX",
                "trade_no": "",
                "gateway": "yipay",
                "gateway_key": "yipay",
                "gateway_label": "易支付",
                "amount": "20.00",
                "status": 0,
                "status_label": "待支付",
                "invoice_id": 0,
                "invoice_no": "",
                "invoice_type": "",
                "invoice_status": null,
                "paid_at": null,
                "created_at": "2026-07-05 02:20:19"
            }
        ],
        "total": 35,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240527
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:27  
· 响应状态码：200  
· 调用方式：GET /api/client/payments  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\PaymentController@index`  
· 请求校验：`App\Http\Requests\Client\Payment\IndexRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器返回分页结构；控制器 success([...]) 数组字段`  
· 中间件：`api, auth:sanctum, ensure.client`
