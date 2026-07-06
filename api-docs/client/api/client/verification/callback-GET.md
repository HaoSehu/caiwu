# 认证完成回跳页

**请求方法**：GET  
**请求路径**：`/api/client/verification/callback`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| certify_id | integer | 否 | 查询参数；控制器通过 `$request->input()` 读取；未发现 FormRequest 明确规则 |
| order_no | string | 否 | 查询参数；控制器通过 `$request->input()` 读取；未发现 FormRequest 明确规则 |

### 请求示例（完整 JSON）
```json
{}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| body | object | 真实调用返回字段；敏感值已脱敏 |
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 40001,
    "message": "签名验证失败",
    "data": null,
    "timestamp": 1783238914
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：接口可能消费令牌、触发外部调用、修改配置或启动业务流程，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\VerificationController@callback`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, verify.callback`
