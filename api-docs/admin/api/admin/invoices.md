# invoices

**请求方法**：GET  
**请求路径**：`/api/admin/invoices`  
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
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:80；来源：InvoiceListRequest |
| invoice_no | string | 否 | 查询参数；校验规则：nullable\|string\|max:80；来源：InvoiceListRequest |
| user_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：InvoiceListRequest |
| status | integer | 否 | 查询参数；校验规则：nullable\|integer；来源：InvoiceListRequest |
| type | string | 否 | 查询参数；校验规则：nullable\|string\|in:"new","normal","renew","recharge","upgrade","deduction","referral_credit","manual"；来源：InvoiceListRequest |
| product_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：InvoiceListRequest |
| start_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：InvoiceListRequest |
| end_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：InvoiceListRequest |
| date_range | string | 否 | 查询参数；校验规则：prohibited；来源：InvoiceListRequest |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：InvoiceListRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：InvoiceListRequest |

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
| data.list.order | null | 真实调用返回字段 |
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
| data.list.scene.items.amount | number | 真实调用返回字段 |
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
| data.list.paid_at | null | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.payment_summary | null | 真实调用返回字段 |
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
                "id": 2169,
                "invoice_no": "zd202607050211301446",
                "product_spec_snapshot": "2 vCPU 2G",
                "product_spec_display": "2 vCPU 2G",
                "product_display_name": "2 vCPU 2G",
                "combined_display_name": "2 vCPU 2G",
                "user": {
                    "id": 1,
                    "email": "2908990438@qq.com",
                    "nickname": "李维佳",
                    "phone": "19219178808"
                },
                "order_id": 0,
                "order": null,
                "product_id": 35,
                "product": {
                    "id": 35,
                    "name": "2 vCPU 2G",
                    "product_type": "vps"
                },
                "type": "renew",
                "type_label": "续费账单",
                "scene": {
                    "kind": "renew",
                    "headline": "续费账单",
                    "subheadline": "用于延长现有服务周期，通常与已有实例关联。",
                    "badge": "续费",
                    "highlight": "monthly",
                    "remark": "续费订单已生成",
                    "fields": [
                        {
                            "label": "账单编号",
                            "value": "zd202607050211301446"
                        },
                        {
                            "label": "配置名称",
                            "value": "2 vCPU 2G"
                        },
                        {
                            "label": "计费周期",
                            "value": "monthly"
                        },
                        {
                            "label": "账单金额",
                            "value": "9.90"
                        },
                        {
                            "label": "已付金额",
                            "value": "0.00"
                        },
                        {
                            "label": "应付金额",
                            "value": "9.90"
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
                            "description": "2 vCPU 2G / monthly",
                            "amount": 9.9
                        }
                    ]
                },
                "amount": "9.90",
                "discount": "0.00",
                "paid_amount": "0.00",
                "payable_amount": "9.90",
                "status": 2,
                "status_label": "已取消",
                "raw_status": 2,
                "raw_status_label": "已取消",
                "billing_cycle": "monthly",
                "quantity": 1,
                "summary": {
                    "headline": "续费账单",
                    "subheadline": "用于延长现有服务周期，通常与已有实例关联。",
                    "badge": "续费",
                    "highlight": "monthly",
                    "remark": "续费订单已生成"
                },
                "due_date": "2026-07-12",
                "paid_at": null,
                "created_at": "2026-07-05 02:11:30",
                "payment_summary": null
            }
        ],
        "total": 2163,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240489
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:49  
· 响应状态码：200  
· 调用方式：GET /api/admin/invoices  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\InvoiceController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:invoice.list`
