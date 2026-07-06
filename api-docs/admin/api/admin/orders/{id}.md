# {id}

**请求方法**：GET  
**请求路径**：`/api/admin/orders/{id}`  
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
| data.order_no | string | 真实调用返回字段 |
| data.user_id | integer | 真实调用返回字段 |
| data.user | object | 真实调用返回字段 |
| data.user.id | integer | 真实调用返回字段 |
| data.user.email | string | 真实调用返回字段 |
| data.user.nickname | string | 真实调用返回字段 |
| data.user.phone | string | 真实调用返回字段 |
| data.invoice | object | 真实调用返回字段 |
| data.invoice.id | integer | 真实调用返回字段 |
| data.invoice.invoice_no | string | 真实调用返回字段 |
| data.invoice.status | integer | 真实调用返回字段 |
| data.invoice.amount | string | 真实调用返回字段 |
| data.invoice.paid_amount | string | 真实调用返回字段 |
| data.invoice.paid_at | null | 真实调用返回字段 |
| data.invoice.trace_id | string | 真实调用返回字段 |
| data.invoice.refund_trace_id | string | 真实调用返回字段 |
| data.product_id | integer | 真实调用返回字段 |
| data.product_name | string | 真实调用返回字段 |
| data.product_full_path | string | 真实调用返回字段 |
| data.product_type | string | 真实调用返回字段 |
| data.service | null | 真实调用返回字段 |
| data.type | string | 真实调用返回字段 |
| data.type_label | string | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.status_label | string | 真实调用返回字段 |
| data.amount | string | 真实调用返回字段 |
| data.discount | string | 真实调用返回字段 |
| data.paid_amount | string | 真实调用返回字段 |
| data.billing_cycle | string | 真实调用返回字段 |
| data.quantity | integer | 真实调用返回字段 |
| data.paid_at | null | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| data.updated_at | string | 真实调用返回字段 |
| data.trace_id | string | 真实调用返回字段 |
| data.config_snapshot | object | 真实调用返回字段 |
| data.config_snapshot.bw | integer | 真实调用返回字段 |
| data.config_snapshot.os | string | 真实调用返回字段 |
| data.config_snapshot.cpu | string | 真实调用返回字段 |
| data.config_snapshot.area | string | 真实调用返回字段 |
| data.config_snapshot.ip_num | integer | 真实调用返回字段 |
| data.config_snapshot.memory | string | 真实调用返回字段 |
| data.config_snapshot.hostname | null | 真实调用返回字段 |
| data.config_snapshot.os_group | string | 真实调用返回字段 |
| data.config_snapshot.data_disk | null | 真实调用返回字段 |
| data.config_snapshot.network_type | null | 真实调用返回字段 |
| data.config_snapshot.data_disk_size | integer | 真实调用返回字段 |
| data.config_snapshot._schema_version | integer | 真实调用返回字段 |
| data.config_snapshot._schema_type | string | 真实调用返回字段 |
| data.config_pricing_snapshot | array | 真实调用返回字段 |
| data.coupon | null | 真实调用返回字段 |
| data.coupon_code | string | 真实调用返回字段 |
| data.coupon_snapshot | array | 真实调用返回字段 |
| data.remark | string | 真实调用返回字段 |
| data.payments | array | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 58,
        "order_no": "ORD202603261314167685",
        "user_id": 1,
        "user": {
            "id": 1,
            "email": "2908990438@qq.com",
            "nickname": "李维佳",
            "phone": "19219178808"
        },
        "invoice": {
            "id": 1294,
            "invoice_no": "INV202603261314168476",
            "status": 2,
            "amount": "110.00",
            "paid_amount": "0.00",
            "paid_at": null,
            "trace_id": "legacy-invoice-1294",
            "refund_trace_id": ""
        },
        "product_id": 4,
        "product_name": "美国三网精品-16H16G",
        "product_full_path": "云服务器/美国/三网精品/美国三网精品-16H16G",
        "product_type": "vps",
        "service": null,
        "type": "new",
        "type_label": "新购",
        "status": 4,
        "status_label": "已取消",
        "amount": "110.00",
        "discount": "0.00",
        "paid_amount": "0.00",
        "billing_cycle": "monthly",
        "quantity": 1,
        "paid_at": null,
        "created_at": "2026-03-26 05:14:16",
        "updated_at": "2026-03-26 07:29:56",
        "trace_id": "legacy-order-58",
        "config_snapshot": {
            "bw": 100,
            "os": "12",
            "cpu": "16",
            "area": "2",
            "ip_num": 1,
            "memory": "16384",
            "hostname": null,
            "os_group": "centos",
            "data_disk": null,
            "network_type": null,
            "data_disk_size": 120,
            "_schema_version": 1,
            "_schema_type": "order.config_snapshot"
        },
        "config_pricing_snapshot": [],
        "coupon": null,
        "coupon_code": "",
        "coupon_snapshot": [],
        "remark": "",
        "payments": []
    },
    "timestamp": 1783240510
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:11  
· 响应状态码：200  
· 调用方式：GET /api/admin/orders/{id}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\OrderController@show`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:order.detail`
