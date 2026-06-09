# frontend-user-v4-console TDesign Starter 重构开发文档

版本：Draft 1  
日期：2026-06-09  
范围：在 `C:\Users\USER125536\Desktop\caiwu\frontend-user-v4-console` 新建用户控制台 SPA；`frontend-user-v3-console` 仅作为迁移来源和回归基线保留。  
目标：使用 TDesign Starter 作为新前端底座，保留现有用户控制台全部业务功能与 `/client/*` 访问边界，不删除、不覆盖 `frontend-user-v3-console`。

## 0. 实施边界更新

本文件最初以 `frontend-user-v3-console` 为重构对象。最新实施口径调整为：

- 不在 `frontend-user-v3-console` 目录内直接执行脚手架、覆盖、清理或删除。
- 新建 `frontend-user-v4-console` 作为 TDesign Starter 重构实施目录。
- `frontend-user-v3-console` 保持现状，作为页面、API、runtime、store、路由、测试和验收对照来源。
- `frontend-user-v3-www` 继续作为 SEO 官网，不并入 console。
- 发布切换在 `frontend-user-v4-console` 完成全量验证后另行处理，本阶段不以删除 v3 或原地替换 v3 作为交付条件。
- 文档文件名暂保留历史命名，避免已引用的开发入口断链；正文目标目录以 `frontend-user-v4-console` 为准。

## 1. 背景与结论

当前 `frontend-user-v3-console` 是从用户端拆分出来的控制台应用，也是本次 v4 重构的功能基线。它仍大量依赖 Element Plus：

- `package.json` 中存在 `element-plus` 与 `@element-plus/icons-vue`。
- `src/app/bootstrap.ts` 通过 `provideGlobalConfig` 注册 Element Plus 全局配置。
- `src/app/http/request.ts`、多处 composable 与页面使用 `ElMessage`、`ElMessageBox`。
- 页面模板大量使用 `el-table`、`el-form`、`el-dialog`、`el-drawer`、`el-pagination`、`el-upload` 等组件。

重构不是简单换皮，而是一次前端底座替换：

- 以 TDesign Starter Vue Next 为工程底座，**布局、侧边导航、顶部栏、内容区、移动端抽屉均直接复用 Starter 模板的 `layouts/` 结构**，不自造导航体系。
- 以 TDesign Vue Next 组件体系替代 Element Plus。
- 保持现有 API、鉴权、路由、支付、充值、服务控制台、工单、内容中心等业务行为不降级。
- 保持 `frontend-user-v3-www` 与新 `frontend-user-v4-console` 的职责边界：`www` 负责 SEO 官网，`console` 负责用户控制台，不做 SEO。

## 2. 外部依据与版本快照

官方参考：

- TDesign Starter Vue Next 快速开始：https://tdesign.tencent.com/starter/docs/vue-next/get-started
- TDesign Starter Vue Next 开发说明：https://tdesign.tencent.com/starter/docs/vue-next/develop
- TDesign Vue Next Starter 仓库：https://github.com/Tencent/tdesign-vue-next-starter

2026-06-09 本地查询到的 npm 版本：

| 包 | 版本 | 用途 |
|---|---:|---|
| `tdesign-starter-cli` | `0.5.3` | Starter CLI，提供 `td-starter` 命令 |
| `tdesign-vue-next` | `1.20.1` | TDesign Vue 3 组件库 |
| `tdesign-icons-vue-next` | `0.4.4` | TDesign 图标库 |
| `tvision-color` | `1.6.0` | TDesign Starter 主题色能力 |

落地时以 `td-starter init` 实际生成的 `package.json` 与锁文件为准，不手工猜测 Starter 内部依赖。

## 3. 重构目标

### 3.1 产品目标

构建一个面向 IDC/云服务客户的高效率控制台：

- 用户可以快速看见服务状态、账单风险、余额、待处理工单。
- 用户可以完成购买后确认下单、账单支付、充值、续费、流量包购买。
- 用户可以管理云主机或 NAT 服务，包括电源、重装、VNC、监控、安全组、NAT 转发、操作日志。
- 用户可以提交、回复、撤回、关闭工单，并查看公告与帮助文档。
- 用户可以完成实名认证、资料维护、优惠券领取和推荐奖励查看。

### 3.2 工程目标

- 使用 TDesign Starter 的 Vite、Vue 3、TypeScript、Pinia、Vue Router、Less、TDesign 主题变量体系。
- 禁止继续混用 Element Plus 与 `@element-plus/icons-vue`。
- 保留 `@caiwu/shared` 共享模块，尤其是 `shared/user-v3/components` 中已经 TDesign 化的基础组件。
- 保留现有 `client_token` 鉴权、请求拦截、弱网增强、安全 GET 去重、写请求 `X-Request-Id`、401 跳转登录等行为。
- 保留构建预压缩 `.gz` / `.br`、资源 base、代理、VNC WebSocket 代理等运维能力。

### 3.3 非目标

- 不重构后端接口。
- 不修改数据库，不初始化数据库，不执行迁移。
- 不把 `frontend-user-v4-console` 合回 `frontend-user-v3-www`。
- 不删除、不覆盖 `frontend-user-v3-console`；v3 只作为迁移来源、回归基线和临时回退目录。
- 不给控制台做 SEO，不生成 sitemap，不开放 `index,follow`。
- 不要求像素级复刻旧 Element Plus 布局，但要求功能和业务流程百分百保留。
- 不引入第三套 UI 库、请求库、状态管理库。

## 4. PRFAQ

### 4.1 Press Release

创欧云用户控制台将升级为基于 TDesign Starter 的新一代客户工作台。新控制台面向已经购买或准备购买云服务的用户，重点解决旧控制台在复杂业务页面中信息密度不稳定、组件体系混杂、移动端体验割裂、服务实例操作入口分散的问题。

新版控制台不追求“看起来像后台模板”，而是以客户真实任务为中心：先看服务是否正常，再处理账单与余额，再进入实例控制台完成电源、重装、VNC、NAT、安全组等高频操作。财务页面继续保留订单、账单、充值、余额流水、优惠券、推荐奖励的完整链路；工单页面保留创建、回复、上传附件、撤回、关闭等交互；内容页面保留公告与帮助详情。

本次升级最大的变化是工程底座。控制台将从 Element Plus 迁移到 TDesign Starter Vue Next，统一使用 TDesign Vue Next 组件、TDesign Icons、Less 变量和 Starter 布局模式。所有视觉、动效、响应式和可访问性规范会在新底座内实现，而不是在旧项目上继续堆叠 `:deep(.el-*)` 覆盖。

用户感知上的收益是：页面更清晰，关键操作更靠前，表格与卡片在手机上更可用，账单支付与服务控制台的异常反馈更稳定。研发收益是：控制台与新版管理端同属 TDesign 技术栈，后续共享组件、状态标签、数据空态、抽屉、弹窗和响应式骨架可以统一维护。

### 4.2 FAQ

