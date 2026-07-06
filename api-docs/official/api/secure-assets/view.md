# view

**请求方法**：GET  
**请求路径**：`/api/secure-assets/view`  
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
| path | string | 是 | 查询参数；只能是 SecureAsset 允许前缀下的图片路径 |
| expires | integer | 是 | 签名 URL 参数；由 `SecureAsset::temporaryUrl()` 生成 |
| signature | string | 是 | 签名 URL 参数；路由中间件 `signed:relative` 校验 |

### 请求示例（完整 JSON）
```json
{
    "path": "private/tickets/example.png",
    "expires": 1783240600,
    "signature": "***已脱敏***"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| body | binary | 图片文件流；成功响应不是 JSON |
| header.Cache-Control | string | private, max-age=300, must-revalidate |
| header.X-Content-Type-Options | string | nosniff |
| header.Content-Disposition | string | inline 文件名 |

### 返回示例（完整 JSON）
```json
{
    "说明": "成功响应为图片文件流，非 JSON；需通过 SecureAsset::temporaryUrl() 生成带签名 URL 后访问"
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:40  
· 响应状态码：403  
· 调用方式：GET /api/secure-assets/view  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是直接调用未携带 Laravel signed URL 参数。源码成功响应为 `response()->file()` 图片流。

### 源码依据
· 控制器动作：`App\Http\Controllers\SecureAssetController@show`  
· 请求校验：`App\Http\Requests\Site\ShowSecureAssetRequest::rules()`  
· 响应结构：`控制器直接 `response()` 返回，需实际调用确认`  
· 中间件：`web, signed:relative`
