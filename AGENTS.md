# 项目规则

仓库级指令文件。目标：让代理避免常见错误、快速对齐现有约定。

> **阅读顺序**：规则按权重从高到低排列。优先服从靠前的规则，冲突时以权重高者为准。

## 速查索引

| 场景 | 去查 |
| --- | --- |
| 入项路线 | §六 操作参考 → 文档查阅路径 |
| 本地启动命令 | §六 操作参考 → 本地启动 / `启动指南.md` |
| 查接口 / 接口格式 | §六 操作参考 → 文档查阅路径 + `文档/开发文档/后端/后端API清单.md` |
| API 直接重构 | `文档/开发文档/后端/API直接重构方案.md` |
| 查表结构 | §四 开发规范 → 数据库与接口 / `文档/开发文档/数据库/当前数据库结构.md` |
| 设计新表 / 数据库设计 | `文档/开发文档/数据库/MySQL数据库设计提示词.md`（可直接喂给 AI） |
| 前端规范 | §四 开发规范 → 前端规则 / `文档/开发文档/前端/前端项目规范.md` |
| 后端规范 | §四 开发规范 → 后端规则 / `文档/开发文档/后端/API格式规范.md` |
| 视觉与页面结构 | §四 开发规范 → 视觉与交互 / `页面风格.md` |
| 验证命令 | §五 验证与交付 |
| 禁止项速览 | §一 执行铁律 → 禁止项 |
| 测试账号 | §六 操作参考 → 测试账号 |

---

# 一、执行铁律（最高权重）

每次行动必须遵守，无例外。

## 全局工作规范

不使用终端PowerShell改为使用终端CMD
终端默认使用 UTF-8
以瞎猜接口为耻，以认真查询为荣。
以模糊执行为耻，以寻求确认为荣。
以臆想业务为耻，以人类确认为荣。
以创造接口为耻，以复用现有为荣。
以跳过验证为耻，以主动测试为荣。
以破坏架构为耻，以遵循规范为荣。
以假装理解为耻，以诚实无知为荣。
以盲目修改为耻，以谨慎重构为荣。

## 1.1 执行原则

- 能自行判断的直接执行，不要中途停下来等用户确认。
- 多个子任务按依赖顺序依次完成，全部完成后输出总结。
- 多步骤改造先形成执行计划，按当前代码和文档自动审查计划，修正明显遗漏或冲突后再动手。
- 每完成一个子任务都执行受影响范围的小测试；全部任务完成后执行本次改动涉及层的完整验证。测试失败先修复，再进入下一任务。
- 修改以最小必要范围为原则，不做无关重构。
- 新实现不做旧兼容：不保留废弃代码路径、不添加"过渡期"兼容层、不为了"万一以后用到"而保留旧代码。旧代码无调用方即删除，历史可从 git 追溯。
- 仓库可能处于脏工作区，禁止回滚不是自己造成的改动。
- 终端命令只需查看结果时，不得使用输出重定向（`>`）写入文件。需要保存临时内容统一放到系统临时目录（`$env:TEMP`），用完即删。
- 所有自己创建的临时文件（日志、调试输出、重定向产物等）在任务完成后必须清理删除。
- **每完成一个任务（或子任务）后，先向用户展示改动摘要并等待确认，用户确认后再执行 git commit 并 push 到 GitHub**。commit message 采用 Google 项目风格（如 `Fix:修复无效格式`、`Feat:新增导出功能`、`Refactor:重构用户模块`），格式为 `Type:中文描述`，多个子任务各自独立提交，不要攒到最后一次性提交。

## 1.2 禁止项

