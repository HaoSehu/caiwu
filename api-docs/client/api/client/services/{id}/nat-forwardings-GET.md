# nat-forwardings

**请求方法**：GET  
**请求路径**：`/api/client/services/{id}/nat-forwardings`  
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
| id | integer\|string | 是 | 路径参数；来自路由占位 `{id}` |

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
| data.supported | boolean | 真实调用返回字段 |
| data.message | string | 真实调用返回字段 |
| data.error | string | 真实调用返回字段 |
| data.module_key | string | 真实调用返回字段 |
| data.module_name | string | 真实调用返回字段 |
| data.endpoint | string | 真实调用返回字段 |
| data.can_create | boolean | 真实调用返回字段 |
| data.protocols | array | 真实调用返回字段 |
| data.list | array | 分页列表数据 |
| data.summary | object | 真实调用返回字段 |
| data.summary.total | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "supported": true,
        "message": "",
        "error": "读取 NAT 转发失败，请稍后重试",
        "module_key": "nat_acl",
        "module_name": "NAT 转发",
        "endpoint": "",
        "can_create": false,
        "protocols": [],
        "list": [],
        "summary": {
            "total": 0
        }
    },
    "timestamp": 1783240533
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:33  
· 响应状态码：200  
· 调用方式：GET /api/client/services/{id}/nat-forwardings  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@natForwardings`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
