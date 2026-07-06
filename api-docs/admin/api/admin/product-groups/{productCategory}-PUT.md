# {productCategory}

**请求方法**：PUT  
**请求路径**：`/api/admin/product-groups/{productCategory}`  
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
| productCategory | integer\|string | 是 | 路径参数；来自路由占位 `{productCategory}` |
| effective_product_group_level | integer | 是 | 请求体参数；校验规则：required\|integer\|in:"1","2","3"；来源：UpdateRequest |
| service_type_code | string | 否 | 请求体参数；校验规则：nullable\|string\|max:50；来源：UpdateRequest |
| first_product_group_code | string | 否 | 请求体参数；校验规则：nullable\|string\|max:50；来源：UpdateRequest |
| first_product_group_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：UpdateRequest |
| second_product_group_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：UpdateRequest |
| name | string | 否 | 请求体参数；校验规则：sometimes\|string\|max:100；来源：UpdateRequest |
| description | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateRequest |
| slogan | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateRequest |
| slug | string | 否 | 请求体参数；校验规则：nullable\|string\|max:120；来源：UpdateRequest |
| banner_image | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateRequest |
| sort_order | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:0\|max:999999；来源：UpdateRequest |
| is_visible | string | 否 | 请求体参数；校验规则：nullable\|in:"0","1","0","1"；来源：UpdateRequest |
| is_system | string | 否 | 请求体参数；校验规则：nullable\|in:"0","1","0","1"；来源：UpdateRequest |

### 请求示例（完整 JSON）
```json
{
    "effective_product_group_level": "\"1\"",
    "service_type_code": "123456",
    "first_product_group_code": "123456",
    "first_product_group_id": 1,
    "second_product_group_id": 1,
    "name": "string",
    "description": "string",
    "slogan": "string",
    "slug": "string",
    "banner_image": "string",
    "sort_order": 1,
    "is_visible": "\"0\"",
    "is_system": "\"0\""
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
    "message": "分类已更新",
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
· 控制器动作：`App\Http\Controllers\Admin\ProductCategoryController@update`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.manage`
