# upgrade

**请求方法**：GET  
**请求路径**：`/api/client/services/{id}/upgrade`  
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
| id | integer|string | 是 | 路径参数；当前用户服务 ID |

### 请求示例（完整 JSON）
```json
{
    "id": 1
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object|array|null | 业务数据 |
| timestamp | integer | Unix 秒级时间戳 |
| data.current | object | 当前服务/商品配置 |
| data.products | array | 可升降级目标商品列表 |
| data.products[].id | integer | 目标商品 ID |
| data.products[].name | string | 目标商品名称 |
| data.products[].billing_cycles | array | 可选计费周期 |
| data.products[].config_options | array | 配置项 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "current": {
            "service_id": 1,
            "product_id": 1
        },
        "products": [
            {
                "id": 2,
                "name": "升级套餐",
                "billing_cycles": [
                    "monthly"
                ],
                "config_options": []
            }
        ]
    },
    "timestamp": 1783240000
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:38  
· 响应状态码：422  
· 调用方式：GET /api/client/services/{id}/upgrade  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码补充说明
本次异常原因是样例服务/上游上下文无法读取升降级选项；源码正常返回当前配置和可升级商品列表。

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@hostUpgradePreview`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
