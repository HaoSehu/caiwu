# Laravel 12 后端完整体检 GOAL 提示词

本文档用于通过 `/goal` 模式启动一次完整的 caiwu Laravel 12 后端体检。执行者应读取本文档并严格按本文档完成审查、报告与修复方案设计。

## GOAL

你现在是一个 Laravel 12 后端完整体检团队的主协调代理。请对当前项目 `C:/Users/USER125536/Desktop/caiwu/backend` 做一次完整、专业、可落地的后端体检，并输出完整体检报告与修复方案。

本次任务的核心不是立即改代码，而是形成一份足够具体、可执行、能指导后续修复的专业体检报告。除非用户另行明确要求进入修复阶段，否则不要直接改业务代码。

重要前提：当前开发文档、开发规范以及其他约束性文档不具备完全真实性。必须以本次对当前代码、真实路由、可观察行为和必要受控验证的检查结果为主；发现文档与代码不一致时，应记录冲突，并在报告中提出需要更新补充的文档和 skills。

## 一、角色与目标

你需要站在以下角色视角综合审查：

- 后端工程师：业务实现、接口规范、代码质量、可维护性。
- Laravel 框架工程师：Laravel 12 最佳实践、分层、Service/FormRequest/Resource/Job 使用。
- 架构工程师：模块边界、领域划分、复杂度、长期演进风险。
- 安全工程师：认证、授权、越权、注入、回调验签、敏感信息保护。
- 数据库工程师：查询性能、索引、事务、锁、幂等、数据一致性。
- 支付/财务工程师：账单、支付、余额、审计、金额精度、并发扣款。
- 第三方集成工程师：上游接口、魔方财务/魔方云、超时重试、失败补偿。
- 运维工程师：队列、调度、日志、配置、部署假设、可观测性。
- 测试工程师：测试覆盖、回归风险、验证命令、缺失测试建议。
- 工程化治理工程师：分层规范、目录边界、接口抽象、注释规范、文档与 skills 同步。
- API 工程化工程师：请求参数归一化、FormRequest、Resource/DTO、统一响应、错误映射、兼容字段治理。
- 语言体验工程师：后端注释、API `message`、前端页面提示、错误提示、空状态和操作反馈的简体中文一致性。
- 审查质量控制工程师：防错审、防漏审、防审完不报，建立范围矩阵、证据链和强制报告闭环。

最终目标：

1. 完整体检当前 Laravel 12 后端。
2. 找出架构、业务、安全、性能、集成、运维、测试等问题。
3. 按严重级别输出问题清单。
4. 给出可执行的修复方案、优先级、影响面和验证方式。
5. 专项评估第三方集成的耦合问题，并设计插件化、可替换、可复用的接入方案。
6. 明确哪些问题必须立即修、哪些可以渐进治理、哪些不建议本轮动。
7. 输出后端规范化、工程化、必要注释和人工维护友好性的治理方案。
8. 明确需要同步更新的 caiwu skills 和项目文档。
9. 生成一份完整 Markdown 体检报告。

## 二、允许创建专业子代理

允许创建多个专业性功能子代理并行审查，但必须由主协调代理统一收敛结论。

建议子代理：

1. `backend-architecture-reviewer`
   - 检查 Controller / FormRequest / Service / Model / Resource / Job 分层。
   - 检查模块边界、重复逻辑、胖 Controller、万能 Service。

2. `laravel-framework-reviewer`
   - 检查 Laravel 12 使用方式。
   - 检查中间件、请求校验、资源响应、异常处理、队列、调度。

3. `security-reviewer`
   - 检查认证、授权、越权、SQL 注入、SSRF、文件上传、敏感日志、回调验签。

4. `database-performance-reviewer`
   - 检查 N+1、循环查询、索引风险、事务范围、锁、并发一致性。

5. `finance-payment-reviewer`
   - 检查账单、支付、余额、充值、优惠券、审计、金额精度、幂等。

6. `upstream-integration-reviewer`
   - 检查魔方财务/魔方云、上游调用封装、超时、重试、失败补偿、provider key。

7. `queue-ops-reviewer`
   - 检查 Job、Schedule、日志、部署假设、`schedule:run`、失败任务处理。

8. `test-quality-reviewer`
   - 检查现有测试覆盖、缺失测试、高风险链路验证方式。

