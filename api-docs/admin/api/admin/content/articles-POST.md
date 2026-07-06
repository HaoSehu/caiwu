# articles

**请求方法**：POST  
**请求路径**：`/api/admin/content/articles`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |
| X-Request-Id | {trace_id} | 否 | 请求追踪 ID；控制器读取该请求头 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| content_type | string | 是 | 请求体参数；校验规则：required_without:type\|in:"notice","help"；来源：StoreContentArticleRequest |
| type | string | 是 | 请求体参数；校验规则：required_without:content_type\|in:"notice","help"；来源：StoreContentArticleRequest |
| category_id | integer | 是 | 请求体参数；校验规则：required_without:content_category_id\|integer\|exists:content_categories,id；来源：StoreContentArticleRequest |
| content_category_id | integer | 是 | 请求体参数；校验规则：required_without:category_id\|integer\|exists:content_categories,id；来源：StoreContentArticleRequest |
| title | string | 是 | 请求体参数；校验规则：required\|string\|max:200；来源：StoreContentArticleRequest |
| slug | string | 否 | 请求体参数；校验规则：nullable\|string\|max:220\|unique:content_articles,slug,NULL,id；来源：StoreContentArticleRequest |
| summary | string | 否 | 请求体参数；校验规则：nullable\|string\|max:500；来源：StoreContentArticleRequest |
| content | string | 是 | 请求体参数；校验规则：required\|string；来源：StoreContentArticleRequest |
| keywords | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：StoreContentArticleRequest |
| status | integer | 否 | 请求体参数；校验规则：nullable\|integer\|in:"0","1","2"；来源：StoreContentArticleRequest |
| is_pinned | integer | 否 | 请求体参数；校验规则：nullable\|integer\|in:"0","1"；来源：StoreContentArticleRequest |
| cover_image | string | 是 | 请求体参数；校验规则：nullable\|required_if:is_pinned,1\|string\|max:500；来源：StoreContentArticleRequest |
| is_recommended | integer | 否 | 请求体参数；校验规则：nullable\|integer\|in:"0","1"；来源：StoreContentArticleRequest |
| sort_order | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:0\|max:999999；来源：StoreContentArticleRequest |
| publish_at | string(datetime) | 否 | 请求体参数；校验规则：nullable\|date；来源：StoreContentArticleRequest |
| remark | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：StoreContentArticleRequest |
| operator | string | 否 | 请求体参数；校验规则：nullable\|string\|max:50；来源：StoreContentArticleRequest |

### 请求示例（完整 JSON）
```json
{
    "content_type": "\"notice\"",
    "type": "\"notice\"",
    "category_id": 1,
    "content_category_id": 1,
    "title": "string",
    "slug": "string",
    "summary": "string",
    "content": "string",
    "keywords": "string",
    "status": "\"0\"",
    "is_pinned": "\"0\"",
    "cover_image": "string",
    "is_recommended": "\"0\"",
    "sort_order": 1,
    "publish_at": "2026-07-05",
    "remark": "string",
    "operator": "string"
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
| data.content_type | string | 业务字段；由源码静态提取 |
| data.type | string | 业务字段；由源码静态提取 |
| data.type_label | string | 业务字段；由源码静态提取 |
| data.category_id | integer | 业务字段；由源码静态提取 |
| data.content_category_id | integer | 业务字段；由源码静态提取 |
| data.title | string | 业务字段；由源码静态提取 |
| data.slug | string | 业务字段；由源码静态提取 |
| data.summary | string | 业务字段；由源码静态提取 |
| data.excerpt | string | 业务字段；由源码静态提取 |
| data.content | string | 业务字段；由源码静态提取 |
| data.category_name | string | 业务字段；由源码静态提取 |
| data.category | string | 业务字段；由源码静态提取 |
| data.category_slug | string | 业务字段；由源码静态提取 |
| data.category_description | string | 业务字段；由源码静态提取 |
| data.category_detail | string | 业务字段；由源码静态提取 |
| data.keywords | array | 业务字段；由源码静态提取 |
| data.cover_image | string | 业务字段；由源码静态提取 |
| data.status | integer | 业务字段；由源码静态提取 |
| data.status_label | string | 业务字段；由源码静态提取 |
| data.is_pinned | boolean | 业务字段；由源码静态提取 |
| data.is_recommended | boolean | 业务字段；由源码静态提取 |
| data.sort_order | string | 业务字段；由源码静态提取 |
| data.view_count | string | 业务字段；由源码静态提取 |
| data.publish_at | string(datetime) | 业务字段；由源码静态提取 |
| data.last_published_at | string(datetime) | 业务字段；由源码静态提取 |
| data.operator | string | 业务字段；由源码静态提取 |
| data.remark | string | 业务字段；由源码静态提取 |
| data.trace_id | integer | 业务字段；由源码静态提取 |
| data.created_at | string(datetime) | 业务字段；由源码静态提取 |
| data.updated_at | string(datetime) | 业务字段；由源码静态提取 |
| data.creator | string | 业务字段；由源码静态提取 |
| data.updater | string | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "内容创建成功",
    "data": {
        "id": 1,
        "content_type": "string",
        "type": "string",
        "type_label": "string",
        "category_id": 1,
        "content_category_id": 1,
        "title": "string",
        "slug": "string",
        "summary": "string",
        "excerpt": "string",
        "content": "string",
        "category_name": "string",
        "category": "string",
        "category_slug": "string",
        "category_description": "string",
        "category_detail": "string",
        "keywords": [],
        "cover_image": "string",
        "status": [],
        "status_label": "string",
        "is_pinned": true,
        "is_recommended": true,
        "sort_order": "string",
        "view_count": "string",
        "publish_at": "2026-07-05 12:00:00",
        "last_published_at": "2026-07-05 12:00:00",
        "operator": "string",
        "remark": "string",
        "trace_id": 1,
        "created_at": "2026-07-05 12:00:00",
        "updated_at": "2026-07-05 12:00:00",
        "creator": "string",
        "updater": "string"
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
· 控制器动作：`App\Http\Controllers\Admin\ContentArticleController@store`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:content.manage`
