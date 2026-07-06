# product-types

**请求方法**：GET  
**请求路径**：`/api/admin/product-types`  
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
| data.list.internal_id | integer | 真实调用返回字段 |
| data.list.value | string | 真实调用返回字段 |
| data.list.label | string | 真实调用返回字段 |
| data.list.first_product_group_id | integer | 真实调用返回字段 |
| data.list.first_product_group_code | string | 真实调用返回字段 |
| data.list.first_product_group_name | string | 真实调用返回字段 |
| data.list.icon | string | 真实调用返回字段 |
| data.list.is_builtin | boolean | 真实调用返回字段 |
| data.list.is_hidden | boolean | 真实调用返回字段 |
| data.list.sort_order | integer | 真实调用返回字段 |
| data.list.usage_count | integer | 真实调用返回字段 |
| data.list.group_count | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "internal_id": 1,
                "value": "vps",
                "label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "icon": "",
                "is_builtin": true,
                "is_hidden": false,
                "sort_order": 1,
                "usage_count": 106,
                "group_count": 27
            },
            {
                "internal_id": 2,
                "value": "dedicated",
                "label": "游戏云",
                "first_product_group_id": 2,
                "first_product_group_code": "dedicated",
                "first_product_group_name": "游戏云",
                "icon": "",
                "is_builtin": true,
                "is_hidden": false,
                "sort_order": 2,
                "usage_count": 10,
                "group_count": 4
            },
            {
                "internal_id": 4,
                "value": "domain",
                "label": "云电脑",
                "first_product_group_id": 3,
                "first_product_group_code": "domain",
                "first_product_group_name": "云电脑",
                "icon": "",
                "is_builtin": true,
                "is_hidden": false,
                "sort_order": 3,
                "usage_count": 5,
                "group_count": 3
            },
            {
                "internal_id": 7,
                "value": "type_iwjqnj",
                "label": "裸金属",
                "first_product_group_id": 4,
                "first_product_group_code": "type_iwjqnj",
                "first_product_group_name": "裸金属",
                "icon": "",
                "is_builtin": false,
                "is_hidden": false,
                "sort_order": 4,
                "usage_count": 4,
                "group_count": 2
            },
            {
                "internal_id": 5,
                "value": "other",
                "label": "CDN",
                "first_product_group_id": 5,
                "first_product_group_code": "other",
                "first_product_group_name": "CDN",
                "icon": "",
                "is_builtin": true,
                "is_hidden": false,
                "sort_order": 5,
                "usage_count": 0,
                "group_count": 0
            },
            {
                "internal_id": 6,
                "value": "type_ipragu",
                "label": "其他",
                "first_product_group_id": 6,
                "first_product_group_code": "type_ipragu",
                "first_product_group_name": "其他",
                "icon": "",
                "is_builtin": false,
                "is_hidden": false,
                "sort_order": 6,
                "usage_count": 1,
                "group_count": 2
            },
            {
                "internal_id": 9,
                "value": "type_tgynng",
                "label": "物理机",
                "first_product_group_id": 8,
                "first_product_group_code": "type_tgynng",
                "first_product_group_name": "物理机",
                "icon": "physical",
                "is_builtin": false,
                "is_hidden": false,
                "sort_order": 7,
                "usage_count": 0,
                "group_count": 3
            },
            {
                "internal_id": 10,
                "value": "type_1",
                "label": "1",
                "first_product_group_id": 10,
                "first_product_group_code": "type_1",
                "first_product_group_name": "1",
                "icon": "",
                "is_builtin": false,
                "is_hidden": false,
                "sort_order": 8,
                "usage_count": 0,
                "group_count": 0
            }
        ]
    },
    "timestamp": 1783240512
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:12  
· 响应状态码：200  
· 调用方式：GET /api/admin/product-types  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ProductTypeController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.list`
