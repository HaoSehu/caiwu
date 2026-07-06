# reorder

**请求方法**：POST  
**请求路径**：`/api/admin/products/reorder`  
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
| product_id | integer | 是 | 请求体参数；校验规则：required\|integer\|min:1\|exists:products,id；来源：ReorderRequest |
| target_first_product_group_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：ReorderRequest |
| target_second_product_group_id | integer | 是 | 请求体参数；校验规则：required_without:target_third_product_group_id\|integer\|min:1\|exists:second_product_groups,id；来源：ReorderRequest |
| target_third_product_group_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1\|exists:third_product_groups,id；来源：ReorderRequest |
| reference_product_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1\|exists:products,id；来源：ReorderRequest |
| position | string | 是 | 请求体参数；校验规则：required\|string\|in:before,after,append；来源：ReorderRequest |

### 请求示例（完整 JSON）
```json
{
    "product_id": 1,
    "target_first_product_group_id": 1,
    "target_second_product_group_id": 1,
    "target_third_product_group_id": 1,
    "reference_product_id": 1,
    "position": "before"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.first_product_group_id | integer | 业务字段；由源码静态提取 |
| data.second_product_group_id | integer | 业务字段；由源码静态提取 |
| data.third_product_group_id | integer | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "商品位置已更新",
    "data": {
        "first_product_group_id": 1,
        "second_product_group_id": 1,
        "third_product_group_id": 1
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
· 控制器动作：`App\Http\Controllers\Admin\ProductController@reorder`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.manage`