| 问题 | 答案 |
|---|---|
| 这是什么？ | `frontend-user-v4-console` 的 TDesign Starter 重构方案，目标是在新目录替换工程底座和 UI 组件体系，同时保留 v3 作为基线。 |
| 为什么现在做？ | 当前控制台仍依赖 Element Plus，而 `shared/user-v3` 和 `frontend-admin-v3` 已开始使用 TDesign，继续混用会增加长期维护成本。 |
| 用户会感知到什么？ | 页面结构更统一，移动端更稳定，服务控制台和财务操作入口更明确。 |
| 是否改接口？ | 不改。继续使用 `/api/client/*` 和现有响应结构 `{ code, message, data, timestamp }`。 |
| 是否改路由？ | 不破坏已有 `/client/*` 路由。可以新增内部重定向，但不得删除旧入口。 |
| 是否保留登录态？ | 保留 `client_token` 机制与 session 过期逻辑。 |
| 是否还需要官网页面？ | 控制台不承载官网 SEO 页面。`/products`、`/about`、`/terms`、`/privacy`、`/notices`、`/help` 等公共入口继续跳转到 public site。 |
| 最大风险是什么？ | 支付、充值轮询、VNC、NAT、安全组、工单附件这类复杂交互在 UI 替换时容易遗漏状态。需要按页面矩阵逐项验收。 |
| 如何发布？ | 先在 `frontend-user-v4-console` 完成 Starter 底座、页面迁移和完整验证；是否替换线上入口或下线 v3 另行决策。 |

## 5. 当前功能范围

当前真实入口在 `src/app/router/routes.ts`。

> **架构注意**：v3 采用双层目录架构——`src/pages/` 存放路由入口页面（薄封装），`src/views/` 存放实际业务逻辑（组件、composables、子页面）。例如：
> - `pages/client/profile/index.vue` → 引用 `views/client/Profile/components/ProfileLayout.vue`、`AgentPanel.vue`、`NotificationPanel.vue`、`SecurityDialogs.vue`
> - `pages/client/service-console/index.vue`（7 行）→ 引用 `views/client/ServiceConsole/` 下的完整实现
> - `pages/client/help/`、`notices/` → 引用 `views/client/content/ContentListPage.vue`、`ContentDetailPage.vue`
>
> 迁移时 `views/` 中的业务逻辑是主要迁移对象，需映射到 v4 的 `domains/`、`features/`、`widgets/` 或 `components/` 中。Phase 0 盘点必须覆盖 `src/views/` 全部内容。

### 5.1 访客页

| 路由 | 页面 | 必须保留的功能 |
|---|---|---|
| `/client/login` | 用户登录 | 邮箱/账号登录、验证码或极验触发、登录后 redirect |
| `/client/login-as` | 代登录 | 后台代登录 code 换 token，进入客户中心 |
| `/client/register` | 用户注册 | 注册表单、协议入口、注册后写入 token |
| `/client/forgot-password` | 找回密码 | 找回流程、校验、错误反馈 |

### 5.2 登录后页面

| 分组 | 路由 | 页面 | 优先级 |
|---|---|---|---|
| 总览 | `/client/dashboard` | 控制台 | P0 |
| 账户 | `/client/profile` | 个人资料 | P0 |
| 账户 | `/client/verification` | 实名认证 | P0 |
| 购买 | `/client/order/create` | 确认下单 | P0 |
| 购买 | `/client/checkout-resume` | 创建账单中 | P0 |
| 服务 | `/client/services` | 我的服务 | P0 |
| 服务 | `/client/services/:id` | 实例控制台 | P0 |
| 财务 | `/client/orders` | 订单记录 | P1 |
| 财务 | `/client/invoices` | 账单记录 | P0 |
| 财务 | `/client/invoices/:id` | 账单详情 | P0 |
| 产品 | `/client/catalog` | 产品目录 | P1 |
| 财务 | `/client/recharge` | 账户充值 | P0 |
| 财务 | `/client/payments` | 充值记录 | P1 |
| 财务 | `/client/balance-logs` | 余额流水 | P1 |
| 营销 | `/client/coupons` | 优惠券中心 | P1 |
| 营销 | `/client/referral` | 推荐奖励 | P1 |
| 支持 | `/client/tickets` | 工单支持 | P0 |
| 支持 | `/client/tickets/:id` | 工单详情 | P0 |
| 支持 | `/client/ticket-conversations/:id` | 工单交流 | P0 |
| 工具 | `/client/tools` | 管理工具 | P2 |
| 内容 | `/client/notices` | 系统公告 | P1 |
| 内容 | `/client/notices/:id` | 公告详情 | P1 |
| 内容 | `/client/help` | 帮助中心 | P1 |
| 内容 | `/client/help/:id` | 帮助详情 | P1 |

P0 是重构首批必须可用页面。P1 可在 P0 后迁移，但最终上线前必须完成。P2 当前路由存在但菜单注释，仍需保留入口，避免历史链接失效。

## 6. 目标工程架构

### 6.1 初始化策略

使用 `td-starter init` 脚手架直接生成 `frontend-user-v4-console`。`td-starter init` 本质是从 TDesign Vue Next Starter 开源仓库拉取模板并在本地构建，无需额外 `git clone`。

**首选路线 — `td-starter init`：**

```bat
cd C:\Users\USER125536\Desktop\caiwu
npm i tdesign-starter-cli -g
td-starter init frontend-user-v4-console
```

执行前先运行 `td-starter init --help` 确认可用标志。若 CLI 进入交互模式，选择 Vue Next / TDesign Starter 对应模板，项目名使用 `frontend-user-v4-console`。

**备用路线 — `git clone`（CLI 不可用时）：**

```bat
cd C:\Users\USER125536\Desktop\caiwu
git clone https://github.com/Tencent/tdesign-vue-next-starter.git frontend-user-v4-console
cd frontend-user-v4-console
rm -rf .git && git init
git add -A && git commit -m "chore: init from tdesign-vue-next-starter base"
npm install
```

无论采用哪种方式，`frontend-user-v3-console` 都不得作为脚手架输出目录或 clone 目标目录。

合并原则：

- Starter 的构建体系（Vite、TypeScript、ESLint、Stylelint、Less）、layouts、router modules、store modules、style 主题变量作为底座。
- `frontend-user-v3-console` 的 API、runtime session、业务 composable、页面业务逻辑作为迁移来源。
- 只在 `frontend-user-v4-console` 内替换和调整对应文件，不回写 v3。
- 每阶段提交或至少保留可回退目录，不在一次大改中删除旧功能；v3 目录本身就是最终回退基线。
- `frontend-admin-v3` 的 TDesign 集成经验（组件用法、Less token、图标注入）可作为参考，但不复制其管理端业务布局。

### 6.2 目标目录

```text
frontend-user-v4-console/
  src/
    api/
      auth.ts
      client.ts
      site.ts
      contentNormalizer.ts
    app/
      http/request.ts
      runtime/session.ts
      runtime/network.ts
      stores/siteBranding.ts
    router/
      index.ts
      modules/
        client.ts
        public-redirect.ts
        result.ts
    permission.ts
    store/
      index.ts
      modules/
        user.ts
        app.ts
        setting.ts
    layouts/
      index.vue
      blank.vue
      components/
        LayoutHeader.vue
        LayoutSideNav.vue
        LayoutContent.vue
        ClientAccountMenu.vue
        ClientMobileDrawer.vue
    pages/
      client/
        dashboard/
        services/
        service-console/
        invoices/
        invoice-detail/
        recharge/
        tickets/
        ticket-detail/
        profile/
        verification/
        ...
      common/
        not-found/
        forbidden/
    domains/
      services/
      products/
      finance/
    features/
      tickets/
      services/
      payment/
    widgets/
      services/
      tickets/
      finance/
    components/
      auth/
        AuthShell.vue
      mobile/
        MobileSheet.vue
        MobileOptionPicker.vue
        MobileOsPicker.vue
        MobileRegionPicker.vue
        MobileRangePicker.vue
      ...
    style/
      index.less
      variables.less
      layout.less
      client-console.less
      mobile-overlays.less
```

