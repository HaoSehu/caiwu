# catalog

**请求方法**：GET  
**请求路径**：`/api/site/product-categories/{groupId}/catalog`  
**调试状态**：✅ 通过

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| groupId | integer\|string | 是 | 路径参数；来自路由占位 `{groupId}` |

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
| data.effective_product_group_id | integer | 真实调用返回字段 |
| data.effective_product_group_level | integer | 真实调用返回字段 |
| data.children | array | 真实调用返回字段 |
| data.children.id | integer | 真实调用返回字段 |
| data.children.parent_id | integer | 真实调用返回字段 |
| data.children.product_type | string | 真实调用返回字段 |
| data.children.product_type_id | integer | 真实调用返回字段 |
| data.children.product_type_label | string | 真实调用返回字段 |
| data.children.first_product_group_id | integer | 真实调用返回字段 |
| data.children.first_product_group_code | string | 真实调用返回字段 |
| data.children.first_product_group_name | string | 真实调用返回字段 |
| data.children.second_product_group_id | integer | 真实调用返回字段 |
| data.children.second_product_group_name | string | 真实调用返回字段 |
| data.children.second_product_group_parent_id | integer | 真实调用返回字段 |
| data.children.second_product_group_parent_name | string | 真实调用返回字段 |
| data.children.third_product_group_id | integer | 真实调用返回字段 |
| data.children.third_product_group_name | string | 真实调用返回字段 |
| data.children.effective_product_group_id | integer | 真实调用返回字段 |
| data.children.effective_product_group_level | integer | 真实调用返回字段 |
| data.children.service_type_code | string | 真实调用返回字段 |
| data.children.name | string | 真实调用返回字段 |
| data.children.slogan | string | 真实调用返回字段 |
| data.children.slug | string | 真实调用返回字段 |
| data.children.product_count | integer | 真实调用返回字段 |
| data.items_by_group | array | 真实调用返回字段 |
| data.items_by_group.effective_product_group_id | integer | 真实调用返回字段 |
| data.items_by_group.products | array | 真实调用返回字段 |
| data.items_by_group.products.id | integer | 真实调用返回字段 |
| data.items_by_group.products.name | string | 真实调用返回字段 |
| data.items_by_group.products.display_name | string | 真实调用返回字段 |
| data.items_by_group.products.product_display_name | string | 真实调用返回字段 |
| data.items_by_group.products.combined_display_name | string | 真实调用返回字段 |
| data.items_by_group.products.cpu_memory_display | string | 真实调用返回字段 |
| data.items_by_group.products.instance_spec_id | string | 真实调用返回字段 |
| data.items_by_group.products.instance_spec_value | string | 真实调用返回字段 |
| data.items_by_group.products.instance_spec_text | string | 真实调用返回字段 |
| data.items_by_group.products.instance_spec_alias | string | 真实调用返回字段 |
| data.items_by_group.products.instance_spec_note | string | 真实调用返回字段 |
| data.items_by_group.products.cpu_display | string | 真实调用返回字段 |
| data.items_by_group.products.memory_display | string | 真实调用返回字段 |
| data.items_by_group.products.cpu_model_name | string | 真实调用返回字段 |
| data.items_by_group.products.cpu_base_frequency | string | 真实调用返回字段 |
| data.items_by_group.products.cpu_turbo_frequency | string | 真实调用返回字段 |
| data.items_by_group.products.product_type | string | 真实调用返回字段 |
| data.items_by_group.products.type | string | 真实调用返回字段 |
| data.items_by_group.products.type_label | string | 真实调用返回字段 |
| data.items_by_group.products.first_product_group_id | integer | 真实调用返回字段 |
| data.items_by_group.products.first_product_group_code | string | 真实调用返回字段 |
| data.items_by_group.products.first_product_group_name | string | 真实调用返回字段 |
| data.items_by_group.products.second_product_group_id | integer | 真实调用返回字段 |
| data.items_by_group.products.second_product_group_name | string | 真实调用返回字段 |
| data.items_by_group.products.second_product_group_parent_id | integer | 真实调用返回字段 |
| data.items_by_group.products.second_product_group_parent_name | string | 真实调用返回字段 |
| data.items_by_group.products.third_product_group_id | integer | 真实调用返回字段 |
| data.items_by_group.products.third_product_group_name | string | 真实调用返回字段 |
| data.items_by_group.products.effective_product_group_id | integer | 真实调用返回字段 |
| data.items_by_group.products.effective_product_group_level | integer | 真实调用返回字段 |
| data.items_by_group.products.service_type_code | string | 真实调用返回字段 |
| data.items_by_group.products.pricing | object | 真实调用返回字段 |
| data.items_by_group.products.pricing.monthly | string | 真实调用返回字段 |
| data.items_by_group.products.pricing.annually | string | 真实调用返回字段 |
| data.items_by_group.products.pricing.quarterly | string | 真实调用返回字段 |
| data.items_by_group.products.pricing.semiannually | string | 真实调用返回字段 |
| data.items_by_group.products.pricing_entries | array | 真实调用返回字段 |
| data.items_by_group.products.pricing_entries.cycle | string | 真实调用返回字段 |
| data.items_by_group.products.pricing_entries.label | string | 真实调用返回字段 |
| data.items_by_group.products.pricing_entries.amount | string | 真实调用返回字段 |
| data.items_by_group.products.pricing_entries.setup_fee | string | 真实调用返回字段 |
| data.items_by_group.products.pricing_entries.total_amount | string | 真实调用返回字段 |
| data.items_by_group.products.primary_cycle | string | 真实调用返回字段 |
| data.items_by_group.products.primary_price | string | 真实调用返回字段 |
| data.items_by_group.products.setup_fee | string | 真实调用返回字段 |
| data.items_by_group.products.stock | integer | 真实调用返回字段 |
| data.items_by_group.products.auto_setup | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "effective_product_group_id": 1,
        "effective_product_group_level": 2,
        "children": [
            {
                "id": 3,
                "parent_id": 1,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 1,
                "second_product_group_name": "美国",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": 3,
                "third_product_group_name": "三网精品",
                "effective_product_group_id": 3,
                "effective_product_group_level": 3,
                "service_type_code": "vps",
                "name": "三网精品",
                "slogan": "CN2+CMIN2+9929三网精品，30G DDOS防御 黑洞10分钟 测试IP 156.238.224.1（kurun机房） CPU:E5 2696V4*2/2698/2699V4*2",
                "slug": "group-4",
                "product_count": 5
            },
            {
                "id": 5,
                "parent_id": 1,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 1,
                "second_product_group_name": "美国",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": 5,
                "third_product_group_name": "高性能",
                "effective_product_group_id": 5,
                "effective_product_group_level": 3,
                "service_type_code": "vps",
                "name": "高性能",
                "slogan": "三网去程CN2+CMIN2+4837 三网CN2+CMIN2+9929精品回国，10G DDOS防御 黑洞10分钟 测试IP 154.64.232.1 CPU:EPYC7532区域不支持win",
                "slug": "group-6",
                "product_count": 5
            },
            {
                "id": 19,
                "parent_id": 1,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 1,
                "second_product_group_name": "美国",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": 19,
                "third_product_group_name": "家宽",
                "effective_product_group_id": 19,
                "effective_product_group_level": 3,
                "service_type_code": "vps",
                "name": "家宽",
                "slogan": "",
                "slug": "category-10",
                "product_count": 8
            },
            {
                "id": 20,
                "parent_id": 1,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
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
                "name": "高宽",
                "slogan": "200G防御 AMD EPYC处理器 测试ip：154.29.148.0/24",
                "slug": "category-11",
                "product_count": 30
            }
        ],
        "items_by_group": [
            {
                "effective_product_group_id": 1,
                "products": [
                    {
                        "id": 6,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-2gib",
                        "cpu_memory_display": "2 vCPU 2G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "2G",
                        "cpu_model_name": "Intel Xeon E5-2673v4/Gold6133",
                        "cpu_base_frequency": "2.3&2.5",
                        "cpu_turbo_frequency": "2.6&3.0",
                        "product_type": "vps",
                        "type": "vps",
                        "type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_code": "vps",
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "second_product_group_parent_id": 1,
                        "second_product_group_parent_name": "云服务器",
                        "third_product_group_id": 1,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "20.00",
                            "annually": "240.00",
                            "quarterly": "60.00",
                            "semiannually": "120.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "20.00",
                                "setup_fee": "0.00",
                                "total_amount": "20.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "240.00",
                                "setup_fee": "0.00",
                                "total_amount": "240.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "60.00",
                                "setup_fee": "0.00",
                                "total_amount": "60.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "120.00",
                                "setup_fee": "0.00",
                                "total_amount": "120.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "20.00",
                        "setup_fee": "0.00",
                        "stock": 33,
                        "auto_setup": 1
                    },
                    {
                        "id": 7,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "cpu_memory_display": "4 vCPU 4G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "4G",
                        "cpu_model_name": "Intel Xeon E5-2673v4/Gold6133",
                        "cpu_base_frequency": "2.3&2.5",
                        "cpu_turbo_frequency": "2.6&3.0",
                        "product_type": "vps",
                        "type": "vps",
                        "type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_code": "vps",
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "second_product_group_parent_id": 1,
                        "second_product_group_parent_name": "云服务器",
                        "third_product_group_id": 1,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "30.00",
                            "annually": "360.00",
                            "quarterly": "90.00",
                            "semiannually": "180.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "30.00",
                                "setup_fee": "0.00",
                                "total_amount": "30.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "360.00",
                                "setup_fee": "0.00",
                                "total_amount": "360.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "90.00",
                                "setup_fee": "0.00",
                                "total_amount": "90.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "180.00",
                                "setup_fee": "0.00",
                                "total_amount": "180.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "30.00",
                        "setup_fee": "0.00",
                        "stock": 3,
                        "auto_setup": 1
                    },
                    {
                        "id": 8,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-8gib",
                        "cpu_memory_display": "4 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "Intel Xeon E5-2673v4/Gold6133",
                        "cpu_base_frequency": "2.3&2.5",
                        "cpu_turbo_frequency": "2.6&3.0",
                        "product_type": "vps",
                        "type": "vps",
                        "type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_code": "vps",
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "second_product_group_parent_id": 1,
                        "second_product_group_parent_name": "云服务器",
                        "third_product_group_id": 1,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "40.00",
                            "annually": "480.00",
                            "quarterly": "120.00",
                            "semiannually": "240.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "40.00",
                                "setup_fee": "0.00",
                                "total_amount": "40.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "480.00",
                                "setup_fee": "0.00",
                                "total_amount": "480.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "120.00",
                                "setup_fee": "0.00",
                                "total_amount": "120.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "240.00",
                                "setup_fee": "0.00",
                                "total_amount": "240.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "40.00",
                        "setup_fee": "0.00",
                        "stock": 36,
                        "auto_setup": 1
                    },
                    {
                        "id": 9,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "cpu_memory_display": "8 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "Intel Xeon E5-2673v4/Gold6133",
                        "cpu_base_frequency": "2.3&2.5",
                        "cpu_turbo_frequency": "2.6&3.0",
                        "product_type": "vps",
                        "type": "vps",
                        "type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_code": "vps",
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "second_product_group_parent_id": 1,
                        "second_product_group_parent_name": "云服务器",
                        "third_product_group_id": 1,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "60.00",
                            "annually": "720.00",
                            "quarterly": "180.00",
                            "semiannually": "360.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "60.00",
                                "setup_fee": "0.00",
                                "total_amount": "60.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "720.00",
                                "setup_fee": "0.00",
                                "total_amount": "720.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "180.00",
                                "setup_fee": "0.00",
                                "total_amount": "180.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "360.00",
                                "setup_fee": "0.00",
                                "total_amount": "360.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "60.00",
                        "setup_fee": "0.00",
                        "stock": 5,
                        "auto_setup": 1
                    },
                    {
                        "id": 10,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-16gib",
                        "cpu_memory_display": "16 vCPU 16G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "16G",
                        "cpu_model_name": "Intel Xeon E5-2673v4/Gold6133",
                        "cpu_base_frequency": "2.3&2.5",
                        "cpu_turbo_frequency": "2.6&3.0",
                        "product_type": "vps",
                        "type": "vps",
                        "type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_code": "vps",
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "second_product_group_parent_id": 1,
                        "second_product_group_parent_name": "云服务器",
                        "third_product_group_id": 1,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "120.00",
                            "annually": "1440.00",
                            "quarterly": "360.00",
                            "semiannually": "720.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "120.00",
                                "setup_fee": "0.00",
                                "total_amount": "120.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1440.00",
                                "setup_fee": "0.00",
                                "total_amount": "1440.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "360.00",
                                "setup_fee": "0.00",
                                "total_amount": "360.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "720.00",
                                "setup_fee": "0.00",
                                "total_amount": "720.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "120.00",
                        "setup_fee": "0.00",
                        "stock": 1,
                        "auto_setup": 1
                    }
                ]
            },
            {
                "effective_product_group_id": 3,
                "products": [
                    {
                        "id": 1,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-2gib",
                        "cpu_memory_display": "2 vCPU 2G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "2G",
                        "cpu_model_name": "Intel Xeon E5‑26** v4",
                        "cpu_base_frequency": "2.2GHz",
                        "cpu_turbo_frequency": "3.6GHz",
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
                        "third_product_group_id": 3,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "20.00",
                            "annually": "240.00",
                            "quarterly": "60.00",
                            "semiannually": "120.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "20.00",
                                "setup_fee": "0.00",
                                "total_amount": "20.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "240.00",
                                "setup_fee": "0.00",
                                "total_amount": "240.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "60.00",
                                "setup_fee": "0.00",
                                "total_amount": "60.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "120.00",
                                "setup_fee": "0.00",
                                "total_amount": "120.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "20.00",
                        "setup_fee": "0.00",
                        "stock": 98,
                        "auto_setup": 1
                    },
                    {
                        "id": 2,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "cpu_memory_display": "4 vCPU 4G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "4G",
                        "cpu_model_name": "Intel Xeon E5‑26** v4",
                        "cpu_base_frequency": "2.2GHz",
                        "cpu_turbo_frequency": "3.6GHz",
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
                        "third_product_group_id": 3,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "25.00",
                            "annually": "300.00",
                            "quarterly": "75.00",
                            "semiannually": "150.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "25.00",
                                "setup_fee": "0.00",
                                "total_amount": "25.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "300.00",
                                "setup_fee": "0.00",
                                "total_amount": "300.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "75.00",
                                "setup_fee": "0.00",
                                "total_amount": "75.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "150.00",
                                "setup_fee": "0.00",
                                "total_amount": "150.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "25.00",
                        "setup_fee": "0.00",
                        "stock": 0,
                        "auto_setup": 1
                    },
                    {
                        "id": 5,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-8gib",
                        "cpu_memory_display": "4 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "Intel Xeon E5‑26** v4",
                        "cpu_base_frequency": "2.2GHz",
                        "cpu_turbo_frequency": "3.6GHz",
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
                        "third_product_group_id": 3,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "35.00",
                            "annually": "420.00",
                            "quarterly": "105.00",
                            "semiannually": "210.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "35.00",
                                "setup_fee": "0.00",
                                "total_amount": "35.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "420.00",
                                "setup_fee": "0.00",
                                "total_amount": "420.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "105.00",
                                "setup_fee": "0.00",
                                "total_amount": "105.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "210.00",
                                "setup_fee": "0.00",
                                "total_amount": "210.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "35.00",
                        "setup_fee": "0.00",
                        "stock": 77,
                        "auto_setup": 1
                    },
                    {
                        "id": 3,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "cpu_memory_display": "8 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "Intel Xeon E5‑26** v4",
                        "cpu_base_frequency": "2.2GHz",
                        "cpu_turbo_frequency": "3.6GHz",
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
                        "third_product_group_id": 3,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "60.00",
                            "annually": "720.00",
                            "quarterly": "180.00",
                            "semiannually": "360.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "60.00",
                                "setup_fee": "0.00",
                                "total_amount": "60.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "720.00",
                                "setup_fee": "0.00",
                                "total_amount": "720.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "180.00",
                                "setup_fee": "0.00",
                                "total_amount": "180.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "360.00",
                                "setup_fee": "0.00",
                                "total_amount": "360.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "60.00",
                        "setup_fee": "0.00",
                        "stock": 54,
                        "auto_setup": 1
                    },
                    {
                        "id": 4,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-16gib",
                        "cpu_memory_display": "16 vCPU 16G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "16G",
                        "cpu_model_name": "Intel Xeon E5‑26** v4",
                        "cpu_base_frequency": "2.2GHz",
                        "cpu_turbo_frequency": "3.6GHz",
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
                        "third_product_group_id": 3,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "120.00",
                            "annually": "1440.00",
                            "quarterly": "360.00",
                            "semiannually": "720.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "120.00",
                                "setup_fee": "0.00",
                                "total_amount": "120.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1440.00",
                                "setup_fee": "0.00",
                                "total_amount": "1440.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "360.00",
                                "setup_fee": "0.00",
                                "total_amount": "360.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "720.00",
                                "setup_fee": "0.00",
                                "total_amount": "720.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "120.00",
                        "setup_fee": "0.00",
                        "stock": 90,
                        "auto_setup": 1
                    }
                ]
            },
            {
                "effective_product_group_id": 5,
                "products": [
                    {
                        "id": 47,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-2gib",
                        "cpu_memory_display": "2 vCPU 2G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "2G",
                        "cpu_model_name": "AMD EPYC 7532",
                        "cpu_base_frequency": "2.4GHz",
                        "cpu_turbo_frequency": "3.3GHz",
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
                        "third_product_group_id": 5,
                        "third_product_group_name": "高性能",
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "22.00",
                            "annually": "264.00",
                            "quarterly": "66.00",
                            "semiannually": "132.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "22.00",
                                "setup_fee": "0.00",
                                "total_amount": "22.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "264.00",
                                "setup_fee": "0.00",
                                "total_amount": "264.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "66.00",
                                "setup_fee": "0.00",
                                "total_amount": "66.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "132.00",
                                "setup_fee": "0.00",
                                "total_amount": "132.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "22.00",
                        "setup_fee": "0.00",
                        "stock": 6,
                        "auto_setup": 1
                    },
                    {
                        "id": 48,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "cpu_memory_display": "4 vCPU 4G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "4G",
                        "cpu_model_name": "AMD EPYC 7532",
                        "cpu_base_frequency": "2.4GHz",
                        "cpu_turbo_frequency": "3.3GHz",
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
                        "third_product_group_id": 5,
                        "third_product_group_name": "高性能",
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "30.00",
                            "annually": "360.00",
                            "quarterly": "90.00",
                            "semiannually": "180.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "30.00",
                                "setup_fee": "0.00",
                                "total_amount": "30.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "360.00",
                                "setup_fee": "0.00",
                                "total_amount": "360.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "90.00",
                                "setup_fee": "0.00",
                                "total_amount": "90.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "180.00",
                                "setup_fee": "0.00",
                                "total_amount": "180.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "30.00",
                        "setup_fee": "0.00",
                        "stock": 2,
                        "auto_setup": 1
                    },
                    {
                        "id": 49,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-8gib",
                        "cpu_memory_display": "4 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "AMD EPYC 7532",
                        "cpu_base_frequency": "2.4GHz",
                        "cpu_turbo_frequency": "3.3GHz",
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
                        "third_product_group_id": 5,
                        "third_product_group_name": "高性能",
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "40.00",
                            "annually": "480.00",
                            "quarterly": "120.00",
                            "semiannually": "240.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "40.00",
                                "setup_fee": "0.00",
                                "total_amount": "40.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "480.00",
                                "setup_fee": "0.00",
                                "total_amount": "480.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "120.00",
                                "setup_fee": "0.00",
                                "total_amount": "120.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "240.00",
                                "setup_fee": "0.00",
                                "total_amount": "240.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "40.00",
                        "setup_fee": "0.00",
                        "stock": 6,
                        "auto_setup": 1
                    },
                    {
                        "id": 50,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "cpu_memory_display": "8 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "AMD EPYC 7532",
                        "cpu_base_frequency": "2.4GHz",
                        "cpu_turbo_frequency": "3.3GHz",
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
                        "third_product_group_id": 5,
                        "third_product_group_name": "高性能",
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "60.00",
                            "annually": "720.00",
                            "quarterly": "180.00",
                            "semiannually": "360.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "60.00",
                                "setup_fee": "0.00",
                                "total_amount": "60.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "720.00",
                                "setup_fee": "0.00",
                                "total_amount": "720.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "180.00",
                                "setup_fee": "0.00",
                                "total_amount": "180.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "360.00",
                                "setup_fee": "0.00",
                                "total_amount": "360.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "60.00",
                        "setup_fee": "0.00",
                        "stock": 1,
                        "auto_setup": 1
                    },
                    {
                        "id": 51,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-16gib",
                        "cpu_memory_display": "16 vCPU 16G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "16G",
                        "cpu_model_name": "AMD EPYC 7532",
                        "cpu_base_frequency": "2.4GHz",
                        "cpu_turbo_frequency": "3.3GHz",
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
                        "third_product_group_id": 5,
                        "third_product_group_name": "高性能",
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "120.00",
                            "annually": "1440.00",
                            "quarterly": "360.00",
                            "semiannually": "720.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "120.00",
                                "setup_fee": "0.00",
                                "total_amount": "120.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1440.00",
                                "setup_fee": "0.00",
                                "total_amount": "1440.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "360.00",
                                "setup_fee": "0.00",
                                "total_amount": "360.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "720.00",
                                "setup_fee": "0.00",
                                "total_amount": "720.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "120.00",
                        "setup_fee": "0.00",
                        "stock": 1,
                        "auto_setup": 1
                    }
                ]
            },
            {
                "effective_product_group_id": 19,
                "products": [
                    {
                        "id": 94,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-2gib",
                        "cpu_memory_display": "2 vCPU 2G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "2G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "45.00",
                            "annually": "540.00",
                            "quarterly": "135.00",
                            "semiannually": "270.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "45.00",
                                "setup_fee": "0.00",
                                "total_amount": "45.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "540.00",
                                "setup_fee": "0.00",
                                "total_amount": "540.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "135.00",
                                "setup_fee": "0.00",
                                "total_amount": "135.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "270.00",
                                "setup_fee": "0.00",
                                "total_amount": "270.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "45.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 95,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-4gib",
                        "cpu_memory_display": "2 vCPU 4G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "4G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "65.00",
                            "annually": "780.00",
                            "quarterly": "195.00",
                            "semiannually": "390.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "65.00",
                                "setup_fee": "0.00",
                                "total_amount": "65.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "780.00",
                                "setup_fee": "0.00",
                                "total_amount": "780.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "195.00",
                                "setup_fee": "0.00",
                                "total_amount": "195.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "390.00",
                                "setup_fee": "0.00",
                                "total_amount": "390.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "65.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 96,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "cpu_memory_display": "4 vCPU 4G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "4G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "85.00",
                            "annually": "1020.00",
                            "quarterly": "255.00",
                            "semiannually": "510.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "85.00",
                                "setup_fee": "0.00",
                                "total_amount": "85.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1020.00",
                                "setup_fee": "0.00",
                                "total_amount": "1020.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "255.00",
                                "setup_fee": "0.00",
                                "total_amount": "255.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "510.00",
                                "setup_fee": "0.00",
                                "total_amount": "510.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "85.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 97,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-8gib",
                        "cpu_memory_display": "4 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "125.00",
                            "annually": "1500.00",
                            "quarterly": "375.00",
                            "semiannually": "750.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "125.00",
                                "setup_fee": "0.00",
                                "total_amount": "125.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1500.00",
                                "setup_fee": "0.00",
                                "total_amount": "1500.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "375.00",
                                "setup_fee": "0.00",
                                "total_amount": "375.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "750.00",
                                "setup_fee": "0.00",
                                "total_amount": "750.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "125.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 98,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "cpu_memory_display": "8 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "165.00",
                            "annually": "1980.00",
                            "quarterly": "495.00",
                            "semiannually": "990.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "165.00",
                                "setup_fee": "0.00",
                                "total_amount": "165.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1980.00",
                                "setup_fee": "0.00",
                                "total_amount": "1980.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "495.00",
                                "setup_fee": "0.00",
                                "total_amount": "495.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "990.00",
                                "setup_fee": "0.00",
                                "total_amount": "990.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "165.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 99,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-16gib",
                        "cpu_memory_display": "8 vCPU 16G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "16G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "245.00",
                            "annually": "2940.00",
                            "quarterly": "735.00",
                            "semiannually": "1470.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "245.00",
                                "setup_fee": "0.00",
                                "total_amount": "245.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "2940.00",
                                "setup_fee": "0.00",
                                "total_amount": "2940.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "735.00",
                                "setup_fee": "0.00",
                                "total_amount": "735.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1470.00",
                                "setup_fee": "0.00",
                                "total_amount": "1470.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "245.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 100,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-16gib",
                        "cpu_memory_display": "16 vCPU 16G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "16G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "325.00",
                            "annually": "3900.00",
                            "quarterly": "975.00",
                            "semiannually": "1950.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "325.00",
                                "setup_fee": "0.00",
                                "total_amount": "325.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "3900.00",
                                "setup_fee": "0.00",
                                "total_amount": "3900.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "975.00",
                                "setup_fee": "0.00",
                                "total_amount": "975.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1950.00",
                                "setup_fee": "0.00",
                                "total_amount": "1950.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "325.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 101,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-32gib",
                        "cpu_memory_display": "16 vCPU 32G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "32G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3,
                        "service_type_code": "vps",
                        "pricing": {
                            "monthly": "485.00",
                            "annually": "5820.00",
                            "quarterly": "1455.00",
                            "semiannually": "2910.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "485.00",
                                "setup_fee": "0.00",
                                "total_amount": "485.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "5820.00",
                                "setup_fee": "0.00",
                                "total_amount": "5820.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "1455.00",
                                "setup_fee": "0.00",
                                "total_amount": "1455.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "2910.00",
                                "setup_fee": "0.00",
                                "total_amount": "2910.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "485.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    }
                ]
            },
            {
                "effective_product_group_id": 20,
                "products": [
                    {
                        "id": 102,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-2gib",
                        "cpu_memory_display": "2 vCPU 2G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "2G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "45.00",
                            "annually": "540.00",
                            "quarterly": "135.00",
                            "semiannually": "270.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "45.00",
                                "setup_fee": "0.00",
                                "total_amount": "45.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "540.00",
                                "setup_fee": "0.00",
                                "total_amount": "540.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "135.00",
                                "setup_fee": "0.00",
                                "total_amount": "135.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "270.00",
                                "setup_fee": "0.00",
                                "total_amount": "270.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "45.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 121,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-4gib",
                        "cpu_memory_display": "2 vCPU 4G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "4G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "61.00",
                            "annually": "732.00",
                            "quarterly": "183.00",
                            "semiannually": "366.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "61.00",
                                "setup_fee": "0.00",
                                "total_amount": "61.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "732.00",
                                "setup_fee": "0.00",
                                "total_amount": "732.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "183.00",
                                "setup_fee": "0.00",
                                "total_amount": "183.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "366.00",
                                "setup_fee": "0.00",
                                "total_amount": "366.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "61.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 122,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-8gib",
                        "cpu_memory_display": "2 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "93.00",
                            "annually": "1116.00",
                            "quarterly": "279.00",
                            "semiannually": "558.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "93.00",
                                "setup_fee": "0.00",
                                "total_amount": "93.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1116.00",
                                "setup_fee": "0.00",
                                "total_amount": "1116.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "279.00",
                                "setup_fee": "0.00",
                                "total_amount": "279.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "558.00",
                                "setup_fee": "0.00",
                                "total_amount": "558.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "93.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 123,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-16gib",
                        "cpu_memory_display": "2 vCPU 16G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "16G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "157.00",
                            "annually": "1884.00",
                            "quarterly": "471.00",
                            "semiannually": "942.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "157.00",
                                "setup_fee": "0.00",
                                "total_amount": "157.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1884.00",
                                "setup_fee": "0.00",
                                "total_amount": "1884.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "471.00",
                                "setup_fee": "0.00",
                                "total_amount": "471.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "942.00",
                                "setup_fee": "0.00",
                                "total_amount": "942.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "157.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 124,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-32gib",
                        "cpu_memory_display": "2 vCPU 32G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "32G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "285.00",
                            "annually": "3420.00",
                            "quarterly": "855.00",
                            "semiannually": "1710.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "285.00",
                                "setup_fee": "0.00",
                                "total_amount": "285.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "3420.00",
                                "setup_fee": "0.00",
                                "total_amount": "3420.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "855.00",
                                "setup_fee": "0.00",
                                "total_amount": "855.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1710.00",
                                "setup_fee": "0.00",
                                "total_amount": "1710.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "285.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 125,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-2vcpu-64gib",
                        "cpu_memory_display": "2 vCPU 64G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "2 vCPU",
                        "memory_display": "64G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "541.00",
                            "annually": "6492.00",
                            "quarterly": "1623.00",
                            "semiannually": "3246.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "541.00",
                                "setup_fee": "0.00",
                                "total_amount": "541.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "6492.00",
                                "setup_fee": "0.00",
                                "total_amount": "6492.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "1623.00",
                                "setup_fee": "0.00",
                                "total_amount": "1623.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "3246.00",
                                "setup_fee": "0.00",
                                "total_amount": "3246.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "541.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 126,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-2gib",
                        "cpu_memory_display": "4 vCPU 2G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "2G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "61.00",
                            "annually": "732.00",
                            "quarterly": "183.00",
                            "semiannually": "366.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "61.00",
                                "setup_fee": "0.00",
                                "total_amount": "61.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "732.00",
                                "setup_fee": "0.00",
                                "total_amount": "732.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "183.00",
                                "setup_fee": "0.00",
                                "total_amount": "183.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "366.00",
                                "setup_fee": "0.00",
                                "total_amount": "366.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "61.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 127,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "cpu_memory_display": "4 vCPU 4G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "4G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "77.00",
                            "annually": "924.00",
                            "quarterly": "231.00",
                            "semiannually": "462.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "77.00",
                                "setup_fee": "0.00",
                                "total_amount": "77.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "924.00",
                                "setup_fee": "0.00",
                                "total_amount": "924.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "231.00",
                                "setup_fee": "0.00",
                                "total_amount": "231.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "462.00",
                                "setup_fee": "0.00",
                                "total_amount": "462.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "77.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 128,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-8gib",
                        "cpu_memory_display": "4 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "109.00",
                            "annually": "1308.00",
                            "quarterly": "327.00",
                            "semiannually": "654.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "109.00",
                                "setup_fee": "0.00",
                                "total_amount": "109.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1308.00",
                                "setup_fee": "0.00",
                                "total_amount": "1308.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "327.00",
                                "setup_fee": "0.00",
                                "total_amount": "327.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "654.00",
                                "setup_fee": "0.00",
                                "total_amount": "654.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "109.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 129,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-16gib",
                        "cpu_memory_display": "4 vCPU 16G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "16G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "173.00",
                            "annually": "2076.00",
                            "quarterly": "519.00",
                            "semiannually": "1038.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "173.00",
                                "setup_fee": "0.00",
                                "total_amount": "173.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "2076.00",
                                "setup_fee": "0.00",
                                "total_amount": "2076.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "519.00",
                                "setup_fee": "0.00",
                                "total_amount": "519.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1038.00",
                                "setup_fee": "0.00",
                                "total_amount": "1038.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "173.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 130,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-32gib",
                        "cpu_memory_display": "4 vCPU 32G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "32G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "301.00",
                            "annually": "3612.00",
                            "quarterly": "903.00",
                            "semiannually": "1806.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "301.00",
                                "setup_fee": "0.00",
                                "total_amount": "301.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "3612.00",
                                "setup_fee": "0.00",
                                "total_amount": "3612.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "903.00",
                                "setup_fee": "0.00",
                                "total_amount": "903.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1806.00",
                                "setup_fee": "0.00",
                                "total_amount": "1806.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "301.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 131,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-4vcpu-64gib",
                        "cpu_memory_display": "4 vCPU 64G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "4 vCPU",
                        "memory_display": "64G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "557.00",
                            "annually": "6684.00",
                            "quarterly": "1671.00",
                            "semiannually": "3342.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "557.00",
                                "setup_fee": "0.00",
                                "total_amount": "557.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "6684.00",
                                "setup_fee": "0.00",
                                "total_amount": "6684.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "1671.00",
                                "setup_fee": "0.00",
                                "total_amount": "1671.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "3342.00",
                                "setup_fee": "0.00",
                                "total_amount": "3342.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "557.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 132,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-2gib",
                        "cpu_memory_display": "8 vCPU 2G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "2G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "93.00",
                            "annually": "1116.00",
                            "quarterly": "279.00",
                            "semiannually": "558.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "93.00",
                                "setup_fee": "0.00",
                                "total_amount": "93.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1116.00",
                                "setup_fee": "0.00",
                                "total_amount": "1116.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "279.00",
                                "setup_fee": "0.00",
                                "total_amount": "279.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "558.00",
                                "setup_fee": "0.00",
                                "total_amount": "558.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "93.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 133,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-4gib",
                        "cpu_memory_display": "8 vCPU 4G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "4G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "109.00",
                            "annually": "1308.00",
                            "quarterly": "327.00",
                            "semiannually": "654.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "109.00",
                                "setup_fee": "0.00",
                                "total_amount": "109.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1308.00",
                                "setup_fee": "0.00",
                                "total_amount": "1308.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "327.00",
                                "setup_fee": "0.00",
                                "total_amount": "327.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "654.00",
                                "setup_fee": "0.00",
                                "total_amount": "654.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "109.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 134,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "cpu_memory_display": "8 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "141.00",
                            "annually": "1692.00",
                            "quarterly": "423.00",
                            "semiannually": "846.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "141.00",
                                "setup_fee": "0.00",
                                "total_amount": "141.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1692.00",
                                "setup_fee": "0.00",
                                "total_amount": "1692.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "423.00",
                                "setup_fee": "0.00",
                                "total_amount": "423.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "846.00",
                                "setup_fee": "0.00",
                                "total_amount": "846.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "141.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 135,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-16gib",
                        "cpu_memory_display": "8 vCPU 16G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "16G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "205.00",
                            "annually": "2460.00",
                            "quarterly": "615.00",
                            "semiannually": "1230.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "205.00",
                                "setup_fee": "0.00",
                                "total_amount": "205.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "2460.00",
                                "setup_fee": "0.00",
                                "total_amount": "2460.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "615.00",
                                "setup_fee": "0.00",
                                "total_amount": "615.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1230.00",
                                "setup_fee": "0.00",
                                "total_amount": "1230.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "205.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 136,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-32gib",
                        "cpu_memory_display": "8 vCPU 32G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "32G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "333.00",
                            "annually": "3996.00",
                            "quarterly": "999.00",
                            "semiannually": "1998.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "333.00",
                                "setup_fee": "0.00",
                                "total_amount": "333.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "3996.00",
                                "setup_fee": "0.00",
                                "total_amount": "3996.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "999.00",
                                "setup_fee": "0.00",
                                "total_amount": "999.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1998.00",
                                "setup_fee": "0.00",
                                "total_amount": "1998.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "333.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 137,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-8vcpu-64gib",
                        "cpu_memory_display": "8 vCPU 64G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "8 vCPU",
                        "memory_display": "64G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "589.00",
                            "annually": "7068.00",
                            "quarterly": "1767.00",
                            "semiannually": "3534.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "589.00",
                                "setup_fee": "0.00",
                                "total_amount": "589.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "7068.00",
                                "setup_fee": "0.00",
                                "total_amount": "7068.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "1767.00",
                                "setup_fee": "0.00",
                                "total_amount": "1767.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "3534.00",
                                "setup_fee": "0.00",
                                "total_amount": "3534.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "589.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 138,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-2gib",
                        "cpu_memory_display": "16 vCPU 2G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "2G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "157.00",
                            "annually": "1884.00",
                            "quarterly": "471.00",
                            "semiannually": "942.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "157.00",
                                "setup_fee": "0.00",
                                "total_amount": "157.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "1884.00",
                                "setup_fee": "0.00",
                                "total_amount": "1884.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "471.00",
                                "setup_fee": "0.00",
                                "total_amount": "471.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "942.00",
                                "setup_fee": "0.00",
                                "total_amount": "942.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "157.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 139,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-4gib",
                        "cpu_memory_display": "16 vCPU 4G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "4G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "173.00",
                            "annually": "2076.00",
                            "quarterly": "519.00",
                            "semiannually": "1038.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "173.00",
                                "setup_fee": "0.00",
                                "total_amount": "173.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "2076.00",
                                "setup_fee": "0.00",
                                "total_amount": "2076.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "519.00",
                                "setup_fee": "0.00",
                                "total_amount": "519.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1038.00",
                                "setup_fee": "0.00",
                                "total_amount": "1038.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "173.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 140,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-8gib",
                        "cpu_memory_display": "16 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "205.00",
                            "annually": "2460.00",
                            "quarterly": "615.00",
                            "semiannually": "1230.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "205.00",
                                "setup_fee": "0.00",
                                "total_amount": "205.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "2460.00",
                                "setup_fee": "0.00",
                                "total_amount": "2460.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "615.00",
                                "setup_fee": "0.00",
                                "total_amount": "615.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1230.00",
                                "setup_fee": "0.00",
                                "total_amount": "1230.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "205.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 141,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-16gib",
                        "cpu_memory_display": "16 vCPU 16G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "16G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "269.00",
                            "annually": "3228.00",
                            "quarterly": "807.00",
                            "semiannually": "1614.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "269.00",
                                "setup_fee": "0.00",
                                "total_amount": "269.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "3228.00",
                                "setup_fee": "0.00",
                                "total_amount": "3228.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "807.00",
                                "setup_fee": "0.00",
                                "total_amount": "807.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1614.00",
                                "setup_fee": "0.00",
                                "total_amount": "1614.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "269.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 142,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-32gib",
                        "cpu_memory_display": "16 vCPU 32G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "32G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "397.00",
                            "annually": "4764.00",
                            "quarterly": "1191.00",
                            "semiannually": "2382.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "397.00",
                                "setup_fee": "0.00",
                                "total_amount": "397.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "4764.00",
                                "setup_fee": "0.00",
                                "total_amount": "4764.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "1191.00",
                                "setup_fee": "0.00",
                                "total_amount": "1191.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "2382.00",
                                "setup_fee": "0.00",
                                "total_amount": "2382.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "397.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 143,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-16vcpu-64gib",
                        "cpu_memory_display": "16 vCPU 64G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "16 vCPU",
                        "memory_display": "64G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "653.00",
                            "annually": "7836.00",
                            "quarterly": "1959.00",
                            "semiannually": "3918.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "653.00",
                                "setup_fee": "0.00",
                                "total_amount": "653.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "7836.00",
                                "setup_fee": "0.00",
                                "total_amount": "7836.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "1959.00",
                                "setup_fee": "0.00",
                                "total_amount": "1959.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "3918.00",
                                "setup_fee": "0.00",
                                "total_amount": "3918.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "653.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 144,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-32vcpu-2gib",
                        "cpu_memory_display": "32 vCPU 2G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "32 vCPU",
                        "memory_display": "2G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "285.00",
                            "annually": "3420.00",
                            "quarterly": "855.00",
                            "semiannually": "1710.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "285.00",
                                "setup_fee": "0.00",
                                "total_amount": "285.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "3420.00",
                                "setup_fee": "0.00",
                                "total_amount": "3420.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "855.00",
                                "setup_fee": "0.00",
                                "total_amount": "855.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1710.00",
                                "setup_fee": "0.00",
                                "total_amount": "1710.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "285.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 145,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-32vcpu-4gib",
                        "cpu_memory_display": "32 vCPU 4G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "32 vCPU",
                        "memory_display": "4G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "301.00",
                            "annually": "3612.00",
                            "quarterly": "903.00",
                            "semiannually": "1806.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "301.00",
                                "setup_fee": "0.00",
                                "total_amount": "301.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "3612.00",
                                "setup_fee": "0.00",
                                "total_amount": "3612.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "903.00",
                                "setup_fee": "0.00",
                                "total_amount": "903.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1806.00",
                                "setup_fee": "0.00",
                                "total_amount": "1806.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "301.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 146,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-32vcpu-8gib",
                        "cpu_memory_display": "32 vCPU 8G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "32 vCPU",
                        "memory_display": "8G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "333.00",
                            "annually": "3996.00",
                            "quarterly": "999.00",
                            "semiannually": "1998.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "333.00",
                                "setup_fee": "0.00",
                                "total_amount": "333.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "3996.00",
                                "setup_fee": "0.00",
                                "total_amount": "3996.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "999.00",
                                "setup_fee": "0.00",
                                "total_amount": "999.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "1998.00",
                                "setup_fee": "0.00",
                                "total_amount": "1998.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "333.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 147,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-32vcpu-16gib",
                        "cpu_memory_display": "32 vCPU 16G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "32 vCPU",
                        "memory_display": "16G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "397.00",
                            "annually": "4764.00",
                            "quarterly": "1191.00",
                            "semiannually": "2382.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "397.00",
                                "setup_fee": "0.00",
                                "total_amount": "397.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "4764.00",
                                "setup_fee": "0.00",
                                "total_amount": "4764.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "1191.00",
                                "setup_fee": "0.00",
                                "total_amount": "1191.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "2382.00",
                                "setup_fee": "0.00",
                                "total_amount": "2382.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "397.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 148,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-32vcpu-32gib",
                        "cpu_memory_display": "32 vCPU 32G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "32 vCPU",
                        "memory_display": "32G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "525.00",
                            "annually": "6300.00",
                            "quarterly": "1575.00",
                            "semiannually": "3150.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "525.00",
                                "setup_fee": "0.00",
                                "total_amount": "525.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "6300.00",
                                "setup_fee": "0.00",
                                "total_amount": "6300.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "1575.00",
                                "setup_fee": "0.00",
                                "total_amount": "1575.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "3150.00",
                                "setup_fee": "0.00",
                                "total_amount": "3150.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "525.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    },
                    {
                        "id": 149,
                        "name": "gscs",
                        "display_name": "gscs",
                        "product_display_name": "gscs",
                        "combined_display_name": "gscs-32vcpu-64gib",
                        "cpu_memory_display": "32 vCPU 64G",
                        "instance_spec_id": "spec_1779808447596_mux9rb",
                        "instance_spec_value": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "instance_spec_note": "通用共享",
                        "cpu_display": "32 vCPU",
                        "memory_display": "64G",
                        "cpu_model_name": "",
                        "cpu_base_frequency": "",
                        "cpu_turbo_frequency": "",
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
                        "pricing": {
                            "monthly": "781.00",
                            "annually": "9372.00",
                            "quarterly": "2343.00",
                            "semiannually": "4686.00"
                        },
                        "pricing_entries": [
                            {
                                "cycle": "monthly",
                                "label": "月付",
                                "amount": "781.00",
                                "setup_fee": "0.00",
                                "total_amount": "781.00"
                            },
                            {
                                "cycle": "annually",
                                "label": "年付",
                                "amount": "9372.00",
                                "setup_fee": "0.00",
                                "total_amount": "9372.00"
                            },
                            {
                                "cycle": "quarterly",
                                "label": "季付",
                                "amount": "2343.00",
                                "setup_fee": "0.00",
                                "total_amount": "2343.00"
                            },
                            {
                                "cycle": "semiannually",
                                "label": "半年付",
                                "amount": "4686.00",
                                "setup_fee": "0.00",
                                "total_amount": "4686.00"
                            }
                        ],
                        "primary_cycle": "monthly",
                        "primary_price": "781.00",
                        "setup_fee": "0.00",
                        "stock": -1,
                        "auto_setup": 1
                    }
                ]
            }
        ]
    },
    "timestamp": 1783240542
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:42  
· 响应状态码：200  
· 调用方式：GET /api/site/product-categories/{groupId}/catalog  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteProductController@groupCatalog`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api`
