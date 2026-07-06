# product-tree

**请求方法**：GET  
**请求路径**：`/api/admin/coupons/product-tree`  
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
| 无 | - | 否 | 无请求参数 |

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
| data.tree | array | 真实调用返回字段 |
| data.tree.id | string | 真实调用返回字段 |
| data.tree.label | string | 真实调用返回字段 |
| data.tree.node_type | string | 真实调用返回字段 |
| data.tree.first_product_group_id | integer | 真实调用返回字段 |
| data.tree.first_product_group_name | string | 真实调用返回字段 |
| data.tree.service_type_code | string | 真实调用返回字段 |
| data.tree.service_type_label | string | 真实调用返回字段 |
| data.tree.leaf | boolean | 真实调用返回字段 |
| data.tree.disabled | boolean | 真实调用返回字段 |
| data.tree.children | array | 真实调用返回字段 |
| data.tree.children.id | string | 真实调用返回字段 |
| data.tree.children.label | string | 真实调用返回字段 |
| data.tree.children.node_type | string | 真实调用返回字段 |
| data.tree.children.first_product_group_id | integer | 真实调用返回字段 |
| data.tree.children.first_product_group_name | string | 真实调用返回字段 |
| data.tree.children.service_type_code | string | 真实调用返回字段 |
| data.tree.children.service_type_label | string | 真实调用返回字段 |
| data.tree.children.second_product_group_id | integer | 真实调用返回字段 |
| data.tree.children.second_product_group_name | string | 真实调用返回字段 |
| data.tree.children.third_product_group_id | null | 真实调用返回字段 |
| data.tree.children.third_product_group_name | null | 真实调用返回字段 |
| data.tree.children.effective_product_group_id | integer | 真实调用返回字段 |
| data.tree.children.effective_product_group_level | integer | 真实调用返回字段 |
| data.tree.children.effective_product_group_full_name | string | 真实调用返回字段 |
| data.tree.children.leaf | boolean | 真实调用返回字段 |
| data.tree.children.disabled | boolean | 真实调用返回字段 |
| data.tree.children.children | array | 真实调用返回字段 |
| data.tree.children.children.id | string | 真实调用返回字段 |
| data.tree.children.children.label | string | 真实调用返回字段 |
| data.tree.children.children.node_type | string | 真实调用返回字段 |
| data.tree.children.children.first_product_group_id | integer | 真实调用返回字段 |
| data.tree.children.children.first_product_group_name | string | 真实调用返回字段 |
| data.tree.children.children.service_type_code | string | 真实调用返回字段 |
| data.tree.children.children.service_type_label | string | 真实调用返回字段 |
| data.tree.children.children.second_product_group_id | integer | 真实调用返回字段 |
| data.tree.children.children.second_product_group_name | string | 真实调用返回字段 |
| data.tree.children.children.third_product_group_id | integer | 真实调用返回字段 |
| data.tree.children.children.third_product_group_name | string | 真实调用返回字段 |
| data.tree.children.children.effective_product_group_id | integer | 真实调用返回字段 |
| data.tree.children.children.effective_product_group_level | integer | 真实调用返回字段 |
| data.tree.children.children.effective_product_group_full_name | string | 真实调用返回字段 |
| data.tree.children.children.leaf | boolean | 真实调用返回字段 |
| data.tree.children.children.disabled | boolean | 真实调用返回字段 |
| data.tree.children.children.children | array | 真实调用返回字段 |
| data.tree.children.children.children.id | integer | 真实调用返回字段 |
| data.tree.children.children.children.label | string | 真实调用返回字段 |
| data.tree.children.children.children.node_type | string | 真实调用返回字段 |
| data.tree.children.children.children.leaf | boolean | 真实调用返回字段 |
| data.tree.children.children.children.disabled | boolean | 真实调用返回字段 |
| data.tree.children.children.children.product_name | string | 真实调用返回字段 |
| data.tree.children.children.children.display_name | string | 真实调用返回字段 |
| data.tree.children.children.children.product_display_name | string | 真实调用返回字段 |
| data.tree.children.children.children.custom_display_name | string | 真实调用返回字段 |
| data.tree.children.children.children.cpu_memory_display | string | 真实调用返回字段 |
| data.tree.children.children.children.cpu_memory_slug_display | string | 真实调用返回字段 |
| data.tree.children.children.children.product_spec_display | string | 真实调用返回字段 |
| data.tree.children.children.children.combined_display_name | string | 真实调用返回字段 |
| data.tree.children.children.children.effective_product_group_full_name | string | 真实调用返回字段 |
| data.tree.children.children.children.primary_price | object | 真实调用返回字段 |
| data.tree.children.children.children.primary_price.cycle | string | 真实调用返回字段 |
| data.tree.children.children.children.primary_price.amount | string | 真实调用返回字段 |
| data.tree.children.children.children.status | integer | 真实调用返回字段 |
| data.tree.children.children.children.sort_order | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "tree": [
            {
                "id": "first-1",
                "label": "云服务器",
                "node_type": "first_product_group",
                "first_product_group_id": 1,
                "first_product_group_name": "云服务器",
                "service_type_code": "vps",
                "service_type_label": "云服务器",
                "leaf": false,
                "disabled": false,
                "children": [
                    {
                        "id": "group-13",
                        "label": "襄阳",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 13,
                        "second_product_group_name": "襄阳",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 13,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 襄阳",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-15",
                                "label": "高宽",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 13,
                                "second_product_group_name": "襄阳",
                                "third_product_group_id": 15,
                                "third_product_group_name": "高宽",
                                "effective_product_group_id": 15,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 襄阳 / 高宽",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 82,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "48.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 117,
                                        "label": "2vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-4gib",
                                        "display_name": "2vcpu-4gib",
                                        "product_display_name": "2vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 4G",
                                        "cpu_memory_slug_display": "2vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "88.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 83,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "90.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 118,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "170.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 84,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "130.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 119,
                                        "label": "8vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-16gib",
                                        "display_name": "8vcpu-16gib",
                                        "product_display_name": "8vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 16G",
                                        "cpu_memory_slug_display": "8vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "290.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 85,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "240.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 120,
                                        "label": "16vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-32gib",
                                        "display_name": "16vcpu-32gib",
                                        "product_display_name": "16vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 32G",
                                        "cpu_memory_slug_display": "16vcpu-32gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-32gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "560.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    }
                                ]
                            },
                            {
                                "id": "group-13-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 13,
                                "second_product_group_name": "襄阳",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 13,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 襄阳 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 27,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "45.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 28,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "55.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 29,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "70.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 30,
                                        "label": "12vcpu-12gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "12vcpu-12gib",
                                        "display_name": "12vcpu-12gib",
                                        "product_display_name": "12vcpu-12gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "12 vCPU 12G",
                                        "cpu_memory_slug_display": "12vcpu-12gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-12vcpu-12gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "100.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 31,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 襄阳",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "125.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-1",
                        "label": "美国",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 美国",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-3",
                                "label": "三网精品",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 1,
                                "second_product_group_name": "美国",
                                "third_product_group_id": 3,
                                "third_product_group_name": "三网精品",
                                "effective_product_group_id": 3,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 美国 / 三网精品",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 1,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 三网精品",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "20.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 2,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 三网精品",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "25.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 5,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 三网精品",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "35.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 3,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 三网精品",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "60.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 4,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 三网精品",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "120.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    }
                                ]
                            },
                            {
                                "id": "group-5",
                                "label": "高性能",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 1,
                                "second_product_group_name": "美国",
                                "third_product_group_id": 5,
                                "third_product_group_name": "高性能",
                                "effective_product_group_id": 5,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 美国 / 高性能",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 47,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高性能",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "22.00"
                                        },
                                        "status": 1,
                                        "sort_order": 120
                                    },
                                    {
                                        "id": 48,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高性能",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "30.00"
                                        },
                                        "status": 1,
                                        "sort_order": 121
                                    },
                                    {
                                        "id": 49,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高性能",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "40.00"
                                        },
                                        "status": 1,
                                        "sort_order": 122
                                    },
                                    {
                                        "id": 50,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高性能",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "60.00"
                                        },
                                        "status": 1,
                                        "sort_order": 123
                                    },
                                    {
                                        "id": 51,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高性能",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "120.00"
                                        },
                                        "status": 1,
                                        "sort_order": 124
                                    }
                                ]
                            },
                            {
                                "id": "group-19",
                                "label": "家宽",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 1,
                                "second_product_group_name": "美国",
                                "third_product_group_id": 19,
                                "third_product_group_name": "家宽",
                                "effective_product_group_id": 19,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 美国 / 家宽",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 94,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 家宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "45.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 95,
                                        "label": "2vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-4gib",
                                        "display_name": "2vcpu-4gib",
                                        "product_display_name": "2vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 4G",
                                        "cpu_memory_slug_display": "2vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 家宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "65.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 96,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 家宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "85.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 97,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 家宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "125.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 98,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 家宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "165.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    },
                                    {
                                        "id": 99,
                                        "label": "8vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-16gib",
                                        "display_name": "8vcpu-16gib",
                                        "product_display_name": "8vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 16G",
                                        "cpu_memory_slug_display": "8vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 家宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "245.00"
                                        },
                                        "status": 1,
                                        "sort_order": 6
                                    },
                                    {
                                        "id": 100,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 家宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "325.00"
                                        },
                                        "status": 1,
                                        "sort_order": 7
                                    },
                                    {
                                        "id": 101,
                                        "label": "16vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-32gib",
                                        "display_name": "16vcpu-32gib",
                                        "product_display_name": "16vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 32G",
                                        "cpu_memory_slug_display": "16vcpu-32gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-32gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 家宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "485.00"
                                        },
                                        "status": 1,
                                        "sort_order": 8
                                    }
                                ]
                            },
                            {
                                "id": "group-20",
                                "label": "高宽",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 1,
                                "second_product_group_name": "美国",
                                "third_product_group_id": 20,
                                "third_product_group_name": "高宽",
                                "effective_product_group_id": 20,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 102,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "45.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 121,
                                        "label": "2vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-4gib",
                                        "display_name": "2vcpu-4gib",
                                        "product_display_name": "2vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 4G",
                                        "cpu_memory_slug_display": "2vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "61.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 122,
                                        "label": "2vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-8gib",
                                        "display_name": "2vcpu-8gib",
                                        "product_display_name": "2vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 8G",
                                        "cpu_memory_slug_display": "2vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "93.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 123,
                                        "label": "2vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-16gib",
                                        "display_name": "2vcpu-16gib",
                                        "product_display_name": "2vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 16G",
                                        "cpu_memory_slug_display": "2vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "157.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 124,
                                        "label": "2vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-32gib",
                                        "display_name": "2vcpu-32gib",
                                        "product_display_name": "2vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 32G",
                                        "cpu_memory_slug_display": "2vcpu-32gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-32gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "285.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 125,
                                        "label": "2vcpu-64gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-64gib",
                                        "display_name": "2vcpu-64gib",
                                        "product_display_name": "2vcpu-64gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 64G",
                                        "cpu_memory_slug_display": "2vcpu-64gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-64gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "541.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 126,
                                        "label": "4vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-2gib",
                                        "display_name": "4vcpu-2gib",
                                        "product_display_name": "4vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 2G",
                                        "cpu_memory_slug_display": "4vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "61.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 127,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "77.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 128,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "109.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 129,
                                        "label": "4vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-16gib",
                                        "display_name": "4vcpu-16gib",
                                        "product_display_name": "4vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 16G",
                                        "cpu_memory_slug_display": "4vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "173.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 130,
                                        "label": "4vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-32gib",
                                        "display_name": "4vcpu-32gib",
                                        "product_display_name": "4vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 32G",
                                        "cpu_memory_slug_display": "4vcpu-32gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-32gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "301.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 131,
                                        "label": "4vcpu-64gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-64gib",
                                        "display_name": "4vcpu-64gib",
                                        "product_display_name": "4vcpu-64gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 64G",
                                        "cpu_memory_slug_display": "4vcpu-64gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-64gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "557.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 132,
                                        "label": "8vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-2gib",
                                        "display_name": "8vcpu-2gib",
                                        "product_display_name": "8vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 2G",
                                        "cpu_memory_slug_display": "8vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "93.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 133,
                                        "label": "8vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-4gib",
                                        "display_name": "8vcpu-4gib",
                                        "product_display_name": "8vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 4G",
                                        "cpu_memory_slug_display": "8vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "109.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 134,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "141.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 135,
                                        "label": "8vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-16gib",
                                        "display_name": "8vcpu-16gib",
                                        "product_display_name": "8vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 16G",
                                        "cpu_memory_slug_display": "8vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "205.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 136,
                                        "label": "8vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-32gib",
                                        "display_name": "8vcpu-32gib",
                                        "product_display_name": "8vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 32G",
                                        "cpu_memory_slug_display": "8vcpu-32gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-32gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "333.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 137,
                                        "label": "8vcpu-64gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-64gib",
                                        "display_name": "8vcpu-64gib",
                                        "product_display_name": "8vcpu-64gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 64G",
                                        "cpu_memory_slug_display": "8vcpu-64gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-64gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "589.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 138,
                                        "label": "16vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-2gib",
                                        "display_name": "16vcpu-2gib",
                                        "product_display_name": "16vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 2G",
                                        "cpu_memory_slug_display": "16vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "157.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 139,
                                        "label": "16vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-4gib",
                                        "display_name": "16vcpu-4gib",
                                        "product_display_name": "16vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 4G",
                                        "cpu_memory_slug_display": "16vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "173.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 140,
                                        "label": "16vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-8gib",
                                        "display_name": "16vcpu-8gib",
                                        "product_display_name": "16vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 8G",
                                        "cpu_memory_slug_display": "16vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "205.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 141,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "269.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 142,
                                        "label": "16vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-32gib",
                                        "display_name": "16vcpu-32gib",
                                        "product_display_name": "16vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 32G",
                                        "cpu_memory_slug_display": "16vcpu-32gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-32gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "397.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 143,
                                        "label": "16vcpu-64gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-64gib",
                                        "display_name": "16vcpu-64gib",
                                        "product_display_name": "16vcpu-64gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 64G",
                                        "cpu_memory_slug_display": "16vcpu-64gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-64gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "653.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 144,
                                        "label": "32vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-2gib",
                                        "display_name": "32vcpu-2gib",
                                        "product_display_name": "32vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 2G",
                                        "cpu_memory_slug_display": "32vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-32vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "285.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 145,
                                        "label": "32vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-4gib",
                                        "display_name": "32vcpu-4gib",
                                        "product_display_name": "32vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 4G",
                                        "cpu_memory_slug_display": "32vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-32vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "301.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 146,
                                        "label": "32vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-8gib",
                                        "display_name": "32vcpu-8gib",
                                        "product_display_name": "32vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 8G",
                                        "cpu_memory_slug_display": "32vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-32vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "333.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 147,
                                        "label": "32vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-16gib",
                                        "display_name": "32vcpu-16gib",
                                        "product_display_name": "32vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 16G",
                                        "cpu_memory_slug_display": "32vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-32vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "397.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 148,
                                        "label": "32vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-32gib",
                                        "display_name": "32vcpu-32gib",
                                        "product_display_name": "32vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 32G",
                                        "cpu_memory_slug_display": "32vcpu-32gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-32vcpu-32gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "525.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 149,
                                        "label": "32vcpu-64gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-64gib",
                                        "display_name": "32vcpu-64gib",
                                        "product_display_name": "32vcpu-64gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 64G",
                                        "cpu_memory_slug_display": "32vcpu-64gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-32vcpu-64gib",
                                        "effective_product_group_full_name": "云服务器 / 美国 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "781.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    }
                                ]
                            },
                            {
                                "id": "group-1-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 1,
                                "second_product_group_name": "美国",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 1,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 美国 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 6,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "20.00"
                                        },
                                        "status": 1,
                                        "sort_order": 6
                                    },
                                    {
                                        "id": 7,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "30.00"
                                        },
                                        "status": 1,
                                        "sort_order": 7
                                    },
                                    {
                                        "id": 8,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "40.00"
                                        },
                                        "status": 1,
                                        "sort_order": 8
                                    },
                                    {
                                        "id": 9,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "60.00"
                                        },
                                        "status": 1,
                                        "sort_order": 9
                                    },
                                    {
                                        "id": 10,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "120.00"
                                        },
                                        "status": 1,
                                        "sort_order": 10
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-2",
                        "label": "香港",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 2,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 香港",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-1",
                                "label": "三网精品",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 2,
                                "second_product_group_name": "香港",
                                "third_product_group_id": 1,
                                "third_product_group_name": "三网精品",
                                "effective_product_group_id": 1,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 香港 / 三网精品",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 6,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 香港 / 三网精品",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "20.00"
                                        },
                                        "status": 1,
                                        "sort_order": 6
                                    },
                                    {
                                        "id": 7,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 香港 / 三网精品",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "30.00"
                                        },
                                        "status": 1,
                                        "sort_order": 7
                                    },
                                    {
                                        "id": 8,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 香港 / 三网精品",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "40.00"
                                        },
                                        "status": 1,
                                        "sort_order": 8
                                    },
                                    {
                                        "id": 9,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 香港 / 三网精品",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "60.00"
                                        },
                                        "status": 1,
                                        "sort_order": 9
                                    },
                                    {
                                        "id": 10,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 香港 / 三网精品",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "120.00"
                                        },
                                        "status": 1,
                                        "sort_order": 10
                                    }
                                ]
                            },
                            {
                                "id": "group-2",
                                "label": "大宽带",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 2,
                                "second_product_group_name": "香港",
                                "third_product_group_id": 2,
                                "third_product_group_name": "大宽带",
                                "effective_product_group_id": 2,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 香港 / 大宽带",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 22,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 香港 / 大宽带",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "23.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 23,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 香港 / 大宽带",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "35.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 24,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 香港 / 大宽带",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "45.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 26,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 香港 / 大宽带",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "60.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 25,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 香港 / 大宽带",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "120.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    }
                                ]
                            },
                            {
                                "id": "group-2-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 2,
                                "second_product_group_name": "香港",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 2,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 香港 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 22,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 香港",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "23.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 23,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 香港",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "35.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 24,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 香港",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "45.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 26,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 香港",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "60.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 25,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 香港",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "120.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-10",
                        "label": "内蒙古电信",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 10,
                        "second_product_group_name": "内蒙古电信",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 10,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 内蒙古电信",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-10",
                                "label": "性价比",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 10,
                                "second_product_group_name": "内蒙古电信",
                                "third_product_group_id": 10,
                                "third_product_group_name": "性价比",
                                "effective_product_group_id": 10,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 内蒙古电信 / 性价比",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 52,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信 / 性价比",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "25.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 53,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信 / 性价比",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "30.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 56,
                                        "label": "12vcpu-12gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "12vcpu-12gib",
                                        "display_name": "12vcpu-12gib",
                                        "product_display_name": "12vcpu-12gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "12 vCPU 12G",
                                        "cpu_memory_slug_display": "12vcpu-12gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-12vcpu-12gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信 / 性价比",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "75.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 54,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信 / 性价比",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "40.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 57,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信 / 性价比",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "100.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    },
                                    {
                                        "id": 55,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信 / 性价比",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "55.00"
                                        },
                                        "status": 1,
                                        "sort_order": 6
                                    }
                                ]
                            },
                            {
                                "id": "group-10-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 10,
                                "second_product_group_name": "内蒙古电信",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 10,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 内蒙古电信 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 52,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "25.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 53,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "30.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 56,
                                        "label": "12vcpu-12gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "12vcpu-12gib",
                                        "display_name": "12vcpu-12gib",
                                        "product_display_name": "12vcpu-12gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "12 vCPU 12G",
                                        "cpu_memory_slug_display": "12vcpu-12gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-12vcpu-12gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "75.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 54,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "40.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 57,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "100.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    },
                                    {
                                        "id": 55,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 内蒙古电信",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "55.00"
                                        },
                                        "status": 1,
                                        "sort_order": 6
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-9",
                        "label": "西安高防",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 9,
                        "second_product_group_name": "西安高防",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 9,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 西安高防",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-14",
                                "label": "高防",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 9,
                                "second_product_group_name": "西安高防",
                                "third_product_group_id": 14,
                                "third_product_group_name": "高防",
                                "effective_product_group_id": 14,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 西安高防 / 高防",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 42,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 西安高防 / 高防",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "40.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 43,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 西安高防 / 高防",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "50.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 44,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 西安高防 / 高防",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "65.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 45,
                                        "label": "12vcpu-12gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "12vcpu-12gib",
                                        "display_name": "12vcpu-12gib",
                                        "product_display_name": "12vcpu-12gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "12 vCPU 12G",
                                        "cpu_memory_slug_display": "12vcpu-12gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-12vcpu-12gib",
                                        "effective_product_group_full_name": "云服务器 / 西安高防 / 高防",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "90.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 46,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 西安高防 / 高防",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "110.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    }
                                ]
                            },
                            {
                                "id": "group-9-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 9,
                                "second_product_group_name": "西安高防",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 9,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 西安高防 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 61,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs-nat",
                                        "combined_display_name": "gscs-nat-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 西安高防",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "6.00"
                                        },
                                        "status": 1,
                                        "sort_order": 146
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-8",
                        "label": "轻量云",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 8,
                        "second_product_group_name": "轻量云",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 8,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 轻量云",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-4",
                                "label": "美国",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 8,
                                "second_product_group_name": "轻量云",
                                "third_product_group_id": 4,
                                "third_product_group_name": "美国",
                                "effective_product_group_id": 4,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 轻量云 / 美国",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 19,
                                        "label": "2vcpu-1gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-1gib",
                                        "display_name": "2vcpu-1gib",
                                        "product_display_name": "2vcpu-1gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 1G",
                                        "cpu_memory_slug_display": "2vcpu-1gib",
                                        "product_spec_display": "2 vCPU 1G",
                                        "combined_display_name": "2 vCPU 1G",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "4.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 20,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "4 vCPU 4G",
                                        "combined_display_name": "4 vCPU 4G",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "12.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 21,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "8 vCPU 8G",
                                        "combined_display_name": "8 vCPU 8G",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "15.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 35,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "2 vCPU 2G",
                                        "combined_display_name": "2 vCPU 2G",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "9.90"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 60,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "4 vCPU 4G",
                                        "combined_display_name": "4 vCPU 4G",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "28.00"
                                        },
                                        "status": 0,
                                        "sort_order": 5
                                    },
                                    {
                                        "id": 32,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "9.90"
                                        },
                                        "status": 1,
                                        "sort_order": 94
                                    },
                                    {
                                        "id": 33,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "19.90"
                                        },
                                        "status": 1,
                                        "sort_order": 95
                                    },
                                    {
                                        "id": 34,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "29.90"
                                        },
                                        "status": 1,
                                        "sort_order": 96
                                    }
                                ]
                            },
                            {
                                "id": "group-6",
                                "label": "西安",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 8,
                                "second_product_group_name": "轻量云",
                                "third_product_group_id": 6,
                                "third_product_group_name": "西安",
                                "effective_product_group_id": 6,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 轻量云 / 西安",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 66,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "29.90"
                                        },
                                        "status": 1,
                                        "sort_order": 155
                                    },
                                    {
                                        "id": 67,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "49.90"
                                        },
                                        "status": 1,
                                        "sort_order": 156
                                    },
                                    {
                                        "id": 68,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "69.90"
                                        },
                                        "status": 1,
                                        "sort_order": 157
                                    }
                                ]
                            },
                            {
                                "id": "group-7",
                                "label": "香港",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 8,
                                "second_product_group_name": "轻量云",
                                "third_product_group_id": 7,
                                "third_product_group_name": "香港",
                                "effective_product_group_id": 7,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 轻量云 / 香港",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 72,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 香港",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "9.90"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 70,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 香港",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "19.90"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 71,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 香港",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "29.90"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 73,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云 / 香港",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "49.90"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    }
                                ]
                            },
                            {
                                "id": "group-8-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 8,
                                "second_product_group_name": "轻量云",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 8,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 轻量云 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 74,
                                        "label": "2vcpu-1gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-1gib",
                                        "display_name": "2vcpu-1gib",
                                        "product_display_name": "2vcpu-1gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 1G",
                                        "cpu_memory_slug_display": "2vcpu-1gib",
                                        "product_spec_display": "gscs-nat",
                                        "combined_display_name": "gscs-nat-2vcpu-1gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "5.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 75,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs-nat",
                                        "combined_display_name": "gscs-nat-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "10.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 76,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs-nat",
                                        "combined_display_name": "gscs-nat-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "14.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 77,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs-nat",
                                        "combined_display_name": "gscs-nat-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 轻量云",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "18.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-7",
                        "label": "十堰高宽",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 7,
                        "second_product_group_name": "十堰高宽",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 7,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 十堰高宽",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-13",
                                "label": "高宽",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 7,
                                "second_product_group_name": "十堰高宽",
                                "third_product_group_id": 13,
                                "third_product_group_name": "高宽",
                                "effective_product_group_id": 13,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 十堰高宽 / 高宽",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 27,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 十堰高宽 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "45.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 28,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 十堰高宽 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "55.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 29,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 十堰高宽 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "70.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 30,
                                        "label": "12vcpu-12gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "12vcpu-12gib",
                                        "display_name": "12vcpu-12gib",
                                        "product_display_name": "12vcpu-12gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "12 vCPU 12G",
                                        "cpu_memory_slug_display": "12vcpu-12gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-12vcpu-12gib",
                                        "effective_product_group_full_name": "云服务器 / 十堰高宽 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "100.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 31,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 十堰高宽 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "125.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    }
                                ]
                            },
                            {
                                "id": "group-7-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 7,
                                "second_product_group_name": "十堰高宽",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 7,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 十堰高宽 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 72,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 十堰高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "9.90"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 70,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 十堰高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "19.90"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 71,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 十堰高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "29.90"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 73,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 十堰高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "49.90"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-3",
                        "label": "宁波高宽",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 3,
                        "second_product_group_name": "宁波高宽",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 宁波高宽",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-12",
                                "label": "高宽",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 3,
                                "second_product_group_name": "宁波高宽",
                                "third_product_group_id": 12,
                                "third_product_group_name": "高宽",
                                "effective_product_group_id": 12,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 宁波高宽 / 高宽",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 12,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 宁波高宽 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "60.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 13,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 宁波高宽 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "75.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 14,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 宁波高宽 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "90.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 15,
                                        "label": "8vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-16gib",
                                        "display_name": "8vcpu-16gib",
                                        "product_display_name": "8vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 16G",
                                        "cpu_memory_slug_display": "8vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 宁波高宽 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "105.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 16,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 宁波高宽 / 高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "125.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    }
                                ]
                            },
                            {
                                "id": "group-3-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 3,
                                "second_product_group_name": "宁波高宽",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 3,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 宁波高宽 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 1,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 宁波高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "20.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 2,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 宁波高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "25.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 5,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 宁波高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "35.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 3,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 宁波高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "60.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 4,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 宁波高宽",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "120.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-4",
                        "label": "特价云服务器",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 4,
                        "second_product_group_name": "特价云服务器",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 4,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 特价云服务器",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-17",
                                "label": "特价",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 4,
                                "second_product_group_name": "特价云服务器",
                                "third_product_group_id": 17,
                                "third_product_group_name": "特价",
                                "effective_product_group_id": 17,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 特价云服务器 / 特价",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 17,
                                        "label": "2vcpu-1gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-1gib",
                                        "display_name": "2vcpu-1gib",
                                        "product_display_name": "2vcpu-1gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 1G",
                                        "cpu_memory_slug_display": "2vcpu-1gib",
                                        "product_spec_display": "2 vCPU 1G",
                                        "combined_display_name": "2 vCPU 1G",
                                        "effective_product_group_full_name": "云服务器 / 特价云服务器 / 特价",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "16.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 18,
                                        "label": "2vcpu-1gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-1gib",
                                        "display_name": "2vcpu-1gib",
                                        "product_display_name": "2vcpu-1gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 1G",
                                        "cpu_memory_slug_display": "2vcpu-1gib",
                                        "product_spec_display": "2 vCPU 1G",
                                        "combined_display_name": "2 vCPU 1G",
                                        "effective_product_group_full_name": "云服务器 / 特价云服务器 / 特价",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "16.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    }
                                ]
                            },
                            {
                                "id": "group-4-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 4,
                                "second_product_group_name": "特价云服务器",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 4,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 特价云服务器 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 19,
                                        "label": "2vcpu-1gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-1gib",
                                        "display_name": "2vcpu-1gib",
                                        "product_display_name": "2vcpu-1gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 1G",
                                        "cpu_memory_slug_display": "2vcpu-1gib",
                                        "product_spec_display": "2 vCPU 1G",
                                        "combined_display_name": "2 vCPU 1G",
                                        "effective_product_group_full_name": "云服务器 / 特价云服务器",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "4.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 20,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "4 vCPU 4G",
                                        "combined_display_name": "4 vCPU 4G",
                                        "effective_product_group_full_name": "云服务器 / 特价云服务器",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "12.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 21,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "8 vCPU 8G",
                                        "combined_display_name": "8 vCPU 8G",
                                        "effective_product_group_full_name": "云服务器 / 特价云服务器",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "15.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 35,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "2 vCPU 2G",
                                        "combined_display_name": "2 vCPU 2G",
                                        "effective_product_group_full_name": "云服务器 / 特价云服务器",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "9.90"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 60,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "4 vCPU 4G",
                                        "combined_display_name": "4 vCPU 4G",
                                        "effective_product_group_full_name": "云服务器 / 特价云服务器",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "28.00"
                                        },
                                        "status": 0,
                                        "sort_order": 5
                                    },
                                    {
                                        "id": 32,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云服务器 / 特价云服务器",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "9.90"
                                        },
                                        "status": 1,
                                        "sort_order": 94
                                    },
                                    {
                                        "id": 33,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云服务器 / 特价云服务器",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "19.90"
                                        },
                                        "status": 1,
                                        "sort_order": 95
                                    },
                                    {
                                        "id": 34,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云服务器 / 特价云服务器",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "29.90"
                                        },
                                        "status": 1,
                                        "sort_order": 96
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-11",
                        "label": "99计划",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 11,
                        "second_product_group_name": "99计划",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 11,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 99计划",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-16",
                                "label": "99",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 11,
                                "second_product_group_name": "99计划",
                                "third_product_group_id": 16,
                                "third_product_group_name": "99",
                                "effective_product_group_id": 16,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 99计划 / 99",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 58,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "16 vCPU 16G",
                                        "combined_display_name": "16 vCPU 16G",
                                        "effective_product_group_full_name": "云服务器 / 99计划 / 99",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "99.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 59,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "16 vCPU 16G",
                                        "combined_display_name": "16 vCPU 16G",
                                        "effective_product_group_full_name": "云服务器 / 99计划 / 99",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "99.00"
                                        },
                                        "status": 0,
                                        "sort_order": 2
                                    }
                                ]
                            },
                            {
                                "id": "group-11-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 1,
                                "first_product_group_name": "云服务器",
                                "service_type_code": "vps",
                                "service_type_label": "云服务器",
                                "second_product_group_id": 11,
                                "second_product_group_name": "99计划",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 11,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云服务器 / 99计划 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 78,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "ercs",
                                        "combined_display_name": "ercs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云服务器 / 99计划",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "299.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 79,
                                        "label": "16vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-32gib",
                                        "display_name": "16vcpu-32gib",
                                        "product_display_name": "16vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 32G",
                                        "cpu_memory_slug_display": "16vcpu-32gib",
                                        "product_spec_display": "ercs",
                                        "combined_display_name": "ercs-16vcpu-32gib",
                                        "effective_product_group_full_name": "云服务器 / 99计划",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "450.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 80,
                                        "label": "32vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-32gib",
                                        "display_name": "32vcpu-32gib",
                                        "product_display_name": "32vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 32G",
                                        "cpu_memory_slug_display": "32vcpu-32gib",
                                        "product_spec_display": "ercs",
                                        "combined_display_name": "ercs-32vcpu-32gib",
                                        "effective_product_group_full_name": "云服务器 / 99计划",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "599.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 81,
                                        "label": "32vcpu-64gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-64gib",
                                        "display_name": "32vcpu-64gib",
                                        "product_display_name": "32vcpu-64gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 64G",
                                        "cpu_memory_slug_display": "32vcpu-64gib",
                                        "product_spec_display": "ercs",
                                        "combined_display_name": "ercs-32vcpu-64gib",
                                        "effective_product_group_full_name": "云服务器 / 99计划",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "899.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-16",
                        "label": "历史未归档分类",
                        "node_type": "second_product_group",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "second_product_group_id": 16,
                        "second_product_group_name": "历史未归档分类",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 16,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云服务器 / 历史未归档分类",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": 58,
                                "label": "16vcpu-16gib",
                                "node_type": "product",
                                "leaf": true,
                                "disabled": false,
                                "product_name": "16vcpu-16gib",
                                "display_name": "16vcpu-16gib",
                                "product_display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "16 vCPU 16G",
                                "combined_display_name": "16 vCPU 16G",
                                "effective_product_group_full_name": "云服务器 / 历史未归档分类",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "99.00"
                                },
                                "status": 1,
                                "sort_order": 1
                            },
                            {
                                "id": 59,
                                "label": "16vcpu-16gib",
                                "node_type": "product",
                                "leaf": true,
                                "disabled": false,
                                "product_name": "16vcpu-16gib",
                                "display_name": "16vcpu-16gib",
                                "product_display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "16 vCPU 16G",
                                "combined_display_name": "16 vCPU 16G",
                                "effective_product_group_full_name": "云服务器 / 历史未归档分类",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "99.00"
                                },
                                "status": 0,
                                "sort_order": 2
                            }
                        ]
                    }
                ]
            },
            {
                "id": "first-2",
                "label": "游戏云",
                "node_type": "first_product_group",
                "first_product_group_id": 2,
                "first_product_group_name": "游戏云",
                "service_type_code": "dedicated",
                "service_type_label": "游戏云",
                "leaf": false,
                "disabled": false,
                "children": [
                    {
                        "id": "group-14",
                        "label": "Gold",
                        "node_type": "second_product_group",
                        "first_product_group_id": 2,
                        "first_product_group_name": "游戏云",
                        "service_type_code": "dedicated",
                        "service_type_label": "游戏云",
                        "second_product_group_id": 14,
                        "second_product_group_name": "Gold",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 14,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "游戏云 / Gold",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-21",
                                "label": "西安",
                                "node_type": "third_product_group",
                                "first_product_group_id": 2,
                                "first_product_group_name": "游戏云",
                                "service_type_code": "dedicated",
                                "service_type_label": "游戏云",
                                "second_product_group_id": 14,
                                "second_product_group_name": "Gold",
                                "third_product_group_id": 21,
                                "third_product_group_name": "西安",
                                "effective_product_group_id": 21,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "游戏云 / Gold / 西安",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 107,
                                        "label": "2vcpu-1gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-1gib",
                                        "display_name": "2vcpu-1gib",
                                        "product_display_name": "2vcpu-1gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 1G",
                                        "cpu_memory_slug_display": "2vcpu-1gib",
                                        "product_spec_display": "gscs-gc",
                                        "combined_display_name": "gscs-gc-2vcpu-1gib",
                                        "effective_product_group_full_name": "游戏云 / Gold / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "10.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 108,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs-gc",
                                        "combined_display_name": "gscs-gc-2vcpu-2gib",
                                        "effective_product_group_full_name": "游戏云 / Gold / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "15.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 109,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs-gc",
                                        "combined_display_name": "gscs-gc-4vcpu-4gib",
                                        "effective_product_group_full_name": "游戏云 / Gold / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "20.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 110,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs-gc",
                                        "combined_display_name": "gscs-gc-8vcpu-8gib",
                                        "effective_product_group_full_name": "游戏云 / Gold / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "28.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 111,
                                        "label": "8vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-16gib",
                                        "display_name": "8vcpu-16gib",
                                        "product_display_name": "8vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 16G",
                                        "cpu_memory_slug_display": "8vcpu-16gib",
                                        "product_spec_display": "gscs-gc",
                                        "combined_display_name": "gscs-gc-8vcpu-16gib",
                                        "effective_product_group_full_name": "游戏云 / Gold / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "40.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    }
                                ]
                            },
                            {
                                "id": "group-14-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 2,
                                "first_product_group_name": "游戏云",
                                "service_type_code": "dedicated",
                                "service_type_label": "游戏云",
                                "second_product_group_id": 14,
                                "second_product_group_name": "Gold",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 14,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "游戏云 / Gold / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 42,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "游戏云 / Gold",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "40.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 43,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "游戏云 / Gold",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "50.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 44,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "游戏云 / Gold",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "65.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 45,
                                        "label": "12vcpu-12gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "12vcpu-12gib",
                                        "display_name": "12vcpu-12gib",
                                        "product_display_name": "12vcpu-12gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "12 vCPU 12G",
                                        "cpu_memory_slug_display": "12vcpu-12gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-12vcpu-12gib",
                                        "effective_product_group_full_name": "游戏云 / Gold",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "90.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 46,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "游戏云 / Gold",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "110.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-15",
                        "label": "Platinum",
                        "node_type": "second_product_group",
                        "first_product_group_id": 2,
                        "first_product_group_name": "游戏云",
                        "service_type_code": "dedicated",
                        "service_type_label": "游戏云",
                        "second_product_group_id": 15,
                        "second_product_group_name": "Platinum",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 15,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "游戏云 / Platinum",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-22",
                                "label": "十堰",
                                "node_type": "third_product_group",
                                "first_product_group_id": 2,
                                "first_product_group_name": "游戏云",
                                "service_type_code": "dedicated",
                                "service_type_label": "游戏云",
                                "second_product_group_id": 15,
                                "second_product_group_name": "Platinum",
                                "third_product_group_id": 22,
                                "third_product_group_name": "十堰",
                                "effective_product_group_id": 22,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "游戏云 / Platinum / 十堰",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 112,
                                        "label": "2vcpu-1gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-1gib",
                                        "display_name": "2vcpu-1gib",
                                        "product_display_name": "2vcpu-1gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 1G",
                                        "cpu_memory_slug_display": "2vcpu-1gib",
                                        "product_spec_display": "gscs-gc",
                                        "combined_display_name": "gscs-gc-2vcpu-1gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum / 十堰",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "12.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 113,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs-gc",
                                        "combined_display_name": "gscs-gc-2vcpu-2gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum / 十堰",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "18.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 114,
                                        "label": "2vcpu-6gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-6gib",
                                        "display_name": "2vcpu-6gib",
                                        "product_display_name": "2vcpu-6gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 6G",
                                        "cpu_memory_slug_display": "2vcpu-6gib",
                                        "product_spec_display": "gscs-gc",
                                        "combined_display_name": "gscs-gc-2vcpu-6gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum / 十堰",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "30.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 115,
                                        "label": "4vcpu-12gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-12gib",
                                        "display_name": "4vcpu-12gib",
                                        "product_display_name": "4vcpu-12gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 12G",
                                        "cpu_memory_slug_display": "4vcpu-12gib",
                                        "product_spec_display": "gscs-gc",
                                        "combined_display_name": "gscs-gc-4vcpu-12gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum / 十堰",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "50.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 116,
                                        "label": "4vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-16gib",
                                        "display_name": "4vcpu-16gib",
                                        "product_display_name": "4vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 16G",
                                        "cpu_memory_slug_display": "4vcpu-16gib",
                                        "product_spec_display": "gscs-gc",
                                        "combined_display_name": "gscs-gc-4vcpu-16gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum / 十堰",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "62.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    }
                                ]
                            },
                            {
                                "id": "group-15-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 2,
                                "first_product_group_name": "游戏云",
                                "service_type_code": "dedicated",
                                "service_type_label": "游戏云",
                                "second_product_group_id": 15,
                                "second_product_group_name": "Platinum",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 15,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "游戏云 / Platinum / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 82,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "48.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 117,
                                        "label": "2vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-4gib",
                                        "display_name": "2vcpu-4gib",
                                        "product_display_name": "2vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 4G",
                                        "cpu_memory_slug_display": "2vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-4gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "88.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 83,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "90.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 118,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "170.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 84,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "130.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 119,
                                        "label": "8vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-16gib",
                                        "display_name": "8vcpu-16gib",
                                        "product_display_name": "8vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 16G",
                                        "cpu_memory_slug_display": "8vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-16gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "290.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 85,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "240.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 120,
                                        "label": "16vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-32gib",
                                        "display_name": "16vcpu-32gib",
                                        "product_display_name": "16vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 32G",
                                        "cpu_memory_slug_display": "16vcpu-32gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-32gib",
                                        "effective_product_group_full_name": "游戏云 / Platinum",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "560.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    }
                                ]
                            }
                        ]
                    }
                ]
            },
            {
                "id": "first-3",
                "label": "云电脑",
                "node_type": "first_product_group",
                "first_product_group_id": 3,
                "first_product_group_name": "云电脑",
                "service_type_code": "domain",
                "service_type_label": "云电脑",
                "leaf": false,
                "disabled": false,
                "children": [
                    {
                        "id": "group-5",
                        "label": "云电脑",
                        "node_type": "second_product_group",
                        "first_product_group_id": 3,
                        "first_product_group_name": "云电脑",
                        "service_type_code": "domain",
                        "service_type_label": "云电脑",
                        "second_product_group_id": 5,
                        "second_product_group_name": "云电脑",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "云电脑 / 云电脑",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-8",
                                "label": "西安",
                                "node_type": "third_product_group",
                                "first_product_group_id": 3,
                                "first_product_group_name": "云电脑",
                                "service_type_code": "domain",
                                "service_type_label": "云电脑",
                                "second_product_group_id": 5,
                                "second_product_group_name": "云电脑",
                                "third_product_group_id": 8,
                                "third_product_group_name": "西安",
                                "effective_product_group_id": 8,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云电脑 / 云电脑 / 西安",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 74,
                                        "label": "2vcpu-1gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-1gib",
                                        "display_name": "2vcpu-1gib",
                                        "product_display_name": "2vcpu-1gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 1G",
                                        "cpu_memory_slug_display": "2vcpu-1gib",
                                        "product_spec_display": "gscs-nat",
                                        "combined_display_name": "gscs-nat-2vcpu-1gib",
                                        "effective_product_group_full_name": "云电脑 / 云电脑 / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "5.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 75,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs-nat",
                                        "combined_display_name": "gscs-nat-2vcpu-2gib",
                                        "effective_product_group_full_name": "云电脑 / 云电脑 / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "10.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 76,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs-nat",
                                        "combined_display_name": "gscs-nat-4vcpu-4gib",
                                        "effective_product_group_full_name": "云电脑 / 云电脑 / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "14.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    },
                                    {
                                        "id": 77,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs-nat",
                                        "combined_display_name": "gscs-nat-8vcpu-8gib",
                                        "effective_product_group_full_name": "云电脑 / 云电脑 / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "18.00"
                                        },
                                        "status": 1,
                                        "sort_order": 0
                                    }
                                ]
                            },
                            {
                                "id": "group-9",
                                "label": "成都",
                                "node_type": "third_product_group",
                                "first_product_group_id": 3,
                                "first_product_group_name": "云电脑",
                                "service_type_code": "domain",
                                "service_type_label": "云电脑",
                                "second_product_group_id": 5,
                                "second_product_group_name": "云电脑",
                                "third_product_group_id": 9,
                                "third_product_group_name": "成都",
                                "effective_product_group_id": 9,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云电脑 / 云电脑 / 成都",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 61,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs-nat",
                                        "combined_display_name": "gscs-nat-2vcpu-2gib",
                                        "effective_product_group_full_name": "云电脑 / 云电脑 / 成都",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "6.00"
                                        },
                                        "status": 1,
                                        "sort_order": 146
                                    }
                                ]
                            },
                            {
                                "id": "group-5-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 3,
                                "first_product_group_name": "云电脑",
                                "service_type_code": "domain",
                                "service_type_label": "云电脑",
                                "second_product_group_id": 5,
                                "second_product_group_name": "云电脑",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 5,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "云电脑 / 云电脑 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 47,
                                        "label": "2vcpu-2gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "2vcpu-2gib",
                                        "display_name": "2vcpu-2gib",
                                        "product_display_name": "2vcpu-2gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "2 vCPU 2G",
                                        "cpu_memory_slug_display": "2vcpu-2gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-2vcpu-2gib",
                                        "effective_product_group_full_name": "云电脑 / 云电脑",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "22.00"
                                        },
                                        "status": 1,
                                        "sort_order": 120
                                    },
                                    {
                                        "id": 48,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "云电脑 / 云电脑",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "30.00"
                                        },
                                        "status": 1,
                                        "sort_order": 121
                                    },
                                    {
                                        "id": 49,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "云电脑 / 云电脑",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "40.00"
                                        },
                                        "status": 1,
                                        "sort_order": 122
                                    },
                                    {
                                        "id": 50,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "云电脑 / 云电脑",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "60.00"
                                        },
                                        "status": 1,
                                        "sort_order": 123
                                    },
                                    {
                                        "id": 51,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "云电脑 / 云电脑",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "120.00"
                                        },
                                        "status": 1,
                                        "sort_order": 124
                                    }
                                ]
                            }
                        ]
                    }
                ]
            },
            {
                "id": "first-4",
                "label": "裸金属",
                "node_type": "first_product_group",
                "first_product_group_id": 4,
                "first_product_group_name": "裸金属",
                "service_type_code": "type_iwjqnj",
                "service_type_label": "裸金属",
                "leaf": false,
                "disabled": false,
                "children": [
                    {
                        "id": "group-12",
                        "label": "裸金属",
                        "node_type": "second_product_group",
                        "first_product_group_id": 4,
                        "first_product_group_name": "裸金属",
                        "service_type_code": "type_iwjqnj",
                        "service_type_label": "裸金属",
                        "second_product_group_id": 12,
                        "second_product_group_name": "裸金属",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 12,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "裸金属 / 裸金属",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-11",
                                "label": "美国",
                                "node_type": "third_product_group",
                                "first_product_group_id": 4,
                                "first_product_group_name": "裸金属",
                                "service_type_code": "type_iwjqnj",
                                "service_type_label": "裸金属",
                                "second_product_group_id": 12,
                                "second_product_group_name": "裸金属",
                                "third_product_group_id": 11,
                                "third_product_group_name": "美国",
                                "effective_product_group_id": 11,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "裸金属 / 裸金属 / 美国",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 78,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "ercs",
                                        "combined_display_name": "ercs-16vcpu-16gib",
                                        "effective_product_group_full_name": "裸金属 / 裸金属 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "299.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 79,
                                        "label": "16vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-32gib",
                                        "display_name": "16vcpu-32gib",
                                        "product_display_name": "16vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 32G",
                                        "cpu_memory_slug_display": "16vcpu-32gib",
                                        "product_spec_display": "ercs",
                                        "combined_display_name": "ercs-16vcpu-32gib",
                                        "effective_product_group_full_name": "裸金属 / 裸金属 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "450.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 80,
                                        "label": "32vcpu-32gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-32gib",
                                        "display_name": "32vcpu-32gib",
                                        "product_display_name": "32vcpu-32gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 32G",
                                        "cpu_memory_slug_display": "32vcpu-32gib",
                                        "product_spec_display": "ercs",
                                        "combined_display_name": "ercs-32vcpu-32gib",
                                        "effective_product_group_full_name": "裸金属 / 裸金属 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "599.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 81,
                                        "label": "32vcpu-64gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-64gib",
                                        "display_name": "32vcpu-64gib",
                                        "product_display_name": "32vcpu-64gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 64G",
                                        "cpu_memory_slug_display": "32vcpu-64gib",
                                        "product_spec_display": "ercs",
                                        "combined_display_name": "ercs-32vcpu-64gib",
                                        "effective_product_group_full_name": "裸金属 / 裸金属 / 美国",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "899.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    }
                                ]
                            },
                            {
                                "id": "group-12-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 4,
                                "first_product_group_name": "裸金属",
                                "service_type_code": "type_iwjqnj",
                                "service_type_label": "裸金属",
                                "second_product_group_id": 12,
                                "second_product_group_name": "裸金属",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 12,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "裸金属 / 裸金属 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 12,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "裸金属 / 裸金属",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "60.00"
                                        },
                                        "status": 1,
                                        "sort_order": 1
                                    },
                                    {
                                        "id": 13,
                                        "label": "4vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-8gib",
                                        "display_name": "4vcpu-8gib",
                                        "product_display_name": "4vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 8G",
                                        "cpu_memory_slug_display": "4vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-8gib",
                                        "effective_product_group_full_name": "裸金属 / 裸金属",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "75.00"
                                        },
                                        "status": 1,
                                        "sort_order": 2
                                    },
                                    {
                                        "id": 14,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "裸金属 / 裸金属",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "90.00"
                                        },
                                        "status": 1,
                                        "sort_order": 3
                                    },
                                    {
                                        "id": 15,
                                        "label": "8vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-16gib",
                                        "display_name": "8vcpu-16gib",
                                        "product_display_name": "8vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 16G",
                                        "cpu_memory_slug_display": "8vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-16gib",
                                        "effective_product_group_full_name": "裸金属 / 裸金属",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "105.00"
                                        },
                                        "status": 1,
                                        "sort_order": 4
                                    },
                                    {
                                        "id": 16,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "裸金属 / 裸金属",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "125.00"
                                        },
                                        "status": 1,
                                        "sort_order": 5
                                    }
                                ]
                            }
                        ]
                    }
                ]
            },
            {
                "id": "first-5",
                "label": "CDN",
                "node_type": "first_product_group",
                "first_product_group_id": 5,
                "first_product_group_name": "CDN",
                "service_type_code": "other",
                "service_type_label": "CDN",
                "leaf": false,
                "disabled": false,
                "children": []
            },
            {
                "id": "first-6",
                "label": "其他",
                "node_type": "first_product_group",
                "first_product_group_id": 6,
                "first_product_group_name": "其他",
                "service_type_code": "type_ipragu",
                "service_type_label": "其他",
                "leaf": false,
                "disabled": false,
                "children": [
                    {
                        "id": "group-6",
                        "label": "二级域名",
                        "node_type": "second_product_group",
                        "first_product_group_id": 6,
                        "first_product_group_name": "其他",
                        "service_type_code": "type_ipragu",
                        "service_type_label": "其他",
                        "second_product_group_id": 6,
                        "second_product_group_name": "二级域名",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 6,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "其他 / 二级域名",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-18",
                                "label": "1",
                                "node_type": "third_product_group",
                                "first_product_group_id": 6,
                                "first_product_group_name": "其他",
                                "service_type_code": "type_ipragu",
                                "service_type_label": "其他",
                                "second_product_group_id": 6,
                                "second_product_group_name": "二级域名",
                                "third_product_group_id": 18,
                                "third_product_group_name": "1",
                                "effective_product_group_id": 18,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "其他 / 二级域名 / 1",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 69,
                                        "label": "32vcpu-64gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-64gib",
                                        "display_name": "32vcpu-64gib",
                                        "product_display_name": "32vcpu-64gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 64G",
                                        "cpu_memory_slug_display": "32vcpu-64gib",
                                        "product_spec_display": "32 vCPU 64G",
                                        "combined_display_name": "32 vCPU 64G",
                                        "effective_product_group_full_name": "其他 / 二级域名 / 1",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "380.00"
                                        },
                                        "status": 1,
                                        "sort_order": 158
                                    }
                                ]
                            },
                            {
                                "id": "group-6-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 6,
                                "first_product_group_name": "其他",
                                "service_type_code": "type_ipragu",
                                "service_type_label": "其他",
                                "second_product_group_id": 6,
                                "second_product_group_name": "二级域名",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 6,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "其他 / 二级域名 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 66,
                                        "label": "4vcpu-4gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "4vcpu-4gib",
                                        "display_name": "4vcpu-4gib",
                                        "product_display_name": "4vcpu-4gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "4 vCPU 4G",
                                        "cpu_memory_slug_display": "4vcpu-4gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-4vcpu-4gib",
                                        "effective_product_group_full_name": "其他 / 二级域名",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "29.90"
                                        },
                                        "status": 1,
                                        "sort_order": 155
                                    },
                                    {
                                        "id": 67,
                                        "label": "8vcpu-8gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "8vcpu-8gib",
                                        "display_name": "8vcpu-8gib",
                                        "product_display_name": "8vcpu-8gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "8 vCPU 8G",
                                        "cpu_memory_slug_display": "8vcpu-8gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-8vcpu-8gib",
                                        "effective_product_group_full_name": "其他 / 二级域名",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "49.90"
                                        },
                                        "status": 1,
                                        "sort_order": 156
                                    },
                                    {
                                        "id": 68,
                                        "label": "16vcpu-16gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "16vcpu-16gib",
                                        "display_name": "16vcpu-16gib",
                                        "product_display_name": "16vcpu-16gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "16 vCPU 16G",
                                        "cpu_memory_slug_display": "16vcpu-16gib",
                                        "product_spec_display": "gscs",
                                        "combined_display_name": "gscs-16vcpu-16gib",
                                        "effective_product_group_full_name": "其他 / 二级域名",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "69.90"
                                        },
                                        "status": 1,
                                        "sort_order": 157
                                    }
                                ]
                            }
                        ]
                    }
                ]
            },
            {
                "id": "first-8",
                "label": "物理机",
                "node_type": "first_product_group",
                "first_product_group_id": 8,
                "first_product_group_name": "物理机",
                "service_type_code": "type_tgynng",
                "service_type_label": "物理机",
                "leaf": false,
                "disabled": false,
                "children": [
                    {
                        "id": "group-18",
                        "label": "西安",
                        "node_type": "second_product_group",
                        "first_product_group_id": 8,
                        "first_product_group_name": "物理机",
                        "service_type_code": "type_tgynng",
                        "service_type_label": "物理机",
                        "second_product_group_id": 18,
                        "second_product_group_name": "西安",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 18,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "物理机 / 西安",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": "group-23",
                                "label": "电信",
                                "node_type": "third_product_group",
                                "first_product_group_id": 8,
                                "first_product_group_name": "物理机",
                                "service_type_code": "type_tgynng",
                                "service_type_label": "物理机",
                                "second_product_group_id": 18,
                                "second_product_group_name": "西安",
                                "third_product_group_id": 23,
                                "third_product_group_name": "电信",
                                "effective_product_group_id": 23,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "物理机 / 西安 / 电信",
                                "leaf": false,
                                "disabled": false,
                                "children": []
                            },
                            {
                                "id": "group-18-other",
                                "label": "其他",
                                "node_type": "third_product_group",
                                "first_product_group_id": 8,
                                "first_product_group_name": "物理机",
                                "service_type_code": "type_tgynng",
                                "service_type_label": "物理机",
                                "second_product_group_id": 18,
                                "second_product_group_name": "西安",
                                "third_product_group_id": null,
                                "third_product_group_name": "其他",
                                "effective_product_group_id": 18,
                                "effective_product_group_level": 3,
                                "effective_product_group_full_name": "物理机 / 西安 / 其他",
                                "leaf": false,
                                "disabled": false,
                                "children": [
                                    {
                                        "id": 69,
                                        "label": "32vcpu-64gib",
                                        "node_type": "product",
                                        "leaf": true,
                                        "disabled": false,
                                        "product_name": "32vcpu-64gib",
                                        "display_name": "32vcpu-64gib",
                                        "product_display_name": "32vcpu-64gib",
                                        "custom_display_name": "",
                                        "cpu_memory_display": "32 vCPU 64G",
                                        "cpu_memory_slug_display": "32vcpu-64gib",
                                        "product_spec_display": "32 vCPU 64G",
                                        "combined_display_name": "32 vCPU 64G",
                                        "effective_product_group_full_name": "物理机 / 西安",
                                        "primary_price": {
                                            "cycle": "monthly",
                                            "amount": "380.00"
                                        },
                                        "status": 1,
                                        "sort_order": 158
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": "group-19",
                        "label": "2",
                        "node_type": "second_product_group",
                        "first_product_group_id": 8,
                        "first_product_group_name": "物理机",
                        "service_type_code": "type_tgynng",
                        "service_type_label": "物理机",
                        "second_product_group_id": 19,
                        "second_product_group_name": "2",
                        "third_product_group_id": null,
                        "third_product_group_name": null,
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 2,
                        "effective_product_group_full_name": "物理机 / 2",
                        "leaf": false,
                        "disabled": false,
                        "children": [
                            {
                                "id": 94,
                                "label": "2vcpu-2gib",
                                "node_type": "product",
                                "leaf": true,
                                "disabled": false,
                                "product_name": "2vcpu-2gib",
                                "display_name": "2vcpu-2gib",
                                "product_display_name": "2vcpu-2gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "2 vCPU 2G",
                                "cpu_memory_slug_display": "2vcpu-2gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-2vcpu-2gib",
                                "effective_product_group_full_name": "物理机 / 2",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "45.00"
                                },
                                "status": 1,
                                "sort_order": 1
                            },
                            {
                                "id": 95,
                                "label": "2vcpu-4gib",
                                "node_type": "product",
                                "leaf": true,
                                "disabled": false,
                                "product_name": "2vcpu-4gib",
                                "display_name": "2vcpu-4gib",
                                "product_display_name": "2vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "2 vCPU 4G",
                                "cpu_memory_slug_display": "2vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-2vcpu-4gib",
                                "effective_product_group_full_name": "物理机 / 2",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "65.00"
                                },
                                "status": 1,
                                "sort_order": 2
                            },
                            {
                                "id": 96,
                                "label": "4vcpu-4gib",
                                "node_type": "product",
                                "leaf": true,
                                "disabled": false,
                                "product_name": "4vcpu-4gib",
                                "display_name": "4vcpu-4gib",
                                "product_display_name": "4vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 4G",
                                "cpu_memory_slug_display": "4vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-4gib",
                                "effective_product_group_full_name": "物理机 / 2",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "85.00"
                                },
                                "status": 1,
                                "sort_order": 3
                            },
                            {
                                "id": 97,
                                "label": "4vcpu-8gib",
                                "node_type": "product",
                                "leaf": true,
                                "disabled": false,
                                "product_name": "4vcpu-8gib",
                                "display_name": "4vcpu-8gib",
                                "product_display_name": "4vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 8G",
                                "cpu_memory_slug_display": "4vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-8gib",
                                "effective_product_group_full_name": "物理机 / 2",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "125.00"
                                },
                                "status": 1,
                                "sort_order": 4
                            },
                            {
                                "id": 98,
                                "label": "8vcpu-8gib",
                                "node_type": "product",
                                "leaf": true,
                                "disabled": false,
                                "product_name": "8vcpu-8gib",
                                "display_name": "8vcpu-8gib",
                                "product_display_name": "8vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 8G",
                                "cpu_memory_slug_display": "8vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-8gib",
                                "effective_product_group_full_name": "物理机 / 2",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "165.00"
                                },
                                "status": 1,
                                "sort_order": 5
                            },
                            {
                                "id": 99,
                                "label": "8vcpu-16gib",
                                "node_type": "product",
                                "leaf": true,
                                "disabled": false,
                                "product_name": "8vcpu-16gib",
                                "display_name": "8vcpu-16gib",
                                "product_display_name": "8vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 16G",
                                "cpu_memory_slug_display": "8vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-16gib",
                                "effective_product_group_full_name": "物理机 / 2",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "245.00"
                                },
                                "status": 1,
                                "sort_order": 6
                            },
                            {
                                "id": 100,
                                "label": "16vcpu-16gib",
                                "node_type": "product",
                                "leaf": true,
                                "disabled": false,
                                "product_name": "16vcpu-16gib",
                                "display_name": "16vcpu-16gib",
                                "product_display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-16gib",
                                "effective_product_group_full_name": "物理机 / 2",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "325.00"
                                },
                                "status": 1,
                                "sort_order": 7
                            },
                            {
                                "id": 101,
                                "label": "16vcpu-32gib",
                                "node_type": "product",
                                "leaf": true,
                                "disabled": false,
                                "product_name": "16vcpu-32gib",
                                "display_name": "16vcpu-32gib",
                                "product_display_name": "16vcpu-32gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 32G",
                                "cpu_memory_slug_display": "16vcpu-32gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-32gib",
                                "effective_product_group_full_name": "物理机 / 2",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "485.00"
                                },
                                "status": 1,
                                "sort_order": 8
                            }
                        ]
                    }
                ]
            },
            {
                "id": "first-10",
                "label": "1",
                "node_type": "first_product_group",
                "first_product_group_id": 10,
                "first_product_group_name": "1",
                "service_type_code": "type_1",
                "service_type_label": "1",
                "leaf": false,
                "disabled": false,
                "children": []
            }
        ]
    },
    "timestamp": 1783240485
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:45  
· 响应状态码：200  
· 调用方式：GET /api/admin/coupons/product-tree  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\CouponController@productTree`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.list`