说明：

- `src/router/modules/client.ts` 负责 `/client/*`。
- `src/permission.ts` 负责登录、访客页、动态 import 失败重载、页面标题。
- `src/store/modules/user.ts` 替代当前 `src/stores/user.js`，但行为保持一致。
- `src/app/http/request.ts` 可以保留当前请求能力，但替换 UI 提示为 TDesign `MessagePlugin`。
- `src/style/` 使用 Less 和 TDesign token，不继续维护 `assets/styles/element/index.scss`。
- `src/components/` 存放 v3 迁移过来的本地通用组件（`AuthShell`、移动端面板 `MobileSheet`、选择器 `MobileOptionPicker` 等）。这些组件当前在 v3 的 `src/components/` 下，部分依赖 Element Plus（如 `MobileSheet` 使用 `el-drawer`），迁移时需替换为 TDesign 对应组件。

### 6.3 必须保留的运行时能力

| 能力 | 当前位置 | 迁移要求 |
|---|---|---|
| API baseURL | `src/app/http/request.ts` | 继续使用 `VITE_API_BASE_URL || '/api'` |
| client token | `src/app/runtime/session.ts` | 继续只使用 `client_token`，不得混入 `admin_token` |
| 401/40100 | request + guards | 继续清 token 并跳 `/client/login?redirect=...` |
| 422 表单错误 | request | 继续提取 `errors` 并展示简体中文提示 |
| 429 限流 | request | 继续识别 `retry-after` |
| 弱网超时 | request + runtime network | 保留安全 GET 超时增强 |
| 安全 GET 去重 | `@caiwu/shared/runtime` | 保留，不重写 |
| 写请求 request id | request | POST/PUT/PATCH/DELETE 继续带 `X-Request-Id` |
| 动态 import 失败重载 | router guards | 保留 sessionStorage 一次性重载逻辑 |
| 站点配置 | `siteBranding` store | 保留 logo、favicon、站点名、客服信息 |
| Element Plus 全局配置 | `bootstrap.ts` 中 `provideGlobalConfig` | 替换为 TDesign 的全局配置机制（ConfigProvider 或 Starter 内置主题配置）；移除 `element-plus/es/locale/lang/zh-cn` 引入，改用 TDesign `zh_CN` 配置 |
| Element Plus 样式入口 | `bootstrap.ts` 中 `@/assets/styles/element/index.scss` | 替换为 `src/style/index.less` 与 TDesign Less token |
| VNC | noVNC + `/ws/vnc` | 保留依赖和代理，禁止泄露原始 token 或密码 |

## 7. 依赖迁移策略

> **关键发现**：`shared/` 模块存在两套组件体系——`shared/user-v3/components/` 已 TDesign 化（`<t-tag>`、`<t-dialog>` 等），但 `shared/components/` 仍重度依赖 Element Plus。尤其是 **`shared/components/TicketChatPanel.vue`**，使用了 `el-button`、`el-image`、`el-upload`、`el-input`、`el-dialog`、`ElMessage`、`@element-plus/icons-vue` 以及 `var(--el-*)` CSS 变量。这些组件是用户端和管理端共享的，迁移 v4 时必须同步改造为 TDesign。

### 7.1 必须移除

| 依赖 | 替代 |
|---|---|
| `element-plus` | `tdesign-vue-next` |
| `@element-plus/icons-vue` | `tdesign-icons-vue-next` |
| `unplugin-vue-components/resolvers` 中的 `ElementPlusResolver` | TDesign Starter 默认配置或显式组件导入 |
| `assets/styles/element/index.scss` | `src/style/index.less` 与 TDesign token |
| `shared/components/StatusTag.vue`（Element Plus 版） | 统一使用 `shared/user-v3/components/StatusTag.vue`（TDesign 版） |
| `shared/components/TicketChatPanel.vue`（Element Plus 版） | 当前仅 admin-v3 使用，v3-console 未引用。但与 v4-console 同属 shared 模块，建议与 admin-v3 协调后统一改造为 TDesign 版本，放入 `shared/user-v3/components/` |
| `shared/statusConfig.js` 中 `el-tag`/`el-select` 的 JSDoc 注释 | 更新注释为 TDesign 对应组件名 |

### 7.2 必须保留或重新接入

| 依赖 | 原因 |
|---|---|
| `@caiwu/shared` | 共享状态、runtime、安全请求、用户端 TDesign 基础组件 |
| `axios` | 当前请求层依赖 |
| `pinia` | 用户信息、站点配置、布局状态 |
| `vue-router` | 路由与鉴权 |
| `@novnc/novnc` | VNC 控制台 |
| `qrcode.vue` | 支付二维码 |
| `markdown-it` | 公告、帮助正文渲染 |
| `three` | 仍用于 HeroPointCloud 点云背景（`src/components/HeroPointCloud.vue`），保留 |

## 8. 组件替换矩阵

| Element Plus | TDesign Vue Next | 迁移注意 |
|---|---|---|
| `el-container`、`el-aside`、`el-header`、`el-main` | `t-layout`、`t-aside`、`t-header`、`t-content` | 优先复用 Starter layout |
| `el-menu`、`el-menu-item` | `t-menu`、`t-menu-item`、`t-submenu` | 菜单 active、折叠、移动端抽屉需重测 |
| `el-dropdown` | `t-dropdown` | 账户菜单、退出登录 |
| `el-avatar` | `t-avatar` | 首字母和头像回退 |
| `el-breadcrumb` | `t-breadcrumb` | 面包屑标题来自 route meta |
| `el-table` | `t-table` 或 `t-enhanced-table` | 分页、固定列、小屏卡片化 |
| `el-pagination` | `t-pagination` | `page`、`pageSize` 与后端 `page_size` 对齐 |
| `el-form` | `t-form` | 校验规则、错误文案、提交 loading |
| `el-input` | `t-input`、`t-textarea` | 回车搜索、maxlength、字数统计 |
| `el-input-number` | `t-input-number` | 金额充值、规格配置 |
| `el-select` | `t-select` | 远程搜索、清空、筛选项 |
| `el-button` | `t-button` | loading、disabled、危险操作 |
| `el-tag` | `t-tag` | 优先复用 `shared/user-v3/components/StatusTag.vue` |
| `el-dialog` | `t-dialog` | 工单、资料、安全操作确认 |
| `el-drawer` | `t-drawer` | 账单详情、移动端导航、服务面板 |
| `el-upload` | `t-upload` | 工单图片上传限制、预览、失败提示 |
| `el-image` | `t-image` 或自定义预览 | 工单附件预览 |
| `el-empty` | `t-empty` | 优先复用 `DataState` |
| `el-result` | `t-result` | 下单异常、404、网络错误 |
| `ElMessage` | `MessagePlugin` | 成功、警告、错误提示 |
| `ElMessageBox` | `DialogPlugin` 或 `t-dialog` | 危险操作确认 |

