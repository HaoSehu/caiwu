# 实名记录详情

**请求方法**：GET  
**请求路径**：`/api/admin/verifications/{user}`  
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
| user | integer\|string | 是 | 路径参数；来自路由占位 `{user}` |

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
| data.id | integer | 真实调用返回字段 |
| data.display_name | string | 真实调用返回字段 |
| data.email | string | 真实调用返回字段 |
| data.phone | string | 真实调用返回字段 |
| data.real_name | string | 真实调用返回字段 |
| data.id_card_masked | string | 真实调用返回字段 |
| data.verification_status | integer | 真实调用返回字段 |
| data.verification_message | string | 真实调用返回字段 |
| data.verification_certify_id | string | 真实调用返回字段 |
| data.verification_biz_code | string | 真实调用返回字段 |
| data.verification_method_label | string | 真实调用返回字段 |
| data.verification_type_label | string | 真实调用返回字段 |
| data.document_type_label | string | 真实调用返回字段 |
| data.identity_region_label | string | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| data.updated_at | string | 真实调用返回字段 |
| data.verified_at | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 1,
        "display_name": "李维佳",
        "email": "2908990438@qq.com",
        "phone": "19219178808",
        "real_name": "李维佳",
        "id_card_masked": "***已脱敏***",
        "verification_status": 2,
        "verification_message": "审核通过",
        "verification_certify_id": "***已脱敏***",
        "verification_biz_code": "FACE",
        "verification_method_label": "人脸识别",
        "verification_type_label": "个人认证",
        "document_type_label": "居民身份证",
        "identity_region_label": "***已脱敏***",
        "created_at": "2025-01-17 06:30:14",
        "updated_at": "2026-07-05 16:34:43",
        "verified_at": "2026-07-04 23:49:47"
    },
    "timestamp": 1783240521
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:21  
· 响应状态码：200  
· 调用方式：GET /api/admin/verifications/{user}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\VerificationController@show`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:verification.list`
