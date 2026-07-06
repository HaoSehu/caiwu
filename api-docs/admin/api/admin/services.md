# 全量服务列表

**请求方法**：GET  
**请求路径**：`/api/admin/services`  
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
| 无 | - | 否 | 无请求参数 |

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
| data.list.service_id | integer | 真实调用返回字段 |
| data.list.instance_id | integer | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.product_display_name | string | 真实调用返回字段 |
| data.list.domain | string | 真实调用返回字段 |
| data.list.requested_hostname | string | 真实调用返回字段 |
| data.list.custom_hostname | string | 真实调用返回字段 |
| data.list.has_custom_hostname | boolean | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.billing_cycle | string | 真实调用返回字段 |
| data.list.amount | string | 真实调用返回字段 |
| data.list.expires_at | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.auto_renew | boolean | 真实调用返回字段 |
| data.list.upstream_host_id | integer | 真实调用返回字段 |
| data.list.upstream_host_id_text | string | 真实调用返回字段 |
| data.list.upstream_host_ids | array | 真实调用返回字段 |
| data.list.dedicated_ip | string | 真实调用返回字段 |
| data.list.host_ips | array | 真实调用返回字段 |
| data.list.internal_ip | string | 真实调用返回字段 |
| data.list.host_username | string | 真实调用返回字段 |
| data.list.connection | object | 真实调用返回字段 |
| data.list.connection.hostname | string | 真实调用返回字段 |
| data.list.connection.username | string | 真实调用返回字段 |
| data.list.connection.internal_ip | string | 真实调用返回字段 |
| data.list.connection.port | integer | 真实调用返回字段 |
| data.list.os | string | 真实调用返回字段 |
| data.list.user | object | 真实调用返回字段 |
| data.list.user.id | integer | 真实调用返回字段 |
| data.list.user.username | string | 真实调用返回字段 |
| data.list.user.email | string | 真实调用返回字段 |
| data.list.user.phone | string | 真实调用返回字段 |
| data.list.user.status | integer | 真实调用返回字段 |
| data.list.product | object | 真实调用返回字段 |
| data.list.product.id | integer | 真实调用返回字段 |
| data.list.product.name | string | 真实调用返回字段 |
| data.list.product.type | string | 真实调用返回字段 |
| data.list.order | object | 真实调用返回字段 |
| data.list.order.id | integer | 真实调用返回字段 |
| data.list.order.order_no | string | 真实调用返回字段 |
| data.list.invoice | object | 真实调用返回字段 |
| data.list.invoice.id | integer | 真实调用返回字段 |
| data.list.invoice.invoice_no | string | 真实调用返回字段 |
| data.list.invoice.status | integer | 真实调用返回字段 |
| data.list.invoice.paid_at | null | 真实调用返回字段 |
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
                "id": 189,
                "service_id": 189,
                "instance_id": 189,
                "name": "gscs-2vcpu-2gib",
                "product_display_name": "未配置规格",
                "domain": "ser784470365925",
                "requested_hostname": "ser784470365925",
                "custom_hostname": "",
                "has_custom_hostname": false,
                "status": 1,
                "status_label": "已开通",
                "billing_cycle": "monthly",
                "amount": "48.00",
                "expires_at": "2026-08-04 14:09:51",
                "created_at": "2026-07-04 22:09:33",
                "auto_renew": false,
                "upstream_host_id": 81725,
                "upstream_host_id_text": "81725",
                "upstream_host_ids": [
                    "81725"
                ],
                "dedicated_ip": "171.80.3.207",
                "host_ips": [
                    "171.80.3.207"
                ],
                "internal_ip": "",
                "host_username": "root",
                "connection": {
                    "hostname": "ser784470365925",
                    "username": "root",
                    "internal_ip": "",
                    "port": 0
                },
                "os": "CentOS-7.6.1810-x64",
                "user": {
                    "id": 1,
                    "username": "李维佳",
                    "email": "2908990438@qq.com",
                    "phone": "19219178808",
                    "status": 1
                },
                "product": {
                    "id": 82,
                    "name": "gscs",
                    "type": "vps"
                },
                "order": {
                    "id": 274,
                    "order_no": "dd202607042209238520"
                },
                "invoice": {
                    "id": 0,
                    "invoice_no": "",
                    "status": 0,
                    "paid_at": null
                }
            }
        ],
        "total": 154,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240516
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:16  
· 响应状态码：200  
· 调用方式：GET /api/admin/services  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ServiceController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.list`
