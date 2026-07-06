# orders

**请求方法**：GET  
**请求路径**：`/api/client/orders`  
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
| type | string | 否 | 查询参数；校验规则：nullable\|string\|in:"new","renew","upgrade"；来源：IndexRequest |
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
| data.list.order_no | string | 真实调用返回字段 |
| data.list.type | string | 真实调用返回字段 |
| data.list.type_label | string | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.amount | string | 真实调用返回字段 |
| data.list.paid_amount | string | 真实调用返回字段 |
| data.list.discount | string | 真实调用返回字段 |
| data.list.billing_cycle | string | 真实调用返回字段 |
| data.list.quantity | integer | 真实调用返回字段 |
| data.list.product_name | string | 真实调用返回字段 |
| data.list.product_full_path | string | 真实调用返回字段 |
| data.list.service_name | string | 真实调用返回字段 |
| data.list.invoice | object | 真实调用返回字段 |
| data.list.invoice.id | integer | 真实调用返回字段 |
| data.list.invoice.invoice_no | string | 真实调用返回字段 |
| data.list.invoice.status | integer | 真实调用返回字段 |
| data.list.invoice.amount | string | 真实调用返回字段 |
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
                "id": 278,
                "order_no": "dd202607050200009940",
                "type": "new",
                "type_label": "新购",
                "status": 0,
                "status_label": "待付款",
                "amount": "48.00",
                "paid_amount": "0.00",
                "discount": "0.00",
                "billing_cycle": "monthly",
                "quantity": 1,
                "product_name": "gscs-2vcpu-2gib",
                "product_full_path": "云服务器/襄阳/高宽/gscs-2vcpu-2gib",
                "service_name": "",
                "invoice": {
                    "id": 2168,
                    "invoice_no": "zd202607050200009940",
                    "status": 2,
                    "amount": "48.00"
                },
                "paid_at": null,
                "created_at": "2026-07-05 02:00:00"
            }
        ],
        "total": 16,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240527
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:27  
· 响应状态码：200  
· 调用方式：GET /api/client/orders  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\OrderController@index`  
· 请求校验：`App\Http\Requests\Client\Order\IndexRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器返回分页结构；控制器 success([...]) 数组字段`  
· 中间件：`api, auth:sanctum, ensure.client`
