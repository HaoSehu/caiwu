# summary

**请求方法**：GET  
**请求路径**：`/api/admin/logs/tasks/summary`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：GeneralLogListRequest |
| per_page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：GeneralLogListRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| actor_keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| description_keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| ip_address | string | 否 | 查询参数；校验规则：nullable\|string\|max:45；来源：GeneralLogListRequest |
| level | string | 否 | 查询参数；校验规则：nullable\|in:DEBUG,INFO,NOTICE,WARNING,ERROR,CRITICAL,ALERT,EMERGENCY；来源：GeneralLogListRequest |
| module | string | 否 | 查询参数；校验规则：nullable\|string\|max:60；来源：GeneralLogListRequest |
| method | string | 否 | 查询参数；校验规则：nullable\|in:GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD；来源：GeneralLogListRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|string\|max:20；来源：GeneralLogListRequest |
| task_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:60；来源：GeneralLogListRequest |
| user_type | string | 否 | 查询参数；校验规则：nullable\|in:admin,client,guest；来源：GeneralLogListRequest |
| gateway | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：GeneralLogListRequest |
| gateway_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| driver_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| plugin_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：GeneralLogListRequest |
| trace_id | string | 否 | 查询参数；校验规则：nullable\|string\|max:64；来源：GeneralLogListRequest |
| action | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：GeneralLogListRequest |
| result_status | string | 否 | 查询参数；校验规则：nullable\|in:success,failed,pending,unknown；来源：GeneralLogListRequest |
| actor_type | string | 否 | 查询参数；校验规则：nullable\|in:admin,client,system,sub_account；来源：GeneralLogListRequest |
| subject_type | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：GeneralLogListRequest |
| start_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date；来源：GeneralLogListRequest |
| end_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date；来源：GeneralLogListRequest |

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
| data.total | integer | 总条数 |
| data.tasks | integer | 真实调用返回字段 |
| data.errors | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "total": 1000,
        "tasks": 16,
        "errors": 12
    },
    "timestamp": 1783240509
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:09  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/tasks/summary  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@taskLogsSummary`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
