# 易支付 插件

该插件把 易支付 接入 Caiwu 的 `payment` 能力域，slug 为 `yi_pay`、plugin_key 为 `yipay`。

## 目录结构

```text
backend/plugins/gateways/yi_pay/
├── YiPayPlugin.php
├── lib/YiPayClient.php
├── lib/YiPayService.php
└── config.php
```

## 配置项

在后台插件管理中按下列字段填写；标记为「加密」的字段保存后不会明文回显。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `enabled` | switch | 是 | 否 | 关闭后该支付渠道不会作为可用支付方式。 |
| `merchant_id` | text | 是 | 否 |  |
| `api_endpoint` | url | 是 | 否 | 可填写平台根地址；若填写 mapi.php 等具体接口地址，系统会按同目录解析其他接口。 |
| `sign_type` | select | 是 | 否 |  |
| `merchant_key` | password | 否 | 是 | 签名方式为 MD5 时必填；查询/退款接口如果服务商仍要求 KEY，也需要填写。 |
| `merchant_private_key` | textarea | 否 | 是 |  |
| `platform_public_key` | textarea | 否 | 是 |  |
| `payment_types` | checkbox | 是 | 否 | 可同时启用支付宝和微信支付；未勾选时该渠道不会展示给用户。 |

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
