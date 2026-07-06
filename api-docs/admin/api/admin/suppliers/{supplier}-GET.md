# {supplier}

**请求方法**：GET  
**请求路径**：`/api/admin/suppliers/{supplier}`  
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
| supplier | string | 是 | 路径参数；来自路由占位 `{supplier}` |

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
| data.code | string | 真实调用返回字段 |
| data.provider_key | string | 真实调用返回字段 |
| data.provider_label | string | 真实调用返回字段 |
| data.api_url | string | 真实调用返回字段 |
| data.has_api_url | boolean | 真实调用返回字段 |
| data.api_username | string | 真实调用返回字段 |
| data.has_api_key | string | 真实调用返回字段 |
| data.provider_config | array | 真实调用返回字段 |
| data.has_provider_secret_values | string | 真实调用返回字段 |
| data.upstream_binding | object | 真实调用返回字段 |
| data.upstream_binding.id | integer | 真实调用返回字段 |
| data.upstream_binding.plugin_id | integer | 真实调用返回字段 |
| data.upstream_binding.provider_key | string | 真实调用返回字段 |
| data.upstream_binding.environment | string | 真实调用返回字段 |
| data.upstream_binding.status | integer | 真实调用返回字段 |
| data.upstream_binding.priority | integer | 真实调用返回字段 |
| data.upstream_binding.base_url | string | 真实调用返回字段 |
| data.upstream_binding.has_base_url | boolean | 真实调用返回字段 |
| data.upstream_binding.account_name | string | 真实调用返回字段 |
| data.upstream_binding.has_secret_values | string | 真实调用返回字段 |
| data.upstream_binding.last_checked_at | string | 真实调用返回字段 |
| data.upstream_binding.last_check_status | string | 真实调用返回字段 |
| data.upstream_binding.last_check_error | null | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.sort_order | integer | 真实调用返回字段 |
| data.card | object | 真实调用返回字段 |
| data.card.provided | boolean | 真实调用返回字段 |
| data.card.title | string | 真实调用返回字段 |
| data.card.subtitle | string | 真实调用返回字段 |
| data.card.status | object | 真实调用返回字段 |
| data.card.status.label | string | 真实调用返回字段 |
| data.card.status.theme | string | 真实调用返回字段 |
| data.card.status.variant | string | 真实调用返回字段 |
| data.card.fields | array | 真实调用返回字段 |
| data.card.fields.key | string | 真实调用返回字段 |
| data.card.fields.label | string | 真实调用返回字段 |
| data.card.fields.value | string | 真实调用返回字段 |
| data.card.actions | array | 真实调用返回字段 |
| data.card.actions.key | string | 真实调用返回字段 |
| data.card.actions.label | string | 真实调用返回字段 |
| data.card.actions.action | string | 真实调用返回字段 |
| data.card.actions.request_action | string | 真实调用返回字段 |
| data.card.actions.theme | string | 真实调用返回字段 |
| data.card.actions.variant | string | 真实调用返回字段 |
| data.card.actions.disabled | boolean | 真实调用返回字段 |
| data.card.actions.disabled_reason | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 1,
        "name": "极点云",
        "code": "supplier_1",
        "provider_key": "mofang_finance_api",
        "provider_label": "魔方财务接口",
        "api_url": "",
        "has_api_url": true,
        "api_username": "19083287894",
        "has_api_key": "***已脱敏***",
        "provider_config": [],
        "has_provider_secret_values": "***已脱敏***",
        "upstream_binding": {
            "id": 1,
            "plugin_id": 8,
            "provider_key": "mofang_finance_api",
            "environment": "production",
            "status": 1,
            "priority": 0,
            "base_url": "",
            "has_base_url": true,
            "account_name": "19083287894",
            "has_secret_values": "***已脱敏***",
            "last_checked_at": "2026-07-05 01:58:37",
            "last_check_status": "success",
            "last_check_error": null
        },
        "status": 1,
        "sort_order": 0,
        "card": {
            "provided": true,
            "title": "极点云",
            "subtitle": "魔方财务接口",
            "status": {
                "label": "启用中",
                "theme": "success",
                "variant": "light"
            },
            "fields": [
                {
                    "key": "username",
                    "label": "用户名",
                    "value": "19083287894"
                },
                {
                    "key": "upstream_balance",
                    "label": "上游余额",
                    "value": "-"
                },
                {
                    "key": "updated_at",
                    "label": "最近更新时间",
                    "value": "2026-07-05 01:58:37"
                }
            ],
            "actions": [
                {
                    "key": "refresh_card",
                    "label": "同步余额",
                    "action": "supplier.remote_metric.refresh",
                    "request_action": "server.supplier.refresh_card",
                    "theme": "primary",
                    "variant": "text",
                    "disabled": false,
                    "disabled_reason": "接口配置不完整，暂时无法同步余额"
                },
                {
                    "key": "bulk_connect",
                    "label": "批量导入/对接",
                    "action": "supplier.batch_connect",
                    "request_action": "server.supplier.bulk_connect",
                    "variant": "text",
                    "disabled": false,
                    "disabled_reason": "接口配置不完整，暂时无法批量对接商品"
                }
            ]
        }
    },
    "timestamp": 1783240517
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:17  
· 响应状态码：200  
· 调用方式：GET /api/admin/suppliers/{supplier}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\SupplierController@show`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:supplier.detail`
