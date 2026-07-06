# 站内信中心：完整列表（可只看未读，分页） */

**请求方法**：GET  
**请求路径**：`/api/client/notifications`  
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
| unread_only | boolean | 否 | 查询参数；校验规则：nullable\|boolean；来源：IndexRequest |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：IndexRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:50；来源：IndexRequest |

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
| data.list.raw_id | integer | 真实调用返回字段 |
| data.list.source | string | 真实调用返回字段 |
| data.list.type | string | 真实调用返回字段 |
| data.list.type_label | string | 真实调用返回字段 |
| data.list.title | string | 真实调用返回字段 |
| data.list.summary | string | 真实调用返回字段 |
| data.list.link | string | 真实调用返回字段 |
| data.list.read | boolean | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
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
                "id": "msg-32",
                "raw_id": 32,
                "source": "message",
                "type": "order_paid",
                "type_label": "订购提醒",
                "title": "开通成功",
                "summary": "「gscs-2vcpu-2gib」已处理完成，账单号 zd202607042209238520。",
                "link": "/client/services/189",
                "read": false,
                "created_at": "2026-07-04 22:09:59"
            }
        ],
        "total": 11,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240526
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:26  
· 响应状态码：200  
· 调用方式：GET /api/client/notifications  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\NotificationController@index`  
· 请求校验：`App\Http\Requests\Client\Notification\IndexRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器返回分页结构；控制器 success([...]) 数组字段`  
· 中间件：`api, auth:sanctum, ensure.client`
