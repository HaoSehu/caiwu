# service-options

**请求方法**：GET  
**请求路径**：`/api/client/tickets/service-options`  
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
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：ServiceOptionsRequest |
| limit | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:50；来源：ServiceOptionsRequest |

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
| data.service_name | string | 真实调用返回字段 |
| data.product_name | string | 真实调用返回字段 |
| data.domain | string | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.status_label | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": [
        {
            "id": 189,
            "name": "gscs / gscs-2vcpu-2gib",
            "service_name": "gscs-2vcpu-2gib",
            "product_name": "gscs",
            "domain": "ser784470365925",
            "status": 1,
            "status_label": "已开通"
        },
        {
            "id": 188,
            "name": "gscs / gscs-2vcpu-2gib",
            "service_name": "gscs-2vcpu-2gib",
            "product_name": "gscs",
            "domain": "ser446022637145",
            "status": 0,
            "status_label": "开通中"
        },
        {
            "id": 187,
            "name": "gscs / gscs-2vcpu-2gib",
            "service_name": "gscs-2vcpu-2gib",
            "product_name": "gscs",
            "domain": "ser082512144412",
            "status": 0,
            "status_label": "开通中"
        },
        {
            "id": 97,
            "name": "gscs / 美国1区精品网 2H2G",
            "service_name": "美国1区精品网 2H2G",
            "product_name": "gscs",
            "domain": "ser244169726736",
            "status": 4,
            "status_label": "已取消"
        },
        {
            "id": 95,
            "name": "gscs / 美国1区精品网 4H4G",
            "service_name": "美国1区精品网 4H4G",
            "product_name": "gscs",
            "domain": "ser650380919131",
            "status": 4,
            "status_label": "已取消"
        },
        {
            "id": 91,
            "name": "gscs / 襄阳高防大带宽 2H2G",
            "service_name": "襄阳高防大带宽 2H2G",
            "product_name": "gscs",
            "domain": "ser785911989116",
            "status": 4,
            "status_label": "已取消"
        },
        {
            "id": 89,
            "name": "2 vCPU 2G / 好色狐の机器",
            "service_name": "好色狐の机器",
            "product_name": "2 vCPU 2G",
            "domain": "srv592496220",
            "status": 1,
            "status_label": "已开通"
        },
        {
            "id": 88,
            "name": "gscs / 美国1区精品网 2H2G",
            "service_name": "美国1区精品网 2H2G",
            "product_name": "gscs",
            "domain": "ser707625720719",
            "status": 4,
            "status_label": "已取消"
        }
    ],
    "timestamp": 1783240539
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:39  
· 响应状态码：200  
· 调用方式：GET /api/client/tickets/service-options  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\TicketController@serviceOptions`  
· 请求校验：`App\Http\Requests\Client\Ticket\ServiceOptionsRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
