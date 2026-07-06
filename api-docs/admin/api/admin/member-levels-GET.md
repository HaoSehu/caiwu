# member-levels

**请求方法**：GET  
**请求路径**：`/api/admin/member-levels`  
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
| data | array | 业务数据 |
| data.id | integer | 真实调用返回字段 |
| data.name | string | 真实调用返回字段 |
| data.code | string | 真实调用返回字段 |
| data.sales_amount_min | string | 真实调用返回字段 |
| data.sales_amount_max | string | 真实调用返回字段 |
| data.reward_rate | string | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.sort_order | integer | 真实调用返回字段 |
| data.remark | null | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| data.updated_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": [
        {
            "id": 1,
            "name": "v1",
            "code": "v1",
            "sales_amount_min": "0.00",
            "sales_amount_max": "300.00",
            "reward_rate": "5.00",
            "status": 1,
            "sort_order": 0,
            "remark": null,
            "created_at": "2026-04-21 23:13:43",
            "updated_at": "2026-04-21 23:13:43"
        },
        {
            "id": 2,
            "name": "v2",
            "code": "v2",
            "sales_amount_min": "301.00",
            "sales_amount_max": "800.00",
            "reward_rate": "10.00",
            "status": 1,
            "sort_order": 0,
            "remark": null,
            "created_at": "2026-04-21 23:14:14",
            "updated_at": "2026-04-21 23:14:14"
        },
        {
            "id": 3,
            "name": "v3",
            "code": "v3",
            "sales_amount_min": "801.00",
            "sales_amount_max": null,
            "reward_rate": "15.00",
            "status": 1,
            "sort_order": 0,
            "remark": null,
            "created_at": "2026-04-21 23:14:31",
            "updated_at": "2026-04-21 23:14:31"
        }
    ],
    "timestamp": 1783240510
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:10  
· 响应状态码：200  
· 调用方式：GET /api/admin/member-levels  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\MemberLevelController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:member_level.list`
