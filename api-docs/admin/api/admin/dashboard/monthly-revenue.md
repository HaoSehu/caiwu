# monthly-revenue

**请求方法**：GET  
**请求路径**：`/api/admin/dashboard/monthly-revenue`  
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
| data.revenue_by_product | array | 真实调用返回字段 |
| data.revenue_by_product.label | string | 真实调用返回字段 |
| data.revenue_by_product.amount | integer | 真实调用返回字段 |
| data.revenue_by_product.count | integer | 真实调用返回字段 |
| data.daily_revenue | array | 真实调用返回字段 |
| data.daily_revenue.date | string | 真实调用返回字段 |
| data.daily_revenue.day | integer | 真实调用返回字段 |
| data.daily_revenue.amount | integer | 真实调用返回字段 |
| data.daily_revenue.count | integer | 真实调用返回字段 |
| data.month_label | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "revenue_by_product": [
            {
                "label": "未知产品",
                "amount": 220,
                "count": 9
            },
            {
                "label": "gscs-2vcpu-2gib",
                "amount": 119,
                "count": 3
            },
            {
                "label": "gscs",
                "amount": 90,
                "count": 2
            }
        ],
        "daily_revenue": [
            {
                "date": "2026-07-01",
                "day": 1,
                "amount": 60,
                "count": 2
            },
            {
                "date": "2026-07-02",
                "day": 2,
                "amount": 1,
                "count": 1
            },
            {
                "date": "2026-07-03",
                "day": 3,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-04",
                "day": 4,
                "amount": 367,
                "count": 10
            },
            {
                "date": "2026-07-05",
                "day": 5,
                "amount": 1,
                "count": 1
            },
            {
                "date": "2026-07-06",
                "day": 6,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-07",
                "day": 7,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-08",
                "day": 8,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-09",
                "day": 9,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-10",
                "day": 10,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-11",
                "day": 11,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-12",
                "day": 12,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-13",
                "day": 13,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-14",
                "day": 14,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-15",
                "day": 15,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-16",
                "day": 16,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-17",
                "day": 17,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-18",
                "day": 18,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-19",
                "day": 19,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-20",
                "day": 20,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-21",
                "day": 21,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-22",
                "day": 22,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-23",
                "day": 23,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-24",
                "day": 24,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-25",
                "day": 25,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-26",
                "day": 26,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-27",
                "day": 27,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-28",
                "day": 28,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-29",
                "day": 29,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-30",
                "day": 30,
                "amount": 0,
                "count": 0
            },
            {
                "date": "2026-07-31",
                "day": 31,
                "amount": 0,
                "count": 0
            }
        ],
        "month_label": "2026年7月"
    },
    "timestamp": 1783240486
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:46  
· 响应状态码：200  
· 调用方式：GET /api/admin/dashboard/monthly-revenue  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\DashboardController@monthlyRevenue`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:dashboard.view`