9. `third-party-plugin-architect`
   - 专项审查支付、实名认证、魔方财务、短信、邮件、对象存储、上游服务等第三方集成。
   - 设计统一接口、驱动注册、配置隔离、能力探测、异常映射、插件切换与灰度迁移方案。
   - 输出如何把现有第三方集成剥离成“插件/驱动/适配器”形式，保证后续可随时替换供应商。

10. `backend-engineering-governance-reviewer`
   - 专项审查后端工程化程度，包括目录边界、分层一致性、接口抽象、DTO、异常、日志、测试入口。
   - 检查哪些代码不利于人工维护，哪些地方需要必要注释。
   - 输出应同步更新的项目文档和 caiwu skills 清单。

11. `api-contract-engineering-reviewer`
   - 专项审查 API 请求和响应工程化。
   - 检查 FormRequest、Request DTO、Resource、Response DTO、统一错误、分页、字段命名、兼容字段和前端消费边界。
   - 输出需要更新的 API 文档，优先更新 `文档/后端/API格式规范.md`，禁止手工编辑自动生成的 `文档/后端/后端API清单.md`。

12. `language-experience-reviewer`
   - 专项审查简体中文体验。
   - 检查代码注释、API 错误 `message`、前端页面提示、表单校验、确认框、通知、空状态、加载态、操作结果提示是否使用简体中文。
   - 检查是否直接展示英文异常、第三方原始错误、供应商错误码、后端堆栈或未翻译字段名。

13. `review-quality-controller`
   - 专项防止错审、漏审、审完不报。
   - 建立审查范围矩阵、文件证据清单、未覆盖项清单、交叉复核清单和最终报告门禁。
   - 确保所有子代理结论都被主报告吸收或标注为已排除。

每个子代理的输出必须包含：

- 审查范围。
- 关键发现。
- 文件路径和方法名。
- 风险等级。
- 修复建议。
- 对其他模块的影响。
- 建议验证方式。

要求：

- 每个子代理必须给出明确发现、文件路径、风险说明、修复建议。
- 主协调代理必须去重、合并冲突结论，并形成最终报告。
- 子代理不得执行数据库初始化、迁移、历史迁移补跑或破坏性命令。
- 子代理不得泄露 `.env`、密钥、token、密码等敏感值。
- 最终只输出一份统一报告，不要散落多份互相冲突的结论。
- 第三方插件化方案必须兼容现有业务，不允许为了抽象而破坏当前支付、实名认证、上游开通等核心流程。

主协调代理必须额外完成：

- 合并重复问题。
- 识别子代理结论冲突。
- 将同一根因下的多个表现归并成一个修复项。
- 输出最终优先级。
- 给出是否进入修复阶段的判断。

## 三、项目背景

- 项目：caiwu，IDC/云服务业务平台。
- 后端目录：`backend`。
- 技术栈：Laravel 12 + PHP 8.2 + Sanctum 4 + MySQL 8。
- 队列：`database` 驱动，并入 `schedule:run`。
- 缓存：Redis。
- 会话：file。
- 鉴权：Sanctum Token。
- 权限：`permission:{code}` 中间件。
- 本地后端启动入口：`php artisan app:serve`。
- 当前工作目录：`C:/Users/USER125536/Desktop/caiwu`。

## 四、硬性约束

必须遵守：

1. 先阅读根目录 `AGENTS.md`。
2. 不使用 PowerShell；需要命令时使用 `cmd`，复杂脚本使用 Python。
3. 不允许执行数据库初始化。
4. 不允许执行数据库迁移。
5. 不允许补跑历史迁移。
6. 不允许修改真实 `.env` 密钥。
7. 不允许在报告中输出 secret、token、password、私钥等敏感值。
8. 不允许回滚不是你造成的改动。
9. 不允许用 `php artisan serve` 替代 `php artisan app:serve`。
10. 不允许手工编辑 `文档/后端/后端API清单.md`。
11. 体检阶段以只读审查为主；如需修复，先输出方案、影响面和验证命令。
12. 如发现必须查询数据库才能确认的问题，只能写入“需用户授权后查询”，不得擅自执行破坏性操作。

## 五、事实来源优先级

按以下顺序建立事实：

1. 当前代码、真实路由、当前配置、可观察行为。
2. `AGENTS.md`。
3. `开发规范.md`。
4. `文档/架构/架构现状说明.md`。
5. `文档/后端/API格式规范.md`。
6. `文档/数据库/当前数据库结构.md`。
7. 其他文档仅作补充。

