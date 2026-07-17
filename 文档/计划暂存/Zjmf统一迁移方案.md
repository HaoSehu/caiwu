# ZJMF 统一迁移方案（零兼容层）

- 状态：实施中
- 对齐时间：`2026-07-17`
- 范围：出站上游插件边界、运行时标识、持久化数据与文档统一为 `zjmf`
- 不变范围：`zjmf_bridge` 的入站职责与 `/zjmf/v1/*` 路由

---

## 1. 最终标识

| 层级 | 最终值 |
| --- | --- |
| 插件目录 / slug | `plugins/servers/zjmf_finance` / `zjmf_finance` |
| PHP 命名空间 | `Caiwu\Plugins\Servers\ZjmfFinance` |
| PHP 类名 | `Zjmf*` |
| provider 常量 | `ProviderKey::ZJMF_FINANCE_API` |
| 数据面 provider key | `zjmf_finance_api` |
| 调度 hook | `plugins.zjmf_finance.*` |
| 调度任务 | `refresh-zjmf-finance-auth`、`sync-zjmf-finance-inventory-and-services` |
| 账单恢复命令 | `finance:restore-zjmf-billing` |
| 账单恢复确认短语 | `RESTORE_ZJMF_BILLING` |

上游 Cookie、HTTP 字段和 `/zjmf/v1/*` 协议保持其既有外部契约；它们不属于 Caiwu 内部命名空间。

## 2. 职责边界

| 插件 | 方向 | 职责 |
| --- | --- | --- |
| `plugins/servers/zjmf_finance` | 出站 | Caiwu 调用 ZJMF 财务上游：商品、开通、续费、状态、控制台、库存与认证刷新 |
| `plugins/addons/zjmf_bridge` | 入站 | ZJMF 调用 Caiwu：签名、JWT、财务、产品、服务和工单兼容 API |

两个插件方向相反，不合并、不互相承载业务代码。核心仅保留通用上游契约、共享传输和插件运行时能力；ZJMF 业务实现全部位于 servers 插件。

## 3. 一次性数据迁移

新增 migration `2026_07_17_000002_rename_zjmf_finance_plugin_identity.php` 在单一事务中完成转换：

1. 迁移 `integration_plugins` 的 slug、plugin key、展示名、入口类与 Provider 类，保持原记录 ID。
2. 转换供应商、商品、插件绑定、产品绑定、服务绑定、运行快照和开通尝试中的 provider key。
3. 转换服务开通 JSON、插件配置及运行日志 JSON 中的标识引用。
4. 转换已记录的插件 slug、调度任务 key、调度任务标题和相关日志上下文。
5. 若发现多个记录竞争同一 ZJMF 插件身份，立即抛错并停止，不猜测合并策略。

历史 migration 文件不修改。新安装直接读取 ZJMF manifest；已有库通过本 migration 一次迁完。

## 4. 不做兼容层

- 不保留旧目录、命名空间、类名、slug、provider 常量或 provider key。
- 不做旧 key 双读、缓存回退、别名解析或旧任务 key 监听。
- 不保留旧账单恢复命令或旧确认短语。
- 不把 ZJMF provider key 归一化为 `hosting_panel_api`。
- 不把 `zjmf_bridge` 合入 servers 插件。

数据库迁移的 `down()` 仅用于与同一提交的代码回滚配套执行，不构成运行时兼容能力。

## 5. 验收

- [ ] 核心不保留任何 ZJMF 业务实现或遗留集成命名空间。
- [ ] `backend/plugins/servers/zjmf_finance` 可被插件扫描并注册 provider。
- [ ] 新购同步履约由 `ProvidesSynchronousNewPurchaseFulfillment` capability 声明，不依赖 provider key 特判。
- [ ] 供应商创建、商品绑定、服务开通、控制台、库存/状态同步均使用 `zjmf_finance_api`。
- [ ] 已安装插件、历史绑定、服务 JSON 和调度记录完成一次性数据转换。
- [ ] `zjmf_bridge` 路由与测试保持独立可用。
- [ ] 目标 PHP 测试、前端构建、PHP lint 和全仓旧运行时标识扫描通过。

## 6. 风险与部署顺序

1. 先部署包含目录改名、代码改名和 migration 的同一版本。
2. 运行 migration，再重启 PHP/队列/调度进程以清除旧的类与缓存实例。
3. 通过插件扫描、供应商绑定、一次开通和两项插件任务验证生产运行。
4. 出现身份冲突时停止部署，核对 `integration_plugins` 记录后处理；禁止通过别名绕过冲突。

回滚必须整体回退代码与 migration，不能只回退数据库或只恢复旧目录。
