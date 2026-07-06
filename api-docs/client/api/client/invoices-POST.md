# invoices

**请求方法**：POST  
**请求路径**：`/api/client/invoices`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |
| X-Idempotency-Key | {idempotency_key} | 否 | 幂等键；控制器读取该请求头 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| product_id | integer | 是 | 请求体参数；校验规则：required\|integer\|min:1；来源：StoreRequest |
| billing_cycle | string | 是 | 请求体参数；校验规则：required\|string\|max:30；来源：StoreRequest |
| quantity | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1\|max:10；来源：StoreRequest |
| config | array | 否 | 请求体参数；校验规则：nullable\|array；来源：StoreRequest |
| quote_token | string | 是 | 请求体参数；校验规则：required\|string\|min:20\|max:120；来源：StoreRequest |
| user_coupon_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：StoreRequest |

### 请求示例（完整 JSON）
```json
{
    "product_id": 1,
    "billing_cycle": "string",
    "quantity": 1,
    "config": [],
    "quote_token": "sample_token",
    "user_coupon_id": 1
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.service_id | integer | 业务字段；由源码静态提取 |
| data.pay_methods | array | 业务字段；由源码静态提取 |
| data.can_cancel | boolean | 业务字段；由源码静态提取 |
| data.payable_amount | string(decimal) | 业务字段；由源码静态提取 |
| data.product | object | 业务字段；由源码静态提取 |
| data.config_snapshot | object | 业务字段；由源码静态提取 |
| data.config_pricing_snapshot | object | 业务字段；由源码静态提取 |
| data.coupon | object | 业务字段；由源码静态提取 |
| data.payment_security | object | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "账单创建成功",
    "data": {
        "service_id": 1,
        "pay_methods": [],
        "can_cancel": true,
        "payable_amount": "0.00",
        "product": [],
        "config_snapshot": [],
        "config_pricing_snapshot": [],
        "coupon": [],
        "payment_security": []
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
· 控制器动作：`App\Http\Controllers\Client\InvoiceController@store`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.client, throttle:8,1,client-invoices-store`
