# 客户注册

**请求方法**：POST  
**请求路径**：`/api/client/register`  
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
| account | string | 是 | 请求体参数；校验规则：required\|string\|max:100\|closure:自定义校验；来源：RegisterRequest |
| code | string | 是 | 请求体参数；校验规则：required\|string\|size:6；来源：RegisterRequest |
| password | string | 是 | 请求体参数；校验规则：required\|string\|min:6\|confirmed；来源：RegisterRequest |
| password_confirmation | string | 是 | 请求体参数；由 `password` 的 confirmed 校验规则要求，必须与 `password` 一致；来源：RegisterRequest |
| nickname | string | 否 | 请求体参数；校验规则：nullable\|string\|max:50；来源：RegisterRequest |
| email | string(email) | 否 | 请求体参数；校验规则：nullable\|email\|max:100；来源：RegisterRequest |
| phone | string | 否 | 请求体参数；校验规则：nullable\|string\|max:20\|closure:自定义校验；来源：RegisterRequest |
| referral_code | string | 否 | 请求体参数；校验规则：nullable\|string\|max:24；来源：RegisterRequest |

### 请求示例（完整 JSON）
```json
{
    "account": "string",
    "code": "123456",
    "password": "password123",
    "password_confirmation": "password123",
    "nickname": "string",
    "email": "user@example.com",
    "phone": "13800138000",
    "referral_code": "123456"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.referrer_user_id | integer | 业务字段；由源码静态提取 |
| data.referral_code | string | 业务字段；由源码静态提取 |
| data.token | string | 业务字段；由源码静态提取 |
| data.user | object | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "注册成功",
    "data": {
        "referrer_user_id": 1,
        "referral_code": "string",
        "token": "sample_token",
        "user": []
    },
    "timestamp": 1760000000
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：接口为写操作、删除操作、支付/退款/开通/服务控制/通知发送/上游动作之一，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\AuthController@register`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, throttle:5,1,client-auth-register`