## 9. 页面迁移要求

### 9.1 控制台总览

必须保留：

- 服务状态概览（运行中/异常数量）、待支付账单提醒、账户余额摘要。
- 待处理工单数量、最近公告条目。
- 快速入口：购买服务、查看账单、提交工单。

TDesign 化重点：

- 使用 `t-card` 构建指标卡，指标卡只展示可行动数据，不做装饰性空卡。
- 使用 `t-table` 或 `t-list` 展示最近账单/工单列表。
- 数据加载使用 `t-loading` 或 `t-skeleton`，失败展示 `t-result`。
- 禁止大 Hero、渐变背景、三卡片公式布局。

### 9.2 登录与账户（访客页）

必须保留：

- 登录后写入 `client_token`。
- 已登录访问访客页时跳 `/client/dashboard`。
- 未登录访问受保护页时跳 `/client/login?redirect=...`。
- 代登录 code 换 token 后拉取用户信息。
- 注册成功后直接登录。
- 实名认证初始化、二维码/状态轮询、实名失败原因展示。
- 个人资料、安全设置、代理/通知面板等现有组件能力。

TDesign 化重点：

- 登录、注册、找回密码统一使用 `AuthShell` 或 Starter blank layout。
- 表单使用 `t-form`，错误提示以 TDesign 视觉展示，但文案沿用现有中文。
- 验证码或极验逻辑保持在 composable，不写死在页面模板里。

### 9.3 服务中心与实例控制台

> **迁移注意**：当前 `src/pages/client/service-console/index.vue` 仅 7 行，是一个聚合入口。实际业务逻辑分散在子组件中（云主机控制台、NAT 控制台、电源操作、重装、VNC、监控、NAT 转发、安全组、操作日志、流量包等）。Phase 0 盘点时必须追踪完整子组件引用链，不能只看入口文件。

必须保留：

- 服务列表、筛选、卡片/表格双视图、小屏策略。
- 服务详情基础信息、远程状态、模块状态。
- 续费预览、创建续费账单、自动续费切换。
- 电源操作：开机、关机、重启等。
- 重装系统、重置密码、系统镜像选项。
- VNC token 获取和控制台打开。
- 监控图表、批量监控、弱网容错。
- 操作日志。
- NAT 转发规则创建/删除。
- 安全组列表、规则查看、创建、应用、删除。
- 流量包报价和创建订单。

TDesign 化重点：

- 服务详情页建议采用”状态头部 + 操作条 + tabs”的结构。
- 云主机与 NAT 服务内部可继续拆 `CloudConsolePage`、`NatConsolePage`。
- 危险操作使用 `t-popconfirm` 或 `DialogPlugin.confirm`，所有操作有 loading 和禁用态。
- 图表区域小屏降级为关键指标列表，避免横向溢出。

**VNC 迁移专项说明**（高风险项）：

1. VNC token 获取：保留现有 API 调用逻辑，token 不得写入 URL 或 localStorage，仅通过内存传递。
2. WebSocket 连接：保留 `/ws/vnc` 代理配置，noVNC 的 `RFB` 对象通过 token 鉴权连接。
3. UI 嵌入：noVNC 的 `canvas` 元素嵌入 TDesign 布局中，使用 `t-card` 包裹，提供全屏切换按钮。
4. 关闭清理：页面或抽屉关闭时，断开 WebSocket 连接并释放 noVNC 资源，避免内存泄漏。
5. 验收点：VNC 连接成功、画面渲染正常、键盘输入正常、断开后资源释放、token 不泄露到浏览器地址栏或开发者工具网络面板。

### 9.4 财务、账单与支付

必须保留：

- 订单记录列表和跳转账单。
- 账单列表、账单详情、取消账单、余额支付、支付宝支付、混合支付。
- 支付宝二维码展示、状态轮询、支付成功后刷新。
- 账户充值、充值状态查询、复制支付链接。
- 充值记录、余额流水、财务汇总。
- 优惠券列表、公用券领取、优惠券汇总。
- 推荐奖励概览、奖励记录、账户日志、提现申请。

约束：

- 对用户可见尽量使用“账单”，订单页作为历史记录保留，不扩大“订单”概念。
- 支付状态查询不能只做前端成功态，必须消费现有后端状态接口。
- 金额统一两位小数，空值显示 `--` 或 `¥0.00`，不得显示 `NaN`。

### 9.5 工单与内容

必须保留：

- 工单列表搜索、状态筛选、分页。
- 提交工单，选择问题分类、关联服务、优先级、标题和内容。
- 工单详情、回复、撤回回复、关闭工单。
- 图片上传限制：类型、大小、数量、上传中禁发。
- 附件预览。
- 公告列表、公告详情、帮助中心列表、帮助详情。
- Markdown 渲染和内容 normalize 逻辑。

TDesign 化重点：

- 工单详情在桌面端可用抽屉或详情页，移动端必须全屏可读。
- 上传组件必须明确展示上传失败原因。
- 内容详情需要保留返回入口和空态。

### 9.6 工具页

当前 `/client/tools` 路由存在但侧边菜单注释。重构后必须保留路由，并决定是否继续隐藏菜单。

必须保留的 API：

- 黑洞查询。
- 宁波白名单提交。
- 十堰四层规则新增/删除。
- 十堰七层规则开关。

### 9.7 产品目录

当前 `/client/catalog` 为控制台内产品目录页（P1）。首期保持跳转 `/products` 官网购买页的行为，但需保留 `/client/catalog` 路由入口，避免历史链接失效。

必须保留：

- 产品分类展示（云主机、NAT、流量包等）。
- 产品规格、价格、库存信息。
- 从产品目录跳转到购买流程（`/client/order/create`）的入口。

TDesign 化重点：

- 产品卡片使用 `t-card`，规格表格使用 `t-table` 或 `t-descriptions`。
- 筛选使用 `t-select` 或 `t-tabs` 切换分类。
- 加载态使用 `t-skeleton`。

## 10. 信息架构与导航

目标侧边导航：

```text
客户中心
  总览
    控制台 /client/dashboard
    我的服务 /client/services
    产品目录 /products 或 /client/catalog
  财务
    账单记录 /client/invoices
    订单记录 /client/orders
    账户充值 /client/recharge
    充值记录 /client/payments
    余额流水 /client/balance-logs
    优惠券中心 /client/coupons
    推荐奖励 /client/referral
  支持
    工单支持 /client/tickets
    系统公告 /client/notices
    帮助中心 /client/help
  账户
    实名认证 /client/verification
    个人资料 /client/profile
```

需要产品确认但不阻塞开发的点：

- “产品目录”在侧边栏继续跳 `/products` 官网购买页，还是改为 `/client/catalog` 控制台内产品目录。
- `/client/tools` 是否恢复到支持分组菜单中。

默认建议：

- 首期保持旧行为，“产品目录”继续跳 public site，避免影响购买页 SEO 与路径。
- `/client/tools` 保留路由但不放主菜单，除非运营明确需要曝光。

## 11. 设计系统

### 11.1 视觉方向

控制台的设计方向是”克制的业务驾驶舱”，不是营销页，也不是 AI 生成的炫技页面。

