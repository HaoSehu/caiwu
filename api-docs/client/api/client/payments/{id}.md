# {id}

**请求方法**：GET  
**请求路径**：`/api/client/payments/{id}`  
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
| id | integer\|string | 是 | 路径参数；来自路由占位 `{id}` |

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
| data.id | integer | 真实调用返回字段 |
| data.payment_no | string | 真实调用返回字段 |
| data.trade_no | string | 真实调用返回字段 |
| data.gateway | string | 真实调用返回字段 |
| data.gateway_key | string | 真实调用返回字段 |
| data.gateway_label | string | 真实调用返回字段 |
| data.amount | string | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.status_label | string | 真实调用返回字段 |
| data.invoice | null | 真实调用返回字段 |
| data.paid_at | null | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 6,
        "payment_no": "PAY20260328111639332DBPDJDKB",
        "trade_no": "",
        "gateway": "alipay",
        "gateway_key": "alipay",
        "gateway_label": "支付宝",
        "amount": "50.00",
        "status": 2,
        "status_label": "失败",
        "invoice": null,
        "paid_at": null,
        "created_at": "2026-03-28 03:16:39"
    },
    "timestamp": 1783240527
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:27  
· 响应状态码：200  
· 调用方式：GET /api/client/payments/{id}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\PaymentController@show`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器/服务/资源可静态确认 data 字段`  
· 中间件：`api, auth:sanctum, ensure.client`
