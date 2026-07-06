# help-articles

**请求方法**：GET  
**请求路径**：`/api/site/help-articles`  
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
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：PublishedListContentRequest |
| category_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|exists:content_categories,id；来源：PublishedListContentRequest |
| content_category_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|exists:content_categories,id；来源：PublishedListContentRequest |
| is_recommended | integer | 否 | 查询参数；校验规则：nullable\|integer\|in:"0","1"；来源：PublishedListContentRequest |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：PublishedListContentRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:50；来源：PublishedListContentRequest |

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
| data.list.content_type | string | 真实调用返回字段 |
| data.list.type | string | 真实调用返回字段 |
| data.list.type_label | string | 真实调用返回字段 |
| data.list.category_id | integer | 真实调用返回字段 |
| data.list.content_category_id | integer | 真实调用返回字段 |
| data.list.title | string | 真实调用返回字段 |
| data.list.slug | string | 真实调用返回字段 |
| data.list.summary | null | 真实调用返回字段 |
| data.list.excerpt | string | 真实调用返回字段 |
| data.list.category_name | string | 真实调用返回字段 |
| data.list.category | string | 真实调用返回字段 |
| data.list.category_slug | string | 真实调用返回字段 |
| data.list.category_description | null | 真实调用返回字段 |
| data.list.category_detail | object | 真实调用返回字段 |
| data.list.category_detail.id | integer | 真实调用返回字段 |
| data.list.category_detail.name | string | 真实调用返回字段 |
| data.list.category_detail.slug | string | 真实调用返回字段 |
| data.list.category_detail.description | null | 真实调用返回字段 |
| data.list.category_detail.status | integer | 真实调用返回字段 |
| data.list.category_detail.sort_order | integer | 真实调用返回字段 |
| data.list.keywords | null | 真实调用返回字段 |
| data.list.cover_image | null | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.is_pinned | integer | 真实调用返回字段 |
| data.list.is_recommended | integer | 真实调用返回字段 |
| data.list.sort_order | integer | 真实调用返回字段 |
| data.list.view_count | integer | 真实调用返回字段 |
| data.list.publish_at | string | 真实调用返回字段 |
| data.list.last_published_at | string | 真实调用返回字段 |
| data.list.operator | string | 真实调用返回字段 |
| data.list.remark | null | 真实调用返回字段 |
| data.list.trace_id | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.list.creator | null | 真实调用返回字段 |
| data.list.updater | null | 真实调用返回字段 |
| data.total | integer | 总条数 |
| data.page | integer | 当前页码 |
| data.page_size | integer | 每页数量 |
| data.categories | array | 真实调用返回字段 |
| data.categories.id | integer | 真实调用返回字段 |
| data.categories.content_type | string | 真实调用返回字段 |
| data.categories.type | string | 真实调用返回字段 |
| data.categories.name | string | 真实调用返回字段 |
| data.categories.slug | string | 真实调用返回字段 |
| data.categories.description | null | 真实调用返回字段 |
| data.categories.status | integer | 真实调用返回字段 |
| data.categories.sort_order | integer | 真实调用返回字段 |
| data.categories.articles_count | integer | 真实调用返回字段 |
| data.categories.created_at | string | 真实调用返回字段 |
| data.categories.updated_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "id": 22,
                "content_type": "help",
                "type": "help",
                "type_label": "帮助",
                "category_id": 6,
                "content_category_id": 6,
                "title": "Linux 带宽测速脚本",
                "slug": "linux",
                "summary": null,
                "excerpt": "Linux带宽测速脚本 http://speedcs.cn 致力打造全网最好用的Linux带宽测速脚本 使用命令 curl -O http://speedcs.cn/speedtest &...",
                "category_name": "帮助",
                "category": "帮助",
                "category_slug": "help-5",
                "category_description": null,
                "category_detail": {
                    "id": 6,
                    "name": "帮助",
                    "slug": "help-5",
                    "description": null,
                    "status": 1,
                    "sort_order": 0
                },
                "keywords": null,
                "cover_image": null,
                "status": 1,
                "status_label": "已发布",
                "is_pinned": 0,
                "is_recommended": 0,
                "sort_order": 0,
                "view_count": 15,
                "publish_at": "2026-05-31 10:29:11",
                "last_published_at": "2026-05-31 10:29:11",
                "operator": "admin#1",
                "remark": null,
                "trace_id": "bb4da84c-13d4-4139-a2d3-da5d3c533a8d",
                "created_at": "2026-05-31 10:29:11",
                "updated_at": "2026-06-26 21:20:33",
                "creator": null,
                "updater": null
            }
        ],
        "total": 4,
        "page": 1,
        "page_size": 1,
        "categories": [
            {
                "id": 5,
                "content_type": "help",
                "type": "help",
                "name": "介绍",
                "slug": "help-3",
                "description": null,
                "status": 1,
                "sort_order": 0,
                "articles_count": 2,
                "created_at": "2026-03-25 17:50:49",
                "updated_at": "2026-03-25 17:50:49"
            },
            {
                "id": 6,
                "content_type": "help",
                "type": "help",
                "name": "帮助",
                "slug": "help-5",
                "description": null,
                "status": 1,
                "sort_order": 0,
                "articles_count": 2,
                "created_at": "2026-03-25 17:50:49",
                "updated_at": "2026-03-25 17:50:49"
            }
        ]
    },
    "timestamp": 1783240541
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:41  
· 响应状态码：200  
· 调用方式：GET /api/site/help-articles  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteContentController@helpArticles`  
· 请求校验：`App\Http\Requests\Site\PublishedListContentRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api`
