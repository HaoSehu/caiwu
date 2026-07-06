# suppliers

**请求方法**：POST  
**请求路径**：`/api/admin/suppliers`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| name | string | 是 | 请求体参数；校验规则：required\|string\|max:120；来源：UpsertRequest |
| provider_key | string | 是 | 请求体参数；校验规则：required\|in:"kanghostx","mofang_finance_api"；来源：UpsertRequest |
| api_url | string | 否 | 请求体参数；校验规则：nullable\|url:http,https\|max:255\|closure:自定义校验；来源：UpsertRequest |
| api_username | string | 否 | 请求体参数；校验规则：nullable\|string\|max:100；来源：UpsertRequest |
| api_key | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpsertRequest |
| upstream_binding | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpsertRequest |
| upstream_binding.provider_key | string | 否 | 请求体参数；校验规则：nullable\|in:"kanghostx","mofang_finance_api"；来源：UpsertRequest |
| upstream_binding.base_url | string | 否 | 请求体参数；校验规则：nullable\|url:http,https\|max:255\|closure:自定义校验；来源：UpsertRequest |
| upstream_binding.account_name | string | 否 | 请求体参数；校验规则：nullable\|string\|max:100；来源：UpsertRequest |
| provider_config | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpsertRequest |
| contact_name | string | 否 | 请求体参数；校验规则：nullable\|string\|max:60；来源：UpsertRequest |
| contact_phone | string | 否 | 请求体参数；校验规则：nullable\|string\|max:30；来源：UpsertRequest |
| contact_email | string(email) | 否 | 请求体参数；校验规则：nullable\|email\|max:100；来源：UpsertRequest |
| website | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpsertRequest |
| status | string | 否 | 请求体参数；校验规则：nullable\|in:0,1；来源：UpsertRequest |
| sort_order | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:0\|max:999999；来源：UpsertRequest |
| notes | string | 否 | 请求体参数；校验规则：nullable\|string\|max:4000；来源：UpsertRequest |

### 请求示例（完整 JSON）
```json
{
    "name": "string",
    "provider_key": "\"kanghostx\"",
    "api_url": "string",
    "api_username": "string",
    "api_key": "string",
    "upstream_binding": [],
    "upstream_binding.provider_key": "\"kanghostx\"",
    "upstream_binding.base_url": "string",
    "upstream_binding.account_name": "string",
    "provider_config": [],
    "contact_name": "string",
    "contact_phone": "13800138000",
    "contact_email": "user@example.com",
    "website": "string",
    "status": "1",
    "sort_order": 1,
    "notes": "string"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.id | integer | 业务字段；由源码静态提取 |
| data.name | string | 业务字段；由源码静态提取 |
| data.code | string | 业务字段；由源码静态提取 |
| data.provider_key | string | 业务字段；由源码静态提取 |
| data.provider_label | string | 业务字段；由源码静态提取 |
| data.api_url | string | 业务字段；由源码静态提取 |
| data.has_api_url | string | 业务字段；由源码静态提取 |
| data.api_username | string | 业务字段；由源码静态提取 |
| data.has_api_key | string | 业务字段；由源码静态提取 |
| data.has_provider_secret_values | array | 业务字段；由源码静态提取 |
| data.provider_config | string | 业务字段；由源码静态提取 |
| data.upstream_binding | string | 业务字段；由源码静态提取 |
| data.contact_name | string | 业务字段；由源码静态提取 |
| data.contact_phone | string | 业务字段；由源码静态提取 |
| data.contact_email | string | 业务字段；由源码静态提取 |
| data.website | string | 业务字段；由源码静态提取 |
| data.status | integer | 业务字段；由源码静态提取 |
| data.sort_order | string | 业务字段；由源码静态提取 |
| data.notes | array | 业务字段；由源码静态提取 |
| data.created_at | string(datetime) | 业务字段；由源码静态提取 |
| data.updated_at | string(datetime) | 业务字段；由源码静态提取 |
| data.card | string | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "创建成功",
    "data": {
        "id": 1,
        "name": "string",
        "code": "string",
        "provider_key": "string",
        "provider_label": "string",
        "api_url": "string",
        "has_api_url": "string",
        "api_username": "string",
        "has_api_key": "string",
        "has_provider_secret_values": [],
        "provider_config": "string",
        "upstream_binding": "string",
        "contact_name": "string",
        "contact_phone": "string",
        "contact_email": "string",
        "website": "string",
        "status": [],
        "sort_order": "string",
        "notes": [],
        "created_at": "2026-07-05 12:00:00",
        "updated_at": "2026-07-05 12:00:00",
        "card": "string"
    },
    "timestamp": 1760000000
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：接口为写操作、删除操作、支付/退款/开通/服务控制/通知发送/上游动作之一，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\SupplierController@store`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:supplier.manage`
