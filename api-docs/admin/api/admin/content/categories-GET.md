# categories

**请求方法**：GET  
**请求路径**：`/api/admin/content/categories`  
**调试状态**：⚠️ 异常

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| content_type | string | 是 | 查询参数；`content_type` 与 `type` 至少传一个；可选值：notice、help；来源：ListContentCategoriesRequest |
| type | string | 否 | 查询参数；兼容字段；未传 content_type 时必填；可选值：notice、help |

### 请求示例（完整 JSON）
```json
{
    "content_type": "notice"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data | array | 内容分类列表 |
| data[].id | integer | 分类 ID |
| data[].content_type | string | 内容类型：notice/help |
| data[].type | string | 兼容内容类型字段 |
| data[].name | string | 分类名称 |
| data[].slug | string | 分类标识 |
| data[].description | string|null | 分类描述 |
| data[].status | integer | 状态 |
| data[].sort_order | integer | 排序值 |
| data[].articles_count | integer|null | 文章数量 |
| data[].created_at | string|null | 创建时间 |
| data[].updated_at | string|null | 更新时间 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": [
        {
            "id": 1,
            "content_type": "notice",
            "type": "notice",
            "name": "系统公告",
            "slug": "system-notice",
            "description": "系统公告分类",
            "status": 1,
            "sort_order": 0,
            "articles_count": 0,
            "created_at": "2026-07-05 16:00:00",
            "updated_at": "2026-07-05 16:00:00"
        }
    ],
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:44  
· 响应状态码：422  
· 调用方式：GET /api/admin/content/categories  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是未携带 `content_type`/`type` 查询参数；源码要求二者至少传一个。

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ContentCategoryController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:content.list`
