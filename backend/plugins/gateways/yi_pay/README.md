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

## 移动端 device 口径

预下单会按**本次请求的 User-Agent** 上报 `device`，因为它决定网关给什么链接：

| 场景 | `device` | 网关返回 |
| --- | --- | --- |
| 微信通道 + 微信内置浏览器 | `wechat` | `pay/jspay/…`（公众号 JSAPI 收银台，微信内直接唤起支付） |
| 微信通道 + 其他移动端浏览器 | `mobile` | 移动分支（H5 / wap / 二维码） |
| 微信通道 + 桌面 | `pc` | `pay/jspay/…` 或扫码页 |
| 支付宝通道 | 固定 `pc` | 收银台跳转页 |

微信通道曾固定上报 `pc`，网关只下发 `pay/jspay/…` 公众号收银台；该页面在**任何**浏览器都会
302 到微信 OAuth，微信外打开会被微信拒绝并提示 `invalid_source`，手机端因此无法完成支付。
前端不要把这个 https 链接包成 `weixin://dl/businessWebview/link/`——该路径不是微信公开协议，
同样会触发 `invalid_source`；正确做法是微信内直接跳转，微信外提示用户复制链接到微信打开。
