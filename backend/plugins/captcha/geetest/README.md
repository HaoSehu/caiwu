# GeeTest 行为验证 插件

该插件把 GeeTest 行为验证 接入 Caiwu 的 `captcha` 能力域，slug 为 `geetest`、plugin_key 为 `geetest`。

## 目录结构

```text
backend/plugins/captcha/geetest/
├── GeetestPlugin.php
├── lib/GeetestCaptchaService.php
└── config.php
```

## 配置项

在后台插件管理中按下列字段填写；标记为「加密」的字段保存后不会明文回显。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `captcha_id` | text | 是 | 否 | 来自 GeeTest 控制台的 captcha_id。 |
| `captcha_key` | password | 是 | 是 | 来自 GeeTest 控制台的 captcha_key。 |

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
