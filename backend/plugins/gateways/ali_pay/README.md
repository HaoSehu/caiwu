# 支付宝当面付 插件

该插件把 支付宝当面付 接入 Caiwu 的 `payment` 能力域，slug 为 `ali_pay`、plugin_key 为 `alipay`。

## 目录结构

```text
backend/plugins/gateways/ali_pay/
├── AliPayPlugin.php
├── lib/AlipayClient.php
├── lib/AlipayService.php
└── config.php
```

## 配置项

在后台插件管理中按下列字段填写；标记为「加密」的字段保存后不会明文回显。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `alipay_enabled` | switch | 是 | 否 | 关闭后该支付渠道不会作为可用支付方式。 |
| `app_id` | text | 是 | 否 |  |
| `private_key` | textarea | 是 | 是 | 请填写应用私钥，不要填写支付宝公钥。 |
| `alipay_public_key` | textarea | 是 | 是 |  |

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
