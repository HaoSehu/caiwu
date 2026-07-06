# status

**请求方法**：GET  
**请求路径**：`/api/client/invoices/{id}/pay/alipay/status`  
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
| id | integer|string | 是 | 路径参数；账单 ID，必须属于当前用户 |
| payment_no | string | 是 | 查询参数；支付单号 |
| poll_token | string | 是 | 查询参数；支付发起接口返回的一次性轮询 token，长度 20-120 |
| gateway | string | 否 | 查询参数；第三方支付网关：alipay、yipay、wechat、stripe |

### 请求示例（完整 JSON）
```json
{
    "payment_no": "PAY202607050001",
    "poll_token": "***已脱敏***",
    "gateway": "alipay"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.paid | boolean | 是否已支付 |
| data.trade_no | string|null | 第三方交易号；已支付时返回 |
| data.trade_status | string|null | 第三方交易状态；未支付或查询中返回 |
| data.invoice | object|null | 账单详情；支付成功后返回 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "paid": false,
        "trade_status": "WAIT_BUYER_PAY"
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:26  
· 响应状态码：422  
· 调用方式：GET /api/client/invoices/{id}/pay/alipay/status  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
正常调用必须使用支付发起接口返回的 `poll_token`。本次异常是手工样例 token/gateway 不满足源码校验。

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\InvoiceController@queryAlipayStatus`  
· 请求校验：`App\Http\Requests\Client\Invoice\QueryAlipayStatusRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client, throttle:30,1,client-invoices-pay-alipay-status`
