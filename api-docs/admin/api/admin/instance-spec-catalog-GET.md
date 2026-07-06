# instance-spec-catalog

**请求方法**：GET  
**请求路径**：`/api/admin/instance-spec-catalog`  
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
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：IndexRequest |
| binding_status | string | 否 | 查询参数；校验规则：nullable\|string\|in:bound,unbound；来源：IndexRequest |

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
| data.list.text | string | 真实调用返回字段 |
| data.list.alias | string | 真实调用返回字段 |
| data.list.note | string | 真实调用返回字段 |
| data.list.status | string | 真实调用返回字段 |
| data.list.sort_order | integer | 真实调用返回字段 |
| data.list.bindings | array | 真实调用返回字段 |
| data.list.bindings.product_id | integer | 真实调用返回字段 |
| data.list.bindings.display_name | string | 真实调用返回字段 |
| data.list.bindings.custom_display_name | string | 真实调用返回字段 |
| data.list.bindings.cpu_memory_display | string | 真实调用返回字段 |
| data.list.bindings.cpu_memory_slug_display | string | 真实调用返回字段 |
| data.list.bindings.product_spec_display | string | 真实调用返回字段 |
| data.list.bindings.combined_display_name | string | 真实调用返回字段 |
| data.list.bindings.category_full_name | string | 真实调用返回字段 |
| data.list.bindings.primary_price | object | 真实调用返回字段 |
| data.list.bindings.primary_price.cycle | string | 真实调用返回字段 |
| data.list.bindings.primary_price.amount | string | 真实调用返回字段 |
| data.list.bindings.status | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "id": "spec_1779808447596_mux9rb",
                "value": "gscs",
                "text": "gscs",
                "alias": "",
                "note": "通用共享",
                "status": "展示中",
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
                    },
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
                    },
                    {
                        "product_id": 12,
                        "display_name": "4vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 4G",
                        "cpu_memory_slug_display": "4vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "category_full_name": "宁波高宽 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "60.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 13,
                        "display_name": "4vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 8G",
                        "cpu_memory_slug_display": "4vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-8gib",
                        "category_full_name": "宁波高宽 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "75.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 14,
                        "display_name": "8vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 8G",
                        "cpu_memory_slug_display": "8vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "category_full_name": "宁波高宽 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "90.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 15,
                        "display_name": "8vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 16G",
                        "cpu_memory_slug_display": "8vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-16gib",
                        "category_full_name": "宁波高宽 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "105.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 16,
                        "display_name": "16vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 16G",
                        "cpu_memory_slug_display": "16vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-16gib",
                        "category_full_name": "宁波高宽 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "125.00"
                        },
                        "status": 1
                    },
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
                    },
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
                        "product_id": 32,
                        "display_name": "2vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 2G",
                        "cpu_memory_slug_display": "2vcpu-2gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-2vcpu-2gib",
                        "category_full_name": "轻量云 / 美国",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "9.90"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 33,
                        "display_name": "4vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 4G",
                        "cpu_memory_slug_display": "4vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "category_full_name": "轻量云 / 美国",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "19.90"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 34,
                        "display_name": "8vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 8G",
                        "cpu_memory_slug_display": "8vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "category_full_name": "轻量云 / 美国",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "29.90"
                        },
                        "status": 1
                    },
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
                    },
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
                        "product_id": 66,
                        "display_name": "4vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 4G",
                        "cpu_memory_slug_display": "4vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "category_full_name": "轻量云 / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "29.90"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 67,
                        "display_name": "8vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 8G",
                        "cpu_memory_slug_display": "8vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "category_full_name": "轻量云 / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "49.90"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 68,
                        "display_name": "16vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 16G",
                        "cpu_memory_slug_display": "16vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-16gib",
                        "category_full_name": "轻量云 / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "69.90"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 70,
                        "display_name": "4vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 4G",
                        "cpu_memory_slug_display": "4vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "category_full_name": "轻量云 / 香港",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "19.90"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 71,
                        "display_name": "8vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 8G",
                        "cpu_memory_slug_display": "8vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "category_full_name": "轻量云 / 香港",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "29.90"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 72,
                        "display_name": "2vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 2G",
                        "cpu_memory_slug_display": "2vcpu-2gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-2vcpu-2gib",
                        "category_full_name": "轻量云 / 香港",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "9.90"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 73,
                        "display_name": "16vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 16G",
                        "cpu_memory_slug_display": "16vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-16gib",
                        "category_full_name": "轻量云 / 香港",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "49.90"
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
                        "product_id": 94,
                        "display_name": "2vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 2G",
                        "cpu_memory_slug_display": "2vcpu-2gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-2vcpu-2gib",
                        "category_full_name": "美国 / 家宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "45.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 95,
                        "display_name": "2vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 4G",
                        "cpu_memory_slug_display": "2vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-2vcpu-4gib",
                        "category_full_name": "美国 / 家宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "65.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 96,
                        "display_name": "4vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 4G",
                        "cpu_memory_slug_display": "4vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "category_full_name": "美国 / 家宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "85.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 97,
                        "display_name": "4vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 8G",
                        "cpu_memory_slug_display": "4vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-8gib",
                        "category_full_name": "美国 / 家宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "125.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 98,
                        "display_name": "8vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 8G",
                        "cpu_memory_slug_display": "8vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "category_full_name": "美国 / 家宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "165.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 99,
                        "display_name": "8vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 16G",
                        "cpu_memory_slug_display": "8vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-16gib",
                        "category_full_name": "美国 / 家宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "245.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 100,
                        "display_name": "16vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 16G",
                        "cpu_memory_slug_display": "16vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-16gib",
                        "category_full_name": "美国 / 家宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "325.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 101,
                        "display_name": "16vcpu-32gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 32G",
                        "cpu_memory_slug_display": "16vcpu-32gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-32gib",
                        "category_full_name": "美国 / 家宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "485.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 102,
                        "display_name": "2vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 2G",
                        "cpu_memory_slug_display": "2vcpu-2gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-2vcpu-2gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "45.00"
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
                    },
                    {
                        "product_id": 121,
                        "display_name": "2vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 4G",
                        "cpu_memory_slug_display": "2vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-2vcpu-4gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "61.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 122,
                        "display_name": "2vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 8G",
                        "cpu_memory_slug_display": "2vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-2vcpu-8gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "93.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 123,
                        "display_name": "2vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 16G",
                        "cpu_memory_slug_display": "2vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-2vcpu-16gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "157.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 124,
                        "display_name": "2vcpu-32gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 32G",
                        "cpu_memory_slug_display": "2vcpu-32gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-2vcpu-32gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "285.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 125,
                        "display_name": "2vcpu-64gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 64G",
                        "cpu_memory_slug_display": "2vcpu-64gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-2vcpu-64gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "541.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 126,
                        "display_name": "4vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 2G",
                        "cpu_memory_slug_display": "4vcpu-2gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-2gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "61.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 127,
                        "display_name": "4vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 4G",
                        "cpu_memory_slug_display": "4vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-4gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "77.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 128,
                        "display_name": "4vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 8G",
                        "cpu_memory_slug_display": "4vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-8gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "109.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 129,
                        "display_name": "4vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 16G",
                        "cpu_memory_slug_display": "4vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-16gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "173.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 130,
                        "display_name": "4vcpu-32gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 32G",
                        "cpu_memory_slug_display": "4vcpu-32gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-32gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "301.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 131,
                        "display_name": "4vcpu-64gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 64G",
                        "cpu_memory_slug_display": "4vcpu-64gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-4vcpu-64gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "557.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 132,
                        "display_name": "8vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 2G",
                        "cpu_memory_slug_display": "8vcpu-2gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-2gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "93.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 133,
                        "display_name": "8vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 4G",
                        "cpu_memory_slug_display": "8vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-4gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "109.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 134,
                        "display_name": "8vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 8G",
                        "cpu_memory_slug_display": "8vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-8gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "141.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 135,
                        "display_name": "8vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 16G",
                        "cpu_memory_slug_display": "8vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-16gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "205.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 136,
                        "display_name": "8vcpu-32gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 32G",
                        "cpu_memory_slug_display": "8vcpu-32gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-32gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "333.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 137,
                        "display_name": "8vcpu-64gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 64G",
                        "cpu_memory_slug_display": "8vcpu-64gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-8vcpu-64gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "589.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 138,
                        "display_name": "16vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 2G",
                        "cpu_memory_slug_display": "16vcpu-2gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-2gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "157.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 139,
                        "display_name": "16vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 4G",
                        "cpu_memory_slug_display": "16vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-4gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "173.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 140,
                        "display_name": "16vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 8G",
                        "cpu_memory_slug_display": "16vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-8gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "205.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 141,
                        "display_name": "16vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 16G",
                        "cpu_memory_slug_display": "16vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-16gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "269.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 142,
                        "display_name": "16vcpu-32gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 32G",
                        "cpu_memory_slug_display": "16vcpu-32gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-32gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "397.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 143,
                        "display_name": "16vcpu-64gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 64G",
                        "cpu_memory_slug_display": "16vcpu-64gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-16vcpu-64gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "653.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 144,
                        "display_name": "32vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "32 vCPU 2G",
                        "cpu_memory_slug_display": "32vcpu-2gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-32vcpu-2gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "285.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 145,
                        "display_name": "32vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "32 vCPU 4G",
                        "cpu_memory_slug_display": "32vcpu-4gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-32vcpu-4gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "301.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 146,
                        "display_name": "32vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "32 vCPU 8G",
                        "cpu_memory_slug_display": "32vcpu-8gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-32vcpu-8gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "333.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 147,
                        "display_name": "32vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "32 vCPU 16G",
                        "cpu_memory_slug_display": "32vcpu-16gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-32vcpu-16gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "397.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 148,
                        "display_name": "32vcpu-32gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "32 vCPU 32G",
                        "cpu_memory_slug_display": "32vcpu-32gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-32vcpu-32gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "525.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 149,
                        "display_name": "32vcpu-64gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "32 vCPU 64G",
                        "cpu_memory_slug_display": "32vcpu-64gib",
                        "product_spec_display": "gscs",
                        "combined_display_name": "gscs-32vcpu-64gib",
                        "category_full_name": "美国 / 高宽",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "781.00"
                        },
                        "status": 1
                    }
                ]
            },
            {
                "id": "spec_1779808456820_payznq",
                "value": "gscs_nat",
                "text": "gscs-nat",
                "alias": "",
                "note": "NAT通用共享",
                "status": "展示中",
                "sort_order": 2,
                "bindings": [
                    {
                        "product_id": 61,
                        "display_name": "2vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 2G",
                        "cpu_memory_slug_display": "2vcpu-2gib",
                        "product_spec_display": "gscs-nat",
                        "combined_display_name": "gscs-nat-2vcpu-2gib",
                        "category_full_name": "云电脑 / 成都",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "6.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 74,
                        "display_name": "2vcpu-1gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 1G",
                        "cpu_memory_slug_display": "2vcpu-1gib",
                        "product_spec_display": "gscs-nat",
                        "combined_display_name": "gscs-nat-2vcpu-1gib",
                        "category_full_name": "云电脑 / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "5.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 75,
                        "display_name": "2vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 2G",
                        "cpu_memory_slug_display": "2vcpu-2gib",
                        "product_spec_display": "gscs-nat",
                        "combined_display_name": "gscs-nat-2vcpu-2gib",
                        "category_full_name": "云电脑 / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "10.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 76,
                        "display_name": "4vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 4G",
                        "cpu_memory_slug_display": "4vcpu-4gib",
                        "product_spec_display": "gscs-nat",
                        "combined_display_name": "gscs-nat-4vcpu-4gib",
                        "category_full_name": "云电脑 / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "14.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 77,
                        "display_name": "8vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 8G",
                        "cpu_memory_slug_display": "8vcpu-8gib",
                        "product_spec_display": "gscs-nat",
                        "combined_display_name": "gscs-nat-8vcpu-8gib",
                        "category_full_name": "云电脑 / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "18.00"
                        },
                        "status": 1
                    }
                ]
            },
            {
                "id": "spec_1779808467611_gcao93",
                "value": "gscs_gc",
                "text": "gscs-gc",
                "alias": "",
                "note": "游戏通用共享",
                "status": "展示中",
                "sort_order": 3,
                "bindings": [
                    {
                        "product_id": 107,
                        "display_name": "2vcpu-1gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 1G",
                        "cpu_memory_slug_display": "2vcpu-1gib",
                        "product_spec_display": "gscs-gc",
                        "combined_display_name": "gscs-gc-2vcpu-1gib",
                        "category_full_name": "Gold / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "10.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 108,
                        "display_name": "2vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 2G",
                        "cpu_memory_slug_display": "2vcpu-2gib",
                        "product_spec_display": "gscs-gc",
                        "combined_display_name": "gscs-gc-2vcpu-2gib",
                        "category_full_name": "Gold / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "15.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 109,
                        "display_name": "4vcpu-4gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 4G",
                        "cpu_memory_slug_display": "4vcpu-4gib",
                        "product_spec_display": "gscs-gc",
                        "combined_display_name": "gscs-gc-4vcpu-4gib",
                        "category_full_name": "Gold / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "20.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 110,
                        "display_name": "8vcpu-8gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 8G",
                        "cpu_memory_slug_display": "8vcpu-8gib",
                        "product_spec_display": "gscs-gc",
                        "combined_display_name": "gscs-gc-8vcpu-8gib",
                        "category_full_name": "Gold / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "28.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 111,
                        "display_name": "8vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "8 vCPU 16G",
                        "cpu_memory_slug_display": "8vcpu-16gib",
                        "product_spec_display": "gscs-gc",
                        "combined_display_name": "gscs-gc-8vcpu-16gib",
                        "category_full_name": "Gold / 西安",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "40.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 112,
                        "display_name": "2vcpu-1gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 1G",
                        "cpu_memory_slug_display": "2vcpu-1gib",
                        "product_spec_display": "gscs-gc",
                        "combined_display_name": "gscs-gc-2vcpu-1gib",
                        "category_full_name": "Platinum / 十堰",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "12.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 113,
                        "display_name": "2vcpu-2gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 2G",
                        "cpu_memory_slug_display": "2vcpu-2gib",
                        "product_spec_display": "gscs-gc",
                        "combined_display_name": "gscs-gc-2vcpu-2gib",
                        "category_full_name": "Platinum / 十堰",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "18.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 114,
                        "display_name": "2vcpu-6gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "2 vCPU 6G",
                        "cpu_memory_slug_display": "2vcpu-6gib",
                        "product_spec_display": "gscs-gc",
                        "combined_display_name": "gscs-gc-2vcpu-6gib",
                        "category_full_name": "Platinum / 十堰",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "30.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 115,
                        "display_name": "4vcpu-12gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 12G",
                        "cpu_memory_slug_display": "4vcpu-12gib",
                        "product_spec_display": "gscs-gc",
                        "combined_display_name": "gscs-gc-4vcpu-12gib",
                        "category_full_name": "Platinum / 十堰",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "50.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 116,
                        "display_name": "4vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "4 vCPU 16G",
                        "cpu_memory_slug_display": "4vcpu-16gib",
                        "product_spec_display": "gscs-gc",
                        "combined_display_name": "gscs-gc-4vcpu-16gib",
                        "category_full_name": "Platinum / 十堰",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "62.00"
                        },
                        "status": 1
                    }
                ]
            },
            {
                "id": "spec_1779808500745_ec3mvj",
                "value": "ercs",
                "text": "ercs",
                "alias": "",
                "note": "通用独享",
                "status": "展示中",
                "sort_order": 4,
                "bindings": [
                    {
                        "product_id": 78,
                        "display_name": "16vcpu-16gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 16G",
                        "cpu_memory_slug_display": "16vcpu-16gib",
                        "product_spec_display": "ercs",
                        "combined_display_name": "ercs-16vcpu-16gib",
                        "category_full_name": "裸金属 / 美国",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "299.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 79,
                        "display_name": "16vcpu-32gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "16 vCPU 32G",
                        "cpu_memory_slug_display": "16vcpu-32gib",
                        "product_spec_display": "ercs",
                        "combined_display_name": "ercs-16vcpu-32gib",
                        "category_full_name": "裸金属 / 美国",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "450.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 80,
                        "display_name": "32vcpu-32gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "32 vCPU 32G",
                        "cpu_memory_slug_display": "32vcpu-32gib",
                        "product_spec_display": "ercs",
                        "combined_display_name": "ercs-32vcpu-32gib",
                        "category_full_name": "裸金属 / 美国",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "599.00"
                        },
                        "status": 1
                    },
                    {
                        "product_id": 81,
                        "display_name": "32vcpu-64gib",
                        "custom_display_name": "",
                        "cpu_memory_display": "32 vCPU 64G",
                        "cpu_memory_slug_display": "32vcpu-64gib",
                        "product_spec_display": "ercs",
                        "combined_display_name": "ercs-32vcpu-64gib",
                        "category_full_name": "裸金属 / 美国",
                        "primary_price": {
                            "cycle": "monthly",
                            "amount": "899.00"
                        },
                        "status": 1
                    }
                ]
            }
        ]
    },
    "timestamp": 1783240488
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:48  
· 响应状态码：200  
· 调用方式：GET /api/admin/instance-spec-catalog  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\InstanceSpecCatalogController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.list`
