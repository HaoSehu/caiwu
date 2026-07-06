# product-categories

**请求方法**：GET  
**请求路径**：`/api/site/product-categories`  
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
| product_type | string | 否 | 查询参数；校验规则：nullable\|in:"vps","dedicated","domain","type_iwjqnj","other","type_ipragu","type_tgynng","type_1"；来源：ProductGroupsRequest |

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
| data.list.product_type | string | 真实调用返回字段 |
| data.list.product_type_id | integer | 真实调用返回字段 |
| data.list.product_type_label | string | 真实调用返回字段 |
| data.list.first_product_group_id | integer | 真实调用返回字段 |
| data.list.first_product_group_code | string | 真实调用返回字段 |
| data.list.first_product_group_name | string | 真实调用返回字段 |
| data.list.second_product_group_id | integer | 真实调用返回字段 |
| data.list.second_product_group_name | string | 真实调用返回字段 |
| data.list.second_product_group_parent_id | integer | 真实调用返回字段 |
| data.list.second_product_group_parent_name | string | 真实调用返回字段 |
| data.list.third_product_group_id | null | 真实调用返回字段 |
| data.list.third_product_group_name | null | 真实调用返回字段 |
| data.list.effective_product_group_id | integer | 真实调用返回字段 |
| data.list.effective_product_group_level | integer | 真实调用返回字段 |
| data.list.service_type_code | string | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.slogan | string | 真实调用返回字段 |
| data.list.slug | string | 真实调用返回字段 |
| data.list.children_count | integer | 真实调用返回字段 |
| data.list.direct_product_count | integer | 真实调用返回字段 |
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
                "id": 12,
                "product_type": "type_iwjqnj",
                "product_type_id": 7,
                "product_type_label": "裸金属",
                "first_product_group_id": 4,
                "first_product_group_code": "type_iwjqnj",
                "first_product_group_name": "裸金属",
                "second_product_group_id": 12,
                "second_product_group_name": "裸金属",
                "second_product_group_parent_id": 4,
                "second_product_group_parent_name": "裸金属",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 12,
                "effective_product_group_level": 2,
                "service_type_code": "type_iwjqnj",
                "name": "裸金属",
                "slogan": "",
                "slug": "category-2",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 4
            },
            {
                "id": 14,
                "product_type": "dedicated",
                "product_type_id": 2,
                "product_type_label": "游戏云",
                "first_product_group_id": 2,
                "first_product_group_code": "dedicated",
                "first_product_group_name": "游戏云",
                "second_product_group_id": 14,
                "second_product_group_name": "Gold",
                "second_product_group_parent_id": 2,
                "second_product_group_parent_name": "游戏云",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 14,
                "effective_product_group_level": 2,
                "service_type_code": "dedicated",
                "name": "Gold",
                "slogan": "",
                "slug": "category-12",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 5
            },
            {
                "id": 15,
                "product_type": "dedicated",
                "product_type_id": 2,
                "product_type_label": "游戏云",
                "first_product_group_id": 2,
                "first_product_group_code": "dedicated",
                "first_product_group_name": "游戏云",
                "second_product_group_id": 15,
                "second_product_group_name": "Platinum",
                "second_product_group_parent_id": 2,
                "second_product_group_parent_name": "游戏云",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 15,
                "effective_product_group_level": 2,
                "service_type_code": "dedicated",
                "name": "Platinum",
                "slogan": "",
                "slug": "platinum",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 5
            },
            {
                "id": 18,
                "product_type": "type_tgynng",
                "product_type_id": 9,
                "product_type_label": "物理机",
                "first_product_group_id": 8,
                "first_product_group_code": "type_tgynng",
                "first_product_group_name": "物理机",
                "second_product_group_id": 18,
                "second_product_group_name": "西安",
                "second_product_group_parent_id": 8,
                "second_product_group_parent_name": "物理机",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 18,
                "effective_product_group_level": 2,
                "service_type_code": "type_tgynng",
                "name": "西安",
                "slogan": "",
                "slug": "group",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 0
            },
            {
                "id": 19,
                "product_type": "type_tgynng",
                "product_type_id": 9,
                "product_type_label": "物理机",
                "first_product_group_id": 8,
                "first_product_group_code": "type_tgynng",
                "first_product_group_name": "物理机",
                "second_product_group_id": 19,
                "second_product_group_name": "2",
                "second_product_group_parent_id": 8,
                "second_product_group_parent_name": "物理机",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 19,
                "effective_product_group_level": 2,
                "service_type_code": "type_tgynng",
                "name": "2",
                "slogan": "",
                "slug": "2",
                "children_count": 0,
                "direct_product_count": 0,
                "product_count": 0
            },
            {
                "id": 13,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 13,
                "second_product_group_name": "襄阳",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 13,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "襄阳",
                "slogan": "",
                "slug": "category-7",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 8
            },
            {
                "id": 1,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 1,
                "second_product_group_name": "美国",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 1,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "美国",
                "slogan": "",
                "slug": "group-1",
                "children_count": 4,
                "direct_product_count": 0,
                "product_count": 48
            },
            {
                "id": 2,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 2,
                "second_product_group_name": "香港",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 2,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "香港",
                "slogan": "",
                "slug": "group-2",
                "children_count": 2,
                "direct_product_count": 0,
                "product_count": 10
            },
            {
                "id": 10,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 10,
                "second_product_group_name": "内蒙古电信",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 10,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "内蒙古电信",
                "slogan": "",
                "slug": "group-25",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 6
            },
            {
                "id": 9,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 9,
                "second_product_group_name": "西安高防",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 9,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "西安高防",
                "slogan": "",
                "slug": "group-22",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 5
            },
            {
                "id": 8,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 8,
                "second_product_group_name": "轻量云",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 8,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "轻量云",
                "slogan": "",
                "slug": "group-20",
                "children_count": 3,
                "direct_product_count": 0,
                "product_count": 10
            },
            {
                "id": 7,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 7,
                "second_product_group_name": "十堰高宽",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 7,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "十堰高宽",
                "slogan": "",
                "slug": "group-18",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 5
            },
            {
                "id": 5,
                "product_type": "domain",
                "product_type_id": 4,
                "product_type_label": "云电脑",
                "first_product_group_id": 3,
                "first_product_group_code": "domain",
                "first_product_group_name": "云电脑",
                "second_product_group_id": 5,
                "second_product_group_name": "云电脑",
                "second_product_group_parent_id": 3,
                "second_product_group_parent_name": "云电脑",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 5,
                "effective_product_group_level": 2,
                "service_type_code": "domain",
                "name": "云电脑",
                "slogan": "",
                "slug": "group-15",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 4
            }
        ]
    },
    "timestamp": 1783240541
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:41  
· 响应状态码：200  
· 调用方式：GET /api/site/product-categories  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteProductController@productGroups`  
· 请求校验：`App\Http\Requests\Site\ProductGroupsRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api`
