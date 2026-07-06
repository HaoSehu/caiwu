# 第三方支付网关异步通知（通用入口）

**请求方法**：POST  
**请求路径**：`/api/client/payment/notify/{gateway}`  
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
| gateway | string | 是 | 路径参数；来自路由占位 `{gateway}` |

### 请求示例（完整 JSON）
```json
{}
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
· 未调用原因：接口可能消费令牌、触发外部调用、修改配置或启动业务流程，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\PaymentCallbackController@notify`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, verify.payment.callback`
