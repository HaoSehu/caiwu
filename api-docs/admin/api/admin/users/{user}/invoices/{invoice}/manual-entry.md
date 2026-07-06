# manual-entry

**请求方法**：POST  
**请求路径**：`/api/admin/users/{user}/invoices/{invoice}/manual-entry`  
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
| user | integer\|string | 是 | 路径参数；来自路由占位 `{user}` |
| invoice | integer\|string | 是 | 路径参数；来自路由占位 `{invoice}` |
| amount | number | 是 | 请求体参数；校验规则：required\|numeric\|min:0.01\|max:99999999；来源：ManualInvoiceEntryRequest |
| paid_at | string(datetime) | 是 | 请求体参数；校验规则：required\|date；来源：ManualInvoiceEntryRequest |
| payment_gateway | string | 是 | 请求体参数；校验规则：required\|string\|in:manual,alipay,wechat,balance,bank_transfer,offline；来源：ManualInvoiceEntryRequest |
| trade_no | string | 否 | 请求体参数；校验规则：nullable\|string\|max:100；来源：ManualInvoiceEntryRequest |
| send_email | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：ManualInvoiceEntryRequest |
| sync_business_flow | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：ManualInvoiceEntryRequest |
| remark | string | 否 | 请求体参数；校验规则：nullable\|string\|max:200；来源：ManualInvoiceEntryRequest |

### 请求示例（完整 JSON）
```json
{
    "amount": "10.00",
    "paid_at": "2026-07-05",
    "payment_gateway": "manual",
    "trade_no": "string",
    "send_email": true,
    "sync_business_flow": true,
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
| data.operator_id | integer | 业务字段；由源码静态提取 |
| data.operator_name | string | 业务字段；由源码静态提取 |
| data.trace_id | integer | 业务字段；由源码静态提取 |
| data.ip_address | array | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "手动入账成功",
    "data": {
        "operator_id": 1,
        "operator_name": "string",
        "trace_id": 1,
        "ip_address": []
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
· 控制器动作：`App\Http\Controllers\Admin\UserController@manualInvoiceEntry`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:invoice.manage`
