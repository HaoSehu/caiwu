# coupons

**请求方法**：GET  
**请求路径**：`/api/admin/coupons`  
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
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：IndexRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|in:0,1,expired；来源：IndexRequest |
| discount_type | string | 否 | 查询参数；校验规则：nullable\|in:"fixed","percentage"；来源：IndexRequest |
| distribution_type | string | 否 | 查询参数；校验规则：nullable\|in:"public","private"；来源：IndexRequest |
| discount_scope | string | 否 | 查询参数；校验规则：nullable\|in:"first_month","recurring","renew"；来源：IndexRequest |
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
| data.list.coupon_campaign_id | integer | 真实调用返回字段 |
| data.list.coupon_campaign_name | string | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.description | string | 真实调用返回字段 |
| data.list.distribution_type | string | 真实调用返回字段 |
| data.list.distribution_type_label | string | 真实调用返回字段 |
| data.list.discount_scope | string | 真实调用返回字段 |
| data.list.discount_scope_label | string | 真实调用返回字段 |
| data.list.discount_type | string | 真实调用返回字段 |
| data.list.discount_type_label | string | 真实调用返回字段 |
| data.list.discount_value | string | 真实调用返回字段 |
| data.list.discount_value_raw | integer | 真实调用返回字段 |
| data.list.discount_label | string | 真实调用返回字段 |
| data.list.min_amount | string | 真实调用返回字段 |
| data.list.min_amount_raw | integer | 真实调用返回字段 |
| data.list.max_discount_amount | null | 真实调用返回字段 |
| data.list.max_discount_amount_raw | null | 真实调用返回字段 |
| data.list.billing_cycles | array | 真实调用返回字段 |
| data.list.billing_cycle_text | string | 真实调用返回字段 |
| data.list.product_ids | array | 真实调用返回字段 |
| data.list.product_names | array | 真实调用返回字段 |
| data.list.product_scope_text | string | 真实调用返回字段 |
| data.list.first_order_only | boolean | 真实调用返回字段 |
| data.list.total_usage_limit | null | 真实调用返回字段 |
| data.list.per_user_limit | null | 真实调用返回字段 |
| data.list.used_count | integer | 真实调用返回字段 |
| data.list.recipient_count | integer | 真实调用返回字段 |
| data.list.user_ids | array | 真实调用返回字段 |
| data.list.remaining_stock | null | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.status_label | string | 真实调用返回字段 |
| data.list.display_status | string | 真实调用返回字段 |
| data.list.display_status_label | string | 真实调用返回字段 |
| data.list.display_status_reason | string | 真实调用返回字段 |
| data.list.sort_order | integer | 真实调用返回字段 |
| data.list.starts_at | null | 真实调用返回字段 |
| data.list.expires_at | null | 真实调用返回字段 |
| data.list.validity_text | string | 真实调用返回字段 |
| data.list.remark | string | 真实调用返回字段 |
| data.list.operator | string | 真实调用返回字段 |
| data.list.trace_id | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.list.can_update | boolean | 真实调用返回字段 |
| data.list.can_delete | boolean | 真实调用返回字段 |
| data.list.lock_reason | string | 真实调用返回字段 |
| data.list.locked_fields | array | 真实调用返回字段 |
| data.list.delete_reason | string | 真实调用返回字段 |
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
                "id": 5,
                "coupon_campaign_id": 0,
                "coupon_campaign_name": "",
                "name": "国内8折",
                "description": "",
                "distribution_type": "private",
                "distribution_type_label": "私有优惠券",
                "discount_scope": "first_month",
                "discount_scope_label": "首月优惠",
                "discount_type": "percentage",
                "discount_type_label": "折扣券",
                "discount_value": "80.00",
                "discount_value_raw": 80,
                "discount_label": "8 折优惠",
                "min_amount": "0.00",
                "min_amount_raw": 0,
                "max_discount_amount": null,
                "max_discount_amount_raw": null,
                "billing_cycles": [],
                "billing_cycle_text": "全部周期可用",
                "product_ids": [
                    82,
                    83,
                    84,
                    85,
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
                "product_names": [
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
                "product_scope_text": "gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs / gscs",
                "first_order_only": false,
                "total_usage_limit": null,
                "per_user_limit": null,
                "used_count": 1,
                "recipient_count": 1,
                "user_ids": [
                    440
                ],
                "remaining_stock": null,
                "status": 1,
                "status_label": "生效中",
                "display_status": "active",
                "display_status_label": "生效中",
                "display_status_reason": "当前可正常使用",
                "sort_order": 0,
                "starts_at": null,
                "expires_at": null,
                "validity_text": "长期有效",
                "remark": "",
                "operator": "cerbo",
                "trace_id": "1c36b42d-b165-4cae-9542-ead0e575d372",
                "created_at": "2026-05-23 09:14:38",
                "updated_at": "2026-05-23 10:11:32",
                "can_update": false,
                "can_delete": false,
                "lock_reason": "已发放的优惠券",
                "locked_fields": [
                    "distribution_type",
                    "discount_type",
                    "discount_scope"
                ],
                "delete_reason": "该优惠券已有人使用，不能删除"
            }
        ],
        "total": 3,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240484
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:44  
· 响应状态码：200  
· 调用方式：GET /api/admin/coupons  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\CouponController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:product.list`
