# 用户短信日志

**请求方法**：GET  
**请求路径**：`/api/admin/users/{user}/sms-logs`  
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
| user | integer\|string | 是 | 路径参数；来自路由占位 `{user}` |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：UserLogPaginationRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：UserLogPaginationRequest |

### 请求示例（完整 JSON）
```json
{
    "page": 1,
    "page_size": 1
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.list | array | 分页列表数据 |
| data.list.id | integer | 真实调用返回字段 |
| data.list.phone | string | 真实调用返回字段 |
| data.list.template_code | string | 真实调用返回字段 |
| data.list.content | string | 真实调用返回字段 |
| data.list.params_json | object | 真实调用返回字段 |
| data.list.params_json.min | string | 真实调用返回字段 |
| data.list.params_json.code | string | 真实调用返回字段 |
| data.list.status | string | 真实调用返回字段 |
| data.list.provider | string | 真实调用返回字段 |
| data.list.request_id | null | 真实调用返回字段 |
| data.list.error_msg | null | 真实调用返回字段 |
| data.list.sent_at | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.list.origin_type | string | 真实调用返回字段 |
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
                "id": 960,
                "phone": "192****8808",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "params_json": {
                    "min": "5",
                    "code": "***"
                },
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-01T09:12:08.000000Z",
                "created_at": "2026-06-01T09:12:07.000000Z",
                "updated_at": "2026-06-01T09:12:08.000000Z",
                "origin_type": "sms_verify"
            }
        ],
        "total": 2,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240520
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:20  
· 响应状态码：200  
· 调用方式：GET /api/admin/users/{user}/sms-logs  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\UserController@smsLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.detail`
