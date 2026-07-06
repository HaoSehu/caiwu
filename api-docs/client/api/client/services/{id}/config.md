# config

**请求方法**：GET  
**请求路径**：`/api/client/services/{id}/config`  
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
| data.id | integer | 真实调用返回字段 |
| data.name | string | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.status_label | string | 真实调用返回字段 |
| data.product_type | string | 真实调用返回字段 |
| data.product_type_label | string | 真实调用返回字段 |
| data.machine_category | object | 真实调用返回字段 |
| data.machine_category.key | string | 真实调用返回字段 |
| data.machine_category.label | string | 真实调用返回字段 |
| data.console_mode | string | 真实调用返回字段 |
| data.is_nat_console | boolean | 真实调用返回字段 |
| data.expires_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 88,
        "name": "美国1区精品网 2H2G",
        "status": 4,
        "status_label": "已取消",
        "product_type": "vps",
        "product_type_label": "云服务器",
        "machine_category": {
            "key": "cloud_server",
            "label": "云服务器"
        },
        "console_mode": "default",
        "is_nat_console": false,
        "expires_at": "2026-04-19 13:30:03"
    },
    "timestamp": 1783240532
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:32  
· 响应状态码：200  
· 调用方式：GET /api/client/services/{id}/config  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@config`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