规则与当前稳定代码冲突时，先以当前代码行为为准，并在报告中说明冲突、推荐更新位置和更新内容。

## 六、执行流程

按以下阶段执行，不要跳步：

1. `READ_RULES`：读取 `AGENTS.md`、后端规范、架构说明、API 格式规范。
2. `MAP_BACKEND`：梳理后端目录、路由、核心 Service、关键业务链路。
3. `SPAWN_EXPERTS`：按需要创建专业子代理并行审查。
4. `CHECK_HARD_GATE`：优先检查 HARD-GATE，发现即记录。
5. `DEEP_REVIEW`：按架构、业务、安全、数据库、第三方、队列、测试逐项深挖。
6. `PLUGIN_REDESIGN`：专项输出第三方插件化改造方案。
7. `MERGE_FINDINGS`：归并问题、去重、排序、标严重级别。
8. `WRITE_REPORT`：输出最终 Markdown 报告。
9. `SELF_REVIEW`：对报告进行自检，确认不空泛、不泄密、不越权、不包含未验证的断言。
10. `DOC_SYNC_PLAN`：基于当前检查结论列出需要更新补充的开发文档、规范文档、项目文档和 caiwu skills。

## 七、必须优先检查的 HARD-GATE

以下问题一旦存在，必须标为 Blocker 或 High：

1. Controller 不薄，包含大量业务逻辑。
2. Controller 直接调用 `Http::*` 或第三方接口。
3. 第三方/上游调用没有走 `app/Services` 专用客户端。
4. `mofang_finance_api` 被错误归一化或别名成 `hosting_panel_api`。
5. 魔方财务/魔方云差异没有收敛到 Mofang 中间层。
6. 回调接口缺少签名中间件。
7. 回调处理不幂等。
8. 回调没有关键日志。
9. 财务、账单、支付、余额、审计写入缺少事务。
10. 金额计算存在浮点精度风险。
11. Payment 记录被用于余额支付、免费订单、管理员手动开服等非第三方真实资金流入。
12. 存在物理删除 `payments` 历史记录的逻辑。
13. 存在水平越权或垂直越权。
14. 存在 SQL 注入、SSRF、文件上传路径穿越等安全风险。
15. 硬编码后端地址、token 键名、权限码、状态文案、密钥。
16. 存在生产常驻 `queue:work` 的部署假设。
17. 引用或补跑历史激进迁移。
18. 接口响应结构破坏现有前端兼容性。
19. 第三方集成强耦合到 Controller、业务 Service 或具体供应商字段，导致无法替换供应商。
20. 支付、实名认证、魔方财务等第三方集成没有统一接口、统一异常、统一配置、统一日志和统一测试替身。
21. 后端代码缺少稳定分层规范，导致人工开发只能靠猜测维护。
22. 关键业务、第三方协议、幂等、事务、历史兼容缺少必要注释。
23. 后端规则变化后未同步项目文档和 caiwu 相关 skills。
24. API 请求参数读取、类型转换、默认值、筛选排序、分页归一化散落在 Controller 中。
25. API 响应字段、错误格式、第三方原始响应或敏感字段直接穿透到前端。
26. API 规则变化后未更新 `文档/后端/API格式规范.md`。
27. API 错误 `message`、`data.errors` 或前端用户可见提示不是简体中文。
28. 前端直接展示英文异常、第三方原始错误、供应商错误码、后端堆栈或未翻译字段名。
29. 审查没有输出范围矩阵、证据引用、未覆盖项和最终报告，导致错审、漏审或审完不报。

## 八、审查范围

至少审查：

- `backend/app/Http/Controllers`
- `backend/app/Http/Requests`
- `backend/app/Http/Resources`
- `backend/app/Services`
- `backend/app/Models`
- `backend/app/Jobs`
- `backend/app/Listeners`
- `backend/app/Console`
- `backend/app/Constants`
- `backend/app/Support`
- `backend/routes`
- `backend/config`
- `backend/database/migrations`，只审查风险，不执行迁移。
- `backend/tests`

重点业务域：

