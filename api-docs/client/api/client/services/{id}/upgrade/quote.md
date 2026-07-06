# quote

**请求方法**：POST  
**请求路径**：`/api/client/services/{id}/upgrade/quote`  
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
| id | integer|string | 是 | 路径参数；当前用户服务 ID |
| product_id | integer | 是 | 请求体参数；目标商品 ID |
| billing_cycle | string | 是 | 请求体参数；目标计费周期 |
| promo_code | string | 否 | 请求体参数；优惠码 |

### 请求示例（完整 JSON）
```json
{
    "product_id": 2,
    "billing_cycle": "monthly",
    "promo_code": ""
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.product_id | integer | 目标商品 ID |
| data.billing_cycle | string | 计费周期 |
| data.amount | string | 应付差价/金额 |
| data.discount_amount | string|null | 优惠金额 |
| data.total_amount | string | 最终应付金额 |
| data.description | string|null | 说明 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "product_id": 2,
        "billing_cycle": "monthly",
        "amount": "20.00",
        "discount_amount": "0.00",
        "total_amount": "20.00",
        "description": "产品升降级报价"
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:39  
· 响应状态码：422  
· 调用方式：POST /api/client/services/{id}/upgrade/quote  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是样例服务/目标商品无法完成升降级预览；源码正常返回升降级报价。

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@quoteHostUpgrade`  
· 请求校验：`App\Http\Requests\Client\Service\QuoteHostUpgradeRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client, throttle:12,1,client-service-host-upgrade-quote`
