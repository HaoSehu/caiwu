# 保存官网首页 Hero 轮播配置。

**请求方法**：POST  
**请求路径**：`/api/admin/site/home-hero`  
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
| slides | array | 是 | 请求体参数；校验规则：required\|array\|min:1\|max:5；来源：UpdateHomeHeroRequest |
| slides.*.key | string | 否 | 请求体参数；校验规则：nullable\|string\|max:64；来源：UpdateHomeHeroRequest |
| slides.*.rail_title | string | 是 | 请求体参数；校验规则：required\|string\|max:20；来源：UpdateHomeHeroRequest |
| slides.*.title | string | 是 | 请求体参数；校验规则：required\|string\|max:80；来源：UpdateHomeHeroRequest |
| slides.*.desc | string | 是 | 请求体参数；校验规则：required\|string\|max:300；来源：UpdateHomeHeroRequest |
| slides.*.primary_text | string | 是 | 请求体参数；校验规则：required\|string\|max:20；来源：UpdateHomeHeroRequest |
| slides.*.primary_path | string | 是 | 请求体参数；校验规则：required\|string\|max:255；来源：UpdateHomeHeroRequest |
| slides.*.secondary_text | string | 是 | 请求体参数；校验规则：required\|string\|max:20；来源：UpdateHomeHeroRequest |
| slides.*.secondary_path | string | 是 | 请求体参数；校验规则：required\|string\|max:255；来源：UpdateHomeHeroRequest |
| slides.*.shape | string | 否 | 请求体参数；校验规则：nullable\|in:"computer","connection","security","value","support"；来源：UpdateHomeHeroRequest |
| slides.*.video | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateHomeHeroRequest |
| slides.*.ribbon | string | 否 | 请求体参数；校验规则：nullable\|string\|max:10；来源：UpdateHomeHeroRequest |
| slides.*.ribbon_type | string | 否 | 请求体参数；校验规则：nullable\|in:"hot","warm","new"；来源：UpdateHomeHeroRequest |
| features | array | 是 | 请求体参数；校验规则：required\|array\|min:1\|max:5；来源：UpdateHomeHeroRequest |
| features.*.key | string | 否 | 请求体参数；校验规则：nullable\|string\|max:64；来源：UpdateHomeHeroRequest |
| features.*.kicker | string | 是 | 请求体参数；校验规则：required\|string\|max:20；来源：UpdateHomeHeroRequest |
| features.*.title | string | 是 | 请求体参数；校验规则：required\|string\|max:50；来源：UpdateHomeHeroRequest |
| features.*.desc | string | 是 | 请求体参数；校验规则：required\|string\|max:120；来源：UpdateHomeHeroRequest |
| features.*.path | string | 否 | 请求体参数；校验规则：nullable\|string\|max:255；来源：UpdateHomeHeroRequest |

### 请求示例（完整 JSON）
```json
{
    "slides": [],
    "slides.*.key": "string",
    "slides.*.rail_title": "string",
    "slides.*.title": "string",
    "slides.*.desc": "string",
    "slides.*.primary_text": "string",
    "slides.*.primary_path": "string",
    "slides.*.secondary_text": "string",
    "slides.*.secondary_path": "string",
    "slides.*.shape": "\"computer\"",
    "slides.*.video": "string",
    "slides.*.ribbon": "string",
    "slides.*.ribbon_type": "\"hot\"",
    "features": [],
    "features.*.key": "string",
    "features.*.kicker": "string",
    "features.*.title": "string",
    "features.*.desc": "string",
    "features.*.path": "string"
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.slides | array | 业务字段；由源码静态提取 |
| data.features | array | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "首页 Banner 已保存",
    "data": {
        "slides": [],
        "features": []
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
· 控制器动作：`App\Http\Controllers\Admin\HomeHeroController@update`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:site.manage`
