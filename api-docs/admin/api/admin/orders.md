# orders

**请求方法**：GET  
**请求路径**：`/api/admin/orders`  
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
| data.list.invoice.paid_at | null | 真实调用返回字段 |
| data.list.invoice.trace_id | string | 真实调用返回字段 |
| data.list.invoice.refund_trace_id | string | 真实调用返回字段 |
| data.list.product_id | integer | 真实调用返回字段 |
| data.list.product_name | string | 真实调用返回字段 |
| data.list.product_full_path | string | 真实调用返回字段 |
| data.list.product_type | string | 真实调用返回字段 |
| data.list.service | null | 真实调用返回字段 |
| data.list.type | string | 真实调用返回字段 |
| data.list.type_label | string | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.amount | string | 真实调用返回字段 |
| data.list.discount | string | 真实调用返回字段 |
| data.list.paid_amount | string | 真实调用返回字段 |
| data.list.billing_cycle | string | 真实调用返回字段 |
| data.list.quantity | integer | 真实调用返回字段 |
| data.list.paid_at | null | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.list.trace_id | string | 真实调用返回字段 |
| data.list.config_snapshot | object | 真实调用返回字段 |
| data.list.config_snapshot.bw | string | 真实调用返回字段 |
| data.list.config_snapshot.os | string | 真实调用返回字段 |
| data.list.config_snapshot.cpu | string | 真实调用返回字段 |
| data.list.config_snapshot.area | string | 真实调用返回字段 |
| data.list.config_snapshot.ip_num | string | 真实调用返回字段 |
| data.list.config_snapshot.memory | string | 真实调用返回字段 |
| data.list.config_snapshot.flow_limit | string | 真实调用返回字段 |
| data.list.config_snapshot._schema_type | string | 真实调用返回字段 |
| data.list.config_snapshot.network_type | string | 真实调用返回字段 |
| data.list.config_snapshot._schema_version | integer | 真实调用返回字段 |
| data.list.config_snapshot.system_disk_size | string | 真实调用返回字段 |
| data.list.config_snapshot.product_full_path | string | 真实调用返回字段 |
| data.list.config_snapshot.traffic_bill_type | string | 真实调用返回字段 |
| data.list.config_snapshot.product_path_segments | array | 真实调用返回字段 |
| data.list.config_snapshot.first_product_group_name | string | 真实调用返回字段 |
| data.list.config_snapshot.third_product_group_name | string | 真实调用返回字段 |
| data.list.config_snapshot.second_product_group_name | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot | object | 真实调用返回字段 |
| data.list.config_pricing_snapshot.items | array | 真实调用返回字段 |
| data.list.config_pricing_snapshot.items.field | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.items.label | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.items.value | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.items.amount | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.items.value_label | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.quantity | integer | 真实调用返回字段 |
| data.list.config_pricing_snapshot.setup_fee | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.base_amount | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot._schema_type | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.total_amount | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.config_amount | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.unit_setup_fee | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot._schema_version | integer | 真实调用返回字段 |
| data.list.config_pricing_snapshot.unit_base_amount | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.unit_total_amount | string | 真实调用返回字段 |
| data.list.config_pricing_snapshot.unit_config_amount | string | 真实调用返回字段 |
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
                "user_id": 1,
                "user": {
                    "id": 1,
                    "email": "2908990438@qq.com",
                    "nickname": "李维佳",
                    "phone": "19219178808"
                },
                "invoice": {
                    "id": 2168,
                    "invoice_no": "zd202607050200009940",
                    "status": 2,
                    "amount": "48.00",
                    "paid_amount": "0.00",
                    "paid_at": null,
                    "trace_id": "",
                    "refund_trace_id": ""
                },
                "product_id": 82,
                "product_name": "gscs-2vcpu-2gib",
                "product_full_path": "云服务器/襄阳/高宽/gscs-2vcpu-2gib",
                "product_type": "vps",
                "service": null,
                "type": "new",
                "type_label": "新购",
                "status": 0,
                "status_label": "待付款",
                "amount": "48.00",
                "discount": "0.00",
                "paid_amount": "0.00",
                "billing_cycle": "monthly",
                "quantity": 1,
                "paid_at": null,
                "created_at": "2026-07-05 02:00:00",
                "updated_at": "2026-07-05 02:00:00",
                "trace_id": "",
                "config_snapshot": {
                    "bw": "683521",
                    "os": "683486",
                    "cpu": "2",
                    "area": "5",
                    "ip_num": "683523",
                    "memory": "683514",
                    "flow_limit": "683529",
                    "_schema_type": "order.config_snapshot",
                    "network_type": "683519",
                    "_schema_version": 1,
                    "system_disk_size": "683516",
                    "product_full_path": "云服务器/襄阳/高宽/gscs-2vcpu-2gib",
                    "traffic_bill_type": "last_30days",
                    "product_path_segments": [
                        "云服务器",
                        "襄阳",
                        "高宽",
                        "gscs-2vcpu-2gib"
                    ],
                    "first_product_group_name": "云服务器",
                    "third_product_group_name": "高宽",
                    "second_product_group_name": "襄阳"
                },
                "config_pricing_snapshot": {
                    "items": [
                        {
                            "field": "area",
                            "label": "区域",
                            "value": "5",
                            "amount": "0.00",
                            "value_label": "湖北襄阳"
                        },
                        {
                            "field": "os",
                            "label": "操作系统",
                            "value": "683486",
                            "amount": "0.00",
                            "value_label": "CentOS^CentOS-7.6.1810-x64"
                        },
                        {
                            "field": "cpu",
                            "label": "CPU",
                            "value": "2",
                            "amount": "0.00",
                            "value_label": "2核"
                        },
                        {
                            "field": "memory",
                            "label": "内存",
                            "value": "683514",
                            "amount": "0.00",
                            "value_label": "2G"
                        },
                        {
                            "field": "system_disk_size",
                            "label": "系统盘",
                            "value": "683516",
                            "amount": "0.00",
                            "value_label": "50GB"
                        },
                        {
                            "field": "network_type",
                            "label": "网络类型",
                            "value": "683519",
                            "amount": "0.00",
                            "value_label": "VPC网络"
                        },
                        {
                            "field": "bw",
                            "label": "带宽",
                            "value": "683521",
                            "amount": "0.00",
                            "value_label": "300Mbps"
                        },
                        {
                            "field": "ip_num",
                            "label": "IP数量",
                            "value": "683523",
                            "amount": "0.00",
                            "value_label": "1IP"
                        },
                        {
                            "field": "traffic_bill_type",
                            "label": "流量计费方式",
                            "value": "last_30days",
                            "amount": "0.00",
                            "value_label": "订购日至下月"
                        },
                        {
                            "field": "flow_limit",
                            "label": "流量",
                            "value": "683529",
                            "amount": "0.00",
                            "value_label": "1024GB"
                        }
                    ],
                    "quantity": 1,
                    "setup_fee": "0.00",
                    "base_amount": "48.00",
                    "_schema_type": "order.config_pricing_snapshot",
                    "total_amount": "48.00",
                    "config_amount": "0.00",
                    "unit_setup_fee": "0.00",
                    "_schema_version": 1,
                    "unit_base_amount": "48.00",
                    "unit_total_amount": "48.00",
                    "unit_config_amount": "0.00"
                }
            }
        ],
        "total": 262,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240510
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:10  
· 响应状态码：200  
· 调用方式：GET /api/admin/orders  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\OrderController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:order.list`
