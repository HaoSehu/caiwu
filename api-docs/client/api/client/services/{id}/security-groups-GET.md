# security-groups

**请求方法**：GET  
**请求路径**：`/api/client/services/{id}/security-groups`  
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
| fresh | boolean | 否 | 查询参数；控制器通过 `booleanQuery()` 读取；未发现 FormRequest 明确规则 |

### 请求示例（完整 JSON）
```json
{
    "fresh": true
}
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
| data.host_type | string | 真实调用返回字段 |
| data.directions | array | 真实调用返回字段 |
| data.protocols | array | 真实调用返回字段 |
| data.groups | array | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "supported": false,
        "message": "",
        "error": "读取安全组失败，请稍后重试",
        "module_key": "",
        "module_name": "",
        "host_type": "",
        "directions": [],
        "protocols": [],
        "groups": []
    },
    "timestamp": 1783240534
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:34  
· 响应状态码：200  
· 调用方式：GET /api/client/services/{id}/security-groups  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@securityGroups`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