**布局基线**：整体页面壳（侧边导航、顶部栏、内容区、移动端抽屉）直接使用 TDesign Vue Next Starter 模板的 `layouts/` 结构，不在其外自建一套导航体系。所有页面内容在 Starter 的内容区插槽内实现，不覆盖或重写 Starter 的 layout 组件。

必须做到：

- 信息先行：服务状态、待支付账单、余额、工单响应等关键数据优先出现。
- 操作明确：主操作只有一个，次操作收敛到工具栏、更多菜单或详情区域。
- 层级清楚：页面标题、摘要、筛选、数据、详情、帮助提示各有固定位置。
- 密度适中：PC 端提高信息效率，移动端牺牲部分密度换取可读性。
- 风格统一：所有页面使用同一套 Starter layout、TDesign token、共享组件。

禁止出现：

- 每个页面都做一张大 Hero 卡片。
- 随机渐变、玻璃拟态、大面积发光边框。
- 没有业务含义的装饰图形、插画和图标堆叠。
- 紫蓝渐变模板感、空洞标语、过度居中、三卡片公式布局。
- 为了“好看”重写 TDesign 已经解决的表格、表单、弹窗、抽屉、菜单。

### 11.2 复用优先级

重构主打不重复造轮子，复用顺序必须固定：

1. TDesign Starter 原生能力：以 `td-starter init` 生成的 seed 项目为准，复用 `layouts`、`router/modules`、`store/modules`、`src/style`、全局主题、移动端布局规则。
2. TDesign Vue Next 组件：`t-layout`、`t-menu`、`t-card`、`t-table`、`t-form`、`t-dialog`、`t-drawer`、`t-pagination`、`t-upload`、`MessagePlugin`。
3. `shared/user-v3/components`：`StatusTag`、`PageScaffold`、`DataState`、`ResponsiveActionBar`、`AppDialog`、`AppDrawer`、`SideNavShell`、`DetailScaffold`、`MotionWrapper`。
4. 业务域组件：`features`、`widgets`、`domains` 下的复用组件。
5. 最后才允许新增 app-local 组件，且必须证明 TDesign Starter 和 shared 不能覆盖。

落地约束：

- 不为每个页面单独写一套 header、empty、drawer、dialog、status tag。
- 不把 TDesign 组件再包一层没有业务语义的 `BaseButton`、`BaseCard`。
- 只抽象有业务语义的组件，例如 `ServiceStatusPanel`、`InvoicePaymentPanel`、`TicketReplyBox`。
- 视觉间距、字体、圆角、阴影优先走 TDesign token，不写散落的魔法数字。
- `frontend-admin-v3` 是手动集成 TDesign 的项目（非 `td-starter init` 生成），只借鉴其 TDesign 组件用法、Less token 体系和路由图标注入方式。不得直接复制其绑定管理端 `permissionStore`、`tabsRouterStore`、i18n、admin 菜单分组的业务布局代码。

### 11.3 Token 策略

使用 TDesign Starter 的 Less 变量和 TDesign token。当前 `frontend-admin-v3/src/style/layout.less` 已有可复用参考，例如内容区 padding 使用 `var(--td-comp-paddingTB-xl)` 与 `var(--td-comp-paddingLR-xl)`，移动端收敛到更小 padding。

```less
@console-bg: var(--td-bg-color-page);
@console-panel: var(--td-bg-color-container);
@console-text: var(--td-text-color-primary);
@console-text-secondary: var(--td-text-color-secondary);
@console-border: var(--td-border-level-1-color);
@console-brand: var(--td-brand-color);
@console-success: var(--td-success-color);
@console-warning: var(--td-warning-color);
@console-error: var(--td-error-color);
@console-radius: var(--td-radius-medium);
@console-shadow: var(--td-shadow-1);
```

若需要覆盖品牌色，统一在 `src/style/variables.less` 或 Starter 主题配置中处理，不在页面里硬编码。

### 11.4 视觉引导与页面骨架

页面骨架统一采用“标题语境 -> 关键摘要 -> 操作/筛选 -> 数据主体 -> 详情/帮助”的顺序。

| 区域 | PC 端 | 平板端 | 手机端 |
|---|---|---|---|
| 页面标题 | 标题 + 面包屑 + 右侧主操作 | 标题 + 主操作靠右或折行 | 标题单行，主操作进入底部或顶部按钮 |
| 关键摘要 | 3 到 4 个指标卡横排 | 2 列指标卡 | 单列或横向滚动小卡 |
| 筛选区 | 一行内收敛，复杂筛选进展开面板 | 2 列 grid | 单列堆叠，按钮全宽 |
| 数据主体 | `t-table` 优先，固定操作列 | 表格保留关键列，更多字段进详情 | 卡片列表优先，必要时横向滚动表格 |
| 详情区 | `t-drawer` 480 到 720 宽 | `t-drawer` 80vw | 全屏抽屉或详情页 |
| 帮助提示 | 右侧说明、tooltip、alert | 折叠说明 | 放在表单项下方或底部说明 |

页面类型规则：

- Dashboard：指标卡只展示可行动数据，不做无意义装饰卡。
- 列表页：筛选区和表格间距固定，不在列表顶部做大标题卡。
- 详情页：服务状态与危险操作必须视觉分区。
- 表单页：表单项按业务步骤分组，不用两列硬塞移动端。
- 内容页：公告和帮助以阅读体验为主，正文宽度受控。

### 11.5 图标与导航布局

图标只用于提升识别，不承担文案职责。所有图标来自 `tdesign-icons-vue-next`，路由菜单图标通过 route meta 的 `shallowRef(TDesignIcon)` 注入，沿用 `frontend-admin-v3` 的做法：

```ts
// router/modules/client.ts
import { shallowRef } from 'vue'
import { DashboardIcon, ServiceIcon, MoneyCircleIcon } from 'tdesign-icons-vue-next'

export const clientNavRoutes = [
  {
    path: 'dashboard',
    name: 'ClientDashboard',
    meta: { title: '控制台', icon: shallowRef(DashboardIcon) },
  },
  {
    path: 'services',
    name: 'ClientServices',
    meta: { title: '我的服务', icon: shallowRef(ServiceIcon) },
  },
  // ...
]
```

Layout 侧边栏渲染时读取 `route.meta.icon`，直接传入 `t-menu-item` 的 `icon` prop。

| 场景 | 规格 | 布局要求 |
|---|---|---|
| 侧边导航图标 | 使用 TDesign menu 默认图标规格，视觉目标约 20px | 图标左、文字右，文字不换行，折叠时只保留图标 |
| 顶部账户入口 | 头像 + 用户名 + 余额，头像用 `t-avatar` | PC 显示完整信息，平板隐藏余额，手机只显示头像或菜单按钮 |
| 指标卡图标 | 使用 `var(--td-comp-size-xl)` 到 `var(--td-comp-size-xxl)` 建立浅底图标容器 | 图标与数字不要抢主次，图标在左或右上角固定 |
| 行内操作图标 | 使用 TDesign 小尺寸图标规格，视觉目标约 16px | 只用于复制、刷新、更多、外链等轻操作 |
| 空态图标 | 使用 `t-empty` 默认能力 | 不自绘大插画，避免 AI 味 |
| 移动端菜单触发 | 使用 TDesign 按钮尺寸与最小 44px 触控热区 | 放在 header 左侧，抽屉宽度沿用 Starter 移动端规则 |