- 登录认证
- 管理员权限
- 用户权限
- 账单 Invoice
- 支付 Payment
- 余额 Balance
- 充值 Recharge
- 优惠券 Coupon
- 服务实例 Service Instance
- 上游开通、续费、暂停、重启
- 工单 Ticket
- 内容 Content
- 系统配置 Setting
- 回调 Callback / Webhook
- 队列 Job / 调度 Schedule
- 第三方插件化架构
- 后端规范化与工程化治理
- API 请求与响应工程化
- 必要注释治理
- 简体中文用户体验
- 防错审、漏审、审完不报机制
- 项目文档与 caiwu skills 同步
- 支付网关接入
- 实名认证服务接入
- 魔方财务/魔方云接入
- 短信、邮件、对象存储等可替换外部服务

## 九、建议搜索命令

可使用 `rg` 搜索风险点：

```cmd
cd backend
rg -n "Http::" app routes config
rg -n "DB::raw|whereRaw|orderByRaw|selectRaw" app routes
rg -n "forceDelete|->delete\(|truncate\(" app routes database
rg -n "payments|Payment" app routes database tests
rg -n "callback|webhook|notify" app routes
rg -n "queue:work|schedule:run|withoutOverlapping" app config routes
rg -n "mofang_finance_api|hosting_panel_api|Mofang" app config routes
rg -n "Alipay|Wechat|PaymentGateway|RealName|Identity|Verify|Sms|Mail|Cos|Oss" app config routes tests
rg -n "interface .*Gateway|interface .*Provider|implements .*Gateway|implements .*Provider" app
rg -n "TODO|FIXME|兼容|幂等|事务|锁|trace_id|provider|DTO|Contract|Interface" app tests
rg -n "validated\\(|input\\(|query\\(|all\\(|only\\(|Resource|JsonResource|ApiResponseBuilder|BusinessException" app/Http app/Services tests
rg -n "message|errors|Exception|Failed|Error|Unknown|success|warning|placeholder|title|label|empty|loading" app resources routes tests ..\\frontend-admin\\src ..\\frontend-admin-v3\\src ..\\frontend-client\\src
rg -n "env\(" app config
rg -n "validate\(" app/Http/Controllers
rg -n "fillable|guarded" app/Models
```

注意：

- 搜索只是入口，必须阅读关键代码确认。
- 不要只凭关键词下结论。
- 所有重要发现尽量给出文件路径和方法名。

## 十、审查维度

### 1. 架构分层

检查：

- Controller 是否只做参数接收、鉴权、调用 Service、返回响应。
- FormRequest 是否承担参数验证。
- Resource 是否负责响应转换。
- Service 是否按领域聚合。
- Model 是否没有塞入过多业务流程。
- Job/Listener/Command 是否只做编排，具体逻辑委托 Service。
- 是否存在重复实现、跨模块直接依赖、临时式代码堆叠。

### 2. API 规范

检查：

- 路由命名是否清晰。
- HTTP method 是否符合语义。
- 是否使用统一响应格式。
- 成功响应是否 `code = 0`。
- 分页是否符合 `list / total / page / page_size`。
- 错误码与错误信息是否一致。
- 权限中间件是否完整。
- 对外是否统一使用“账单 Invoice”，避免混乱使用“订单 Order”。
- 是否破坏现有前端消费格式。

### 3. 业务正确性

检查：

- 账单、支付、余额状态流转是否合法。
- 支付回调是否可重复接收且无副作用。
- 服务开通、续费、暂停、重启是否有失败补偿。
- 优惠券、余额扣减是否有并发保护。
- 金额计算是否使用整数分或 bcmath。
- 审计字段是否完整。
- 操作来源 `operator_*`、`actor_*`、`trace_id`、`ip_address` 是否延续。

### 4. 数据库与性能

检查：

- N+1 查询。
- 循环内查库/写库。
- 大表查询缺索引。
- 不必要的 `select *`。
- 事务范围过大。
- 写操作缺少锁。
- 软删除处理不一致。
- 唯一性约束只靠应用层判断。
- 历史迁移风险。

### 5. 安全

检查：

- Sanctum Token 是否分端正确。
- 权限码是否缺失或硬编码。
- 是否存在水平越权。
- 是否存在垂直越权。
- 是否存在 SQL 注入。
- 是否存在 SSRF。
- 文件上传是否安全。
- 批量赋值是否过宽。
- 日志和响应是否泄露敏感信息。
- 回调签名是否严格验证。

### 6. 上游与第三方集成

检查：

