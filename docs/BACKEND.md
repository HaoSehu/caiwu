---
status: current
updated: 2026-11-03
owner: backend-platform
---

# 后端工程规范

本文档定义 caiwu Laravel 12 后端后续人工开发、审查、重构和第三方集成治理的长期标准。目标是让后端代码可读、可测、可替换、可追踪，并降低后续人工维护成本。

重要约束：当前开发文档、开发规范和其他约束性文档不具备完全真实性，不保证完全真实。后续治理必须以当前代码检查、真实路由、可观察运行行为和必要的受控验证为主，发现文档与当前代码冲突时，应先记录冲突，再按检查结论更新补充文档。

治理执行原则：审计要覆盖关键目录和风险域，但修改只落在有价值文件上；不得为了“覆盖”机械加注释、机械格式化或制造无意义改动。自动生成的 `docs/自动生成/接口/后端API清单.md` 不手工编辑。

> 2026-11-03 全量审查复核：分层边界、插件边界、日志与脱敏口径、测试与验证命令等条款已对照当前代码核验，结论仍成立（控制器无直接 `Http::*`、管理端路由权限覆盖完整、回调验签与财务写口符合本文约定）；状态由 `needs-review` 恢复为 `current`。

## 1. 治理目标

1. Controller 保持薄层，业务逻辑收敛到 Service、Action、Domain Service 或专用集成客户端。
2. 参数验证、响应转换、业务流程、第三方协议、日志审计各自有清晰边界。
3. API 请求和响应工程化，避免参数归一化、响应字段转换、错误映射散落在 Controller 或第三方驱动里。
4. 支付、实名认证、ZJMF 财务/ZJMF 云、短信、邮件、对象存储等第三方能力以插件/驱动方式接入，业务层依赖项目内部接口而不是具体供应商。
5. 所有高风险业务具备可测试入口，包括支付回调、余额扣减、服务开通、上游失败、权限校验。
6. 必要注释解释业务原因、边界条件、第三方协议差异和历史兼容，不写无意义的逐行翻译。
7. 后端注释、API 错误 `message`、字段校验错误和前端用户可见提示必须使用简体中文。
8. 建立审查闭环，防止错审、漏审和审完不报。
9. 规则变化必须同步项目文档和 caiwu 相关 skills，避免后续代理和人工开发继续沿用旧约定。

## 2. 非目标

1. 不做一次性大重写。
2. 不为了抽象破坏当前稳定支付、账单、服务实例、回调链路。
3. 不未经确认执行数据库初始化、迁移或历史迁移补跑。
4. 不手工编辑自动生成的 `docs/自动生成/接口/后端API清单.md`。
5. 不把所有第三方能力塞进一个万能接口。

## 3. 后端分层标准

### Controller

- 只负责接收请求、鉴权上下文、调用应用服务、返回响应。
- 不直接拼装第三方请求。
- 不直接调用 `Http::*`。
- 不写复杂业务分支。
- 不在 Controller 中散落事务。

### FormRequest

- 请求参数、类型、范围、枚举值、业务前置约束优先放入 FormRequest。
- 复杂校验可以委托 Rule 或 Service，但 Controller 不应手写 validate。

### Resource

- 负责 API 输出结构转换。
- 不承载业务判断。
- 不直接访问第三方或触发写操作。

### Service / Action

- Service 按业务领域聚合，例如账单、支付、余额、服务实例、上游集成。
- 单次清晰业务动作可抽为 Action，避免一个 Service 无限膨胀。
- 涉及财务、审计、状态流转的 Service 必须考虑事务、幂等、审计字段和并发锁。
- 资金流按流拆分（`Finance/`）：充值 `RechargePaymentService`、消费-余额 `BalanceInvoicePaymentService`、消费-在线 `GatewayInvoicePaymentService`、消费-混合 `MixPaymentService`、冲正退款 `InvoiceRefundService`；支付成功编排归 `InvoicePaidOrchestrator`，网关回调/轮询入账事务体统一收敛到 `InvoiceSettlementService`。资金写入点不得增加：余额唯一经 `AccountService`、台账唯一经 `FinanceLedgerService`（`Finance/Core/`）、单据唯一经 `FinanceDocumentService`；`callback_raw` 幂等键读写统一经 `PaymentCallbackRaw`。

