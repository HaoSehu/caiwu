# system

**请求方法**：GET  
**请求路径**：`/api/admin/logs/system`  
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
| data.data.actor_type | string | 真实调用返回字段 |
| data.data.actor_id | integer | 真实调用返回字段 |
| data.data.actor_name | string | 真实调用返回字段 |
| data.data.module | string | 真实调用返回字段 |
| data.data.action | string | 真实调用返回字段 |
| data.data.description | string | 真实调用返回字段 |
| data.data.subject_type | string | 真实调用返回字段 |
| data.data.subject_id | null | 真实调用返回字段 |
| data.data.context | object | 真实调用返回字段 |
| data.data.context.params | object | 真实调用返回字段 |
| data.data.context.params.page | string | 真实调用返回字段 |
| data.data.context.params.page_size | string | 真实调用返回字段 |
| data.data.context.status | integer | 真实调用返回字段 |
| data.data.context.request_id | string | 真实调用返回字段 |
| data.data.context.user_agent | string | 真实调用返回字段 |
| data.data.ip_address | string | 真实调用返回字段 |
| data.data.created_at | string | 真实调用返回字段 |
| data.data.updated_at | string | 真实调用返回字段 |
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
| data.summary.modules | integer | 真实调用返回字段 |
| data.summary.source | string | 真实调用返回字段 |
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
                "id": 4118,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "invoices",
                "action": "GET api/admin/invoices/1",
                "description": "[invoices] GET api/admin/invoices/1",
                "subject_type": "invoices",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:49.000000Z",
                "updated_at": "2026-07-05T08:34:49.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4117,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "invoices",
                "action": "GET api/admin/invoices",
                "description": "[invoices] GET api/admin/invoices",
                "subject_type": "invoices",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:49.000000Z",
                "updated_at": "2026-07-05T08:34:49.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4116,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "integration-plugins",
                "action": "GET api/admin/integration-plugins",
                "description": "[integration-plugins] GET api/admin/integration-plugins",
                "subject_type": "integration-plugins",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:49.000000Z",
                "updated_at": "2026-07-05T08:34:49.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4115,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "instance-spec-catalog",
                "action": "GET api/admin/instance-spec-catalog",
                "description": "[instance-spec-catalog] GET api/admin/instance-spec-catalog",
                "subject_type": "instance-spec-catalog",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:48.000000Z",
                "updated_at": "2026-07-05T08:34:48.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4114,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "finance",
                "action": "GET api/admin/finance/upgrade-orders",
                "description": "[finance] GET api/admin/finance/upgrade-orders",
                "subject_type": "finance",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:47.000000Z",
                "updated_at": "2026-07-05T08:34:47.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4113,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "finance",
                "action": "GET api/admin/finance/renewal-orders",
                "description": "[finance] GET api/admin/finance/renewal-orders",
                "subject_type": "finance",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:47.000000Z",
                "updated_at": "2026-07-05T08:34:47.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4112,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "finance",
                "action": "GET api/admin/finance/recharges",
                "description": "[finance] GET api/admin/finance/recharges",
                "subject_type": "finance",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:47.000000Z",
                "updated_at": "2026-07-05T08:34:47.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4111,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "finance",
                "action": "GET api/admin/finance/product-income-summary",
                "description": "[finance] GET api/admin/finance/product-income-summary",
                "subject_type": "finance",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 422,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:47.000000Z",
                "updated_at": "2026-07-05T08:34:47.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4110,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "finance",
                "action": "GET api/admin/finance/new-customer-daily-summary",
                "description": "[finance] GET api/admin/finance/new-customer-daily-summary",
                "subject_type": "finance",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 422,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:47.000000Z",
                "updated_at": "2026-07-05T08:34:47.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4109,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "finance",
                "action": "GET api/admin/finance/ledger/summary",
                "description": "[finance] GET api/admin/finance/ledger/summary",
                "subject_type": "finance",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:47.000000Z",
                "updated_at": "2026-07-05T08:34:47.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4108,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "finance",
                "action": "GET api/admin/finance/ledger",
                "description": "[finance] GET api/admin/finance/ledger",
                "subject_type": "finance",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:47.000000Z",
                "updated_at": "2026-07-05T08:34:47.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4107,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "dashboard",
                "action": "GET api/admin/dashboard/stats",
                "description": "[dashboard] GET api/admin/dashboard/stats",
                "subject_type": "dashboard",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:46.000000Z",
                "updated_at": "2026-07-05T08:34:46.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4106,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "dashboard",
                "action": "GET api/admin/dashboard/recent-invoices",
                "description": "[dashboard] GET api/admin/dashboard/recent-invoices",
                "subject_type": "dashboard",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 500,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:46.000000Z",
                "updated_at": "2026-07-05T08:34:46.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4105,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "dashboard",
                "action": "GET api/admin/dashboard/monthly-revenue",
                "description": "[dashboard] GET api/admin/dashboard/monthly-revenue",
                "subject_type": "dashboard",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:46.000000Z",
                "updated_at": "2026-07-05T08:34:46.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4104,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "dashboard",
                "action": "GET api/admin/dashboard",
                "description": "[dashboard] GET api/admin/dashboard",
                "subject_type": "dashboard",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 500,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:46.000000Z",
                "updated_at": "2026-07-05T08:34:46.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4103,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "cpu-model-catalog",
                "action": "GET api/admin/cpu-model-catalog",
                "description": "[cpu-model-catalog] GET api/admin/cpu-model-catalog",
                "subject_type": "cpu-model-catalog",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:46.000000Z",
                "updated_at": "2026-07-05T08:34:46.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4102,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "coupons",
                "action": "GET api/admin/coupons/summary",
                "description": "[coupons] GET api/admin/coupons/summary",
                "subject_type": "coupons",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:45.000000Z",
                "updated_at": "2026-07-05T08:34:45.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4101,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "coupons",
                "action": "GET api/admin/coupons/product-tree",
                "description": "[coupons] GET api/admin/coupons/product-tree",
                "subject_type": "coupons",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:45.000000Z",
                "updated_at": "2026-07-05T08:34:45.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4100,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "coupons",
                "action": "GET api/admin/coupons",
                "description": "[coupons] GET api/admin/coupons",
                "subject_type": "coupons",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:44.000000Z",
                "updated_at": "2026-07-05T08:34:44.000000Z",
                "source": "activity_log"
            },
            {
                "id": 4099,
                "actor_type": "admin",
                "actor_id": 1,
                "actor_name": "admin",
                "module": "coupon-campaigns",
                "action": "GET api/admin/coupon-campaigns/summary",
                "description": "[coupon-campaigns] GET api/admin/coupon-campaigns/summary",
                "subject_type": "coupon-campaigns",
                "subject_id": null,
                "context": {
                    "params": {
                        "page": "1",
                        "page_size": "1"
                    },
                    "status": 200,
                    "request_id": "",
                    "user_agent": ""
                },
                "ip_address": "127.0.0.1",
                "created_at": "2026-07-05T08:34:44.000000Z",
                "updated_at": "2026-07-05T08:34:44.000000Z",
                "source": "activity_log"
            }
        ],
        "first_page_url": "http://127.0.0.1:8000/api/admin/logs/system?page=1",
        "from": 1,
        "last_page": 206,
        "last_page_url": "http://127.0.0.1:8000/api/admin/logs/system?page=206",
        "links": [
            {
                "url": null,
                "label": "pagination.previous",
                "page": null,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=1",
                "label": "1",
                "page": 1,
                "active": true
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=2",
                "label": "2",
                "page": 2,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=3",
                "label": "3",
                "page": 3,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=4",
                "label": "4",
                "page": 4,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=5",
                "label": "5",
                "page": 5,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=6",
                "label": "6",
                "page": 6,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=7",
                "label": "7",
                "page": 7,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=8",
                "label": "8",
                "page": 8,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=9",
                "label": "9",
                "page": 9,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=10",
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
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=205",
                "label": "205",
                "page": 205,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=206",
                "label": "206",
                "page": 206,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/system?page=2",
                "label": "pagination.next",
                "page": 2,
                "active": false
            }
        ],
        "next_page_url": "http://127.0.0.1:8000/api/admin/logs/system?page=2",
        "path": "http://127.0.0.1:8000/api/admin/logs/system",
        "per_page": 20,
        "prev_page_url": null,
        "to": 20,
        "total": 4118,
        "summary": {
            "total": 4118,
            "modules": 45,
            "source": "activity_logs"
        }
    },
    "timestamp": 1783240507
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:07  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/system  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@systemLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