- 上游调用是否封装在 `app/Services`。
- 是否设置 timeout、retry、异常处理。
- 上游失败是否可观测、可补偿。
- 魔方财务/魔方云差异是否隔离。
- provider key 是否保持真实值。
- 是否把上游协议细节泄漏到 Controller。

### 7. 第三方插件化与可替换架构

这是本次体检的重点专项。必须评估支付、实名认证、魔方财务、短信、邮件、对象存储、上游服务等第三方能力是否已经具备可替换性。

检查：

- 是否存在统一接口，例如 `PaymentGatewayInterface`、`IdentityVerificationProviderInterface`、`UpstreamProviderInterface`。
- 业务 Service 是否依赖抽象接口，而不是依赖支付宝、微信、魔方财务等具体 SDK 或具体字段。
- 是否有驱动注册机制，例如 `GatewayManager`、`ProviderManager`、Laravel Container binding、配置驱动映射。
- 配置是否按 provider 隔离，避免一个供应商配置散落在 `.env`、settings、业务代码中。
- 第三方错误是否映射成项目内部统一异常和错误码。
- 第三方回调是否先进入统一入口，再分发给具体 provider 处理。
- 第三方请求和响应日志是否结构一致，并做敏感字段脱敏。
- 是否支持 capability 能力探测，例如某 provider 是否支持退款、实名二要素、三要素、服务续费、服务暂停。
- 是否支持无侵入切换供应商，例如从支付宝切换到其他支付网关，从当前实名认证供应商切换到新供应商。
- 是否有测试替身 fake provider / mock gateway，支持不访问真实第三方即可跑自动化测试。
- 是否有插件生命周期设计：启用、禁用、配置校验、健康检查、回调验签、失败重试、审计记录。
- 是否能在不改业务 Controller 的情况下新增一个第三方插件。
- 是否避免把所有第三方强行塞进一个“大而全插件接口”，应按能力域拆分接口。
- 是否有统一 DTO，避免业务层直接依赖第三方原始响应数组。
- 是否有 provider 版本和迁移策略，避免切换供应商时破坏历史数据解释。

必须输出插件化改造建议：

- 当前耦合点清单。
- 推荐接口定义。
- 推荐目录结构。
- 推荐配置结构。
- 推荐异常和日志规范。
- 推荐迁移步骤。
- 推荐测试策略。
- 不建议改动的边界。

### 8. 队列、调度、运维

检查：

- Job 是否实现 `ShouldQueue`。
- 是否设置 tries、timeout、backoff。
- 是否有失败处理。
- Schedule 是否有 `withoutOverlapping`。
- 是否错误假设生产常驻 `queue:work`。
- 日志是否能定位线上问题。
- 配置是否硬编码路径或环境。

### 9. 错误处理与日志

检查：

- 是否吞异常。
- 是否把技术异常暴露给用户。
- 是否使用统一错误响应。
- 日志级别是否合理。
- 关键业务是否有日志。
- 日志字段是否足够排查问题。
- 日志是否脱敏。

### 10. 测试质量

检查：

- 关键业务是否有 Feature Test。
- Service 是否有 Unit Test。
- 支付回调是否有幂等测试。
- 权限是否有越权测试。
- 金额并发是否有测试。
- 上游失败是否有测试。
- 当前测试是否可作为回归门槛。

### 11. 规范化、工程化与注释治理

这是本次体检的重点专项之一。必须评估后端是否适合后续人工长期开发维护。

检查：

- 目录结构是否稳定，新增业务是否有明确落点。
- Controller、FormRequest、Resource、Service、Model、Job、Listener、Command 分层是否一致。
- 是否存在临时功能堆叠、跨域调用、重复请求封装、重复状态判断。
- 是否有明确 Contracts、DTO、Exception、Enum/Constant、Manager/Registry 等工程化结构。
- 关键业务是否有足够注释说明“为什么”，尤其是财务、支付、回调、上游协议、历史兼容、并发锁、幂等键。
- 是否存在无意义注释、过时注释或泄露敏感信息的注释。
- 是否有后续人工开发需要遵守的目录、命名、注释、测试和文档更新规则。
- 后端规则变化是否需要同步到 `文档/后端/后端规范化工程化治理方案.md`、`AGENTS.md`、caiwu skills。

必须输出：

- 当前工程化短板。
- 规范化改造清单。
- 必要注释补充清单。
- 不应添加注释的位置。
- 后续人工开发维护规则。
- 需要同步更新的项目文档和 skills。

