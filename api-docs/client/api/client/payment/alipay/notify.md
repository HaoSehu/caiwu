# 支付宝异步通知（向后兼容，委托给通用入口）

**请求方法**：POST  
**请求路径**：`/api/client/payment/alipay/notify`  
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
| out_trade_no | string | 否 | 请求体参数；控制器通过 `$request->input()` 读取；未发现 FormRequest 明确规则 |
| trade_no | string | 否 | 请求体参数；控制器通过 `$request->input()` 读取；未发现 FormRequest 明确规则 |
| trade_status | array | 否 | 请求体参数；控制器通过 `$request->input()` 读取；未发现 FormRequest 明确规则 |
| app_id | integer | 否 | 请求体参数；控制器通过 `$request->input()` 读取；未发现 FormRequest 明确规则 |

### 请求示例（完整 JSON）
```json
{
    "out_trade_no": "string",
    "trade_no": "string",
    "trade_status": [],
    "app_id": 1
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| HTTP body | string\|binary | 非统一 JSON 响应；响应体由控制器直接返回 |

### 返回示例（完整 JSON）
```json
{
    "待调试后补充": "非统一 JSON 响应，需实际调用确认响应体"
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：接口可能消费令牌、触发外部调用、修改配置或启动业务流程，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\PaymentCallbackController@alipayNotify`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, verify.alipay.callback`
