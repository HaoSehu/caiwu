# 以该客户身份登录（生成客户端 token）

**请求方法**：POST  
**请求路径**：`/api/admin/users/{user}/login-as`  
**调试状态**：⬜ 待调试

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
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.admin_id | integer | 业务字段；由源码静态提取 |
| data.ip_address | array | 业务字段；由源码静态提取 |
| data.user_agent | string | 业务字段；由源码静态提取 |
| data.login_code | string | 业务字段；由源码静态提取 |
| data.expires_in | string | 业务字段；由源码静态提取 |
| data.user | object | 业务字段；由源码静态提取 |
| data.target_url | string | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "admin_id": 1,
        "ip_address": [],
        "user_agent": "string",
        "login_code": "string",
        "expires_in": "string",
        "user": [],
        "target_url": "string"
    },
    "timestamp": 1760000000
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：接口可能消费令牌、触发外部调用、修改配置或启动业务流程，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\UserController@loginAs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.login_as`
