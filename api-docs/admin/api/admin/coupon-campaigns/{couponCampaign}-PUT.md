# {couponCampaign}

**请求方法**：PUT  
**请求路径**：`/api/admin/coupon-campaigns/{couponCampaign}`  
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
| couponCampaign | string | 是 | 路径参数；来自路由占位 `{couponCampaign}` |
| name | string | 是 | 请求体参数；校验规则：required\|string\|max:120；来源：UpdateRequest |
| description | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateRequest |
| weekdays | array | 是 | 请求体参数；校验规则：required\|array\|min:1；来源：UpdateRequest |
| weekdays.* | integer | 否 | 请求体参数；校验规则：integer\|between:0,6；来源：UpdateRequest |
| trigger_time | string | 是 | 请求体参数；校验规则：required\|string\|regex:/^\d{2}:\d{2}(:\d{2})?$/；来源：UpdateRequest |
| issue_quantity | integer | 是 | 请求体参数；校验规则：required\|integer\|min:1；来源：UpdateRequest |
| valid_duration_hours | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1\|max:87600；来源：UpdateRequest |
| discount_type | string | 是 | 请求体参数；校验规则：required\|in:"fixed","percentage"；来源：UpdateRequest |
| discount_scope | string | 是 | 请求体参数；校验规则：required\|in:"first_month","recurring","renew"；来源：UpdateRequest |
| discount_value | number | 是 | 请求体参数；校验规则：required\|numeric\|min:0.01；来源：UpdateRequest |
| min_amount | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0；来源：UpdateRequest |
| max_discount_amount | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0；来源：UpdateRequest |
| billing_cycles | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateRequest |
| billing_cycles.* | string | 否 | 请求体参数；校验规则：string\|max:30；来源：UpdateRequest |
| product_ids | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateRequest |
| product_ids.* | integer | 否 | 请求体参数；校验规则：integer\|exists:products,id；来源：UpdateRequest |
| first_order_only | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：UpdateRequest |
| per_user_limit | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：UpdateRequest |
| status | string | 否 | 请求体参数；校验规则：nullable\|in:0,1；来源：UpdateRequest |
| sort_order | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:0\|max:999999；来源：UpdateRequest |
| remark | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateRequest |

### 请求示例（完整 JSON）
```json
{
    "name": "string",
    "description": "string",
    "weekdays": [],
    "weekdays.*": 1,
    "trigger_time": "string",
    "issue_quantity": 1,
    "valid_duration_hours": 1,
    "discount_type": "\"fixed\"",
    "discount_scope": "\"first_month\"",
    "discount_value": "10.00",
    "min_amount": "10.00",
    "max_discount_amount": "10.00",
    "billing_cycles": [],
    "billing_cycles.*": "string",
    "product_ids": [],
    "product_ids.*": 1,
    "first_order_only": true,
    "per_user_limit": 1,
    "status": "1",
    "sort_order": 1,
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
| data | object\|array\|null | 待调试后补充；未能从源码静态确认业务字段 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "活动已更新",
    "data": "待调试后补充",
    "timestamp": 1760000000
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：接口为写操作、删除操作、支付/退款/开通/服务控制/通知发送/上游动作之一，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\CouponCampaignController@update`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.manage`
