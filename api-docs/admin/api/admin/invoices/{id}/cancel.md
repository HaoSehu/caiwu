# cancel

**请求方法**：POST  
**请求路径**：`/api/admin/invoices/{id}/cancel`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |
| X-Request-Id | {trace_id} | 否 | 请求追踪 ID；控制器读取该请求头 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| id | integer\|string | 是 | 路径参数；来自路由占位 `{id}` |

### 请求示例（完整 JSON）
```json
{}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.id | integer | 业务字段；由源码静态提取 |
| data.invoice_no | string | 业务字段；由源码静态提取 |
| data.user_id | integer | 业务字段；由源码静态提取 |
| data.product_spec_snapshot | string | 业务字段；由源码静态提取 |
| data.product_spec_display | string | 业务字段；由源码静态提取 |
| data.product_display_name | string | 业务字段；由源码静态提取 |
| data.combined_display_name | string | 业务字段；由源码静态提取 |
| data.user | object | 业务字段；由源码静态提取 |
| data.order_id | integer | 业务字段；由源码静态提取 |
| data.order | string | 业务字段；由源码静态提取 |
| data.product_id | integer | 业务字段；由源码静态提取 |
| data.product | object | 业务字段；由源码静态提取 |
| data.service | object | 业务字段；由源码静态提取 |
| data.type | string | 业务字段；由源码静态提取 |
| data.type_label | string | 业务字段；由源码静态提取 |
| data.scene | string | 业务字段；由源码静态提取 |
| data.amount | string(decimal) | 业务字段；由源码静态提取 |
| data.discount | string | 业务字段；由源码静态提取 |
| data.paid_amount | string(decimal) | 业务字段；由源码静态提取 |
| data.payable_amount | string(decimal) | 业务字段；由源码静态提取 |
| data.status | integer | 业务字段；由源码静态提取 |
| data.status_label | string | 业务字段；由源码静态提取 |
| data.raw_status | array | 业务字段；由源码静态提取 |
| data.raw_status_label | string | 业务字段；由源码静态提取 |
| data.billing_cycle | string | 业务字段；由源码静态提取 |
| data.quantity | integer | 业务字段；由源码静态提取 |
| data.summary | string | 业务字段；由源码静态提取 |
| data.due_date | string(datetime) | 业务字段；由源码静态提取 |
| data.paid_at | string(datetime) | 业务字段；由源码静态提取 |
| data.created_at | string(datetime) | 业务字段；由源码静态提取 |
| data.updated_at | string(datetime) | 业务字段；由源码静态提取 |
| data.trace_id | integer | 业务字段；由源码静态提取 |
| data.refund_trace_id | integer | 业务字段；由源码静态提取 |
| data.config_snapshot | object | 业务字段；由源码静态提取 |
| data.config_pricing_snapshot | object | 业务字段；由源码静态提取 |
| data.coupon_snapshot | string | 业务字段；由源码静态提取 |
| data.payment_summary | string | 业务字段；由源码静态提取 |
| data.payments | array | 业务字段；由源码静态提取 |
| data.items | array | 业务字段；由源码静态提取 |
| data.logs | array | 业务字段；由源码静态提取 |
| data.can_cancel | boolean | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "账单已取消",
    "data": {
        "id": 1,
        "invoice_no": "string",
        "user_id": 1,
        "product_spec_snapshot": "string",
        "product_spec_display": "string",
        "product_display_name": "string",
        "combined_display_name": "string",
        "user": [],
        "order_id": 1,
        "order": "string",
        "product_id": 1,
        "product": [],
        "service": [],
        "type": "string",
        "type_label": "string",
        "scene": "string",
        "amount": "0.00",
        "discount": "string",
        "paid_amount": "0.00",
        "payable_amount": "0.00",
        "status": [],
        "status_label": "string",
        "raw_status": [],
        "raw_status_label": "string",
        "billing_cycle": "string",
        "quantity": "string",
        "summary": "string",
        "due_date": "2026-07-05 12:00:00",
        "paid_at": "2026-07-05 12:00:00",
        "created_at": "2026-07-05 12:00:00",
        "updated_at": "2026-07-05 12:00:00",
        "trace_id": 1,
        "refund_trace_id": 1,
        "config_snapshot": [],
        "config_pricing_snapshot": [],
        "coupon_snapshot": "string",
        "payment_summary": "string",
        "payments": [],
        "items": [],
        "logs": [],
        "can_cancel": true
    },
    "timestamp": 1760000000
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：接口为写操作、删除操作、支付/退款/开通/服务控制/通知发送/上游动作之一，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\InvoiceController@cancel`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:invoice.manage`
