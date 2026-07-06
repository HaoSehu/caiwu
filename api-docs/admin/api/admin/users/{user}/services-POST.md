# 管理员手动新增服务

**请求方法**：POST  
**请求路径**：`/api/admin/users/{user}/services`  
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
| product_id | integer | 是 | 请求体参数；校验规则：required\|integer\|exists:products,id；来源：StoreUserServiceRequest |
| billing_cycle | string | 是 | 请求体参数；校验规则：required\|string\|max:30；来源：StoreUserServiceRequest |
| source_type | string | 是 | 请求体参数；校验规则：required\|in:manual,upstream；来源：StoreUserServiceRequest |
| name | string | 否 | 请求体参数；校验规则：nullable\|string\|max:200；来源：StoreUserServiceRequest |
| domain | string | 否 | 请求体参数；校验规则：nullable\|string\|max:200；来源：StoreUserServiceRequest |
| amount | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0\|max:99999999.99；来源：StoreUserServiceRequest |
| status | string | 是 | 请求体参数；校验规则：required\|in:0,1,2,3,4；来源：StoreUserServiceRequest |
| expires_at | string(datetime) | 否 | 请求体参数；校验规则：nullable\|date；来源：StoreUserServiceRequest |
| auto_renew | string | 是 | 请求体参数；校验规则：required\|in:0,1；来源：StoreUserServiceRequest |
| dedicated_ip | string | 否 | 请求体参数；校验规则：nullable\|string\|max:100；来源：StoreUserServiceRequest |
| internal_ip | string | 否 | 请求体参数；校验规则：nullable\|string\|max:100；来源：StoreUserServiceRequest |
| port | integer | 否 | 请求体参数；校验规则：nullable\|integer\|between:1,65535；来源：StoreUserServiceRequest |
| username | string | 否 | 请求体参数；校验规则：nullable\|string\|max:100；来源：StoreUserServiceRequest |
| password | string | 否 | 请求体参数；校验规则：nullable\|string\|max:200；来源：StoreUserServiceRequest |
| upstream_host_id | integer | 是 | 请求体参数；校验规则：nullable\|required_if:source_type,upstream\|integer\|min:1；来源：StoreUserServiceRequest |
| upstream_status | string | 否 | 请求体参数；校验规则：nullable\|string\|max:50；来源：StoreUserServiceRequest |
| os | string | 否 | 请求体参数；校验规则：nullable\|string\|max:100；来源：StoreUserServiceRequest |
| remark | string | 否 | 请求体参数；校验规则：nullable\|string\|max:200；来源：StoreUserServiceRequest |

### 请求示例（完整 JSON）
```json
{
    "product_id": 1,
    "billing_cycle": "string",
    "source_type": "manual",
    "name": "string",
    "domain": "string",
    "amount": "10.00",
    "status": "1",
    "expires_at": "2026-07-05",
    "auto_renew": "1",
    "dedicated_ip": "string",
    "internal_ip": "string",
    "port": 1,
    "username": "string",
    "password": "password123",
    "upstream_host_id": 1,
    "upstream_status": "string",
    "os": "string",
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
| data.operator_id | integer | 业务字段；由源码静态提取 |
| data.operator_name | string | 业务字段；由源码静态提取 |
| data.trace_id | integer | 业务字段；由源码静态提取 |
| data.ip_address | array | 业务字段；由源码静态提取 |
| data.order_no | string | 业务字段；由源码静态提取 |
| data.invoice_id | integer | 业务字段；由源码静态提取 |
| data.invoice_no | string | 业务字段；由源码静态提取 |
| data.service_id | integer | 业务字段；由源码静态提取 |
| data.source_type | string | 业务字段；由源码静态提取 |
| data.service_status | array | 业务字段；由源码静态提取 |
| data.billing_cycle | string | 业务字段；由源码静态提取 |
| data.amount | string(decimal) | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "服务创建成功",
    "data": {
        "operator_id": 1,
        "operator_name": "string",
        "trace_id": 1,
        "ip_address": [],
        "order_no": "string",
        "invoice_id": 1,
        "invoice_no": "string",
        "service_id": 1,
        "source_type": "string",
        "service_status": [],
        "billing_cycle": "string",
        "amount": "0.00"
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
· 控制器动作：`App\Http\Controllers\Admin\UserController@storeService`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.manage`