- 禁止无关文件顺手格式化或大面积重排。
- 禁止引入与现有体系重复的 UI 库、请求层、状态层（详见 §三 技术基线、§四 前端规则）。
- 禁止把管理端改成深色大屏风格（详见 §四 视觉与交互）。
- 禁止硬编码后端地址、token 键名、权限码和状态文案（详见 §三 技术基线、§四 前端规则、§四 后端规则）。
- 禁止删除已有可复用能力后再重写一遍（详见 §四 通用规则）。
- 禁止未说明影响面就修改接口响应结构或公共样式入口。
- 禁止为了 v2 重构修改旧接口契约；禁止在 v2 新接口中接受 `per_page` 或复用旧宽 Resource 作为默认输出（详见 §四 后端规则）。
- 禁止手工编辑 `文档/开发文档/后端/后端API清单.md`（自动生成）；改业务分组导航请改 `文档/开发文档/后端/API清单导航.md`，重生成跑 `php backend/scripts/export_api_inventory.php`。
- 禁止用 `php artisan serve` 替代 `php artisan app:serve`（详见 §六 本地启动）。
- 禁止在 Controller 里直接 `Http::*` 调上游或第三方（详见 §四 后端规则）。
- 禁止在生产常驻 `queue:work`（队列已并入 `schedule:run`，详见 §三 技术基线）。
- 禁止补跑历史激进迁移文件（详见 §四 数据库与接口）。
- 禁止物理删除 Payment 记录、禁止把 `zjmf_finance_api` 别名为 `hosting_panel_api`（详见 §四 后端规则）。
- 禁止管理端页面新增独立"头部说明卡片"（详见 §四 前端规则）。
- 禁止用户控制台财务记录页使用统计/指标卡片（详见 §四 前端规则）。
- 禁止插件注册系统级定时任务、系统级 API 路由或全局中间件（详见 §四 后端规则）。
- 禁止新增旧兼容层、保留已无调用方的废弃代码（详见 §一 执行原则）。
- 禁止随手在仓库根新建文档；规则类以外放 `文档/` 下对应子目录。
- 禁止使用输出重定向（`>` / `>>`）将命令结果写入仓库内的文件。仅查看结果时用管道或直接输出；确需落盘时写到系统临时目录（`$env:TEMP`）。
- 禁止在仓库任何目录下遗留临时文件、日志 dump、grep 重定向产物、空占位文件。会话结束前必须清理自己产生的所有临时文件。
- 禁止在非当前工作目录下盲目执行文件创建/写入操作；创建前先确认 `$PWD` 是否为目标目录。

---

# 二、裁决依据（高权重）

冲突时按以下顺序执行：

1. 本文件 `AGENTS.md`（权重从 §一 → §六 递减）
2. 编码始终 UTF-8
3. `页面风格.md`（视觉与页面结构）
4. `启动指南.md`（本地启动命令）
5. 当前代码中的既有实现
6. `文档/开发文档/架构/架构现状说明.md`（架构真源）
7. `开发规范.md` 与 `CLAUDE.md` 中不冲突的补充说明

规则与稳定运行中的现有代码冲突时，先以现有代码行为为准。

---

# 三、项目认知（中高权重）

先理解项目再动手。优先看E:\caiwu\文档

## 3.1 项目结构

- `backend`：Laravel 12 后端（PHP 8.2+），承载认证、支付、订单、账单、工单、服务实例、内容、优惠券等。
- `frontend-admin-v3`：当前管理端，Vue 3 + Vite + TypeScript + TDesign Vue Next（TDesign Starter for Vue Next 基底）。
- `frontend-user-v3-www`：当前官网与用户端入口，Vue 3 + Vite + Element Plus，承载官网、登录注册、用户中心与站点公开页面。
- `frontend-user-v4-console`：新版用户控制台，Vue 3 + Vite + TypeScript + TDesign Vue Next，承载 `/client/*` 控制台体验并逐步替代旧 Element Plus 控制台来源。
- `shared`：跨端共享状态映射、展示组件与通用配置。前端通过 `@shared` / `@caiwu/shared` 别名引用。
- `文档`：项目文档库，入口看 `文档/README.md` 与 `文档/目录说明.md`。
- `backend/scripts/`：维护脚本（`export_api_inventory.php` 等），不要放长期业务代码。
- `migration-output`：历史旧数据快照的临时输出目录。

> 不存在的目录不要引用：根目录无 `scripts/`、无 `.kiro/specs`、无 `frontend-admin`、无 `frontend-client`、无 `frontend-user-v3-console`。

## 3.2 技术基线

- 后端：PHP 8.2、**Laravel 12**（`composer.json` 为准）、Sanctum 4、MySQL 8。
- 前端：Vue 3、Composition API、Vite 6、Pinia、axios、Sass。
- 前端 UI 框架与图标：
  - `frontend-admin-v3`：TDesign Vue Next + `tdesign-icons-vue-next`，禁止混用 Element Plus
  - `frontend-user-v3-www`：Element Plus + `@element-plus/icons-vue`，不引入第二套 UI 库
  - `frontend-user-v4-console`：TDesign Vue Next + `tdesign-icons-vue-next`，禁止混用 Element Plus
