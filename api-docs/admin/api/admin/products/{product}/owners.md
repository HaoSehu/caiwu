# owners

**请求方法**：GET  
**请求路径**：`/api/admin/products/{product}/owners`  
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
| product | integer\|string | 是 | 路径参数；来自路由占位 `{product}` |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListProductOwnersRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：ListProductOwnersRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：ListProductOwnersRequest |

### 请求示例（完整 JSON）
```json
{
    "page": 1,
    "page_size": 1,
    "keyword": "string"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.list | array | 分页列表数据 |
| data.summary | object | 真实调用返回字段 |
| data.summary.owners_total | integer | 真实调用返回字段 |
| data.summary.services_total | integer | 真实调用返回字段 |
| data.summary.active_services_total | integer | 真实调用返回字段 |
| data.summary.latest_service_created_at | string | 真实调用返回字段 |
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
        "list": [],
        "summary": {
            "owners_total": 7,
            "services_total": 8,
            "active_services_total": 4,
            "latest_service_created_at": "2026-06-13 21:36:49"
        },
        "total": 0,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240514
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:14  
· 响应状态码：200  
· 调用方式：GET /api/admin/products/{product}/owners  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ProductController@owners`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.list`
