# member-levels

**请求方法**：POST  
**请求路径**：`/api/admin/member-levels`  
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
| name | string | 是 | 请求体参数；校验规则：required\|string\|max:50；来源：SaveRequest |
| code | string | 否 | 请求体参数；校验规则：nullable\|string\|max:30；来源：SaveRequest |
| sales_amount_min | number | 是 | 请求体参数；校验规则：required\|numeric\|min:0；来源：SaveRequest |
| sales_amount_max | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0；来源：SaveRequest |
| reward_rate | number | 是 | 请求体参数；校验规则：required\|numeric\|min:0\|max:100；来源：SaveRequest |
| status | integer | 否 | 请求体参数；校验规则：nullable\|integer\|in:0,1；来源：SaveRequest |
| sort_order | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:0\|max:999999；来源：SaveRequest |
| remark | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：SaveRequest |

### 请求示例（完整 JSON）
```json
{
    "name": "string",
    "code": "123456",
    "sales_amount_min": "10.00",
    "sales_amount_max": "10.00",
    "reward_rate": "10.00",
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
| data.id | integer | 业务字段；由源码静态提取 |
| data.name | string | 业务字段；由源码静态提取 |
| data.code | string | 业务字段；由源码静态提取 |
| data.sales_amount_min | string(decimal) | 业务字段；由源码静态提取 |
| data.sales_amount_max | string(decimal) | 业务字段；由源码静态提取 |
| data.reward_rate | string | 业务字段；由源码静态提取 |
| data.status | integer | 业务字段；由源码静态提取 |
| data.sort_order | string | 业务字段；由源码静态提取 |
| data.remark | string | 业务字段；由源码静态提取 |
| data.created_at | string(datetime) | 业务字段；由源码静态提取 |
| data.updated_at | string(datetime) | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "等级创建成功",
    "data": {
        "id": 1,
        "name": "string",
        "code": "string",
        "sales_amount_min": "0.00",
        "sales_amount_max": "0.00",
        "reward_rate": "string",
        "status": [],
        "sort_order": "string",
        "remark": "string",
        "created_at": "2026-07-05 12:00:00",
        "updated_at": "2026-07-05 12:00:00"
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
· 控制器动作：`App\Http\Controllers\Admin\MemberLevelController@store`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:member_level.manage`
