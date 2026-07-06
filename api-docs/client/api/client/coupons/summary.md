# summary

**请求方法**：GET  
**请求路径**：`/api/client/coupons/summary`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListCouponsRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:50；来源：ListCouponsRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|in:all,available,used_up,expired；来源：ListCouponsRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：ListCouponsRequest |

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
| data.total | integer | 总条数 |
| data.available | integer | 真实调用返回字段 |
| data.used_up | integer | 真实调用返回字段 |
| data.expired | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "total": 2,
        "available": 0,
        "used_up": 1,
        "expired": 1
    },
    "timestamp": 1783240525
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:25  
· 响应状态码：200  
· 调用方式：GET /api/client/coupons/summary  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\CouponController@summary`  
· 请求校验：`App\Http\Requests\Client\Coupon\ListCouponsRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；CouponService::summaryForUser() 服务返回数组字段`  
· 中间件：`api, auth:sanctum, ensure.client`
