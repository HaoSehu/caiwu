# renewal-orders

**请求方法**：GET  
**请求路径**：`/api/admin/finance/renewal-orders`  
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
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:80；来源：OrderListRequest |
| status | integer | 否 | 查询参数；校验规则：nullable\|integer；来源：OrderListRequest |
| type | string | 否 | 查询参数；校验规则：nullable\|string\|in:"new","renew","upgrade"；来源：OrderListRequest |
| start_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：OrderListRequest |
| end_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：OrderListRequest |
| date_range | string | 否 | 查询参数；校验规则：prohibited；来源：OrderListRequest |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：OrderListRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：OrderListRequest |

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
| data.list.user_id | integer | 真实调用返回字段 |
| data.list.user | object | 真实调用返回字段 |
| data.list.user.id | integer | 真实调用返回字段 |
| data.list.user.email | string | 真实调用返回字段 |
| data.list.user.nickname | string | 真实调用返回字段 |
| data.list.user.phone | string | 真实调用返回字段 |
| data.list.invoice | object | 真实调用返回字段 |
| data.list.invoice.id | integer | 真实调用返回字段 |
| data.list.invoice.invoice_no | string | 真实调用返回字段 |
| data.list.invoice.status | integer | 真实调用返回字段 |
| data.list.invoice.amount | string | 真实调用返回字段 |
| data.list.invoice.paid_amount | string | 真实调用返回字段 |
| data.list.invoice.paid_at | string | 真实调用返回字段 |
| data.list.invoice.trace_id | string | 真实调用返回字段 |
| data.list.invoice.refund_trace_id | string | 真实调用返回字段 |
| data.list.product_id | integer | 真实调用返回字段 |
| data.list.product_name | string | 真实调用返回字段 |
| data.list.product_full_path | string | 真实调用返回字段 |
| data.list.product_type | string | 真实调用返回字段 |
| data.list.service | object | 真实调用返回字段 |
| data.list.service.id | integer | 真实调用返回字段 |
| data.list.service.name | string | 真实调用返回字段 |
| data.list.service.domain | string | 真实调用返回字段 |
| data.list.service.status | integer | 真实调用返回字段 |
| data.list.service.expires_at | string | 真实调用返回字段 |
| data.list.type | string | 真实调用返回字段 |
| data.list.type_label | string | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.amount | string | 真实调用返回字段 |
| data.list.discount | string | 真实调用返回字段 |
| data.list.paid_amount | string | 真实调用返回字段 |
| data.list.billing_cycle | string | 真实调用返回字段 |
| data.list.quantity | integer | 真实调用返回字段 |
| data.list.paid_at | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.list.trace_id | string | 真实调用返回字段 |
| data.list.config_snapshot | object | 真实调用返回字段 |
| data.list.config_snapshot.auto_renew | integer | 真实调用返回字段 |
| data.list.config_snapshot.created_by | string | 真实调用返回字段 |
| data.list.config_snapshot.source_type | string | 真实调用返回字段 |
| data.list.config_snapshot._schema_type | string | 真实调用返回字段 |
| data.list.config_snapshot._schema_version | integer | 真实调用返回字段 |
| data.list.config_snapshot.discount_amount | string | 真实调用返回字段 |
| data.list.config_snapshot.renew_service_id | integer | 真实调用返回字段 |
| data.list.config_snapshot.upstream_host_id | integer | 真实调用返回字段 |
| data.list.config_snapshot.supports_upstream | boolean | 真实调用返回字段 |
| data.list.config_snapshot.local_renew_amount | string | 真实调用返回字段 |
| data.list.config_snapshot.renew_service_name | string | 真实调用返回字段 |
| data.list.config_snapshot.auto_renew_trace_id | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot | array | 真实调用返回字段 |
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
                "id": 276,
                "order_no": "dd202607042300032346",
                "user_id": 419,
                "user": {
                    "id": 419,
                    "email": "placeholder-419@dev.local",
                    "nickname": "ken",
                    "phone": "13372418878"
                },
                "invoice": {
                    "id": 2155,
                    "invoice_no": "zd202607042300032346",
                    "status": 1,
                    "amount": "30.00",
                    "paid_amount": "30.00",
                    "paid_at": "2026-07-04 23:00:03",
                    "trace_id": "",
                    "refund_trace_id": ""
                },
                "product_id": 48,
                "product_name": "gscs",
                "product_full_path": "云服务器/美国/高性能/gscs",
                "product_type": "vps",
                "service": {
                    "id": 125,
                    "name": "美国2区精品网 4H4G",
                    "domain": "ser179874523345",
                    "status": 1,
                    "expires_at": "2026-08-07 19:39:14"
                },
                "type": "renew",
                "type_label": "续费",
                "status": 1,
                "status_label": "已付款",
                "amount": "30.00",
                "discount": "0.00",
                "paid_amount": "30.00",
                "billing_cycle": "monthly",
                "quantity": 1,
                "paid_at": "2026-07-04 23:00:03",
                "created_at": "2026-07-04 23:00:03",
                "updated_at": "2026-07-04 23:00:03",
                "trace_id": "auto_renew:service:125:202607042300",
                "config_snapshot": {
                    "auto_renew": 1,
                    "created_by": "auto_renew",
                    "source_type": "manual",
                    "_schema_type": "order.config_snapshot",
                    "_schema_version": 1,
                    "discount_amount": "0.00",
                    "renew_service_id": 125,
                    "upstream_host_id": 73900,
                    "supports_upstream": true,
                    "local_renew_amount": "30.00",
                    "renew_service_name": "美国2区精品网 4H4G",
                    "auto_renew_trace_id": "auto_renew:service:125:202607042300"
                },
                "config_pricing_snapshot": []
            }
        ],
        "total": 44,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240487
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:47  
· 响应状态码：200  
· 调用方式：GET /api/admin/finance/renewal-orders  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\FinanceMenuController@renewalOrders`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:invoice.list`
