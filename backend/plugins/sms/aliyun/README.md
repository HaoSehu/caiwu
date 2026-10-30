# 阿里云号码认证短信 插件

该插件把 阿里云号码认证短信 接入 Caiwu 的 `sms` 能力域，slug 为 `aliyun`、plugin_key 为 `aliyun`。

## 目录结构

```text
backend/plugins/sms/aliyun/
├── AliyunPlugin.php
├── lib/AliyunSmsService.php
├── lib/AliyunSmsClient.php
└── config.php
```

## 配置项

在后台插件管理中按下列字段填写；标记为「加密」的字段保存后不会明文回显。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `access_key` | password | 是 | 是 |  |
| `secret_key` | password | 是 | 是 |  |
| `sign_name` | text | 是 | 否 | 请填写阿里云号码认证控制台中可用于发送验证码的短信签名。 |
| `rate_limit_enabled` | switch | 否 | 否 | 限制使用此插件发送短信验证码的单 IP 频率。 |
| `ip_minute_limit` | number | 否 | 否 | 设为 0 表示不限制。 |

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
