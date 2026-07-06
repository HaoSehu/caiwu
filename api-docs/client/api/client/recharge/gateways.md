# gateways

**请求方法**：GET  
**请求路径**：`/api/client/recharge/gateways`  
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
| 无 | - | 否 | 无请求参数 |

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
| data.list | array | 分页列表数据 |
| data.list.key | string | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.label | string | 真实调用返回字段 |
| data.list.option_key | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "key": "alipay",
                "name": "支付宝当面付",
                "label": "支付宝支付",
                "option_key": "alipay"
            },
            {
                "key": "yipay",
                "name": "易支付 - 支付宝",
                "label": "支付宝",
                "option_key": "yipay:alipay",
                "payment_type": "alipay"
            },
            {
                "key": "yipay",
                "name": "易支付 - 微信支付",
                "label": "微信支付",
                "option_key": "yipay:wxpay",
                "payment_type": "wxpay"
            }
        ]
    },
    "timestamp": 1783240528
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:28  
· 响应状态码：200  
· 调用方式：GET /api/client/recharge/gateways  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\RechargeController@gateways`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器 success([...]) 数组字段`  
· 中间件：`api, auth:sanctum, ensure.client`