### 12. API 请求与响应工程化

必须专项检查 API 的请求入口和响应出口是否规范化、工程化。

检查：

- 复杂接口是否使用 FormRequest，Controller 是否仍大量手写 `$request->input()`、`query()`、`all()`。
- 请求字段、分页、排序、筛选、布尔、枚举、金额、日期范围是否统一归一化。
- 是否存在用户输入直接进入 `orderByRaw`、`whereRaw`、第三方请求体或 SQL 片段。
- 跨 Service 传递的复杂请求是否需要 Request DTO / Command。
- 响应是否通过统一响应方法、Resource 或 Response DTO 转换。
- Service 是否直接返回 HTTP Response。
- 第三方原始响应是否直接传给前端。
- 敏感字段是否被响应泄露。
- 错误响应是否统一业务码、HTTP 状态码、`message`、`data.errors`。
- 兼容字段是否说明保留原因和移除条件。
- API 规则变化是否需要更新 `文档/后端/API格式规范.md`。

必须输出：

- API 请求侧问题清单。
- API 响应侧问题清单。
- 需要迁移到 FormRequest / Resource / DTO 的接口清单。
- 需要补充或修正的 API 文档条目。
- 对前端调用的影响。

### 13. 简体中文体验一致性

必须专项检查后端注释、API 错误提示和前端用户可见文案。

检查：

- 后端新增或建议补充的注释是否使用简体中文。
- API 返回的 `message` 是否为简体中文。
- API 返回的 `data.errors` 中用户可见校验错误是否为简体中文。
- 第三方英文错误、繁体中文错误、供应商错误码、原始异常是否被映射为项目内部简体中文提示。
- 前端页面标题、按钮、表单标签、占位符、校验错误、接口错误提示、确认框、通知、空状态、加载态、操作结果提示是否全部为简体中文。
- 前端是否直接展示 `Error`、`Failed`、`Unknown error`、后端堆栈、供应商错误码或未翻译字段名。

必须输出：

- 非简体中文用户可见文案清单。
- API message 语言问题清单。
- 前端提示/错误提示语言问题清单。
- 建议统一替换文案。

### 14. 防错审、漏审、审完不报机制

必须建立审查质量门禁，避免错审、漏审或审完不报。

执行要求：

- 输出审查范围矩阵：目录、模块、接口、风险域、负责子代理、是否已覆盖。
- 每个 Blocker / High 必须带文件路径、方法名或路由证据；无法定位的必须说明原因。
- 每个审查维度必须给出“已检查 / 未覆盖 / 需进一步验证”状态。
- 对未覆盖范围必须列出原因、风险和后续补查建议。
- 主协调代理必须交叉复核子代理结论，处理重复、冲突和遗漏。
- 发现无问题的维度也必须写明“未发现问题”和检查依据，不能省略。
- 最终必须输出统一 Markdown 报告；不得只做内部审查不报告。
- 报告末尾必须包含“审查完整性自检表”。

审查完整性自检表至少包含：

- 是否读取 `AGENTS.md`。
- 是否读取当前代码和真实路由。
- 是否检查 HARD-GATE。
- 是否检查 API 请求/响应工程化。
- 是否检查第三方插件化。
- 是否检查简体中文体验。
- 是否列出未覆盖项。
- 是否输出修复优先级。
- 是否输出文档/skills 同步建议。
- 是否明确下一步是否进入修复阶段。

## 十一、第三方插件化目标架构要求

请在报告中额外给出“第三方集成插件化改造方案”。目标不是立即重写所有第三方逻辑，而是设计一套可渐进落地、兼容现有业务的插件化架构。

### 1. 设计目标

- 第三方能力以插件/驱动形式接入。
- 业务代码只依赖项目内部接口，不直接依赖具体供应商 SDK、字段和异常。
- 支持支付、实名认证、魔方财务、短信、邮件、对象存储等能力横向扩展。
- 支持通过配置切换 provider。
- 支持 provider 级健康检查、能力声明、日志追踪、异常映射和测试替身。
- 支持逐步迁移，不破坏当前线上流程。
- 支持同一能力多个 provider 并存，例如旧 provider 继续处理历史订单，新 provider 处理新请求。
- 支持通过配置、数据库 settings 或管理后台策略切换 provider，但必须说明推荐来源和安全边界。

### 2. 推荐抽象方向

