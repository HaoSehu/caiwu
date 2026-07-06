# product-income-summary

**请求方法**：GET  
**请求路径**：`/api/admin/finance/product-income-summary`  
**调试状态**：⚠️ 异常

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| month | string | 是 | 查询参数；格式 `YYYY-MM`；未传 start_date 时必填 |
| start_date | string | 否 | 查询参数；格式 `YYYY-MM-DD`；与 end_date 成对使用 |
| end_date | string | 否 | 查询参数；格式 `YYYY-MM-DD`；必须大于等于 start_date |

### 请求示例（完整 JSON）
```json
{
    "month": "2026-07"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.month | string | 统计月份或起始日期 |
| data.range.start | string | 统计开始日期 |
| data.range.end | string | 统计结束日期 |
| data.total_income | string|number | 产品收入合计 |
| data.list | array | 产品收入列表 |
| data.list[].product_id | integer | 商品 ID |
| data.list[].product_name | string | 商品名称 |
| data.list[].income | string|number | 收入金额 |
| data.list[].order_count | integer | 订单数量 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "month": "2026-07",
        "range": {
            "start": "2026-07-01",
            "end": "2026-07-31"
        },
        "total_income": "0.00",
        "list": [
            {
                "product_id": 1,
                "product_name": "云服务器",
                "income": "0.00",
                "order_count": 0
            }
        ]
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:47  
· 响应状态码：422  
· 调用方式：GET /api/admin/finance/product-income-summary  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是未携带 `month` 或日期区间；源码 FormRequest 要求 `month` 或 `start_date/end_date`。

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\FinanceMenuController@productIncomeSummary`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:finance.report`
