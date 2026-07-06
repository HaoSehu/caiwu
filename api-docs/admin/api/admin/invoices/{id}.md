# {id}

**请求方法**：GET  
**请求路径**：`/api/admin/invoices/{id}`  
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
| id | integer\|string | 是 | 路径参数；来自路由占位 `{id}` |

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
| data.id | integer | 真实调用返回字段 |
| data.invoice_no | string | 真实调用返回字段 |
| data.user_id | integer | 真实调用返回字段 |
| data.product_spec_snapshot | string | 真实调用返回字段 |
| data.product_spec_display | string | 真实调用返回字段 |
| data.product_display_name | string | 真实调用返回字段 |
| data.combined_display_name | string | 真实调用返回字段 |
| data.user | object | 真实调用返回字段 |
| data.user.id | integer | 真实调用返回字段 |
| data.user.email | string | 真实调用返回字段 |
| data.user.nickname | string | 真实调用返回字段 |
| data.user.phone | string | 真实调用返回字段 |
| data.order_id | integer | 真实调用返回字段 |
| data.order | null | 真实调用返回字段 |
| data.product_id | integer | 真实调用返回字段 |
| data.product | null | 真实调用返回字段 |
| data.service | null | 真实调用返回字段 |
| data.type | string | 真实调用返回字段 |
| data.type_label | string | 真实调用返回字段 |
| data.scene | object | 真实调用返回字段 |
| data.scene.kind | string | 真实调用返回字段 |
| data.scene.headline | string | 真实调用返回字段 |
| data.scene.subheadline | string | 真实调用返回字段 |
| data.scene.badge | string | 真实调用返回字段 |
| data.scene.highlight | string | 真实调用返回字段 |
| data.scene.remark | string | 真实调用返回字段 |
| data.scene.fields | array | 真实调用返回字段 |
| data.scene.fields.label | string | 真实调用返回字段 |
| data.scene.fields.value | string | 真实调用返回字段 |
| data.scene.items | array | 真实调用返回字段 |
| data.scene.items.description | string | 真实调用返回字段 |
| data.scene.items.amount | number | 真实调用返回字段 |
| data.amount | string | 真实调用返回字段 |
| data.discount | string | 真实调用返回字段 |
| data.paid_amount | string | 真实调用返回字段 |
| data.payable_amount | string | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.status_label | string | 真实调用返回字段 |
| data.raw_status | integer | 真实调用返回字段 |
| data.raw_status_label | string | 真实调用返回字段 |
| data.billing_cycle | string | 真实调用返回字段 |
| data.quantity | integer | 真实调用返回字段 |
| data.summary | object | 真实调用返回字段 |
| data.summary.headline | string | 真实调用返回字段 |
| data.summary.subheadline | string | 真实调用返回字段 |
| data.summary.badge | string | 真实调用返回字段 |
| data.summary.highlight | string | 真实调用返回字段 |
| data.summary.remark | string | 真实调用返回字段 |
| data.due_date | string | 真实调用返回字段 |
| data.paid_at | string | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| data.updated_at | string | 真实调用返回字段 |
| data.trace_id | string | 真实调用返回字段 |
| data.refund_trace_id | string | 真实调用返回字段 |
| data.config_snapshot | array | 真实调用返回字段 |
| data.config_pricing_snapshot | array | 真实调用返回字段 |
| data.coupon_snapshot | array | 真实调用返回字段 |
| data.payment_summary | null | 真实调用返回字段 |
| data.payments | array | 真实调用返回字段 |
| data.items | array | 真实调用返回字段 |
| data.items.id | integer | 真实调用返回字段 |
| data.items.description | string | 真实调用返回字段 |
| data.items.amount | string | 真实调用返回字段 |
| data.logs | array | 真实调用返回字段 |
| data.can_cancel | boolean | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 1,
        "invoice_no": "202501201287123",
        "user_id": 1,
        "product_spec_snapshot": "",
        "product_spec_display": "新购账单",
        "product_display_name": "新购账单",
        "combined_display_name": "新购账单",
        "user": {
            "id": 1,
            "email": "2908990438@qq.com",
            "nickname": "李维佳",
            "phone": "19219178808"
        },
        "order_id": 0,
        "order": null,
        "product_id": 0,
        "product": null,
        "service": null,
        "type": "normal",
        "type_label": "新购账单",
        "scene": {
            "kind": "new_purchase",
            "headline": "新购账单",
            "subheadline": "首次购买产生的账单，通常包含产品价格、配置附加费与优惠信息。",
            "badge": "新购",
            "highlight": "",
            "remark": "新购订单已生成",
            "fields": [
                {
                    "label": "账单编号",
                    "value": "202501201287123"
                },
                {
                    "label": "配置名称",
                    "value": "新购账单"
                },
                {
                    "label": "账单金额",
                    "value": "0.10"
                },
                {
                    "label": "已付金额",
                    "value": "0.10"
                },
                {
                    "label": "应付金额",
                    "value": "0.00"
                },
                {
                    "label": "数量",
                    "value": "1"
                },
                {
                    "label": "优惠抵扣",
                    "value": "0.00"
                }
            ],
            "items": [
                {
                    "description": "新购账单",
                    "amount": 0.1
                }
            ]
        },
        "amount": "0.10",
        "discount": "0.00",
        "paid_amount": "0.10",
        "payable_amount": "0.00",
        "status": 1,
        "status_label": "已付",
        "raw_status": 1,
        "raw_status_label": "已付",
        "billing_cycle": "",
        "quantity": 1,
        "summary": {
            "headline": "新购账单",
            "subheadline": "首次购买产生的账单，通常包含产品价格、配置附加费与优惠信息。",
            "badge": "新购",
            "highlight": "新购订单已生成",
            "remark": "新购订单已生成"
        },
        "due_date": "2025-01-20",
        "paid_at": "2025-01-20 13:27:15",
        "created_at": "2025-01-20 13:22:47",
        "updated_at": "2026-03-25 17:03:22",
        "trace_id": "legacy-invoice-1",
        "refund_trace_id": "",
        "config_snapshot": [],
        "config_pricing_snapshot": [],
        "coupon_snapshot": [],
        "payment_summary": null,
        "payments": [],
        "items": [
            {
                "id": 1,
                "description": "账单项目",
                "amount": "0.10"
            }
        ],
        "logs": [],
        "can_cancel": false
    },
    "timestamp": 1783240489
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:49  
· 响应状态码：200  
· 调用方式：GET /api/admin/invoices/{id}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\InvoiceController@show`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:invoice.detail`
