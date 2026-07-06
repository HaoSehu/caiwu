# init

**请求方法**：GET  
**请求路径**：`/api/site/products/init`  
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
| product_type | string | 否 | 查询参数；校验规则：nullable\|in:"vps","dedicated","domain","type_iwjqnj","other","type_ipragu","type_tgynng","type_1"；来源：InitProductRequest |

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
| data.types | array | 真实调用返回字段 |
| data.types.id | integer | 真实调用返回字段 |
| data.types.value | string | 真实调用返回字段 |
| data.types.label | string | 真实调用返回字段 |
| data.types.first_product_group_id | integer | 真实调用返回字段 |
| data.types.first_product_group_code | string | 真实调用返回字段 |
| data.types.first_product_group_name | string | 真实调用返回字段 |
| data.types.icon | string | 真实调用返回字段 |
| data.types.group_count | integer | 真实调用返回字段 |
| data.types.product_count | integer | 真实调用返回字段 |
| data.root_groups | array | 真实调用返回字段 |
| data.root_groups.id | integer | 真实调用返回字段 |
| data.root_groups.product_type | string | 真实调用返回字段 |
| data.root_groups.product_type_id | integer | 真实调用返回字段 |
| data.root_groups.product_type_label | string | 真实调用返回字段 |
| data.root_groups.first_product_group_id | integer | 真实调用返回字段 |
| data.root_groups.first_product_group_code | string | 真实调用返回字段 |
| data.root_groups.first_product_group_name | string | 真实调用返回字段 |
| data.root_groups.second_product_group_id | integer | 真实调用返回字段 |
| data.root_groups.second_product_group_name | string | 真实调用返回字段 |
| data.root_groups.second_product_group_parent_id | integer | 真实调用返回字段 |
| data.root_groups.second_product_group_parent_name | string | 真实调用返回字段 |
| data.root_groups.third_product_group_id | null | 真实调用返回字段 |
| data.root_groups.third_product_group_name | null | 真实调用返回字段 |
| data.root_groups.effective_product_group_id | integer | 真实调用返回字段 |
| data.root_groups.effective_product_group_level | integer | 真实调用返回字段 |
| data.root_groups.service_type_code | string | 真实调用返回字段 |
| data.root_groups.name | string | 真实调用返回字段 |
| data.root_groups.slogan | string | 真实调用返回字段 |
| data.root_groups.slug | string | 真实调用返回字段 |
| data.root_groups.children_count | integer | 真实调用返回字段 |
| data.root_groups.direct_product_count | integer | 真实调用返回字段 |
| data.root_groups.product_count | integer | 真实调用返回字段 |
| data.catalog | object | 真实调用返回字段 |
| data.catalog.effective_product_group_id | integer | 真实调用返回字段 |
| data.catalog.effective_product_group_level | integer | 真实调用返回字段 |
| data.catalog.children | array | 真实调用返回字段 |
| data.catalog.children.id | integer | 真实调用返回字段 |
| data.catalog.children.parent_id | integer | 真实调用返回字段 |
| data.catalog.children.product_type | string | 真实调用返回字段 |
| data.catalog.children.product_type_id | integer | 真实调用返回字段 |
| data.catalog.children.product_type_label | string | 真实调用返回字段 |
| data.catalog.children.first_product_group_id | integer | 真实调用返回字段 |
| data.catalog.children.first_product_group_code | string | 真实调用返回字段 |
| data.catalog.children.first_product_group_name | string | 真实调用返回字段 |
| data.catalog.children.second_product_group_id | integer | 真实调用返回字段 |
| data.catalog.children.second_product_group_name | string | 真实调用返回字段 |
| data.catalog.children.second_product_group_parent_id | integer | 真实调用返回字段 |
| data.catalog.children.second_product_group_parent_name | string | 真实调用返回字段 |
| data.catalog.children.third_product_group_id | integer | 真实调用返回字段 |
| data.catalog.children.third_product_group_name | string | 真实调用返回字段 |
| data.catalog.children.effective_product_group_id | integer | 真实调用返回字段 |
| data.catalog.children.effective_product_group_level | integer | 真实调用返回字段 |
| data.catalog.children.service_type_code | string | 真实调用返回字段 |
| data.catalog.children.name | string | 真实调用返回字段 |
| data.catalog.children.slogan | string | 真实调用返回字段 |
| data.catalog.children.slug | string | 真实调用返回字段 |
| data.catalog.children.product_count | integer | 真实调用返回字段 |
| data.catalog.items_by_group | array | 真实调用返回字段 |
| data.catalog.items_by_group.effective_product_group_id | integer | 真实调用返回字段 |
| data.catalog.items_by_group.products | array | 真实调用返回字段 |
| data.catalog.items_by_group.products.id | integer | 真实调用返回字段 |
| data.catalog.items_by_group.products.name | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.display_name | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.product_display_name | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.combined_display_name | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.cpu_memory_display | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.instance_spec_id | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.instance_spec_value | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.instance_spec_text | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.instance_spec_alias | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.instance_spec_note | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.cpu_display | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.memory_display | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.cpu_model_name | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.cpu_base_frequency | string | 真实调用返回字段 |
| data.catalog.items_by_group.products.cpu_turbo_frequency | string | 真实调用返回字段 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "types": [
            {
                "id": 1,
                "value": "vps",
                "label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "icon": "",
                "group_count": 7,
                "product_count": 92
            },
            {
                "id": 2,
                "value": "dedicated",
                "label": "游戏云",
                "first_product_group_id": 2,
                "first_product_group_code": "dedicated",
                "first_product_group_name": "游戏云",
                "icon": "",
                "group_count": 2,
                "product_count": 10
            },
            {
                "id": 4,
                "value": "domain",
                "label": "云电脑",
                "first_product_group_id": 3,
                "first_product_group_code": "domain",
                "first_product_group_name": "云电脑",
                "icon": "",
                "group_count": 1,
                "product_count": 4
            },
            {
                "id": 7,
                "value": "type_iwjqnj",
                "label": "裸金属",
                "first_product_group_id": 4,
                "first_product_group_code": "type_iwjqnj",
                "first_product_group_name": "裸金属",
                "icon": "",
                "group_count": 1,
                "product_count": 4
            },
            {
                "id": 9,
                "value": "type_tgynng",
                "label": "物理机",
                "first_product_group_id": 8,
                "first_product_group_code": "type_tgynng",
                "first_product_group_name": "物理机",
                "icon": "physical",
                "group_count": 2,
                "product_count": 0
            }
        ],
        "root_groups": [
            {
                "id": 13,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 13,
                "second_product_group_name": "襄阳",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 13,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "襄阳",
                "slogan": "",
                "slug": "category-7",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 8
            },
            {
                "id": 1,
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
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 1,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "美国",
                "slogan": "",
                "slug": "group-1",
                "children_count": 4,
                "direct_product_count": 0,
                "product_count": 48
            },
            {
                "id": 2,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 2,
                "second_product_group_name": "香港",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 2,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "香港",
                "slogan": "",
                "slug": "group-2",
                "children_count": 2,
                "direct_product_count": 0,
                "product_count": 10
            },
            {
                "id": 10,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 10,
                "second_product_group_name": "内蒙古电信",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 10,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "内蒙古电信",
                "slogan": "",
                "slug": "group-25",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 6
            },
            {
                "id": 9,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 9,
                "second_product_group_name": "西安高防",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 9,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "西安高防",
                "slogan": "",
                "slug": "group-22",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 5
            },
            {
                "id": 8,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 8,
                "second_product_group_name": "轻量云",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 8,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "轻量云",
                "slogan": "",
                "slug": "group-20",
                "children_count": 3,
                "direct_product_count": 0,
                "product_count": 10
            },
            {
                "id": 7,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器",
                "first_product_group_id": 1,
                "first_product_group_code": "vps",
                "first_product_group_name": "云服务器",
                "second_product_group_id": 7,
                "second_product_group_name": "十堰高宽",
                "second_product_group_parent_id": 1,
                "second_product_group_parent_name": "云服务器",
                "third_product_group_id": null,
                "third_product_group_name": null,
                "effective_product_group_id": 7,
                "effective_product_group_level": 2,
                "service_type_code": "vps",
                "name": "十堰高宽",
                "slogan": "",
                "slug": "group-18",
                "children_count": 1,
                "direct_product_count": 0,
                "product_count": 5
            }
        ],
        "catalog": {
            "effective_product_group_id": 13,
            "effective_product_group_level": 2,
            "children": [
                {
                    "id": 15,
                    "parent_id": 13,
                    "product_type": "vps",
                    "product_type_id": 1,
                    "product_type_label": "云服务器",
                    "first_product_group_id": 1,
                    "first_product_group_code": "vps",
                    "first_product_group_name": "云服务器",
                    "second_product_group_id": 13,
                    "second_product_group_name": "襄阳",
                    "second_product_group_parent_id": 1,
                    "second_product_group_parent_name": "云服务器",
                    "third_product_group_id": 15,
                    "third_product_group_name": "高宽",
                    "effective_product_group_id": 15,
                    "effective_product_group_level": 3,
                    "service_type_code": "vps",
                    "name": "高宽",
                    "slogan": "铂金8269CY CPU 200G防御 域名自助过白 SAS企业硬盘 傲盾定制防御策略， 测试IP：171.80.3.1",
                    "slug": "category-8",
                    "product_count": 8
                }
            ],
            "items_by_group": [
                {
                    "effective_product_group_id": 13,
                    "products": [
                        {
                            "id": 27,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 7,
                            "second_product_group_name": "十堰高宽",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 13,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 13,
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
                            "stock": 11,
                            "auto_setup": 1
                        },
                        {
                            "id": 28,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 7,
                            "second_product_group_name": "十堰高宽",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 13,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 13,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "55.00",
                                "annually": "660.00",
                                "quarterly": "165.00",
                                "semiannually": "330.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "55.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "55.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "660.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "660.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "165.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "165.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "330.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "330.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "55.00",
                            "setup_fee": "0.00",
                            "stock": 11,
                            "auto_setup": 1
                        },
                        {
                            "id": 29,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 7,
                            "second_product_group_name": "十堰高宽",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 13,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 13,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "70.00",
                                "annually": "840.00",
                                "quarterly": "210.00",
                                "semiannually": "420.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "70.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "70.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "840.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "840.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "210.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "210.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "420.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "420.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "70.00",
                            "setup_fee": "0.00",
                            "stock": 9,
                            "auto_setup": 1
                        },
                        {
                            "id": 30,
                            "name": "gscs",
                            "display_name": "gscs",
                            "product_display_name": "gscs",
                            "combined_display_name": "gscs-12vcpu-12gib",
                            "cpu_memory_display": "12 vCPU 12G",
                            "instance_spec_id": "spec_1779808447596_mux9rb",
                            "instance_spec_value": "gscs",
                            "instance_spec_text": "gscs",
                            "instance_spec_alias": "",
                            "instance_spec_note": "通用共享",
                            "cpu_display": "12 vCPU",
                            "memory_display": "12G",
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 7,
                            "second_product_group_name": "十堰高宽",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 13,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 13,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "100.00",
                                "annually": "1200.00",
                                "quarterly": "300.00",
                                "semiannually": "600.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "100.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "100.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "1200.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "1200.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "300.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "300.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "600.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "600.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "100.00",
                            "setup_fee": "0.00",
                            "stock": 9,
                            "auto_setup": 1
                        },
                        {
                            "id": 31,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 7,
                            "second_product_group_name": "十堰高宽",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 13,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 13,
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
                            "stock": 14,
                            "auto_setup": 1
                        }
                    ]
                },
                {
                    "effective_product_group_id": 15,
                    "products": [
                        {
                            "id": 82,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 13,
                            "second_product_group_name": "襄阳",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 15,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 15,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "48.00",
                                "annually": "576.00",
                                "quarterly": "144.00",
                                "semiannually": "288.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "48.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "48.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "576.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "576.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "144.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "144.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "288.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "288.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "48.00",
                            "setup_fee": "0.00",
                            "stock": -1,
                            "auto_setup": 1
                        },
                        {
                            "id": 117,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 13,
                            "second_product_group_name": "襄阳",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 15,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 15,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "88.00",
                                "annually": "1056.00",
                                "quarterly": "264.00",
                                "semiannually": "288.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "88.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "88.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "1056.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "1056.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "264.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "264.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "288.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "288.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "88.00",
                            "setup_fee": "0.00",
                            "stock": -1,
                            "auto_setup": 1
                        },
                        {
                            "id": 83,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 13,
                            "second_product_group_name": "襄阳",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 15,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 15,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "90.00",
                                "annually": "1080.00",
                                "quarterly": "270.00",
                                "semiannually": "540.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "90.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "90.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "1080.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "1080.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "270.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "270.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "540.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "540.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "90.00",
                            "setup_fee": "0.00",
                            "stock": -1,
                            "auto_setup": 1
                        },
                        {
                            "id": 118,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 13,
                            "second_product_group_name": "襄阳",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 15,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 15,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "170.00",
                                "annually": "2040.00",
                                "quarterly": "510.00",
                                "semiannually": "540.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "170.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "170.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "2040.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "2040.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "510.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "510.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "540.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "540.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "170.00",
                            "setup_fee": "0.00",
                            "stock": -1,
                            "auto_setup": 1
                        },
                        {
                            "id": 84,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 13,
                            "second_product_group_name": "襄阳",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 15,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 15,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "130.00",
                                "annually": "1560.00",
                                "quarterly": "390.00",
                                "semiannually": "780.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "130.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "130.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "1560.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "1560.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "390.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "390.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "780.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "780.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "130.00",
                            "setup_fee": "0.00",
                            "stock": -1,
                            "auto_setup": 1
                        },
                        {
                            "id": 119,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 13,
                            "second_product_group_name": "襄阳",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 15,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 15,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "290.00",
                                "annually": "2520.00",
                                "quarterly": "630.00",
                                "semiannually": "780.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "290.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "290.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "2520.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "2520.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "630.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "630.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "780.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "780.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "290.00",
                            "setup_fee": "0.00",
                            "stock": -1,
                            "auto_setup": 1
                        },
                        {
                            "id": 85,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 13,
                            "second_product_group_name": "襄阳",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 15,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 15,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "240.00",
                                "annually": "2880.00",
                                "quarterly": "720.00",
                                "semiannually": "1440.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "240.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "240.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "2880.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "2880.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "720.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "720.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "1440.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "1440.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "240.00",
                            "setup_fee": "0.00",
                            "stock": -1,
                            "auto_setup": 1
                        },
                        {
                            "id": 120,
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
                            "cpu_model_name": "Intel Xeon Platinum 8269CY",
                            "cpu_base_frequency": "2.5GHz",
                            "cpu_turbo_frequency": "3.8GHz",
                            "product_type": "vps",
                            "type": "vps",
                            "type_label": "云服务器",
                            "first_product_group_id": 1,
                            "first_product_group_code": "vps",
                            "first_product_group_name": "云服务器",
                            "second_product_group_id": 13,
                            "second_product_group_name": "襄阳",
                            "second_product_group_parent_id": 1,
                            "second_product_group_parent_name": "云服务器",
                            "third_product_group_id": 15,
                            "third_product_group_name": "高宽",
                            "effective_product_group_id": 15,
                            "effective_product_group_level": 3,
                            "service_type_code": "vps",
                            "pricing": {
                                "monthly": "560.00",
                                "annually": "6720.00",
                                "quarterly": "1680.00",
                                "semiannually": "1440.00"
                            },
                            "pricing_entries": [
                                {
                                    "cycle": "monthly",
                                    "label": "月付",
                                    "amount": "560.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "560.00"
                                },
                                {
                                    "cycle": "annually",
                                    "label": "年付",
                                    "amount": "6720.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "6720.00"
                                },
                                {
                                    "cycle": "quarterly",
                                    "label": "季付",
                                    "amount": "1680.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "1680.00"
                                },
                                {
                                    "cycle": "semiannually",
                                    "label": "半年付",
                                    "amount": "1440.00",
                                    "setup_fee": "0.00",
                                    "total_amount": "1440.00"
                                }
                            ],
                            "primary_cycle": "monthly",
                            "primary_price": "560.00",
                            "setup_fee": "0.00",
                            "stock": -1,
                            "auto_setup": 1
                        }
                    ]
                }
            ]
        }
    },
    "timestamp": 1783240543
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:43  
· 响应状态码：200  
· 调用方式：GET /api/site/products/init  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteProductController@init`  
· 请求校验：`App\Http\Requests\Site\InitProductRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api`
