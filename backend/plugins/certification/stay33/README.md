# Stay33 实名认证 插件

该插件把 Stay33 实名认证 接入 Caiwu 的 `verification` 能力域，slug 为 `stay33`、plugin_key 为 `stay33`。

## 目录结构

```text
backend/plugins/certification/stay33/
├── Stay33Plugin.php
├── logic/Stay33.php
├── logic/Stay33Client.php
└── config.php
```

## 配置项

在后台插件管理中按下列字段填写；标记为「加密」的字段保存后不会明文回显。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `api` | text | 是 | 否 | 用于识别当前认证应用。 |
| `key` | password | 是 | 是 | 已配置时不会明文回显，留空表示不修改。 |
| `biz_code` | text | 是 | 否 | 服务商分配的实名业务场景码。 |
| `api_endpoint` | url | 否 | 否 | 通常保持默认地址，只有服务商要求时才修改。 |
| `ssl_verify` | switch | 否 | 否 | 开启后校验服务商 HTTPS 证书；证书链异常时请配置 CA 证书路径。 |
| `ca_bundle` | text | 否 | 否 | 可选，填写服务器本地 CA bundle 文件路径。 |
| `charge_enabled` | switch | 否 | 否 | 开启后，用户发起实名认证时按配置金额扣费。 |
| `amount` | number | 否 | 否 | 单位：元。关闭收费时该字段不生效。 |
| `free_times` | number | 否 | 否 | 每个用户可免费发起认证的次数。 |

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