### Model

- Model 保持数据模型、关联、scope、cast、轻量领域状态判断。
- 不放跨表复杂流程。
- `$fillable` 必须精确，不开放敏感字段批量赋值。

### 类型声明

- 新增 PHP 源文件必须写 `declare(strict_types=1)`，`app/` 与 `plugins/` 一律适用。
- 历史文件的补齐是**基线收敛**而非一次性开关：给已上线的文件补严格类型会改变隐式类型转换行为（例如把 `"12"` 传给 `int` 参数会直接抛 `TypeError`），必须随所属模块评估并回归，不能靠一个提交批量打开。
- 待补齐文件登记在 `backend/strict-types-baseline.json`，由 `composer check:strict-types` 校验；清单**只减不增**——条目对应文件补上声明或文件已消失同样报错，避免清单烂尾。

### Job / Listener / Command

- 只做异步调度和流程编排，具体业务逻辑委托 Service。
- Job 必须明确 `$tries`、`$timeout`、`$backoff` 或说明不需要的原因。
- 调度任务涉及并发时使用 `withoutOverlapping()`。

## 4. 第三方插件化标准

第三方能力按“能力域接口 + Provider 驱动 + DTO + 统一异常 + 配置注册 + fake provider 测试替身”治理。

### 推荐目录

```text
backend/app/Contracts/Integrations/
backend/app/Services/Integrations/
backend/app/Services/Integrations/Payments/
backend/app/Services/Integrations/Identity/
backend/app/Services/Integrations/Upstream/
backend/app/Services/Integrations/Notifications/
backend/app/Services/Integrations/Storage/
backend/app/Services/Integrations/Drivers/
backend/config/integrations.php
```

### 推荐接口

- `PaymentGatewayInterface`：支付创建、查询、关闭、回调验签、回调解析。
- `RefundGatewayInterface`：退款申请、退款查询、退款回调解析。
- `IdentityVerificationProviderInterface`：实名二要素、三要素或供应商支持的实名能力。
- `UpstreamProviderInterface`：ZJMF 财务/ZJMF 云等上游开通、续费、暂停、重启、同步。
- `SmsProviderInterface`：短信发送、模板校验、发送结果查询。
- `ObjectStorageProviderInterface`：上传、下载、临时链接、删除。

### 强制要求

1. 业务 Service 依赖项目内部接口，不依赖具体 SDK 或供应商字段。
2. Provider 原始响应必须转换为项目内部 DTO。
3. Provider 异常必须映射为统一业务异常。
4. 回调必须先通过统一入口处理签名、日志、幂等，再分发到具体 provider。
5. 日志必须包含 trace_id、provider、能力类型、资源 ID、结果状态。日志**不做脱敏**（见下文安全段落）：管理员端与日志都需要完整信息，凭据类字段由 `AuditParamRedactor` 单独处理。
6. 每个 Provider 必须声明能力，例如是否支持退款、实名三要素、服务暂停、续费、重试。
7. 必须提供 fake provider 或 mock gateway，用于自动化测试和本地开发。
8. 允许旧 Provider 和新 Provider 并存，历史记录按原 provider 解释，新请求可按配置切换。

### 当前落地进度

