# gateway

**请求方法**：GET  
**请求路径**：`/api/admin/logs/gateway`  
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
| data.data.plugin_id | integer | 真实调用返回字段 |
| data.data.gateway_key | string | 真实调用返回字段 |
| data.data.gateway | string | 真实调用返回字段 |
| data.data.action | string | 真实调用返回字段 |
| data.data.out_trade_no | string | 真实调用返回字段 |
| data.data.trade_no | null | 真实调用返回字段 |
| data.data.invoice_id | null | 真实调用返回字段 |
| data.data.trace_id | string | 真实调用返回字段 |
| data.data.request_data | object | 真实调用返回字段 |
| data.data.request_data.pid | string | 真实调用返回字段 |
| data.data.request_data.name | string | 真实调用返回字段 |
| data.data.request_data.sign | string | 真实调用返回字段 |
| data.data.request_data.type | string | 真实调用返回字段 |
| data.data.request_data.money | string | 真实调用返回字段 |
| data.data.request_data.param | string | 真实调用返回字段 |
| data.data.request_data.device | string | 真实调用返回字段 |
| data.data.request_data.clientip | string | 真实调用返回字段 |
| data.data.request_data.sitename | string | 真实调用返回字段 |
| data.data.request_data.sign_type | string | 真实调用返回字段 |
| data.data.request_data.notify_url | string | 真实调用返回字段 |
| data.data.request_data.return_url | string | 真实调用返回字段 |
| data.data.request_data.out_trade_no | string | 真实调用返回字段 |
| data.data.response_data | object | 真实调用返回字段 |
| data.data.response_data.msg | string | 真实调用返回字段 |
| data.data.response_data.code | integer | 真实调用返回字段 |
| data.data.result_status | string | 真实调用返回字段 |
| data.data.error_msg | string | 真实调用返回字段 |
| data.data.ip_address | null | 真实调用返回字段 |
| data.data.created_at | string | 真实调用返回字段 |
| data.data.updated_at | string | 真实调用返回字段 |
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
| data.summary.success | integer | 真实调用返回字段 |
| data.summary.failed | integer | 真实调用返回字段 |
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
                "id": 53,
                "plugin_id": 11,
                "gateway_key": "yipay",
                "gateway": "yipay",
                "action": "precreate",
                "out_trade_no": "PAY202607050220195423AMV9TWX",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "yipay:user:1:20260705022019",
                "request_data": {
                    "pid": "1039",
                    "name": "创欧云 - 账户充值 ¥20.00",
                    "sign": "[REDACTED]",
                    "type": "alipay",
                    "money": "20.00",
                    "param": "PAY202607050220195423AMV9TWX",
                    "device": "pc",
                    "clientip": "127.0.0.1",
                    "sitename": "创欧云",
                    "sign_type": "MD5",
                    "notify_url": "http://127.0.0.1:8000/api/client/payment/notify/yipay",
                    "return_url": "http://127.0.0.1:5173/client/recharge",
                    "out_trade_no": "PAY202607050220195423AMV9TWX"
                },
                "response_data": {
                    "msg": "更新订单失败，请返回重试",
                    "code": -1
                },
                "result_status": "failed",
                "error_msg": "更新订单失败，请返回重试",
                "ip_address": null,
                "created_at": "2026-07-04T18:21:20.000000Z",
                "updated_at": "2026-07-04T18:21:20.000000Z"
            },
            {
                "id": 52,
                "plugin_id": 11,
                "gateway_key": "yipay",
                "gateway": "yipay",
                "action": "precreate",
                "out_trade_no": "PAY202607050220195423AMV9TWX",
                "trade_no": "2026070502201969795",
                "invoice_id": null,
                "trace_id": "yipay:user:1:20260705022019",
                "request_data": {
                    "pid": "1039",
                    "name": "创欧云 - 账户充值 ¥20.00",
                    "sign": "[REDACTED]",
                    "type": "wxpay",
                    "money": "20.00",
                    "param": "PAY202607050220195423AMV9TWX",
                    "device": "pc",
                    "clientip": "127.0.0.1",
                    "sitename": "创欧云",
                    "sign_type": "MD5",
                    "notify_url": "http://127.0.0.1:8000/api/client/payment/notify/yipay",
                    "return_url": "http://127.0.0.1:5173/client/recharge",
                    "out_trade_no": "PAY202607050220195423AMV9TWX"
                },
                "response_data": {
                    "code": 1,
                    "qrcode": "weixin://wxpay/bizpayurl?pr=ndd0IyRz1",
                    "trade_no": "2026070502201969795"
                },
                "result_status": "success",
                "error_msg": null,
                "ip_address": null,
                "created_at": "2026-07-04T18:21:17.000000Z",
                "updated_at": "2026-07-04T18:21:17.000000Z"
            },
            {
                "id": 51,
                "plugin_id": 11,
                "gateway_key": "yipay",
                "gateway": "yipay",
                "action": "precreate",
                "out_trade_no": "PAY202607050220195423AMV9TWX",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "yipay:user:1:20260705022019",
                "request_data": {
                    "pid": "1039",
                    "name": "创欧云 - 账户充值 ¥20.00",
                    "sign": "[REDACTED]",
                    "type": "alipay",
                    "money": "20.00",
                    "param": "PAY202607050220195423AMV9TWX",
                    "device": "pc",
                    "clientip": "127.0.0.1",
                    "sitename": "创欧云",
                    "sign_type": "MD5",
                    "notify_url": "http://127.0.0.1:8000/api/client/payment/notify/yipay",
                    "return_url": "http://127.0.0.1:5173/client/recharge",
                    "out_trade_no": "PAY202607050220195423AMV9TWX"
                },
                "response_data": {
                    "msg": "更新订单失败，请返回重试",
                    "code": -1
                },
                "result_status": "failed",
                "error_msg": "更新订单失败，请返回重试",
                "ip_address": null,
                "created_at": "2026-07-04T18:20:41.000000Z",
                "updated_at": "2026-07-04T18:20:41.000000Z"
            },
            {
                "id": 50,
                "plugin_id": 11,
                "gateway_key": "yipay",
                "gateway": "yipay",
                "action": "precreate",
                "out_trade_no": "PAY202607050220195423AMV9TWX",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "yipay:user:1:20260705022019",
                "request_data": {
                    "pid": "1039",
                    "name": "创欧云 - 账户充值 ¥20.00",
                    "sign": "[REDACTED]",
                    "type": "alipay",
                    "money": "20.00",
                    "param": "PAY202607050220195423AMV9TWX",
                    "device": "pc",
                    "clientip": "127.0.0.1",
                    "sitename": "创欧云",
                    "sign_type": "MD5",
                    "notify_url": "http://127.0.0.1:8000/api/client/payment/notify/yipay",
                    "return_url": "http://127.0.0.1:5173/client/recharge",
                    "out_trade_no": "PAY202607050220195423AMV9TWX"
                },
                "response_data": {
                    "msg": "更新订单失败，请返回重试",
                    "code": -1
                },
                "result_status": "failed",
                "error_msg": "更新订单失败，请返回重试",
                "ip_address": null,
                "created_at": "2026-07-04T18:20:26.000000Z",
                "updated_at": "2026-07-04T18:20:26.000000Z"
            },
            {
                "id": 49,
                "plugin_id": 11,
                "gateway_key": "yipay",
                "gateway": "yipay",
                "action": "precreate",
                "out_trade_no": "PAY202607050220195423AMV9TWX",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "yipay:user:1:20260705022019",
                "request_data": {
                    "pid": "1039",
                    "name": "创欧云 - 账户充值 ¥20.00",
                    "sign": "[REDACTED]",
                    "type": "alipay",
                    "money": "20.00",
                    "param": "PAY202607050220195423AMV9TWX",
                    "device": "pc",
                    "clientip": "127.0.0.1",
                    "sitename": "创欧云",
                    "sign_type": "MD5",
                    "notify_url": "http://127.0.0.1:8000/api/client/payment/notify/yipay",
                    "return_url": "http://127.0.0.1:5173/client/recharge",
                    "out_trade_no": "PAY202607050220195423AMV9TWX"
                },
                "response_data": {
                    "msg": "更新订单失败，请返回重试",
                    "code": -1
                },
                "result_status": "failed",
                "error_msg": "更新订单失败，请返回重试",
                "ip_address": null,
                "created_at": "2026-07-04T18:20:23.000000Z",
                "updated_at": "2026-07-04T18:20:23.000000Z"
            },
            {
                "id": 48,
                "plugin_id": 11,
                "gateway_key": "yipay",
                "gateway": "yipay",
                "action": "precreate",
                "out_trade_no": "PAY202607050220195423AMV9TWX",
                "trade_no": "2026070502201969795",
                "invoice_id": null,
                "trace_id": "yipay:user:1:20260705022019",
                "request_data": {
                    "pid": "1039",
                    "name": "创欧云 - 账户充值 ¥20.00",
                    "sign": "[REDACTED]",
                    "type": "wxpay",
                    "money": "20.00",
                    "param": "PAY202607050220195423AMV9TWX",
                    "device": "pc",
                    "clientip": "127.0.0.1",
                    "sitename": "创欧云",
                    "sign_type": "MD5",
                    "notify_url": "http://127.0.0.1:8000/api/client/payment/notify/yipay",
                    "return_url": "http://127.0.0.1:5173/client/recharge",
                    "out_trade_no": "PAY202607050220195423AMV9TWX"
                },
                "response_data": {
                    "code": 1,
                    "qrcode": "weixin://wxpay/bizpayurl?pr=bjO9UfRz1",
                    "trade_no": "2026070502201969795"
                },
                "result_status": "success",
                "error_msg": null,
                "ip_address": null,
                "created_at": "2026-07-04T18:20:20.000000Z",
                "updated_at": "2026-07-04T18:20:20.000000Z"
            },
            {
                "id": 47,
                "plugin_id": 11,
                "gateway_key": "yipay",
                "gateway": "yipay",
                "action": "precreate",
                "out_trade_no": "PAY20260705015436457LZWFRBLY",
                "trade_no": "2026070501543695318",
                "invoice_id": null,
                "trace_id": "yipay:user:1:20260705015436",
                "request_data": {
                    "pid": "1039",
                    "name": "创欧云 - 账户充值 ¥1.00",
                    "sign": "[REDACTED]",
                    "type": "alipay",
                    "money": "1.00",
                    "param": "PAY20260705015436457LZWFRBLY",
                    "device": "pc",
                    "clientip": "127.0.0.1",
                    "sitename": "创欧云",
                    "sign_type": "MD5",
                    "notify_url": "http://127.0.0.1:8000/api/client/payment/notify/yipay",
                    "return_url": "http://127.0.0.1:5173/client/recharge",
                    "out_trade_no": "PAY20260705015436457LZWFRBLY"
                },
                "response_data": {
                    "code": 1,
                    "qrcode": "https://bbs.sg65.cn/pay/qrcode/2026070501543695318/",
                    "trade_no": "2026070501543695318"
                },
                "result_status": "success",
                "error_msg": null,
                "ip_address": null,
                "created_at": "2026-07-04T17:54:36.000000Z",
                "updated_at": "2026-07-04T17:54:36.000000Z"
            },
            {
                "id": 46,
                "plugin_id": 11,
                "gateway_key": "yipay",
                "gateway": "yipay",
                "action": "precreate",
                "out_trade_no": "PAY20260705015412259VEYFQHRC",
                "trade_no": "2026070501541268862",
                "invoice_id": null,
                "trace_id": "yipay:user:1:20260705015412",
                "request_data": {
                    "pid": "1039",
                    "name": "创欧云 - 账户充值 ¥20.00",
                    "sign": "[REDACTED]",
                    "type": "alipay",
                    "money": "20.00",
                    "param": "PAY20260705015412259VEYFQHRC",
                    "device": "pc",
                    "clientip": "127.0.0.1",
                    "sitename": "创欧云",
                    "sign_type": "MD5",
                    "notify_url": "http://127.0.0.1:8000/api/client/payment/notify/yipay",
                    "return_url": "http://127.0.0.1:5173/client/recharge",
                    "out_trade_no": "PAY20260705015412259VEYFQHRC"
                },
                "response_data": {
                    "code": 1,
                    "qrcode": "https://bbs.sg65.cn/pay/qrcode/2026070501541268862/",
                    "trade_no": "2026070501541268862"
                },
                "result_status": "success",
                "error_msg": null,
                "ip_address": null,
                "created_at": "2026-07-04T17:54:12.000000Z",
                "updated_at": "2026-07-04T17:54:12.000000Z"
            },
            {
                "id": 45,
                "plugin_id": 11,
                "gateway_key": "yipay",
                "gateway": "yipay",
                "action": "precreate",
                "out_trade_no": "PAY20260705013507322NKXACGWX",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "yipay:user:1:20260705013506",
                "request_data": {
                    "pid": "1039",
                    "name": "创欧云 - 账户充值 ¥20.00",
                    "sign": "[REDACTED]",
                    "type": "alipay",
                    "money": "20.00",
                    "param": "PAY20260705013507322NKXACGWX",
                    "device": "pc",
                    "clientip": "127.0.0.1",
                    "sitename": "创欧云",
                    "sign_type": "MD5",
                    "notify_url": "http://127.0.0.1:8000/api/client/payment/notify/yipay",
                    "return_url": "http://127.0.0.1:5173/client/recharge",
                    "out_trade_no": "PAY20260705013507322NKXACGWX"
                },
                "response_data": {
                    "msg": "当前请求域名「127.0.0.1」未完成授权，暂不可发起支付。请前往财务中心「授权支付域名」中添加该域名并完成审核后重试。",
                    "code": -1
                },
                "result_status": "failed",
                "error_msg": "当前请求域名「127.0.0.1」未完成授权，暂不可发起支付。请前往财务中心「授权支付域名」中添加该域名并完成审核后重试。",
                "ip_address": null,
                "created_at": "2026-07-04T17:48:10.000000Z",
                "updated_at": "2026-07-04T17:48:10.000000Z"
            },
            {
                "id": 44,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260705004944473U1GDP3IP",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260705004944",
                "request_data": {
                    "subject": "创欧云 - 账户充值 ¥20.00",
                    "out_trade_no": "PAY20260705004944473U1GDP3IP",
                    "total_amount": "20.00",
                    "timeout_express": "30m"
                },
                "response_data": {
                    "msg": "Success",
                    "code": "10000",
                    "qr_code": "https://qr.alipay.com/bax08543qrwsr9g7wgvu5570",
                    "out_trade_no": "PAY20260705004944473U1GDP3IP"
                },
                "result_status": "success",
                "error_msg": null,
                "ip_address": null,
                "created_at": "2026-07-04T16:49:44.000000Z",
                "updated_at": "2026-07-04T16:49:44.000000Z"
            },
            {
                "id": 43,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260704192326304J4PBNRRF",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260704192326",
                "request_data": {
                    "subject": "创欧云 - 账户充值 ¥20.00",
                    "out_trade_no": "PAY20260704192326304J4PBNRRF",
                    "total_amount": "20.00",
                    "timeout_express": "30m"
                },
                "response_data": {
                    "msg": "Success",
                    "code": "10000",
                    "qr_code": "https://qr.alipay.com/bax0015447gz6h58wzkg009a",
                    "out_trade_no": "PAY20260704192326304J4PBNRRF"
                },
                "result_status": "success",
                "error_msg": null,
                "ip_address": null,
                "created_at": "2026-07-04T11:35:47.000000Z",
                "updated_at": "2026-07-04T11:35:47.000000Z"
            },
            {
                "id": 42,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260704193207210K9FUXVVH",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260704193207",
                "request_data": {
                    "subject": "创欧云 - 账户充值 ¥1.00",
                    "out_trade_no": "PAY20260704193207210K9FUXVVH",
                    "total_amount": "1.00",
                    "timeout_express": "30m"
                },
                "response_data": {
                    "msg": "Success",
                    "code": "10000",
                    "qr_code": "https://qr.alipay.com/bax02176mbigkbpvickk0072",
                    "out_trade_no": "PAY20260704193207210K9FUXVVH"
                },
                "result_status": "success",
                "error_msg": null,
                "ip_address": null,
                "created_at": "2026-07-04T11:34:15.000000Z",
                "updated_at": "2026-07-04T11:34:15.000000Z"
            },
            {
                "id": 41,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260704193207210K9FUXVVH",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260704193207",
                "request_data": {
                    "subject": "创欧云 - 账户充值 ¥1.00",
                    "out_trade_no": "PAY20260704193207210K9FUXVVH",
                    "total_amount": "1.00",
                    "timeout_express": "30m"
                },
                "response_data": [],
                "result_status": "failed",
                "error_msg": "支付网关暂时不可用，请稍后重试",
                "ip_address": null,
                "created_at": "2026-07-04T11:32:07.000000Z",
                "updated_at": "2026-07-04T11:32:07.000000Z"
            },
            {
                "id": 40,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260704192326304J4PBNRRF",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260704192326",
                "request_data": {
                    "subject": "创欧云 - 账户充值 ¥20.00",
                    "out_trade_no": "PAY20260704192326304J4PBNRRF",
                    "total_amount": "20.00",
                    "timeout_express": "30m"
                },
                "response_data": [],
                "result_status": "failed",
                "error_msg": "支付网关暂时不可用，请稍后重试",
                "ip_address": null,
                "created_at": "2026-07-04T11:23:33.000000Z",
                "updated_at": "2026-07-04T11:23:33.000000Z"
            },
            {
                "id": 39,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260704192326304J4PBNRRF",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260704192326",
                "request_data": {
                    "subject": "创欧云 - 账户充值 ¥20.00",
                    "out_trade_no": "PAY20260704192326304J4PBNRRF",
                    "total_amount": "20.00",
                    "timeout_express": "30m"
                },
                "response_data": [],
                "result_status": "failed",
                "error_msg": "支付网关暂时不可用，请稍后重试",
                "ip_address": null,
                "created_at": "2026-07-04T11:23:26.000000Z",
                "updated_at": "2026-07-04T11:23:26.000000Z"
            },
            {
                "id": 38,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260704190727635AD8X8ZRF",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260704190727",
                "request_data": {
                    "subject": "创欧云 - 账户充值 ¥50.00",
                    "out_trade_no": "PAY20260704190727635AD8X8ZRF",
                    "total_amount": "50.00",
                    "timeout_express": "30m"
                },
                "response_data": [],
                "result_status": "failed",
                "error_msg": "支付网关暂时不可用，请稍后重试",
                "ip_address": null,
                "created_at": "2026-07-04T11:07:27.000000Z",
                "updated_at": "2026-07-04T11:07:27.000000Z"
            },
            {
                "id": 37,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260704185415421UYK5XBTD",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260704185415",
                "request_data": {
                    "subject": "创欧云 - 账户充值 ¥20.00",
                    "out_trade_no": "PAY20260704185415421UYK5XBTD",
                    "total_amount": "20.00",
                    "timeout_express": "30m"
                },
                "response_data": [],
                "result_status": "failed",
                "error_msg": "支付网关暂时不可用，请稍后重试",
                "ip_address": null,
                "created_at": "2026-07-04T11:05:51.000000Z",
                "updated_at": "2026-07-04T11:05:51.000000Z"
            },
            {
                "id": 36,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260704185415421UYK5XBTD",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260704185415",
                "request_data": {
                    "subject": "创欧云 - 账户充值 ¥20.00",
                    "out_trade_no": "PAY20260704185415421UYK5XBTD",
                    "total_amount": "20.00",
                    "timeout_express": "30m"
                },
                "response_data": [],
                "result_status": "failed",
                "error_msg": "支付网关暂时不可用，请稍后重试",
                "ip_address": null,
                "created_at": "2026-07-04T11:03:44.000000Z",
                "updated_at": "2026-07-04T11:03:44.000000Z"
            },
            {
                "id": 35,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260704035631051LLOHPTWH",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260704035631",
                "request_data": {
                    "subject": "��ŷ�� - 账户充值 ¥3.00",
                    "out_trade_no": "PAY20260704035631051LLOHPTWH",
                    "total_amount": "3.00",
                    "timeout_express": "30m"
                },
                "response_data": {
                    "msg": "Success",
                    "code": "10000",
                    "qr_code": "https://qr.alipay.com/bax088055qlmio3ojzyv550b",
                    "out_trade_no": "PAY20260704035631051LLOHPTWH"
                },
                "result_status": "success",
                "error_msg": null,
                "ip_address": null,
                "created_at": "2026-07-03T19:56:31.000000Z",
                "updated_at": "2026-07-03T19:56:31.000000Z"
            },
            {
                "id": 34,
                "plugin_id": 2,
                "gateway_key": "alipay",
                "gateway": "alipay",
                "action": "precreate",
                "out_trade_no": "PAY20260704034705566NDMB1OAI",
                "trade_no": null,
                "invoice_id": null,
                "trace_id": "alipay:user:1:20260704034705",
                "request_data": {
                    "subject": "��ŷ�� - 账户充值 ¥20.00",
                    "out_trade_no": "PAY20260704034705566NDMB1OAI",
                    "total_amount": "20.00",
                    "timeout_express": "30m"
                },
                "response_data": {
                    "msg": "Success",
                    "code": "10000",
                    "qr_code": "https://qr.alipay.com/bax02602yydfrpwskeof30db",
                    "out_trade_no": "PAY20260704034705566NDMB1OAI"
                },
                "result_status": "success",
                "error_msg": null,
                "ip_address": null,
                "created_at": "2026-07-03T19:56:24.000000Z",
                "updated_at": "2026-07-03T19:56:24.000000Z"
            }
        ],
        "first_page_url": "http://127.0.0.1:8000/api/admin/logs/gateway?page=1",
        "from": 1,
        "last_page": 3,
        "last_page_url": "http://127.0.0.1:8000/api/admin/logs/gateway?page=3",
        "links": [
            {
                "url": null,
                "label": "pagination.previous",
                "page": null,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/gateway?page=1",
                "label": "1",
                "page": 1,
                "active": true
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/gateway?page=2",
                "label": "2",
                "page": 2,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/gateway?page=3",
                "label": "3",
                "page": 3,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/gateway?page=2",
                "label": "pagination.next",
                "page": 2,
                "active": false
            }
        ],
        "next_page_url": "http://127.0.0.1:8000/api/admin/logs/gateway?page=2",
        "path": "http://127.0.0.1:8000/api/admin/logs/gateway",
        "per_page": 20,
        "prev_page_url": null,
        "to": 20,
        "total": 53,
        "summary": {
            "total": 53,
            "success": 42,
            "failed": 11
        }
    },
    "timestamp": 1783240501
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:01  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/gateway  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@gatewayLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
