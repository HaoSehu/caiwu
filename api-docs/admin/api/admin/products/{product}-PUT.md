# {product}

**请求方法**：PUT  
**请求路径**：`/api/admin/products/{product}`  
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
| product | integer\|string | 是 | 路径参数；来自路由占位 `{product}` |
| name | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateRequest |
| display_name | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateRequest |
| custom_display_name | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateRequest |
| product_type | string | 否 | 请求体参数；校验规则：nullable\|string\|max:50；来源：UpdateRequest |
| service_type_code | string | 否 | 请求体参数；校验规则：nullable\|string\|max:50；来源：UpdateRequest |
| first_product_group_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：UpdateRequest |
| second_product_group_id | integer | 是 | 请求体参数；校验规则：required\|integer\|min:1；来源：UpdateRequest |
| third_product_group_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:1；来源：UpdateRequest |
| remark | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateRequest |
| pricing | array | 是 | 请求体参数；校验规则：required\|array；来源：UpdateRequest |
| pricing.monthly | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0；来源：UpdateRequest |
| pricing.quarterly | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0；来源：UpdateRequest |
| pricing.semiannually | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0；来源：UpdateRequest |
| pricing.annually | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0；来源：UpdateRequest |
| setup_fee | number | 否 | 请求体参数；校验规则：nullable\|numeric\|min:0；来源：UpdateRequest |
| config_options | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateRequest |
| purchase_requires | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateRequest |
| purchase_requires.require_verification | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：UpdateRequest |
| purchase_requires.require_phone | boolean | 否 | 请求体参数；校验规则：nullable\|boolean；来源：UpdateRequest |
| purchase_requires.provision_hostname | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateRequest |
| purchase_requires.provision_hostname.mode | string | 否 | 请求体参数；校验规则：nullable\|string\|in:"system","fixed","prefix"；来源：UpdateRequest |
| purchase_requires.provision_hostname.value | string | 否 | 请求体参数；校验规则：nullable\|string\|max:200；来源：UpdateRequest |
| purchase_requires.provision_hostname.length | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:4\|max:63；来源：UpdateRequest |
| stock | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:-1；来源：UpdateRequest |
| status | string | 否 | 请求体参数；校验规则：nullable\|in:0,1；来源：UpdateRequest |
| sort_order | integer | 否 | 请求体参数；校验规则：nullable\|integer\|min:0\|max:999999；来源：UpdateRequest |
| auto_setup | string | 否 | 请求体参数；校验规则：nullable\|in:0,1；来源：UpdateRequest |
| upstream_binding | array | 否 | 请求体参数；校验规则：nullable\|array；来源：UpdateRequest |
| upstream_binding.supplier_id | integer | 否 | 请求体参数；校验规则：nullable\|integer\|exists:suppliers,id；来源：UpdateRequest |
| upstream_binding.upstream_product_id | string | 否 | 请求体参数；校验规则：nullable；来源：UpdateRequest |

### 请求示例（完整 JSON）
```json
{
    "name": "string",
    "display_name": "string",
    "custom_display_name": "string",
    "product_type": "string",
    "service_type_code": "123456",
    "first_product_group_id": 1,
    "second_product_group_id": 1,
    "third_product_group_id": 1,
    "remark": "string",
    "pricing": [],
    "pricing.monthly": "10.00",
    "pricing.quarterly": "10.00",
    "pricing.semiannually": "10.00",
    "pricing.annually": "10.00",
    "setup_fee": "10.00",
    "config_options": [],
    "purchase_requires": [],
    "purchase_requires.require_verification": true,
    "purchase_requires.require_phone": true,
    "purchase_requires.provision_hostname": [],
    "purchase_requires.provision_hostname.mode": "\"system\"",
    "purchase_requires.provision_hostname.value": "string",
    "purchase_requires.provision_hostname.length": 1,
    "stock": 1,
    "status": "1",
    "sort_order": 1,
    "auto_setup": "1",
    "upstream_binding": [],
    "upstream_binding.supplier_id": 1,
    "upstream_binding.upstream_product_id": "string"
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
| data.effective_product_group_name | string | 业务字段；由源码静态提取 |
| data.effective_product_group_parent_name | string | 业务字段；由源码静态提取 |
| data.effective_product_group_full_name | string | 业务字段；由源码静态提取 |
| data.name | string | 业务字段；由源码静态提取 |
| data.display_name | string | 业务字段；由源码静态提取 |
| data.custom_display_name | string | 业务字段；由源码静态提取 |
| data.product_spec_display | string | 业务字段；由源码静态提取 |
| data.product_display_name | string | 业务字段；由源码静态提取 |
| data.cpu_memory_display | string | 业务字段；由源码静态提取 |
| data.combined_display_name | string | 业务字段；由源码静态提取 |
| data.product_type | string | 业务字段；由源码静态提取 |
| data.type | string | 业务字段；由源码静态提取 |
| data.type_label | string | 业务字段；由源码静态提取 |
| data.remark | string | 业务字段；由源码静态提取 |
| data.pricing | string | 业务字段；由源码静态提取 |
| data.product_prices | string(decimal) | 业务字段；由源码静态提取 |
| data.primary_price | string(decimal) | 业务字段；由源码静态提取 |
| data.primary_cycle | string | 业务字段；由源码静态提取 |
| data.setup_fee | string(decimal) | 业务字段；由源码静态提取 |
| data.config_options | array | 业务字段；由源码静态提取 |
| data.product_options | array | 业务字段；由源码静态提取 |
| data.purchase_requires | array | 业务字段；由源码静态提取 |
| data.stock | string | 业务字段；由源码静态提取 |
| data.status | integer | 业务字段；由源码静态提取 |
| data.is_deleted | boolean | 业务字段；由源码静态提取 |
| data.lifecycle_status | array | 业务字段；由源码静态提取 |
| data.deleted_at | string(datetime) | 业务字段；由源码静态提取 |
| data.sort_order | string | 业务字段；由源码静态提取 |
| data.auto_setup | string | 业务字段；由源码静态提取 |
| data.provision_hostname | string | 业务字段；由源码静态提取 |
| data.upstream_binding | string | 业务字段；由源码静态提取 |
| data.orders_count | string | 业务字段；由源码静态提取 |
| data.services_count | string | 业务字段；由源码静态提取 |
| data.created_at | string(datetime) | 业务字段；由源码静态提取 |
| data.updated_at | string(datetime) | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "商品更新成功",
    "data": {
        "id": 1,
        "effective_product_group_name": "string",
        "effective_product_group_parent_name": "string",
        "effective_product_group_full_name": "string",
        "name": "string",
        "display_name": "string",
        "custom_display_name": "string",
        "product_spec_display": "string",
        "product_display_name": "string",
        "cpu_memory_display": "string",
        "combined_display_name": "string",
        "product_type": "string",
        "type": "string",
        "type_label": "string",
        "remark": "string",
        "pricing": "string",
        "product_prices": "0.00",
        "primary_price": "0.00",
        "primary_cycle": "string",
        "setup_fee": "0.00",
        "config_options": [],
        "product_options": [],
        "purchase_requires": [],
        "stock": "string",
        "status": [],
        "is_deleted": true,
        "lifecycle_status": [],
        "deleted_at": "2026-07-05 12:00:00",
        "sort_order": "string",
        "auto_setup": "string",
        "provision_hostname": "string",
        "upstream_binding": "string",
        "orders_count": "string",
        "services_count": "string",
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
· 控制器动作：`App\Http\Controllers\Admin\ProductController@update`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.manage`
