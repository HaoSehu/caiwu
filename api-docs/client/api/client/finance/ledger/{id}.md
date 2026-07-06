# {id}

**请求方法**：GET  
**请求路径**：`/api/client/finance/ledger/{id}`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| id | integer\|string | 是 | 路径参数；来自路由占位 `{id}` |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListFinanceLedgerRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：ListFinanceLedgerRequest |
| tab | string | 否 | 查询参数；校验规则：nullable\|in:"all","invoices","balance","recharge","adjustment"；来源：ListFinanceLedgerRequest |
| event_type | string | 否 | 查询参数；校验规则：nullable\|in:"invoice_payment","invoice_refund","recharge","manual_recharge","manual_deduction","referral_credit_cash","system_adjustment"；来源：ListFinanceLedgerRequest |
| direction | string | 否 | 查询参数；校验规则：nullable\|in:"in","out"；来源：ListFinanceLedgerRequest |
| status | integer | 否 | 查询参数；校验规则：nullable\|integer；来源：ListFinanceLedgerRequest |
| service_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：ListFinanceLedgerRequest |
| invoice_no | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：ListFinanceLedgerRequest |
| payment_no | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：ListFinanceLedgerRequest |
| start_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：ListFinanceLedgerRequest |
| end_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date_format:Y-m-d；来源：ListFinanceLedgerRequest |
| date_range | string | 否 | 查询参数；校验规则：prohibited；来源：ListFinanceLedgerRequest |

### 请求示例（完整 JSON）
```json
{
    "page": 1,
    "page_size": 1,
    "tab": "\"all\"",
    "event_type": "\"invoice_payment\"",
    "direction": "\"in\"",
    "status": 1,
    "service_id": 1,
    "invoice_no": "string",
    "payment_no": "string",
    "start_date": "2026-07-05",
    "end_date": "2026-07-05"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data | object\|array\|null | 待调试后补充；未能从源码静态确认业务字段 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": "待调试后补充",
    "timestamp": 1760000000
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：缺少可安全复用的本地样例 ID，未构造临时数据

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\FinanceLedgerController@show`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.client`
