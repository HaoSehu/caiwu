# media-files

**请求方法**：GET  
**请求路径**：`/api/admin/media-files`  
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
| group | string | 否 | 查询参数；控制器通过 `$request->query()` 读取；未发现 FormRequest 明确规则 |
| keyword | string | 否 | 查询参数；控制器通过 `$request->query()` 读取；未发现 FormRequest 明确规则 |
| page_size | integer | 否 | 查询参数；控制器通过 `$request->query()` 读取；未发现 FormRequest 明确规则 |

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
| data.list.filename | string | 真实调用返回字段 |
| data.list.path | string | 真实调用返回字段 |
| data.list.url | string | 真实调用返回字段 |
| data.list.mime_type | string | 真实调用返回字段 |
| data.list.size | integer | 真实调用返回字段 |
| data.list.width | null | 真实调用返回字段 |
| data.list.height | null | 真实调用返回字段 |
| data.list.group | string | 真实调用返回字段 |
| data.list.type | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
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
                "id": 22,
                "filename": "logo_w.svg",
                "path": "/media/logo_w.svg",
                "url": "http://127.0.0.1:5175/media/logo_w.svg",
                "mime_type": "image/svg+xml",
                "size": 86543,
                "width": null,
                "height": null,
                "group": "content",
                "type": "image",
                "created_at": "2026-07-01 17:26:23"
            }
        ],
        "total": 16,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240510
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:10  
· 响应状态码：200  
· 调用方式：GET /api/admin/media-files  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\MediaFileController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:content.list`