- 插件运行时已落地 `IntegrationPluginService`、`PluginInstaller`、`PluginScanner`、`PluginRuntimeRegistry`、`PluginConfigRepository` 和能力域 adapter。
- 插件文件位于 `backend/plugins/{domain}/{slug}/`，当前能力域包含支付网关、实名认证、短信、邮件、上游服务器。
- 插件配置由 `integration_plugins`、`integration_plugin_configs` 管理；敏感配置必须加密保存，API 只返回是否已配置和脱敏预览。
- 支付网关已通过 `PaymentGatewayInterface`、`PaymentGatewayManager` 和插件支付适配器接入；`payments.gateway` 仍保留历史业务值，网关插件 key 与业务 gateway 值不得混用。
- 后续新增支付方式必须新增插件或 driver，并通过插件运行时注册，不得在支付流 Service（如 `GatewayInvoicePaymentService`）或 Controller 中直接注入具体 provider。
- 实名认证能力通过插件实名适配器接入，初始化、扫码链接、查询结果、回调验签、收费配置必须通过内部 DTO/接口表达，业务层不得依赖供应商原始数组。
- 短信、邮件能力通过插件适配器接入，供应商英文或技术类错误必须映射为简体中文业务提示。
- 短信模板双轨（2026-10-11）：**默认走系统自带业务模板**——`notification_templates`（channel=sms）文案渲染后随 `SmsMessageRequest` 直发（文本通道如 stay33 零配置可用），管理端短信模板页不做运营商模板 UI（用户裁决）。**可选的运营商模板管理能力层**（供审核型通道如阿里云启用，当前无 UI 入口）：插件经 `sms.templates.pull/push/update/delete/defaults/status` action 协议翻译（不落库），平台落库 `sms_provider_templates`（生命周期 draft→pending→approved/rejected，照抄 ZJMF message_template 模式），发送解析链 `SmsTemplateResolver` 按「options 显式 > approved 映射（plugin_key=绑定 driver）> notification_templates.provider_template_id 旧列 > fallback > 业务码」取运营商模板 ID；仅 approved 可挂接业务码，声明变量交集校验缺失 fail-fast。业务码与运营商原生模板 ID 数值可能撞车，属不同命名空间，禁止混用。
- 上游 provider 已通过能力对象声明服务开通、续费、控制台、网络、安全组、状态同步等能力；`zjmf_finance_api` 必须继续解析到ZJMF插件 adapter，不得折叠为共享主机面板 transport。
- ZJMF 财务/ZJMF 云和主机面板缓存 key 必须 provider-aware；是否保留旧 key 读取只能由当前已批准的执行计划明确，禁止把 `zjmf_finance_api` 归一化为 `hosting_panel_api`。
- 对象存储等尚未插件化的能力域接入前，必须先补内部 contract、DTO、错误映射、fake provider 或插件测试替身。

### 禁止做法

- Controller 直接选择支付宝、微信、ZJMF 财务等 provider。
- 业务表直接保存不可解释的第三方原始响应作为唯一状态来源。
- 一个万能 `PluginInterface` 覆盖支付、实名、上游、短信、存储所有能力。
- 没有回调幂等和回归测试就切换真实供应商。

## 5. API 请求与响应工程化

API 治理必须同时覆盖请求入口和响应出口。新增或重构接口时，以 `docs/参考资料/接口/API格式规范.md` 为当前 API 规范文档，并以当前代码检查结果反向补充该文档。

### 请求侧

- 复杂接口必须使用 FormRequest，Controller 不应手写大量参数读取和类型转换。
- 请求字段统一 `snake_case`。
- 分页统一 `page`、`page_size`。
- 排序、筛选、布尔、枚举、金额、日期范围必须在 FormRequest 或 Request DTO 中归一化。
- 金额必须明确单位，支付/余额相关不得隐式使用 float。
- 跨 Service 传递的请求参数推荐转为 Command/DTO。

### 响应侧

- Controller 使用统一响应方法或抛出统一业务异常。
- Service 不直接返回 HTTP Response。
- 面向前端字段由 Resource 或 Response DTO 控制。
- 第三方原始响应不得直接穿透到 Controller 或前端。
- 敏感字段默认不返回。
- 错误响应必须统一业务码、HTTP 状态码和 `message`。
- 兼容字段必须说明保留原因和移除条件。

### 文档同步

- API 请求/响应规则变化时，优先更新 `docs/参考资料/接口/API格式规范.md`。
- 路由清单变化时按脚本重刷 `docs/自动生成/接口/后端API清单.md`，禁止手工编辑该文件。
- 影响前端字段时，必须同步前端调用和测试建议。

## 6. 注释标准

### 必须注释

- 第三方协议差异和非显然字段映射。
- 财务、账单、支付、余额、服务实例状态流转中的关键业务约束。
- 幂等键、锁、事务边界的设计原因。
- 为兼容历史数据而保留的特殊逻辑。
- public interface、DTO、关键 Service public 方法、Job 的业务目的和副作用。
- 所有新增注释优先使用简体中文，便于后续人工维护。

### 不建议注释

- “给变量赋值”“调用某方法”这类代码本身已经表达清楚的内容。
- 与代码不同步的长段背景说明。
- 包含密钥、token、真实账号、供应商私密参数的内容。
- 面向维护者的中文注释中夹杂未解释的英文业务缩写。

### 推荐注释形式

