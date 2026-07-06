# 实名统计与接口配置

**请求方法**：GET  
**请求路径**：`/api/admin/verifications/summary`  
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
| data.stats | object | 真实调用返回字段 |
| data.stats.total | integer | 真实调用返回字段 |
| data.stats.verified | integer | 真实调用返回字段 |
| data.stats.pending | integer | 真实调用返回字段 |
| data.stats.failed | integer | 真实调用返回字段 |
| data.stats.unbound | integer | 真实调用返回字段 |
| data.config | object | 真实调用返回字段 |
| data.config.verification_api_masked | string | 真实调用返回字段 |
| data.config.verification_biz_code | string | 真实调用返回字段 |
| data.config.configured | boolean | 真实调用返回字段 |
| data.config.driver_key | string | 真实调用返回字段 |
| data.config.plugin_id | integer | 真实调用返回字段 |
| data.config.free_attempts | integer | 真实调用返回字段 |
| data.config.retry_fee | integer | 真实调用返回字段 |
| data.config.charge_enabled | boolean | 真实调用返回字段 |
| data.config.amount | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "stats": {
            "total": 463,
            "verified": 88,
            "pending": 6,
            "failed": 13,
            "unbound": 1
        },
        "config": {
            "verification_api_masked": "",
            "verification_biz_code": "FACE",
            "configured": false,
            "driver_key": "baidu_face",
            "plugin_id": 15,
            "free_attempts": 0,
            "retry_fee": 0,
            "charge_enabled": false,
            "amount": 0
        }
    },
    "timestamp": 1783240520
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:20  
· 响应状态码：200  
· 调用方式：GET /api/admin/verifications/summary  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\VerificationController@summary`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:verification.list`
