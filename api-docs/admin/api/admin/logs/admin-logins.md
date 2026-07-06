# admin-logins

**请求方法**：GET  
**请求路径**：`/api/admin/logs/admin-logins`  
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
| data.current_page | integer | 真实调用返回字段 |
| data.data | array | 真实调用返回字段 |
| data.data.id | integer | 真实调用返回字段 |
| data.data.user_id | integer | 真实调用返回字段 |
| data.data.user_type | string | 真实调用返回字段 |
| data.data.actor_name | string | 真实调用返回字段 |
| data.data.role_name | string | 真实调用返回字段 |
| data.data.action | string | 真实调用返回字段 |
| data.data.module | string | 真实调用返回字段 |
| data.data.target_id | integer | 真实调用返回字段 |
| data.data.detail | object | 真实调用返回字段 |
| data.data.detail.role_name | string | 真实调用返回字段 |
| data.data.detail.admin_nickname | string | 真实调用返回字段 |
| data.data.detail.admin_username | string | 真实调用返回字段 |
| data.data.ip_address | string | 真实调用返回字段 |
| data.data.created_at | string | 真实调用返回字段 |
| data.data.admin_username | string | 真实调用返回字段 |
| data.data.admin_nickname | string | 真实调用返回字段 |
| data.data.source | string | 真实调用返回字段 |
| data.first_page_url | string | 真实调用返回字段 |
| data.from | integer | 真实调用返回字段 |
| data.last_page | integer | 真实调用返回字段 |
| data.last_page_url | string | 真实调用返回字段 |
| data.links | array | 真实调用返回字段 |
| data.links.url | null | 真实调用返回字段 |
| data.links.label | string | 真实调用返回字段 |
| data.links.page | null | 真实调用返回字段 |
| data.links.active | boolean | 真实调用返回字段 |
| data.next_page_url | string | 真实调用返回字段 |
| data.path | string | 真实调用返回字段 |
| data.per_page | integer | 真实调用返回字段 |
| data.prev_page_url | null | 真实调用返回字段 |
| data.to | integer | 真实调用返回字段 |
| data.total | integer | 总条数 |
| data.summary | object | 真实调用返回字段 |
| data.summary.total | integer | 真实调用返回字段 |
| data.summary.mode | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 125761,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:08:01",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125749,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:06:01",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125721,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 12:02:11",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125719,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 12:01:23",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125365,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:53:32",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125352,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:53:10",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125340,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:52:54",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125326,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:52:05",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125311,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:51:27",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125298,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:51:05",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125286,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:50:50",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125265,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:50:30",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125245,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:49:43",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125221,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:49:27",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125198,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:48:55",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125181,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:48:31",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125134,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:47:22",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125119,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:46:56",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125105,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:46:01",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            },
            {
                "id": 125091,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "admin.login",
                "module": "auth",
                "target_id": 1,
                "detail": {
                    "role_name": "超级管理员",
                    "admin_nickname": "管理员",
                    "admin_username": "cerbo"
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 00:44:57",
                "admin_username": "cerbo",
                "admin_nickname": "管理员",
                "source": "operation_log"
            }
        ],
        "first_page_url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=1",
        "from": 1,
        "last_page": 15,
        "last_page_url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=15",
        "links": [
            {
                "url": null,
                "label": "pagination.previous",
                "page": null,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=1",
                "label": "1",
                "page": 1,
                "active": true
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=2",
                "label": "2",
                "page": 2,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=3",
                "label": "3",
                "page": 3,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=4",
                "label": "4",
                "page": 4,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=5",
                "label": "5",
                "page": 5,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=6",
                "label": "6",
                "page": 6,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=7",
                "label": "7",
                "page": 7,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=8",
                "label": "8",
                "page": 8,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=9",
                "label": "9",
                "page": 9,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=10",
                "label": "10",
                "page": 10,
                "active": false
            },
            {
                "url": null,
                "label": "...",
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=14",
                "label": "14",
                "page": 14,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=15",
                "label": "15",
                "page": 15,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=2",
                "label": "pagination.next",
                "page": 2,
                "active": false
            }
        ],
        "next_page_url": "http://127.0.0.1:8000/api/admin/logs/admin-logins?page=2",
        "path": "http://127.0.0.1:8000/api/admin/logs/admin-logins",
        "per_page": 20,
        "prev_page_url": null,
        "to": 20,
        "total": 289,
        "summary": {
            "total": 289,
            "mode": "operation_log"
        }
    },
    "timestamp": 1783240489
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:49  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/admin-logins  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@adminLoginLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
