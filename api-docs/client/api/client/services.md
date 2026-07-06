# services

**请求方法**：GET  
**请求路径**：`/api/client/services`  
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
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：IndexRequest |
| status | integer | 否 | 查询参数；校验规则：nullable\|integer；来源：IndexRequest |
| status_scope | string | 否 | 查询参数；校验规则：nullable\|string\|in:"active_pending"；来源：IndexRequest |
| quick_filter | string | 否 | 查询参数；校验规则：nullable\|string\|in:"expiring_7d","auto_renew_enabled","auto_renew_7d"；来源：IndexRequest |
| catalog_type | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：IndexRequest |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：IndexRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:50；来源：IndexRequest |

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
| data.list.name | string | 真实调用返回字段 |
| data.list.product_display_name | string | 真实调用返回字段 |
| data.list.domain | string | 真实调用返回字段 |
| data.list.custom_hostname | string | 真实调用返回字段 |
| data.list.has_custom_hostname | boolean | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.status_tone | string | 真实调用返回字段 |
| data.list.billing_cycle | string | 真实调用返回字段 |
| data.list.billing_cycle_label | string | 真实调用返回字段 |
| data.list.amount | string | 真实调用返回字段 |
| data.list.expires_at | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.product | object | 真实调用返回字段 |
| data.list.product.name | string | 真实调用返回字段 |
| data.list.product.display_name | string | 真实调用返回字段 |
| data.list.product.type | string | 真实调用返回字段 |
| data.list.product.type_label | string | 真实调用返回字段 |
| data.list.product.catalog_type | string | 真实调用返回字段 |
| data.list.product.group_name | string | 真实调用返回字段 |
| data.list.product.root_group_name | string | 真实调用返回字段 |
| data.list.product.menu_name | string | 真实调用返回字段 |
| data.list.invoice | object | 真实调用返回字段 |
| data.list.invoice.id | integer | 真实调用返回字段 |
| data.list.invoice.invoice_no | string | 真实调用返回字段 |
| data.list.custom_service_name | string | 真实调用返回字段 |
| data.list.has_custom_service_name | boolean | 真实调用返回字段 |
| data.list.upstream | object | 真实调用返回字段 |
| data.list.upstream.host_id | integer | 真实调用返回字段 |
| data.list.upstream.status | string | 真实调用返回字段 |
| data.list.upstream.status_label | string | 真实调用返回字段 |
| data.list.upstream.dedicated_ip | string | 真实调用返回字段 |
| data.list.upstream.os | string | 真实调用返回字段 |
| data.list.remark | string | 真实调用返回字段 |
| data.list.can_manage | boolean | 真实调用返回字段 |
| data.list.console_mode | string | 真实调用返回字段 |
| data.list.is_nat_console | boolean | 真实调用返回字段 |
| data.list.machine_category | object | 真实调用返回字段 |
| data.list.machine_category.key | string | 真实调用返回字段 |
| data.list.machine_category.label | string | 真实调用返回字段 |
| data.list.specs | array | 真实调用返回字段 |
| data.list.specs.key | string | 真实调用返回字段 |
| data.list.specs.label | string | 真实调用返回字段 |
| data.list.specs.value | string | 真实调用返回字段 |
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
                "name": "gscs-2vcpu-2gib",
                "product_display_name": "未配置规格",
                "domain": "ser784470365925",
                "custom_hostname": "",
                "has_custom_hostname": false,
                "status": 1,
                "status_label": "已开通",
                "status_tone": "success",
                "billing_cycle": "monthly",
                "billing_cycle_label": "月付",
                "amount": "48.00",
                "expires_at": "2026-08-04 14:09:51",
                "created_at": "2026-07-04 22:09:33",
                "product": {
                    "name": "gscs",
                    "display_name": "未配置规格",
                    "type": "vps",
                    "type_label": "云服务器",
                    "catalog_type": "vps",
                    "group_name": "高宽",
                    "root_group_name": "云服务器",
                    "menu_name": "云服务器"
                },
                "invoice": {
                    "id": 0,
                    "invoice_no": ""
                },
                "custom_service_name": "",
                "has_custom_service_name": false,
                "upstream": {
                    "host_id": 81725,
                    "status": "on",
                    "status_label": "on",
                    "dedicated_ip": "171.80.3.207",
                    "os": "CentOS-7.6.1810-x64"
                },
                "remark": "",
                "can_manage": true,
                "console_mode": "default",
                "is_nat_console": false,
                "machine_category": {
                    "key": "cloud_server",
                    "label": "云服务器"
                },
                "specs": [
                    {
                        "key": "area",
                        "label": "区域",
                        "value": "湖北襄阳"
                    },
                    {
                        "key": "os",
                        "label": "操作系统",
                        "value": "CentOS-7.6.1810-x64"
                    },
                    {
                        "key": "cpu",
                        "label": "CPU",
                        "value": "2核"
                    },
                    {
                        "key": "memory",
                        "label": "内存",
                        "value": "2G"
                    },
                    {
                        "key": "system_disk_size",
                        "label": "系统盘",
                        "value": "50GB"
                    },
                    {
                        "key": "bw",
                        "label": "带宽",
                        "value": "300Mbps"
                    },
                    {
                        "key": "flow_limit",
                        "label": "流量",
                        "value": "1024GB"
                    },
                    {
                        "key": "ip_num",
                        "label": "IP数量",
                        "value": "1IP"
                    },
                    {
                        "key": "data_disk_size",
                        "label": "数据盘",
                        "value": "0G"
                    },
                    {
                        "key": "traffic_bill_type",
                        "label": "流量计费方式",
                        "value": "订购日至下月"
                    },
                    {
                        "key": "_schema_type",
                        "label": "_schema_type",
                        "value": "order.config_snapshot"
                    },
                    {
                        "key": "_schema_version",
                        "label": "_schema_version",
                        "value": "1"
                    },
                    {
                        "key": "product_full_path",
                        "label": "product_full_path",
                        "value": "云服务器/襄阳/高宽/gscs-2vcpu-2gib"
                    },
                    {
                        "key": "product_path_segments",
                        "label": "product_path_segments",
                        "value": "云服务器, 襄阳, 高宽, gscs-2vcpu-2gib"
                    },
                    {
                        "key": "first_product_group_name",
                        "label": "first_product_group_name",
                        "value": "云服务器"
                    },
                    {
                        "key": "third_product_group_name",
                        "label": "third_product_group_name",
                        "value": "高宽"
                    },
                    {
                        "key": "second_product_group_name",
                        "label": "second_product_group_name",
                        "value": "襄阳"
                    }
                ]
            }
        ],
        "total": 8,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240528
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:28  
· 响应状态码：200  
· 调用方式：GET /api/client/services  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@index`  
· 请求校验：`App\Http\Requests\Client\Service\IndexRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
