# runtime

**请求方法**：GET  
**请求路径**：`/api/admin/logs/runtime`  
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
| page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：GeneralLogListRequest |
| per_page | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1\|max:100；来源：GeneralLogListRequest |
| keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| actor_keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| description_keyword | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| ip_address | string | 否 | 查询参数；校验规则：nullable\|string\|max:45；来源：GeneralLogListRequest |
| level | string | 否 | 查询参数；校验规则：nullable\|in:DEBUG,INFO,NOTICE,WARNING,ERROR,CRITICAL,ALERT,EMERGENCY；来源：GeneralLogListRequest |
| module | string | 否 | 查询参数；校验规则：nullable\|string\|max:60；来源：GeneralLogListRequest |
| method | string | 否 | 查询参数；校验规则：nullable\|in:GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD；来源：GeneralLogListRequest |
| status | string | 否 | 查询参数；校验规则：nullable\|string\|max:20；来源：GeneralLogListRequest |
| task_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:60；来源：GeneralLogListRequest |
| user_type | string | 否 | 查询参数；校验规则：nullable\|in:admin,client,guest；来源：GeneralLogListRequest |
| gateway | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：GeneralLogListRequest |
| gateway_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| driver_key | string | 否 | 查询参数；校验规则：nullable\|string\|max:120；来源：GeneralLogListRequest |
| plugin_id | integer | 否 | 查询参数；校验规则：nullable\|integer\|min:1；来源：GeneralLogListRequest |
| trace_id | string | 否 | 查询参数；校验规则：nullable\|string\|max:64；来源：GeneralLogListRequest |
| action | string | 否 | 查询参数；校验规则：nullable\|string\|max:100；来源：GeneralLogListRequest |
| result_status | string | 否 | 查询参数；校验规则：nullable\|in:success,failed,pending,unknown；来源：GeneralLogListRequest |
| actor_type | string | 否 | 查询参数；校验规则：nullable\|in:admin,client,system,sub_account；来源：GeneralLogListRequest |
| subject_type | string | 否 | 查询参数；校验规则：nullable\|string\|max:50；来源：GeneralLogListRequest |
| start_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date；来源：GeneralLogListRequest |
| end_date | string(datetime) | 否 | 查询参数；校验规则：nullable\|date；来源：GeneralLogListRequest |

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
| data.data.id | string | 真实调用返回字段 |
| data.data.time | string | 真实调用返回字段 |
| data.data.level | string | 真实调用返回字段 |
| data.data.message | string | 真实调用返回字段 |
| data.data.raw | string | 真实调用返回字段 |
| data.data.task_key | null | 真实调用返回字段 |
| data.data.task_title | string | 真实调用返回字段 |
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
                "id": "21db9639ad6dd76f30658cfbf6701d66",
                "time": "2026-07-05 16:34:46",
                "level": "ERROR",
                "message": "Undefined variable $privacy",
                "raw": "Undefined variable $privacy {\"userId\":1,\"exception\":\"[object] (ErrorException(code: 0): Undefined variable $privacy at C:/Users/Admin/Desktop/caiwu/backend/app/Services/System/DashboardService.php:140)",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "31fb5ba9e3bad57bf3632911565dcd7d",
                "time": "2026-07-05 16:34:46",
                "level": "ERROR",
                "message": "Undefined variable $privacy",
                "raw": "Undefined variable $privacy {\"userId\":1,\"exception\":\"[object] (ErrorException(code: 0): Undefined variable $privacy at C:/Users/Admin/Desktop/caiwu/backend/app/Services/System/DashboardService.php:140)",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "plugin-runtime-3635",
                "source": "integration_plugin_runtime_logs",
                "time": "2026-07-05 16:34:46",
                "level": "INFO",
                "message": "multi_smtp_round_robin mail.send_html",
                "raw": "multi_smtp_round_robin mail.send_html",
                "status": "success",
                "trace_id": "plugin:41b841459d534057a7e21908d31bf317",
                "domain": "mail",
                "plugin_id": 7,
                "plugin_key": "multi_smtp_round_robin",
                "slug": "multi_smtp_round_robin",
                "action": "mail.send_html",
                "duration_ms": 1684,
                "error_msg": "",
                "request_meta": {
                    "context": [],
                    "payload": {
                        "to": "2908990438@qq.com",
                        "html": "<!doctype html>\n<html lang=\"zh-CN\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>创欧云 登录提醒</title>\n  <style>\n    body {\n      margin: 0;\n      padding: 0;\n      background: #f3f4f6;\n      font-family: \"PingFang SC\", \"Microsoft YaHei\", Arial, sans-serif;\n      color: #1f2329;\n    }\n    .mail-shell {\n      width: 100%;\n      padding: 32px 12px;\n      box-sizing: border-box;\n      background: #f3f4f6;\n    }\n    .mail-card {\n      width: 100%;\n      max-width: 680px;\n      margin: 0 auto;\n      background: #ffffff;\n      border: 1px solid #cfd6e4;\n      overflow: hidden;\n    }\n    .mail-header {\n      display: flex;\n      align-items: center;\n      padding: 24px 28px 20px;\n      border-top: 4px solid #1f4b99;\n      border-bottom: 1px solid #d9e0ec;\n      background: #f8fafc;\n    }\n    .mail-branding {\n      display: flex;\n      align-items: center;\n      gap: 16px;\n      min-width: 0;\n    }\n    .mail-logo {\n      display: block;\n      flex: 0 0 auto;\n      width: auto;\n      height: 44px;\n      max-width: 63px;\n    }\n    .mail-brand {\n      min-width: 0;\n    }\n    .mail-brand strong {\n      display: block;\n      font-size: 18px;\n      line-height: 1.3;\n      letter-spacing: 0.02em;\n      color: #162033;\n    }\n    .mail-brand span {\n      display: block;\n      margin-top: 6px;\n      font-size: 12px;\n      color: #5b6575;\n    }\n    .mail-body {\n      padding: 28px;\n    }\n    .mail-title {\n      margin: 0;\n      font-size: 28px;\n      line-height: 1.4;\n      color: #162033;\n    }\n    .mail-summary {\n      margin: 12px 0 0;\n      font-size: 14px;\n      line-height: 1.8;\n      color: #4b5565;\n    }\n    .mail-divider {\n      height: 1px;\n      margin: 24px 0;\n      background: #d9e0ec;\n    }\n    .mail-content {\n      font-size: 14px;\n      line-height: 1.85;\n      color: #1f2329;\n    }\n    .mail-content p {\n      margin: 0 0 14px;\n    }\n    .mail-content p:last-child {\n      margin-bottom: 0;\n    }\n    .mail-...（已截断）",
                        "context": {
                            "template_code": "100002"
                        },
                        "subject": "创欧云 登录提醒"
                    }
                },
                "response_meta": {
                    "raw": [],
                    "data": {
                        "sent": true
                    },
                    "action": "mail.send_html",
                    "plugin": {
                        "key": "multi_smtp_round_robin",
                        "name": "多 SMTP 轮询",
                        "slug": "multi_smtp_round_robin",
                        "domain": "mail"
                    },
                    "message": "",
                    "success": true
                }
            },
            {
                "id": "plugin-runtime-3634",
                "source": "integration_plugin_runtime_logs",
                "time": "2026-07-05 16:34:43",
                "level": "INFO",
                "message": "vaptcha captcha.config",
                "raw": "vaptcha captcha.config",
                "status": "success",
                "trace_id": "plugin:6c9357cd372144bca83de37b4b803ee7",
                "domain": "captcha",
                "plugin_id": 14,
                "plugin_key": "vaptcha",
                "slug": "vaptcha",
                "action": "captcha.config",
                "duration_ms": 7,
                "error_msg": "",
                "request_meta": {
                    "context": [],
                    "payload": []
                },
                "response_meta": {
                    "raw": [],
                    "data": {
                        "vid": "id_d15ffed6c9d5697",
                        "enabled": true,
                        "provider": "vaptcha",
                        "captcha_id": "[REDACTED]"
                    },
                    "action": "captcha.config",
                    "plugin": {
                        "key": "vaptcha",
                        "name": "VAPTCHA 智能人机验证",
                        "slug": "vaptcha",
                        "domain": "captcha"
                    },
                    "message": "",
                    "success": true
                }
            },
            {
                "id": "a76154f50b0e9d334ec086cc376d8974",
                "time": "2026-07-05 16:34:42",
                "level": "ERROR",
                "message": "用户名或密码错误",
                "raw": "用户名或密码错误 {\"exception\":\"[object] (App\\Exceptions\\BusinessException(code: 401): 用户名或密码错误 at C:/Users/Admin/Desktop/caiwu/backend/app/Services/Auth/AuthService.php:599)",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "1231507e9d9ef29d215c039f549dd11f",
                "time": "2026-07-05 16:34:05",
                "level": "ERROR",
                "message": "无效的计费周期",
                "raw": "无效的计费周期 {\"exception\":\"[object] (App\\Exceptions\\BusinessException(code: 422): 无效的计费周期 at C:/Users/Admin/Desktop/caiwu/backend/app/Services/Order/Concerns/HandlesOrderCalculation.php:67)",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "plugin-runtime-3633",
                "source": "integration_plugin_runtime_logs",
                "time": "2026-07-05 16:34:05",
                "level": "INFO",
                "message": "mofang_finance_api server.resolve_capability",
                "raw": "mofang_finance_api server.resolve_capability",
                "status": "success",
                "trace_id": "plugin:072e94adde43446d99b5eb9dd4163f8c",
                "domain": "upstream",
                "plugin_id": 8,
                "plugin_key": "mofang_finance_api",
                "slug": "mofang_finance",
                "action": "server.resolve_capability",
                "duration_ms": 2,
                "error_msg": "",
                "request_meta": {
                    "context": [],
                    "payload": {
                        "capability": "App\Services\Upstream\Contracts\ProvidesConsoleCatalog"
                    }
                },
                "response_meta": {
                    "raw": [],
                    "data": {
                        "resolved": "[OBJECT]"
                    },
                    "action": "server.resolve_capability",
                    "plugin": {
                        "key": "mofang_finance_api",
                        "name": "魔方财务接口",
                        "slug": "mofang_finance",
                        "domain": "upstream"
                    },
                    "message": "",
                    "success": true
                }
            },
            {
                "id": "plugin-runtime-3632",
                "source": "integration_plugin_runtime_logs",
                "time": "2026-07-05 16:34:05",
                "level": "INFO",
                "message": "mofang_finance_api server.supplier_form_schema",
                "raw": "mofang_finance_api server.supplier_form_schema",
                "status": "success",
                "trace_id": "plugin:b9ba5ec211224ae2bc6df3c8078a5fe1",
                "domain": "upstream",
                "plugin_id": 8,
                "plugin_key": "mofang_finance_api",
                "slug": "mofang_finance",
                "action": "server.supplier_form_schema",
                "duration_ms": 5,
                "error_msg": "",
                "request_meta": {
                    "context": [],
                    "payload": []
                },
                "response_meta": {
                    "raw": [],
                    "data": {
                        "help": "魔方财务插件使用供应商后台地址、账号和密码/API 密钥登录并刷新 JWT。",
                        "fields": [
                            {
                                "key": "api_url",
                                "type": "url",
                                "label": "魔方财务地址",
                                "required": true,
                                "placeholder": "https://finance.example.com"
                            },
                            {
                                "key": "api_username",
                                "type": "text",
                                "label": "登录账号",
                                "required": true
                            },
                            {
                                "key": "api_key",
                                "type": "password",
                                "label": "登录密码/API 密钥",
                                "secret": "***已脱敏***",
                                "required": true,
                                "placeholder": "编辑时留空则保持原密钥"
                            }
                        ]
                    },
                    "action": "server.supplier_form_schema",
                    "plugin": {
                        "key": "mofang_finance_api",
                        "name": "魔方财务接口",
                        "slug": "mofang_finance",
                        "domain": "upstream"
                    },
                    "message": "",
                    "success": true
                }
            },
            {
                "id": "ad68bae759ef4a702b9429c31a67e18a",
                "time": "2026-07-05 16:34:04",
                "level": "ERROR",
                "message": "内容不存在或未发布",
                "raw": "内容不存在或未发布 {\"exception\":\"[object] (App\\Exceptions\\BusinessException(code: 422): 内容不存在或未发布 at C:/Users/Admin/Desktop/caiwu/backend/app/Services/Content/ContentArticleService.php:182)",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "83789b1e6f17aed7cd1820d841b4e3bf",
                "time": "2026-07-05 16:34:02",
                "level": "ERROR",
                "message": "认证二维码已失效，请重新生成",
                "raw": "认证二维码已失效，请重新生成 {\"exception\":\"[object] (App\\Exceptions\\BusinessException(code: 422): 认证二维码已失效，请重新生成 at C:/Users/Admin/Desktop/caiwu/backend/app/Services/Auth/VerificationService.php:148)",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "107814583602fb20dd26279240bccc92",
                "time": "2026-07-05 16:34:02",
                "level": "ERROR",
                "message": "预览产品升降级失败，请稍后重试",
                "raw": "预览产品升降级失败，请稍后重试 {\"userId\":1,\"exception\":\"[object] (App\\Exceptions\\BusinessException(code: 422): 预览产品升降级失败，请稍后重试 at C:/Users/Admin/Desktop/caiwu/backend/app/Services/ClientServiceConsole/ServiceDetailService.php:711)",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "9c5b3cfd1498953b60ff82a31a805802",
                "time": "2026-07-05 16:34:02",
                "level": "WARNING",
                "message": "[服务控制台] 上游返回失败",
                "raw": "[服务控制台] 上游返回失败 {\"action\":\"预览产品升降级\",\"status\":400,\"message\":\"当前产品无法升级或降级可配置项\"}",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "d7c041fc343e301c39058b5a70d098ba",
                "time": "2026-07-05 16:34:02",
                "level": "INFO",
                "message": "[主机面板接口] 接口响应",
                "raw": "[主机面板接口] 接口响应 {\"supplier_id\":1,\"method\":\"POST\",\"url\":\"https://cl***cn/***\",\"http_code\":200,\"duration_ms\":287,\"response\":{\"status\":400,\"msg\":\"当前产品无法升级或降级可配置项\"}}",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "plugin-runtime-3631",
                "source": "integration_plugin_runtime_logs",
                "time": "2026-07-05 16:34:02",
                "level": "INFO",
                "message": "baidu_face certification.fee_config",
                "raw": "baidu_face certification.fee_config",
                "status": "success",
                "trace_id": "plugin:c9b22dffadba453d81a18a7f30485c81",
                "domain": "verification",
                "plugin_id": 15,
                "plugin_key": "baidu_face",
                "slug": "baidu_face",
                "action": "certification.fee_config",
                "duration_ms": 5,
                "error_msg": "",
                "request_meta": {
                    "context": [],
                    "payload": []
                },
                "response_meta": {
                    "raw": [],
                    "data": {
                        "amount": 0,
                        "retry_fee": 0,
                        "free_times": 0,
                        "free_attempts": 0,
                        "charge_enabled": false
                    },
                    "action": "certification.fee_config",
                    "plugin": {
                        "key": "baidu_face",
                        "name": "百度智能云人脸实名认证",
                        "slug": "baidu_face",
                        "domain": "verification"
                    },
                    "message": "",
                    "success": true
                }
            },
            {
                "id": "5833021f5e31e2fd44326ec43c121be7",
                "time": "2026-07-05 16:34:01",
                "level": "ERROR",
                "message": "读取产品升降级选项失败，请稍后重试",
                "raw": "读取产品升降级选项失败，请稍后重试 {\"userId\":1,\"exception\":\"[object] (App\\Exceptions\\BusinessException(code: 422): 读取产品升降级选项失败，请稍后重试 at C:/Users/Admin/Desktop/caiwu/backend/app/Services/ClientServiceConsole/ServiceDetailService.php:711)",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "9dd4bd203d538664296d090c1661d21d",
                "time": "2026-07-05 16:34:01",
                "level": "WARNING",
                "message": "[服务控制台] 上游返回失败",
                "raw": "[服务控制台] 上游返回失败 {\"action\":\"读取产品升降级选项\",\"status\":400,\"message\":\"当前产品无法升级或降级可配置项\"}",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "7dd7645babc44926d5ce0bf1e2abb6f7",
                "time": "2026-07-05 16:34:01",
                "level": "INFO",
                "message": "[主机面板接口] 接口响应",
                "raw": "[主机面板接口] 接口响应 {\"supplier_id\":1,\"method\":\"GET\",\"url\":\"https://cl***cn/***\",\"http_code\":200,\"duration_ms\":277,\"response\":{\"status\":400,\"msg\":\"当前产品无法升级或降级可配置项\"}}",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "060528cc43d1d8a1910f6146a03d8933",
                "time": "2026-07-05 16:34:01",
                "level": "ERROR",
                "message": "当前商品分类未配置可售流量包",
                "raw": "当前商品分类未配置可售流量包 {\"userId\":1,\"exception\":\"[object] (App\\Exceptions\\BusinessException(code: 422): 当前商品分类未配置可售流量包 at C:/Users/Admin/Desktop/caiwu/backend/app/Services/ClientServiceConsole/ServiceTrafficPackageService.php:605)",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "35e587d5ee8ac03f3def9309040047e0",
                "time": "2026-07-05 16:34:01",
                "level": "INFO",
                "message": "[主机面板接口] 文本接口响应",
                "raw": "[主机面板接口] 文本接口响应 {\"supplier_id\":1,\"method\":\"GET\",\"url\":\"https://cl***cn/***?***\",\"http_code\":200,\"duration_ms\":561,\"response_preview\":\"\"}",
                "task_key": null,
                "task_title": ""
            },
            {
                "id": "plugin-runtime-3630",
                "source": "integration_plugin_runtime_logs",
                "time": "2026-07-05 16:34:01",
                "level": "INFO",
                "message": "mofang_finance_api server.resolve_capability",
                "raw": "mofang_finance_api server.resolve_capability",
                "status": "success",
                "trace_id": "plugin:41a52e86a3ca4f7e956f50fc390e1b0b",
                "domain": "upstream",
                "plugin_id": 8,
                "plugin_key": "mofang_finance_api",
                "slug": "mofang_finance",
                "action": "server.resolve_capability",
                "duration_ms": 2,
                "error_msg": "",
                "request_meta": {
                    "context": [],
                    "payload": {
                        "capability": "App\Services\Upstream\Contracts\ProvidesConsoleRuntime"
                    }
                },
                "response_meta": {
                    "raw": [],
                    "data": {
                        "resolved": "[OBJECT]"
                    },
                    "action": "server.resolve_capability",
                    "plugin": {
                        "key": "mofang_finance_api",
                        "name": "魔方财务接口",
                        "slug": "mofang_finance",
                        "domain": "upstream"
                    },
                    "message": "",
                    "success": true
                }
            }
        ],
        "first_page_url": "/?page=1",
        "from": 1,
        "last_page": 84,
        "last_page_url": "/?page=84",
        "links": [
            {
                "url": null,
                "label": "pagination.previous",
                "page": null,
                "active": false
            },
            {
                "url": "/?page=1",
                "label": "1",
                "page": 1,
                "active": true
            },
            {
                "url": "/?page=2",
                "label": "2",
                "page": 2,
                "active": false
            },
            {
                "url": "/?page=3",
                "label": "3",
                "page": 3,
                "active": false
            },
            {
                "url": "/?page=4",
                "label": "4",
                "page": 4,
                "active": false
            },
            {
                "url": "/?page=5",
                "label": "5",
                "page": 5,
                "active": false
            },
            {
                "url": "/?page=6",
                "label": "6",
                "page": 6,
                "active": false
            },
            {
                "url": "/?page=7",
                "label": "7",
                "page": 7,
                "active": false
            },
            {
                "url": "/?page=8",
                "label": "8",
                "page": 8,
                "active": false
            },
            {
                "url": "/?page=9",
                "label": "9",
                "page": 9,
                "active": false
            },
            {
                "url": "/?page=10",
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
                "url": "/?page=83",
                "label": "83",
                "page": 83,
                "active": false
            },
            {
                "url": "/?page=84",
                "label": "84",
                "page": 84,
                "active": false
            },
            {
                "url": "/?page=2",
                "label": "pagination.next",
                "page": 2,
                "active": false
            }
        ],
        "next_page_url": "/?page=2",
        "path": "/",
        "per_page": 20,
        "prev_page_url": null,
        "to": 20,
        "total": 1668,
        "summary": []
    },
    "timestamp": 1783240503
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:03  
· 响应状态码：200  
· 调用方式：GET /api/admin/logs/runtime  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\LogController@runtimeLogs`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:log.list`
