# base

**请求方法**：GET  
**请求路径**：`/api/admin/users/{user}/services/{serviceId}/base`  
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
| user | integer|string | 是 | 路径参数；用户 ID，必须与服务归属匹配 |
| serviceId | integer|string | 是 | 路径参数；服务 ID，必须属于路径中的用户 |

### 请求示例（完整 JSON）
```json
{
    "user": 1,
    "serviceId": 1
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.id | integer | 服务 ID |
| data.name | string | 服务名称 |
| data.domain | string | 服务域名或实例标识 |
| data.status | integer|string | 服务状态 |
| data.product | object | 商品摘要 |
| data.connection | object|null | 基础连接信息；敏感字段按权限脱敏 |
| data.meta | object | 服务元数据 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 1,
        "name": "云服务器",
        "domain": "server.example.com",
        "status": 1,
        "product": {
            "id": 1,
            "name": "云服务器"
        },
        "connection": {},
        "meta": {}
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:19  
· 响应状态码：404  
· 调用方式：GET /api/admin/users/{user}/services/{serviceId}/base  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是样例 `serviceId` 不属于路径中的 `user`，源码查询会按 `user_id` 限定服务归属。

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\UserController@serviceBaseDetail`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.detail`
