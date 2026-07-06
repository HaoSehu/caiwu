# coupons

**请求方法**：GET  
**请求路径**：`/api/client/coupons`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListCouponsRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:50；来源：ListCouponsRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|in:all,available,used_up,expired；来源：ListCouponsRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：ListCouponsRequest |

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
| data.list.uid | string | 真实调用返回字段 |
| data.list.coupon_id | integer | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.description | string | 真实调用返回字段 |
| data.list.distribution_type | string | 真实调用返回字段 |
| data.list.distribution_type_label | string | 真实调用返回字段 |
| data.list.discount_scope | string | 真实调用返回字段 |
| data.list.discount_scope_label | string | 真实调用返回字段 |
| data.list.receive_type | string | 真实调用返回字段 |
| data.list.receive_type_label | string | 真实调用返回字段 |
| data.list.discount_type | string | 真实调用返回字段 |
| data.list.discount_value | string | 真实调用返回字段 |
| data.list.discount_label | string | 真实调用返回字段 |
| data.list.min_amount | string | 真实调用返回字段 |
| data.list.max_discount_amount | null | 真实调用返回字段 |
| data.list.status | string | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.status_reason | string | 真实调用返回字段 |
| data.list.can_use | boolean | 真实调用返回字段 |
| data.list.used_times | integer | 真实调用返回字段 |
| data.list.per_user_limit | integer | 真实调用返回字段 |
| data.list.remaining_times | integer | 真实调用返回字段 |
| data.list.first_order_only | boolean | 真实调用返回字段 |
| data.list.product_ids | array | 真实调用返回字段 |
| data.list.target_product_id | integer | 真实调用返回字段 |
| data.list.product_names | array | 真实调用返回字段 |
| data.list.product_scope_text | string | 真实调用返回字段 |
| data.list.products | array | 真实调用返回字段 |
| data.list.products.id | integer | 真实调用返回字段 |
| data.list.products.name | string | 真实调用返回字段 |
| data.list.products.service_type_code | string | 真实调用返回字段 |
| data.list.products.service_type_label | string | 真实调用返回字段 |
| data.list.products.first_product_group_id | integer | 真实调用返回字段 |
| data.list.products.first_product_group_name | string | 真实调用返回字段 |
| data.list.products.second_product_group_id | integer | 真实调用返回字段 |
| data.list.products.second_product_group_name | string | 真实调用返回字段 |
| data.list.products.third_product_group_id | integer | 真实调用返回字段 |
| data.list.products.third_product_group_name | string | 真实调用返回字段 |
| data.list.products.effective_product_group_id | integer | 真实调用返回字段 |
| data.list.products.effective_product_group_level | integer | 真实调用返回字段 |
| data.list.billing_cycles | array | 真实调用返回字段 |
| data.list.billing_cycle_text | string | 真实调用返回字段 |
| data.list.validity_text | string | 真实调用返回字段 |
| data.list.claimed_at | string | 真实调用返回字段 |
| data.list.used_at | null | 真实调用返回字段 |
| data.list.revoked_at | null | 真实调用返回字段 |
| data.list.granted_at | null | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.expires_at | string | 真实调用返回字段 |
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
                "id": 7,
                "uid": "uc_7c07975d1d1f",
                "coupon_id": 4,
                "name": "五一优惠",
                "description": "",
                "distribution_type": "public",
                "distribution_type_label": "公开优惠券",
                "discount_scope": "first_month",
                "discount_scope_label": "首月优惠",
                "receive_type": "claim",
                "receive_type_label": "手动领取",
                "discount_type": "percentage",
                "discount_value": "80.00",
                "discount_label": "8 折优惠",
                "min_amount": "10.00",
                "max_discount_amount": null,
                "status": "expired",
                "status_label": "已过期",
                "status_reason": "有效期已结束",
                "can_use": false,
                "used_times": 0,
                "per_user_limit": 1,
                "remaining_times": 1,
                "first_order_only": false,
                "product_ids": [
                    78,
                    79,
                    80,
                    81,
                    82,
                    83,
                    84,
                    85,
                    1,
                    2,
                    5,
                    3,
                    4,
                    47,
                    48,
                    49,
                    50,
                    51,
                    94,
                    95,
                    96,
                    97,
                    98,
                    99,
                    100,
                    101,
                    6,
                    7,
                    8,
                    9,
                    10,
                    22,
                    23,
                    24,
                    26,
                    25,
                    52,
                    53,
                    56,
                    54,
                    57,
                    55,
                    42,
                    43,
                    44,
                    45,
                    46,
                    27,
                    28,
                    29,
                    30,
                    31,
                    12,
                    13,
                    14,
                    15,
                    16
                ],
                "target_product_id": 0,
                "product_names": [
                    "ercs",
                    "ercs",
                    "ercs",
                    "ercs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs",
                    "gscs"
                ],
                "product_scope_text": "ercs / ercs / ercs / ercs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs",
                "products": [
                    {
                        "id": 78,
                        "name": "16 vCPU 16G",
                        "service_type_code": "type_iwjqnj",
                        "service_type_label": "裸金属",
                        "first_product_group_id": 4,
                        "first_product_group_name": "裸金属",
                        "second_product_group_id": 12,
                        "second_product_group_name": "裸金属",
                        "third_product_group_id": 11,
                        "third_product_group_name": "美国",
                        "effective_product_group_id": 11,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 79,
                        "name": "16 vCPU 32G",
                        "service_type_code": "type_iwjqnj",
                        "service_type_label": "裸金属",
                        "first_product_group_id": 4,
                        "first_product_group_name": "裸金属",
                        "second_product_group_id": 12,
                        "second_product_group_name": "裸金属",
                        "third_product_group_id": 11,
                        "third_product_group_name": "美国",
                        "effective_product_group_id": 11,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 80,
                        "name": "32 vCPU 32G",
                        "service_type_code": "type_iwjqnj",
                        "service_type_label": "裸金属",
                        "first_product_group_id": 4,
                        "first_product_group_name": "裸金属",
                        "second_product_group_id": 12,
                        "second_product_group_name": "裸金属",
                        "third_product_group_id": 11,
                        "third_product_group_name": "美国",
                        "effective_product_group_id": 11,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 81,
                        "name": "32 vCPU 64G",
                        "service_type_code": "type_iwjqnj",
                        "service_type_label": "裸金属",
                        "first_product_group_id": 4,
                        "first_product_group_name": "裸金属",
                        "second_product_group_id": 12,
                        "second_product_group_name": "裸金属",
                        "third_product_group_id": 11,
                        "third_product_group_name": "美国",
                        "effective_product_group_id": 11,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 56,
                        "name": "12 vCPU 12G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 10,
                        "second_product_group_name": "内蒙古电信",
                        "third_product_group_id": 10,
                        "third_product_group_name": "性价比",
                        "effective_product_group_id": 10,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 57,
                        "name": "16 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 10,
                        "second_product_group_name": "内蒙古电信",
                        "third_product_group_id": 10,
                        "third_product_group_name": "性价比",
                        "effective_product_group_id": 10,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 52,
                        "name": "2 vCPU 2G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 10,
                        "second_product_group_name": "内蒙古电信",
                        "third_product_group_id": 10,
                        "third_product_group_name": "性价比",
                        "effective_product_group_id": 10,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 53,
                        "name": "4 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 10,
                        "second_product_group_name": "内蒙古电信",
                        "third_product_group_id": 10,
                        "third_product_group_name": "性价比",
                        "effective_product_group_id": 10,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 54,
                        "name": "4 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 10,
                        "second_product_group_name": "内蒙古电信",
                        "third_product_group_id": 10,
                        "third_product_group_name": "性价比",
                        "effective_product_group_id": 10,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 55,
                        "name": "8 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 10,
                        "second_product_group_name": "内蒙古电信",
                        "third_product_group_id": 10,
                        "third_product_group_name": "性价比",
                        "effective_product_group_id": 10,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 30,
                        "name": "12 vCPU 12G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 7,
                        "second_product_group_name": "十堰高宽",
                        "third_product_group_id": 13,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 13,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 31,
                        "name": "16 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 7,
                        "second_product_group_name": "十堰高宽",
                        "third_product_group_id": 13,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 13,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 27,
                        "name": "4 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 7,
                        "second_product_group_name": "十堰高宽",
                        "third_product_group_id": 13,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 13,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 28,
                        "name": "4 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 7,
                        "second_product_group_name": "十堰高宽",
                        "third_product_group_id": 13,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 13,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 29,
                        "name": "8 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 7,
                        "second_product_group_name": "十堰高宽",
                        "third_product_group_id": 13,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 13,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 16,
                        "name": "16 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 3,
                        "second_product_group_name": "宁波高宽",
                        "third_product_group_id": 12,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 12,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 12,
                        "name": "4 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 3,
                        "second_product_group_name": "宁波高宽",
                        "third_product_group_id": 12,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 12,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 13,
                        "name": "4 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 3,
                        "second_product_group_name": "宁波高宽",
                        "third_product_group_id": 12,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 12,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 15,
                        "name": "8 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 3,
                        "second_product_group_name": "宁波高宽",
                        "third_product_group_id": 12,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 12,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 14,
                        "name": "8 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 3,
                        "second_product_group_name": "宁波高宽",
                        "third_product_group_id": 12,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 12,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 4,
                        "name": "16 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 3,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 1,
                        "name": "2 vCPU 2G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 3,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 2,
                        "name": "4 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 3,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 5,
                        "name": "4 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 3,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 3,
                        "name": "8 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 3,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 3,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 100,
                        "name": "16 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 101,
                        "name": "16 vCPU 32G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 94,
                        "name": "2 vCPU 2G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 95,
                        "name": "2 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 96,
                        "name": "4 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 97,
                        "name": "4 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 99,
                        "name": "8 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 98,
                        "name": "8 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 19,
                        "third_product_group_name": "家宽",
                        "effective_product_group_id": 19,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 51,
                        "name": "16 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 5,
                        "third_product_group_name": "高性能",
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 47,
                        "name": "2 vCPU 2G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 5,
                        "third_product_group_name": "高性能",
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 48,
                        "name": "4 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 5,
                        "third_product_group_name": "高性能",
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 49,
                        "name": "4 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 5,
                        "third_product_group_name": "高性能",
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 50,
                        "name": "8 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 1,
                        "second_product_group_name": "美国",
                        "third_product_group_id": 5,
                        "third_product_group_name": "高性能",
                        "effective_product_group_id": 5,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 85,
                        "name": "16 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 13,
                        "second_product_group_name": "襄阳",
                        "third_product_group_id": 15,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 15,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 82,
                        "name": "2 vCPU 2G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 13,
                        "second_product_group_name": "襄阳",
                        "third_product_group_id": 15,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 15,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 83,
                        "name": "4 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 13,
                        "second_product_group_name": "襄阳",
                        "third_product_group_id": 15,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 15,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 84,
                        "name": "8 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 13,
                        "second_product_group_name": "襄阳",
                        "third_product_group_id": 15,
                        "third_product_group_name": "高宽",
                        "effective_product_group_id": 15,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 45,
                        "name": "12 vCPU 12G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 9,
                        "second_product_group_name": "西安高防",
                        "third_product_group_id": 14,
                        "third_product_group_name": "高防",
                        "effective_product_group_id": 14,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 46,
                        "name": "16 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 9,
                        "second_product_group_name": "西安高防",
                        "third_product_group_id": 14,
                        "third_product_group_name": "高防",
                        "effective_product_group_id": 14,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 42,
                        "name": "4 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 9,
                        "second_product_group_name": "西安高防",
                        "third_product_group_id": 14,
                        "third_product_group_name": "高防",
                        "effective_product_group_id": 14,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 43,
                        "name": "4 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 9,
                        "second_product_group_name": "西安高防",
                        "third_product_group_id": 14,
                        "third_product_group_name": "高防",
                        "effective_product_group_id": 14,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 44,
                        "name": "8 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 9,
                        "second_product_group_name": "西安高防",
                        "third_product_group_id": 14,
                        "third_product_group_name": "高防",
                        "effective_product_group_id": 14,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 10,
                        "name": "16 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": 1,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 6,
                        "name": "2 vCPU 2G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": 1,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 7,
                        "name": "4 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": 1,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 8,
                        "name": "4 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": 1,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 9,
                        "name": "8 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": 1,
                        "third_product_group_name": "三网精品",
                        "effective_product_group_id": 1,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 25,
                        "name": "16 vCPU 16G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": 2,
                        "third_product_group_name": "大宽带",
                        "effective_product_group_id": 2,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 22,
                        "name": "2 vCPU 2G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": 2,
                        "third_product_group_name": "大宽带",
                        "effective_product_group_id": 2,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 23,
                        "name": "4 vCPU 4G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": 2,
                        "third_product_group_name": "大宽带",
                        "effective_product_group_id": 2,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 24,
                        "name": "4 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": 2,
                        "third_product_group_name": "大宽带",
                        "effective_product_group_id": 2,
                        "effective_product_group_level": 3
                    },
                    {
                        "id": 26,
                        "name": "8 vCPU 8G",
                        "service_type_code": "vps",
                        "service_type_label": "云服务器",
                        "first_product_group_id": 1,
                        "first_product_group_name": "云服务器",
                        "second_product_group_id": 2,
                        "second_product_group_name": "香港",
                        "third_product_group_id": 2,
                        "third_product_group_name": "大宽带",
                        "effective_product_group_id": 2,
                        "effective_product_group_level": 3
                    }
                ],
                "billing_cycles": [],
                "billing_cycle_text": "全部周期可用",
                "validity_text": "2026-04-29 00:00 - 2026-05-04 00:00",
                "claimed_at": "2026-05-03 18:25:25",
                "used_at": null,
                "revoked_at": null,
                "granted_at": null,
                "created_at": "2026-05-03 18:25:25",
                "expires_at": "2026-05-04 00:00:00"
            }
        ],
        "total": 2,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240524
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:25  
· 响应状态码：200  
· 调用方式：GET /api/client/coupons  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\CouponController@index`  
· 请求校验：`App\Http\Requests\Client\Coupon\ListCouponsRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；CouponService::paginateForUser() 服务返回数组字段`  
· 中间件：`api, auth:sanctum, ensure.client`
