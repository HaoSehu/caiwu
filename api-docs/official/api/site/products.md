# products

**请求方法**：GET  
**请求路径**：`/api/site/products`  
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
| effective_product_group_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：IndexProductRequest |
| effective_product_group_ids | array | 否 | 查询参数；校验规则：nullable\|array\|min:1；来源：IndexProductRequest |
| effective_product_group_ids.* | integer | 否 | 查询参数；校验规则：integer\|min:1；来源：IndexProductRequest |
| second_product_group_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：IndexProductRequest |
| second_product_group_ids | array | 否 | 查询参数；校验规则：nullable\|array\|min:1；来源：IndexProductRequest |
| second_product_group_ids.* | integer | 否 | 查询参数；校验规则：integer\|min:1；来源：IndexProductRequest |
| third_product_group_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：IndexProductRequest |
| third_product_group_ids | array | 否 | 查询参数；校验规则：nullable\|array\|min:1；来源：IndexProductRequest |
| third_product_group_ids.* | integer | 否 | 查询参数；校验规则：integer\|min:1；来源：IndexProductRequest |

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
| data.items_by_group | array | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "items_by_group": []
    },
    "timestamp": 1783240543
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:43  
· 响应状态码：200  
· 调用方式：GET /api/site/products  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteProductController@index`  
· 请求校验：`App\Http\Requests\Site\IndexProductRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api`