- 本地地址统一 `127.0.0.1`，不要混用 `localhost`。
- 鉴权：管理端与用户端都走 Sanctum Token，Token 按 `admin_token` / `client_token` 分端存储，权限码走 `permission:{code}` 中间件。
- 缓存：Redis；队列：`database` 驱动，并入 `schedule:run` 消费；会话：`file`。
- 调度：生产由宝塔每分钟执行 `php artisan schedule:run`，队列消费同时被触发；异步任务最坏延迟约 60 秒。

---

# 四、开发规范（中权重）

各层怎么写代码。

## 4.1 通用规则

- 新增能力前先复用现有模块、服务、样式入口和共享配置。
- 业务代码按领域聚合，不按"临时功能"堆放。
- 禁止把业务判断、请求拼装、状态映射大段堆在页面模板里。
- 公共逻辑优先抽到同目录 `composables`、`utils`、`services` 或 `shared`。
- 状态展示复用 `shared/statusConfig.js`、`shared/extraStatusMaps.js`、`shared/components/StatusTag.vue`。

## 4.2 后端规则

- 控制器保持薄层：参数接收、鉴权、调用服务、返回响应。
- 参数校验用 `FormRequest`；响应用 `Resource` 和控制器基类。
- 统一通过 `App\Traits\ApiResponse` / `App\Support\ApiResponseBuilder` 返回 JSON。
- 成功响应固定 `code = 0`，不要自创成功码。
- 分页结构：`list`、`total`、`page`、`page_size`。
- API 直接重构按 `文档/开发文档/后端/API直接重构方案.md` 执行：旧接口冻结，新接口优先落到 `/api/v2/admin/*`、`/api/v2/client/*`、`/api/v2/site/*`。
- v2 新接口不做旧结构兼容，不继承 `per_page`、冗余字段、列表详情混用结构；旧接口只允许修安全漏洞和明确 bug。
- v2 新接口必须使用 FormRequest 和 Resource/Response DTO，列表与详情 Resource 分离，并补 Feature 测试、字段白名单测试和大响应防控测试。
- 业务逻辑放 `app/Services`，常量/枚举放 `app/Constants`。
- 支付、账单、订单等流程必须考虑事务、幂等和审计字段。
- Payment 记录（payments 表）只允许修改状态，禁止物理删除任何行（包括 gateway=balance/manual/free 的历史记录）。Payment 仅记录第三方支付网关真实资金流入（如支付宝充值、支付宝付商品）；余额支付、管理员手动开服、免费订单不产生 Payment。
- 操作来源沿用 `operator_*`、`actor_*`、`trace_id`、`ip_address`。
- 调用上游/第三方必须走 `app/Services` 下的专用客户端，**不要**在 Controller 里直接 `Http::*`。
- 上游 provider key 以真实 `suppliers.interface_type` 或服务绑定值为准，禁止把 `zjmf_finance_api` 归一化或别名成 `hosting_panel_api`。
- ZJMF 财务数据面对接与历史兼容能力必须收敛在 `backend/plugins/servers/zjmf_finance/` 插件及其能力服务中；核心不再承载 ZJMF 实现；通用主机面板协议只能保留共享传输与协议能力。
- 回调接口必须走签名中间件，业务处理必须幂等，必须落日志。
- 敏感配置走 `settings` 或 `.env`，不要硬编码。
- 插件功能做到最小化与系统接入：禁止在插件中注册系统级定时任务（`schedule`）、系统级 API 路由或全局中间件；插件的调度、API、业务逻辑全部收敛在插件自身目录内，通过插件自身的服务提供者按需注册，不污染全局命名空间与调度表。

## 4.3 前端规则

### 通用

- 统一 Vue 3 `script setup` + Composition API。
- UI 框架与图标：`frontend-admin-v3`、`frontend-user-v4-console` 用 TDesign Vue Next + `tdesign-icons-vue-next`；`frontend-user-v3-www` 用 Element Plus + `@element-plus/icons-vue`。各端禁止混用另一套 UI 库。
- 接口请求走各端 `src/utils/request.js`（或 `src/utils/request/`），不要直接新建 axios 实例。
- 认证存取走 `src/utils/auth.js`（或等价模块），不要直接操作 localStorage 键。
- 领域请求收敛到 `src/api/*`，视图层只消费明确的 API 方法。

