# 获取配置

**请求方法**：GET  
**请求路径**：`/api/admin/settings`  
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
| group | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：IndexSettingRequest |

### 请求示例（完整 JSON）
```json
{}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | array | 业务数据 |
| data.group | string | 真实调用返回字段 |
| data.key | string | 真实调用返回字段 |
| data.value | string | 真实调用返回字段 |
| data.is_secret | string | 真实调用返回字段 |
| data.has_value | boolean | 真实调用返回字段 |
| data.masked_value | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": [
        {
            "group": "system",
            "key": "provision_hostname_charsets",
            "value": "number",
            "is_secret": "***已脱敏***",
            "has_value": true,
            "masked_value": "number"
        },
        {
            "group": "system",
            "key": "provision_hostname_enforce",
            "value": "0",
            "is_secret": "***已脱敏***",
            "has_value": true,
            "masked_value": "0"
        },
        {
            "group": "system",
            "key": "provision_hostname_length",
            "value": "12",
            "is_secret": "***已脱敏***",
            "has_value": true,
            "masked_value": "12"
        },
        {
            "group": "system",
            "key": "provision_hostname_prefix",
            "value": "srv",
            "is_secret": "***已脱敏***",
            "has_value": true,
            "masked_value": "srv"
        }
    ],
    "timestamp": 1783240516
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:16  
· 响应状态码：200  
· 调用方式：GET /api/admin/settings  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\SettingController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:settings.view`
