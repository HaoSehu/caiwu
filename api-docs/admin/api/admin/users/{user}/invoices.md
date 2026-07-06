# 用户账单列表

**请求方法**：GET  
**请求路径**：`/api/admin/users/{user}/invoices`  
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
| user | integer\|string | 是 | 路径参数；来自路由占位 `{user}` |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：UserInvoicesRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：UserInvoicesRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|in:0,1,2,3,5；来源：UserInvoicesRequest |
| type | string | 否 | 查询参数；校验规则：nullable\|in:normal,renew,manual；来源：UserInvoicesRequest |

### 请求示例（完整 JSON）
```json
{
    "page": 1,
    "page_size": 1,
    "status": "1",
    "type": "normal"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.list | array | 分页列表数据 |
| data.list.id | integer | 真实调用返回字段 |
| data.list.invoice_no | string | 真实调用返回字段 |
| data.list.product_spec_snapshot | string | 真实调用返回字段 |
| data.list.product_spec_display | string | 真实调用返回字段 |
| data.list.product_display_name | string | 真实调用返回字段 |
| data.list.combined_display_name | string | 真实调用返回字段 |
| data.list.user | object | 真实调用返回字段 |
| data.list.user.id | integer | 真实调用返回字段 |
| data.list.user.email | string | 真实调用返回字段 |
| data.list.user.nickname | string | 真实调用返回字段 |
| data.list.user.phone | string | 真实调用返回字段 |
| data.list.order_id | integer | 真实调用返回字段 |
| data.list.order | object | 真实调用返回字段 |
| data.list.order.id | integer | 真实调用返回字段 |
| data.list.order.order_no | string | 真实调用返回字段 |
| data.list.order.status | integer | 真实调用返回字段 |
| data.list.order.service_id | integer | 真实调用返回字段 |
| data.list.order.billing_cycle | string | 真实调用返回字段 |
| data.list.order.paid_at | string | 真实调用返回字段 |
| data.list.order.product | object | 真实调用返回字段 |
| data.list.order.product.id | integer | 真实调用返回字段 |
| data.list.order.product.name | string | 真实调用返回字段 |
| data.list.product_id | integer | 真实调用返回字段 |
| data.list.product | object | 真实调用返回字段 |
| data.list.product.id | integer | 真实调用返回字段 |
| data.list.product.name | string | 真实调用返回字段 |
| data.list.product.product_type | string | 真实调用返回字段 |
| data.list.type | string | 真实调用返回字段 |
| data.list.type_label | string | 真实调用返回字段 |
| data.list.scene | object | 真实调用返回字段 |
| data.list.scene.kind | string | 真实调用返回字段 |
| data.list.scene.headline | string | 真实调用返回字段 |
| data.list.scene.subheadline | string | 真实调用返回字段 |
| data.list.scene.badge | string | 真实调用返回字段 |
| data.list.scene.highlight | string | 真实调用返回字段 |
| data.list.scene.remark | string | 真实调用返回字段 |
| data.list.scene.fields | array | 真实调用返回字段 |
| data.list.scene.fields.label | string | 真实调用返回字段 |
| data.list.scene.fields.value | string | 真实调用返回字段 |
| data.list.scene.items | array | 真实调用返回字段 |
| data.list.scene.items.description | string | 真实调用返回字段 |
| data.list.scene.items.amount | integer | 真实调用返回字段 |
| data.list.amount | string | 真实调用返回字段 |
| data.list.discount | string | 真实调用返回字段 |
| data.list.paid_amount | string | 真实调用返回字段 |
| data.list.payable_amount | string | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.raw_status | integer | 真实调用返回字段 |
| data.list.raw_status_label | string | 真实调用返回字段 |
| data.list.billing_cycle | string | 真实调用返回字段 |
| data.list.quantity | integer | 真实调用返回字段 |
| data.list.summary | object | 真实调用返回字段 |
| data.list.summary.headline | string | 真实调用返回字段 |
| data.list.summary.subheadline | string | 真实调用返回字段 |
| data.list.summary.badge | string | 真实调用返回字段 |
| data.list.summary.highlight | string | 真实调用返回字段 |
| data.list.summary.remark | string | 真实调用返回字段 |
| data.list.due_date | string | 真实调用返回字段 |
| data.list.paid_at | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.payment_summary | null | 真实调用返回字段 |
| data.list.refund_actions | object | 真实调用返回字段 |
| data.list.refund_actions.can_balance | boolean | 真实调用返回字段 |
| data.list.refund_actions.can_original | boolean | 真实调用返回字段 |
| data.list.refund_actions.blocked_reason | string | 真实调用返回字段 |
| data.list.refund_actions.original_blocked_reason | string | 真实调用返回字段 |
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
                "id": 2153,
                "invoice_no": "zd202607042209238520",
                "product_spec_snapshot": "gscs-2vcpu-2gib",
                "product_spec_display": "gscs-2vcpu-2gib",
                "product_display_name": "gscs",
                "combined_display_name": "gscs-2vcpu-2gib",
                "user": {
                    "id": 1,
                    "email": "2908990438@qq.com",
                    "nickname": "李维佳",
                    "phone": "19219178808"
                },
                "order_id": 274,
                "order": {
                    "id": 274,
                    "order_no": "dd202607042209238520",
                    "status": 3,
                    "service_id": 189,
                    "billing_cycle": "monthly",
                    "paid_at": "2026-07-04 22:09:28",
                    "product": {
                        "id": 82,
                        "name": "gscs"
                    }
                },
                "product_id": 82,
                "product": {
                    "id": 82,
                    "name": "gscs",
                    "product_type": "vps"
                },
                "type": "new",
                "type_label": "新购账单",
                "scene": {
                    "kind": "new_purchase",
                    "headline": "新购账单",
                    "subheadline": "首次购买产生的账单，通常包含产品价格、配置附加费与优惠信息。",
                    "badge": "新购",
                    "highlight": "gscs",
                    "remark": "新购订单已生成",
                    "fields": [
                        {
                            "label": "账单编号",
                            "value": "zd202607042209238520"
                        },
                        {
                            "label": "订单编号",
                            "value": "dd202607042209238520"
                        },
                        {
                            "label": "配置名称",
                            "value": "gscs"
                        },
                        {
                            "label": "计费周期",
                            "value": "monthly"
                        },
                        {
                            "label": "账单金额",
                            "value": "48.00"
                        },
                        {
                            "label": "已付金额",
                            "value": "48.00"
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
                            "description": "gscs / monthly",
                            "amount": 48
                        }
                    ]
                },
                "amount": "48.00",
                "discount": "0.00",
                "paid_amount": "48.00",
                "payable_amount": "0.00",
                "status": 1,
                "status_label": "已付",
                "raw_status": 1,
                "raw_status_label": "已付",
                "billing_cycle": "monthly",
                "quantity": 1,
                "summary": {
                    "headline": "新购账单",
                    "subheadline": "首次购买产生的账单，通常包含产品价格、配置附加费与优惠信息。",
                    "badge": "新购",
                    "highlight": "gscs",
                    "remark": "新购订单已生成"
                },
                "due_date": "2026-07-11",
                "paid_at": "2026-07-04 22:09:28",
                "created_at": "2026-07-04 22:09:23",
                "payment_summary": null,
                "refund_actions": {
                    "can_balance": false,
                    "can_original": false,
                    "blocked_reason": "未找到可退款的支付记录",
                    "original_blocked_reason": ""
                }
            }
        ],
        "total": 59,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240519
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:19  
· 响应状态码：200  
· 调用方式：GET /api/admin/users/{user}/invoices  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\UserController@invoices`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.detail`
