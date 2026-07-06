# quote

**请求方法**：POST  
**请求路径**：`/api/site/products/{productId}/quote`  
**调试状态**：⚠️ 异常

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |
| X-Request-Id | {trace_id} | 否 | 请求追踪 ID；控制器读取该请求头 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| productId | integer|string | 是 | 路径参数；在售商品 ID |
| billing_cycle | string | 是 | 请求体参数；必须是商品 pricing 中存在的周期 |
| config | object | 否 | 请求体参数；商品配置项 |
| quantity | integer | 否 | 请求体参数；购买数量，1-10 |
| user_coupon_id | integer | 否 | 请求体参数；用户优惠券 ID |

### 请求示例（完整 JSON）
```json
{
    "billing_cycle": "monthly",
    "config": {},
    "quantity": 1
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.product_id | integer | 商品 ID |
| data.billing_cycle | string | 计费周期 |
| data.quantity | integer | 购买数量 |
| data.amount | string | 单价或基础金额 |
| data.subtotal_amount | string | 优惠前金额 |
| data.discount_amount | string | 优惠金额 |
| data.total_amount | string | 最终应付金额 |
| data.config | object | 归一化配置 |
| data.coupon | object|null | 已选优惠券预览 |
| data.available_coupons | array | 可用优惠券列表 |
| data.quote_token | string | 报价令牌；下单时用于校验报价一致性 |
| data.expires_at | string | 报价令牌过期时间 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "product_id": 1,
        "billing_cycle": "monthly",
        "quantity": 1,
        "amount": "100.00",
        "subtotal_amount": "100.00",
        "discount_amount": "0.00",
        "total_amount": "100.00",
        "config": {},
        "coupon": null,
        "available_coupons": [],
        "quote_token": "***已脱敏***",
        "expires_at": "2026-07-05 16:10:00"
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:43  
· 响应状态码：422  
· 调用方式：POST /api/site/products/{productId}/quote  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是样例 `billing_cycle` 与商品定价周期不匹配；源码要求传入商品 pricing 中存在的周期。

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteProductController@quote`  
· 请求校验：`App\Http\Requests\Site\QuoteProductRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, throttle:60,1`
