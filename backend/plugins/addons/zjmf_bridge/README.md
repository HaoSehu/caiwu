# ZJMF Bridge Addon

ZJMF Bridge 将 `/zjmf/v1/*` 的业务处理收敛到 Caiwu addons 插件中。

核心应用只保留受控入口：

- `routes/zjmf.php` 暴露固定 URL 契约。
- `zjmf.enabled`、`zjmf.signature`、`zjmf.client`、`zjmf.actor`、`zjmf.log` 完成入口级校验与审计。
- 校验通过后由 `PluginRuntimeRegistry::execute('addons', 'zjmf_bridge', 'zjmf.dispatch', ...)` 执行本插件。

入口级 `base_path`、`app_id`、`secret`、签名窗口等配置仍在 `config/zjmf_bridge.php`，因为这些配置必须在进入插件前用于请求校验。
