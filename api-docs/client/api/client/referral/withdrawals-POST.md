# withdrawals

**请求方法**：POST  
**请求路径**：`/api/client/referral/withdrawals`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |
| X-Request-Id | {trace_id} | 否 | 请求追踪 ID；控制器读取该请求头 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| amount | number | 是 | 请求体参数；校验规则：required\|numeric\|min:0.01；来源：ApplyWithdrawalRequest |
| method | string | 是 | 请求体参数；校验规则：required\|string\|in:balance,alipay；来源：ApplyWithdrawalRequest |
| account_name | string | 否 | 请求体参数；校验规则：nullable\|string\|max:80；来源：ApplyWithdrawalRequest |
| account_no | string | 否 | 请求体参数；校验规则：nullable\|regex:/^1[3-9]\d{9}$/；来源：ApplyWithdrawalRequest |
| remark | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：ApplyWithdrawalRequest |

### 请求示例（完整 JSON）
```json
{
    "amount": "10.00",
    "method": "balance",
    "account_name": "string",
    "account_no": "string",
    "remark": "string"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.id | integer | 业务字段；由源码静态提取 |
| data.amount | string(decimal) | 业务字段；由源码静态提取 |
| data.status | integer | 业务字段；由源码静态提取 |
| data.created_at | string(datetime) | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "提现申请已提交",
    "data": {
        "id": 1,
        "amount": "0.00",
        "status": [],
        "created_at": "2026-07-05 12:00:00"
    },
    "timestamp": 1760000000
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：接口为写操作、删除操作、支付/退款/开通/服务控制/通知发送/上游动作之一，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ReferralController@applyWithdrawal`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.client, throttle:3,1,client-referral-withdraw`
