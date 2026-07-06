# sms

**请求方法**：GET  
**请求路径**：`/api/admin/logs/sms`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：SmsLogListRequest |
| per_page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:50；来源：SmsLogListRequest |
| phone | string | 否 | 查询参数；校验规则：nullable\|string\|max:20；来源：SmsLogListRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：SmsLogListRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|in:pending,success,failed；来源：SmsLogListRequest |
| plugin_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：SmsLogListRequest |
| driver_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：SmsLogListRequest |
| trace_id | string | 否 | 查询参数；校验规则：nullable\|string\|max:64；来源：SmsLogListRequest |

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
| data.data.phone | string | 真实调用返回字段 |
| data.data.template_code | string | 真实调用返回字段 |
| data.data.content | string | 真实调用返回字段 |
| data.data.status | string | 真实调用返回字段 |
| data.data.provider | string | 真实调用返回字段 |
| data.data.request_id | null | 真实调用返回字段 |
| data.data.error_msg | null | 真实调用返回字段 |
| data.data.sent_at | string | 真实调用返回字段 |
| data.data.created_at | string | 真实调用返回字段 |
| data.data.updated_at | string | 真实调用返回字段 |
| data.data.origin_type | string | 真实调用返回字段 |
| data.data.plugin_id | integer | 真实调用返回字段 |
| data.data.driver_key | string | 真实调用返回字段 |
| data.data.trace_id | string | 真实调用返回字段 |
| data.data.params | object | 真实调用返回字段 |
| data.data.params.min | string | 真实调用返回字段 |
| data.data.params.code | string | 真实调用返回字段 |
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
| data.summary | array | 真实调用返回字段 |
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
                "id": 1417,
                "phone": "15697289437",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-30T16:38:44.000000Z",
                "created_at": "2026-06-30T16:38:43.000000Z",
                "updated_at": "2026-06-30T16:38:44.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-ad5d7f3c1ffc2225a7ca0617a642df52",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1361,
                "phone": "17266508220",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-27T05:48:45.000000Z",
                "created_at": "2026-06-27T05:48:44.000000Z",
                "updated_at": "2026-06-27T05:48:45.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-4055dffa25c2628bbff965ef9834b55b",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1360,
                "phone": "17266508220",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-27T05:47:26.000000Z",
                "created_at": "2026-06-27T05:47:25.000000Z",
                "updated_at": "2026-06-27T05:47:26.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-7d832cfe443a419711e7ce19ec34f729",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1304,
                "phone": "15697289437",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-23T07:47:42.000000Z",
                "created_at": "2026-06-23T07:47:41.000000Z",
                "updated_at": "2026-06-23T07:47:42.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-b5b66e6c4eccc565094ac98c2706c0af",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1299,
                "phone": "18231202665",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-22T03:51:34.000000Z",
                "created_at": "2026-06-22T03:51:33.000000Z",
                "updated_at": "2026-06-22T03:51:34.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-c1de6c94c8de572784c06ba3e3619b22",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1295,
                "phone": "15697289437",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-21T14:43:52.000000Z",
                "created_at": "2026-06-21T14:43:51.000000Z",
                "updated_at": "2026-06-21T14:43:52.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-9a2c05189a12e1effdb3d097f8bff507",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1278,
                "phone": "15697289437",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-18T17:01:27.000000Z",
                "created_at": "2026-06-18T17:01:27.000000Z",
                "updated_at": "2026-06-18T17:01:27.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-ab2b12381b6007a0921742c4a5e3e4fc",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1203,
                "phone": "17353593324",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-13T12:08:00.000000Z",
                "created_at": "2026-06-13T12:07:59.000000Z",
                "updated_at": "2026-06-13T12:08:00.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-937b01627495ef4c24488bccc2c23653",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1121,
                "phone": "18653051282",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "failed",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": "The stream or file \"/www/wwwroot/backend/storage/logs/laravel.log\" could not be opened in append mode: Failed to open stream: Permission denied\nThe exception occurred while attempting to log: [短信] 请求阿里云短信接口\nContext: {\"url\":\"https:\/\/dypnsapi.aliyuncs.com\/\",\"action\":\"SendSmsVerifyCode\",\"phone\":\"186****1282\"}",
                "sent_at": null,
                "created_at": "2026-06-09T05:21:41.000000Z",
                "updated_at": "2026-06-09T05:21:41.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-03bbef3ff19b1856a3560964c220c438",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1120,
                "phone": "18653051282",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "failed",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": "The stream or file \"/www/wwwroot/backend/storage/logs/laravel.log\" could not be opened in append mode: Failed to open stream: Permission denied\nThe exception occurred while attempting to log: [短信] 请求阿里云短信接口\nContext: {\"url\":\"https:\/\/dypnsapi.aliyuncs.com\/\",\"action\":\"SendSmsVerifyCode\",\"phone\":\"186****1282\"}",
                "sent_at": null,
                "created_at": "2026-06-09T05:21:29.000000Z",
                "updated_at": "2026-06-09T05:21:29.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-29e4786643e33ba6504a2bf533036038",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1045,
                "phone": "17607065013",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-05T13:31:26.000000Z",
                "created_at": "2026-06-05T13:31:25.000000Z",
                "updated_at": "2026-06-05T13:31:26.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-cc1b94d16fa28a3c0452f08cce8e4af2",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 1003,
                "phone": "17266508220",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-02T17:04:09.000000Z",
                "created_at": "2026-06-02T17:04:08.000000Z",
                "updated_at": "2026-06-02T17:04:09.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-b1d282be2fa0fc6c87259341d9c8e346",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 990,
                "phone": "15269266566",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-02T04:18:08.000000Z",
                "created_at": "2026-06-02T04:18:08.000000Z",
                "updated_at": "2026-06-02T04:18:08.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-8c309d4c8c49f7b699a9fcc9499aa470",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 988,
                "phone": "15269266566",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-02T04:17:01.000000Z",
                "created_at": "2026-06-02T04:16:59.000000Z",
                "updated_at": "2026-06-02T04:17:01.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-65c5ddcb03b57eb6cab175b1000d3a15",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            },
            {
                "id": 975,
                "phone": "18921230678",
                "template_code": "100001",
                "content": "短信验证码已发送（内容已脱敏）",
                "status": "success",
                "provider": "aliyun",
                "request_id": null,
                "error_msg": null,
                "sent_at": "2026-06-01T14:42:59.000000Z",
                "created_at": "2026-06-01T14:42:58.000000Z",
                "updated_at": "2026-06-01T14:42:59.000000Z",
                "origin_type": "sms_verify",
                "plugin_id": 4,
                "driver_key": "aliyun",
                "trace_id": "bf-00318ea673c804072e75abfc7ce7b50e",
                "params": {
                    "min": "5",
                    "code": "***"
                }
            }
        ],
        "first_page_url": "http://127.0.0.1:8000/api/admin/logs/sms?page=1",
        "from": 1,
        "last_page": 7,
        "last_page_url": "http://127.0.0.1:8000/api/admin/logs/sms?page=7",
        "links": [
            {
                "url": null,
                "label": "pagination.previous",
                "page": null,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/sms?page=1",
                "label": "1",
                "page": 1,
                "active": true
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/sms?page=2",
                "label": "2",
                "page": 2,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/sms?page=3",
                "label": "3",
                "page": 3,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/sms?page=4",
                "label": "4",
                "page": 4,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/sms?page=5",
                "label": "5",
                "page": 5,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/sms?page=6",
                "label": "6",
                "page": 6,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/sms?page=7",
                "label": "7",
                "page": 7,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/sms?page=2",
                "label": "pagination.next",
                "page": 2,
                "active": false
            }
        ],
        "next_page_url": "http://127.0.0.1:8000/api/admin/logs/sms?page=2",
        "path": "http://127.0.0.1:8000/api/admin/logs/sms",
        "per_page": 15,
        "prev_page_url": null,
        "to": 15,
        "total": 100,
        "summary": []
    },
    "timestamp": 1783240506
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:06  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/sms  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@smsLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
