# {productId}

**请求方法**：GET  
**请求路径**：`/api/site/products/{productId}`  
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
| productId | integer\|string | 是 | 路径参数；来自路由占位 `{productId}` |

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
| data.product | object | 真实调用返回字段 |
| data.product.id | integer | 真实调用返回字段 |
| data.product.name | string | 真实调用返回字段 |
| data.product.display_name | string | 真实调用返回字段 |
| data.product.product_display_name | string | 真实调用返回字段 |
| data.product.combined_display_name | string | 真实调用返回字段 |
| data.product.cpu_memory_display | string | 真实调用返回字段 |
| data.product.instance_spec_id | string | 真实调用返回字段 |
| data.product.instance_spec_value | string | 真实调用返回字段 |
| data.product.instance_spec_text | string | 真实调用返回字段 |
| data.product.instance_spec_alias | string | 真实调用返回字段 |
| data.product.instance_spec_note | string | 真实调用返回字段 |
| data.product.cpu_display | string | 真实调用返回字段 |
| data.product.memory_display | string | 真实调用返回字段 |
| data.product.cpu_model_name | string | 真实调用返回字段 |
| data.product.cpu_base_frequency | string | 真实调用返回字段 |
| data.product.cpu_turbo_frequency | string | 真实调用返回字段 |
| data.product.product_type | string | 真实调用返回字段 |
| data.product.type | string | 真实调用返回字段 |
| data.product.type_label | string | 真实调用返回字段 |
| data.product.first_product_group_id | integer | 真实调用返回字段 |
| data.product.first_product_group_code | string | 真实调用返回字段 |
| data.product.first_product_group_name | string | 真实调用返回字段 |
| data.product.second_product_group_id | integer | 真实调用返回字段 |
| data.product.second_product_group_name | string | 真实调用返回字段 |
| data.product.second_product_group_parent_id | integer | 真实调用返回字段 |
| data.product.second_product_group_parent_name | string | 真实调用返回字段 |
| data.product.third_product_group_id | integer | 真实调用返回字段 |
| data.product.third_product_group_name | string | 真实调用返回字段 |
| data.product.effective_product_group_id | integer | 真实调用返回字段 |
| data.product.effective_product_group_level | integer | 真实调用返回字段 |
| data.product.service_type_code | string | 真实调用返回字段 |
| data.product.pricing | object | 真实调用返回字段 |
| data.product.pricing.monthly | string | 真实调用返回字段 |
| data.product.pricing.annually | string | 真实调用返回字段 |
| data.product.pricing.quarterly | string | 真实调用返回字段 |
| data.product.pricing.semiannually | string | 真实调用返回字段 |
| data.product.pricing_entries | array | 真实调用返回字段 |
| data.product.pricing_entries.cycle | string | 真实调用返回字段 |
| data.product.pricing_entries.label | string | 真实调用返回字段 |
| data.product.pricing_entries.amount | string | 真实调用返回字段 |
| data.product.pricing_entries.setup_fee | string | 真实调用返回字段 |
| data.product.pricing_entries.total_amount | string | 真实调用返回字段 |
| data.product.primary_cycle | string | 真实调用返回字段 |
| data.product.primary_price | string | 真实调用返回字段 |
| data.product.setup_fee | string | 真实调用返回字段 |
| data.product.setup_fee_display | string | 真实调用返回字段 |
| data.product.stock | integer | 真实调用返回字段 |
| data.product.auto_setup | integer | 真实调用返回字段 |
| data.product.group | object | 真实调用返回字段 |
| data.product.group.id | integer | 真实调用返回字段 |
| data.product.group.product_type | string | 真实调用返回字段 |
| data.product.group.product_type_id | integer | 真实调用返回字段 |
| data.product.group.name | string | 真实调用返回字段 |
| data.product.group.display_name | string | 真实调用返回字段 |
| data.product.group.slogan | string | 真实调用返回字段 |
| data.product.group.slug | null | 真实调用返回字段 |
| data.product.group.parent_id | integer | 真实调用返回字段 |
| data.product.group.parent_product_type | string | 真实调用返回字段 |
| data.product.group.parent_product_type_id | integer | 真实调用返回字段 |
| data.product.group.parent_name | string | 真实调用返回字段 |
| data.product.group.parent_display_name | string | 真实调用返回字段 |
| data.product.group.parent_slogan | string | 真实调用返回字段 |
| data.product.group.parent_slug | null | 真实调用返回字段 |
| data.product.group.first_product_group_id | integer | 真实调用返回字段 |
| data.product.group.first_product_group_code | string | 真实调用返回字段 |
| data.product.group.first_product_group_name | string | 真实调用返回字段 |
| data.product.group.second_product_group_id | integer | 真实调用返回字段 |
| data.product.group.second_product_group_name | string | 真实调用返回字段 |
| data.product.group.second_product_group_parent_id | integer | 真实调用返回字段 |
| data.product.group.second_product_group_parent_name | string | 真实调用返回字段 |
| data.product.group.third_product_group_id | integer | 真实调用返回字段 |
| data.product.group.third_product_group_name | string | 真实调用返回字段 |
| data.product.group.effective_product_group_id | integer | 真实调用返回字段 |
| data.product.group.effective_product_group_level | integer | 真实调用返回字段 |
| data.product.group.service_type_code | string | 真实调用返回字段 |
| data.product.group.full_name | string | 真实调用返回字段 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "product": {
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
            "setup_fee_display": "0.00",
            "stock": 98,
            "auto_setup": 1,
            "group": {
                "id": 3,
                "product_type": "vps",
                "product_type_id": 1,
                "name": "三网精品",
                "display_name": "gscs",
                "slogan": "CN2+CMIN2+9929三网精品，30G DDOS防御 黑洞10分钟 测试IP 156.238.224.1（kurun机房） CPU:E5 2696V4*2/2698/2699V4*2",
                "slug": null,
                "parent_id": 1,
                "parent_product_type": "vps",
                "parent_product_type_id": 1,
                "parent_name": "美国",
                "parent_display_name": "美国",
                "parent_slogan": "",
                "parent_slug": null,
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
                "full_name": "云服务器 / 美国 / 三网精品"
            },
            "config_options": [
                {
                    "id": 57449,
                    "field": "area",
                    "spec_key": "",
                    "name": "区域",
                    "description": "",
                    "hidden": 0,
                    "required": 0,
                    "sort_order": 1,
                    "option_type": 12,
                    "option_mode": "",
                    "parameter": "2|美国",
                    "qty_minimum": 0,
                    "qty_maximum": 0,
                    "qty_step": null,
                    "qty_stage": 1,
                    "suffix_text": "",
                    "sub": [
                        {
                            "id": "254249",
                            "label": "",
                            "version": "美国",
                            "option_name": "美国",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        }
                    ]
                },
                {
                    "id": 57450,
                    "field": "os",
                    "spec_key": "",
                    "name": "操作系统",
                    "description": "",
                    "hidden": 0,
                    "required": 0,
                    "sort_order": 2,
                    "option_type": 5,
                    "option_mode": "",
                    "parameter": "12|CentOS^CentOS-7.6.1810-x64,17|Ubuntu^Ubuntu-18.04-x64,19|Windows^Windows-2012R2-Datacenter-cn,38|Windows^Windows7_enterprise-cn,39|Windows^Windows10-cn,54|Windows^Windows-2008R2-Enterprise-cn,14|Debian^Debian-9.12.1-x64,15|Debian^Debian-10.3.3-x64,40|Ubuntu^Ubuntu-20.04.1-x64,63|CentOS^CentOS-8-Stream-x64,66|Debian^Debian-11.1-x64,67|CentOS^CentOS-7.9.2111-x64,68|Ubuntu^Ubuntu-22.04-x64,16|Ubuntu^Ubuntu-16.04-x64,29|CentOS^CentOS-7.8.2003-x64,30|CentOS^CentOS-7.8.2003-x64-BT,21|Windows^Windows-2019-Datacenter-cn,37|Windows^Windows-2003-Enterprise-cn,20|Windows^Windows-2016-Datacenter-cn,71|CentOS^CentOS-9-Stream-x64,85|Debian^Debian-12.0_x64,89|Ubuntu^Ubuntu-24.04.1-x64",
                    "qty_minimum": 0,
                    "qty_maximum": 0,
                    "qty_step": null,
                    "qty_stage": 1,
                    "suffix_text": "",
                    "sub": [
                        {
                            "id": "254250",
                            "label": "",
                            "version": "CentOS^CentOS-7.6.1810-x64",
                            "option_name": "CentOS^CentOS-7.6.1810-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "254251",
                            "label": "",
                            "version": "Ubuntu^Ubuntu-18.04-x64",
                            "option_name": "Ubuntu^Ubuntu-18.04-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "254253",
                            "label": "",
                            "version": "Windows^Windows-2012R2-Datacenter-cn",
                            "option_name": "Windows^Windows-2012R2-Datacenter-cn",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "254254",
                            "label": "",
                            "version": "Windows^Windows7_enterprise-cn",
                            "option_name": "Windows^Windows7_enterprise-cn",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "254255",
                            "label": "",
                            "version": "Windows^Windows10-cn",
                            "option_name": "Windows^Windows10-cn",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "254256",
                            "label": "",
                            "version": "Windows^Windows-2008R2-Enterprise-cn",
                            "option_name": "Windows^Windows-2008R2-Enterprise-cn",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "259747",
                            "label": "",
                            "version": "Debian^Debian-9.12.1-x64",
                            "option_name": "Debian^Debian-9.12.1-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "259749",
                            "label": "",
                            "version": "Debian^Debian-10.3.3-x64",
                            "option_name": "Debian^Debian-10.3.3-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "259751",
                            "label": "",
                            "version": "Ubuntu^Ubuntu-20.04.1-x64",
                            "option_name": "Ubuntu^Ubuntu-20.04.1-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "259753",
                            "label": "",
                            "version": "CentOS^CentOS-8-Stream-x64",
                            "option_name": "CentOS^CentOS-8-Stream-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "259755",
                            "label": "",
                            "version": "Debian^Debian-11.1-x64",
                            "option_name": "Debian^Debian-11.1-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "259757",
                            "label": "",
                            "version": "CentOS^CentOS-7.9.2111-x64",
                            "option_name": "CentOS^CentOS-7.9.2111-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "259759",
                            "label": "",
                            "version": "Ubuntu^Ubuntu-22.04-x64",
                            "option_name": "Ubuntu^Ubuntu-22.04-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "391396",
                            "label": "",
                            "version": "Ubuntu^Ubuntu-16.04-x64",
                            "option_name": "Ubuntu^Ubuntu-16.04-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "391402",
                            "label": "",
                            "version": "CentOS^CentOS-7.8.2003-x64",
                            "option_name": "CentOS^CentOS-7.8.2003-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "391404",
                            "label": "",
                            "version": "CentOS^CentOS-7.8.2003-x64-BT",
                            "option_name": "CentOS^CentOS-7.8.2003-x64-BT",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "417252",
                            "label": "",
                            "version": "Windows^Windows-2019-Datacenter-cn",
                            "option_name": "Windows^Windows-2019-Datacenter-cn",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "417253",
                            "label": "",
                            "version": "Windows^Windows-2003-Enterprise-cn",
                            "option_name": "Windows^Windows-2003-Enterprise-cn",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "517372",
                            "label": "",
                            "version": "Windows^Windows-2016-Datacenter-cn",
                            "option_name": "Windows^Windows-2016-Datacenter-cn",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "569394",
                            "label": "",
                            "version": "CentOS^CentOS-9-Stream-x64",
                            "option_name": "CentOS^CentOS-9-Stream-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "577159",
                            "label": "",
                            "version": "Debian^Debian-12.0_x64",
                            "option_name": "Debian^Debian-12.0_x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        },
                        {
                            "id": "704645",
                            "label": "",
                            "version": "Ubuntu^Ubuntu-24.04.1-x64",
                            "option_name": "Ubuntu^Ubuntu-24.04.1-x64",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        }
                    ]
                },
                {
                    "id": 57451,
                    "field": "cpu",
                    "spec_key": "",
                    "name": "CPU",
                    "description": "",
                    "hidden": 0,
                    "required": 0,
                    "sort_order": 3,
                    "option_type": 6,
                    "option_mode": "",
                    "parameter": "2|2核",
                    "qty_minimum": 0,
                    "qty_maximum": 0,
                    "qty_step": null,
                    "qty_stage": 1,
                    "suffix_text": "",
                    "sub": [
                        {
                            "id": "254262",
                            "label": "",
                            "version": "2核",
                            "option_name": "2核",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        }
                    ]
                },
                {
                    "id": 57452,
                    "field": "memory",
                    "spec_key": "",
                    "name": "内存",
                    "description": "",
                    "hidden": 0,
                    "required": 0,
                    "sort_order": 4,
                    "option_type": 8,
                    "option_mode": "",
                    "parameter": "2048|2G",
                    "qty_minimum": 0,
                    "qty_maximum": 0,
                    "qty_step": null,
                    "qty_stage": 1,
                    "suffix_text": "",
                    "sub": [
                        {
                            "id": "254270",
                            "label": "",
                            "version": "2G",
                            "option_name": "2G",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 0,
                            "qty_maximum": 0
                        }
                    ]
                },
                {
                    "id": 57453,
                    "field": "bw",
                    "spec_key": "",
                    "name": "带宽",
                    "description": "",
                    "hidden": 0,
                    "required": 0,
                    "sort_order": 7,
                    "option_type": 11,
                    "option_mode": "",
                    "parameter": "",
                    "qty_minimum": 50,
                    "qty_maximum": 50,
                    "qty_step": null,
                    "qty_stage": 1,
                    "suffix_text": "",
                    "sub": [
                        {
                            "id": "254278",
                            "label": "",
                            "version": "带宽",
                            "option_name": "带宽",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 50,
                            "qty_maximum": 50
                        }
                    ]
                },
                {
                    "id": 57454,
                    "field": "ip_num",
                    "spec_key": "",
                    "name": "IP数量",
                    "description": "",
                    "hidden": 0,
                    "required": 0,
                    "sort_order": 9,
                    "option_type": 4,
                    "option_mode": "",
                    "parameter": "",
                    "qty_minimum": 1,
                    "qty_maximum": 1,
                    "qty_step": null,
                    "qty_stage": 1,
                    "suffix_text": "",
                    "sub": [
                        {
                            "id": "254279",
                            "label": "",
                            "version": "IP数量",
                            "option_name": "IP数量",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 1,
                            "qty_maximum": 1
                        }
                    ]
                },
                {
                    "id": 57455,
                    "field": "data_disk_size",
                    "spec_key": "",
                    "name": "数据盘",
                    "description": "",
                    "hidden": 0,
                    "required": 0,
                    "sort_order": 10,
                    "option_type": 14,
                    "option_mode": "",
                    "parameter": "",
                    "qty_minimum": 50,
                    "qty_maximum": 50,
                    "qty_step": null,
                    "qty_stage": 1,
                    "suffix_text": "",
                    "sub": [
                        {
                            "id": "254280",
                            "label": "",
                            "version": "数据盘",
                            "option_name": "数据盘",
                            "hidden": 0,
                            "sort_order": 0,
                            "qty_minimum": 50,
                            "qty_maximum": 50
                        }
                    ]
                }
            ],
            "provider_key": "mofang_finance_api",
            "siblings": [
                {
                    "id": 1,
                    "name": "gscs",
                    "display_name": "gscs",
                    "product_display_name": "gscs",
                    "combined_display_name": "gscs-2vcpu-2gib",
                    "instance_spec_text": "gscs"
                },
                {
                    "id": 2,
                    "name": "gscs",
                    "display_name": "gscs",
                    "product_display_name": "gscs",
                    "combined_display_name": "gscs-4vcpu-4gib",
                    "instance_spec_text": "gscs"
                },
                {
                    "id": 5,
                    "name": "gscs",
                    "display_name": "gscs",
                    "product_display_name": "gscs",
                    "combined_display_name": "gscs-4vcpu-8gib",
                    "instance_spec_text": "gscs"
                },
                {
                    "id": 3,
                    "name": "gscs",
                    "display_name": "gscs",
                    "product_display_name": "gscs",
                    "combined_display_name": "gscs-8vcpu-8gib",
                    "instance_spec_text": "gscs"
                },
                {
                    "id": 4,
                    "name": "gscs",
                    "display_name": "gscs",
                    "product_display_name": "gscs",
                    "combined_display_name": "gscs-16vcpu-16gib",
                    "instance_spec_text": "gscs"
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
· 调用方式：GET /api/site/products/{productId}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteProductController@show`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器/服务/资源可静态确认 data 字段`  
· 中间件：`api`
