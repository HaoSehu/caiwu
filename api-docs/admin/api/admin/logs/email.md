# email

**请求方法**：GET  
**请求路径**：`/api/admin/logs/email`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：EmailLogListRequest |
| per_page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:50；来源：EmailLogListRequest |
| email | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：EmailLogListRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：EmailLogListRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|in:pending,success,failed；来源：EmailLogListRequest |
| plugin_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：EmailLogListRequest |
| driver_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：EmailLogListRequest |
| trace_id | string | 否 | 查询参数；校验规则：nullable\|string\|max:64；来源：EmailLogListRequest |

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
| data.current_page | integer | 真实调用返回字段 |
| data.data | array | 真实调用返回字段 |
| data.data.id | integer | 真实调用返回字段 |
| data.data.template_code | string | 真实调用返回字段 |
| data.data.to_email | string | 真实调用返回字段 |
| data.data.subject | string | 真实调用返回字段 |
| data.data.content | string | 真实调用返回字段 |
| data.data.status | string | 真实调用返回字段 |
| data.data.error_msg | null | 真实调用返回字段 |
| data.data.sent_at | string | 真实调用返回字段 |
| data.data.created_at | string | 真实调用返回字段 |
| data.data.updated_at | string | 真实调用返回字段 |
| data.data.plugin_id | integer | 真实调用返回字段 |
| data.data.driver_key | string | 真实调用返回字段 |
| data.data.trace_id | string | 真实调用返回字段 |
| data.first_page_url | string | 真实调用返回字段 |
| data.from | integer | 真实调用返回字段 |
| data.last_page | integer | 真实调用返回字段 |
| data.last_page_url | string | 真实调用返回字段 |
| data.links | array | 真实调用返回字段 |
| data.links.url | null | 真实调用返回字段 |
| data.links.label | string | 真实调用返回字段 |
| data.links.page | null | 真实调用返回字段 |
| data.links.active | boolean | 真实调用返回字段 |
| data.next_page_url | string | 真实调用返回字段 |
| data.path | string | 真实调用返回字段 |
| data.per_page | integer | 真实调用返回字段 |
| data.prev_page_url | null | 真实调用返回字段 |
| data.to | integer | 真实调用返回字段 |
| data.total | integer | 总条数 |
| data.summary | array | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "current_page": 1,
        "data": [
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
                "updated_at": "2026-07-05T08:34:46.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100002:81ef5b0e63d9480a84e440f0d09e465a"
            },
            {
                "id": 1482,
                "template_code": "100002",
                "to_email": "2908990438@qq.com",
                "subject": "创欧云 登录提醒",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>创欧云 登录提醒</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }\n    .mail-...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-05T08:33:23.000000Z",
                "created_at": "2026-07-05T08:33:22.000000Z",
                "updated_at": "2026-07-05T08:33:23.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100002:b69787cfaa864858936ecd327fd80982"
            },
            {
                "id": 1481,
                "template_code": "100002",
                "to_email": "2908990438@qq.com",
                "subject": "创欧云 登录提醒",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>创欧云 登录提醒</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }\n    .mail-...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-05T08:31:25.000000Z",
                "created_at": "2026-07-05T08:31:22.000000Z",
                "updated_at": "2026-07-05T08:31:25.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100002:68ffd337e07d44e48f742b385a46592c"
            },
            {
                "id": 1480,
                "template_code": "100002",
                "to_email": "2908990438@qq.com",
                "subject": "创欧云 登录提醒",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>创欧云 登录提醒</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }\n    .mail-...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-05T08:27:55.000000Z",
                "created_at": "2026-07-05T08:27:53.000000Z",
                "updated_at": "2026-07-05T08:27:55.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100002:3483b90c0e1744df8b616c7d8a4cb259"
            },
            {
                "id": 1479,
                "template_code": "100002",
                "to_email": "2908990438@qq.com",
                "subject": "创欧云 登录提醒",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>创欧云 登录提醒</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }\n    .mail-...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-05T08:08:05.000000Z",
                "created_at": "2026-07-05T08:08:03.000000Z",
                "updated_at": "2026-07-05T08:08:05.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100002:d9257bc22ae345d1bff6c08363a3ad4c"
            },
            {
                "id": 1478,
                "template_code": "100002",
                "to_email": "2908990438@qq.com",
                "subject": "创欧云 登录提醒",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>创欧云 登录提醒</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }\n    .mail-...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-05T08:06:04.000000Z",
                "created_at": "2026-07-05T08:06:02.000000Z",
                "updated_at": "2026-07-05T08:06:04.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100002:b59e16d72ea14ec5bd7761b2d262b1eb"
            },
            {
                "id": 1477,
                "template_code": "100002",
                "to_email": "2908990438@qq.com",
                "subject": "创欧云 登录提醒",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>创欧云 登录提醒</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }\n    .mail-...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-05T08:04:42.000000Z",
                "created_at": "2026-07-05T08:04:39.000000Z",
                "updated_at": "2026-07-05T08:04:42.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100002:21fab1002ce4429ab0308677adf8a733"
            },
            {
                "id": 1476,
                "template_code": "100002",
                "to_email": "2908990438@qq.com",
                "subject": "创欧云 登录提醒",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>创欧云 登录提醒</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }\n    .mail-...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-05T03:53:33.000000Z",
                "created_at": "2026-07-05T03:53:28.000000Z",
                "updated_at": "2026-07-05T03:53:33.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100002:586381e8cc6342ea812145c85f1d1937"
            },
            {
                "id": 1475,
                "template_code": "100003",
                "to_email": "1694779234@qq.com",
                "subject": "【创欧云】服务续费提醒（3 天后到期）",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>【创欧云】服务续费提醒（3 天后到期）</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-04T16:02:04.000000Z",
                "created_at": "2026-07-04T16:02:00.000000Z",
                "updated_at": "2026-07-04T16:02:04.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100003:f603f3d601c04326b3767e2eee32b057"
            },
            {
                "id": 1474,
                "template_code": "100003",
                "to_email": "placeholder-419@dev.local",
                "subject": "【创欧云】服务续费提醒（1 天后到期）",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>【创欧云】服务续费提醒（1 天后到期）</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-04T16:02:00.000000Z",
                "created_at": "2026-07-04T16:01:57.000000Z",
                "updated_at": "2026-07-04T16:02:00.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100003:23d92dd077314e289eeff9d2378e34db"
            },
            {
                "id": 1473,
                "template_code": "100003",
                "to_email": "640153870@qq.com",
                "subject": "【创欧云】服务续费提醒（1 天后到期）",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>【创欧云】服务续费提醒（1 天后到期）</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-04T16:01:57.000000Z",
                "created_at": "2026-07-04T16:01:56.000000Z",
                "updated_at": "2026-07-04T16:01:57.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100003:4986e1d53d1946239a035f785a09466b"
            },
            {
                "id": 1472,
                "template_code": "100003",
                "to_email": "3010087667@qq.com",
                "subject": "【创欧云】服务续费提醒（1 天后到期）",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>【创欧云】服务续费提醒（1 天后到期）</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-04T16:01:56.000000Z",
                "created_at": "2026-07-04T16:01:54.000000Z",
                "updated_at": "2026-07-04T16:01:56.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100003:0de6b4b27b9843749e043011e0d97f38"
            },
            {
                "id": 1471,
                "template_code": "100003",
                "to_email": "3010087667@qq.com",
                "subject": "【创欧云】服务续费提醒（1 天后到期）",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>【创欧云】服务续费提醒（1 天后到期）</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-04T16:01:54.000000Z",
                "created_at": "2026-07-04T16:01:52.000000Z",
                "updated_at": "2026-07-04T16:01:54.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100003:680f04268cf44533ad910bb0247c8e59"
            },
            {
                "id": 1470,
                "template_code": "100003",
                "to_email": "3010087667@qq.com",
                "subject": "【创欧云】服务续费提醒（1 天后到期）",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>【创欧云】服务续费提醒（1 天后到期）</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-04T16:01:52.000000Z",
                "created_at": "2026-07-04T16:01:49.000000Z",
                "updated_at": "2026-07-04T16:01:52.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100003:64e8c921605d49f1b84c4bed6966ed33"
            },
            {
                "id": 1469,
                "template_code": "100003",
                "to_email": "placeholder-385@dev.local",
                "subject": "【创欧云】服务续费提醒（1 天后到期）",
                "content": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>【创欧云】服务续费提醒（1 天后到期）</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }...（已截断）",
                "status": "success",
                "error_msg": null,
                "sent_at": "2026-07-04T16:01:48.000000Z",
                "created_at": "2026-07-04T16:01:46.000000Z",
                "updated_at": "2026-07-04T16:01:48.000000Z",
                "plugin_id": 7,
                "driver_key": "multi_smtp_round_robin",
                "trace_id": "email:100003:4b86029b3e50431dbb50ab496fc6dd59"
            }
        ],
        "first_page_url": "http://127.0.0.1:8000/api/admin/logs/email?page=1",
        "from": 1,
        "last_page": 93,
        "last_page_url": "http://127.0.0.1:8000/api/admin/logs/email?page=93",
        "links": [
            {
                "url": null,
                "label": "pagination.previous",
                "page": null,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=1",
                "label": "1",
                "page": 1,
                "active": true
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=2",
                "label": "2",
                "page": 2,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=3",
                "label": "3",
                "page": 3,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=4",
                "label": "4",
                "page": 4,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=5",
                "label": "5",
                "page": 5,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=6",
                "label": "6",
                "page": 6,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=7",
                "label": "7",
                "page": 7,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=8",
                "label": "8",
                "page": 8,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=9",
                "label": "9",
                "page": 9,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=10",
                "label": "10",
                "page": 10,
                "active": false
            },
            {
                "url": null,
                "label": "...",
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=92",
                "label": "92",
                "page": 92,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=93",
                "label": "93",
                "page": 93,
                "active": false
            },
            {
                "url": "http://127.0.0.1:8000/api/admin/logs/email?page=2",
                "label": "pagination.next",
                "page": 2,
                "active": false
            }
        ],
        "next_page_url": "http://127.0.0.1:8000/api/admin/logs/email?page=2",
        "path": "http://127.0.0.1:8000/api/admin/logs/email",
        "per_page": 15,
        "prev_page_url": null,
        "to": 15,
        "total": 1382,
        "summary": []
    },
    "timestamp": 1783240498
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:58  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/email  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@emailLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