至少评估是否需要以下接口或等价设计：

- `Contracts/Payments/PaymentGatewayInterface`
- `Contracts/Payments/RefundGatewayInterface`
- `Contracts/Identity/IdentityVerificationProviderInterface`
- `Contracts/Upstream/UpstreamProviderInterface`
- `Contracts/Notifications/SmsProviderInterface`
- `Contracts/Storage/ObjectStorageProviderInterface`
- `Services/Integrations/ProviderManager`
- `Services/Integrations/DriverRegistry`
- `Services/Integrations/Capability`
- `Services/Integrations/IntegrationException`

接口应关注项目内部业务语义，而不是照抄第三方字段。

接口草案至少说明：

- 输入 DTO。
- 输出 DTO。
- 业务错误类型。
- 是否需要幂等键。
- 是否需要回调验签。
- 是否需要异步任务。
- 日志字段。
- 测试 fake 的行为约定。

### 3. 推荐目录方向

审查后请结合当前项目给出最终建议。初始候选：

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
backend/tests/Fakes/Integrations/
```

### 4. 输出内容

报告必须包含：

- 当前第三方集成耦合点。
- 哪些集成应优先插件化。
- 插件化后的调用链。
- 接口草案。
- 配置草案。
- 回调入口设计。
- 异常映射设计。
- 日志与脱敏设计。
- fake provider 测试方案。
- 渐进迁移步骤。
- 风险和回滚方案。

### 5. 插件化反目标

不要提出以下方案：

- 一次性大重写所有第三方集成。
- 用一个万能 `PluginInterface` 包住所有支付、实名、上游、短信、存储能力。
- 让业务 Controller 直接选择具体 provider。
- 把第三方原始响应结构直接返回给业务层或前端。
- 为了抽象而删除现有稳定的支付、回调、上游开通链路。
- 没有 fake provider 和回归测试就切换真实供应商。

## 十二、严重级别定义

- Blocker：必须立即修复；存在安全、资金、数据破坏、HARD-GATE 违反或严重架构错误。
- High：高风险；建议本轮修复，否则可能导致线上事故或难以维护。
- Medium：明确问题；可排期修复。
- Low：低风险一致性、可维护性问题。
- Nit：不阻塞的小建议。

## 十三、输出报告要求

请生成 Markdown 报告，建议路径：

`文档/审查/后端完整体检报告-YYYY-MM-DD.md`

报告必须包含以下结构。

### 1. 体检结论

- 总体评级：A / B / C / D。
- 是否存在 Blocker。
- 是否建议立即进入修复。
- 最大风险摘要。
- 第三方集成插件化成熟度评级：A / B / C / D。
- 支付、实名认证、魔方财务三类集成的剥离优先级。
- 本次体检可信度。
- 未覆盖范围。

### 2. 审查范围

- 已审查目录。
- 已审查关键文件。
- 已审查业务模块。
- 已执行命令。
- 未执行命令及原因。

### 3. HARD-GATE 检查表

| 规则 | 结论 | 位置 | 风险 | 修复建议 |
|---|---|---|---|---|

### 4. 问题清单

#### Blocker

| 编号 | 问题 | 位置 | 影响 | 修复方案 | 验证方式 |
|---|---|---|---|---|---|

#### High

| 编号 | 问题 | 位置 | 影响 | 修复方案 | 验证方式 |
|---|---|---|---|---|---|

#### Medium

| 编号 | 问题 | 位置 | 影响 | 修复方案 | 验证方式 |
|---|---|---|---|---|---|

#### Low / Nit

| 编号 | 问题 | 位置 | 影响 | 修复方案 | 验证方式 |
|---|---|---|---|---|---|

### 5. 分维度体检详情

分别输出：

- 架构分层。
- API 规范。
- 业务逻辑。
- 数据库与性能。
- 安全。
- 上游与第三方集成。
- 第三方插件化与可替换架构。
- 规范化、工程化与注释治理。
- API 请求与响应工程化。
- 简体中文体验一致性。
- 防错审、漏审、审完不报机制。
- 队列调度。
- 错误处理与日志。
- 测试质量。

每个维度固定写：

- 当前现状。
- 发现的问题。
- 风险解释。
- 修复建议。
- 优先级。
- 推荐验证方式。

### 6. 修复路线图

#### 第一阶段：立即修复

- 目标。
- 涉及文件。
- 修复步骤。
- 风险。
- 验证命令。

#### 第二阶段：高风险收敛

同上。

#### 第三阶段：质量与可维护性优化

同上。

#### 第四阶段：第三方插件化改造

必须单独列出：

- 支付插件化。
- 实名认证插件化。
- 魔方财务/魔方云插件化。
- 其他第三方能力插件化。
- 每一类的接口、驱动、配置、测试、迁移步骤。
- 每一类的风险、回滚方案和不建议触碰的边界。

#### 第五阶段：规范化工程化治理

必须单独列出：

- 后端目录和分层治理。
- Contracts / DTO / Exception / Constant / Manager 的引入边界。
- 必要注释补充计划。
- 测试补齐计划。
- 需要更新的项目文档。
- 需要更新的 caiwu skills。

#### 第六阶段：API 请求响应工程化治理

必须单独列出：

- 需要迁移到 FormRequest 的接口。
- 需要迁移到 Resource / Response DTO 的接口。
- 请求参数归一化规则。
- 响应字段和错误码治理规则。
- 第三方响应隔离方案。
- 需要更新的 `文档/后端/API格式规范.md` 条目。

#### 第七阶段：简体中文体验治理

必须单独列出：

- API `message` 和 `data.errors` 简体中文治理。
- 前端页面提示和错误提示简体中文治理。
- 第三方原始错误映射策略。
- 需要更新的 `文档/后端/API格式规范.md` 和 `文档/前端/前端项目规范.md` 条目。

#### 第八阶段：审查质量门禁

必须单独列出：

- 审查范围矩阵。
- 证据引用清单。
- 未覆盖项清单。
- 子代理结论合并说明。
- 审查完整性自检表。

### 7. 建议补充测试

按测试类型列出：

- Feature Test。
- Unit Test。
- 回调幂等测试。
- 权限/越权测试。
- 金额并发测试。
- 上游失败测试。
- 队列/调度测试。

每条测试建议必须说明：

- 测试目标。
- 覆盖风险。
- 建议测试文件。
- 关键断言。

### 8. 验证建议

优先使用 targeted test。

如全量 `php artisan test` 存在历史基线失败，必须区分：

- 本次体检发现导致的失败。
- 仓库既有失败。
- 环境导致的失败。

建议命令：

```cmd
cd backend
php artisan test
```

如需要更小范围，给出具体命令，例如：

```cmd
cd backend
php artisan test --filter=Payment
php artisan test --filter=Invoice
php artisan test --filter=Service
```

### 9. 最终建议

必须明确回答：

1. 当前后端是否适合继续扩展新功能？
2. 是否存在必须先修的安全或资金风险？
3. 是否涉及数据库结构调整？
4. 是否涉及接口响应结构调整？
5. 是否涉及配置或部署调整？
6. 建议最先修复的 5 个问题是什么？
7. 第三方集成是否需要插件化重构？
8. 支付、实名认证、魔方财务应按什么顺序剥离？
9. 是否建议先做接口抽象再替换供应商？
10. 后端是否需要规范化、工程化治理？
11. 哪些注释是必须补充的？
12. 哪些项目文档和 caiwu skills 必须同步更新？
13. API 请求和响应是否需要工程化治理？
14. 现有 API 文档需要补充哪些条目？
15. API 错误 message 和前端提示是否全部符合简体中文要求？
16. 是否存在错审、漏审或审完不报风险？
17. 下一步是否应进入修复阶段？

## 十四、质量要求

- 报告必须具体，不要空泛。
- 所有 Blocker / High 必须尽量给文件路径。
- 修复方案必须可执行。
- 不要泄露敏感信息。
- 不要执行数据库初始化或迁移。
- 不要擅自做破坏性操作。
- 不要只列问题，还要给修复优先级和验证方式。
- 如果不能确认，写“需进一步验证”，不要编造结论。
- 插件化建议必须能渐进落地，不要只给抽象名词。
- 对第三方替换必须说明兼容历史数据、回调、账务和审计的办法。
- 规范化建议必须服务于人工维护，不要为了形式引入过度抽象。
- 注释建议必须解释业务原因和维护边界，不要要求机械式逐行注释。
- 后端注释、API 错误 message、前端页面提示和错误提示必须使用简体中文。
- 审查报告必须包含范围矩阵、证据引用、未覆盖项和完整性自检表，禁止审完不报。
