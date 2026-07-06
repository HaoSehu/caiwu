# articles

**请求方法**：GET  
**请求路径**：`/api/admin/content/articles`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListContentArticlesRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：ListContentArticlesRequest |
| content_type | string | 否 | 查询参数；校验规则：nullable\|in:"notice","help"；来源：ListContentArticlesRequest |
| type | string | 否 | 查询参数；校验规则：nullable\|in:"notice","help"；来源：ListContentArticlesRequest |
| status | integer | 否 | 查询参数；校验规则：nullable\|integer\|in:"0","1","2"；来源：ListContentArticlesRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：ListContentArticlesRequest |
| category_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|exists:content_categories,id；来源：ListContentArticlesRequest |
| content_category_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|exists:content_categories,id；来源：ListContentArticlesRequest |
| is_pinned | integer | 否 | 查询参数；校验规则：nullable\|integer\|in:"0","1"；来源：ListContentArticlesRequest |

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
| data.list.summary | string | 真实调用返回字段 |
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
| data.list.cover_image | string | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.is_pinned | integer | 真实调用返回字段 |
| data.list.is_recommended | integer | 真实调用返回字段 |
| data.list.sort_order | integer | 真实调用返回字段 |
| data.list.view_count | integer | 真实调用返回字段 |
| data.list.publish_at | string | 真实调用返回字段 |
| data.list.last_published_at | string | 真实调用返回字段 |
| data.list.operator | string | 真实调用返回字段 |
| data.list.remark | string | 真实调用返回字段 |
| data.list.trace_id | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.list.creator | null | 真实调用返回字段 |
| data.list.updater | object | 真实调用返回字段 |
| data.list.updater.id | integer | 真实调用返回字段 |
| data.list.updater.username | string | 真实调用返回字段 |
| data.list.updater.nickname | string | 真实调用返回字段 |
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
        "list": [
            {
                "id": 15,
                "content_type": "notice",
                "type": "notice",
                "type_label": "公告",
                "category_id": 8,
                "content_category_id": 8,
                "title": "创欧云计算 · 国庆狂欢盛典",
                "slug": "notice-14",
                "summary": "创欧云计算-国庆节活动? 活动时间：2025.10.1 – 2025.10.3 ️⃣ 充值活动 用户充值 100元以上 返现 10% 用户预存 500元 即可升级为 铂金代理（海外、国内产品 8 折 购入/续费，不包括轻量云和活动机型） ️⃣ 新购折扣 优惠码：国庆节快乐 8折 购入，续费同 8 折 注：不包括轻量云和活动机型 （活动最终解释权归创欧云计算所有）",
                "excerpt": "创欧云计算-国庆节活动? 活动时间：2025.10.1 – 2025.10.3 ️⃣ 充值活动 用户充值 100元以上 返现 10% 用户预存 500元 即可升级为 铂金代理（海外、国内产品 8 折 购入/续费，不包括轻量云和活动机型） ️⃣ 新购折扣 优惠码：国庆节快乐 8折 购入，续费同 8 折 注：不包括轻量云和活动机型 （活动最终解释权归创欧云计算所有）",
                "category_name": "官方通知",
                "category": "官方通知",
                "category_slug": "notice-6",
                "category_description": null,
                "category_detail": {
                    "id": 8,
                    "name": "官方通知",
                    "slug": "notice-6",
                    "description": null,
                    "status": 1,
                    "sort_order": 0
                },
                "keywords": null,
                "cover_image": "https://www.coyjs.cn/uploads/content/20260624/img_020935_3650.jpg",
                "status": 1,
                "status_label": "已发布",
                "is_pinned": 1,
                "is_recommended": 0,
                "sort_order": 0,
                "view_count": 529,
                "publish_at": "2025-10-01 11:47:41",
                "last_published_at": "2025-10-01 11:47:41",
                "operator": "管理员",
                "remark": "魔方公告/帮助迁移",
                "trace_id": "70c1aff3-25b7-4b95-83be-72903f182742",
                "created_at": "2025-10-01 11:49:28",
                "updated_at": "2026-06-28 21:50:43",
                "creator": null,
                "updater": {
                    "id": 1,
                    "username": "cerbo",
                    "nickname": "管理员"
                }
            }
        ],
        "total": 12,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240483
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:44  
· 响应状态码：200  
· 调用方式：GET /api/admin/content/articles  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ContentArticleController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:content.list`