- 接口和 DTO 用 DocBlock 说明输入输出语义。
- 复杂业务分支前用 1 到 3 行说明“为什么这样做”。
- 历史兼容逻辑标注触发条件和移除前提。

## 7. 错误、日志与审计

- API 响应使用统一错误结构。
- 成功响应固定 `code = 0`。
- API 返回的 `message` 和 `data.errors` 中用户可见错误必须使用简体中文。
- 不把第三方原始错误直接暴露给用户，必须映射为项目内部简体中文提示。
- 关键业务日志必须可串联 trace_id、actor、resource、provider、operation。
- **日志统一不脱敏**：日志需输出完整信息，包含 secret、token、password、私钥、完整证件号等，便于排查与追溯（与 `AGENTS.md` 口径一致）。脱敏只发生在面向前端的 API 响应层，不发生在日志层。
- 财务和审计相关写入必须记录操作者、来源、IP、trace_id 或等价字段。
- 设置、供应商、支付、实名、上游等配置和凭据输出必须使用白名单 Resource/DTO；`api_key`、secret、token、私钥不得出现在 API 响应。
- 敏感配置更新采用空值保留、非空替换，不能用掩码值或空字符串覆盖真实配置。
- 插件配置中的敏感项必须走加密存储和脱敏预览；更新时空值表示保留旧值，不得覆盖为掩码或空字符串。
- `payments` 表为财务审计边界，任何维护命令、清理命令和测试不得物理删除 Payment 记录；只能输出只读报告、标记异常或跳过处理。
- `balance_logs` 等财务流水也属于审计链路，不得用物理删除掩盖撤销、恢复或退款，必须使用反向流水、状态投影或明确补偿状态。
- 支付宝原路退款、余额退款、混合支付余额恢复必须同步账单退款投影字段，收入统计必须按净额计入（实收 − 累计已完成退款，红字账单不参与收入统计）。**当前口径：只有一条退款出口——退款到余额**（`refund_method=balance`，管理员入口 `POST /v2/admin/invoices/{id}/refunds`），支付插件层无退款能力与契约，不得出现「原路退款」标签或 `refund_method=original` 分支。**2026-10-20 裁决：纯标记通道 `refund_method=manual_mark` 已整体移除**（路由、`InvoiceRefundMarkService`、互斥守卫一并删除），历史 `funds_moved=0` 行只读保留，不回填、不删除；累计退款口径（真实退款与历史纯标记都计入）见 `FinanceRefund` 模型注释。详见 `docs/参考资料/后端/财务单据生成规则.md` §6.2。
- 管理员余额退款（`refundInvoiceToBalance`）必须走资金三唯一写口（余额经 `AccountService`、台账经 `FinanceLedgerService`、单据经 `FinanceDocumentService`），并在事务内持有账单行锁与用户行锁；充值/补录账单不得退回余额（资金从未离开系统），推广奖励可提余额不足时先阻断。
- 支付和实名回调必须在路由层先过签名中间件，Service 层保留二次验签、金额/状态校验、幂等和事务锁。
- VNC、支付、实名、上游等日志不脱敏，保留完整 token、sign、password、secret 以便排查。
- 公开 VNC token 采用**幂等消费**（同一 token 在 TTL 内可重复兑换，不要求一次性消费）、短 TTL、绑定必要上下文；兑换端点 Origin 校验保留「空 Origin 放行」以兼容同源与非浏览器客户端；不得在公开解析响应中返回 VNC 密码、原始 token 或完整 URL。
- 用户端账单详情为白名单输出（`ClientInvoiceDetailResource`）：不得返回 `callback_raw`、`trace_id`、成本/佣金/结算类等管理端内部字段；支付流水级 `trade_no`（第三方订单号）为控制台既有展示口径（用户对账需要），不属于泄露面（2026-10-10 裁决：客户端账单详情 `trade_no` 维持现状并以此条款消解 §9 字面冲突）。

## 8. 审查闭环与防漏审

后端体检、重构审查和第三方集成审查必须输出明确报告，不允许只做内部检查后不报告。

强制要求：

