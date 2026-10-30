# 主机面板接口 插件

该插件把 主机面板接口 接入 Caiwu 的 `upstream` 能力域，slug 为 `hosting_panel`、plugin_key 为 `hosting_panel_api`。

## 目录结构

```text
backend/plugins/servers/hosting_panel/
├── HostingPanelPlugin.php
├── logic/HostingPanel.php
├── lib/HostingPanelAuthRefreshTask.php
└── config.php
```

## 配置项

在后台插件管理中按下列字段填写；标记为「加密」的字段保存后不会明文回显。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `provider_key` | readonly | 否 | 否 | 供应商绑定 provider_key 为 hosting_panel_api 时使用主机面板接口插件。 |

## 供应商侧配置

接口地址、账号与 JWT 登录凭证不在插件配置里，由「供应商」记录维护（`api_url` 等字段）。插件本身只声明 `provider_key = hosting_panel_api`，供应商绑定时选择该 provider 即可。

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
