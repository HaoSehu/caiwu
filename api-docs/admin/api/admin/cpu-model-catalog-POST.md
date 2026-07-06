# cpu-model-catalog

**请求方法**：POST  
**请求路径**：`/api/admin/cpu-model-catalog`  
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
| list | array | 是 | 请求体参数；校验规则：required\|array；来源：UpdateRequest |
| list.*.id | string | 否 | 请求体参数；校验规则：nullable\|string\|max:80；来源：UpdateRequest |
| list.*.value | string | 否 | 请求体参数；校验规则：nullable\|string\|max:60；来源：UpdateRequest |
| list.*.name | string | 是 | 请求体参数；校验规则：required\|string\|max:80；来源：UpdateRequest |
| list.*.models | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateRequest |
| list.*.models.*.id | string | 否 | 请求体参数；校验规则：nullable\|string\|max:80；来源：UpdateRequest |
| list.*.models.*.value | string | 否 | 请求体参数；校验规则：nullable\|string\|max:60；来源：UpdateRequest |
| list.*.models.*.name | string | 是 | 请求体参数；校验规则：required\|string\|max:80；来源：UpdateRequest |
| list.*.models.*.base_frequency | string | 否 | 请求体参数；校验规则：nullable\|string\|max:40；来源：UpdateRequest |
| list.*.models.*.turbo_frequency | string | 否 | 请求体参数；校验规则：nullable\|string\|max:40；来源：UpdateRequest |
| list.*.models.*.bindings | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateRequest |
| list.*.models.*.bindings.*.product_id | integer | 是 | 请求体参数；校验规则：required\|integer\|min:1；来源：UpdateRequest |
| list.*.models.*.bindings.*.category_full_name | string | 否 | 请求体参数；校验规则：nullable\|string\|max:160；来源：UpdateRequest |
| list.*.models.*.bindings.*.primary_price | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateRequest |
| list.*.models.*.bindings.*.primary_price.cycle | string | 否 | 请求体参数；校验规则：nullable\|string\|max:40；来源：UpdateRequest |
| list.*.models.*.bindings.*.primary_price.amount | string | 否 | 请求体参数；校验规则：nullable\|string\|max:40；来源：UpdateRequest |
| list.*.models.*.bindings.*.status | integer | 否 | 请求体参数；校验规则：nullable\|integer\|in:0,1；来源：UpdateRequest |

### 请求示例（完整 JSON）
```json
{
    "list": [],
    "list.*.id": "string",
    "list.*.value": "string",
    "list.*.name": "string",
    "list.*.models": [],
    "list.*.models.*.id": "string",
    "list.*.models.*.value": "string",
    "list.*.models.*.name": "string",
    "list.*.models.*.base_frequency": "string",
    "list.*.models.*.turbo_frequency": "string",
    "list.*.models.*.bindings": [],
    "list.*.models.*.bindings.*.product_id": 1,
    "list.*.models.*.bindings.*.category_full_name": "string",
    "list.*.models.*.bindings.*.primary_price": [],
    "list.*.models.*.bindings.*.primary_price.cycle": "string",
    "list.*.models.*.bindings.*.primary_price.amount": "string",
    "list.*.models.*.bindings.*.status": "1"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.list | array | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "CPU 型号目录已更新",
    "data": {
        "list": []
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
· 控制器动作：`App\Http\Controllers\Admin\CpuModelCatalogController@update`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.manage`
