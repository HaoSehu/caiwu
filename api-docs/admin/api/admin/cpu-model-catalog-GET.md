# cpu-model-catalog

**请求方法**：GET  
**请求路径**：`/api/admin/cpu-model-catalog`  
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
| data.list | array | 分页列表数据 |
| data.list.id | string | 真实调用返回字段 |
| data.list.value | string | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.sort_order | integer | 真实调用返回字段 |
| data.list.model_count | integer | 真实调用返回字段 |
| data.list.models | array | 真实调用返回字段 |
| data.list.models.id | string | 真实调用返回字段 |
| data.list.models.value | string | 真实调用返回字段 |
| data.list.models.name | string | 真实调用返回字段 |
| data.list.models.base_frequency | string | 真实调用返回字段 |
| data.list.models.turbo_frequency | string | 真实调用返回字段 |
| data.list.models.sort_order | integer | 真实调用返回字段 |
| data.list.models.bindings | array | 真实调用返回字段 |
| data.list.models.bindings.product_id | integer | 真实调用返回字段 |
| data.list.models.bindings.display_name | string | 真实调用返回字段 |
| data.list.models.bindings.custom_display_name | string | 真实调用返回字段 |
| data.list.models.bindings.cpu_memory_display | string | 真实调用返回字段 |
| data.list.models.bindings.cpu_memory_slug_display | string | 真实调用返回字段 |
| data.list.models.bindings.product_spec_display | string | 真实调用返回字段 |
| data.list.models.bindings.combined_display_name | string | 真实调用返回字段 |
| data.list.models.bindings.category_full_name | string | 真实调用返回字段 |
| data.list.models.bindings.primary_price | object | 真实调用返回字段 |
| data.list.models.bindings.primary_price.cycle | string | 真实调用返回字段 |
| data.list.models.bindings.primary_price.amount | string | 真实调用返回字段 |
| data.list.models.bindings.status | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "id": "group_1779808641619_q3zguh",
                "value": "amd",
                "name": "amd",
                "sort_order": 1,
                "model_count": 1,
                "models": [
                    {
                        "id": "model_1779808737693_ntzqgj",
                        "value": "amd_epyc_7532",
                        "name": "AMD EPYC 7532",
                        "base_frequency": "2.4GHz",
                        "turbo_frequency": "3.3GHz",
                        "sort_order": 1,
                        "bindings": [
                            {
                                "product_id": 47,
                                "display_name": "2vcpu-2gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "2 vCPU 2G",
                                "cpu_memory_slug_display": "2vcpu-2gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-2vcpu-2gib",
                                "category_full_name": "美国 / 高性能",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "22.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 48,
                                "display_name": "4vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 4G",
                                "cpu_memory_slug_display": "4vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-4gib",
                                "category_full_name": "美国 / 高性能",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "30.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 49,
                                "display_name": "4vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 8G",
                                "cpu_memory_slug_display": "4vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-8gib",
                                "category_full_name": "美国 / 高性能",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "40.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 50,
                                "display_name": "8vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 8G",
                                "cpu_memory_slug_display": "8vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-8gib",
                                "category_full_name": "美国 / 高性能",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "60.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 51,
                                "display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-16gib",
                                "category_full_name": "美国 / 高性能",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "120.00"
                                },
                                "status": 1
                            }
                        ]
                    }
                ]
            },
            {
                "id": "group_1779808672984_tb7ab5",
                "value": "intel",
                "name": "Intel",
                "sort_order": 2,
                "model_count": 4,
                "models": [
                    {
                        "id": "model_1779808712359_056lle",
                        "value": "intel_xeon_e5_26_v4",
                        "name": "Intel Xeon E5‑26** v4",
                        "base_frequency": "2.2GHz",
                        "turbo_frequency": "3.6GHz",
                        "sort_order": 1,
                        "bindings": [
                            {
                                "product_id": 1,
                                "display_name": "2vcpu-2gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "2 vCPU 2G",
                                "cpu_memory_slug_display": "2vcpu-2gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-2vcpu-2gib",
                                "category_full_name": "美国 / 三网精品",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "20.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 2,
                                "display_name": "4vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 4G",
                                "cpu_memory_slug_display": "4vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-4gib",
                                "category_full_name": "美国 / 三网精品",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "25.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 3,
                                "display_name": "8vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 8G",
                                "cpu_memory_slug_display": "8vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-8gib",
                                "category_full_name": "美国 / 三网精品",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "60.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 4,
                                "display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-16gib",
                                "category_full_name": "美国 / 三网精品",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "120.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 5,
                                "display_name": "4vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 8G",
                                "cpu_memory_slug_display": "4vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-8gib",
                                "category_full_name": "美国 / 三网精品",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "35.00"
                                },
                                "status": 1
                            }
                        ]
                    },
                    {
                        "id": "model_1779809104815_akephl",
                        "value": "intel_xeon_e5_2699_v4",
                        "name": "Intel Xeon E5‑2699 v4",
                        "base_frequency": "2.2GHz",
                        "turbo_frequency": "3.6GHz",
                        "sort_order": 2,
                        "bindings": [
                            {
                                "product_id": 22,
                                "display_name": "2vcpu-2gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "2 vCPU 2G",
                                "cpu_memory_slug_display": "2vcpu-2gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-2vcpu-2gib",
                                "category_full_name": "香港 / 大宽带",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "23.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 23,
                                "display_name": "4vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 4G",
                                "cpu_memory_slug_display": "4vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-4gib",
                                "category_full_name": "香港 / 大宽带",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "35.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 24,
                                "display_name": "4vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 8G",
                                "cpu_memory_slug_display": "4vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-8gib",
                                "category_full_name": "香港 / 大宽带",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "45.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 25,
                                "display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-16gib",
                                "category_full_name": "香港 / 大宽带",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "120.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 26,
                                "display_name": "8vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 8G",
                                "cpu_memory_slug_display": "8vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-8gib",
                                "category_full_name": "香港 / 大宽带",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "60.00"
                                },
                                "status": 1
                            }
                        ]
                    },
                    {
                        "id": "model_1779809175809_ihkxhb",
                        "value": "intel_xeon_platinum_8269cy",
                        "name": "Intel Xeon Platinum 8269CY",
                        "base_frequency": "2.5GHz",
                        "turbo_frequency": "3.8GHz",
                        "sort_order": 3,
                        "bindings": [
                            {
                                "product_id": 27,
                                "display_name": "4vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 4G",
                                "cpu_memory_slug_display": "4vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-4gib",
                                "category_full_name": "十堰高宽 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "45.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 28,
                                "display_name": "4vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 8G",
                                "cpu_memory_slug_display": "4vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-8gib",
                                "category_full_name": "十堰高宽 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "55.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 29,
                                "display_name": "8vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 8G",
                                "cpu_memory_slug_display": "8vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-8gib",
                                "category_full_name": "十堰高宽 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "70.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 30,
                                "display_name": "12vcpu-12gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "12 vCPU 12G",
                                "cpu_memory_slug_display": "12vcpu-12gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-12vcpu-12gib",
                                "category_full_name": "十堰高宽 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "100.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 31,
                                "display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-16gib",
                                "category_full_name": "十堰高宽 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "125.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 52,
                                "display_name": "2vcpu-2gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "2 vCPU 2G",
                                "cpu_memory_slug_display": "2vcpu-2gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-2vcpu-2gib",
                                "category_full_name": "内蒙古电信 / 性价比",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "25.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 53,
                                "display_name": "4vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 4G",
                                "cpu_memory_slug_display": "4vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-4gib",
                                "category_full_name": "内蒙古电信 / 性价比",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "30.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 54,
                                "display_name": "4vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 8G",
                                "cpu_memory_slug_display": "4vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-8gib",
                                "category_full_name": "内蒙古电信 / 性价比",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "40.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 55,
                                "display_name": "8vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 8G",
                                "cpu_memory_slug_display": "8vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-8gib",
                                "category_full_name": "内蒙古电信 / 性价比",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "55.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 56,
                                "display_name": "12vcpu-12gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "12 vCPU 12G",
                                "cpu_memory_slug_display": "12vcpu-12gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-12vcpu-12gib",
                                "category_full_name": "内蒙古电信 / 性价比",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "75.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 57,
                                "display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-16gib",
                                "category_full_name": "内蒙古电信 / 性价比",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "100.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 82,
                                "display_name": "2vcpu-2gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "2 vCPU 2G",
                                "cpu_memory_slug_display": "2vcpu-2gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-2vcpu-2gib",
                                "category_full_name": "襄阳 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "48.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 83,
                                "display_name": "4vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 4G",
                                "cpu_memory_slug_display": "4vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-4gib",
                                "category_full_name": "襄阳 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "90.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 84,
                                "display_name": "8vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 8G",
                                "cpu_memory_slug_display": "8vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-8gib",
                                "category_full_name": "襄阳 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "130.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 85,
                                "display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-16gib",
                                "category_full_name": "襄阳 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "240.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 117,
                                "display_name": "2vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "2 vCPU 4G",
                                "cpu_memory_slug_display": "2vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-2vcpu-4gib",
                                "category_full_name": "襄阳 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "88.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 118,
                                "display_name": "4vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 8G",
                                "cpu_memory_slug_display": "4vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-8gib",
                                "category_full_name": "襄阳 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "170.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 119,
                                "display_name": "8vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 16G",
                                "cpu_memory_slug_display": "8vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-16gib",
                                "category_full_name": "襄阳 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "290.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 120,
                                "display_name": "16vcpu-32gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 32G",
                                "cpu_memory_slug_display": "16vcpu-32gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-32gib",
                                "category_full_name": "襄阳 / 高宽",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "560.00"
                                },
                                "status": 1
                            }
                        ]
                    },
                    {
                        "id": "model_1779809281868_jhc9b7",
                        "value": "intel_xeon_gold_6152",
                        "name": "Intel Xeon Gold 6152",
                        "base_frequency": "2.1GHz",
                        "turbo_frequency": "3.7GHz",
                        "sort_order": 4,
                        "bindings": [
                            {
                                "product_id": 42,
                                "display_name": "4vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 4G",
                                "cpu_memory_slug_display": "4vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-4gib",
                                "category_full_name": "西安高防 / 高防",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "40.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 43,
                                "display_name": "4vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 8G",
                                "cpu_memory_slug_display": "4vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-8gib",
                                "category_full_name": "西安高防 / 高防",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "50.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 44,
                                "display_name": "8vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 8G",
                                "cpu_memory_slug_display": "8vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-8gib",
                                "category_full_name": "西安高防 / 高防",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "65.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 45,
                                "display_name": "12vcpu-12gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "12 vCPU 12G",
                                "cpu_memory_slug_display": "12vcpu-12gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-12vcpu-12gib",
                                "category_full_name": "西安高防 / 高防",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "90.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 46,
                                "display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-16gib",
                                "category_full_name": "西安高防 / 高防",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "110.00"
                                },
                                "status": 1
                            }
                        ]
                    }
                ]
            },
            {
                "id": "group_1779808862667_ufo9l9",
                "value": "cpu_group_3rhhfk",
                "name": "随机",
                "sort_order": 3,
                "model_count": 1,
                "models": [
                    {
                        "id": "model_1779808944826_xtz3hx",
                        "value": "intel_xeon_e5_2673v4_gold6133",
                        "name": "Intel Xeon E5-2673v4/Gold6133",
                        "base_frequency": "2.3&2.5",
                        "turbo_frequency": "2.6&3.0",
                        "sort_order": 1,
                        "bindings": [
                            {
                                "product_id": 6,
                                "display_name": "2vcpu-2gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "2 vCPU 2G",
                                "cpu_memory_slug_display": "2vcpu-2gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-2vcpu-2gib",
                                "category_full_name": "香港 / 三网精品",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "20.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 7,
                                "display_name": "4vcpu-4gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 4G",
                                "cpu_memory_slug_display": "4vcpu-4gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-4gib",
                                "category_full_name": "香港 / 三网精品",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "30.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 8,
                                "display_name": "4vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "4 vCPU 8G",
                                "cpu_memory_slug_display": "4vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-4vcpu-8gib",
                                "category_full_name": "香港 / 三网精品",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "40.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 9,
                                "display_name": "8vcpu-8gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "8 vCPU 8G",
                                "cpu_memory_slug_display": "8vcpu-8gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-8vcpu-8gib",
                                "category_full_name": "香港 / 三网精品",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "60.00"
                                },
                                "status": 1
                            },
                            {
                                "product_id": 10,
                                "display_name": "16vcpu-16gib",
                                "custom_display_name": "",
                                "cpu_memory_display": "16 vCPU 16G",
                                "cpu_memory_slug_display": "16vcpu-16gib",
                                "product_spec_display": "gscs",
                                "combined_display_name": "gscs-16vcpu-16gib",
                                "category_full_name": "香港 / 三网精品",
                                "primary_price": {
                                    "cycle": "monthly",
                                    "amount": "120.00"
                                },
                                "status": 1
                            }
                        ]
                    }
                ]
            }
        ]
    },
    "timestamp": 1783240486
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:46  
· 响应状态码：200  
· 调用方式：GET /api/admin/cpu-model-catalog  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\CpuModelCatalogController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.list`
