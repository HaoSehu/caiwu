# 项目规则

仓库级指令文件。目标：让代理避免常见错误、快速对齐现有约定。

## 1. 文档优先级

冲突时按以下顺序执行：

1. 本文件 `AGENTS.md`
2. 编码始终 UTF-8
3. `页面风格.md`（视觉与页面结构）
4. `启动指南.md`（本地启动命令）
5. 当前代码中的既有实现
6. `文档/架构/架构现状说明.md`（架构真源）
7. `开发规范.md` 与 `CLAUDE.md` 中不冲突的补充说明

规则与稳定运行中的现有代码冲突时，先以现有代码行为为准。

## 2. 执行原则

- 能自行判断的直接执行，不要中途停下来等用户确认。
- 多个子任务按依赖顺序依次完成，全部完成后输出总结。
- 多步骤改造先形成执行计划，按当前代码和文档自动审查计划，修正明显遗漏或冲突后再动手。
- 每完成一个子任务都执行受影响范围的小测试；全部任务完成后执行本次改动涉及层的完整验证。测试失败先修复，再进入下一任务。
- 修改以最小必要范围为原则，不做无关重构。
- 仓库可能处于脏工作区，禁止回滚不是自己造成的改动。

## 3. 项目结构

- `backend`：Laravel 12 后端（PHP 8.2+），承载认证、支付、订单、账单、工单、服务实例、内容、优惠券等。
- `frontend-admin`：管理端，Vue 3 + Vite + Element Plus。
- `frontend-admin-v3`：新版管理端，Vue 3 + Vite + TDesign（TDesign Starter for Vue Next 基底），逐步替代 `frontend-admin`。
- `frontend-client`：用户端与官网，Vue 3 + Vite + Element Plus。
- `shared`：跨端共享状态映射、展示组件与通用配置。前端通过 `@shared` / `@caiwu/shared` 别名引用。
- `文档`：项目文档库，入口看 `文档/README.md` 与 `文档/目录说明.md`。
- `backend/scripts/`：维护脚本（`export_api_inventory.php` 等），不要放长期业务代码。
- `migration-output`：历史旧数据快照的临时输出目录。

不存在的目录不要引用：根目录无 `scripts/`、无 `.kiro/specs`。

## 4. 技术基线

- 后端：PHP 8.2、**Laravel 12**（`composer.json` 为准）、Sanctum 4、MySQL 8。
- 前端：Vue 3、Composition API、Vite 6、Pinia、axios、Sass。
- 前端 UI 框架：
  - `frontend-admin`：Element Plus
  - `frontend-admin-v3`：TDesign Vue Next + TDesign Icons Vue Next（基于 TDesign Starter for Vue Next）
  - `frontend-client`：Element Plus
- 图标：
  - Element Plus 端：`@element-plus/icons-vue`
  - TDesign 端：`tdesign-icons-vue-next`
- 本地地址统一 `127.0.0.1`，不要混用 `localhost`。
- 鉴权：管理端与用户端都走 Sanctum Token，Token 按 `admin_token` / `client_token` 分端存储，权限码走 `permission:{code}` 中间件。
- 缓存：Redis；队列：`database` 驱动，并入 `schedule:run` 消费；会话：`file`。
- 调度：生产由宝塔每分钟执行 `php artisan schedule:run`，队列消费同时被触发；异步任务最坏延迟约 60 秒。

## 5. 通用开发规则

- 新增能力前先复用现有模块、服务、样式入口和共享配置。
- 业务代码按领域聚合，不按"临时功能"堆放。
- 禁止把业务判断、请求拼装、状态映射大段堆在页面模板里。
- 公共逻辑优先抽到同目录 `composables`、`utils`、`services` 或 `shared`。
- 状态展示复用 `shared/statusConfig.js`、`shared/extraStatusMaps.js`、`shared/components/StatusTag.vue`。

## 6. 前端规则

### 通用

- 统一 Vue 3 `script setup` + Composition API。
- 接口请求走各端 `src/utils/request.js`（或 `src/utils/request/`），不要直接新建 axios 实例。
- 认证存取走 `src/utils/auth.js`（或等价模块），不要直接操作 localStorage 键。
- 领域请求收敛到 `src/api/*`，视图层只消费明确的 API 方法。

### 管理端 `frontend-admin`

- UI 框架：Element Plus，不引入第二套 UI 库。
- 视觉基线：亮色企业控制台，不是深色后台。
- 样式入口：`src/assets/styles/variables.scss`、`global.scss`、`element/index.scss`。
- 管理端页面禁止新增独立的"头部说明卡片"（例如 `admin-page-head`、`*-hero`、`*-head` 这类只放模块名、标题、说明文案和少量按钮的顶部大卡片）。列表页直接从筛选区、指标区或表格卡片开始；详情页的返回、保存、刷新等必要操作使用紧凑工具栏，不做说明型页头。
- 列表页默认结构：页头 → 筛选区 → 表格卡片 → 分页。
- 表单页必须 `el-form` 校验，提交按钮有 loading/禁用态。
- 非必要不要散落 `:deep(.el-*)` 和硬编码颜色，走主题 token 或全局样式。
- 配置页不做"敏感信息掩码后保持原值"的伪编辑体验。

