# {articleId}

**请求方法**：GET  
**请求路径**：`/api/site/notices/{articleId}`  
**调试状态**：⚠️ 异常

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| articleId | integer|string | 是 | 路径参数；已发布公告文章 ID |

### 请求示例（完整 JSON）
```json
{
    "articleId": 1
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.id | integer | 文章 ID |
| data.content_type | string | 内容类型：notice/help |
| data.type | string | 兼容内容类型字段 |
| data.category_id | integer|null | 分类 ID |
| data.title | string | 标题 |
| data.slug | string | 文章标识 |
| data.summary | string|null | 摘要 |
| data.content | string | 正文内容 |
| data.cover_image | string|null | 封面图 |
| data.status | integer | 发布状态 |
| data.published_at | string|null | 发布时间 |
| data.created_at | string|null | 创建时间 |
| data.updated_at | string|null | 更新时间 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 1,
        "content_type": "notice",
        "type": "notice",
        "category_id": 1,
        "title": "系统公告",
        "slug": "notice-1",
        "summary": "公告摘要",
        "content": "<p>公告内容</p>",
        "cover_image": null,
        "status": 1,
        "published_at": "2026-07-05 16:00:00",
        "created_at": "2026-07-05 16:00:00",
        "updated_at": "2026-07-05 16:00:00"
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:41  
· 响应状态码：422  
· 调用方式：GET /api/site/notices/{articleId}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是样例文章不是已发布公告；源码只返回已发布的 notice 文章。

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteContentController@noticeDetail`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api`