导航分组间距：

- 一级分组使用 Starter `t-menu` 默认密度，不自定义行高。
- 分组标题与菜单项之间使用 `var(--td-comp-margin-xs)`。
- 分组之间使用 `var(--td-comp-margin-s)` 或 Starter 默认间距。
- 侧边栏宽度优先复用 Starter layout 变量与 `layout.less` 规则；如需保留 232px、72px、`min(84vw, 288px)` 这类视觉目标，必须集中写入 layout Less 变量或公共 class，不允许页面内散写。

### 11.6 间距与密度

不要凭感觉写间距。间距统一来自 Starter 与 TDesign token：

| 对象 | PC 端 | 平板端 | 手机端 |
|---|---|---|---|
| 内容区 padding | `var(--td-comp-paddingTB-xl) var(--td-comp-paddingLR-xl)` | `var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l)` | `var(--td-comp-paddingTB-s) var(--td-comp-paddingLR-s)` |
| 页面区块间距 | `var(--td-comp-margin-xl)` | `var(--td-comp-margin-l)` | `var(--td-comp-margin-m)` |
| 卡片内边距 | TDesign `t-card` 默认 | 默认或略收紧 | 仅在移动端收紧到 Starter 已有 mobile 规则 |
| 表单项间距 | TDesign `t-form` 默认 | 默认 | 保留 label 与控件间距，按钮全宽 |
| 按钮间距 | `t-space` 或 `var(--td-comp-margin-s)` | 自动换行 | 单列堆叠，间距 `var(--td-comp-margin-s)` |
| 表格与分页 | 表格下方 `var(--td-comp-margin-l)` | `var(--td-comp-margin-m)` | 分页简化，隐藏 jumper 和 page size |

实现约束：上表中的视觉目标必须落到 `src/style/variables.less`、`src/style/layout.less` 或公共 class 中，页面组件只能消费变量或 class，不直接散写固定 px。

密度规则：

- 财务、服务、工单列表默认使用中等密度，不使用超大卡片。
- Dashboard 可以稍微更松，但不超过 4 个主要指标。
- 服务控制台允许信息密度更高，但操作按钮必须分组。
- 手机端优先可读，不强行保留 PC 的多列布局。

### 11.7 响应式

断点建议沿用 Starter：

| 断点 | 范围 | 策略 |
|---|---|---|
| mobile | `< 768px` | 侧边栏改抽屉，内容区单列，表格卡片化，操作按钮进入底部或顶部全宽按钮 |
| tablet | `768px - 1199px` | 侧边栏使用 compact，内容区 2 列，详情页使用 70 到 80vw 抽屉 |
| desktop | `>= 1200px` | 标准侧边栏，内容区多列，表格固定列，服务控制台多栏布局 |

触控要求：

- 所有主操作点击热区不小于 `44px`。
- 移动端弹窗优先全屏或接近全屏。
- 表格小屏不得直接压缩到不可读，必须卡片化或横向滚动。

三端具体策略：

- PC 端：保留 Starter 固定侧边栏和 header，内容最大宽度不强制居中，数据表优先。
- 平板端：侧边栏默认 compact，筛选区使用 2 列，详情抽屉不超过视口 80%。
- 手机端：侧边栏进入抽屉，header 只保留菜单、页面标题、账户入口；表格转卡片，弹窗和抽屉接近全屏。
- 横屏手机和小平板：按 tablet 策略处理，但按钮区不得挤压到两行不可读。

### 11.8 可访问性

- 每个页面一个明确 `h1` 或等价页面标题。
- 表单项有 label，错误文案贴近控件。
- 键盘可访问：菜单、下拉、弹窗、确认框、上传按钮、分页。
- 焦点可见，不移除 outline 后无替代。
- 动效支持 `prefers-reduced-motion`。

## 12. API 与数据合约

### 12.1 不改接口

继续使用当前 `src/api/client.js` 的接口方法，迁移时可拆分为 TypeScript 模块，但不得改变 URL、HTTP method、请求参数含义。

API 域：

- `services`：服务列表、详情、续费、电源、重装、监控、NAT、安全组、VNC。
- `finance`：余额流水、财务流水、订单、账单、充值、支付。
- `coupons`：优惠券列表、公共券、领取。
- `referral`：推广概览、奖励、账户日志、提现。
- `tickets`：列表、详情、创建、回复、撤回、关闭、图片上传。
- `content`：公告、帮助、内容概览。
- `tools`：黑洞与安全工具。

### 12.2 响应处理

继续按统一响应结构处理：

```json
{ "code": 0, "message": "success", "data": {}, "timestamp": 1710000000 }
```

约束：

- `code = 0` 才视为成功。
- `40100` 走业务未登录跳转。
- HTTP `401` 走 token 清理与登录跳转。
- `422` 提取 `errors`。
- `429` 读取 `retry-after`。
- 第三方原始错误不得直接穿透给前端。

## 13. 迁移阶段计划

### Phase 0：盘点与冻结基线

目标：形成可回归基线。

任务：

- 输出当前路由清单和页面清单。
- 输出当前 Element Plus 使用清单（含 `el-` 标签、`ElMessage`、`ElMessageBox`、`provideGlobalConfig`、样式引入等所有引用点）。
- 输出当前 API 方法清单。
- **盘点 `src/views/` 目录**：v3 采用 `pages/`（路由入口）+ `views/`（业务逻辑）双层架构，需完整列出 `views/client/` 下的所有组件、composables、子页面及其 Element Plus 依赖。
- **追踪服务控制台子组件引用链**：`src/pages/client/service-console/index.vue` 仅 7 行，需完整列出其引用的云主机控制台、NAT 控制台、电源操作、重装、VNC、监控、NAT 转发、安全组、操作日志、流量包等子组件的实际文件路径和 Element Plus 使用情况。
- **盘点 `shared/components/` Element Plus 残留**：`shared/components/TicketChatPanel.vue` 重度依赖 Element Plus，`shared/components/StatusTag.vue` 存在 Element Plus 和 TDesign 两个版本。需列出所有残留点并与 `shared/user-v3/components/` 对比，确定统一改造策略。
- 记录当前 `npm run test`、`npm run build`、`npm run verify:refactor` 结果。

验收：

- 所有页面都有迁移归属。
- 所有 P0 功能都有验收点。
- 服务控制台子组件引用链完整，无遗漏。
- `shared/components/` Element Plus 残留清单完整，TicketChatPanel 有明确改造方案。

### Phase 1：生成 Starter 种子项目

目标：得到干净 TDesign Starter 基底。

任务：

- 首选：全局安装 `tdesign-starter-cli`，执行 `td-starter init frontend-user-v4-console`。
- 备用（CLI 不可用时）：`git clone` TDesign Starter 仓库到 `frontend-user-v4-console`，断开上游 git 关联，`npm install`。
- 记录生成项目的 `package.json`、`vite.config`、`src/router`、`src/layouts`、`src/store`、`src/style`。
- 与 `frontend-admin-v3` 的 TDesign 集成实践对照（注意 admin-v3 是手动集成，非 Starter 模板产物，仅借鉴组件用法和 token 体系）。

