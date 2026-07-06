# {id}

**请求方法**：GET  
**请求路径**：`/api/client/orders/{id}`  
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
| data.type | string | 真实调用返回字段 |
| data.type_label | string | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.status_label | string | 真实调用返回字段 |
| data.amount | string | 真实调用返回字段 |
| data.paid_amount | string | 真实调用返回字段 |
| data.discount | string | 真实调用返回字段 |
| data.billing_cycle | string | 真实调用返回字段 |
| data.quantity | integer | 真实调用返回字段 |
| data.product_name | string | 真实调用返回字段 |
| data.product_full_path | string | 真实调用返回字段 |
| data.service_name | string | 真实调用返回字段 |
| data.invoice | object | 真实调用返回字段 |
| data.invoice.id | integer | 真实调用返回字段 |
| data.invoice.invoice_no | string | 真实调用返回字段 |
| data.invoice.type | string | 真实调用返回字段 |
| data.invoice.status | integer | 真实调用返回字段 |
| data.invoice.amount | string | 真实调用返回字段 |
| data.invoice.paid_amount | string | 真实调用返回字段 |
| data.invoice.paid_at | null | 真实调用返回字段 |
| data.invoice.due_date | string | 真实调用返回字段 |
| data.invoice.created_at | string | 真实调用返回字段 |
| data.paid_at | null | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| data.coupon | null | 真实调用返回字段 |
| data.coupon_code | string | 真实调用返回字段 |
| data.remark | string | 真实调用返回字段 |
| data.service | null | 真实调用返回字段 |
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
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 58,
        "order_no": "ORD202603261314167685",
        "type": "new",
        "type_label": "新购",
        "status": 4,
        "status_label": "已取消",
        "amount": "110.00",
        "paid_amount": "0.00",
        "discount": "0.00",
        "billing_cycle": "monthly",
        "quantity": 1,
        "product_name": "美国三网精品-16H16G",
        "product_full_path": "云服务器/美国/三网精品/美国三网精品-16H16G",
        "service_name": "",
        "invoice": {
            "id": 1294,
            "invoice_no": "INV202603261314168476",
            "type": "normal",
            "status": 2,
            "amount": "110.00",
            "paid_amount": "0.00",
            "paid_at": null,
            "due_date": "2026-04-02 00:00:00",
            "created_at": "2026-03-26 05:14:16"
        },
        "paid_at": null,
        "created_at": "2026-03-26 05:14:16",
        "coupon": null,
        "coupon_code": "",
        "remark": "",
        "service": null,
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
        "config_pricing_snapshot": []
    },
    "timestamp": 1783240527
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:27  
· 响应状态码：200  
· 调用方式：GET /api/client/orders/{id}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\OrderController@show`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
