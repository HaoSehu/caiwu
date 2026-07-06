# 轮询充值状态

**请求方法**：GET  
**请求路径**：`/api/client/recharge/{paymentNo}/status`  
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
| paymentNo | string | 是 | 路径参数；充值支付单号 |
| poll_token | string | 是 | 查询参数；充值接口返回的一次性轮询 token，长度 20-120 |

### 请求示例（完整 JSON）
```json
{
    "paymentNo": "PAY202607050001",
    "poll_token": "***已脱敏***"
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
| data.balance | string|null | 支付成功后返回最新账户余额 |

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
· 调试时间：2026-07-05 16:35:28  
· 响应状态码：422  
· 调用方式：GET /api/client/recharge/{paymentNo}/status  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
正常调用必须使用充值接口返回的 `poll_token`。本次异常是手工样例 token 不满足源码校验。

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\RechargeController@status`  
· 请求校验：`App\Http\Requests\Client\Recharge\StatusRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器/服务/资源可静态确认 data 字段`  
· 中间件：`api, auth:sanctum, ensure.client, throttle:30,1,client-recharge-status`