- 输出审查范围矩阵，列出目录、模块、接口、风险域、是否已覆盖。
- 每个 Blocker / High 必须有文件路径、方法名、路由或命令证据。
- 每个审查维度必须标注“已检查 / 未覆盖 / 需进一步验证”。
- 未覆盖项必须说明原因、风险和后续补查建议。
- 审查发现必须按严重级别排序并给修复优先级。
- 发现无问题的维度也必须写“未发现问题”和检查依据。
- 报告末尾必须包含完整性自检表。

完整性自检表至少检查：

- 是否读取 `AGENTS.md`。
- 是否以当前代码和真实路由为主。
- 是否检查 HARD-GATE。
- 是否检查 API 请求/响应工程化。
- 是否检查第三方插件化。
- 是否检查简体中文体验。
- 是否列出未覆盖项。
- 是否给出修复优先级。
- 是否给出文档/skills 同步建议。

## 9. 测试标准

必须优先补齐：

- 支付创建、回调、重复回调、失败回调。
- 余额扣减、并发扣减、退款或撤销。
- 实名认证成功、失败、供应商异常、字段异常。
- ZJMF 财务/ZJMF 云上游开通、续费、暂停、重启、同步失败。
- 权限码缺失、水平越权、用户资源归属。
- Provider fake 驱动下的本地可重复测试。
- 敏感配置读取/编辑不泄露原值。
- 供应商列表、详情、创建、更新响应不包含 `api_key` 原值。
- 公开 VNC token 不返回密码、过期失效（幂等兑换与日志口径以 §7 为准）。
- 用户端账单详情不返回管理端内部字段（`callback_raw`/`trace_id`/成本佣金类）；支付流水级 `trade_no`（第三方订单号）为控制台既有展示口径，不在此限（见 §7）。
- 维护命令不会删除 `payments`。
- 余额恢复和财务反转不会删除原始 `balance_logs`，保留反向流水或补偿审计记录。
- 支付原路退款覆盖成功、失败、重复退款、金额不一致和事务回滚。
- 收入统计不把已全额退款账单计入正向收入；部分退款按净额计入（实收 − 累计已完成退款），退款红字账单（type=refund）不参与收入统计以避免与净额扣减双计（2026-10-11 口径，红字冲抵的旧「负数冲抵」机制由净额口径替代）。
- 关键 Job 失败不吞异常，能够进入重试、failed_jobs 或明确补偿状态。

后端改动默认执行：

```cmd
cd backend
composer analyse
composer format:check
composer check:strict-types
```

## 10. 文档与 skills 同步

触发以下变化时，必须同步项目文档和 caiwu 相关 skills：

- 新增或调整后端分层规则。
- 新增第三方 provider 或调整 provider 接入方式。
- 修改支付、实名、ZJMF 财务/ZJMF 云、短信、对象存储等集成边界。
- 修改 API 响应格式、错误码、分页结构、权限码。
- 修改 API 请求参数、响应字段、Resource、FormRequest、Request DTO、Response DTO。
- 修改 API 错误 `message`、前端用户可见提示、字段校验错误等简体中文体验规则。
- 修改审查闭环、防漏审、防审完不报规则。
- 修改队列、调度、回调、部署假设。
- 新增必须遵守的注释、日志、测试规则。
- 当前代码检查结果证明既有开发文档、开发规范或约束文档已经失真。

同步位置：

- 项目规则：`AGENTS.md`，仅在规则升级且确认为仓库级硬约束时更新。
- 后端文档：`docs/BACKEND.md`。
- API 格式：`docs/参考资料/接口/API格式规范.md`。
- API 分组导航：`docs/参考资料/接口/API清单导航.md`。
- caiwu 主技能：本机 `~/.agents/skills/caiwu/SKILL.md`。
- caiwu 后端审查技能：本机 `~/.agents/skills/caiwu/code-review/SKILL.md`。
- caiwu reference：本机 `~/.agents/skills/caiwu/references/11-backend-engineering-governance.md`（以上均为本机路径，仅描述安装位置，跨机不可直接引用）。

禁止手工编辑：

- `docs/自动生成/接口/后端API清单.md`，该文件由脚本生成。

同步原则：

1. 当前代码检查结论优先于旧文档描述。
2. 文档更新必须标明新的真实约束、适用范围和不再可信的旧口径。
3. 不确定的内容标注“需进一步验证”，不要为了补齐文档而编造规则。
4. 对规则类文档的更新必须尽量小而准，避免把临时实现误写成长期规范。
