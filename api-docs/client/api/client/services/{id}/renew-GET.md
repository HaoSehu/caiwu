# renew

**请求方法**：GET  
**请求路径**：`/api/client/services/{id}/renew`  
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
| billing_cycle | string | 否 | 查询参数；校验规则：nullable\|string\|max:30；来源：RenewPreviewRequest |
| user_coupon_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：RenewPreviewRequest |

### 请求示例（完整 JSON）
```json
{
    "billing_cycle": "string",
    "user_coupon_id": 1
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.service_id | integer | 真实调用返回字段 |
| data.service_name | string | 真实调用返回字段 |
| data.billing_cycle | string | 真实调用返回字段 |
| data.billing_cycle_label | string | 真实调用返回字段 |
| data.expires_at | string | 真实调用返回字段 |
| data.renew_price | string | 真实调用返回字段 |
| data.auto_renew | integer | 真实调用返回字段 |
| data.supports_upstream | boolean | 真实调用返回字段 |
| data.upstream_host_id | integer | 真实调用返回字段 |
| data.cycles | array | 真实调用返回字段 |
| data.cycles.billing_cycle | string | 真实调用返回字段 |
| data.cycles.billing_cycle_label | string | 真实调用返回字段 |
| data.cycles.amount | string | 真实调用返回字段 |
| data.cycles.upstream_amount | string | 真实调用返回字段 |
| data.cycles.original_amount | string | 真实调用返回字段 |
| data.cycles.discount_amount | string | 真实调用返回字段 |
| data.default_cycle | string | 真实调用返回字段 |
| data.available_coupons | array | 真实调用返回字段 |
| data.selected_user_coupon_id | integer | 真实调用返回字段 |
| data.selected_coupon | null | 真实调用返回字段 |
| data.has_locked_pricing | boolean | 真实调用返回字段 |
| data.has_custom_renew_pricing | boolean | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "service_id": 88,
        "service_name": "美国1区精品网 2H2G",
        "billing_cycle": "monthly",
        "billing_cycle_label": "月付",
        "expires_at": "2026-04-19 13:30:03",
        "renew_price": "20.00",
        "auto_renew": 0,
        "supports_upstream": true,
        "upstream_host_id": 71331,
        "cycles": [
            {
                "billing_cycle": "monthly",
                "billing_cycle_label": "月付",
                "amount": "20.00",
                "upstream_amount": "",
                "original_amount": "20.00",
                "discount_amount": "0.00"
            },
            {
                "billing_cycle": "quarterly",
                "billing_cycle_label": "季付",
                "amount": "60.00",
                "upstream_amount": "",
                "original_amount": "60.00",
                "discount_amount": "0.00"
            },
            {
                "billing_cycle": "semiannually",
                "billing_cycle_label": "半年付",
                "amount": "120.00",
                "upstream_amount": "",
                "original_amount": "120.00",
                "discount_amount": "0.00"
            },
            {
                "billing_cycle": "annually",
                "billing_cycle_label": "年付",
                "amount": "240.00",
                "upstream_amount": "",
                "original_amount": "240.00",
                "discount_amount": "0.00"
            }
        ],
        "default_cycle": "string",
        "available_coupons": [],
        "selected_user_coupon_id": 0,
        "selected_coupon": null,
        "has_locked_pricing": false,
        "has_custom_renew_pricing": false
    },
    "timestamp": 1783240534
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:34  
· 响应状态码：200  
· 调用方式：GET /api/client/services/{id}/renew  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@renewPreview`  
· 请求校验：`App\Http\Requests\Client\Service\RenewPreviewRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器/服务/资源可静态确认 data 字段`  
· 中间件：`api, auth:sanctum, ensure.client`