验收：

- `frontend-user-v4-console` 可 `npm install`。
- `frontend-user-v4-console` 可 `npm run build`。

### Phase 2：建立 console 新底座

目标：在 `frontend-user-v4-console` 内完成 TDesign Starter 工程骨架。

任务：

- 合并 Starter 的 TypeScript、ESLint、Stylelint、Less、TDesign 组件注册。
- 接入 `@caiwu/shared`。
- 保留 `vite` 代理：`/api`、`/uploads`、`/ws/vnc`。
- 保留构建预压缩插件。
- 替换 manualChunks 中的 Element Plus 分包为 TDesign 分包。
- 保留 `VITE_CONSOLE_ASSET_BASE_URL`、`VITE_CDN_ASSET_HOST`、`VITE_ASSET_BASE_URL` 资源 base 解析。

验收：

- 空路由或占位页可运行。
- `element-plus` 不再出现在 `package.json` 依赖。
- `vite.config` 不再引用 `ElementPlusResolver`。

### Phase 3：迁移 runtime、store、router

目标：保证登录、请求、路由守卫先稳定。

任务：

- 迁移 `request.ts`，将 `ElMessage` 替换为 `MessagePlugin`。
- 迁移 `session.ts`、`network.ts`、`siteBranding.ts`。
- 迁移 `user` store 与 `app` store。
- 建立 `/client/login`、`/client/register`、`/client/forgot-password`、`/client/login-as`。
- 建立 `/client` protected layout 与 `/client/dashboard` 占位页。
- 保留 public route redirect。
- 保留动态 import 失败后基于 sessionStorage 的一次性重载逻辑。

验收：

- 未登录访问 `/client/dashboard` 跳登录。
- 登录后访问登录页跳 dashboard。
- 代登录 route 不被 token 自动挡住。
- 401/40100 可正确清 token。
- 动态 import 失败时只重载一次目标路径，不进入无限刷新。

### Phase 4：迁移布局与共享组件

目标：搭建完整客户中心壳。**布局直接采用 Starter 模板，不自行设计。**

> **核心原则**：TDesign Vue Next Starter 的 `layouts/` 结构（`index.vue`、`LayoutHeader`、`LayoutSideNav`、`LayoutContent`）就是 v4-console 的页面壳。只做品牌化微调（logo、站点名、导航菜单项），不改变其布局逻辑、响应式规则、折叠行为。页面内容全部在 Starter 的内容区插槽内实现。

任务：

- 以 `td-starter init` 生成的 Starter `layouts/` 为唯一布局来源，在此基础上：
  - 替换 logo 和站点名为"创欧云"（从 `siteBranding` store 读取）。
  - 将 Starter 默认导航菜单项替换为 §10 的客户中心菜单结构。
  - 顶部账户区替换为：头像 + 用户名 + 余额 + 退出。
  - 移动端菜单触发和抽屉保留 Starter 默认行为，只替换菜单内容。
- **禁止事项**：
  - 禁止从零编写 `ClientLayout.vue` 替代 Starter layout。
  - 禁止参考 `frontend-admin-v3` 的自建 layout 结构（admin-v3 不是 Starter 模板产物，其 layout 是手写的）。
  - 禁止改动 Starter 的侧边栏宽度变量、折叠断点、内容区 padding 计算逻辑。
- 使用 TDesign icons 替换 Element icons。
- 图标尺寸、菜单密度、区块间距、按钮间距统一走 TDesign Starter token，不在页面散写固定像素。
- 优先复用 `shared/user-v3/components`：`StatusTag`、`PageScaffold`、`DataState`、`ResponsiveActionBar`、`AppDialog`、`AppDrawer`、`SideNavShell`、`DetailScaffold`、`MotionWrapper`。

验收：

- 桌面、平板、手机三断点导航可用。
- 菜单 active 状态与详情页归属正确。
- 页面切换无白屏。
- 布局结构与 Starter 模板一致，仅品牌化和菜单内容不同。无自造导航体系。

### Phase 5：迁移 P0 页面

目标：先打通核心业务闭环。

顺序：

1. 登录、注册、找回密码、代登录。
2. 控制台。
3. 我的服务。
4. 实例控制台。
5. 账单列表、账单详情。
6. 账户充值。
7. 确认下单、创建账单中。
8. 工单列表、创建、详情、回复。
9. 实名认证、个人资料。

验收：

- 用户能登录、查看服务、进入实例控制台。
- 用户能创建或恢复账单，并完成支付流程入口。
- 用户能充值并看到轮询状态。
- 用户能提交和回复工单。

### Phase 6：迁移 P1/P2 页面

目标：补齐全部历史入口。

页面：

- 订单记录。
- 充值记录。
- 余额流水。
- 优惠券中心。
- 推荐奖励。
- 产品目录。
- 系统公告、公告详情。
- 帮助中心、帮助详情。
- 管理工具。

验收：

- 所有当前 routes 中的页面都能访问。
- 历史详情链接不 404。
- 隐藏菜单页面仍可通过 URL 打开。

### Phase 7：清理 Element Plus 与收口验证

目标：确保没有混用和功能遗漏。

> **注意**：当前 v3 的 `scripts/check-source-health.mjs` 非常薄弱——仅检查 Unicode 编码损坏和 HTML 语法破损，完全没有 Element Plus 残留检测。Phase 7 需要大幅扩展此脚本，工作量不可低估。

任务：

- 搜索并移除所有 `element-plus`、`@element-plus/icons-vue`、`ElMessage`、`ElMessageBox`、`el-` 组件。
- 删除 `assets/styles/element/index.scss` 引用。
- 更新 `package.json`、lock 文件。
- 扩展 `scripts/check-source-health.mjs`：
  - 增加 Element Plus 禁止项检查：扫描 `src` 目录中 `from 'element-plus'`、`@element-plus/icons-vue`、`ElMessage`、`ElMessageBox`、`<el-` 标签引用。
  - 扫描范围扩展到 `src`、`package.json`、`vite.config.js`、`index.html`、`../shared/user-v3`。
  - 验证 shared 组件路径存在（如 `shared/user-v3/components/StatusTag.vue` 等），避免引用已迁移前的旧路径（如 `src/components/StatusTag.vue`）。
  - 增加 TDesign 依赖完整性检查：确认 `package.json` 包含 `tdesign-vue-next`、`tdesign-icons-vue-next`。
- 更新或补充关键测试，按以下矩阵迁移 v3 测试：

| v3 测试文件 | 迁移策略 |
|---|---|
| `verification-client-contract.test.mjs` | 实名认证合约测试——迁移到 v4，适配 TDesign 组件选择器 |
| `machineSpecOptions.test.mjs` | 机器规格选项测试——迁移到 v4，适配 TDesign 表单组件 |
| `ticketServiceOptionLabel.test.mjs` | 工单服务选项标签测试——迁移到 v4 |
| `invoiceDisplayContract.test.mjs` | 账单展示合约测试——迁移到 v4，适配 TDesign 表格/卡片 |
| `rechargeQrSuccessState.test.mjs` | 充值二维码成功态测试——迁移到 v4，适配 TDesign 消息/弹窗 |

验收命令：

```bat
cd frontend-user-v4-console
npm run typecheck
npm run test
npm run build
npm run verify:refactor
```

