# children

**请求方法**：GET  
**请求路径**：`/api/site/product-categories/{groupId}/children`  
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
| groupId | integer\|string | 是 | 路径参数；来自路由占位 `{groupId}` |

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
| data.list.parent_id | integer | 真实调用返回字段 |
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
| data.list.third_product_group_id | integer | 真实调用返回字段 |
| data.list.third_product_group_name | string | 真实调用返回字段 |
| data.list.effective_product_group_id | integer | 真实调用返回字段 |
| data.list.effective_product_group_level | integer | 真实调用返回字段 |
| data.list.service_type_code | string | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.slogan | string | 真实调用返回字段 |
| data.list.slug | string | 真实调用返回字段 |
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
                "id": 3,
                "parent_id": 1,
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
                "third_product_group_id": 3,
                "third_product_group_name": "三网精品",
                "effective_product_group_id": 3,
                "effective_product_group_level": 3,
                "service_type_code": "vps",
                "name": "三网精品",
                "slogan": "CN2+CMIN2+9929三网精品，30G DDOS防御 黑洞10分钟 测试IP 156.238.224.1（kurun机房） CPU:E5 2696V4*2/2698/2699V4*2",
                "slug": "group-4",
                "product_count": 5
            },
            {
                "id": 5,
                "parent_id": 1,
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
                "third_product_group_id": 5,
                "third_product_group_name": "高性能",
                "effective_product_group_id": 5,
                "effective_product_group_level": 3,
                "service_type_code": "vps",
                "name": "高性能",
                "slogan": "三网去程CN2+CMIN2+4837 三网CN2+CMIN2+9929精品回国，10G DDOS防御 黑洞10分钟 测试IP 154.64.232.1 CPU:EPYC7532区域不支持win",
                "slug": "group-6",
                "product_count": 5
            },
            {
                "id": 19,
                "parent_id": 1,
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
                "third_product_group_id": 19,
                "third_product_group_name": "家宽",
                "effective_product_group_id": 19,
                "effective_product_group_level": 3,
                "service_type_code": "vps",
                "name": "家宽",
                "slogan": "",
                "slug": "category-10",
                "product_count": 8
            },
            {
                "id": 20,
                "parent_id": 1,
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
                "third_product_group_id": 20,
                "third_product_group_name": "高宽",
                "effective_product_group_id": 20,
                "effective_product_group_level": 3,
                "service_type_code": "vps",
                "name": "高宽",
                "slogan": "200G防御 AMD EPYC处理器 测试ip：154.29.148.0/24",
                "slug": "category-11",
                "product_count": 30
            }
        ]
    },
    "timestamp": 1783240542
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:42  
· 响应状态码：200  
· 调用方式：GET /api/site/product-categories/{groupId}/children  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteProductController@childGroups`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api`
