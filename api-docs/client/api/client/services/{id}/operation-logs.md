# operation-logs

**请求方法**：GET  
**请求路径**：`/api/client/services/{id}/operation-logs`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListServiceOperationLogsRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:50；来源：ListServiceOperationLogsRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：ListServiceOperationLogsRequest |
| category | string | 否 | 查询参数；校验规则：nullable\|in:power,password,reinstall,renew,nat_forwarding,security_group,security_rule,service；来源：ListServiceOperationLogsRequest |

### 请求示例（完整 JSON）
```json
{
    "page": 1,
    "page_size": 1,
    "keyword": "string",
    "category": "power"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.list | array | 分页列表数据 |
| data.summary | object | 真实调用返回字段 |
| data.summary.total | integer | 真实调用返回字段 |
| data.summary.today_total | integer | 真实调用返回字段 |
| data.summary.latest_created_at | null | 真实调用返回字段 |
| data.summary.service_name | string | 真实调用返回字段 |
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
        "list": [],
        "summary": {
            "total": 0,
            "today_total": 0,
            "latest_created_at": null,
            "service_name": "美国1区精品网 2H2G"
        },
        "total": 0,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240533
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:33  
· 响应状态码：200  
· 调用方式：GET /api/client/services/{id}/operation-logs  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@operationLogs`  
· 请求校验：`App\Http\Requests\Client\Service\ListServiceOperationLogsRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
