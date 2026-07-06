# integration-plugins

**请求方法**：GET  
**请求路径**：`/api/admin/integration-plugins`  
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
| domain | string | 否 | 查询参数；校验规则：nullable\|string\|in:"payment","verification","captcha","mail","sms","upstream"；来源：IndexIntegrationPluginRequest |

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
| data.list | array | 分页列表数据 |
| data.list.id | integer | 真实调用返回字段 |
| data.list.domain | string | 真实调用返回字段 |
| data.list.slug | string | 真实调用返回字段 |
| data.list.key | string | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.version | string | 真实调用返回字段 |
| data.list.entry_class | string | 真实调用返回字段 |
| data.list.provider_class | null | 真实调用返回字段 |
| data.list.capabilities | array | 真实调用返回字段 |
| data.list.config_schema | array | 真实调用返回字段 |
| data.list.config_schema.key | string | 真实调用返回字段 |
| data.list.config_schema.label | string | 真实调用返回字段 |
| data.list.config_schema.type | string | 真实调用返回字段 |
| data.list.config_schema.required | boolean | 真实调用返回字段 |
| data.list.config_schema.secret | string | 真实调用返回字段 |
| data.list.config_schema.options | null | 真实调用返回字段 |
| data.list.config_schema.default | null | 真实调用返回字段 |
| data.list.config_schema.content | string | 真实调用返回字段 |
| data.list.config_schema.theme | string | 真实调用返回字段 |
| data.list.base_path | string | 真实调用返回字段 |
| data.list.is_installed | boolean | 真实调用返回字段 |
| data.list.is_enabled | boolean | 真实调用返回字段 |
| data.list.can_enable | boolean | 真实调用返回字段 |
| data.list.enable_disabled_reason | string | 真实调用返回字段 |
| data.list.status | integer | 真实调用返回字段 |
| data.list.installed_at | string | 真实调用返回字段 |
| data.list.updated_at | string | 真实调用返回字段 |
| data.list.binding_counts | object | 真实调用返回字段 |
| data.list.binding_counts.integration_plugin_bindings | integer | 真实调用返回字段 |
| data.list.binding_counts.integration_plugin_runtime_logs | integer | 真实调用返回字段 |
| data.list.business_reference_count | integer | 真实调用返回字段 |
| data.list.latest_runtime_log | object | 真实调用返回字段 |
| data.list.latest_runtime_log.id | integer | 真实调用返回字段 |
| data.list.latest_runtime_log.trace_id | string | 真实调用返回字段 |
| data.list.latest_runtime_log.action | string | 真实调用返回字段 |
| data.list.latest_runtime_log.status | string | 真实调用返回字段 |
| data.list.latest_runtime_log.error_message | string | 真实调用返回字段 |
| data.list.latest_runtime_log.created_at | string | 真实调用返回字段 |
| data.list.manifest_missing | boolean | 真实调用返回字段 |
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
                "id": 9,
                "domain": "captcha",
                "slug": "geetest",
                "key": "geetest",
                "name": "GeeTest 行为验证",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Captcha\Geetest\GeetestPlugin",
                "provider_class": null,
                "capabilities": [
                    "config",
                    "verify",
                    "script"
                ],
                "config_schema": [
                    {
                        "key": "basic_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "请填写 GeeTest 控制台分配的 Captcha ID 和 Captcha Key。密钥保存后不会明文回显。",
                        "theme": "info"
                    },
                    {
                        "key": "captcha_id",
                        "label": "Captcha ID",
                        "type": "text",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入 Captcha ID",
                        "description": "来自 GeeTest 控制台的 captcha_id。"
                    },
                    {
                        "key": "captcha_key",
                        "label": "Captcha Key",
                        "type": "password",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入 Captcha Key",
                        "description": "来自 GeeTest 控制台的 captcha_key。"
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\captcha\geetest",
                "is_installed": true,
                "is_enabled": false,
                "can_enable": false,
                "enable_disabled_reason": "当前功能域已启用「VAPTCHA 智能人机验证」，请先停用后再启用其他插件",
                "status": 0,
                "installed_at": "2026-07-02 22:34:59",
                "updated_at": "2026-07-04 22:46:12",
                "binding_counts": {
                    "integration_plugin_bindings": 1,
                    "integration_plugin_runtime_logs": 22
                },
                "business_reference_count": 23,
                "latest_runtime_log": {
                    "id": 486,
                    "trace_id": "plugin:d73aaa205f5a4009bd087a4830262103",
                    "action": "captcha.script",
                    "status": "success",
                    "error_message": "",
                    "created_at": "2026-07-04 21:45:59"
                },
                "manifest_missing": false
            },
            {
                "id": 14,
                "domain": "captcha",
                "slug": "vaptcha",
                "key": "vaptcha",
                "name": "VAPTCHA 智能人机验证",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Captcha\Vaptcha\VaptchaPlugin",
                "provider_class": null,
                "capabilities": [
                    "config",
                    "verify",
                    "script"
                ],
                "config_schema": [
                    {
                        "key": "basic_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "请在 VAPTCHA 控制台创建验证单元并填写 VID 与 VKEY。VID 可下发前端，VKEY 只保存在服务端，保存后不会明文回显。",
                        "theme": "info"
                    },
                    {
                        "key": "vid",
                        "label": "VID",
                        "type": "text",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入 VAPTCHA VID",
                        "description": "来自 VAPTCHA 控制台的验证单元 VID，用于前端初始化。"
                    },
                    {
                        "key": "vkey",
                        "label": "VKEY",
                        "type": "password",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入 VAPTCHA VKEY",
                        "description": "来自 VAPTCHA 控制台的服务端密钥，仅用于后端二次验证。"
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\captcha\vaptcha",
                "is_installed": true,
                "is_enabled": true,
                "can_enable": false,
                "enable_disabled_reason": null,
                "status": 1,
                "installed_at": "2026-07-04 22:45:46",
                "updated_at": "2026-07-04 22:57:47",
                "binding_counts": {
                    "integration_plugin_bindings": 1,
                    "integration_plugin_runtime_logs": 28
                },
                "business_reference_count": 29,
                "latest_runtime_log": {
                    "id": 3634,
                    "trace_id": "plugin:6c9357cd372144bca83de37b4b803ee7",
                    "action": "captcha.config",
                    "status": "success",
                    "error_message": "",
                    "created_at": "2026-07-05 16:34:43"
                },
                "manifest_missing": false
            },
            {
                "id": null,
                "domain": "mail",
                "slug": "smtp",
                "key": "smtp",
                "name": "Single SMTP",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Mail\Smtp\SmtpPlugin",
                "provider_class": null,
                "capabilities": [
                    "smtp",
                    "html"
                ],
                "config_schema": [
                    {
                        "key": "smtp_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "单 SMTP 插件使用一组 SMTP 账号发送系统邮件，密钥保存后不会明文回显。",
                        "theme": "info"
                    },
                    {
                        "key": "host",
                        "label": "SMTP 主机",
                        "type": "text",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "smtp.example.com"
                    },
                    {
                        "key": "port",
                        "label": "SMTP 端口",
                        "type": "number",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": 465,
                        "min": 1,
                        "step": 1
                    },
                    {
                        "key": "username",
                        "label": "SMTP 账号",
                        "type": "text",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "no-reply@example.com"
                    },
                    {
                        "key": "password",
                        "label": "SMTP 密码",
                        "type": "password",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入 SMTP 密码"
                    },
                    {
                        "key": "from_name",
                        "label": "发件名称",
                        "type": "text",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "Caiwu"
                    },
                    {
                        "key": "encryption",
                        "label": "加密方式",
                        "type": "select",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": [
                            {
                                "label": "自动",
                                "value": ""
                            },
                            {
                                "label": "SSL",
                                "value": "ssl"
                            },
                            {
                                "label": "TLS",
                                "value": "tls"
                            },
                            {
                                "label": "无",
                                "value": "none"
                            }
                        ],
                        "default": ""
                    },
                    {
                        "key": "timeout_seconds",
                        "label": "超时秒数",
                        "type": "number",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": 8,
                        "min": 1,
                        "step": 1
                    },
                    {
                        "key": "rate_limit_divider",
                        "label": "验证码限流",
                        "type": "divider",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null
                    },
                    {
                        "key": "rate_limit_enabled",
                        "label": "启用邮箱验证码限流",
                        "type": "switch",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": true,
                        "description": "限制使用此插件发送邮箱验证码的单 IP 频率。"
                    },
                    {
                        "key": "ip_minute_limit",
                        "label": "单 IP 每分钟上限",
                        "type": "number",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": 6,
                        "description": "设为 0 表示不限制。",
                        "min": 0,
                        "step": 1
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\mail\smtp",
                "is_installed": false,
                "is_enabled": false,
                "can_enable": false,
                "enable_disabled_reason": null,
                "status": 0,
                "installed_at": null,
                "updated_at": null,
                "binding_counts": [],
                "business_reference_count": 0,
                "latest_runtime_log": null,
                "manifest_missing": false
            },
            {
                "id": 7,
                "domain": "mail",
                "slug": "multi_smtp_round_robin",
                "key": "multi_smtp_round_robin",
                "name": "多 SMTP 轮询",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Mail\MultiSmtpRoundRobin\MultiSmtpRoundRobinPlugin",
                "provider_class": null,
                "capabilities": [
                    "smtp",
                    "round_robin",
                    "cooldown"
                ],
                "config_schema": [
                    {
                        "key": "accounts_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "支持配置多个 SMTP 账号，发送失败后自动轮询到下一个可用账号。",
                        "theme": "info"
                    },
                    {
                        "key": "accounts",
                        "label": "SMTP 账号列表",
                        "type": "json",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": [],
                        "description": "请通过账号管理器维护 SMTP 主机、端口、账号和密码。"
                    },
                    {
                        "key": "cooldown_seconds",
                        "label": "失败冷却秒数",
                        "type": "number",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": 60,
                        "description": "账号发送失败后进入冷却的秒数。",
                        "min": 1,
                        "step": 1
                    },
                    {
                        "key": "rate_limit_divider",
                        "label": "验证码限流",
                        "type": "divider",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null
                    },
                    {
                        "key": "rate_limit_enabled",
                        "label": "启用邮箱验证码限流",
                        "type": "switch",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": true,
                        "description": "限制使用此插件发送邮箱验证码的单 IP 频率。"
                    },
                    {
                        "key": "ip_minute_limit",
                        "label": "单 IP 每分钟上限",
                        "type": "number",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": 6,
                        "description": "设为 0 表示不限制。",
                        "min": 0,
                        "step": 1
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\mail\multi_smtp_round_robin",
                "is_installed": true,
                "is_enabled": true,
                "can_enable": false,
                "enable_disabled_reason": null,
                "status": 1,
                "installed_at": "2026-07-01 17:41:59",
                "updated_at": "2026-07-01 17:41:59",
                "binding_counts": {
                    "integration_plugin_bindings": 2,
                    "integration_plugin_runtime_logs": 53,
                    "notification_logs": 1380,
                    "email_logs": 241
                },
                "business_reference_count": 1676,
                "latest_runtime_log": {
                    "id": 3635,
                    "trace_id": "plugin:41b841459d534057a7e21908d31bf317",
                    "action": "mail.send_html",
                    "status": "success",
                    "error_message": "",
                    "created_at": "2026-07-05 16:34:46"
                },
                "manifest_missing": false
            },
            {
                "id": 2,
                "domain": "payment",
                "slug": "ali_pay",
                "key": "alipay",
                "name": "支付宝当面付",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Gateways\AliPay\AliPayPlugin",
                "provider_class": null,
                "capabilities": [
                    "precreate",
                    "query",
                    "refund",
                    "notify_verify"
                ],
                "config_schema": [
                    {
                        "key": "alipay_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "请填写支付宝开放平台应用参数。私钥和公钥保存后不会明文回显。",
                        "theme": "warning"
                    },
                    {
                        "key": "alipay_enabled",
                        "label": "启用",
                        "type": "switch",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": true,
                        "description": "关闭后该支付渠道不会作为可用支付方式。"
                    },
                    {
                        "key": "app_id",
                        "label": "App ID",
                        "type": "text",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入支付宝应用 App ID"
                    },
                    {
                        "key": "key_divider",
                        "label": "密钥配置",
                        "type": "divider",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null
                    },
                    {
                        "key": "private_key",
                        "label": "应用私钥",
                        "type": "textarea",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入应用私钥 PEM 内容",
                        "description": "请填写应用私钥，不要填写支付宝公钥。",
                        "rows": 6
                    },
                    {
                        "key": "alipay_public_key",
                        "label": "支付宝公钥",
                        "type": "textarea",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入支付宝公钥 PEM 内容",
                        "rows": 6
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\gateways\ali_pay",
                "is_installed": true,
                "is_enabled": true,
                "can_enable": false,
                "enable_disabled_reason": null,
                "status": 1,
                "installed_at": "2026-07-01 17:41:59",
                "updated_at": "2026-07-05 00:49:27",
                "binding_counts": {
                    "integration_plugin_bindings": 1,
                    "integration_plugin_runtime_logs": 225,
                    "payments": 208,
                    "payment_callbacks": 207,
                    "gateway_logs": 44
                },
                "business_reference_count": 685,
                "latest_runtime_log": {
                    "id": 3605,
                    "trace_id": "plugin:538a87ed4021471498ec3925fa55a999",
                    "action": "payment.options",
                    "status": "success",
                    "error_message": "",
                    "created_at": "2026-07-05 16:33:52"
                },
                "manifest_missing": false
            },
            {
                "id": 11,
                "domain": "payment",
                "slug": "yi_pay",
                "key": "yipay",
                "name": "易支付",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Gateways\YiPay\YiPayPlugin",
                "provider_class": null,
                "capabilities": [
                    "precreate",
                    "query",
                    "refund",
                    "notify_verify"
                ],
                "config_schema": [
                    {
                        "key": "yipay_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "请填写易支付接口地址、商户 ID，并选择签名方式。MD5 签名需填写商户密钥；RSA 签名需填写商户私钥和平台公钥，密钥保存后不会明文回显。",
                        "theme": "warning"
                    },
                    {
                        "key": "enabled",
                        "label": "启用",
                        "type": "switch",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": true,
                        "description": "关闭后该支付渠道不会作为可用支付方式。"
                    },
                    {
                        "key": "merchant_id",
                        "label": "商户 ID",
                        "type": "text",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入易支付商户 ID"
                    },
                    {
                        "key": "api_endpoint",
                        "label": "接口地址",
                        "type": "url",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入易支付接口地址",
                        "description": "可填写平台根地址；若填写 mapi.php 等具体接口地址，系统会按同目录解析其他接口。"
                    },
                    {
                        "key": "sign_type",
                        "label": "签名方式",
                        "type": "select",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": [
                            {
                                "label": "MD5 签名",
                                "value": "MD5"
                            },
                            {
                                "label": "RSA 签名",
                                "value": "RSA"
                            }
                        ],
                        "default": "MD5"
                    },
                    {
                        "key": "merchant_key",
                        "label": "MD5 商户密钥",
                        "type": "password",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "MD5 签名时请输入商户密钥 KEY",
                        "description": "签名方式为 MD5 时必填；查询/退款接口如果服务商仍要求 KEY，也需要填写。",
                        "visible_when": {
                            "field": "sign_type",
                            "operator": "eq",
                            "value": "MD5"
                        }
                    },
                    {
                        "key": "merchant_private_key",
                        "label": "RSA 商户私钥",
                        "type": "textarea",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "RSA 签名时请输入商户私钥",
                        "visible_when": {
                            "field": "sign_type",
                            "operator": "eq",
                            "value": "RSA"
                        }
                    },
                    {
                        "key": "platform_public_key",
                        "label": "RSA 平台公钥",
                        "type": "textarea",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "RSA 签名时请输入平台公钥，用于验签回调",
                        "visible_when": {
                            "field": "sign_type",
                            "operator": "eq",
                            "value": "RSA"
                        }
                    },
                    {
                        "key": "payment_types",
                        "label": "支付方式",
                        "type": "checkbox",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": [
                            {
                                "label": "支付宝",
                                "value": "alipay"
                            },
                            {
                                "label": "微信支付",
                                "value": "wxpay"
                            }
                        ],
                        "default": [
                            "alipay"
                        ],
                        "description": "可同时启用支付宝和微信支付；未勾选时该渠道不会展示给用户。"
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\gateways\yi_pay",
                "is_installed": true,
                "is_enabled": true,
                "can_enable": false,
                "enable_disabled_reason": null,
                "status": 1,
                "installed_at": "2026-07-04 21:08:03",
                "updated_at": "2026-07-05 00:41:47",
                "binding_counts": {
                    "integration_plugin_runtime_logs": 118,
                    "payments": 6,
                    "payment_callbacks": 4,
                    "gateway_logs": 9
                },
                "business_reference_count": 137,
                "latest_runtime_log": {
                    "id": 3606,
                    "trace_id": "plugin:dff3fb32457048baa1dd97c77a1e81d0",
                    "action": "payment.options",
                    "status": "success",
                    "error_message": "",
                    "created_at": "2026-07-05 16:33:52"
                },
                "manifest_missing": false
            },
            {
                "id": 4,
                "domain": "sms",
                "slug": "aliyun",
                "key": "aliyun",
                "name": "阿里云短信",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Sms\Aliyun\AliyunPlugin",
                "provider_class": null,
                "capabilities": [
                    "verify_code"
                ],
                "config_schema": [
                    {
                        "key": "credential_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "请使用拥有短信发送权限的阿里云 AccessKey，密钥保存后不会明文回显。",
                        "theme": "warning"
                    },
                    {
                        "key": "access_key",
                        "label": "Access Key",
                        "type": "password",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入 Access Key ID"
                    },
                    {
                        "key": "secret_key",
                        "label": "Secret Key",
                        "type": "password",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入 Access Key Secret"
                    },
                    {
                        "key": "template_divider",
                        "label": "短信模板",
                        "type": "divider",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null
                    },
                    {
                        "key": "sign_name",
                        "label": "短信签名",
                        "type": "text",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入短信签名"
                    },
                    {
                        "key": "template_code",
                        "label": "模板编号",
                        "type": "text",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入短信模板编号",
                        "description": "发送验证码时使用的模板编号。"
                    },
                    {
                        "key": "network_divider",
                        "label": "接口设置",
                        "type": "divider",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null
                    },
                    {
                        "key": "api_endpoint",
                        "label": "接口地址",
                        "type": "url",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "https://dypnsapi.aliyuncs.com/",
                        "placeholder": "请输入阿里云短信接口地址"
                    },
                    {
                        "key": "rate_limit_divider",
                        "label": "验证码限流",
                        "type": "divider",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null
                    },
                    {
                        "key": "rate_limit_enabled",
                        "label": "启用短信验证码限流",
                        "type": "switch",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": true,
                        "description": "限制使用此插件发送短信验证码的单 IP 频率。"
                    },
                    {
                        "key": "ip_minute_limit",
                        "label": "单 IP 每分钟上限",
                        "type": "number",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": 6,
                        "description": "设为 0 表示不限制。",
                        "min": 0,
                        "step": 1
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\sms\aliyun",
                "is_installed": true,
                "is_enabled": true,
                "can_enable": false,
                "enable_disabled_reason": null,
                "status": 1,
                "installed_at": "2026-07-01 17:41:59",
                "updated_at": "2026-07-01 17:41:59",
                "binding_counts": {
                    "integration_plugin_bindings": 2,
                    "notification_logs": 100,
                    "sms_logs": 24
                },
                "business_reference_count": 126,
                "latest_runtime_log": null,
                "manifest_missing": false
            },
            {
                "id": 16,
                "domain": "upstream",
                "slug": "kanghostx",
                "key": "kanghostx",
                "name": "康乐虚拟主机",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Servers\KangHostx\KangHostxPlugin",
                "provider_class": null,
                "capabilities": [
                    "App\Services\Upstream\Contracts\ProvidesConsoleCatalog",
                    "App\Services\Upstream\Contracts\ProvidesConsoleRuntime",
                    "App\Services\Upstream\Contracts\ProvidesProvisioning",
                    "App\Services\Upstream\Contracts\ProvidesRenewal",
                    "App\Services\Upstream\Contracts\ProvidesStatusSync"
                ],
                "config_schema": [
                    {
                        "key": "kanghostx_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "该插件按 kanghostx 原模块的 Kangle WHM API 对接，供应商接口地址填写面板根地址，API 密钥填写 accesshash。",
                        "theme": "info"
                    },
                    {
                        "key": "provider_key",
                        "label": "上游标识",
                        "type": "readonly",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "kanghostx",
                        "description": "供应商绑定 provider_key 为 kanghostx 时使用康乐虚拟主机插件。"
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\servers\kanghostx",
                "is_installed": true,
                "is_enabled": true,
                "can_enable": false,
                "enable_disabled_reason": null,
                "status": 1,
                "installed_at": "2026-07-04 23:13:33",
                "updated_at": "2026-07-04 23:13:37",
                "binding_counts": {
                    "supplier_plugin_bindings": 1,
                    "integration_plugin_runtime_logs": 291
                },
                "business_reference_count": 292,
                "latest_runtime_log": {
                    "id": 3596,
                    "trace_id": "plugin:848e426b994b43208be058bd1e640dd7",
                    "action": "server.supplier_form_schema",
                    "status": "success",
                    "error_message": "",
                    "created_at": "2026-07-05 16:33:42"
                },
                "manifest_missing": false
            },
            {
                "id": 8,
                "domain": "upstream",
                "slug": "mofang_finance",
                "key": "mofang_finance_api",
                "name": "魔方财务接口",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Servers\MofangFinance\MofangFinancePlugin",
                "provider_class": null,
                "capabilities": [
                    "App\Services\Upstream\Contracts\ProvidesConsoleAccess",
                    "App\Services\Upstream\Contracts\ProvidesConsoleCatalog",
                    "App\Services\Upstream\Contracts\ProvidesConsoleNetwork",
                    "App\Services\Upstream\Contracts\ProvidesConsoleRuntime",
                    "App\Services\Upstream\Contracts\ProvidesConsoleSecurity",
                    "App\Services\Upstream\Contracts\ProvidesProvisioning",
                    "App\Services\Upstream\Contracts\ProvidesRenewal",
                    "App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh",
                    "App\Services\Upstream\Contracts\ProvidesStatusSync"
                ],
                "config_schema": [
                    {
                        "key": "mofang_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "该插件承载魔方财务上游差异适配，接口地址、账号和密钥由供应商配置维护。",
                        "theme": "info"
                    },
                    {
                        "key": "provider_key",
                        "label": "上游标识",
                        "type": "readonly",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "mofang_finance_api",
                        "description": "供应商绑定 provider_key 必须保持该值，不要别名为 hosting_panel_api。"
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\servers\mofang_finance",
                "is_installed": true,
                "is_enabled": true,
                "can_enable": false,
                "enable_disabled_reason": null,
                "status": 1,
                "installed_at": "2026-07-02 11:36:40",
                "updated_at": "2026-07-04 21:18:59",
                "binding_counts": {
                    "integration_plugin_bindings": 1,
                    "supplier_plugin_bindings": 2,
                    "product_upstream_bindings": 143,
                    "service_upstream_bindings": 152,
                    "service_runtime_snapshots": 152,
                    "service_connection_snapshots": 152,
                    "service_provision_attempts": 204,
                    "integration_plugin_runtime_logs": 2614
                },
                "business_reference_count": 3420,
                "latest_runtime_log": {
                    "id": 3633,
                    "trace_id": "plugin:072e94adde43446d99b5eb9dd4163f8c",
                    "action": "server.resolve_capability",
                    "status": "success",
                    "error_message": "",
                    "created_at": "2026-07-05 16:34:05"
                },
                "manifest_missing": false
            },
            {
                "id": 17,
                "domain": "verification",
                "slug": "stay33",
                "key": "stay33",
                "name": "Stay33 实名认证",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Certification\Stay33\Stay33Plugin",
                "provider_class": null,
                "capabilities": [
                    "personal",
                    "scan_url",
                    "query_status",
                    "verify_callback",
                    "fee_config"
                ],
                "config_schema": [
                    {
                        "key": "basic_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "请填写 Stay33 服务商后台分配的 API 标识、接口密钥和认证业务码。",
                        "theme": "info"
                    },
                    {
                        "key": "api",
                        "label": "API 标识",
                        "type": "text",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入 API 标识",
                        "description": "用于识别当前认证应用。"
                    },
                    {
                        "key": "key",
                        "label": "接口密钥",
                        "type": "password",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入接口密钥",
                        "description": "已配置时不会明文回显，留空表示不修改。"
                    },
                    {
                        "key": "biz_code",
                        "label": "认证业务码",
                        "type": "text",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "例如 FACE",
                        "description": "服务商分配的实名业务场景码。"
                    },
                    {
                        "key": "api_endpoint",
                        "label": "接口地址",
                        "type": "url",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "https://idc.stay33.cn/realname/certapi.php",
                        "placeholder": "请输入 HTTPS 接口地址",
                        "description": "通常保持默认地址，只有服务商要求时才修改。"
                    },
                    {
                        "key": "ssl_verify",
                        "label": "SSL 证书校验",
                        "type": "switch",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": true,
                        "description": "开启后校验服务商 HTTPS 证书；证书链异常时请配置 CA 证书路径。"
                    },
                    {
                        "key": "ca_bundle",
                        "label": "CA 证书路径",
                        "type": "text",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "例如 /etc/ssl/certs/cacert.pem",
                        "description": "可选，填写服务器本地 CA bundle 文件路径。"
                    },
                    {
                        "key": "billing_divider",
                        "label": "计费设置",
                        "type": "divider",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null
                    },
                    {
                        "key": "charge_enabled",
                        "label": "插件收费",
                        "type": "switch",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": false,
                        "description": "开启后，用户发起实名认证时按配置金额扣费。"
                    },
                    {
                        "key": "amount",
                        "label": "收费金额",
                        "type": "number",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": 0,
                        "description": "单位：元。关闭收费时该字段不生效。",
                        "min": 0,
                        "step": 0.01,
                        "visible_when": {
                            "field": "charge_enabled",
                            "operator": "eq",
                            "value": true
                        }
                    },
                    {
                        "key": "free_times",
                        "label": "免费次数",
                        "type": "number",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": 0,
                        "description": "每个用户可免费发起认证的次数。",
                        "min": 0,
                        "step": 1
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\certification\stay33",
                "is_installed": true,
                "is_enabled": false,
                "can_enable": false,
                "enable_disabled_reason": "当前功能域已启用「百度智能云人脸实名认证」，请先停用后再启用其他插件",
                "status": 0,
                "installed_at": "2026-07-05 01:23:40",
                "updated_at": "2026-07-05 01:23:40",
                "binding_counts": [],
                "business_reference_count": 0,
                "latest_runtime_log": null,
                "manifest_missing": false
            },
            {
                "id": 15,
                "domain": "verification",
                "slug": "baidu_face",
                "key": "baidu_face",
                "name": "百度智能云人脸实名认证",
                "version": "1.0.0",
                "entry_class": "Caiwu\Plugins\Certification\BaiduFace\BaiduFacePlugin",
                "provider_class": null,
                "capabilities": [
                    "personal",
                    "scan_url",
                    "query_status",
                    "direct_verify",
                    "fee_config"
                ],
                "config_schema": [
                    {
                        "key": "basic_notice",
                        "label": "配置说明",
                        "type": "notice",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": null,
                        "content": "请填写百度智能云人脸识别应用的 API Key、Secret Key，并确认 H5 实名认证方案 ID。密钥保存后不会明文回显。",
                        "theme": "info"
                    },
                    {
                        "key": "api_key",
                        "label": "百度 API Key",
                        "type": "password",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入百度智能云应用 API Key",
                        "description": "填写百度智能云人脸识别应用的 API Key。"
                    },
                    {
                        "key": "secret_key",
                        "label": "百度 Secret Key",
                        "type": "password",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": "",
                        "placeholder": "请输入百度智能云应用 Secret Key",
                        "description": "保存后系统会清空旧 access_token，下次调用实名接口时自动重新获取。"
                    },
                    {
                        "key": "api_version",
                        "label": "百度实名接口版本",
                        "type": "select",
                        "required": false,
                        "secret": "***已脱敏***",
                        "options": [
                            {
                                "label": "V4 - face/v4/mingjing/verify",
                                "value": "v4"
                            },
                            {
                                "label": "V3 - face/v3/person/verify",
                                "value": "v3"
                            }
                        ],
                        "default": "v4",
                        "description": "V4 支持更多风控返回字段，V3 用于兼容旧接口。当前用户端实名流程默认走 H5 方案，接口版本用于服务端直连动作。"
                    },
                    {
                        "key": "h5_plan_id",
                        "label": "H5 方案ID",
                        "type": "number",
                        "required": true,
                        "secret": "***已脱敏***",
                        "options": null,
                        "default": 25921,
                        "description": "使用 H5 人脸实名认证方案时必填，用于获取 verify_token。",
                        "min": 1,
                        "step": 1
                    }
                ],
                "base_path": "C:\Users\Admin\Desktop\caiwu\backend\plugins\certification\baidu_face",
                "is_installed": true,
                "is_enabled": true,
                "can_enable": false,
                "enable_disabled_reason": null,
                "status": 1,
                "installed_at": "2026-07-04 23:10:53",
                "updated_at": "2026-07-04 23:31:17",
                "binding_counts": {
                    "integration_plugin_bindings": 1,
                    "integration_plugin_runtime_logs": 215
                },
                "business_reference_count": 216,
                "latest_runtime_log": {
                    "id": 3631,
                    "trace_id": "plugin:c9b22dffadba453d81a18a7f30485c81",
                    "action": "certification.fee_config",
                    "status": "success",
                    "error_message": "",
                    "created_at": "2026-07-05 16:34:02"
                },
                "manifest_missing": false
            }
        ],
        "total": 11,
        "page": 1,
        "page_size": 11
    },
    "timestamp": 1783240489
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:49  
· 响应状态码：200  
· 调用方式：GET /api/admin/integration-plugins  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\IntegrationPluginController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:integration_plugin.view`