如果脚手架最终采用 Starter 原生脚本，至少保留：

```bat
npm run build
npm run build:type
npm run verify:refactor
```

## 14. 验收标准

### 14.1 功能验收

| 功能域 | 验收点 |
|---|---|
| 鉴权 | 登录、注册、找回、代登录、退出、401 跳转全部可用 |
| 路由 | 所有 `/client/*` 当前路由可访问，未登录保护正确 |
| 购买 | 确认下单、创建账单中流程可用 |
| 服务 | 列表、详情、电源、重装、VNC、监控、NAT、安全组、日志、续费可用 |
| 财务 | 账单、支付、充值、余额流水、充值记录、订单记录可用 |
| 工单 | 创建、回复、撤回、关闭、图片上传和预览可用 |
| 内容 | 公告、帮助列表与详情可用 |
| 账户 | 实名认证、个人资料、安全设置可用 |
| 工具 | `/client/tools` 历史入口保留 |

### 14.2 技术验收

- `package.json` 无 `element-plus` 与 `@element-plus/icons-vue`。
- `src` 中无 `from 'element-plus'`。
- `src` 中无 `@element-plus/icons-vue`。
- `src` 中无 `ElMessage`、`ElMessageBox`。
- 页面模板无 `el-table`、`el-dialog`、`el-drawer`、`el-form` 等 Element 标签。
- TDesign 全局样式、主题变量、布局样式通过 `src/style/index.less` 汇总。
- 主要布局复用 Starter `t-layout`、侧边导航、header、content 结构。
- 页面间距、卡片间距、按钮间距和图标尺寸不散落硬编码，优先来自 TDesign token 或 Starter Less 变量。
- 健康检查覆盖 `../shared/user-v3`，shared 基础组件无 Element Plus 残留且路径校验正确。
- 构建产物继续生成 `.gz` / `.br`。
- noindex 策略保留，控制台 `index.html` 包含 `meta name="robots" content="noindex,nofollow"`。

### 14.3 体验验收

- 桌面、平板、手机三断点无横向溢出。
- 表格在手机端有卡片化或横向滚动策略。
- 危险操作有二次确认。
- 所有异步操作有 loading、成功、失败反馈。
- 空态和错误态不出现白屏。
- 控制台核心路径首屏不依赖官网资源。

## 15. 风险与对策

| 风险 | 影响 | 对策 |
|---|---|---|
| 脚手架误写入 v3 目录 | 丢失或污染现有业务代码 | `td-starter init` 或 `git clone` 只允许写入 `frontend-user-v4-console`；v3 仅只读对照 |
| Element Plus 残留 | 依赖重复、样式冲突、包体膨胀 | 增加源码健康检查禁止项 |
| 支付状态遗漏 | 用户支付后状态不更新 | 对账单支付、充值轮询做专项回归 |
| VNC 迁移破坏 | 服务控制台核心能力不可用 | 保留 noVNC 依赖和 `/ws/vnc` 代理，单独验收 |
| NAT/安全组操作遗漏 | 用户无法管理实例网络 | 按 API 方法和 UI 操作逐项打勾 |
| 移动端表格不可用 | 手机客户无法操作财务和工单 | 列表页统一卡片化策略 |
| AI 味过重 | 页面像模板堆砌，业务焦点被装饰干扰 | 禁止无业务意义的 hero、渐变、插画和装饰图标，复用 Starter 视觉体系 |
| 重复造轮子 | 组件维护成本变高，TDesign 行为不一致 | 先查 Starter 和 shared 组件，只有业务语义明确时才新增组件 |
| public route 混入 console | SEO 和访问边界混乱 | 保留 `redirectToPublicSite`，console 不做 SEO |
| TDesign 组件 API 误用 | 构建失败或交互异常 | 实现前用 TDesign MCP 或官方文档核对组件属性 |
| `SideNavShell.vue` 品牌名硬编码 | shared 组件中写死"川购云"，与项目品牌"创欧云"不一致 | 迁移时改为从 `siteBranding` store 读取站点名称，不硬编码 |
| `TicketChatPanel.vue` Element Plus 依赖 | shared 中的工单聊天面板重度依赖 Element Plus（`el-button`、`el-upload`、`el-image`、`el-dialog`、`ElMessage`、`var(--el-*)` CSS 变量）。当前仅 admin-v3 使用，但位于 shared 模块，v4-console 未来可能复用 | 与 admin-v3 协调后统一改造为 TDesign 版本，放入 `shared/user-v3/components/`；v4-console 首期可不阻塞于此 |

## 16. 开发执行准则

- 每迁移完一个页面，必须在浏览器中同时打开 v3（旧）和 v4（新）的对应页面进行并排对照测试，逐项验证布局、数据、表单、异步操作、移动端适配、错误处理。对照通过后该页面才算迁移完成。
- 每次只迁移一个业务域，不跨域顺手重构。
- 页面逻辑优先迁移 composable，不把请求和业务判断堆进模板。
- 新页面使用 `script setup lang="ts"`，旧 JS 工具可渐进转 TS。
- 页面壳（layout）直接使用 TDesign Vue Next Starter 模板的 `layouts/`，只替换品牌和菜单，不自建导航体系。
- 先用 TDesign Starter 和 shared 组件，禁止为了”统一封装”再包一层无业务语义的基础组件。
- 视觉引导、图标、间距、圆角、阴影、响应式断点统一来自 Starter 和 TDesign token。
- 不做 AI 味重的装饰型页面，所有视觉元素都必须服务业务状态、操作路径或阅读层级。
- 所有用户可见文案使用简体中文，品牌名称统一为"创欧云"，从 `siteBranding` store 读取，禁止硬编码（`SideNavShell.vue` 中已存在硬编码"川购云"的反例，迁移时必须修正）。
- 不直接操作 localStorage，token 统一走 session runtime。
- 不硬编码后端地址，继续使用 env 和 Vite proxy。
- 不手工编辑 `文档/后端/后端API清单.md`。
- 不执行数据库初始化或迁移。
- 不删除、不移动、不覆盖 `frontend-user-v3-console`；如需发布切换，只调整部署入口或代理指向，不以清理 v3 作为当前任务步骤。

## 17. 最终交付物

重构完成时必须交付：

- TDesign Starter 底座后的 `frontend-user-v4-console`。
- 保持未删除、未覆盖的 `frontend-user-v3-console` 迁移基线。
- 全量 `/client/*` 页面迁移结果。
- Element Plus 清理结果。
- `package.json` 与 lock 文件更新。
- 关键回归测试更新。
- 构建和验收命令输出记录。
- 如路由、菜单或购买入口有变更，补充更新本文件或新增验收报告。

## 18. 下一步建议

按以下顺序推进：

1. 使用 `td-starter init`（或 `git clone` 备用）初始化 `frontend-user-v4-console`。
2. 对比 Starter 项目、`frontend-admin-v3` 和当前 v3 console，确定最终目录合并策略。
3. 先迁移 runtime、router、store、layout。
4. 再迁移 P0 页面。
5. 最后迁移 P1/P2 页面并清理 Element Plus。

本文件是开发执行基线。若实现过程中发现当前代码与本文不一致，以当前代码和可运行行为为准，并同步修正文档。
