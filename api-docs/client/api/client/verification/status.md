# 查询认证状态

**请求方法**：GET  
**请求路径**：`/api/client/verification/status`  
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
| certify_id | string | 是 | 请求参数；实名认证初始化接口返回的认证会话 ID |

### 请求示例（完整 JSON）
```json
{
    "certify_id": "CERTIFY202607050001"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.certify_id | string | 认证会话 ID |
| data.status | string|integer | 认证状态 |
| data.status_label | string | 认证状态文案 |
| data.verified | boolean | 是否认证通过 |
| data.message | string|null | 认证结果说明 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "certify_id": "CERTIFY202607050001",
        "status": "pending",
        "status_label": "认证中",
        "verified": false,
        "message": null
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:40  
· 响应状态码：422  
· 调用方式：GET /api/client/verification/status  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
正常调用必须使用实名认证初始化接口返回的 `certify_id`。本次异常是手工样例参数缺失/无效。

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\VerificationController@status`  
· 请求校验：`App\Http\Requests\Client\Verification\StatusRequest::rules()`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
