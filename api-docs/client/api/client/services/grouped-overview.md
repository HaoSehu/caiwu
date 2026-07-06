# grouped-overview

**请求方法**：GET  
**请求路径**：`/api/client/services/grouped-overview`  
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
| data.total | integer | 总条数 |
| data.category_total | integer | 真实调用返回字段 |
| data.list | array | 分页列表数据 |
| data.list.key | string | 真实调用返回字段 |
| data.list.id | null | 真实调用返回字段 |
| data.list.product_type | string | 真实调用返回字段 |
| data.list.product_type_label | string | 真实调用返回字段 |
| data.list.icon | string | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.title | string | 真实调用返回字段 |
| data.list.description | string | 真实调用返回字段 |
| data.list.count | integer | 真实调用返回字段 |
| data.list.active_count | integer | 真实调用返回字段 |
| data.list.pending_count | integer | 真实调用返回字段 |
| data.list.expiring_count | integer | 真实调用返回字段 |
| data.list.children | array | 真实调用返回字段 |
| data.list.children.key | string | 真实调用返回字段 |
| data.list.children.id | integer | 真实调用返回字段 |
| data.list.children.name | string | 真实调用返回字段 |
| data.list.children.title | string | 真实调用返回字段 |
| data.list.children.description | string | 真实调用返回字段 |
| data.list.children.count | integer | 真实调用返回字段 |
| data.list.children.active_count | integer | 真实调用返回字段 |
| data.list.children.pending_count | integer | 真实调用返回字段 |
| data.list.children.expiring_count | integer | 真实调用返回字段 |
| data.list.children.status_label | string | 真实调用返回字段 |
| data.list.children.status_tone | string | 真实调用返回字段 |
| data.list.children.primary_service_id | integer | 真实调用返回字段 |
| data.list.children.preview_names | array | 真实调用返回字段 |
| data.list.children.console_mode | string | 真实调用返回字段 |
| data.list.children.is_nat_console | boolean | 真实调用返回字段 |
| data.list.primary_service_id | integer | 真实调用返回字段 |
| data.list.console_mode | string | 真实调用返回字段 |
| data.list.is_nat_console | boolean | 真实调用返回字段 |
| data.list.items | array | 真实调用返回字段 |
| data.list.items.id | integer | 真实调用返回字段 |
| data.list.items.name | string | 真实调用返回字段 |
| data.list.items.product_name | string | 真实调用返回字段 |
| data.list.items.group_name | string | 真实调用返回字段 |
| data.list.items.root_group_name | string | 真实调用返回字段 |
| data.list.items.status | integer | 真实调用返回字段 |
| data.list.items.status_label | string | 真实调用返回字段 |
| data.list.items.status_tone | string | 真实调用返回字段 |
| data.list.items.billing_cycle_label | string | 真实调用返回字段 |
| data.list.items.expires_at | string | 真实调用返回字段 |
| data.list.items.amount | string | 真实调用返回字段 |
| data.list.items.console_mode | string | 真实调用返回字段 |
| data.list.items.is_nat_console | boolean | 真实调用返回字段 |
| data.catalog_types | array | 真实调用返回字段 |
| data.catalog_types.label | string | 真实调用返回字段 |
| data.catalog_types.value | string | 真实调用返回字段 |
| data.catalog_types.icon | string | 真实调用返回字段 |
| data.catalog_types.count | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "total": 8,
        "category_total": 6,
        "list": [
            {
                "key": "vps",
                "id": null,
                "product_type": "vps",
                "product_type_label": "云服务器",
                "icon": "",
                "name": "云服务器",
                "title": "云服务器",
                "description": "后台一级菜单：云服务器，当前已开通 8 个服务，覆盖 4 个产品分组。",
                "count": 8,
                "active_count": 2,
                "pending_count": 2,
                "expiring_count": 0,
                "children": [
                    {
                        "key": "category-8",
                        "id": 15,
                        "name": "高宽",
                        "title": "高宽",
                        "description": "铂金8269CY CPU 200G防御 域名自助过白 SAS企业硬盘 傲盾定制防御策略， 测试IP：171.80.3.1",
                        "count": 3,
                        "active_count": 1,
                        "pending_count": 1,
                        "expiring_count": 0,
                        "status_label": "已开通",
                        "status_tone": "success",
                        "primary_service_id": 189,
                        "preview_names": [
                            "gscs-2vcpu-2gib",
                            "gscs-2vcpu-2gib"
                        ],
                        "console_mode": "default",
                        "is_nat_console": false
                    },
                    {
                        "key": "group-4",
                        "id": 3,
                        "name": "三网精品",
                        "title": "三网精品",
                        "description": "CN2+CMIN2+9929三网精品，30G DDOS防御 黑洞10分钟 测试IP 156.238.224.1（kurun机房） CPU:E5 2696V4*2/2698/2699V4*2",
                        "count": 3,
                        "active_count": 0,
                        "pending_count": 0,
                        "expiring_count": 0,
                        "status_label": "未开通",
                        "status_tone": "muted",
                        "primary_service_id": 97,
                        "preview_names": [
                            "美国1区精品网 2H2G",
                            "美国1区精品网 4H4G"
                        ],
                        "console_mode": "default",
                        "is_nat_console": false
                    },
                    {
                        "key": "group-14",
                        "id": 4,
                        "name": "特价云服务器",
                        "title": "特价云服务器",
                        "description": "当前分类已开通 1 个服务，可快速进入控制台处理业务。",
                        "count": 1,
                        "active_count": 1,
                        "pending_count": 0,
                        "expiring_count": 0,
                        "status_label": "已开通",
                        "status_tone": "success",
                        "primary_service_id": 89,
                        "preview_names": [
                            "好色狐の机器"
                        ],
                        "console_mode": "default",
                        "is_nat_console": false
                    },
                    {
                        "key": "group-3",
                        "id": 2,
                        "name": "大宽带",
                        "title": "大宽带",
                        "description": "去程CN2+NTT 回程CN2+CMI+CU动态优化 测试IP 69.165.65.1 CPU:E5 2699V4 增加流量：20/T 3/100G",
                        "count": 1,
                        "active_count": 0,
                        "pending_count": 1,
                        "expiring_count": 0,
                        "status_label": "未开通",
                        "status_tone": "muted",
                        "primary_service_id": 187,
                        "preview_names": [
                            "gscs-2vcpu-2gib"
                        ],
                        "console_mode": "default",
                        "is_nat_console": false
                    }
                ],
                "primary_service_id": 189,
                "console_mode": "default",
                "is_nat_console": false,
                "items": [
                    {
                        "id": 189,
                        "name": "gscs-2vcpu-2gib",
                        "product_name": "gscs",
                        "group_name": "高宽",
                        "root_group_name": "云服务器",
                        "status": 1,
                        "status_label": "已开通",
                        "status_tone": "success",
                        "billing_cycle_label": "月付",
                        "expires_at": "2026-08-04 14:09:51",
                        "amount": "48.00",
                        "console_mode": "default",
                        "is_nat_console": false
                    },
                    {
                        "id": 188,
                        "name": "gscs-2vcpu-2gib",
                        "product_name": "gscs",
                        "group_name": "高宽",
                        "root_group_name": "云服务器",
                        "status": 0,
                        "status_label": "开通中",
                        "status_tone": "info",
                        "billing_cycle_label": "月付",
                        "expires_at": null,
                        "amount": "48.00",
                        "console_mode": "default",
                        "is_nat_console": false
                    },
                    {
                        "id": 187,
                        "name": "gscs-2vcpu-2gib",
                        "product_name": "gscs",
                        "group_name": "大宽带",
                        "root_group_name": "云服务器",
                        "status": 0,
                        "status_label": "开通中",
                        "status_tone": "info",
                        "billing_cycle_label": "月付",
                        "expires_at": null,
                        "amount": "23.00",
                        "console_mode": "default",
                        "is_nat_console": false
                    },
                    {
                        "id": 97,
                        "name": "美国1区精品网 2H2G",
                        "product_name": "gscs",
                        "group_name": "三网精品",
                        "root_group_name": "云服务器",
                        "status": 4,
                        "status_label": "已取消",
                        "status_tone": "muted",
                        "billing_cycle_label": "月付",
                        "expires_at": "2026-04-28 13:11:27",
                        "amount": "20.00",
                        "console_mode": "default",
                        "is_nat_console": false
                    },
                    {
                        "id": 95,
                        "name": "美国1区精品网 4H4G",
                        "product_name": "gscs",
                        "group_name": "三网精品",
                        "root_group_name": "云服务器",
                        "status": 4,
                        "status_label": "已取消",
                        "status_tone": "muted",
                        "billing_cycle_label": "月付",
                        "expires_at": "2026-04-22 09:12:21",
                        "amount": "25.00",
                        "console_mode": "default",
                        "is_nat_console": false
                    },
                    {
                        "id": 91,
                        "name": "襄阳高防大带宽 2H2G",
                        "product_name": "gscs",
                        "group_name": "高宽",
                        "root_group_name": "云服务器",
                        "status": 4,
                        "status_label": "已取消",
                        "status_tone": "muted",
                        "billing_cycle_label": "月付",
                        "expires_at": "2026-04-22 09:13:11",
                        "amount": "48.00",
                        "console_mode": "default",
                        "is_nat_console": false
                    }
                ]
            },
            {
                "key": "dedicated",
                "id": null,
                "product_type": "dedicated",
                "product_type_label": "游戏云",
                "icon": "",
                "name": "游戏云",
                "title": "游戏云",
                "description": "后台一级菜单：游戏云，当前已开通 0 个服务，覆盖 0 个产品分组。",
                "count": 0,
                "active_count": 0,
                "pending_count": 0,
                "expiring_count": 0,
                "children": [],
                "primary_service_id": 0,
                "console_mode": "default",
                "is_nat_console": false,
                "items": []
            },
            {
                "key": "domain",
                "id": null,
                "product_type": "domain",
                "product_type_label": "云电脑",
                "icon": "",
                "name": "云电脑",
                "title": "云电脑",
                "description": "后台一级菜单：云电脑，当前已开通 0 个服务，覆盖 0 个产品分组。",
                "count": 0,
                "active_count": 0,
                "pending_count": 0,
                "expiring_count": 0,
                "children": [],
                "primary_service_id": 0,
                "console_mode": "default",
                "is_nat_console": false,
                "items": []
            },
            {
                "key": "type_iwjqnj",
                "id": null,
                "product_type": "type_iwjqnj",
                "product_type_label": "裸金属",
                "icon": "",
                "name": "裸金属",
                "title": "裸金属",
                "description": "后台一级菜单：裸金属，当前已开通 0 个服务，覆盖 0 个产品分组。",
                "count": 0,
                "active_count": 0,
                "pending_count": 0,
                "expiring_count": 0,
                "children": [],
                "primary_service_id": 0,
                "console_mode": "default",
                "is_nat_console": false,
                "items": []
            },
            {
                "key": "other",
                "id": null,
                "product_type": "other",
                "product_type_label": "CDN",
                "icon": "",
                "name": "CDN",
                "title": "CDN",
                "description": "后台一级菜单：CDN，当前已开通 0 个服务，覆盖 0 个产品分组。",
                "count": 0,
                "active_count": 0,
                "pending_count": 0,
                "expiring_count": 0,
                "children": [],
                "primary_service_id": 0,
                "console_mode": "default",
                "is_nat_console": false,
                "items": []
            },
            {
                "key": "other_services",
                "id": null,
                "product_type": "other_services",
                "product_type_label": "其他服务",
                "icon": "",
                "name": "其他服务",
                "title": "其他服务",
                "description": "用于承载未归入前 5 个一级菜单的服务，或暂未归类的业务实例。",
                "count": 0,
                "active_count": 0,
                "pending_count": 0,
                "expiring_count": 0,
                "children": [],
                "primary_service_id": 0,
                "console_mode": "default",
                "is_nat_console": false,
                "items": []
            }
        ],
        "catalog_types": [
            {
                "label": "云服务器",
                "value": "vps",
                "icon": "",
                "count": 8
            },
            {
                "label": "游戏云",
                "value": "dedicated",
                "icon": "",
                "count": 0
            },
            {
                "label": "云电脑",
                "value": "domain",
                "icon": "",
                "count": 0
            },
            {
                "label": "裸金属",
                "value": "type_iwjqnj",
                "icon": "",
                "count": 0
            },
            {
                "label": "CDN",
                "value": "other",
                "icon": "",
                "count": 0
            },
            {
                "label": "其他",
                "value": "type_ipragu",
                "icon": "",
                "count": 0
            },
            {
                "label": "物理机",
                "value": "type_tgynng",
                "icon": "physical",
                "count": 0
            },
            {
                "label": "1",
                "value": "type_1",
                "icon": "",
                "count": 0
            }
        ]
    },
    "timestamp": 1783240528
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:28  
· 响应状态码：200  
· 调用方式：GET /api/client/services/grouped-overview  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@groupedOverview`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
