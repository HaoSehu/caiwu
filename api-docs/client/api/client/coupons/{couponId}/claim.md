# claim

**请求方法**：POST  
**请求路径**：`/api/client/coupons/{couponId}/claim`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| couponId | integer\|string | 是 | 路径参数；来自路由占位 `{couponId}` |
| page | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：ListCouponsRequest |
| page_size | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1\|max:50；来源：ListCouponsRequest |
| status | string | 否 | 请求体参数；校验规则：nullable\|in:all,available,used_up,expired；来源：ListCouponsRequest |
| keyword | string | 否 | 请求体参数；校验规则：nullable\|string\|max:100；来源：ListCouponsRequest |

### 请求示例（完整 JSON）
```json
{
    "page": 1,
    "page_size": 1,
    "status": "all",
    "keyword": "string"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.coupon_id | integer | 业务字段；由源码静态提取 |
| data.user_id | integer | 业务字段；由源码静态提取 |
| data.receive_type | string | 业务字段；由源码静态提取 |
| data.status | integer | 业务字段；由源码静态提取 |
| data.claimed_at | string(datetime) | 业务字段；由源码静态提取 |
| data.id | integer | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "优惠券领取成功",
    "data": {
        "coupon_id": 1,
        "user_id": 1,
        "receive_type": "string",
        "status": [],
        "claimed_at": "2026-07-05 12:00:00",
        "id": 1
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
· 控制器动作：`App\Http\Controllers\Client\CouponController@claim`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.client, throttle:6,1,client-coupons-claim`
