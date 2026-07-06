# config

**请求方法**：GET  
**请求路径**：`/api/site/config`  
**调试状态**：✅ 通过

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| 无 | - | 否 | 无请求参数 |

### 请求示例（完整 JSON）
```json
{}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.site_name | string | 真实调用返回字段 |
| data.browser_title | string | 真实调用返回字段 |
| data.site_logo | string | 真实调用返回字段 |
| data.site_favicon | string | 真实调用返回字段 |
| data.client_console_icon | string | 真实调用返回字段 |
| data.service_qq_group | string | 真实调用返回字段 |
| data.service_phone | string | 真实调用返回字段 |
| data.service_email | string | 真实调用返回字段 |
| data.service_hours | string | 真实调用返回字段 |
| data.support_group_title | string | 真实调用返回字段 |
| data.support_group_text | string | 真实调用返回字段 |
| data.support_group_qr | string | 真实调用返回字段 |
| data.support_group_link | string | 真实调用返回字段 |
| data.terms_url | string | 真实调用返回字段 |
| data.privacy_url | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "site_name": "创欧云",
        "browser_title": "创欧云",
        "site_logo": "http://127.0.0.1:5175/media/logo1.svg",
        "site_favicon": "http://127.0.0.1:5175/media/logo1.svg",
        "client_console_icon": "http://127.0.0.1:5175/media/logo.svg",
        "service_qq_group": "1028089905",
        "service_phone": "1028089905",
        "service_email": "2908990438@qq.com",
        "service_hours": "",
        "support_group_title": "",
        "support_group_text": "",
        "support_group_qr": "http://127.0.0.1:5175/media/img_181926_2929.jpg",
        "support_group_link": "https://qm.qq.com/q/oqTRUMHiiQ",
        "terms_url": "https://www.coyjs.cn/notices/17?category=7",
        "privacy_url": "https://www.coyjs.cn/notices/20?category=7"
    },
    "timestamp": 1783240540
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:40  
· 响应状态码：200  
· 调用方式：GET /api/site/config  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteConfigController@index`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api`