### 新版管理端 `frontend-admin-v3`

- UI 框架：TDesign Vue Next + TDesign Icons Vue Next，**禁止**混用 Element Plus。
- 工程基底：TDesign Starter for Vue Next（`Tencent/tdesign-vue-next-starter`）。
- 样式入口：`src/style/` 目录，沿用 Starter 自带的主题变量（`tvision-color`）和 Less 变量体系。
- 布局组件：`src/layouts/`，基于 Starter Admin Layout，包含侧边菜单、顶部栏、面包屑、用户菜单。
- 路由模块：`src/router/modules/`，按业务域拆分（`homepage.ts`、`user.ts` 等）。
- 状态管理：`src/store/modules/`，使用 Pinia + `pinia-plugin-persistedstate`。
- 权限控制：`src/permission.ts`（路由守卫）+ `src/store/modules/permission.ts`，权限码与旧管理端一致。
- 页面模板：`src/pages/` 下按领域组织，复用 Starter 的列表页、表单页、详情页模板模式。
- 图标统一使用 `tdesign-icons-vue-next`，禁止混用 `@element-plus/icons-vue`。
- 所有 v3 页面必须与旧管理端功能一致、路由覆盖一致、权限控制一致、API 对接一致。
- 禁止在 v3 中硬编码 Element Plus 组件或样式。

### 用户端与官网 `frontend-client`

- 可以比管理端更有视觉表现，但沿用现有 token、圆角、阴影和蓝色品牌体系。
- 样式复用 `src/assets/styles/variables.scss`、`global.scss` 和既有布局组件。
- 购买/结算/恢复流程复用 `src/utils/websiteCheckout.js`、`websiteCoupon.js`。

## 7. 后端规则

- 控制器保持薄层：参数接收、鉴权、调用服务、返回响应。
- 参数校验用 `FormRequest`；响应用 `Resource` 和控制器基类。
- 统一通过 `App\Traits\ApiResponse` / `App\Support\ApiResponseBuilder` 返回 JSON。
- 成功响应固定 `code = 0`，不要自创成功码。
- 分页结构：`list`、`total`、`page`、`page_size`。
- 业务逻辑放 `app/Services`，常量/枚举放 `app/Constants`。
- 支付、账单、订单等流程必须考虑事务、幂等和审计字段。
- Payment 记录（payments 表）只允许修改状态，禁止物理删除任何行（包括 gateway=balance/manual/free 的历史记录）。
- Payment 仅记录第三方支付网关真实资金流入（如支付宝充值、支付宝付商品）；余额支付、管理员手动开服、免费订单不产生 Payment。
- 操作来源沿用 `operator_*`、`actor_*`、`trace_id`、`ip_address`。
- 调用上游/第三方必须走 `app/Services` 下的专用客户端，**不要**在 Controller 里直接 `Http::*`。
- 上游 provider key 以真实 `suppliers.interface_type` 或服务绑定值为准，禁止把 `mofang_finance_api` 归一化或别名成 `hosting_panel_api`。
- 魔方财务/魔方云差异必须收敛在 `Services/Upstream/Drivers/Mofang` 或 `Integrations/Mofang` 中间层；通用主机面板协议只能保留共享传输与协议能力。
- 回调接口必须走签名中间件，业务处理必须幂等，必须落日志。
- 敏感配置走 `settings` 或 `.env`，不要硬编码。

## 8. 数据库与接口

- 表名、字段名 `snake_case`。
- 迁移必须新增文件，不改历史迁移。
- 财务/审计表变更必须考虑索引、回填、兼容旧数据和回滚。
- 仓库里存在早期激进合并方向的历史迁移文件，**不要补跑**。
- 真实表结构以实库 `information_schema` 为准；`文档/数据库/当前数据库结构.md` 是当前结构摘要，`文档/数据库/数据库结构说明-idc-2026-04-17.md` 是带日期的历史快照。
- 数据库重构方向见 `文档/架构/架构现状说明.md`。
初始化新库：backend\scripts\install_db.py
迁移旧库：backend/scripts/migrate_legacy_dump.py

## 9. 视觉与交互

- 后台：浅灰背景、白色卡片、蓝色主操作，和 `页面风格.md` 一致。
- 状态展示：浅底标签，不直接用高饱和纯色文本。
- 新增样式先复用变量文件中的 token，不要手写第二套。
- 登录页/官网首页可以更有表现力，但不破坏整体品牌。

