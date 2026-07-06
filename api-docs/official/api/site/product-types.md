# product-types

**请求方法**：GET  
**请求路径**：`/api/site/product-types`  
**调试状态**：✅ 通过

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |

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
| data.list.value | string | 真实调用返回字段 |
| data.list.label | string | 真实调用返回字段 |
| data.list.first_product_group_id | integer | 真实调用返回字段 |
| data.list.first_product_group_code | string | 真实调用返回字段 |
| data.list.first_product_group_name | string | 真实调用返回字段 |
| data.list.icon | string | 真实调用返回字段 |
| data.list.group_count | integer | 真实调用返回字段 |
| data.list.product_count | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "id": 1,
                "value": "vps",
                "label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "icon": "",
                "group_count": 7,
                "product_count": 92
            },
            {
                "id": 2,
                "value": "dedicated",
                "label": "游戏云",
                "first_product_group_id": 2,
                "first_product_group_code": "dedicated",
                "first_product_group_name": "游戏云",
                "icon": "",
                "group_count": 2,
                "product_count": 10
            },
            {
                "id": 4,
                "value": "domain",
                "label": "云电脑",
                "first_product_group_id": 3,
                "first_product_group_code": "domain",
                "first_product_group_name": "云电脑",
                "icon": "",
                "group_count": 1,
                "product_count": 4
            },
            {
                "id": 7,
                "value": "type_iwjqnj",
                "label": "裸金属",
                "first_product_group_id": 4,
                "first_product_group_code": "type_iwjqnj",
                "first_product_group_name": "裸金属",
                "icon": "",
                "group_count": 1,
                "product_count": 4
            },
            {
                "id": 9,
                "value": "type_tgynng",
                "label": "物理机",
                "first_product_group_id": 8,
                "first_product_group_code": "type_tgynng",
                "first_product_group_name": "物理机",
                "icon": "physical",
                "group_count": 2,
                "product_count": 0
            }
        ]
    },
    "timestamp": 1783240543
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:43  
· 响应状态码：200  
· 调用方式：GET /api/site/product-types  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteProductController@productTypes`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api`
