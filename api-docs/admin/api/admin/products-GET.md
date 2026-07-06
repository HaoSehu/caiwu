# products

**请求方法**：GET  
**请求路径**：`/api/admin/products`  
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
| page_size | integer | 否 | 查询参数；控制器通过 `$request->input()` 读取；未发现 FormRequest 明确规则 |

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
| data.list | array | 分页列表数据 |
| data.list.id | integer | 真实调用返回字段 |
| data.list.effective_product_group_name | string | 真实调用返回字段 |
| data.list.effective_product_group_parent_name | string | 真实调用返回字段 |
| data.list.effective_product_group_full_name | string | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.display_name | string | 真实调用返回字段 |
| data.list.custom_display_name | string | 真实调用返回字段 |
| data.list.product_spec_display | string | 真实调用返回字段 |
| data.list.product_display_name | string | 真实调用返回字段 |
| data.list.cpu_memory_display | string | 真实调用返回字段 |
| data.list.combined_display_name | string | 真实调用返回字段 |
| data.list.product_type | string | 真实调用返回字段 |
| data.list.type | string | 真实调用返回字段 |
| data.list.type_label | string | 真实调用返回字段 |
| data.list.first_product_group_id | integer | 真实调用返回字段 |
| data.list.first_product_group_code | string | 真实调用返回字段 |
| data.list.first_product_group_name | string | 真实调用返回字段 |
| data.list.second_product_group_id | integer | 真实调用返回字段 |
| data.list.second_product_group_name | string | 真实调用返回字段 |
| data.list.second_product_group_parent_id | integer | 真实调用返回字段 |
| data.list.second_product_group_parent_name | string | 真实调用返回字段 |
| data.list.third_product_group_id | integer | 真实调用返回字段 |
| data.list.third_product_group_name | string | 真实调用返回字段 |
| data.list.effective_product_group_id | integer | 真实调用返回字段 |
| data.list.effective_product_group_level | integer | 真实调用返回字段 |
| data.list.service_type_code | string | 真实调用返回字段 |
| data.list.remark | string | 真实调用返回字段 |
| data.list.primary_price | object | 真实调用返回字段 |
| data.list.primary_price.cycle | string | 真实调用返回字段 |
| data.list.primary_price.amount | string | 真实调用返回字段 |
| data.list.monthly_price | string | 真实调用返回字段 |
| data.list.primary_cycle | string | 真实调用返回字段 |
| data.list.stock | integer | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.is_deleted | boolean | 真实调用返回字段 |
| data.list.lifecycle_status | string | 真实调用返回字段 |
| data.list.deleted_at | null | 真实调用返回字段 |
| data.list.sort_order | integer | 真实调用返回字段 |
| data.list.auto_setup | integer | 真实调用返回字段 |
| data.list.provision_hostname | object | 真实调用返回字段 |
| data.list.provision_hostname.mode | string | 真实调用返回字段 |
| data.list.provision_hostname.value | string | 真实调用返回字段 |
| data.list.provision_hostname.length | integer | 真实调用返回字段 |
| data.list.provision_hostname.is_customized | boolean | 真实调用返回字段 |
| data.list.provision_hostname.label | string | 真实调用返回字段 |
| data.list.provision_hostname_mode | string | 真实调用返回字段 |
| data.list.provision_hostname_summary | string | 真实调用返回字段 |
| data.list.upstream_binding | object | 真实调用返回字段 |
| data.list.upstream_binding.provider_key | string | 真实调用返回字段 |
| data.list.upstream_binding.provider_label | string | 真实调用返回字段 |
| data.list.upstream_binding.supplier_id | integer | 真实调用返回字段 |
| data.list.upstream_binding.upstream_product_id | string | 真实调用返回字段 |
| data.list.orders_count | integer | 真实调用返回字段 |
| data.list.total_services_count | integer | 真实调用返回字段 |
| data.list.services_count | integer | 真实调用返回字段 |
| data.list.active_services_count | integer | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.total | integer | 总条数 |
| data.page | integer | 当前页码 |
| data.page_size | integer | 每页数量 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "id": 149,
                "effective_product_group_name": "高宽",
                "effective_product_group_parent_name": "美国",
                "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                "name": "32vcpu-64gib",
                "display_name": "32vcpu-64gib",
                "custom_display_name": "",
                "product_spec_display": "32vcpu-64gib",
                "product_display_name": "32vcpu-64gib",
                "cpu_memory_display": "32 vCPU 64G",
                "combined_display_name": "gscs-32vcpu-64gib",
                "product_type": "vps",
                "type": "vps",
                "type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 1,
                "second_product_group_name": "美国",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": 20,
                "third_product_group_name": "高宽",
                "effective_product_group_id": 20,
                "effective_product_group_level": 3,
                "service_type_code": "vps",
                "remark": "",
                "primary_price": {
                    "cycle": "monthly",
                    "amount": "781.00"
                },
                "monthly_price": "781.00",
                "primary_cycle": "monthly",
                "stock": -1,
                "status": 1,
                "is_deleted": false,
                "lifecycle_status": "active",
                "deleted_at": null,
                "sort_order": 0,
                "auto_setup": 1,
                "provision_hostname": {
                    "mode": "system",
                    "value": "",
                    "length": 12,
                    "is_customized": false,
                    "label": "跟随上游"
                },
                "provision_hostname_mode": "system",
                "provision_hostname_summary": "跟随上游",
                "upstream_binding": {
                    "provider_key": "mofang_finance_api",
                    "provider_label": "魔方财务接口",
                    "supplier_id": 2,
                    "upstream_product_id": "947"
                },
                "orders_count": 0,
                "total_services_count": 0,
                "services_count": 0,
                "active_services_count": 0,
                "updated_at": "2026-07-05 12:17:37"
            }
        ],
        "total": 126,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240513
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:14  
· 响应状态码：200  
· 调用方式：GET /api/admin/products  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ProductController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.list`
