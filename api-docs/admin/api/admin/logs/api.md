# api

**请求方法**：GET  
**请求路径**：`/api/admin/logs/api`  
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
| data.data.target_id | null | 真实调用返回字段 |
| data.data.detail | object | 真实调用返回字段 |
| data.data.detail.params | object | 真实调用返回字段 |
| data.data.detail.params.page | string | 真实调用返回字段 |
| data.data.detail.params.page_size | string | 真实调用返回字段 |
| data.data.detail.status | integer | 真实调用返回字段 |
| data.data.detail.request_id | string | 真实调用返回字段 |
| data.data.detail.user_agent | string | 真实调用返回字段 |
| data.data.ip_address | string | 真实调用返回字段 |
| data.data.created_at | string | 真实调用返回字段 |
| data.data.method | string | 真实调用返回字段 |
| data.data.path | string | 真实调用返回字段 |
| data.data.status | integer | 真实调用返回字段 |
| data.data.request_id | string | 真实调用返回字段 |
| data.data.params | object | 真实调用返回字段 |
| data.data.params.page | string | 真实调用返回字段 |
| data.data.params.page_size | string | 真实调用返回字段 |
| data.data.user_agent | string | 真实调用返回字段 |
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
| data.summary.errors | integer | 真实调用返回字段 |
| data.summary.admin_count | integer | 真实调用返回字段 |
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
                "id": 126292,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/invoices/1",
                "module": "invoices",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:49",
                "method": "GET",
                "path": "api/admin/invoices/1",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126291,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/invoices",
                "module": "invoices",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:49",
                "method": "GET",
                "path": "api/admin/invoices",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126290,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/integration-plugins",
                "module": "integration-plugins",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:49",
                "method": "GET",
                "path": "api/admin/integration-plugins",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126289,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/instance-spec-catalog",
                "module": "instance-spec-catalog",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:48",
                "method": "GET",
                "path": "api/admin/instance-spec-catalog",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126288,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/finance/upgrade-orders",
                "module": "finance",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:47",
                "method": "GET",
                "path": "api/admin/finance/upgrade-orders",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126287,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/finance/renewal-orders",
                "module": "finance",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:47",
                "method": "GET",
                "path": "api/admin/finance/renewal-orders",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126286,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/finance/recharges",
                "module": "finance",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:47",
                "method": "GET",
                "path": "api/admin/finance/recharges",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126285,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/finance/product-income-summary",
                "module": "finance",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 422,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:47",
                "method": "GET",
                "path": "api/admin/finance/product-income-summary",
                "status": 422,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126284,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/finance/new-customer-daily-summary",
                "module": "finance",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 422,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:47",
                "method": "GET",
                "path": "api/admin/finance/new-customer-daily-summary",
                "status": 422,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126283,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/finance/ledger/summary",
                "module": "finance",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:47",
                "method": "GET",
                "path": "api/admin/finance/ledger/summary",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126282,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/finance/ledger",
                "module": "finance",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:47",
                "method": "GET",
                "path": "api/admin/finance/ledger",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126281,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/dashboard/stats",
                "module": "dashboard",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:46",
                "method": "GET",
                "path": "api/admin/dashboard/stats",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126280,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/dashboard/recent-invoices",
                "module": "dashboard",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 500,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:46",
                "method": "GET",
                "path": "api/admin/dashboard/recent-invoices",
                "status": 500,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126279,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/dashboard/monthly-revenue",
                "module": "dashboard",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:46",
                "method": "GET",
                "path": "api/admin/dashboard/monthly-revenue",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126278,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/dashboard",
                "module": "dashboard",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 500,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:46",
                "method": "GET",
                "path": "api/admin/dashboard",
                "status": 500,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126277,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/cpu-model-catalog",
                "module": "cpu-model-catalog",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:46",
                "method": "GET",
                "path": "api/admin/cpu-model-catalog",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126276,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/coupons/summary",
                "module": "coupons",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:45",
                "method": "GET",
                "path": "api/admin/coupons/summary",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126275,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/coupons/product-tree",
                "module": "coupons",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:45",
                "method": "GET",
                "path": "api/admin/coupons/product-tree",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126274,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/coupons",
                "module": "coupons",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:44",
                "method": "GET",
                "path": "api/admin/coupons",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            },
            {
                "id": 126273,
                "user_id": 1,
                "user_type": "admin",
                "actor_name": "管理员",
                "role_name": "超级管理员",
                "action": "GET api/admin/coupon-campaigns/summary",
                "module": "coupon-campaigns",
                "target_id": null,
                "detail": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05 16:34:44",
                "method": "GET",
                "path": "api/admin/coupon-campaigns/summary",
                "status": 200,
                "request_id": "",
                "params": {
                    "page": "1",
                    "page_size": "1"
                },
                "user_agent": ""
            }
        ],
        "first_page_url": "http://127.0.0.1:8000/api/admin/logs/api?page=1",
        "from": 1,
        "last_page": 5955,
        "last_page_url": "http://127.0.0.1:8000/api/admin/logs/api?page=5955",
        "links": [
            {
                "url": null,
                "label": "pagination.previous",
                "page": null,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=1",
                "label": "1",
                "page": 1,
                "active": true
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=2",
                "label": "2",
                "page": 2,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=3",
                "label": "3",
                "page": 3,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=4",
                "label": "4",
                "page": 4,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=5",
                "label": "5",
                "page": 5,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=6",
                "label": "6",
                "page": 6,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=7",
                "label": "7",
                "page": 7,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=8",
                "label": "8",
                "page": 8,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=9",
                "label": "9",
                "page": 9,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=10",
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
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=5954",
                "label": "5954",
                "page": 5954,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=5955",
                "label": "5955",
                "page": 5955,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/api?page=2",
                "label": "pagination.next",
                "page": 2,
                "active": false
            }
        ],
        "next_page_url": "http://127.0.0.1:8000/api/admin/logs/api?page=2",
        "path": "http://127.0.0.1:8000/api/admin/logs/api",
        "per_page": 20,
        "prev_page_url": null,
        "to": 20,
        "total": 119081,
        "summary": {
            "total": 119081,
            "errors": 449,
            "admin_count": 8642
        }
    },
    "timestamp": 1783240493
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:53  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/api  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@apiLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
