# 用户邮件日志

**请求方法**：GET  
**请求路径**：`/api/admin/users/{user}/email-logs`  
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
| user | integer\|string | 是 | 路径参数；来自路由占位 `{user}` |
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：UserLogPaginationRequest |
| page_size | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：UserLogPaginationRequest |

### 请求示例（完整 JSON）
```json
{
    "page": 1,
    "page_size": 1
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.list | array | 分页列表数据 |
| data.list.id | integer | 真实调用返回字段 |
| data.list.template_code | string | 真实调用返回字段 |
| data.list.to_email | string | 真实调用返回字段 |
| data.list.subject | string | 真实调用返回字段 |
| data.list.content | string | 真实调用返回字段 |
| data.list.status | string | 真实调用返回字段 |
| data.list.error_msg | null | 真实调用返回字段 |
| data.list.sent_at | string | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.total | integer | 总条数 |
| data.page | integer | 当前页码 |
| data.page_size | integer | 每页数量 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "id": 1483,
                "template_code": "100002",
                "to_email": "2908990438@qq.com",
                "subject": "创欧云 登录提醒",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>创欧云 登录提醒</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }\n    .mail-...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-05T08:34:46.000000Z",
                "created_at": "2026-07-05T08:34:44.000000Z",
                "updated_at": "2026-07-05T08:34:46.000000Z"
            }
        ],
        "total": 343,
        "page": 1,
        "page_size": 1
    },
    "timestamp": 1783240518
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:18  
· 响应状态码：200  
· 调用方式：GET /api/admin/users/{user}/email-logs  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\UserController@emailLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.detail`
