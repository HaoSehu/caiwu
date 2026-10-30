# 百度智能云人脸实名认证 插件

该插件把 百度智能云人脸实名认证 接入 Caiwu 的 `verification` 能力域，slug 为 `baidu_face`、plugin_key 为 `baidu_face`。

## 目录结构

```text
backend/plugins/certification/baidu_face/
├── BaiduFacePlugin.php
├── logic/BaiduFace.php
├── logic/BaiduFaceClient.php
└── config.php
```

## 配置项

在后台插件管理中按下列字段填写；标记为「加密」的字段保存后不会明文回显。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `api_key` | password | 是 | 是 | 填写百度智能云人脸识别应用的 API Key。 |
| `secret_key` | password | 是 | 是 | 保存后系统会清空旧 access_token，下次调用实名接口时自动重新获取。 |
| `api_version` | select | 否 | 否 | V4 支持更多风控返回字段，V3 用于兼容旧接口。当前用户端实名流程默认走 H5 方案，接口版本用于服务端直连动作。 |
| `h5_plan_id` | number | 是 | 否 | 使用 H5 人脸实名认证方案时必填，用于获取 verify_token。 |
| `charge_enabled` | switch | 否 | 否 | 开启后，用户发起实名认证时按配置金额扣费。 |
| `amount` | number | 否 | 否 | 单位：元。关闭收费时该字段不生效。 |
| `free_times` | number | 否 | 否 | 每个用户可免费发起认证的次数。 |

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
