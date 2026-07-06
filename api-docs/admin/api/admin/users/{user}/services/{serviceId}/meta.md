# 更新用户服务业务信息

**请求方法**：PUT  
**请求路径**：`/api/admin/users/{user}/services/{serviceId}/meta`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |
| X-Request-Id | {trace_id} | 否 | 请求追踪 ID；控制器读取该请求头 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| user | integer\|string | 是 | 路径参数；来自路由占位 `{user}` |
| serviceId | integer\|string | 是 | 路径参数；来自路由占位 `{serviceId}` |
| amount | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0\|max:99999999.99；来源：UpdateUserServiceMetaRequest |
| supplier_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：UpdateUserServiceMetaRequest |
| upstream_product_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：UpdateUserServiceMetaRequest |
| upstream_host_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：UpdateUserServiceMetaRequest |
| service_name | string | 否 | 请求体参数；校验规则：nullable\|string\|max:120；来源：UpdateUserServiceMetaRequest |
| custom_hostname | string | 否 | 请求体参数；校验规则：nullable\|string\|max:200；来源：UpdateUserServiceMetaRequest |
| locked_pricing | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateUserServiceMetaRequest |
| locked_pricing.monthly | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateUserServiceMetaRequest |
| locked_pricing.monthly.enabled | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：UpdateUserServiceMetaRequest |
| locked_pricing.monthly.manual_amount | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0\|max:99999999.99；来源：UpdateUserServiceMetaRequest |
| locked_pricing.quarterly | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateUserServiceMetaRequest |
| locked_pricing.quarterly.enabled | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：UpdateUserServiceMetaRequest |
| locked_pricing.quarterly.manual_amount | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0\|max:99999999.99；来源：UpdateUserServiceMetaRequest |
| locked_pricing.semiannually | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateUserServiceMetaRequest |
| locked_pricing.semiannually.enabled | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：UpdateUserServiceMetaRequest |
| locked_pricing.semiannually.manual_amount | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0\|max:99999999.99；来源：UpdateUserServiceMetaRequest |
| locked_pricing.annually | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateUserServiceMetaRequest |
| locked_pricing.annually.enabled | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：UpdateUserServiceMetaRequest |
| locked_pricing.annually.manual_amount | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0\|max:99999999.99；来源：UpdateUserServiceMetaRequest |
| clear_locked_pricing | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：UpdateUserServiceMetaRequest |
| clear_custom_hostname | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：UpdateUserServiceMetaRequest |

### 请求示例（完整 JSON）
```json
{
    "amount": "10.00",
    "supplier_id": 1,
    "upstream_product_id": 1,
    "upstream_host_id": 1,
    "service_name": "string",
    "custom_hostname": "string",
    "locked_pricing": [],
    "locked_pricing.monthly": [],
    "locked_pricing.monthly.enabled": true,
    "locked_pricing.monthly.manual_amount": "10.00",
    "locked_pricing.quarterly": [],
    "locked_pricing.quarterly.enabled": true,
    "locked_pricing.quarterly.manual_amount": "10.00",
    "locked_pricing.semiannually": [],
    "locked_pricing.semiannually.enabled": true,
    "locked_pricing.semiannually.manual_amount": "10.00",
    "locked_pricing.annually": [],
    "locked_pricing.annually.enabled": true,
    "locked_pricing.annually.manual_amount": "10.00",
    "clear_locked_pricing": true,
    "clear_custom_hostname": true
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.operator_id | integer | 业务字段；由源码静态提取 |
| data.operator_name | string | 业务字段；由源码静态提取 |
| data.trace_id | integer | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "服务信息已更新",
    "data": {
        "operator_id": 1,
        "operator_name": "string",
        "trace_id": 1
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
· 控制器动作：`App\Http\Controllers\Admin\UserController@updateServiceMeta`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.manage`