### 管理端 `frontend-admin-v3`

- 工程基底：TDesign Starter for Vue Next（`Tencent/tdesign-vue-next-starter`）。
- 样式入口：`src/style/` 目录，沿用 Starter 自带的主题变量（`tvision-color`）和 Less 变量体系。
- 布局组件：`src/layouts/`，基于 Starter Admin Layout，包含侧边菜单、顶部栏、面包屑、用户菜单。
- 路由模块：`src/router/modules/`，按业务域拆分（`homepage.ts`、`user.ts` 等）。
- 状态管理：`src/store/modules/`，使用 Pinia + `pinia-plugin-persistedstate`。
- 权限控制：`src/permission.ts`（路由守卫）+ `src/store/modules/permission.ts`，权限码与旧管理端一致。
- 页面模板：`src/pages/` 下按领域组织，复用 Starter 的列表页、表单页、详情页模板模式。
- 管理端页面禁止新增独立的"头部说明卡片"（例如 `admin-page-head`、`*-hero`、`*-head` 这类只放模块名、标题、说明文案和少量按钮的顶部大卡片）。列表页直接从筛选区、指标区或表格卡片开始；详情页的返回、保存、刷新等必要操作使用紧凑工具栏。
- 禁止在 v3 中硬编码 Element Plus 组件或样式。

### 官网与用户入口 `frontend-user-v3-www`

- 可以比管理端更有视觉表现，但沿用现有 token、圆角、阴影和蓝色品牌体系。
- 样式复用 `src/assets/styles/variables.scss`、`global.scss` 和既有布局组件。
- 购买/结算/恢复流程优先复用 `src/domains/products/*`、`src/composables/*` 和现有 API 封装，不在页面模板里重写流程。

### 用户控制台 `frontend-user-v4-console`

- 页面放在 `src/pages/client/`，业务逻辑优先收敛到 `src/domains/`、`src/composables/`、`src/api/`。
- 复用 `shared/user-v3` 的控制台基础组件（如 `PageScaffold`、`DataState`、`StatusTag`）和 `@caiwu/shared` 状态/运行时能力。
- 控制台是高频业务界面，保持浅色、克制、信息密度合理，不做官网式 Hero 或装饰优先布局。
- 用户控制台财务记录页面（账单列表、订单列表、充值记录列表及各自详情页）**禁止使用统计/指标卡片**（即顶部横向排列的数字汇总卡片行）。列表页直接从筛选区或快捷标签开始；详情页直接进入信息展示区。
- 控制台页面间距：页面根元素使用 `padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l)`（均为 12px），叠加 Starter 布局层的 12px，卡片边缘距屏幕 24px、卡片内容距屏幕 36px。新增页面沿用此结构，不要自造间距值。
- 手机端（`max-width: @screen-sm-max`）所有页面 padding 必须统一为 12px，禁止使用 `paddingLR-s`（8px）或 `paddingTB-m`（10px）。

## 4.4 数据库与接口

- 表名、字段名 `snake_case`。
- 迁移必须新增文件，不改历史迁移。
- 财务/审计表变更必须考虑索引、回填、兼容旧数据和回滚。
- 仓库里存在早期激进合并方向的历史迁移文件，**不要补跑**。
- 真实表结构以实库 `information_schema` 为准；`文档/开发文档/数据库/当前数据库结构.md` 是当前结构摘要。
- 新增表/字段设计遵循 `文档/开发文档/数据库/MySQL数据库设计提示词.md`，优先复用现有表而非建新表。
- 数据库重构方向见 `文档/开发文档/架构/架构现状说明.md`。
- 初始化新库：`backend/scripts/install_db.py`；迁移旧库：`backend/scripts/migrate_legacy_dump.py`。

## 4.5 视觉与交互

- 后台：浅灰背景、白色卡片、蓝色主操作，和 `页面风格.md` 一致。
- 状态展示：浅底标签，不直接用高饱和纯色文本。
- 新增样式先复用变量文件中的 token，不要手写第二套。
- 登录页/官网首页可以更有表现力，但不破坏整体品牌。

