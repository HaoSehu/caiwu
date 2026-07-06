# suppliers

**请求方法**：GET  
**请求路径**：`/api/admin/suppliers`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：IndexRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：IndexRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：IndexRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|in:0,1；来源：IndexRequest |

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
| data.list.code | string | 真实调用返回字段 |
| data.list.provider_key | string | 真实调用返回字段 |
| data.list.provider_label | string | 真实调用返回字段 |
| data.list.api_url | string | 真实调用返回字段 |
| data.list.has_api_url | boolean | 真实调用返回字段 |
| data.list.api_username | string | 真实调用返回字段 |
| data.list.has_api_key | string | 真实调用返回字段 |
| data.list.has_provider_secret_values | string | 真实调用返回字段 |
| data.list.provider_config | array | 真实调用返回字段 |
| data.list.upstream_binding | object | 真实调用返回字段 |
| data.list.upstream_binding.id | integer | 真实调用返回字段 |
| data.list.upstream_binding.plugin_id | integer | 真实调用返回字段 |
| data.list.upstream_binding.provider_key | string | 真实调用返回字段 |
| data.list.upstream_binding.environment | string | 真实调用返回字段 |
| data.list.upstream_binding.status | integer | 真实调用返回字段 |
| data.list.upstream_binding.priority | integer | 真实调用返回字段 |
| data.list.upstream_binding.base_url | string | 真实调用返回字段 |
| data.list.upstream_binding.has_base_url | boolean | 真实调用返回字段 |
| data.list.upstream_binding.account_name | string | 真实调用返回字段 |
| data.list.upstream_binding.has_secret_values | string | 真实调用返回字段 |
| data.list.upstream_binding.last_checked_at | string | 真实调用返回字段 |
| data.list.upstream_binding.last_check_status | string | 真实调用返回字段 |
| data.list.upstream_binding.last_check_error | string | 真实调用返回字段 |
| data.list.contact_name | string | 真实调用返回字段 |
| data.list.contact_phone | string | 真实调用返回字段 |
| data.list.contact_email | string | 真实调用返回字段 |
| data.list.website | null | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.sort_order | integer | 真实调用返回字段 |
| data.list.notes | null | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.list.card | object | 真实调用返回字段 |
| data.list.card.provided | boolean | 真实调用返回字段 |
| data.list.card.title | string | 真实调用返回字段 |
| data.list.card.subtitle | string | 真实调用返回字段 |
| data.list.card.status | object | 真实调用返回字段 |
| data.list.card.status.label | string | 真实调用返回字段 |
| data.list.card.status.theme | string | 真实调用返回字段 |
| data.list.card.status.variant | string | 真实调用返回字段 |
| data.list.card.fields | array | 真实调用返回字段 |
| data.list.card.fields.key | string | 真实调用返回字段 |
| data.list.card.fields.label | string | 真实调用返回字段 |
| data.list.card.fields.value | string | 真实调用返回字段 |
| data.list.card.actions | array | 真实调用返回字段 |
| data.list.card.actions.key | string | 真实调用返回字段 |
| data.list.card.actions.label | string | 真实调用返回字段 |
| data.list.card.actions.action | string | 真实调用返回字段 |
| data.list.card.actions.request_action | string | 真实调用返回字段 |
| data.list.card.actions.theme | string | 真实调用返回字段 |
| data.list.card.actions.variant | string | 真实调用返回字段 |
| data.list.card.actions.disabled | boolean | 真实调用返回字段 |
| data.list.card.actions.disabled_reason | string | 真实调用返回字段 |
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
                "id": 3,
                "name": "美国主机1",
                "code": "kanghostx",
                "provider_key": "kanghostx",
                "provider_label": "康乐虚拟主机",
                "api_url": "",
                "has_api_url": true,
                "api_username": "",
                "has_api_key": "***已脱敏***",
                "has_provider_secret_values": "***已脱敏***",
                "provider_config": [],
                "upstream_binding": {
                    "id": 3,
                    "plugin_id": 16,
                    "provider_key": "kanghostx",
                    "environment": "production",
                    "status": 1,
                    "priority": 0,
                    "base_url": "",
                    "has_base_url": true,
                    "account_name": "",
                    "has_secret_values": "***已脱敏***",
                    "last_checked_at": "2026-07-05 01:58:20",
                    "last_check_status": "failed",
                    "last_check_error": "插件执行失败"
                },
                "contact_name": "",
                "contact_phone": "",
                "contact_email": "",
                "website": null,
                "status": 1,
                "sort_order": 0,
                "notes": null,
                "created_at": "2026-07-04 23:55:18",
                "updated_at": "2026-07-04 23:55:18",
                "card": {
                    "provided": true,
                    "title": "美国主机1",
                    "subtitle": "康乐虚拟主机",
                    "status": {
                        "label": "启用中",
                        "theme": "success",
                        "variant": "light"
                    },
                    "fields": [
                        {
                            "key": "panel_url",
                            "label": "面板地址",
                            "value": "http://186.241.81.83:3312"
                        },
                        {
                            "key": "connection_status",
                            "label": "连接状态",
                            "value": "失败",
                            "theme": "danger"
                        },
                        {
                            "key": "updated_at",
                            "label": "最近更新时间",
                            "value": "2026-07-05 01:58:20"
                        }
                    ],
                    "actions": [
                        {
                            "key": "refresh_card",
                            "label": "检测",
                            "action": "supplier.remote_metric.refresh",
                            "request_action": "server.supplier.refresh_card",
                            "theme": "primary",
                            "variant": "text",
                            "disabled": false,
                            "disabled_reason": "接口配置不完整，暂时无法检测连接"
                        }
                    ]
                }
            }
        ],
        "total": 3,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240517
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:17  
· 响应状态码：200  
· 调用方式：GET /api/admin/suppliers  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\SupplierController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:supplier.list`
