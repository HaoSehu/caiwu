# quote

**请求方法**：POST  
**请求路径**：`/api/client/services/{id}/traffic-packages/quote`  
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
| target_value | integer | 是 | 请求体参数；目标流量包值，最小 1 |

### 请求示例（完整 JSON）
```json
{
    "target_value": 100
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.target_value | integer | 购买流量包目标值 |
| data.name | string | 流量包名称 |
| data.amount | string | 应付金额 |
| data.currency | string|null | 币种 |
| data.billing_cycle | string | 计费周期，通常为 one_time |
| data.description | string|null | 说明 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "target_value": 100,
        "name": "100G 流量包",
        "amount": "10.00",
        "currency": "CNY",
        "billing_cycle": "one_time",
        "description": "一次性流量包"
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:38  
· 响应状态码：422  
· 调用方式：POST /api/client/services/{id}/traffic-packages/quote  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是样例服务所属商品分类未配置可售流量包；源码正常返回流量包报价。

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@quoteTrafficPackage`  
· 请求校验：`App\Http\Requests\Client\Service\QuoteTrafficPackageRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client, throttle:12,1,client-service-traffic-package-quote`
