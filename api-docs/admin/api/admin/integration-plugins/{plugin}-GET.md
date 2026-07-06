# {plugin}

**请求方法**：GET  
**请求路径**：`/api/admin/integration-plugins/{plugin}`  
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
| plugin | integer|string | 是 | 路径参数；Laravel 路由模型绑定，当前模型默认按主键 ID 绑定；传 slug 可能返回 404 |

### 请求示例（完整 JSON）
```json
{
    "plugin": 1
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.id | integer | 插件 ID |
| data.slug | string | 插件标识；路由模型绑定使用主键时可传 ID |
| data.name | string | 插件名称 |
| data.domain | string | 插件能力域 |
| data.version | string | 插件版本 |
| data.description | string | 插件说明 |
| data.enabled | boolean|integer | 是否启用 |
| data.installed | boolean|integer | 是否已安装 |
| data.config | object | 普通配置 |
| data.secrets | object | 敏感配置存在状态/脱敏信息 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 1,
        "slug": "alipay",
        "name": "支付宝",
        "domain": "gateways",
        "version": "1.0.0",
        "description": "支付宝支付插件",
        "enabled": true,
        "installed": true,
        "config": {},
        "secrets": {}
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:49  
· 响应状态码：404  
· 调用方式：GET /api/admin/integration-plugins/{plugin}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是样例参数未命中路由模型绑定。源码 `IntegrationPlugin $plugin` 默认按主键绑定。

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\IntegrationPluginController@show`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:integration_plugin.view`