### 反主流视觉模式（用户明确要求视觉设计时启用）

- 设计目标不是标准 SaaS 模板，而是有触感、有审美立场的页面。
- 允许打破对称，保留轻微偏移和留白张力。
- 页面必须有材质感（噪点、渐变、纸感纹理）。
- 色彩：深灰+橙色、炭黑+米白+焦橙，避免蓝紫体系。
- 文案直白有人味，不写"赋能""闭环"这类空话。

#### 禁止清单

- 禁止紫色/靛蓝渐变、纯平背景色、Hero+三卡片公式、整页完美居中。
- 禁止 Emoji 做功能图标、`ease-in-out` 做默认动画曲线。

## 10. 验证要求

- 只改文档：自检内容与仓库现状一致。
- 改 `frontend-admin`：执行 `npm run build`。
- 改 `frontend-admin-v3`：执行 `npm run build`（含 `vue-tsc --noEmit`）。
- 改 `frontend-client`：执行 `npm run build`；涉及重构收口范围再执行 `npm run verify:refactor`。
- 改 `backend`：执行 `php artisan test`，必要时缩小到受影响测试文件。
- 多步骤开发：子任务完成后先跑最小相关测试，最后再跑完整受影响验证；失败必须修复后继续。
- 无法运行验证时，在总结中说明原因。

## 11. 本地启动

### 后端

```bash
cd backend
php artisan app:serve
# 需要带调度时：
php artisan app:serve --with-schedule
```

`app:serve` 同时拉起 HTTP、VNC Relay、Queue Worker。**不要**用 `php artisan serve` 替代。

### 前端

```bash
# 用户端（127.0.0.1:5173）
cd frontend-client && npm run dev

# 管理端（127.0.0.1:5174）
cd frontend-admin && npm run dev

# 新版管理端（127.0.0.1:5175）
cd frontend-admin-v3 && npm run dev
```

联调时统一 `127.0.0.1`，不要混用 `localhost`。

## 12. 禁止项

- 禁止无关文件顺手格式化或大面积重排。
- 禁止引入与现有体系重复的 UI 库、请求层、状态层。
- 禁止把管理端改成深色大屏风格。
- 禁止硬编码后端地址、token 键名、权限码和状态文案。
- 禁止删除已有可复用能力后再重写一遍。
- 禁止未说明影响面就修改接口响应结构或公共样式入口。
- 禁止手工编辑 `文档/后端/后端API清单.md`（自动生成）；改业务分组导航请改 `文档/后端/API清单导航.md`，重生成跑 `php backend/scripts/export_api_inventory.php`。
- 禁止用 `php artisan serve` 替代 `php artisan app:serve`。
- 禁止在 Controller 里直接 `Http::*` 调上游或第三方。
- 禁止在生产常驻 `queue:work`（队列已并入 `schedule:run`）。
- 禁止补跑历史激进迁移文件。
- 禁止随手在仓库根新建文档；规则类以外放 `文档/` 下对应子目录。

## 13. 文档查阅路径

- **新人入项**：`AGENTS.md` → `启动指南.md` → `开发规范.md` → `文档/README.md` → `文档/产品/产品总览.md` → `文档/架构/架构现状说明.md` → `文档/目录说明.md`。
- **查接口**：`文档/后端/后端API清单.md`（精确）+ `文档/后端/API清单导航.md`（业务分组）。
- **查接口格式**：`文档/后端/API格式规范.md`。
- **查表结构**：先看 `文档/数据库/当前数据库结构.md`，历史对照再看 `文档/数据库/数据库结构说明-idc-2026-04-17.md`；疑难以实库 `information_schema` 与 `文档/架构/架构现状说明.md` 为准。
- **后端规范**：`文档/后端/API格式规范.md`、`文档/后端/后端目录分类规范.md`。
- **前端规范**：`文档/前端/前端项目规范.md`。
- **上游对接**：`文档/集成/本地对接说明.md`。
- **部署与调度**：`文档/部署与调度指南.md`。
- **回溯旧方案**：直接查 `git` 历史，`文档/` 目录不保留历史副本。

## 14. 测试账号

- 用户：`2908990438@qq.com` / `Cheng2008li#7111`
- 管理员：`cerbo` / `Temp@123456`

## 15. 前端构建产物

- `frontend-admin`、`frontend-admin-v3` 和 `frontend-client` 的 build 都会自动生成 `.gz` / `.br` 预压缩文件。
- `frontend-client` 的 `npm run build` 还会自动注入站点验证 meta。
- `frontend-client` 额外脚本：`verify:refactor`（源码健康检查+构建）、`check:source-health`（单独源码检查）。
- `frontend-admin` 额外脚本：`api:catalog`（生成 API 目录）。