### 反模板视觉风格（用户明确要求视觉设计时启用）

- 设计目标不是标准 SaaS 模板，而是有触感、有审美立场的页面。
- 允许打破对称，保留轻微偏移和留白张力。
- 页面必须有材质感（噪点、渐变、纸感纹理）。
- 色彩：深灰+橙色、炭黑+米白+焦橙，避免蓝紫体系。
- 文案直白有人味，不写"赋能""闭环"这类空话。

#### 反模板禁止清单

- 禁止紫色/靛蓝渐变、纯平背景色、Hero+三卡片公式、整页完美居中。
- 禁止 Emoji 做功能图标、`ease-in-out` 做默认动画曲线。

---

# 五、验证与交付（中高权重）

写完必须检查。

- 只改文档：自检内容与仓库现状一致。
- 改 `frontend-admin-v3`：执行 `npm run build`（含 `vue-tsc --noEmit`）。
- 改 `frontend-user-v3-www`：执行 `npm run build`；涉及重构收口范围再执行 `npm run verify:refactor`。
- 改 `frontend-user-v4-console`：执行 `npm run build`；涉及重构收口范围再执行 `npm run verify:refactor`。
- 改 `backend`：执行 `php artisan test`，必要时缩小到受影响测试文件。
- 多步骤开发：子任务完成后先跑最小相关测试，最后再跑完整受影响验证；失败必须修复后继续。
- 无法运行验证时，在总结中说明原因。

---

# 六、操作参考（低权重）

需要时查阅。

## 6.1 本地启动

### 后端

```bash
cd backend
php artisan app:serve
# 需要带调度时：
php artisan app:serve --with-schedule
```

`app:serve` 同时拉起 HTTP、VNC Relay、Queue Worker。**不要**用 `php artisan serve` 替代。

本地 PHP 路径：`D:\BtSoft\php\83`（PHP 8.3）。

### 前端

```bash
# 管理端（127.0.0.1:5175）
cd frontend-admin-v3 && npm run dev

# 官网/用户入口（端口以 vite 配置为准）
cd frontend-user-v3-www && npm run dev

# 用户控制台（端口以 vite 配置为准）
cd frontend-user-v4-console && npm run dev
```

联调时统一 `127.0.0.1`，不要混用 `localhost`。

## 6.2 文档查阅路径

- **新人入项**：`AGENTS.md` → `启动指南.md` → `开发规范.md` → `页面风格.md` → `文档/README.md` → `文档/开发文档/架构/架构现状说明.md` → `文档/目录说明.md`。
- **查接口**：`api-docs/README.md` 与 `api-docs/` 单接口文档（现有接口快照）+ `文档/开发文档/后端/后端API清单.md`（精确）+ `文档/开发文档/后端/API清单导航.md`（业务分组）。
- **查接口格式**：`文档/开发文档/后端/API格式规范.md`。
- **API 直接重构**：先读 `文档/开发文档/后端/API直接重构方案.md`，再回查 `api-docs/`、路由、Controller、FormRequest、Resource、Service；旧接口冻结，新接口按 v2 规范新建。
- **查表结构**：先看 `文档/开发文档/数据库/当前数据库结构.md`；疑难以实库 `information_schema` 与 `文档/开发文档/架构/架构现状说明.md` 为准。
- **设计新表**：把 `文档/开发文档/数据库/MySQL数据库设计提示词.md` 全文喂给 AI，后面跟上需求描述。
- **上游对接**：`文档/开发文档/集成/本地对接说明.md`。
- **部署与调度**：`文档/开发文档/部署与调度指南.md`。
- **回溯旧方案**：直接查 `git` 历史，`文档/` 目录不保留历史副本。

## 6.3 测试账号

- 用户：`2908990438@qq.com` / `Cheng2008li#7111`
- 管理员：`cerbo` / `Cheng2008li#7111`

## 6.4 前端构建产物

- `frontend-admin-v3`、`frontend-user-v3-www` 和 `frontend-user-v4-console` 的 build 都可能生成 `.gz` / `.br` 预压缩文件。
- `frontend-user-v3-www` 的 `npm run build` 会生成 sitemap/prerender 相关产物。
- `frontend-user-v3-www` 额外脚本：`verify:refactor`、`check:source-health`。
- `frontend-user-v4-console` 额外脚本：`verify:refactor`。
