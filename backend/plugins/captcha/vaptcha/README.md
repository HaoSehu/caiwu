# VAPTCHA 智能人机验证 插件

该插件把 VAPTCHA 智能人机验证 接入 Caiwu 的 `captcha` 能力域，slug 为 `vaptcha`、plugin_key 为 `vaptcha`。

## 目录结构

```text
backend/plugins/captcha/vaptcha/
├── VaptchaPlugin.php
├── lib/VaptchaCaptchaService.php
└── config.php
```

## 配置项

在后台插件管理中按下列字段填写；标记为「加密」的字段保存后不会明文回显。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `vid` | text | 是 | 否 | 来自 VAPTCHA 控制台的验证单元 VID，用于前端初始化。 |
| `replay_ttl_seconds` | number | 否 | 否 | 同一次行为验证结果在多少秒内禁止重复提交，默认 600，允许范围 60-1800。 |
| `vkey` | password | 是 | 是 | 来自 VAPTCHA 控制台的服务端密钥，仅用于后端二次验证。 |

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
