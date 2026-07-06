# base

**请求方法**：GET  
**请求路径**：`/api/client/services/{id}/base`  
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
| data.name | string | 真实调用返回字段 |
| data.product_display_name | string | 真实调用返回字段 |
| data.combined_display_name | string | 真实调用返回字段 |
| data.domain | string | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.status_label | string | 真实调用返回字段 |
| data.status_tone | string | 真实调用返回字段 |
| data.billing_cycle | string | 真实调用返回字段 |
| data.billing_cycle_label | string | 真实调用返回字段 |
| data.amount | string | 真实调用返回字段 |
| data.expires_at | string | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| data.auto_renew | integer | 真实调用返回字段 |
| data.suspended_reason | null | 真实调用返回字段 |
| data.remark | string | 真实调用返回字段 |
| data.custom_service_name | string | 真实调用返回字段 |
| data.has_custom_service_name | boolean | 真实调用返回字段 |
| data.custom_hostname | string | 真实调用返回字段 |
| data.has_custom_hostname | boolean | 真实调用返回字段 |
| data.has_custom_renew_pricing | boolean | 真实调用返回字段 |
| data.has_locked_pricing | boolean | 真实调用返回字段 |
| data.renew_pricing_cycles | array | 真实调用返回字段 |
| data.renew_pricing_cycles.billing_cycle | string | 真实调用返回字段 |
| data.renew_pricing_cycles.billing_cycle_label | string | 真实调用返回字段 |
| data.renew_pricing_cycles.enabled | boolean | 真实调用返回字段 |
| data.renew_pricing_cycles.base_amount | string | 真实调用返回字段 |
| data.renew_pricing_cycles.manual_amount | null | 真实调用返回字段 |
| data.renew_pricing_cycles.effective_amount | string | 真实调用返回字段 |
| data.console_mode | string | 真实调用返回字段 |
| data.is_nat_console | boolean | 真实调用返回字段 |
| data.product | object | 真实调用返回字段 |
| data.product.id | integer | 真实调用返回字段 |
| data.product.name | string | 真实调用返回字段 |
| data.product.display_name | string | 真实调用返回字段 |
| data.product.type | string | 真实调用返回字段 |
| data.product.type_label | string | 真实调用返回字段 |
| data.product.catalog_type | string | 真实调用返回字段 |
| data.invoice | object | 真实调用返回字段 |
| data.invoice.id | integer | 真实调用返回字段 |
| data.invoice.invoice_no | string | 真实调用返回字段 |
| data.invoice.status | integer | 真实调用返回字段 |
| data.invoice.paid_at | null | 真实调用返回字段 |
| data.upstream | object | 真实调用返回字段 |
| data.upstream.provider_key | string | 真实调用返回字段 |
| data.upstream.supplier_id | integer | 真实调用返回字段 |
| data.upstream.upstream_product_id | integer | 真实调用返回字段 |
| data.upstream.host_id | integer | 真实调用返回字段 |
| data.upstream.invoice_id | integer | 真实调用返回字段 |
| data.upstream.status | string | 真实调用返回字段 |
| data.upstream.status_label | string | 真实调用返回字段 |
| data.upstream.remote_error | string | 真实调用返回字段 |
| data.upstream.dedicated_ip | string | 真实调用返回字段 |
| data.upstream.os | string | 真实调用返回字段 |
| data.runtime | object | 真实调用返回字段 |
| data.runtime.power_state | string | 真实调用返回字段 |
| data.runtime.power_label | string | 真实调用返回字段 |
| data.runtime.description | string | 真实调用返回字段 |
| data.connection | object | 真实调用返回字段 |
| data.connection.hostname | string | 真实调用返回字段 |
| data.connection.username | string | 真实调用返回字段 |
| data.connection.password | string | 真实调用返回字段 |
| data.connection.has_password | string | 真实调用返回字段 |
| data.connection.port | integer | 真实调用返回字段 |
| data.connection.dedicated_ip | string | 真实调用返回字段 |
| data.connection.internal_ip | string | 真实调用返回字段 |
| data.connection.assigned_ips | array | 真实调用返回字段 |
| data.connection.nat_remote_address | string | 真实调用返回字段 |
| data.connection.nat_remote_host | string | 真实调用返回字段 |
| data.connection.nat_remote_port | integer | 真实调用返回字段 |
| data.connection.nat_remote_checked_at | string | 真实调用返回字段 |
| data.specs | array | 真实调用返回字段 |
| data.specs.key | string | 真实调用返回字段 |
| data.specs.label | string | 真实调用返回字段 |
| data.specs.value | string | 真实调用返回字段 |
| data.traffic | object | 真实调用返回字段 |
| data.traffic.usage | string | 真实调用返回字段 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 88,
        "name": "美国1区精品网 2H2G",
        "product_display_name": "美国三网精品-2H2G",
        "combined_display_name": "gscs-2vcpu-2gib",
        "domain": "ser707625720719",
        "status": 4,
        "status_label": "已取消",
        "status_tone": "muted",
        "billing_cycle": "monthly",
        "billing_cycle_label": "月付",
        "amount": "20.00",
        "expires_at": "2026-04-19 13:30:03",
        "created_at": "2026-04-19 03:56:56",
        "auto_renew": 0,
        "suspended_reason": null,
        "remark": "",
        "custom_service_name": "",
        "has_custom_service_name": false,
        "custom_hostname": "",
        "has_custom_hostname": false,
        "has_custom_renew_pricing": false,
        "has_locked_pricing": false,
        "renew_pricing_cycles": [
            {
                "billing_cycle": "monthly",
                "billing_cycle_label": "月付",
                "enabled": true,
                "base_amount": "20.00",
                "manual_amount": null,
                "effective_amount": "20.00"
            },
            {
                "billing_cycle": "quarterly",
                "billing_cycle_label": "季付",
                "enabled": true,
                "base_amount": "60.00",
                "manual_amount": null,
                "effective_amount": "60.00"
            },
            {
                "billing_cycle": "semiannually",
                "billing_cycle_label": "半年付",
                "enabled": true,
                "base_amount": "120.00",
                "manual_amount": null,
                "effective_amount": "120.00"
            },
            {
                "billing_cycle": "annually",
                "billing_cycle_label": "年付",
                "enabled": true,
                "base_amount": "240.00",
                "manual_amount": null,
                "effective_amount": "240.00"
            }
        ],
        "console_mode": "default",
        "is_nat_console": false,
        "product": {
            "id": 1,
            "name": "gscs",
            "display_name": "美国三网精品-2H2G",
            "type": "vps",
            "type_label": "云服务器",
            "catalog_type": "vps"
        },
        "invoice": {
            "id": 0,
            "invoice_no": "",
            "status": 0,
            "paid_at": null
        },
        "upstream": {
            "provider_key": "mofang_finance_api",
            "supplier_id": 1,
            "upstream_product_id": 453,
            "host_id": 71331,
            "invoice_id": 0,
            "status": "Deleted",
            "status_label": "已删除",
            "remote_error": "",
            "dedicated_ip": "",
            "os": "Ubuntu-16.04-x64"
        },
        "runtime": {
            "power_state": "",
            "power_label": "",
            "description": ""
        },
        "connection": {
            "hostname": "ser707625720719",
            "username": "root",
            "password": "***已脱敏***",
            "has_password": "***已脱敏***",
            "port": 0,
            "dedicated_ip": "",
            "internal_ip": "",
            "assigned_ips": [],
            "nat_remote_address": "",
            "nat_remote_host": "",
            "nat_remote_port": 0,
            "nat_remote_checked_at": "2026-07-05 16:35:31"
        },
        "specs": [
            {
                "key": "area",
                "label": "区域",
                "value": "美国"
            },
            {
                "key": "os",
                "label": "操作系统",
                "value": "Ubuntu-16.04-x64"
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
                "key": "bw",
                "label": "带宽",
                "value": "50Mbps"
            },
            {
                "key": "ip_num",
                "label": "IP数量",
                "value": "1"
            },
            {
                "key": "data_disk_size",
                "label": "数据盘",
                "value": "50G"
            }
        ],
        "traffic": {
            "usage": "0.03",
            "limit": 0,
            "remaining": "",
            "usage_label": "0.03G",
            "limit_label": "不限",
            "remaining_label": "不限",
            "usage_percent": null,
            "limited": false,
            "button_text": "购买流量包",
            "display_threshold_percent": 0,
            "purchase_enabled": true
        },
        "actions": {
            "refresh": true,
            "power": false,
            "module_status": true,
            "manual_provision": false,
            "password_reset": "***已脱敏***",
            "reinstall": false,
            "traffic_package": false,
            "available": [
                "on",
                "off",
                "reboot",
                "hard_off",
                "hard_reboot"
            ]
        }
    },
    "timestamp": 1783240532
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:32  
· 响应状态码：200  
· 调用方式：GET /api/client/services/{id}/base  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@baseDetail`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
