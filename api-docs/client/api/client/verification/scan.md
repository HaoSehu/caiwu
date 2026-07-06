# scan

**请求方法**：GET  
**请求路径**：`/api/client/verification/scan`  
**调试状态**：⚠️ 异常

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| certify_id | string | 是 | 查询参数；实名认证初始化接口返回的认证会话 ID |

### 请求示例（完整 JSON）
```json
{
    "certify_id": "CERTIFY202607050001"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| body | html | 扫码落地页 HTML；成功/失败均不是 JSON |
| certify_id | string | 查询参数；认证会话 ID |

### 返回示例（完整 JSON）
```json
{
    "说明": "成功响应为实名认证扫码落地页 HTML，非 JSON；certify_id 必须来自 init/qrcode 流程"
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:40  
· 响应状态码：410  
· 调用方式：GET /api/client/verification/scan  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是样例 `certify_id` 不存在或已失效；源码成功响应为 HTML 页面。

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\VerificationController@scan`  
· 请求校验：`无 FormRequest`  
· 响应结构：`控制器直接 `response()` 返回，需实际调用确认`  
· 中间件：`api`
