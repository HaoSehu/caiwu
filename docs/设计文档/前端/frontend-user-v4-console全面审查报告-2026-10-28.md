---
status: needs-review
updated: 2026-10-28
owner: frontend-platform
---

# frontend-user-v4-console 全面审查报告（只读）· 2026-10-28

## 一、审查概况

- **范围**：`frontend-user-v4-console/` 全量前端——`src/pages`（55 个 `.vue`）、`src/layouts`（含 8 个布局组件）、`src/components`（含 `auth/`）、`src/domains`（全部 composable）、`src/router/modules/client.ts`（41 个 path 节点：30 个挂页面组件、1 个 `/client` 布局壳、10 个纯重定向；32 条具名路由中 14 条 `hidden: true`）、`src/style`（含 `layout.less`/`record-page.less`/`console-tables.less`）、根 `theme.css` 与 `dist` 产物。不含后端与 `shared/user-v3` 的既有实现（仅作为被依赖方标注影响面）。
- **方法**：8 个并行只读智能体，按「设计 × 视觉 × 交互 × 性能（构建体积 / 渲染运行时）」五视角拆分，半区 A（交易钱包 + 认证）与半区 B（服务 / 内容 / 账户 / 布局）全覆盖；每条发现强制附 `文件:行号`，无法核实的单独列「待确认」，不混入结论。主进程对 17 条关键结论做了二次实读复核（13 条成立、4 条撤销或部分撤销，见附录 B），并对 116 个引用路径做了存在性校验。
- **本阶段不改任何代码**：无 git 操作、无格式化、无回滚，全程只读；审查期间的唯一写盘动作是本报告与配套索引/登记。
- **证据口径**：行号取自 2026-10-28 工作区当前状态；`node_modules` 未安装，凡依赖 TDesign 运行时事件语义（如 `t-pagination` 是否双发 `change`、`t-button` 的 `loading` 是否阻断 click）的结论一律停留在「待确认」，不升级为缺陷。
- **文档基线**：结论对齐 `docs/FRONTEND.md`（§5 v4-console 约定：仅 TDesign、优先复用 `shared/user-v3` 的 DataState/LoadingState/StatusTag、移动端对话框形态由根 `theme.css` 统一适配、列表详情必须 `requestSeq` 竞态防护）与 `docs/DESIGN.md`（仅浅色主题、颜色/字体/间距/圆角/阴影必须映射 TDesign CSS 变量、禁止全局覆写圆角、§5.2 财务记录页禁止统计卡片与官网式 Hero）。

### 1.1 汇总统计

**原始报告自报条数**（8 位专家各自的维度内计数，含同一根因在不同页面的重复表述）：

| 维度 | 条数 | 高 | 中 | 低 |
| --- | --- | --- | --- | --- |
| 设计（信息层级 / 结构 / 令牌一致性） | 96 | 12 | 41 | 43 |
| 视觉（间距 / 对齐 / 配色 / 字体 / 图标 / 响应式） | 118 | 14 | 55 | 49 |
| 交互（反馈 / 状态 / 校验 / 边界 / 可达性） | 141 | 17 | 73 | 51 |
| 性能·构建与资源体积 | 26 | 3 | 8 | 15 |
| 性能·渲染与运行时 | 46 | 15 | 20 | 11 |
| **合计（未跨维度去重）** | **427** | **61** | **197** | **169** |

**本报告问题清单实际条目**（第二章 2.1–2.16 逐行编号，一条 = 一个可修复单元）：

| 维度 | 条数 | 高 | 中 | 低 | 待确认 | 不作评级 |
| --- | --- | --- | --- | --- | --- | --- |
| 设计（含跨维度归口） | 45 | 9 | 32 | 1 | 2 | 1 |
| 视觉 | 110 | 20 | 61 | 27 | 0 | 2 |
| 交互 | 117 | 26 | 67 | 17 | 7 | 0 |
| 性能·构建与资源体积 | 28 | 3 | 10 | 11 | 0 | 4 |
| 性能·渲染与运行时 | 62 | 22 | 29 | 8 | 0 | 3 |
| **合计** | **362** | **80** | **199** | **64** | **9** | **10** |

> 「不作评级」的 10 条是**正例记录、性能基线与已核实无问题项**（PR-42～PR-44、PB-23～PB-26 等），保留在清单中用于防止后续复审误报，不构成缺陷。

> 说明：427 行原始发现中，约 65 行是同一根因在不同维度/不同页面的重复表述（例：`.record-list-card` 悬空类名被设计、视觉、交互 3 个维度各自记录）。本报告按「一个可修复单元 = 一条问题」合并为 362 条编号条目，合并处标注「（跨维度重复）」，原维度归入「主要维度」；其中 9 条的严重度栏标「待确认」（清单内保留，但不参与严重度统计）。另有第五章「待确认清单」21 项，是上述 9 条之外还包含仅靠运行时/业务侧才能裁定的事项（如 TDesign 事件语义、后端字段语义、是否受某条设计约束），二者是不同层级，不重复计数。

### 1.2 覆盖清单（逐页面，无抽样）

#### 1.2.1 路由与页面覆盖

| 路由 | 页面组件 | 审查结论 |
| --- | --- | --- |
| `/client/login` | `src/pages/client-auth/login.vue` | 有发现（A-02、A-04、A-09～A-11、A-14、A-17、A-18、PR-35） |
| `/client/login-as` | `src/pages/client-auth/login-as.vue` | 有发现（A-12、A-23） |
| `/client/register` | `src/pages/client-auth/register.vue` | 有发现（A-02、A-07、A-11、A-15、A-16、A-23） |
| `/client/forgot-password` | `src/pages/client-auth/forgot-password.vue` | 有发现（A-02、A-10、A-11、A-17） |
| `/client/dashboard` | `src/pages/client/dashboard/index.vue` + `index.less` | 有发现（D-01～D-15、PR-09、PR-10、PR-28、PR-36、PR-37） |
| `/client/services` | `src/pages/client/services/index.vue` + `index.less` | 有发现（S-01～S-13、PR-08、PR-12、PR-13、PR-46） |
| `/client/catalog` | `src/pages/client/catalog/index.vue` | 有发现（S-14） |
| `/client/orders` | `src/pages/client/orders/index.vue`；`src/domains/finance/useOrders.ts` | 有发现（O-01～O-10、O-16、PB-04、PR-11、PR-19；O-07/O-08/O-10/PR-11/W-17 为三张记录列表页共用） |
| `/client/order/create` | `src/pages/client/order-create/index.vue` | 有发现（O-17～O-19） |
| `/client/orders/:id` | `src/pages/client/order-detail/index.vue` | 有发现（O-20～O-25） |
| `/client/invoices` | `src/pages/client/invoices/index.vue` | 有发现（I-04、I-11、O-07、O-08、O-10、PR-11、PR-18、W-17） |
| `/client/invoices/:id/pay` | `src/pages/client/invoice-detail/index.vue` + `index.less` | 有发现（I-01～I-03、I-06～I-10、I-13、O-16、PR-20、PR-43） |
| `/client/invoices/:id` | `src/pages/client/invoice-detail-view/index.vue` | 有发现（I-03、I-05、I-06、I-14、O-21～O-23） |
| `/client/checkout-resume` | `src/pages/client/checkout-resume/index.vue` | 有发现（I-15～I-17、O-17、O-19） |
| `/client/recharge` | `src/pages/client/recharge/index.vue` + `index.less` | 有发现（W-01～W-12、I-08、L-33、PR-21、PR-41、PR-43） |
| `/client/payments` | `src/pages/client/payments/index.vue` | 有发现（W-13～W-16、W-20、O-07、O-08、O-10、PR-11） |
| `/client/payments/:id` | `src/pages/client/payment-detail/index.vue` | 有发现（W-18、W-19、I-14、O-21～O-23） |
| `/client/coupons` | `src/pages/client/coupons/index.vue` | 有发现（P-01～P-08、PR-45） |
| `/client/referral` | `src/pages/client/referral/index.vue` | 有发现（P-09～P-18、PR-22） |
| `/client/tickets` | `src/pages/client/tickets/index.vue`；`src/domains/support/useTickets.ts` | 有发现（T-01～T-07、T-18、T-19、PR-38） |
| `/client/tickets/:id` | `src/pages/client/ticket-detail/index.vue` | 有发现（T-08～T-17、PR-17、PR-38、PR-39） |
| `/client/notices`、`/client/help` | `src/pages/client/{notices,help}/index.vue`（各 7 行薄壳）→ `content-list/ContentListPage.vue` | 有发现（N-01～N-08） |
| `/client/notices/:id`、`/client/help/:id` | `src/pages/client/{notice-detail,help-detail}/index.vue`（各 7 行薄壳）→ `content-detail/ContentDetailPage.vue` | 有发现（N-09～N-17、PR-14） |
| `/client/verification` | `src/pages/client/verification/index.vue` | 有发现（AC-01～AC-11、PR-43） |
| `/client/profile` | `src/pages/client/profile/index.vue` | 有发现（AC-12～AC-24、PR-27） |
| `/client/services/:id` | `src/pages/client/service-console/entry.vue` | 有发现（SC-05、PR-07） |
| `/client/services/:id/compute` | `src/pages/client/service-console/compute/index.vue` | 有发现（SC-04、SC-06～SC-08、PB-07） |
| `/client/services/:id/port-mapping` | `src/pages/client/service-console/nat/index.vue` | 有发现（SC-04、SC-06～SC-08、PB-07；与 compute 页同构，同条命中两页） |
| 服务控制台 9 个 tab、9 个 dialog | `service-console/components/**`；`src/domains/services/console/**` | 有发现（SC-01～SC-04、SC-09～SC-13、SC-15～SC-30、N-17、PR-04～PR-06、PR-15、PR-16、PR-29～PR-34） |
| `*`（404） | `src/pages/result/404/index.vue` | 有发现（E-01～E-06） |
| `/client/balance-logs` | `src/router/modules/client.ts:289`（deprecated 重定向） | 无页面实现，仅重定向（无发现） |
| `/client/ticket-conversations/:id` | `src/router/modules/client.ts:303`（redirect → `/client/tickets/:id`） | 无页面实现，仅重定向（无发现） |
| `/client/overview`、`/client/products`、`/client/trade`、`/client/wallet`、`/client/promo`、`/client/support`、`/client/account` | `src/router/modules/client.ts:65-66`、`:85-86`、`:104-105`、`:123-124`、`:148-149`、`:167-168`、`:192-193` | 无页面实现，均为纯重定向（分别 → dashboard / services / orders / recharge / coupons / tickets / verification） |
| `/client`（布局壳） | `src/router/modules/client.ts:54-56`（`component: Layout` + redirect） | 由 `src/layouts/index.vue` 承载，发现归入 1.2.2「布局外壳」 |
| `/`（根） | `src/router/modules/client.ts:26`（redirect → `/client/dashboard`） | 无页面实现，仅重定向 |

> 说明：上表「审查结论」是按问题清单里每条问题的**位置字段实际命中的页面文件**归口统计的，因此跨页问题（如 O-07/O-08/O-10/PR-11 同时命中三张记录列表页、SC-04/SC-06～SC-08 同时命中 compute 与 nat 两页、N-17 同时命中内容详情页与服务控制台面包屑）会在多行重复出现，属事实而非重复计数；全局与公共件的条目统一归入 1.2.2。

#### 1.2.2 全局与公共件覆盖

| 范围 | 文件 | 审查结论 |
| --- | --- | --- |
| 布局外壳 | `src/layouts/index.vue`、`components/{LayoutContent,LayoutHeader,LayoutSideNav,Header,SideNav,MenuContent,Breadcrumb,Content,FrameContent}.vue` | 有发现（L-01～L-29、PR-01、PR-23～PR-26） |
| 布局与基础样式 | `src/style/{layout,variables,reset,index,font-family}.less` | 有发现（L-30～L-34、C-06、C-23） |
| 记录页公共样式 | `src/pages/client/record-page.less`、`src/style/console-tables.less` | 有发现（O-11～O-16、I-12、C-03） |
| 记录页快捷筛选 | `src/pages/client/components/RecordQuickFilters.vue` | 有发现（O-09、PB-04） |
| 认证公共件 | `src/components/auth/{AuthShell,PasswordToggle}.vue`、`src/pages/client-auth/shared-auth.less` | 有发现（A-01、A-03、A-05、A-06、A-08、A-13、A-19～A-22） |
| 跨端状态组件 | `shared/user-v3/components/{DataState,LoadingState,StatusTag}.vue`（被 12 个页面复用） | 有发现（C-04、C-05、C-16、C-24） |
| 财务域 composable | `src/domains/finance/*`（useOrders/useInvoices/useRecharge/useRecords/dateFilters/usePaymentDetail…） | 有发现（O-01、O-13、O-14、I-01、W-07、W-08、PR-43、PR-44） |
| 服务域 composable | `src/domains/services/**`（含 `console/*` 17 个） | 有发现（S-09、SC-02、SC-03、SC-14、SC-17、SC-20、SC-21、SC-24、SC-27、PR-02、PR-03、PR-06～PR-08、PR-29、PR-31、PR-46） |
| 内容与账户域 composable | `src/domains/{content,account,support,marketing}/*` | 有发现（N-02、N-11、N-12、AC-05～AC-08、AC-19～AC-23、T-18、T-19、P-03、P-05、P-15～P-17、PR-45） |
| 请求与运行时 | `src/utils/request.ts`、`src/composables/{useDeviceLayout,useMediaQuery,useGeeTestCaptcha}.ts` | 有发现（PR-35、PR-40、PR-41、PR-42） |
| 跨页共性 | 全站（令牌、卡片、断点、三态、加载语言、无障碍、对话框契约、死代码） | 有发现（C-01～C-26） |
| 构建设置与产物 | `vite.config.ts`、`package.json`、`backend/scripts/frontend_budget.mjs`、`dist/**` | 有发现（PB-01～PB-26） |
| 根令牌 | `E:/caiwu/theme.css` | 有发现（C-01、C-02、C-13、C-15、L-26、L-35、L-36） |

**未覆盖项**：`public/` 下除 `vnc/` 与 `favicon.ico` 外的静态资源仅做体积归因；`backend/` 仅在判断前端死调用时被引用，未做后端审查。

## 二、问题清单（按页面组织）

> 列说明：**位置**给出可直接跳转的 文件:行号（相对仓库根 E:/caiwu）；**维度**取 设计 / 视觉 / 交互 / 性能·构建 / 性能·渲染；**严重**为 高 / 中 / 低；同一条问题被多维度同时记录时标注「（跨维度）」，取证以「主维度」那一行为准。

### 2.1 认证域（/client/login、/client/login-as、/client/register、/client/forgot-password）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| A-01 | frontend-user-v4-console/src/components/auth/PasswordToggle.vue:7-9 | 交互 | 高 | 组件根节点是原生 button type="button"，浏览器已把 Enter/Space 合成为 click，组件又额外绑 @keydown.enter.prevent 与 @keydown.space.prevent 各 emit 一次 update:model-value。键盘按一次 Enter 实际切换两次（显示→隐藏），等于对键盘用户完全失效。三页（登录/注册/找回密码）复用该组件。主进程实读复核成立。 |
| A-02 | frontend-user-v4-console/src/pages/client-auth/login.vue:67-69、:121-129；register.vue:111-113；forgot-password.vue:85-87 | 交互 | 高 | 三页提交按钮只有 :loading，无 :disabled；同时存在表单 @submit（login.vue:23、:80；register.vue:12；forgot-password.vue:12）与按钮 @click 双入口，@submit 路径不检查 loading。触发：点提交后立刻在输入框按 Enter → 第二个请求并发（登录接口有爆破计数风险，注册/重置会重复消费验证码）。 |
| A-03 | frontend-user-v4-console/src/pages/client-auth/shared-auth.less:49-51、:119-122、:143-146 | 视觉 | 高 | 同一行内控件高度不一致：输入框 :deep(.t-input) min-height 2.875rem（46px），验证码发送按钮与主提交按钮 height 3rem（48px），相差 2px，验证码行输入框与按钮顶/底边肉眼不齐。 |
| A-04 | frontend-user-v4-console/src/pages/client-auth/login.vue:452-465 | 视觉 | 高 | 页面 scoped 内把 .client-auth-code-row 重定义为 flex + gap .75rem 并把 :deep(.t-button) 改为 height:auto，覆盖 shared-auth.less:113-122 的 grid + 48px；而 register.vue:348-350、forgot-password.vue:302-304 只 @import，仍是 grid。同一验证码行在三个认证页呈两套布局、两套高度（gap 12px vs 8px）。 |
| A-05 | frontend-user-v4-console/src/pages/client-auth/shared-auth.less:13、14、30、37、51、71、121、144、150、157 | 设计 | 中 | 认证域整文件脱离令牌：字体、间距、控件高度全为裸 rem（.875rem / .8125rem / 1.25rem / 2.875rem / 3rem），仅颜色走 --td-*；theme.css 中并无 13px 号段。量化：该文件 bareRem 20 处、令牌化间距 0 处。 |
| A-06 | frontend-user-v4-console/src/components/auth/AuthShell.vue:32、:46、:405-407 | 设计 | 中 | 标题层级倒置：h1 给右侧营销文案（auth-guide__title，:46），表单标题只是 h2（:32）；且 ≤991px 时 .auth-guide 整块 display:none（:405-407），页面唯一 h1 随之消失，读屏大纲从 h2 开始。 |
| A-07 | frontend-user-v4-console/src/components/auth/AuthShell.vue:406；register.vue:2-11 | 设计 | 中 | .auth-guide 在 ≤991px 被隐藏后，注册页仅有的转化入口「查看产品目录」CTA 在手机端不可达。 |
| A-08 | frontend-user-v4-console/src/components/auth/AuthShell.vue:349-412 | 视觉 | 中 | 以 @screen-sm-max（991px）作移动形态切换点：768–991px 已切单列、隐藏 guide、页脚由 center 改 left（:409-411）。同视口下控制台内页仍是桌面形态（theme.css:732-735 仅收窄卡片 padding），两套布局语言并存；页脚纯对齐跳变无结构依据。 |
| A-09 | frontend-user-v4-console/src/pages/client-auth/login.vue:100、:102-108 | 交互 | 中 | 验证码项 label for="login-code" 对应的 t-input 没有 id，label 断链：点标签不聚焦、读屏不关联。同页账号项 for="login-code-account"（:84）与 id（:86）一致，register.vue:29/31、forgot-password.vue:29/31 亦正确，属单点遗漏。 |
| A-10 | frontend-user-v4-console/src/pages/client-auth/login.vue:202-217、:228-240、:273-289；forgot-password.vue:140-170、:244-272 | 交互 | 中 | 每页两套校验并存：t-form :rules（trigger 全为 blur）与手写 validateForm 逐字段重复实现，文案写两处。只改一处即导致 blur 与提交时提示不一致。 |
| A-11 | frontend-user-v4-console/src/pages/client-auth/login.vue、register.vue、forgot-password.vue、login-as.vue（全文件） | 交互 | 中 | 四页 aria-/role 命中 0 处；校验失败只靠 t-form 视觉红字与 MessagePlugin toast，无 role="alert" / aria-live，读屏用户得不到失败通知。 |
| A-12 | frontend-user-v4-console/src/pages/client-auth/login-as.vue:19-24、:47、:149-162 | 交互 | 中 | loading/success/error 状态机容器无 aria-live / role="status" / 焦点转移；requestLoginAsCode 有 10s 超时（:158-160）→ handleMissingMessage，超时后仅文案切换 + toast，读屏用户不知发生了什么。 |
| A-13 | frontend-user-v4-console/src/components/auth/PasswordToggle.vue:22-30 | 视觉 | 中 | .password-toggle 无 width/height、padding 2px，图标 1em，有效热区约 20–24px。theme.css:760-771 的 44px 触控规则只作用于 #app .t-button，不覆盖这个裸 button。 |
| A-14 | frontend-user-v4-console/src/pages/client-auth/login.vue:67-69 与 :121-129 | 视觉 | 中 | 密码模式提交按钮无 .client-auth-submit 类，验证码模式有；该类提供 margin-top 8px（shared-auth.less:139-141），切换 tab 时按钮与上方间距跳变 8px。 |
| A-15 | frontend-user-v4-console/src/pages/client-auth/register.vue:44-55、:57-68、:46、:59 | 视觉 | 中 | 六个字段单列串联，选填项（nickname、referral_code）与必填项在字号、字重、间距、分组上完全一致，仅靠红色星号区分；且两个选填项被夹在必填项之间，长表单中极易误认为必填。 |
| A-16 | frontend-user-v4-console/src/pages/client-auth/register.vue:13-42、:44-88、:101 | 交互 | 低 | 全表单仅确认密码项有 @enter="submitForm"，账号/验证码/用户名/推广码/密码均无 @enter；login 则明确给账号与密码加了 @enter，两页行为不一致。 |
| A-17 | frontend-user-v4-console/src/pages/client-auth/login.vue:346-355；forgot-password.vue:179-189 | 交互 | 低 | login 倒计时到 0 只 clearTimer 未把 countdown 复位为 0，forgot-password 复位了；当前因判断条件 countdown>0 侥幸无影响，属两页实现漂移。 |
| A-18 | frontend-user-v4-console/src/pages/client-auth/login.vue:439-443 | 交互 | 低 | onMounted 用 route.query.account 只回填密码表单 form.account，不回填 codeForm.account；从带 account 参数的链接进入后切「验证码登录」，账号为空需重输。 |
| A-19 | frontend-user-v4-console/src/pages/client-auth/shared-auth.less:36-38、:95-110 | 视觉 | 低 | 表单项 margin-bottom 1.25rem（20px）与 links 区 margin 1.375rem（22px）并存，同表单两种段后距。 |
| A-20 | frontend-user-v4-console/src/pages/client-auth/shared-auth.less:41-43 | 视觉 | 低 | :deep(.t-form__item-with-extra) padding-bottom 借用 --td-line-height-body-small 行高令牌当间距，语义错位，字号变化时间距随之漂移。 |
| A-21 | frontend-user-v4-console/src/components/auth/AuthShell.vue:22-25 | 交互 | 低 | 返回按钮是原生 button，仅靠文本「返回」，无 aria-label；图标型按钮缺可读名。 |
| A-22 | frontend-user-v4-console/src/pages/client-auth/shared-auth.less:19-24 | 交互 | 低（待确认） | 必填星号由 .client-auth-label.is-required::before {content:'*'} 纯 CSS 生成，DOM 无 aria-required、无 sr-only「必填」；t-form-item 是否自行输出 aria-required 需运行时确认。 |
| A-23 | frontend-user-v4-console/src/pages/client-auth/register.vue:174；login-as.vue:58 | 交互 | 中 | 两处 redirectPath 校验口径不一致（register 版未排除以 // 开头的协议相对地址），存在开放重定向面；需按同一函数收敛。 |

### 2.2 订单域（/client/orders、/client/order/create、/client/orders/:id）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| O-01 | frontend-user-v4-console/src/domains/finance/useOrders.ts:82-105、:176-202 | 交互 | 高 | cancelOrder 用 DialogPlugin.confirm 且 cancelingIds.add 与 setConfirmLoading(true) 都在 onConfirm 内，**打开确认框前不占位**。连点「取消」可叠出多个确认框，逐个确认即并发多次 cancelOrder，第二次起必然失败并叠加错误 toast。列表页与详情页两处实现同病。主进程实读复核成立。 |
| O-02 | frontend-user-v4-console/src/pages/client/orders/index.vue:8-18 | 交互 | 中 | 关键词框只有 @enter（:12）与 @clear（:13）触发 handleSearch，suffixIcon 槽只放 SearchIcon，无 @click、无显式搜索按钮；输入订单号后找不到可点入口，只能猜要按 Enter。 |
| O-03 | frontend-user-v4-console/src/pages/client/orders/index.vue:41-43 | 交互 | 中 | 「去产品目录选购」包在 v-if="!hasActiveFilters" 内，一旦设了关键词/状态/日期，空列表时没有任何「清除筛选」入口，用户只能逐个手动清四个控件。 |
| O-04 | frontend-user-v4-console/src/pages/client/orders/index.vue:111-120、:141-169 | 交互 | 中 | 移动卡片 article 挂 role="link" + tabindex="0"，只处理 @keydown.enter.self.prevent，不处理 Space（键盘按 Space 无反应）；卡片内又嵌套三个 t-button，交互元素嵌套打断读屏语义。 |
| O-05 | frontend-user-v4-console/src/pages/client/orders/index.vue:240-248 | 视觉 | 中 | 表格列宽合计约 77rem（1232px）；可用宽 = 视口 − 侧栏 232px（theme.css:267-271）− 页面 48px（layout.less:138-140）→ 1366 仅 1086px、1440 仅 1160px，必然横向滚动且 fixed-right 操作列压内容。 |
| O-06 | frontend-user-v4-console/src/pages/client/orders/index.vue:94-95、:158-159、:260-264 | 设计 | 中 | 两种阻塞语义等权平铺：「支付窗口已关闭」（时效型）与「无可支付账单」（数据缺失型）共用同一个 --td-text-color-placeholder 灰字，且与主按钮同排，用户无法判断该等待还是该联系客服。 |
| O-07 | frontend-user-v4-console/src/pages/client/orders/index.vue:32；invoices/index.vue:32；payments/index.vue:26 | 设计（跨维度） | 中 | 三个列表页使用 section.record-list-card，该类在 src 内零定义（src/style 与 record-page.less 均无），列表容器直接贴 --td-bg-color-page（#f5f7fb），无背景/边框/圆角/阴影，与上方 .record-card（record-page.less:8-13）割裂。主进程 grep 复核成立。 |
| O-08 | frontend-user-v4-console/src/pages/client/orders/index.vue:3-6；invoices/index.vue:3-6；payments/index.vue:3-6 | 设计 | 中 | 筛选被切成「卡外快捷标签 + 卡内工具栏」两块，同一功能两个容器，视觉层级与分组语义冲突。 |
| O-09 | frontend-user-v4-console/src/pages/client/components/RecordQuickFilters.vue:50、:53-59 | 视觉 | 中 | .quick-filter-tag 恒定 44px 高，桌面端与相邻输入框/下拉不齐；:50 的 margin-bottom 又与 record-page.less:1-6 的 gap 叠成 24px。 |
| O-10 | frontend-user-v4-console/src/pages/client/orders/index.vue:253-258；invoices/index.vue:228-233；payments/index.vue:179-184；record-page.less:40-48 | 视觉 | 中 | 金额单元格样式四份复制，且详情页（invoice-detail-view/index.vue:323-328）再复制一份；改一处必漏三处。 |
| O-11 | frontend-user-v4-console/src/pages/client/record-page.less:15-24 | 视觉 | 中 | .record-toolbar 四列最小宽合计 46rem+24px≈760px，而 ≤991px 可用宽度约 744px，必然换行挤压；且两列断点写成 @screen-md-rem(992px)，在 768–991px 区间不生效（断点方向反了）。 |
| O-12 | frontend-user-v4-console/src/style/console-tables.less:50-53 | 视觉 | 中 | 列表页表头字重 400，而 theme.css:198-202 规定 600；详情页内嵌 t-table（invoice-detail-view/index.vue:140-147）不走 .record-table 因而保持 600 —— 同站表头两种字重。 |
| O-13 | frontend-user-v4-console/src/domains/finance/useRecords.ts:446-449；useOrders.ts:157 | 交互 | 中 | 列表页同时用 toast 与 DataState 双通道反馈同一失败，内容重复且互相遮挡。 |
| O-14 | frontend-user-v4-console/src/domains/finance/dateFilters.ts:14-19、:40；useRecords.ts:477-479 | 交互 | 中 | QUICK_FILTER_OPTIONS 把业务语义（status=pending/待支付）混入通用日期筛选常量并被订单与账单共用；useRecords.ts:477-479 再硬编码 status=0 映射，两处口径分散；「本月」end 取当月最后一天，包含未来日期。 |
| O-15 | frontend-user-v4-console/src/pages/client/record-page.less:76-81、:102、:246 | 视觉 | 低 | 同一文件内 1px 边框写 0.875rem/0.0625rem/thin 三种字面量。 |
| O-16 | frontend-user-v4-console/src/pages/client/orders/index.vue:299-317；record-page.less:135-158；invoice-detail/index.less:517-543、:660-685 | 视觉 | 低 | 标题左竖条伪元素的圆角两处写 0.125rem 字面量、两处用 var(--td-radius-small)，同语义两种写法。 |
| O-17 | frontend-user-v4-console/src/pages/client/record-page.less:8-13；components/auth/AuthShell.vue:226-233；checkout-resume/index.vue:196-208；order-create/index.vue:25-39 | 设计 | 中 | 卡片外观四份手写（背景+边框+圆角+阴影），与 theme.css:186-192 的全局 t-card 规范并行维护。 |
| O-18 | frontend-user-v4-console/src/pages/client/order-create/index.vue:41-62 | 视觉 | 中 | 全局已给 .t-card__body 设 padding 18px/24px（theme.css:190-192），页面又在卡片内容容器上加 padding TB-xl/LR-xl，实际内边距叠加为 36px/48px。 |
| O-19 | frontend-user-v4-console/src/pages/client/order-create/index.vue:29；checkout-resume/index.vue:199 | 视觉 | 低 | 使用 --td-comp-size-xxxxxl（5 个 x）作高度，而布局层 layout.less:143/144/206/209/228 用 --td-comp-size-xxxl（3 个 x）。两者均为 TDesign 合法令牌（dist 中分别定义为 td-size-16 / td-size-15），但同站同一语义高度混用三个量级，实际渲染差 8–16px。 |
| O-20 | frontend-user-v4-console/src/pages/client/order-detail/index.vue:2、:266-280 | 视觉 | 中 | 根类 .order-detail-page 在页面 scoped 内无任何规则，容器无 flex/gap，间距全靠零散 margin-bottom 拼接；payment-detail/index.vue:2、invoice-detail-view/index.vue:2 同病。 |
| O-21 | frontend-user-v4-console/src/pages/client/order-detail/index.vue；invoice-detail-view/index.vue；payment-detail/index.vue:31-97 | 视觉 | 中 | 三个同层级详情页三种容器模式：两个用 t-tabs，一个用两张 t-card #title 拼装，区块切分与标题语义不一致。 |
| O-22 | frontend-user-v4-console/src/pages/client/order-detail/index.vue:410-419；payment-detail/index.vue:219-229；invoice-detail-view/index.vue:361-371 | 视觉 | 中 | 三个详情页的移动断点用 @screen-xs-rem（640px），与控制台统一的 767.98px 不一致，640–767px 区间详情页仍用桌面排版。 |
| O-23 | frontend-user-v4-console/src/pages/client/order-detail/index.vue；payment-detail/index.vue；invoice-detail-view/index.vue（面包屑 @click） | 交互 | 中 | 三个详情页的自绘面包屑用 @click 跳转，非 a/button，键盘不可聚焦、无 Enter 响应。 |
| O-24 | frontend-user-v4-console/src/pages/client/order-detail/index.vue:397-401、:403-408 | 视觉 | 低 | 内联空态自写两套类名（.related-empty 与 .order-detail-empty），属性完全相同；payment-detail/index.vue:214-217 的 .detail-empty 与 invoice-detail-view/index.vue:356-359 的同类甚至模板未使用。 |
| O-25 | frontend-user-v4-console/src/pages/client/order-detail/index.vue:95、:99 | 交互 | 低 | 创建/支付时间原样输出后端字符串，未做本地化格式化；列表页有格式化而详情页没有，同站两种时间呈现。 |

### 2.3 账单域（/client/invoices、/client/invoices/:id/pay、/client/invoices/:id、/client/checkout-resume）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| I-01 | frontend-user-v4-console/src/domains/finance/useInvoices.ts:565-585；invoice-detail/index.vue:285-289 | 交互 | 高 | 选支付宝 + 勾选余额抵扣 + 余额 ≥ 应付时，直接转 handlePayByBalance 全额扣余额，无任何二次确认；而按钮文案此时恰好从「去支付」变为「支付」，用户预期是走支付宝却直接扣了余额。主进程实读复核成立。 |
| I-02 | frontend-user-v4-console/src/pages/client/invoice-detail/index.vue:216；useInvoices.ts:237、:269 | 交互 | 高 | 支付卡 v-if="showPayActions && hasPayMethods"；待支付但 pay_methods 为空时，整张卡（含**唯一**解释性 t-alert「暂无可用支付方式」）一起不渲染，页面变成只有金额的空白页。主进程实读复核成立。 |
| I-03 | frontend-user-v4-console/src/pages/client/invoice-detail/index.vue:144-151；invoice-detail-view/index.vue:42-91 | 设计 | 高 | 同一业务的「账单详情」两套信息层级：支付页有「本次应付 + 倒计时」主视觉，详情页把 12 个 detail-kv-item 两列等权平铺、完全没有金额主视觉。两页靠路由区分但用户认知上是同一单据。 |
| I-04 | frontend-user-v4-console/src/pages/client/invoices/index.vue:214-223 | 视觉 | 高 | 列宽合计约 82rem（1312px），1366 视口可用 1086px，横向溢出 226px，必然横滚；「优惠」与「优惠券」两列同源却各占约 10rem，且只有 coupon 列设 ellipsis、.record-table 无 table-layout:fixed，内容长度一变列宽即抖。 |
| I-05 | frontend-user-v4-console/src/pages/client/invoice-detail-view/index.vue:244-266 | 交互 | 中 | 详情加载无 requestSeq 竞态防护，违反 docs/FRONTEND.md §5「列表详情必须做 requestSeq 竞态防护」；快速切换两张账单时可显示上一条数据。payment-detail/index.vue:130-152 同病。 |
| I-06 | frontend-user-v4-console/src/pages/client/invoice-detail-view/index.vue:208-215；useInvoices.ts:115-117 | 交互 | 中 | 可支付判据不同源：页面用 detail.can_pay，支付逻辑用 payment_window_expired 计算，两者口径分裂，出现「按钮可点但提交被拒」或反之。 |
| I-07 | frontend-user-v4-console/src/pages/client/invoice-detail/index.less（58 个顶层选择器） | 视觉 | 中 | 约 12 个选择器在模板中 grep 为 0（含整套 .payment-flow 三列进度条，:185-220、:418-447、:590-603、:632-652），属死样式；其中 :15 的 .pay-toolbar__main 还带 h1 样式，说明该页原本应有页标题但实际没有。 |
| I-08 | frontend-user-v4-console/src/pages/client/invoice-detail/index.less:249-358；recharge/index.less:221-288 | 视觉 | 中 | 同名 .pay-method-card 两份实现存在 8 处差异（gap margin-m vs margin-s、min-height 4.25rem vs 4.75rem、paddingLR-l vs LR-m、背景 color-container vs color-component、前者有 hover/focus-visible + translateY 而后者无、svg 1.25rem vs 1.375rem、__check 有内圈 vs 无内圈、__text padding-right 1.5rem vs 无）。 |
| I-09 | frontend-user-v4-console/src/pages/client/invoice-detail/index.vue:224-249 | 设计 | 中 | 支付方式选择器全站三套实现：此处 t-card 列表、recharge 移动端 button.pay-method-card、recharge 桌面端 t-button，选中态与热区各不相同。 |
| I-10 | frontend-user-v4-console/src/pages/client/invoice-detail/index.vue:27-65、:67-103、:105-138 | 设计 | 中 | 三个区块顺序随账单类型跳动（是否显示优惠、是否显示余额抵扣、是否显示支付方式），页面骨架不稳定，用户每次要重新找位置。 |
| I-11 | frontend-user-v4-console/src/pages/client/invoices/index.vue:87、:136、:235 | 设计 | 中 | 与 O-06 同根：账单列表把时效型与数据型阻塞同样呈现。 |
| I-12 | frontend-user-v4-console/src/style/console-tables.less:10 | 视觉 | 低 | #app .client-invoices .invoice-table-card 类在模板中不存在，连带 8 处规则为死样式。 |
| I-13 | frontend-user-v4-console/src/pages/client/invoice-detail/index.less:151-156、:167-171 | 视觉 | 中 | .pay-shell 双列 minmax(0,1fr) minmax(20rem,23rem)，单列断点写 @screen-md-rem(992px)，992–1100px 仍是两栏，左栏实际只有约 600px，金额与说明被压窄。 |
| I-14 | frontend-user-v4-console/src/pages/client/invoice-detail-view/index.vue:81、:85、:89；payment-detail/index.vue:56、:60 | 交互 | 低 | 详情页时间原样输出后端字符串，未本地化。 |
| I-15 | frontend-user-v4-console/src/pages/client/checkout-resume/index.vue:18-20、:52 | 交互 | 高 | 重试按钮 :loading="retrying" 而 retrying 全文件从未置 true（仅声明与使用两处），永不转圈、永不禁用；配合 resumeCheckout（:159-182）无重入守卫，连点可并发多次恢复请求。主进程实读复核成立。 |
| I-16 | frontend-user-v4-console/src/pages/client/checkout-resume/index.vue:211-232 | 视觉 | 中 | 与 O-18 同根：卡片内容容器再叠一层 padding。 |
| I-17 | frontend-user-v4-console/src/pages/client/checkout-resume/index.vue:86 | 交互 | 低 | 失败文案把技术原因（如接口 500）直出给用户，无兜底话术与「联系客服」出口。 |

### 2.4 钱包域（/client/recharge、/client/payments、/client/payments/:id）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| W-01 | frontend-user-v4-console/src/pages/client/recharge/index.vue:335-348（移动）、:364-388（桌面） | 交互 | 高 | 充值确认框既无 setConfirmLoading 也无防重入；移动端 onConfirm 里 dialog.destroy() 后 void handleCreateOrder() 不 await，确认按钮立即失效但请求已在飞，连点可创建多张充值单（资金域最高风险之一）。 |
| W-02 | frontend-user-v4-console/src/pages/client/recharge/index.vue:66-85（移动）、:178-195（桌面） | 交互 | 高 | 移动端支付方式按钮缺 :disabled/:loading（桌面端有）；下单进行中切换渠道会触发 useRecharge.ts:460-464 清空 paymentPayload，导致二维码与订单脱钩，用户扫了一个已废弃的单据。 |
| W-03 | frontend-user-v4-console/src/pages/client/recharge/index.vue:270-280；src/style/variables.less:32-35 | 视觉 | 高 | const isMobileScreen = useMediaQuery('(max-width: 47.98rem)') 即 767.68px，而全仓 CSS 移动断点为 767.98px；767.69–767.98px 区间 JS 判桌面、CSS 判移动，形态与样式错配。全仓唯一偏离该断点处。主进程实读复核成立。 |
| W-04 | frontend-user-v4-console/src/pages/client/recharge/index.vue:5-134（移动）、:136-252（桌面）、:137-140 | 设计 | 中 | 用 JS 断点复制整页 DOM（两套完整模板并存），且桌面分支没有页面标题（移动分支 :8 有 h1「在线充值」），同页主标题随视口消失。 |
| W-05 | frontend-user-v4-console/src/pages/client/recharge/index.vue:11-14、:137-140；index.less:23-33、:123-132 | 视觉 | 中 | 余额在移动端是主卡片（.mobile-balance-card），在桌面端只是一行灰字（.desktop-balance-line），同一信息两种权重；充值时用户最需要看的数字在桌面反而最弱。 |
| W-06 | frontend-user-v4-console/src/pages/client/recharge/index.less:75-87、:206-214 | 视觉 | 中 | .amount-payable 与 .amount-payable--mobile 两套应付金额样式，字号与颜色不一致。 |
| W-07 | frontend-user-v4-console/src/domains/finance/useRecharge.ts:85-86 | 交互 | 中 | amount 初值 10 与 inputAmount 初值 20 不一致，首屏展示与提交值可能不同源。 |
| W-08 | frontend-user-v4-console/src/domains/finance/useRecharge.ts:92、:135、:336-375 | 交互 | 低 | 三个函数无调用方（死代码），且其中仍带旧的支付方式清理逻辑，易被误接。 |
| W-09 | frontend-user-v4-console/src/pages/client/recharge/index.vue:68-191 | 性能·渲染 | 中 | 模板调用 paymentOptionKey 共 11 次，每次重建字符串；与 services/index.vue:74-284 的 resolveServiceName×7 / findListSpecValue×4 同属「模板承担视图模型」。 |
| W-10 | frontend-user-v4-console/src/pages/client/recharge/index.less:495；record-page.less:256 | 视觉 | 低 | 同为分隔线，一处 border-bottom thin dashed、一处 solid，且宽度写法不同。 |
| W-11 | frontend-user-v4-console/src/pages/client/recharge/index.less | 设计 | 中 | 该文件 bareRem 22 处，未令牌化。 |
| W-12 | frontend-user-v4-console/src/pages/client/recharge/index.vue（桌面分支） | 交互 | 待确认 | 移动端有充值协议勾选，桌面分支未找到对应控件；是否为设计上有意区分需产品确认。 |
| W-13 | frontend-user-v4-console/src/pages/client/payments/index.vue:166-174 | 视觉 | 中 | 列宽合计约 72rem，1366 视口可用 1086px，仍横向溢出（三处列表页中溢出最少但仍溢出）。 |
| W-14 | frontend-user-v4-console/src/pages/client/payments/index.vue:94-98、:186-190、:192-195 | 交互 | 中 | 「关联账单」用无 href 的 a 实现，不可聚焦、无 hover/focus 反馈；.payment-invoice-btn 未覆盖 theme.css:209-212 的 min-width:72px；同一「关联账单」在全站有三套实现。 |
| W-15 | frontend-user-v4-console/src/pages/client/payments/index.vue:72-81 | 交互 | 中 | 卡片 role="link" 的 @keydown.enter.prevent 缺 .self 修饰（orders:119、invoices:101 都有），卡片内按钮上按 Enter 会同时触发按钮与卡片导航。 |
| W-16 | frontend-user-v4-console/src/pages/client/payments/index.vue:19-21 | 交互 | 待确认 | 用 PAYMENT_GATEWAY_OPTIONS 绑定 filters.type，渠道筛选与支付方式筛选语义是否同一，需对后端参数确认。 |
| W-17 | frontend-user-v4-console/src/domains/finance/useRecords.ts:315-322；invoices/index.vue:41 | 交互 | 中 | hasActiveFilters 不包含 quickFilter，选中「待支付」后列表为空，空态仍显示「暂无账单记录」而非「筛选无结果」，并且不提供清除筛选。 |
| W-18 | frontend-user-v4-console/src/pages/client/payment-detail/index.vue:130-152 | 交互 | 中 | 与 I-05 同根：详情加载无 requestSeq 防护。 |
| W-19 | frontend-user-v4-console/src/pages/client/payment-detail/index.vue:183-186、:214-217 | 视觉 | 低 | .payment-detail-card / .payment-related-card 对最后一张卡也加 margin-bottom，页面底部多出 12px；.detail-empty 定义了但模板未使用。 |
| W-20 | frontend-user-v4-console/src/pages/client/payments/index.vue:26 | 设计 | 中 | 与 O-07 同根：record-list-card 悬空类名。 |

### 2.5 服务域（/client/services、/client/catalog）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| S-01 | frontend-user-v4-console/src/pages/client/services/index.less:141-164 | 视觉 | 高 | 「开通中」加载环用 --td-error-color-1/-2/-color（错误色系）表达进行中状态，与真正的失败红同色；用户无法区分「正在开通」与「开通失败」。 |
| S-02 | frontend-user-v4-console/src/pages/client/services/index.less:302-321、:363-391 | 视觉 | 高 | 自绘 button（.service-action-console、.__more）高度 1.875rem（30px），.service-ip-button min-height 2rem（32px），均低于 theme.css:209-212 的 min-width:72px 与 44px 触控基线；theme.css:760-771 的 44px 规则只覆盖 .t-button，自绘按钮全部漏网。 |
| S-03 | frontend-user-v4-console/src/pages/client/services/index.less:540-546、:548-552 | 视觉 | 高 | ≤767.98px 时 display:none 掉 .service-filter-actions 与 .service-view-toggle（移动端无法切换视图、无法清筛选），但紧接着 :548-552 又为卡片视图写移动端样式 —— 保留了容器却删掉了操作入口。 |
| S-04 | frontend-user-v4-console/src/pages/client/services/index.less:33-38 | 视觉 | 中 | 卡片栅格 minmax(24rem,1fr)，992–1200px 内容区只排得下 1 列，平板横向大片空白。 |
| S-05 | frontend-user-v4-console/src/pages/client/services/index.less:65-93 与 :95-108 | 视觉 | 中 | 同一语义（推荐/标签）两种视觉：三角旗（12px 文字塞 28px 高）与方形徽标，形状与可读性都不一致。 |
| S-06 | frontend-user-v4-console/src/pages/client/services/index.less:348-357 | 视觉 | 中 | .service-row-foot 用 margin-top:auto 撑开，分隔线随内容长度上下浮动，长卡片与短卡片行基线不对齐。 |
| S-07 | frontend-user-v4-console/src/pages/client/services/index.vue:18-36 | 交互 | 中 | 三个 t-select 的 @change 直接绑 handleSearch，无 debounce；连续改筛选即连续发请求，且与分页请求互相打断。 |
| S-08 | frontend-user-v4-console/src/pages/client/services/index.vue:266-276 | 交互 | 待确认 | t-pagination 同时绑 @change 与 @page-size-change，若 TDesign 在改页大小时同时触发 change 则一次操作发两次请求（node_modules 未安装，无法从源码确认）。 |
| S-09 | frontend-user-v4-console/src/domains/services/useServiceCenter.ts:381-400（:389-392）；services/index.vue:296-304 | 交互 | 高 | 与 SC-02 同根：续费弹窗加载预览后无条件用服务端 default_cycle / selected_user_coupon_id 覆盖用户刚选的 billing_cycle 与 user_coupon_id，而 @change 又重调加载 → 选了又跳回，用户永远改不动周期与优惠券。 |
| S-10 | frontend-user-v4-console/src/pages/client/services/index.vue:74-284 | 性能·渲染 | 中 | 模板内 resolveServiceName 调 7 次、findListSpecValue 调 4 次，:253 的 :options="actionOptions(row)" 每帧新建数组，表格每行每列都在跑计算函数。 |
| S-11 | frontend-user-v4-console/src/pages/client/services/index.less:502、:512 | 视觉 | 低 | 两个连续的同断点媒体查询块未合并，样式分散在 500 行附近，后续改动易只改一处。 |
| S-12 | frontend-user-v4-console/src/pages/client/services/index.less | 设计 | 中 | 该文件 bareRem 多处、圆角五档并存，未令牌化。 |
| S-13 | frontend-user-v4-console/src/pages/client/services/index.vue:278-359 | 设计 | 中 | 页内内联「续费」「备注」dialog 与服务控制台 console dialogs 同名重复实现，两处宽度/表单形态不同。 |
| S-14 | frontend-user-v4-console/src/pages/client/catalog/index.vue:1-20 | 设计 | 高 | 整页只有 t-card + t-empty type="maintenance" + 一行说明 + 「打开产品目录」按钮（push('/products') 跳站外），而 src/router/modules/client.ts:95-100 的 orderNo 为 20 且未 hidden → 这是一个可见的侧栏死入口。主进程实读复核成立。 |

> 服务控制台（`/client/services/:id`、`/compute`、`/port-mapping` 及 9 个 tab、8 个 dialog）单独编号 SC-*，见 2.6。

### 2.6 服务控制台（service-console/**）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| SC-01 | frontend-user-v4-console/src/pages/client/service-console/components/ConsoleHeader.vue:38-66 | 交互 | 高 | 电源按钮用 v-if="isInstanceRunning" / v-else 二选一，中间态（starting/stopping/rebooting）全部落到 v-else 显示「开机」，与真实状态相反，且按钮只 :disabled="!detail.actions?.power \|\| actionLoading" → 中间态可再次下发开机。rebooting 时还会显示「开机」。主进程实读复核成立。 |
| SC-02 | frontend-user-v4-console/src/domains/services/console/useConsoleRenew.ts:48-51、:67-75 | 交互 | 高 | loadRenewPreview 成功后无条件回写 renewForm.billing_cycle 与 user_coupon_id；handleRenewCycleChange / handleRenewCouponChange 均先写表单再 await 重新加载 → 用户选择被服务端默认值覆盖。主进程实读复核成立。 |
| SC-03 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/SecurityTab.vue:51-60；useConsoleSecurity.ts:219-231 | 交互 | 高 | 「应用」安全组无二次确认直接执行，而同文件「删除安全组」「删除规则」都有 DialogPlugin.confirm —— 危险度更高的写操作反而没有确认。 |
| SC-04 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/VncTab.vue:14-22；compute/index.vue:12-17；nat/index.vue:12-18 | 性能·渲染 | 高 | iframe 绑在 v-if 上，切换 tab 即卸载 → VNC WebSocket 断连；且 noVNC 的一次性连接地址不可复用，切回需重新走签名 → 用户视角是「一切 tab 就掉线」。 |
| SC-05 | frontend-user-v4-console/src/pages/client/service-console/entry.vue:21-42 | 交互 | 中 | 判定 console 类型（compute / nat）期间无 loading 占位，页面白屏一帧后直接渲染；叠加 useConsoleDetail.ts:119-126 的 bootstrap 中 loadRemoteStatus 与 fetchConnectionInfo 并发写 detail（后者 :87-95 整体替换覆盖前者 merge）→ 打开一次控制台 ≥3 次 detail 级请求且数据可能被覆盖。 |
| SC-06 | frontend-user-v4-console/src/pages/client/service-console/compute/index.vue:54-61；nat/index.vue:56-63 | 交互 | 中 | 两个页面用 watch(detail.id) 互相跳转，detail 未加载完时 detail.id 为空/旧值，可能把 NAT 服务渲染成云主机控制台。 |
| SC-07 | frontend-user-v4-console/src/pages/client/service-console/compute/index.vue:1-24；nat/index.vue:1-25 | 设计 | 高 | 两文件逐行同构，仅 tab 集合与互跳 watch 不同 → 应抽 ConsoleWorkbench.vue，当前任一处修复需手工同步两次（实际已出现 SC-06 只在一边修的情况）。 |
| SC-08 | frontend-user-v4-console/src/pages/client/service-console/compute/index.vue:63；nat/index.vue:65 | 性能·构建 | 中 | <style src="../components/styles.less" lang="less">（非 scoped）导致该 Less 被编译成独立 JS chunk dist/assets/js/styles.less_vue_type_style_index_0_src_true_lang-CkUNEie1.js 105,006 B + dist/assets/css/styles-Fka1ZKWX.css 25,171 B，样式被当作模块并连带拉入组件代码。 |
| SC-09 | frontend-user-v4-console/src/pages/client/service-console/components/styles.less:151-195 | 视觉 | 高 | .console-sidebar-item 的 :hover（:186-190）与 &.is-active（:191-194）样式**完全相同**（同色同底），无法分辨当前 tab；:687-704 的 .security-group-card 同病。 |
| SC-10 | frontend-user-v4-console/src/pages/client/service-console/components/styles.less:885-896、:1031-1041、:1122-1142、:1115-1119 | 视觉 | 高 | 财务 tab 的应付金额用 --td-error-color（与「支出」红撞语义），把中性金额读成告警；:273-293 的 .detail-cell-value.is-warning 直接用 --td-warning-color 当正文色，对比度约 2.1:1 不达 AA；:307-309 重复定义同一规则。 |
| SC-11 | frontend-user-v4-console/src/pages/client/service-console/components/styles.less:1-3 vs 全文件 | 视觉 | 中 | 文件头注释自称「令牌基线…禁止 !important」，实测 8 处 !important、104 处裸 rem 间距、三套断点、四档圆角；:986-1015、:1074-1093 共 7 处 !important 直接违背自述契约。 |
| SC-12 | frontend-user-v4-console/src/pages/client/service-console/components/styles.less:450-481、:657-680 | 视觉 | 中 | monitor-card-header 的 strong（line-height 1.2）与 small（1.8）基线错位；monitor-metrics 的标签与数值同为 body-small，仅靠 600 字重区分层级。 |
| SC-13 | frontend-user-v4-console/src/pages/client/service-console/components/styles.less:483-529；composables/useConsoleMonitor.ts:59-63 | 性能·渲染 | 中 | 图表 SVG preserveAspectRatio="none" + 固定高 8.75rem，而坐标按 320×140 硬算 → 窄屏横向压缩、线条被拉粗变形。 |
| SC-14 | frontend-user-v4-console/src/pages/client/service-console/composables/useConsoleMonitor.ts:68-71、:143-153 | 性能·渲染 | 高 | computed 内 buildMonitorChartView 全量重建（逐点 map/toFixed/三次贝塞尔），:143-153 用 Math.min(...values) spread，点数多时有栈溢出风险。 |
| SC-15 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/MonitorTab.vue:71-101；useConsoleMonitor.ts:255-271 | 性能·渲染 | 高 | 同一 chart 在每个 mousemove 帧调 resolveActiveMonitorPointCached 8 次（:71/73/75/79/95/99/97），且 @mousemove 无节流 → 悬停时主线程持续满载。 |
| SC-16 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/MonitorTab.vue:16、:17、:42-70、:118、:131 | 交互 | 中 | 同一 tab 四种「无内容」表达（t-alert warning / t-empty / 自绘 .monitor-chart-empty / 又一处 t-empty）；手写 SVG 只绑 mousemove/mouseleave，无 tabindex/keydown，触屏与键盘用户无法看数据点。 |
| SC-17 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/MonitorTab.vue:6；useConsoleTabs.ts:120、:242、:272 | 交互 | 中 | 区间切换 @change 无节流且带 fresh=1 + timeout 12000；同一控制台三种错误反馈（t-alert / toast / state.error）并存，用户不知道哪种才算真失败。 |
| SC-18 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/OverviewTab.vue:32-40；useConsoleTrafficPackages.ts:23、:44 | 交互 | 中 | 「购买流量包」按钮的 :loading 绑的是弹窗数据加载标志 → 弹窗打开期间主页按钮一直转圈。 |
| SC-19 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/SecurityTab.vue:26-32、:43-50、:93-146 | 交互 | 中 | 整卡 @click 与「查看」按钮同一动作可双触发，每次成功都 toast「安全组规则已加载」；:93-146 规则区双份 DOM。 |
| SC-20 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/NatTab.vue:33-42；useConsoleTabs.ts:196 | 交互 | 中 | can_delete === false 时删除按钮仍可见可点，点了静默 return，无禁用态、无解释。 |
| SC-21 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/LogsTab.vue（全文件）；useConsoleTabs.ts | 交互 | 低 | 无筛选 UI，但 logsState.category / keyword 参与请求参数（死字段）。 |
| SC-22 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/FinanceTab.vue:5 | 交互 | 低 | loadFinanceLogs 无 force 形参，但调用处按需传参，参数被静默丢弃。 |
| SC-23 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/VncTab.vue:8-9、:14-22；useConsoleVnc.ts:77-105 | 交互 | 中 | 两个按钮共用同一 actionLoading（点一个另一个也转圈）；无连接中/失败/已断开状态与重连；window 模式先 window.open('about:blank') 再 await，失败只 popup.close() 无提示。 |
| SC-24 | frontend-user-v4-console/src/domains/services/console/useConsoleDialogs.ts:120-148 | 交互 | 中 | 重置密码、重装、改密等多个写操作共用同一 actionLoading，任一转圈全部按钮禁用。 |
| SC-25 | frontend-user-v4-console/src/pages/client/service-console/components/dialogs/**（9 个 dialog） | 视觉 | 中 | 9 个 dialog 四种宽度（NameDialog 28rem、PasswordResetDialog 30rem、ReinstallDialog 34rem、TrafficPackageDialog 36rem）+ 三种表单形态 + 两种页脚；NatForwardingDialog.vue:2-9 无 width、用旧式 @confirm、:label-width="96" 无单位。 |
| SC-26 | frontend-user-v4-console/src/pages/client/service-console/components/ConsoleHeader.vue:87-104、:146；OverviewTab.vue:159-179、:214 | 交互 | 中 | 两份 IP 弹窗定义 ipDialogVisible 但模板无任何入口（死代码），后续维护者会误以为有功能。 |
| SC-27 | frontend-user-v4-console/src/pages/client/service-console/components/ConsoleHeader.vue:19-23；useConsolePower.ts:101-115 | 交互 | 中 | 自动续费 switch 直接 @change 提交，失败只 toast 不回滚 → 开关显示与服务端不一致。 |
| SC-28 | frontend-user-v4-console/src/pages/client/service-console/components/ConsoleAlerts.vue:20-26 | 交互 | 中 | isExpiringSoon 只判 expiresAt - now <= 7 天，已过期（负数）同样命中 → 对已过期服务显示「将于…到期」。 |
| SC-29 | frontend-user-v4-console/src/pages/client/service-console/components/styles.less:940-976 | 视觉 | 低 | VNC 空态与 iframe 同 35rem 但空态无边框无背景；≤767.98px 统一 26.25rem，小屏下 VNC 可视区域过小。 |
| SC-30 | frontend-user-v4-console/src/pages/client/service-console/components/styles.less:978-983 | 视觉 | 低 | .console-pagination 右对齐 + 横向滚动，页数多时出现横向滚动条且与内容区不对齐。 |

### 2.7 首页（/client/dashboard）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| D-01 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:730-790 | 交互 | 高 | loadDashboard 用 Promise.allSettled 拉六个接口，仅当 results.every(rejected) 才进整页失败态；messagesReady / todosReady / summaryReady / productsReady 均在 if 之外无条件置 true（:753、:757、:770、:779）→ 某分区失败时显示「0 元 / 0 条」，被误读为真实数据。主进程实读复核成立。 |
| D-02 | frontend-user-v4-console/src/pages/client/dashboard/index.less:208-211、:203-206 | 视觉 | 高 | .todo-row__icon.is-warning 用 --td-warning-color（#f59e0b，theme.css:18 的 -6 档）配 #fff，对比度约 2.1:1；.is-success 同病。均不满足 WCAG AA 4.5:1。 |
| D-03 | frontend-user-v4-console/src/pages/client/dashboard/index.less:339-355 | 视觉 | 高 | .bar-chart__value 默认 opacity:0，只有 :hover / :focus-within 显示 → 触屏设备上没有 hover，月度柱状图的金额**完全不可读**。 |
| D-04 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:606；index.less:295-312 | 视觉 | 高 | dailyBars 每柱 showLabel 恒 true，而柱宽 ≤40rem 时七日标签必然重叠，金额文字互相压盖。 |
| D-05 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:733-740 | 性能·渲染 | 高 | 首屏一次性 6 路并发请求（notices、tickets、financeLedgerSummary、couponsSummary、groupedOverview、referralOverview），占满浏览器 6 连接上限，阻塞后续资源；其中 referralOverview、couponsSummary 并非首屏必需。 |
| D-06 | frontend-user-v4-console/src/pages/client/dashboard/composables/useDashboardSecondary.ts:49-67、:70-104 | 性能·渲染 | 高 | 账单分页串行穷尽：当年全年 maxPages=20 × page_size=100 串行请求，最坏 11–20 次 HTTP/次进入，无 AbortController、无缓存；且 :73-74 一进来就把 invoicesLoaded / balanceLogsLoaded 同时置 false → 局部重试变全局重拉（dashboard/index.vue:116-119、:158-161 两个图表的重试都调 loadConsumptionCharts）。 |
| D-07 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:57-64、:82-84 | 视觉 | 中 | 骨架屏尺寸与真实排版不匹配，加载完成后卡片高度跳变（CLS）。 |
| D-08 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:115、:157、:116-119 | 视觉 | 中 | 图表加载用 t-loading 而非 skeleton，与页面其余 skeleton 不一致；自绘 .chart-load-failed（index.less:81-89）与 4 处 t-empty 并存，失败态两种语言。 |
| D-09 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:183-189、:195-224 | 交互 | 中 | 「查看全部」的去向依赖当前 tab 值而非固定路由，用户在待办 tab 点「查看全部账单」会被带到别的列表。 |
| D-10 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:722 | 交互 | 中 | ensureUserInfo().catch(() => {}) 静默吞掉错误，用户信息缺失时页面无任何提示。 |
| D-11 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:660 | 交互 | 中 | openTicketCount 只统计 recentTickets（page_size=5），待办数字恒 ≤5，与真实待办数不符。 |
| D-12 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:410-427 | 设计 | 中 | resolveProductMeta 用产品标题做中文 includes 匹配来选图标，标题文案一改图标就错配（业务耦合进视图层）。 |
| D-13 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:496-511 与 :514-575 | 视觉 | 中 | 无数据时硬编码的 6 张占位卡与真实卡片视觉完全一致，用户会以为是真实产品（假数据占位）。 |
| D-14 | frontend-user-v4-console/src/pages/client/dashboard/index.less:5-9、:363-365、:457-470、:151、:37 | 视觉 | 中 | summary-grid 用 minmax(22rem,2fr)+4×minmax(10rem,1fr)，1200–1400px 已被挤压；.message-tabs 负 margin 外溢；二维码无 max-width 可能不可扫；:151 用尺寸令牌 --td-comp-size-xxxs 当边框宽度；:37 自绘卡片。 |
| D-15 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:48-71 | 设计 | 待确认 | 顶部指标卡是否违反 docs/DESIGN.md §5.2「财务记录页禁止统计/指标卡片」——该条款点名「记录页（账单/订单/充值）」，dashboard 不在列举范围，需设计侧确认边界。 |

### 2.8 优惠券与推广（/client/coupons、/client/referral）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| P-01 | frontend-user-v4-console/src/pages/client/coupons/index.vue:376-385 | 视觉 | 高 | .coupon-item__detail 绝对定位 + border:0 + 无 padding，实际可点击热区约 13px 高，远低于 44px 触控基线；卡片主体不是可点区域，用户点卡片无反应。 |
| P-02 | frontend-user-v4-console/src/pages/client/coupons/index.vue:576-583 | 设计 | 高 | 根类 coupon-page **不带 client- 前缀**，在 ≤991px 又自加 padding，而 theme.css:737-742 的移动端归零只命中 [class^='client-'] / .record-page → 与布局层叠加成双重内边距（主进程已按类名实测确认）。 |
| P-03 | frontend-user-v4-console/src/pages/client/coupons/index.vue:18；src/domains/marketing/useCoupons.ts:92-93 | 交互 | 中 | ownedState.status 初值写死 'available'（只显示可用），但下拉 placeholder 写「全部状态」→ 首屏展示与文案矛盾，用户以为在看全部。 |
| P-04 | frontend-user-v4-console/src/pages/client/coupons/index.vue:74-84、:137-147 | 交互 | 待确认 | 两处 t-pagination 同时绑 @change 与 @page-size-change，改页大小时是否双请求依赖 TDesign 事件语义（node_modules 未安装）。 |
| P-05 | frontend-user-v4-console/src/domains/marketing/useCoupons.ts:153-160 | 交互 | 中 | switchTab 仅在该 tab 无数据时才 loadList → 切换 tab 时若已有缓存则显示上一 tab 的数据，状态与列表错配。 |
| P-06 | frontend-user-v4-console/src/pages/client/coupons/index.vue:395-410、:412-435 | 视觉 | 中 | 面额用 headline-medium 字号压迫左侧标题；描述单行 ellipsis 且无 tooltip，长券名/长规则不可读。 |
| P-07 | frontend-user-v4-console/src/pages/client/coupons/index.vue:470-477、:508-574、:614-616 | 视觉 | 中 | 抽屉 radius large 反而大于外层卡片 medium（圆角层级倒置）；抽屉内 border 用 0.0625rem + --td-component-border，而 :371、:455 用 thin + --td-stroke → 同一文件两套 1px 与两套描边色。 |
| P-08 | frontend-user-v4-console/src/pages/client/coupons/index.vue:42-71 与 :97-134 | 设计 | 中 | 优惠券卡片的「桌面表格式」与「移动卡片式」两套模板同存 DOM，仅靠 CSS 显示切换，维护需改两处。 |
| P-09 | frontend-user-v4-console/src/pages/client/referral/index.vue:265-269 | 视觉 | 高 | .referral-stats 固定 repeat(6, minmax(0,1fr))，992–1200px 时每列约 110px，数字与标签折行破版；未做断点降级。 |
| P-10 | frontend-user-v4-console/src/pages/client/referral/index.vue:9-14 | 设计 | 中 | 顶部 6 格纯指标卡（无趋势、无对比、无下钻），信息密度低且占据首屏最佳位置；建议压缩为 2–3 项关键数 + 其余折叠。 |
| P-11 | frontend-user-v4-console/src/pages/client/referral/index.vue:40-43、:68、:89、:122、:145 | 视觉 | 中 | 5 处同尺寸 t-empty 并列，主次不分；无数据时整页全是「暂无」，用户无法判断哪块是核心。 |
| P-12 | frontend-user-v4-console/src/pages/client/referral/index.vue:338-342 被 :344-349 完全覆盖 | 视觉 | 低 | 后一条同名选择器覆盖前一条，前者为死规则，容易误导后续修改。 |
| P-13 | frontend-user-v4-console/src/pages/client/referral/index.vue:222-250 | 视觉 | 低 | 列宽写成字符串 12rem，而同项目 useConsoleTables.ts 用数字列宽，两种口径混用。 |
| P-14 | frontend-user-v4-console/src/pages/client/referral/index.vue:44-54 | 交互 | 中 | 提现用 t-form inline 布局，无窄屏适配，手机上一行挤「金额 + 按钮」。 |
| P-15 | frontend-user-v4-console/src/pages/client/referral/index.vue:157-175；useReferral.ts:279-293 | 交互 | 中 | 绑定支付宝弹窗无 destroy-on-close、无前端校验，关闭再开保留上次脏值。 |
| P-16 | frontend-user-v4-console/src/domains/marketing/useReferral.ts:295-318 | 交互 | 高 | 提现只校验 amount > 0，不校验最小提现额、不校验可用余额上限 → 前端可提交必然失败的请求，用户只在后端拿到报错。 |
| P-17 | frontend-user-v4-console/src/domains/marketing/useReferral.ts:214-231 | 交互 | 中 | loadAll 用 Promise.all，任一接口失败整页失败；相比 dashboard 的 allSettled 策略不一致。 |
| P-18 | frontend-user-v4-console/src/pages/client/referral/index.vue:73-149 | 性能·渲染 | 中 | 模板内承担大量视图模型计算（格式化、条件拼 class、内联 map），每帧重复执行，建议提为 computed。 |

### 2.9 工单（/client/tickets、/client/tickets/:id）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| T-01 | frontend-user-v4-console/src/pages/client/tickets/index.vue:421-428 | 设计 | 高 | 页面自加 padding，与 src/style/layout.less:137-140（桌面 20px 24px）叠加成双重内边距；根类为 client-tickets（命中 theme.css 移动归零），但桌面侧无归零，实测桌面确实双层。 |
| T-02 | frontend-user-v4-console/src/pages/client/tickets/index.vue:144-172、:152-169 | 交互 | 高 | button.attachment-thumb 内部再嵌 span[role=button] tabindex=0 的删除按钮 —— 交互元素非法嵌套，Enter 会在两个可聚焦元素上重复触发；删除热区压在整条底栏上，容易误删。 |
| T-03 | frontend-user-v4-console/src/pages/client/tickets/index.vue:146-149 | 设计 | 中 | 手写 input[type=file] 而非 t-upload（全项目 t-upload 使用 0 处），无拖拽、无进度、无类型/大小前置校验。 |
| T-04 | frontend-user-v4-console/src/pages/client/tickets/index.vue:242-249 | 交互 | 中 | 多图上传串行 await，每张成功都单独 toast → 上传 5 张弹 5 次提示，且总耗时线性叠加。 |
| T-05 | frontend-user-v4-console/src/pages/client/tickets/index.vue:79-89 | 交互 | 待确认 | 分页同时绑 @change 与 @page-size-change，存在双请求风险（依赖 TDesign 事件语义）。 |
| T-06 | frontend-user-v4-console/src/pages/client/tickets/index.vue:355-374 | 视觉 | 中 | .upload-trigger 的边框色与背景色同为 brand-light，虚线框在浅底上几乎不可见，用户看不出这是上传区。 |
| T-07 | frontend-user-v4-console/src/pages/client/tickets/index.vue:37-59 与 :61-75 | 设计 | 中 | 工单列表桌面/移动两套模板同存 DOM，仅 CSS 切换，需双份维护（与 P-08 同类）。 |
| T-08 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:552-560 | 交互 | 高 | 移动端 tab 切换依赖 :has(.t-tabs__nav-item:nth-child(3).t-is-active)，而页面只有 2 个 t-tab-panel（chat / detail）→ 选择第 2 个时选择器永不匹配，「详情」视图在移动端**完全失效**。主进程实读复核成立（应为 nth-child(2)）。 |
| T-09 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:521-527 | 视觉 | 高 | ≤767.98px 时 h1 直接 display:none，移动端页面丢失标题，用户不知道当前在看哪个工单。 |
| T-10 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:3-9 | 视觉 | 中 | toolbar 用 grid auto / minmax(0,1fr) / auto 三列，标题偏离中轴，与全站页面头部左侧对齐的规范不符。 |
| T-11 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:4 | 交互 | 中 | 返回按钮固定 push('/client/tickets')，从公告/其他入口进入的用户丢失来源栈。 |
| T-12 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:194 | 交互 | 待确认 | @enter 绑在 t-textarea 上，是否能拿到 enter 事件需查 TDesign 实现（node_modules 未安装）；若不支持则「回车发送」实际不生效。 |
| T-13 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:100、:102、:134、:136 | 性能·渲染 | 中 | 同一条消息的 parseAttachments 在同一模板里被调用 4 次，每条消息重复解析。 |
| T-14 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:122-124 | 设计 | 中 | 工单详情直接输出 user_id 给前端展示，属内部标识外露（管理端才需要完整信息，用户端不应出现）。 |
| T-15 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:356-365 | 视觉 | 中 | .message-list 用 max-height: calc(100vh - 20rem)，20rem 为魔法数，与其他页面高度口径不一致，小屏下消息区可能只剩一两行。 |
| T-16 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:277-283 | 视觉 | 低 | 页面 min-height 用 --td-comp-size-xxxxxl，而 layout.less:142-177 用 --td-comp-size-xxxl → 同语义不同量级（主进程已核实两个令牌都存在，问题在混用）。 |
| T-17 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:65-89 与桌面 aside | 设计 | 中 | 会话列表在桌面侧栏与移动端区域两套实现，逻辑重复。 |
| T-18 | frontend-user-v4-console/src/domains/support/useTickets.ts:367-373 | 交互 | 中 | recalling 是全局单一标志，一列表里点任一工单的「召回」，所有行的按钮同时转圈。 |
| T-19 | frontend-user-v4-console/src/domains/support/useTickets.ts:507-515 | 交互 | 中 | watch(ticketId) + onMounted 双发 loadDetail，且无 requestSeq 竞态防护 → 快速切换工单时后到的旧响应会覆盖新数据（违反 docs/FRONTEND.md §5 的 requestSeq 要求）。 |

### 2.10 公告与帮助（/client/notices、/client/notices/:id、/client/help、/client/help/:id）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| N-01 | frontend-user-v4-console/src/pages/client/content-list/ContentListPage.vue:5-31 | 设计 | 待确认 | 列表页顶部使用官网式 Hero 区块（见 :5-31 结构与 :195-199 的 h1 字重重置）。docs/DESIGN.md §5.2 禁止官网式大 Hero，但该条款列举的是财务记录页 → 公告/帮助是否同样受限需设计侧确认。 |
| N-02 | frontend-user-v4-console/src/pages/client/content-list/ContentListPage.vue:153-159；src/domains/content/useNoticeReadStatus.ts:16-23 | 交互 | 高 | handleMarkAllRead 无 try/catch，而 useNoticeReadStatus 在失败时 throw → 未捕获 rejection，用户点「全部已读」失败后没有任何提示，按钮也无失败态。 |
| N-03 | frontend-user-v4-console/src/pages/client/content-list/ContentListPage.vue:55-59 | 交互 | 中 | 「全部标记已读」的数量来自 unreadCount，但列表页自身不调用 fetchUnreadCount → 数字可能是路由切换前的旧值或 0。 |
| N-04 | frontend-user-v4-console/src/pages/client/content-list/ContentListPage.vue:35-46 | 视觉 | 中 | 分类筛选的选中态只靠 outline + theme 色，与未选中差异弱，浅色主题下几乎看不出当前分类。 |
| N-05 | frontend-user-v4-console/src/pages/client/content-list/ContentListPage.vue:70-71 | 视觉 | 中 | 「置顶」用 t-tag theme="danger"（错误红），语义与「紧急/失败」撞色。 |
| N-06 | frontend-user-v4-console/src/pages/client/content-list/ContentListPage.vue:102、:113 | 设计 | 中 | 空态只显示「暂无内容」，无筛选复位引导、无返回入口；违反 docs/FRONTEND.md:119 三态完整性要求（缺可操作出口）。 |
| N-07 | frontend-user-v4-console/src/pages/client/content-list/ContentListPage.vue:219-229 | 视觉 | 低 | 首条 padding-top:0 使标题区与首条的间距小于条目之间的间距，视觉上首条像「黏」在标题下。 |
| N-08 | frontend-user-v4-console/src/pages/client/content-list/ContentListPage.vue:344-349 | 视觉 | 低 | 对 .hero-card 设 grid-template-columns，但该元素实际是 flex column → 死代码。 |
| N-09 | frontend-user-v4-console/src/pages/client/content-detail/ContentDetailPage.vue:276-279 | 设计 | 高 | 根类 content-detail-page **不是 client- 前缀**，≤767.98px 又自加 padding → 与布局层叠加成双重内边距（theme.css 归零不命中，主进程实测确认）。 |
| N-10 | frontend-user-v4-console/src/pages/client/content-detail/ContentDetailPage.vue:33；src/domains/content/useContent.ts:407-413 | 性能·渲染 | 高 | v-html 一次性注入整篇正文，随后 buildToc 全量 querySelectorAll 逐节点写 id → 长文首帧主线程同步阻塞。 |
| N-11 | frontend-user-v4-console/src/domains/content/useContent.ts:186-191 | 交互 | 中 | 侧栏目录请求固定 page=1&page_size=20 再前端取前 5 → 切换分类后侧栏列表不更新，仍是首次的结果。 |
| N-12 | frontend-user-v4-console/src/domains/content/useContent.ts:369-374 | 交互 | 中 | scrollToAnchor 用 getElementById + scrollIntoView，滚动容器不一定是 window，锚点跳转可能无效。 |
| N-13 | frontend-user-v4-console/src/pages/client/content-detail/ContentDetailPage.vue:143-150 | 视觉 | 中 | h1 用 title-large 的字号配 headline-medium 的行高，大字号配紧行高，多行标题会挤在一起。 |
| N-14 | frontend-user-v4-console/src/pages/client/content-detail/ContentDetailPage.vue:152-163 | 视觉 | 中 | .reader-meta 底色用 brand-light 而边框用 brand-focus，同一元素两个品牌色档混用，观感发脏。 |
| N-15 | frontend-user-v4-console/src/pages/client/content-detail/ContentDetailPage.vue:200-213 | 视觉 | 低 | blockquote 用 --td-comp-size-xxxs 当边框宽度（尺寸令牌误用于边框，与 D-14 同类）。 |
| N-16 | frontend-user-v4-console/src/pages/client/content-detail/ContentDetailPage.vue:254-268 | 视觉 | 中 | .toc-row button 实际命中高度约 28px，低于 44px 触控基线，移动端目录难以点中。 |
| N-17 | frontend-user-v4-console/src/pages/client/content-detail/ContentDetailPage.vue:3-9；src/pages/client/service-console/components/ConsoleBreadcrumb.vue:2-8 | 设计 | 中 | 页面自绘面包屑与布局层面包屑双份；src/config/style.ts:1-14 默认 showBreadcrumb=false，属「潜伏配置」——一旦有人打开配置即出现双面包屑。ConsoleBreadcrumb.vue:3 的「用户中心」还指向 /client/services。 |

### 2.11 账户（/client/verification、/client/profile）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| AC-01 | frontend-user-v4-console/src/pages/client/verification/index.vue:8 | 设计 | 高 | 把状态文案（如「已认证」）当作页面级 h1，页面缺少真正的标题；全站 7 个无 h1 页面之一，破坏文档大纲与屏幕阅读器层级。 |
| AC-02 | frontend-user-v4-console/src/pages/client/verification/index.vue:295-297 | 视觉 | 高 | .verification-message 用 --td-warning-color 当正文色，正文信息读起来像告警（且对比度不足 AA）。 |
| AC-03 | frontend-user-v4-console/src/pages/client/verification/index.vue:325-331 | 视觉 | 高 | ≤767.98px 把 display:flex; justify-content:space-between 的 .verification-status-card__main 强行改 grid，原 flex 子项的间距/对齐规则全部失效，状态卡片在移动端错位。 |
| AC-04 | frontend-user-v4-console/src/pages/client/verification/index.vue:82 | 视觉 | 高 | qrcode-vue 的 size 固定 260px，而 320px 宽设备上容器内可用宽度不足 → 二维码溢出容器被裁切，可能无法扫描。 |
| AC-05 | frontend-user-v4-console/src/pages/client/verification/index.vue:3；src/domains/account/useVerification.ts | 交互 | 中 | 只有 LoadingState，无 error / empty / retry，违反 docs/FRONTEND.md:119 与 docs/DESIGN.md:202 的三态要求。 |
| AC-06 | frontend-user-v4-console/src/pages/client/verification/index.vue:88-107；useVerification.ts:186-203 | 交互 | 中 | 「刷新二维码」与「重新认证」共用同一 verificationLoading → 点任一个另一个也变灰，且两次操作语义完全不同。 |
| AC-07 | frontend-user-v4-console/src/pages/client/verification/index.vue:59-68；useVerification.ts:346-368 | 交互 | 中 | 二维码态下 confirm-btn 为 null，弹窗只剩「取消」，而取消会销毁会话 → 用户误点取消后需从头再来。 |
| AC-08 | frontend-user-v4-console/src/domains/account/useVerification.ts:244-262 | 交互 | 中 | 倒计时归零自动清 url 并切回表单，但不关弹窗、无 toast 提示，用户看到界面突变不知发生了什么。 |
| AC-09 | frontend-user-v4-console/src/pages/client/verification/index.vue:230-250 | 视觉 | 中 | 用 --td-bg-color-container-hover 当静态底色，鼠标悬停时颜色不再变化，hover 反馈失效。 |
| AC-10 | frontend-user-v4-console/src/pages/client/verification/index.vue:219-224 | 视觉 | 低 | meta 区 strong 长文本撑破两列布局，无 overflow 处理。 |
| AC-11 | frontend-user-v4-console/src/pages/client/verification/index.vue:172-177 | 视觉 | — | **正例**：间距全部使用令牌，可作为其他页面的改造样板。 |
| AC-12 | frontend-user-v4-console/src/pages/client/profile/index.vue:2 | 设计 | 高 | 根类 profile-page **非 client- 前缀**，≤991px 时 theme.css 移动归零不生效；该页 :3 还用 header.client-page-heading，两套页面头规范混用。 |
| AC-13 | frontend-user-v4-console/src/pages/client/profile/index.vue:413-423、:425-429 | 视觉 | 中 | 全仓唯一使用 font-size: 0.8125rem（13px）配负 margin 微调，theme.css 中无 13px 号段，属脱离体系的魔法值。 |
| AC-14 | frontend-user-v4-console/src/pages/client/profile/index.vue:493 | 视觉 | 中 | 用 @screen-sm-max（991px）当移动断点，与 src/style/variables.less:32-35 的 @screen-mobile-max（767.98px）语义混用 → 该块样式本应只在小屏生效却在平板上也命中。 |
| AC-15 | frontend-user-v4-console/src/pages/client/profile/index.vue:549-559 | 视觉 | 中 | 4 处 !important 固化样式，后续改版极易失效。 |
| AC-16 | frontend-user-v4-console/src/pages/client/profile/index.vue:121-124 | 设计 | 中 | 改密弹窗内再嵌一层 t-tabs → 弹窗 + tab + 表单三层导航，弹窗内导航是反模式。 |
| AC-17 | frontend-user-v4-console/src/pages/client/profile/index.vue:479-491 | 视觉 | 低 | .agent-list 样式存在但模板零使用（死样式）。 |
| AC-18 | frontend-user-v4-console/src/pages/client/profile/index.vue:435-445 | 视觉 | 低 | .profile-footer 用 border-top dashed，与全站实线分隔（record-page.less:256 solid）不一致。 |
| AC-19 | frontend-user-v4-console/src/pages/client/profile/index.vue:58；src/domains/account/useProfile.ts:29 | 交互 | 高 | 「保存资料」「改密码」「换手机」「换邮箱」「发验证码」共用单一 profileLoading → 任一操作进行中，整页所有按钮同时禁用/转圈。 |
| AC-20 | frontend-user-v4-console/src/pages/client/profile/index.vue:133、:163、:182、:206、:219 | 交互 | 中 | 五个「发送验证码」按钮只有 :disabled 无 :loading，点击后到请求返回之间无任何进行中反馈。 |
| AC-21 | frontend-user-v4-console/src/pages/client/profile/index.vue:149-153；useProfile.ts:237-241 | 交互 | 中 | 密码弹窗提交中可关闭，但请求成功后仍会 toast 并 logout 跳登录 → 用户已关窗却被踢出登录态。 |
| AC-22 | frontend-user-v4-console/src/domains/account/useProfile.ts:485-488、:578-580 | 交互 | 高 | loadProfile 内的 userStore.getUserInfo() 异常未捕获，onMounted 的 Promise.all 也无 catch → 未处理 rejection，表单空白且无提示、无重试。主进程实读复核成立。 |
| AC-23 | frontend-user-v4-console/src/domains/account/useProfile.ts（五路 1s 定时器） | 性能·渲染 | 中 | profile 域内存在五路 1s 间隔轮询，页面切走后若组件未销毁会持续触发（叠加 PR-01 的 keep-alive 常驻）。 |
| AC-24 | frontend-user-v4-console/src/pages/client/profile/index.vue:7-18 与 :20-29 | 设计 | 中 | 资料卡片的桌面/移动两套模板同存 DOM，仅 CSS 切换。 |

### 2.12 布局层（src/layouts/**、src/style/*.less、theme.css）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| L-01 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:107-113 | 交互 | 高 | handleRemove 中 nextRouter = tabRouters[index+1] \|\| tabRouters[index-1]，关闭最后一个非首页标签时两者皆为 undefined，:112 直接读 nextRouter.path → **TypeError 崩溃**。主进程实读复核成立。 |
| L-02 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:98、:107-113、:175-180 | 交互 | 高 | tabRouters 是过滤后的列表（isAlive \|\| isHome），却把过滤后下标传给按 store 原始数组执行的 subtractCurrentTabRouter / handleRemove → **误删相邻标签**。主进程实读复核成立。 |
| L-03 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:101-105 | 交互 | 中 | 切标签只 push({ path, query }) 不带 params → 从详情标签切走再切回，参数丢失回落到父级路由。 |
| L-04 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:116-123 | 交互 | 中 | handleRefresh 连续两次 toggleTabRouterAlive + activeTabPath.value = null → 标签闪烁且选中态丢失。 |
| L-05 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:141-159 | 交互 | 中 | findIndex 可能返回 -1，随后仍 push → 抛错。 |
| L-06 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:14-20、:182-189 | 性能·渲染 | 中 | tab 的 key 由 index 拼接，而 :182-189 的拖拽交换直接换位置 → 所有 tab 组件全部重建（keep-alive 缓存命中被打断）。 |
| L-07 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:3-13；src/style/layout.less:147-155 | 视觉 | 中 | tab 栏内联 style position:sticky，同时 layout.less 又给同一区域 position:fixed → 两种定位模式并存，滚动时层级/遮挡行为不可预期。 |
| L-08 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:203-223 | 视觉 | 中 | .tab-item-more 命中区约 18×18px 且 opacity .6，远低于触控基线。 |
| L-09 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:49-64、:99、:122、:158、:166 | 交互 | 低 | 三个 v-if 条件口径不一致；activeTabPath 在四处被赋成三种不同类型（string / null / undefined）。 |
| L-10 | frontend-user-v4-console/src/layouts/components/Content.vue:11-14；src/store/modules/tabs-router.ts:38-44 | 性能·渲染 | 高 | keep-alive 用 :max="10" 但标签数量无上限，而注释明确说明因组件 name 全为 index 只能用 route.name 作 key → 打开的标签数超 10 后 LRU 淘汰与标签栏不同步；更关键的是**未关闭的标签页组件永不销毁**（含 5 处 1s 倒计时、实名轮询、支付宝轮询、matchMedia 监听）。全仓最系统性风险。 |
| L-11 | frontend-user-v4-console/src/layouts/components/Content.vue:20、:41-45 | 视觉 | 低 | 刷新整块用 t-loading 蒙层，与页面内 skeleton 加载语言不一致。 |
| L-12 | frontend-user-v4-console/src/layouts/index.vue:66-72 | 交互 | 中 | 只 watch(route.path)，query 变化不更新标签；:70 的 querySelector('.tdesign-starter-layout').scrollTo 未判空且用 smooth → 长列表切页时平滑滚动抢主线程。 |
| L-13 | frontend-user-v4-console/src/layouts/components/Breadcrumb.vue:17-33 | 交互 | 中 | route.path.split('/') 后 shift() 再配 route.matched[idx].meta → 层级错位；meta.title 缺失时回退英文路径段；:23 用 Object.values(route.params).includes(path) 判断参数段，当目录名恰好等于参数值时误隐藏。 |
| L-14 | frontend-user-v4-console/src/layouts/components/SideNav.vue:162-171 | 交互 | 中 | watch([isMobile, isTablet], immediate) 直接 updateConfig({ isSidebarCompact: tablet }) → 覆盖用户手动折叠选择，且窗口尺寸变化会反复改写偏好。 |
| L-15 | frontend-user-v4-console/src/layouts/components/SideNav.vue:23-33 | 交互 | 低 | Logo span 绑 @click 但无 role/tabindex/键盘处理。 |
| L-16 | frontend-user-v4-console/src/layouts/components/SideNav.vue:259-284 | 视觉 | 低 | 品牌位 112×34 / 34×34，而 Header.vue:441-451 用 --td-comp-size-l（32px），两处品牌尺寸不统一。 |
| L-17 | frontend-user-v4-console/src/layouts/components/LayoutSideNav.vue:30-36 | 交互 | 低 | menu.children.map 无 null 保护，后端返回 null 时直接崩。 |
| L-18 | frontend-user-v4-console/src/layouts/components/Header.vue:265-271 | 交互 | 高 | handleInboxItemClick 未 await markRead(item) 就关面板并 router.push(item.link)；link 为空时不跳转也无提示。 |
| L-19 | frontend-user-v4-console/src/layouts/components/Header.vue:273-300；src/domains/content/useInbox.ts:67-75 | 交互 | 高 | handleMarkAllRead 未 await 就 MessagePlugin.success('已全部标记为已读')，而 useInbox 在失败时静默 → **失败也提示成功**。 |
| L-20 | frontend-user-v4-console/src/layouts/components/Header.vue:259-263 | 交互 | 低 | 每次打开消息面板都重新 fetchFeed(10)，无缓存，抖动点击即重复请求。 |
| L-21 | frontend-user-v4-console/src/layouts/components/Header.vue:93-116 | 交互 | 低 | window.open 被浏览器拦截时无任何提示，用户以为点击无效。 |
| L-22 | frontend-user-v4-console/src/layouts/components/Header.vue:59、:78 | 交互 | 中 | 手写 role=button 的可点元素无 aria-label，屏幕阅读器读不出用途。 |
| L-23 | frontend-user-v4-console/src/layouts/components/Header.vue:372、:380；layout.less:266-276 | 视觉 | 中 | fixed header 的 left 分别在两处写死 232px（与侧栏宽度重复定义），改侧栏宽需改两处。 |
| L-24 | frontend-user-v4-console/src/layouts/components/Header.vue:517、:533-574、:590-607 | 视觉 | 中 | :517 用 --td-comp-size-xxxs 当字号；:533-574 的 inbox-panel 全硬编码 px；:590-607 未读红点用 --td-error-color 与 .t-badge 两种实现。 |
| L-25 | frontend-user-v4-console/src/layouts/components/Header.vue:644-687 | 视觉 | 中 | 非 scoped 块内用 5 处 !important 固化 .t-popup.inbox-popup，覆盖 TDesign 内部样式，升级 TDesign 时易碎。 |
| L-26 | frontend-user-v4-console/src/layouts/components/MenuContent.vue:6-8；theme.css:371-396 | 视觉 | 中 | 图标仅 level === 0 渲染，而 theme.css 给二/三级菜单预留 padding-left 56px / 80px → 深层菜单出现大片空白缩进且无图标。 |
| L-27 | frontend-user-v4-console/src/layouts/components/MenuContent.vue:128-141 | 视觉 | 低 | 角标字号 10px，低于全站最小号段。 |
| L-28 | frontend-user-v4-console/src/layouts/components/FrameContent.vue:51-70、:102 | 视觉 | 低 | calcHeight 逐项相减并带 +2 魔法数；:102 硬编码 #fff（全项目仅 2 处硬编码色值之一）。 |
| L-29 | frontend-user-v4-console/src/layouts/components/FrameContent.vue:37-39 | 性能·渲染 | 中 | 主题令牌只在挂载时读一次，切换到运行中改主题的场景不会更新。 |
| L-30 | frontend-user-v4-console/src/style/layout.less:41-43、:84-95 | 视觉 | 中 | .t-button + .t-button 的 margin-left 与各页 flex gap 叠加（record-page.less:321-324、invoice-detail/index.less:955-959 各自 margin-left:0 打补丁）；:84-95 在 @screen-sm-max 内就地改写 --td-comp-paddingTB-l/LR-l 为 12px → **令牌漂移**，全局副作用。 |
| L-31 | frontend-user-v4-console/src/style/variables.less:16-18、:32-35 | 视觉 | 中 | @screen-sm-max（991px）与 @screen-mobile-max（767.98px）语义混用，:32-35 注释明写「mobile 形态必须用 767.98」。误用点：profile/index.vue:493、Header.vue:495、:657、layout.less:84、:169、:308、record-page.less:273。 |
| L-32 | frontend-user-v4-console/src/style/index.less:1-7 | 设计 | — | **已核实顺序正确**：theme.css → font-family.less → reset.less → console-tables.less → nprogress.less，theme.css 为 E:/caiwu/theme.css，令牌先于业务样式加载。 |
| L-33 | frontend-user-v4-console/src/style/reset.less:41-46；recharge/index.less:173-177 | 交互 | 中 | 全局已定义 :focus-visible 焦点描边，但 recharge 页用 outline:0 覆盖；全 src 仅 5 处 :focus-visible → 键盘用户大面积丢失焦点可见性（WCAG 2.4.7）。 |
| L-34 | frontend-user-v4-console/src/style/index.less:3；src/style/layout.less:1 | 性能·构建 | 中 | 两处各自展开同一份 @font-face（TencentSansW7 4,128 B base64），dist 中字节完全相同的字体声明出现两次。 |
| L-35 | E:/caiwu/theme.css:440-483 | 设计 | 中 | 令牌库提供的 #app .page-tabs-toolbar / .page-inline-actions / .page-inline-hero 三组规则在 v4-console 与 v3-www 的 src 中 grep 命中 **0**（仅 admin 用 page-inline-actions）→ 属死 CSS，反过来说明页面头规范未被采纳。 |
| L-36 | E:/caiwu/theme.css:732-742 | 视觉 | — | **已核实按设计生效**：/client 前缀页在 ≤991px 时 .t-card__body 改 12px、内容 padding 归零；但只覆盖 [class^='client-'] 与 .record-page，非 client- 前缀的 10 个页面不受益（见 2.14 C-01）。 |

### 2.13 异常页（404）

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| E-01 | frontend-user-v4-console/src/router/index.ts:18-23 | 交互 | 中 | 404 通配路由无 requireAuth，未登录用户访问不存在路径时不跳登录而直接看到 404，登录后也无法回跳原目标。 |
| E-02 | frontend-user-v4-console/src/pages/result/404/index.vue:8 | 交互 | 低 | 固定 push('/client/dashboard')，无视 referer，用户丢失来源。 |
| E-03 | frontend-user-v4-console/src/pages/result/404/index.vue:21-24 | 交互 | 低 | canGoBack 仅依赖 window.history.state.back，直接打开新标签时为 null，「返回上一页」按钮不可用。 |
| E-04 | frontend-user-v4-console/src/pages/result/404/index.vue:36-46 | 视觉 | 中 | 全站唯一**无 border-radius、无 shadow** 的方块卡片，与 theme.css:186-192 的卡片体系割裂。 |
| E-05 | frontend-user-v4-console/src/pages/result/404/index.vue:56-61 | 视觉 | 低 | 先用 font 简写重置了 weight，随后又声明 600，声明冗余且顺序脆弱。 |
| E-06 | frontend-user-v4-console/src/pages/result/404/index.vue（全文件） | 设计 | 中 | 无页面级 h1（全站 7 个无 h1 页面之一），标题用视觉字号冒充语义标题。 |

### 2.14 跨页共性问题（C-*）

> 本节只收录**多个页面重复出现、必须一次性统一**的问题，避免在 2.1–2.13 里重复计数。单页特有问题已归入对应页面小节。

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| C-01 | theme.css:732-742 与各页根类 | 视觉 | 高 | 移动端内容 padding 归零只命中 [class^='client-'] / [class*=' client-'] / .record-page。主进程按类名实测：**命中** client-dashboard / client-services / client-recharge / client-tickets / client-verification / client-order-create 与 record-page 系的 invoices、orders、payments；**不命中**（真实双重 padding）catalog-page、checkout-resume-page、coupon-page、invoice-detail-page、invoice-pay-page、order-detail-page、payment-detail-page、profile-page、referral-page、ticket-detail-page 共 10 个页面根类。→ 同一套布局约束只对 6+3 个页面生效，剩下 10 个页面在手机上白拿一层多余内边距。 |
| C-02 | 全站 | 设计 | 高 | 卡片体系被系统性绕过：26 处自绘卡片（box-shadow 用 shadow-1 硬套）+ 59 处 :bordered="false"，绕过 theme.css:186-192 的卡片基线；同时存在 record-page.less:8-13、AuthShell.vue:226-233、checkout-resume/index.vue:196-208、order-create/index.vue:25-39 四份手写卡片外观。 |
| C-03 | 全站 | 视觉 | 中 | 1px 边框两套写法并存：border: thin（59 处）与 0.0625rem（42 处），二者在部分缩放/高 DPI 下渲染宽度不同；描边色也分裂为 --td-component-border 与 --td-stroke 两套。 |
| C-04 | 全站 | 交互 | 高 | 三态（loading / error / empty + retry）覆盖不全：违反 docs/FRONTEND.md:119 与 docs/DESIGN.md:202。明确缺项：verification/index.vue:3、service-console/compute/index.vue:5、service-console/nat/index.vue:5 只有 LoadingState 无 error/empty/retry；content-list/ContentListPage.vue:102、:113 空态无引导出口。 |
| C-05 | 全站 | 视觉 | 中 | 四种加载表达并存：shared/user-v3/components/LoadingState.vue（60vh 品牌圆点）、t-skeleton、t-loading text、裸 t-loading（entry.vue:2-4）；同一应用内出现两种以上「正在加载」语言。 |
| C-06 | 全站 | 视觉 | 高 | 断点语义混用：@screen-sm-max（991px）与 @screen-mobile-max（767.98px）在 8 处被互换使用（profile/index.vue:493、layouts/components/Header.vue:495、:657、style/layout.less:84、:169、:308、record-page.less:273，以及 recharge/index.vue:276 的 JS 侧 767.68px）。后果：平板（768–991px）与手机（≤767.98px）形态判断不一致，出现「JS 判桌面、CSS 判移动」。 |
| C-07 | 全站 | 视觉 | 中 | 触控基线不完整：theme.css:760-771 的 44px 最小触控只覆盖 .t-button，自绘 button 全部漏网（services 30px、IP 复制 32px、tab 更多 18×18px、优惠券详情约 13px、toc 行 28px、密码眼睛 20–24px）。 |
| C-08 | 全站 | 交互 | 中 | 焦点可见性缺失：src/style/reset.less:41-46 已定义 :focus-visible 描边，但 recharge/index.less:173-177 用 outline:0 覆盖；全 src 仅 5 处 :focus-visible → 键盘用户大面积丢失焦点反馈（WCAG 2.4.7）。 |
| C-09 | 全站 | 交互 | 中 | 键盘可达性缺口：role="link" 的移动卡片缺 Space 键处理且内嵌按钮（orders/index.vue:111-120、invoices/index.vue:92-102、payments/index.vue:72-81）；payments/index.vue:80 的 @keydown.enter 缺 .self → 卡片内按钮回车双重导航；payments/index.vue:94-98 的 a 无 href 不可聚焦；三个详情页面包屑 @click 键盘不可达。 |
| C-10 | 全站 | 交互 | 中 | 竞态防护缺失：docs/FRONTEND.md §5 明确要求列表/详情做 requestSeq 防护，但 invoice-detail-view/index.vue:244-266、payment-detail/index.vue:130-152、useTickets.ts:507-515 均无；可对标的正例是 useRecords.ts:295、:300、:330-337（shallowRef + requestSeq）。 |
| C-11 | 全站 | 交互 | 高 | 提交类按钮缺 :disabled，只靠 :loading：登录/注册/找回密码三页（login.vue:67-69、:121-129、register.vue:111-113、forgot-password.vue:85-87）在请求中按回车仍可再次提交（@submit 与 @click 双入口，login.vue:23、:80 / register.vue:12 / forgot-password.vue:12 的 @submit 不检查 loading）。 |
| C-12 | 全站 | 交互 | 高 | 确认框无防重入：useOrders.ts:82-110（cancelingIds 与 setConfirmLoading 都在 onConfirm 内，打开前不占位）、recharge/index.vue:335-348 与 :364-388（无 setConfirmLoading，移动端还 dialog.destroy() + void handleCreateOrder() 不 await）→ 连点叠多个确认框，逐个确认即并发下单/取消。 |
| C-13 | 全站 | 设计 | 中 | 页面头三件套未采纳：theme.css:440-483 的 #app .page-tabs-toolbar / .page-inline-actions / .page-inline-hero 在 v4-console 与 v3-www 的 src 中命中 **0**（仅 admin 用 page-inline-actions）→ 令牌库提供的页面头规范是死 CSS，各页自造头部结构。 |
| C-14 | 全站 | 设计 | 中 | 页面级 h1 缺失 7 处：dashboard/index.vue:21 只有 h2；services、tickets、catalog、compute、nat、result/404 无 h1；verification/index.vue:8 把状态文案当 h1。文档大纲与屏幕阅读器导航失效。 |
| C-15 | 全站 | 视觉 | 中 | 令牌误用：尺寸令牌被当边框宽度（dashboard/index.less:151、ContentDetailPage.vue:200-213 用 --td-comp-size-xxxs），行高令牌被当间距（shared-auth.less:41-43 用 --td-line-height-body-small），同语义高度混用 --td-comp-size-xxxl / xxxxl / xxxxxl 三量级（主进程已核实三者均为 TDesign 合法令牌，问题在混用而非不存在）。 |
| C-16 | 全站 | 视觉 | 中 | 空态两套视觉语言：页面级走 shared/user-v3/components/DataState.vue（无 scoped 样式，全项目对 t-empty 零规则）；详情页内联自写 .related-empty、.order-detail-empty、.detail-empty（两个还完全未被模板使用）。 |
| C-17 | 全站 | 交互 | 中 | 对话框契约分裂：8–9 个 console dialog 出现四种宽度（28/30/34/36rem）+ 三种表单形态 + 两种页脚；NatForwardingDialog.vue:2-9 无 width、用旧式 @confirm、:label-width="96" 无单位；services/index.vue:278-359 还内联了同名 dialog。 |
| C-18 | 全站 | 设计 | 中 | 桌面/移动双套模板同存 DOM（invoices:44 vs :92-139、orders:44 vs :110-172、payments:38 vs :71-100、tickets:37-59 vs :61-75、coupons:42-71 vs :97-134、profile:7-18 vs :20-29、ticket-detail 桌面 aside vs :65-89、recharge:5-134 vs :136-252）→ 既增体积也增维护面，任何改动需同步两处（已实际出现只改一处的情况）。 |
| C-19 | 全站 | 设计 | 中 | 死代码/死样式：.record-list-card 在三页使用（invoices/index.vue:32、orders/index.vue:32、payments/index.vue:26）但 src 内零定义；invoice-detail/index.less 58 个顶层选择器中 12 个模板 grep 为 0；console-tables.less:10 的 #app .client-invoices .invoice-table-card 模板不存在；referral/index.vue:338-342 被 :344-349 覆盖；profile/index.vue:479-491 的 .agent-list 零使用；ContentListPage.vue:344-349 对 flex 元素设 grid。 |
| C-20 | 全站 | 视觉 | 中 | !important 与 !important 自述冲突：service-console/components/styles.less:1-3 注释自称「禁止 !important」实测 8 处；profile/index.vue:549-559 有 4 处；Header.vue:644-687 非 scoped 块内 5 处固化 .t-popup.inbox-popup（覆盖 TDesign 内部，升级即碎）。 |
| C-21 | 全站 | 视觉 | 中 | 令牌化程度极不均衡：AuthShell.vue bareRem 63 / 裸 font-size 14 / 间距令牌 **0**；shared-auth.less bareRem 20；invoice-detail/index.less bareRem 70 / 圆角裸 24；recharge/index.less bareRem 22；service-console/components/styles.less 104 处裸 rem 间距。对照 docs/DESIGN.md「所有颜色字体间距圆角阴影必须映射 TDesign CSS 变量」。 |
| C-22 | 全站 | 交互 | 中 | 错误提示静默吞掉：useProfile.ts（loadProfile）、useInbox.ts:67-75、useDashboardSecondary.ts、dashboard/index.vue:722（.catch(() => {})）、useConsoleTabs.ts 多处 → 失败无提示或只 toast，用户无法判断数据是否可信。 |
| C-23 | 全站 | 视觉 | 低 | 裸 px 残留：布局层 25 处（LayoutContent.vue:207-209、:222；Header.vue 9 处；SideNav/layout.less 侧栏宽度 6 处；MenuContent.vue:139 的 10px）；profile/index.vue:415、:427 的 0.8125rem 为全仓唯一 13px。 |
| C-24 | 全站 | 设计 | 低 | 圆角无固定映射：services 五档、service-console/components/styles.less 四档、coupons 三档并存；docs/DESIGN.md 明确「禁止全局覆写圆角」但未禁止局部乱用，实际已退化为随手取值。 |
| C-25 | 全站 | 性能·渲染 | 中 | 模板承担视图模型：services/index.vue:74-284、recharge/index.vue:68-191（paymentOptionKey 调 11 次）、invoice-detail/index.vue:227-245、referral/index.vue:73-149、ticket-detail/index.vue:100/102/134/136（parseAttachments 4 次）→ 每帧重复计算，应提为 computed。 |
| C-26 | 全站 | 交互 | 中 | 分页双事件风险：services/index.vue:266-276、tickets/index.vue:79-89、coupons/index.vue:74-84 与 :137-147 均同时绑 @change 与 @page-size-change，若 TDesign 改页大小时同时 emit change 则一次操作两次请求（node_modules 未安装，标待确认）。 |

### 2.15 性能 · 加载体积与资源冗余（PB-*）

> 全部字节数为 `dist` 实测（构建时间 2026-10-28 17:09:37）。基线：dist 513 个文件共 **4,431,412 B**；dist/assets/js 79 个共 **1,771,711 B**；dist/assets/css 45 个共 **474,007 B**；dist/vnc 145 个 614,773 B；.gz 808,845 B + .br 686,131 B；首屏 4 条资源 gzip 合计 **207,059 B**。
> 与页面小节同源的条目已标注「↔」，汇总统计时按去重口径计。

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| PB-01 | dist/assets/js/index-D7G2SG7y.js（入口 chunk，435,665 B / gzip 115,628 B / br 98,068 B） | 产物体积 | 高 | 唯一入口桶把 **42 个 TDesign 组件**（TAside TAvatar TAvatarGroup TBadge TBreadcrumb TButton TCheckTag TCheckTagGroup TConfigProvider TContent TDialog TDialogCard TDivider TDropdown TFakeArrow TFooter THeadMenu THeader TImage TLayout TLoading TMenu TMessage TPopup TSpace TSubmenu TTabNav TTabPanel TTabs TTag TTooltip 等）+ **26 个图标** + nprogress + popper + dayjs 核心 + pinia 持久化 + 全部共用业务逻辑打进同一 chunk，占 js 总量的 **24.6%**。 |
| PB-02 | frontend-user-v4-console/src/api/client.ts（432 行 / 22,137 B，被 26 个文件 import） | 依赖冗余 | 高 | 入口实测含 **107 条** /v2/client/* 端点字符串，而 src/api/*.ts 去重后仅 **55 条** → finance / orders / invoices / recharge / ticket / service 的接口定义与 normalize 逻辑全部进了首屏，而首屏 dashboard 实际只用少数几个。 |
| PB-03 | dist/assets/js/index-1a-F5gNG.js、index-Db4HWjNr.js、index-DtTtFyRm.js、index-ChGspkD0.js、index-C9vWQ-d_.js | 产物体积 | 高 | 非首页路由**边际成本 55–69 万 B**：nat 控制台 26 片 js 560,966 B + 13 片 css 130,969 B = **691,935 B**；另一控制台 559,469 + 130,969 = 690,438 B；invoices 564,250 + 112,877 = 677,127 B；orders 558,296 + 112,873 = 671,169 B；payments 553,108 + 112,922 = 666,030 B。每次都要重拉表格桶 index-B3oN5CV9.js（230,584 B）+ 表单选择器桶 index-BxQcTWLs.js（80,232 B）+ RecordQuickFilters（130,478 B）。 |
| PB-04 | frontend-user-v4-console/src/pages/client/components/RecordQuickFilters.vue（1,373 B，L45-59 仅 2 条 scoped 规则，6 个 t-tag 循环） | 产物体积 | 中 | 该微型组件因引入 t-date-picker 日期范围链，膨胀成 130,478 B 的共享异步桶 RecordQuickFilters-DEcGnKAR.js（gzip 32,169 B）+ 21,808 B CSS（169 处 t-date-picker）。仅被 invoices/index.vue:172、orders/index.vue:206、payments/index.vue:127 引用。 |
| PB-05 | frontend-user-v4-console/vite.config.ts:463-466 | 依赖冗余 | 中 | optimizeDeps.include 含 **vue-i18n**，但 console 未安装未引用（src 零命中、package.json 无该依赖，仅 frontend-admin-v3/package.json:35 声明）→ dev 预打包出 189,098 B + map 389,272 B；同条把图标库 barrel 预打包成 **13,523,939 B**，node_modules/.vite/deps 合计 34,437,404 B。 |
| PB-06 | frontend-user-v4-console/vite.config.ts:251-286 | 产物体积 | 中 | resolveManualChunk 只返回 vendor-axios / vendor-vue / vendor-qrcode / vendor-content，tdesign-vue-next、tdesign-icons-vue-next、@popperjs、lodash-es 全部不归桶 → 实测 5 个「单图标 + 220 B 样板」碎片：search 4,306 B、error-circle 3,478 B、check-circle 3,433 B、lock-on 3,426 B、refresh 2,535 B；另有 logo-wechatpay-filled 10,141 B。 |
| PB-07 | frontend-user-v4-console/src/pages/client/service-console/compute/index.vue:63、nat/index.vue:65 ↔ SC-08 | 产物体积 | 中 | `<style src="../components/styles.less" lang="less">`（非 scoped）使该 Less 被编成 JS 模块：dist/assets/js/**styles.less_vue_type_style_index_0_src_true_lang**-CkUNEie1.js 105,006 B（gzip 27,496 B）+ dist/assets/css/styles-Fka1ZKWX.css 25,171 B → 样式进了 JS 依赖图。 |
| PB-08 | dist/assets/css/index-Dswn5fJo.css（173,523 B / gzip 27,660 B，1,277 条规则） | 产物体积 | 中 | .t-* 规则占 **116,178 B（67%）**：.t-button 四 variant 合计 30,541 B（outline 11,356 / base 11,321 / dashed 11,255 / text 6,609）为最大单项；.t-default-menu 9,610、.t-dialog 8,011、.t-dropdown 7,806、.t-tabs 7,051、.t-popup 5,396；非 .t- 约 47,460 B。 |
| PB-09 | E:/caiwu/theme.css:1、:124 vs dist/assets/css/index-Dswn5fJo.css | 产物体积 | 中 | theme.css 明令「仅浅色主题」「禁止重新引入暗色变量块」，产物却仍带 5,602 B 的 `:root.dark,:root[theme-mode=dark]` 块（133 个变量、偏移 137844），另有偏移 132295 的 5,549 B 默认品牌色块与两块共 5,157 B → 约 **16,308 B（9.4% 首屏 CSS）** 被 theme.css 整体覆盖，属确定性冗余。 |
| PB-10 | frontend-user-v4-console/public/vnc（57 源文件 614,773 B，dist 145 文件） | 资源冗余 | 中 | noVNC 查看器整目录随前端产物发布（vnc.html 33,915 B、core/rfb.js 122,518 B、pako deflate 60,286 B、inflate 47,281 B、trees 38,767 B、keysym 34,609 B），与首屏无关；后端 backend/app/Services/ClientServiceConsole/ServiceVncService.php:611 按 `rtrim($novncBaseUrl,'/') . '/vnc.html'` 运行时取用（base 由 backend/app/Support/PublicUrl.php:24 `PublicUrl::console('/vnc')` 拼出），可独立部署。 |
| PB-11 | backend/scripts/frontend_budget.mjs（FIRST_SCREEN_BUDGETS console=420,000；CHUNK_BUDGETS=1,120,000；CSS_CHUNK_BUDGETS=305,000） | 产物体积 | 中 | 三道门禁全富余：首屏 207,059 B（余 51%）、最大 js 435,665 B（余 61%）、最大 css 173,523 B（余 43%）→ **完全无法发现** PB-01 的 24.6% 单桶占比与 PB-03 的 55–69 万 B 边际成本。 |
| PB-12 | frontend-user-v4-console/src/assets/**（19 个文件 1,855,723 B） | 资源冗余 | 低 | 全仓零引用（对 "@/assets"、".png"、".svg'" 的 grep 无业务命中，唯一含 assets 的是 src/utils/apiOrigin.ts:37 的注释；dist 内也无对应产物）：assets-login-bg-white.png 995,753 B、assets-login-bg-black.png 807,777 B 等属迁移残留。无运行时开销，仅仓库/发布体积。 |
| PB-13 | dist/assets/css/index-Dswn5fJo.css 偏移 862 与 165,764 | 资源冗余 | 低 | 两处 @font-face 字节完全相同（4,128 B base64 TencentSansW7），源于 src/style/index.less:3 与 src/style/layout.less:1 各自 @import font-family.less（layout.less 经 src/layouts/index.vue:27 引入）→ 同一字体声明注入两次，且 base64 内联无法并行下载或缓存复用。 |
| PB-14 | frontend-user-v4-console/vite.config.ts:305-351 | 产物体积 | 低 | createPrecompressedAssetsPlugin（阈值 1024）副产物：.gz 157 个 808,845 B + .br 157 个 686,131 B = **1,494,976 B，占 dist 总体积 33.7%**；对 dist/vnc/core/decoders/*.js 这类 1,622 B 小文件也生成 br，收益接近 0。 |
| PB-15 | frontend-user-v4-console/package.json:25 | 依赖冗余 | 低 | dayjs 的直接依赖只剩两个死文件在用；运行时 dayjs 由 TDesign date-picker 隐式带入（RecordQuickFilters 桶内 43 处 dayjs 标记）。 |
| PB-16 | frontend-user-v4-console/src/utils/charts.ts（915 B）、src/utils/date.ts（321 B） | 依赖冗余 | 低 | 两文件全仓零引用，却把 dayjs 变成显式依赖。 |
| PB-17 | frontend-user-v4-console/vite.config.ts:11、:429；package.json:65 | 依赖冗余 | 低 | vite-svg-loader 已启用，但 src 内不存在任何 ?component / ?url / ?raw 的 svg 导入（grep 无命中）→ 对 console 完全空转，只增加每次构建的 transform 钩子。 |
| PB-18 | frontend-user-v4-console/vite.config.ts:446 | 产物体积 | 低 | build.target = 'es2018' → 入口残余 'void 0' 894 处、'!==void 0' 145 处、'===null\|\|' 242 处，?? 与 ?. 均为 0 处；控制台面向现代浏览器却全面降级，体积与可读性双损（是否有旧浏览器要求待确认）。 |
| PB-19 | dist/assets/js（79 个 .js） | 产物体积 | 低 | 碎片化：**20 个 chunk 小于 3 KB**（合计约 27.3 KB），最小 isNil 45 B、index-Bmge-Wig 150 B、constants 216 B、snapshotDefaults 460 B、LoadingState 694 B，每个仍是一次 HTTP 请求。 |
| PB-20 | dist/index.html:22-25；frontend-user-v4-console/vite.config.ts:353-371；src/main.ts:24-27 | 首屏链路 | 低 | 首屏仅 4 条资源、无 preconnect / dns-prefetch（createIndexNetworkHintsPlugin 仅在 assetBase 为 http(s) 绝对地址时注入，同源部署不生效）；而 src/main.ts:24-27 会预热 static.geetest.com，登录页（16 分片）因此晚一个 RTT。 |
| PB-21 | frontend-user-v4-console/public/favicon.ico（16,958 B，单图 64×64 32bpp） | 资源冗余 | 低 | 单张 64×64 用 16.9 KB 的 .ico 承载，dist 原样复制，每次首访都要请求。 |
| PB-22 | dist/assets/css（同文件内重复规则） | 产物体积 | 低 | 除 PB-13 的 @font-face 外，同文件内仍有小段重复：.service-filter-bar[data-v-c1a82600] 83 B ×2、.auth-card__title[data-v-4b3aca31] 77 B ×2、.recharge-panel[data-v-57bbcf8f] 59 B ×2；根因未定位到具体源文件行（待确认）。 |
| PB-23 | frontend-user-v4-console/vite.config.ts:23-188、:200-219；src 内 15 处 from 'tdesign-vue-next' | 按需引入 | — | **已核实无问题（防误报）**：123 条 TDESIGN_COMPONENT_DIRS 映射与模板实际使用的 48 个 t-* 全部命中（unmapped=[]）；15 处 from 'tdesign-vue-next' 全为 import type，零运行时整包引入；运行时只走 5 条子路径（es/message/ 32 次、es/dialog/ 9 次、es/style/ 1、es/locale/ 1、es/input/ 1）。问题在「归桶」不在「按需」。 |
| PB-24 | dist/assets/js 跨 chunk | 资源冗余 | — | **已核实无问题**：跨分片图标名（name:"*Icon"）重复 0 个、跨分片 T* 组件名重复 0 个 → Rollup 去重正常。 |
| PB-25 | frontend-user-v4-console/vite.config.ts:447 | 产物体积 | — | **已核实无问题**：dist 内 .map 文件 0 个，未泄漏源码。 |
| PB-26 | dist 总量基线 | 产物体积 | — | **基线记录**（用于后续度量）：见本节开头。首屏 4 条资源 gzip 合计 207,059 B。 |

### 2.16 性能 · 渲染与运行时反模式（PR-*）

> 扫描基线（避免误报）：**v-for 缺 :key 0 处**（159 个 .vue/.ts 全量扫描）、模板内数组 transform 0 处、`watch(..., { deep: true })` **0 处**、@scroll/@touchmove 无节流 0 处、requestAnimationFrame 0 处、ResizeObserver 0 处；11 个 `<img>` 中 9 个带 loading="lazy" decoding="async"（缺的 2 处是 AuthShell.vue:6 与 SideNav.vue:25 的站点 logo）；setInterval/setTimeout 逐条核对均有清理；addEventListener 5 处中 4 处正确移除。

| 编号 | 位置 | 维度 | 严重 | 问题描述 |
| --- | --- | --- | --- | --- |
| PR-01 | frontend-user-v4-console/src/layouts/components/Content.vue:11-14；src/store/modules/tabs-router.ts:38-44；LayoutContent.vue:98 ↔ L-10 | 性能·渲染 | 高 | keep-alive 默认对**除 5 个 meta.keepAlive===false 路由外的全部页面**生效；appendTabRouterList 只按 path 去重、**无数量上限**，:max="10" 的 LRU 被持续增长的路由集合击穿 → 已访问过的每个页面实例（含 5 个 1s 倒计时、2 个实名轮询、支付宝轮询、matchMedia 监听、SVG 采样点）在 tab 关闭前都不销毁。全仓最系统性风险。 |
| PR-02 | frontend-user-v4-console/src/pages/client/service-console/composables/useConsoleMonitor.ts:68-71、:74-106、:108-176、:204-227 ↔ SC-14 | 性能·渲染 | 高 | monitorChartViews 是 computed，但每次都全量重建：normalizeMonitorSeries 逐点 map/filter、resolveMonitorValueRange 用 flatMap + spread、buildMonitorRenderPoints 逐点 toFixed×2、buildMonitorSmoothPath 逐点三次贝塞尔、resolveMonitorSummaryText 还被调 4 次（内部再 flatMap 全量）。任意 monitorState 变化（loading 翻转也算）即全量重算，无降采样、无缓存。 |
| PR-03 | frontend-user-v4-console/src/pages/client/service-console/composables/useConsoleMonitor.ts:143-153、:319-330、:249-252 | 性能·渲染 | 高 | `Math.min(...values)` / `Math.max(...values)` 展开数组，点数随 3h/1d/7d/30d 区间线性增长，>约 10 万点即 **RangeError: Maximum call stack size exceeded**，整组件渲染抛错白屏。 |
| PR-04 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/MonitorTab.vue:71、:73、:75、:79、:95、:99、:101（:97 再 1 次）↔ SC-15 | 性能·渲染 | 高 | 同一 chart 一次渲染调 resolveActiveMonitorPointCached **7 次**，:97 内部再调 1 次；配合 useConsoleMonitor.ts:255-271 的 @mousemove 无节流/无 rAF → 鼠标每移 1px 就写 activeMonitorPoint，触发 7 次模板求值 × 图表数，每次内部又 map/filter 全部 series points。 |
| PR-05 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/VncTab.vue:14-22 ↔ SC-04 | 性能·渲染 | 高 | `<iframe v-if="vncUrl" :key="vncUrl">` 生命周期绑在 v-if 上，上层 compute/index.vue:12-17 / nat/index.vue:12-18 用 v-if/v-else-if 链渲染 tab → 切 tab 立刻卸载 iframe，WebSocket 直接断开，一次性 VNC 地址（useConsoleVnc.ts:70-75）不可复用；无 onBeforeUnmount 清场。 |
| PR-06 | frontend-user-v4-console/src/domains/services/console/useConsoleDetail.ts:119-126、:87-95 ↔ SC-05 | 性能·渲染 | 高 | bootstrap 在 loadDetailBase 后同时发起 loadRemoteStatus(true) 与 fetchConnectionInfo()，两者都写 detail，而 fetchConnectionInfo 用 `detail.value = normalizeConsoleDetail(...)` **整体替换**、覆盖 loadRemoteStatus 的 mergeConsoleDetail 结果；fetchConnectionInfo 内部 clientApi.serviceBaseDetail（api/client.ts:216-228）又并发 GET detail + connection，与 loadDetailBase 完全重复；两个 catch 静默（:92-94、:103-105）。 |
| PR-07 | frontend-user-v4-console/src/pages/client/service-console/entry.vue:21-42 ↔ SC-05 | 性能·渲染 | 高 | entry 在 onMounted 调 clientApi.serviceDetail(id) 判断类型后 router.replace 到目标页，目标页 bootstrap 再走一次 serviceDetail，加上 PR-06 的两次 → **打开一次控制台 ≥3 次 detail 级请求**，且第一次的结果被丢弃。 |
| PR-08 | frontend-user-v4-console/src/domains/services/useServiceConsole.ts:301-322 ↔ S-07 | 性能·渲染 | 高 | watch(activeTab) 内 5 个 `await load*()` **串行**执行（monitor/security/nat/logs/finance），快速连点 tab 时前一次 watch 不取消，5 个请求已全量发出，只靠 useConsoleTabs.ts:90-93 的 requestSeq 丢弃响应 → 一次快速切换产生 10+ 个真实 HTTP 请求。 |
| PR-09 | frontend-user-v4-console/src/pages/client/dashboard/composables/useDashboardSecondary.ts:49-67、:70-104 ↔ D-06 | 性能·渲染 | 高 | fetchAllPagedList 用 for 串行翻页（pageSize=100、maxPages 默认 20），loadConsumptionCharts 并发两条链（balanceLogs maxPages=10、invoices 当年全年 maxPages=20）→ 最坏 11–20 次串行 HTTP/次进入；无 AbortController（utils/request.ts 无取消注入点）、无缓存、无去重，反复进出叠加未完成链路；:73-74 一进来就把 invoicesLoaded / balanceLogsLoaded 同时置 false → 局部重试变全局重拉。 |
| PR-10 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:733-740 ↔ D-05 | 性能·渲染 | 高 | 首屏一次性 Promise.allSettled **6 路并发**（notices、tickets、financeLedgerSummary、couponsSummary、groupedOverview、referralOverview），挤占浏览器 6 连接上限、阻塞后续静态资源；其中 referralOverview、couponsSummary 非首屏必需。 |
| PR-11 | frontend-user-v4-console/src/pages/client/invoices/index.vue:44 与 :92-139；orders/index.vue:44 与 :110-172；payments/index.vue:38 与 :71-100 ↔ C-18 | 性能·渲染 | 高 | 三页「桌面 t-table + 移动 record-mobile-list」**两套模板同存 DOM**，仅靠 record-page.less:67-69 与 :303-311 的 display:none 切换 → 每页数据渲染两遍，隐藏分支仍参与 vdom diff 与模板函数调用（每行 5–8 次 formatMoney/formatDateTime/couponDiscountOf/memberDiscountOf）。 |
| PR-12 | frontend-user-v4-console/src/pages/client/services/index.vue:74、:87、:102、:117、:143、:144、:188、:199、:223、:224、:284 ↔ S-10 | 性能·渲染 | 高 | 同一渲染内 resolveServiceName 调 **7 次**、findListSpecValue 调 **4 次**，另有 String()×4、Number()×3；卡片视图每张卡约 12 次格式化调用（含 :94-108 的 shouldShowServiceOsIcon / resolveServiceOsIcon / resolveServiceOsText / resolveServiceMark、:148 isExpiringSoon、:154-155 resolveTdesignStatusTheme / resolveRuntimeStatusLabel）。 |
| PR-13 | frontend-user-v4-console/src/pages/client/services/index.vue:253（定义 :440-446） | 性能·渲染 | 高 | `:options="actionOptions(row)"` 直接调函数，actionOptions 每次返回**新数组字面量** → t-dropdown 每帧收到新 props 引用，触发内部 watcher/props diff，且列表视图每行一个。 |
| PR-14 | frontend-user-v4-console/src/pages/client/content-detail/ContentDetailPage.vue:33；src/domains/content/useContent.ts:325-329、:376-397、:407-413 ↔ N-10 | 性能·渲染 | 高 | v-html 一次性注入整篇正文；同时 watch → nextTick → buildToc 用 `container.querySelectorAll('h1,h2,h3,h4')` 全量遍历并逐个写 heading.id → 「markdown 解析 + v-html 注入 + 全量 DOM 扫描 + 逐节点写属性」全在同一帧完成，长文首屏明显卡顿且无骨架。 |
| PR-15 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/OverviewTab.vue:53、:54、:55、:58 | 性能·渲染 | 高 | findSpecValue([...]) 在同一渲染内调用 4 次（CPU/内存/系统盘/数据盘），每次内部遍历 specs 并做 normalizeToken 正则替换（useConsoleCore.ts:138-150）。 |
| PR-16 | frontend-user-v4-console/src/pages/client/service-console/components/InfoCell.ts:32-35、:19-26、:48-60 | 性能·渲染 | 中 | onUpdated 里无条件 nextTick 后读 textEl.scrollWidth / clientWidth 重判截断 → 父组件任一更新都触发；概览页一屏 10+ 个 InfoCell，每处都是一次「写后读」强制同步布局；:48-60 在 isTruncated 翻转时把文本包进 TPopup，导致该子树整体重建。 |
| PR-17 | frontend-user-v4-console/src/pages/client/ticket-detail/index.vue:100 与 :102、:134 与 :136 ↔ T-13 | 性能·渲染 | 中 | parseAttachments(detail) 在 v-if 与 v-for 里各调一次、parseAttachments(reply) 同样各一次；parseAttachments（useTickets.ts:87-100）每次 map+filter 生成新数组 → 回复数 N 时模板每帧 2N+2 次数组重建，配合 keep-alive 常驻代价持续存在。 |
| PR-18 | frontend-user-v4-console/src/pages/client/invoices/index.vue:47、52、56、57、59、60、70、99、104、110、111、112、115、116、118 | 性能·渲染 | 中 | 单文件模板内 formatMoney 8 次、couponDiscountOf 5 次、memberDiscountOf 5 次、resolveInvoiceNo 4 次；叠加 PR-11 双渲染后每个数据行实际执行两遍。 |
| PR-19 | frontend-user-v4-console/src/pages/client/orders/index.vue:58、63、69、128、130、134 | 性能·渲染 | 中 | formatMoney 6 次、couponDiscountOf 5 次、memberDiscountOf 5 次、Number() 4 次，另有 :101、:165 的 isCanceling(row.id) 每行函数调用。 |
| PR-20 | frontend-user-v4-console/src/pages/client/invoice-detail/index.vue:80、131、149、197、203、207、211、227、230、232、235、245 | 性能·渲染 | 中 | formatMoney 7 次、paymentOptionKey(method) 5 次（列表与选中态各算一遍）；该页已有 useInvoiceDetailHelpers.ts:160-187 的 computed 记忆化习惯（正例），但支付方式列表未纳入。 |
| PR-21 | frontend-user-v4-console/src/pages/client/recharge/index.vue:68、71、72、73、82、180、181、182、183、185、191（定义 :354） | 性能·渲染 | 中 | paymentOptionKey(method) 在一次渲染里对同一 method 调用 **11 次**，每次 String().trim() 拼字符串；虽然 :5/:136 已是 v-if 条件渲染（正例），但断点切换后仍需重算 11 次。 |
| PR-22 | frontend-user-v4-console/src/pages/client/referral/index.vue:60、:73、:76、:81、:99、:103、:114、:126、:137、:149 | 性能·渲染 | 中 | money() 6 次模板调用；同时挂 **4 个 t-table** 各带真实分页对象，而 useReferral.ts:214-231 的 loadAll 用 Promise.all 发 5 个请求、:322-324 onMounted 又并发 loadAlipayAccount → 首屏 6 个并发请求 + 4 张表结构同时建立（t-tabs 面板是否惰性挂载待确认）。 |
| PR-23 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:14-20、:182-189 ↔ L-06 | 性能·渲染 | 中 | `:key="\`\${routeItem.path}_\${index}\`"` 把数组下标拼进 key，而 handleDragend 直接交换 tabRouters 数组元素 → 拖拽后所有 tab-panel 的 key 全部变化，Vue 视为全新节点，整批重建。 |
| PR-24 | frontend-user-v4-console/src/layouts/components/LayoutContent.vue:34-46 | 性能·渲染 | 中 | 每个 tab 的 t-dropdown 都用内联对象字面量 `:popup-props="{ overlayClassName, onVisibleChange: <新箭头函数>, visible }"` → 每次 re-render 产生新对象与新函数引用，t-dropdown 内部 props 比较必然不等。 |
| PR-25 | frontend-user-v4-console/src/layouts/index.vue:66-72 ↔ L-12 | 性能·渲染 | 中 | `document.querySelector(\`.\${prefix}-layout\`).scrollTo({ top:0, behavior:'smooth' })` 既未判空（节点缺失时抛 TypeError 打断后续逻辑），又在**每次**路由切换启动 smooth 滚动合成动画，与目标页首屏渲染争主线程。 |
| PR-26 | frontend-user-v4-console/src/layouts/components/FrameContent.vue:37-39、:51-70 ↔ L-29 | 性能·渲染 | 中 | setup 顶层同步执行 getComputedStyle 两次取 --td-comp-size-xxxl / --td-comp-paddingTB-xxl 存入闭包 → 主题切换后 calcHeight 仍用旧值；:51-70 每次 calcHeight 还有 3 次 document.querySelector（.t-tabs__nav / .t-breadcrumb / .t-layout__footer）未缓存。 |
| PR-27 | frontend-user-v4-console/src/domains/account/useProfile.ts:42-45、:61（创建点 :199、:319、:345、:372、:397）↔ AC-23 | 性能·渲染 | 中 | 手机/邮箱/旧手机/旧邮箱/密码重置共 **5 个独立 setInterval**，各 1s 递减并触发 securityItems（:98-137）重算 → 5 表同跑时每秒 5 次响应式更新 + 模板重渲染。:582-588 有统一 onBeforeUnmount 清理（正例，无泄漏）。 |
| PR-28 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:120-141、:163-173 | 性能·渲染 | 中 | monthlyBars 12 个 + dailyBars 7 个，每个都用独立 t-tooltip 包裹 → 首屏一次性实例化 **19 个 TPopup**（各自带 portal/定位/事件监听）。 |
| PR-29 | frontend-user-v4-console/src/domains/services/console/useConsoleTabs.ts:27、:45、:103-125 | 性能·渲染 | 中 | monitor 请求 page_size 固定 10 且 timeout 12000；compute/index.vue:12-17 卸载组件使 useConsoleMonitor 派生状态全丢，但 loadedTabs 在 useServiceConsole 层保留 → 两处生命周期不同步，切回监控 tab 整段重拉。 |
| PR-30 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/SecurityTab.vue:93-111 与 :114-146 ↔ SC-19 | 性能·渲染 | 中 | 同一份 securityState.rules 既渲染 t-table 又渲染卡片列表（.security-rules-cards），仅靠 CSS 显隐 → 规则行 DOM 双份，行内还各调 resolve 类函数。 |
| PR-31 | frontend-user-v4-console/src/domains/services/console/useConsoleTrafficPackages.ts:43-63 | 性能·渲染 | 中 | loadTrafficPackages 完成后若首个 target_value>0 立即 `await quoteTrafficPackage(...)` → 每次打开弹窗必发 2 个串行请求；无请求序号，快速切档位时旧响应可覆盖新报价。 |
| PR-32 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/OverviewTab.vue:53、:54、:55、:58（同 PR-15 的调用点） | 性能·渲染 | 中 | 与 PR-15 同源的另一面：这 4 次调用在模板而非 computed 内，故 stats/specs 变化时 4 次全量遍历无法被缓存；且 copyText 另有 5 处事件绑定。 |
| PR-33 | frontend-user-v4-console/src/pages/client/service-console/components/ConsoleHeader.vue:148-153 | 性能·渲染 | 中 | moreOptions computed 每次 detail 变化都重建新数组并直接传 t-dropdown :options，而 detail 因 PR-06 的整体替换 / merge 频繁换引用 → 表头下拉每轮都收到新 options。 |
| PR-34 | frontend-user-v4-console/src/pages/client/service-console/components/tabs/LogsTab.vue:12-23 与 :24-33 | 性能·渲染 | 中 | t-table 用 `:pagination="null"` 后另挂外置 t-pagination，两套控件状态需手工同步；切页会重建整表。 |
| PR-35 | frontend-user-v4-console/src/composables/useGeeTestCaptcha.ts:117-135、:329-338 | 性能·渲染 | 中 | 对已存在的 geetest script 注册 load/error 监听（:120-121，once 但从不 removeEventListener），releaseGeeTestScript(:140-145) 只 remove 节点并清 window.initGeetest4、不清监听；模块级 geetestConsumers 计数(:34) 在 login/register/forgot-password/verification/profile 多页并存时靠「最后一个卸载才释放」判定，配合 PR-01 的常驻实际很难归零。 |
| PR-36 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:437 | 性能·渲染 | 低 | `const avatarUrl = computed(() => '')` 恒返回空串，模板 :19 的 t-avatar :image 永远 undefined → 既是死代码，也说明头像取数逻辑缺失（功能缺陷）。 |
| PR-37 | frontend-user-v4-console/src/pages/client/dashboard/index.vue:477、:479-488、:490-494 | 性能·渲染 | 低 | currentYearLabel / greetingText / todayDateText 都在 computed 内读 `new Date()` 但无响应式依赖 → computed 只求值一次，跨天/跨小时不刷新（叠加 PR-01 常驻后更明显）。 |
| PR-38 | frontend-user-v4-console/src/pages/client/tickets/index.vue:154；ticket-detail/index.vue:166 | 性能·渲染 | 低 | `:key="file.id \|\| file.path \|\| index"` 在暂存项既无 id 也无 path 时退化为索引，删除中间项可能复用错误 DOM 节点（影响限于上传预览，≤9 项）。 |
| PR-39 | frontend-user-v4-console/src/api/client.ts:237-250；ticket-detail/index.vue:100-102、:134-136 | 性能·渲染 | 低 | detail.replies 已 page_size=100 一次拉全，回复越多 parseAttachments 的线性放大越明显（PR-17 的数据面）。 |
| PR-40 | frontend-user-v4-console/src/composables/useDeviceLayout.ts:20-27；调用方 Header.vue:191、SideNav.vue:91 | 性能·渲染 | 低 | 每次调用 useDeviceLayout 都新建一套 useBreakpoints（mobile/tablet/wide 三断点）→ 当前 2 处未超标，但每加一个页面就多一整套 matchMedia 监听。 |
| PR-41 | frontend-user-v4-console/src/composables/useMediaQuery.ts:4；recharge/index.vue:5 ↔ W（充值页 767.68px 项） | 性能·渲染 | 低 | matches 初值 false、onMounted 才建 mql 并赋值 → isMobileScreen 首帧为 false，v-if 先渲染桌面分支再翻转，产生一次结构重建与一次 QrcodeVue（defineAsyncComponent，:272）实例化。 |
| PR-42 | frontend-user-v4-console/src/utils/request.ts:27-40、:42-71 | 性能·渲染 | — | **正例**（已缓解）：showError 内置 1s 同文案去重、redirectLogin 三态防重入，并发 401/报错风暴已被抑制，无需改动。 |
| PR-43 | useVerification.ts:269、:285、:403；useInvoices.ts:353、:683；useRecharge.ts:293、:421；useInvoiceDetailHelpers.ts:191、:219 | 性能·渲染 | — | **正例**：上述定时器均有对应 clear 与 onBeforeUnmount 清理，startSessionCountdown 还有 :190 的幂等保护（if (timer !== null) return）。真正的风险只来自 PR-01 使 onBeforeUnmount 不触发。 |
| PR-44 | frontend-user-v4-console/src/domains/finance/useRecords.ts:295、:300、:330-337、:409-418 | 性能·渲染 | — | **正例**：list 用 shallowRef、loadList 用 requestSeq 丢弃过期响应；「点筛选 → loadList → router.replace → watch(route.query) 判同直接 return」不产生重复请求。**可作 useServiceConsole / useCoupons 的对标模板**。 |
| PR-45 | frontend-user-v4-console/src/domains/marketing/useCoupons.ts:153-160、:174 | 性能·渲染 | 低 | switchTab 仅在 list 为空时加载（正例）、claimCoupon 后并发两条 loadList 合理；但全文件无 requestSeq，快速切 tab + 改分页时旧响应可能覆盖新列表。 |
| PR-46 | frontend-user-v4-console/src/domains/services/useServiceCenter.ts:496-515、:508 | 性能·渲染 | 低 | onMounted 内 async 加载、:508 另起 watch，未声明 onUnmounted；页面无 setInterval/addEventListener 且 viewMode 走条件渲染（services/index.vue:68、:180），风险限于 PR-01 常驻下的状态堆积（待确认）。 |

### 2.17 交叉验证与去重说明

为保证「每条都可定位」且不重复计数，本节说明三处容易误报或重叠的处理口径：

1. **性能条目与页面条目同源**：PB-07↔SC-08、PR-01↔L-10、PR-02↔SC-14、PR-04↔SC-15、PR-05↔SC-04、PR-06/07↔SC-05、PR-08↔S-07、PR-09↔D-06、PR-10↔D-05、PR-11↔C-18、PR-12↔S-10、PR-14↔N-10、PR-17↔T-13、PR-23↔L-06、PR-25↔L-12、PR-26↔L-29、PR-27↔AC-23、PR-30↔SC-19、PR-41↔钱包充值页断点项。这些是同一缺陷在两个维度视角下的记录，**统计去重**，见第四章。
2. **已撤销的初期误判**（不可作为缺陷引用）：
   - 「--td-comp-size-xxxxl / xxxxxl 令牌不存在」——主进程实测 dist CSS 中 TDesign 自带定义 `--td-comp-size-xxxxl: var(--td-size-15)`、`--td-comp-size-xxxxxl: var(--td-size-16)`，两者均为合法令牌；真实问题是同语义高度混用 xxxl / xxxxl / xxxxxl 三量级（见 C-15）。
   - 「v-for 缺 :key」——159 个 .vue/.ts 全量扫描为 0 处；抽查 LayoutContent.vue:15-16、ContentListPage.vue:12-13、Header.vue:55-56 均有 :key。
   - 「品牌名待确认」——src/store/modules/site-branding.ts:9 `DEFAULT_SITE_NAME = import.meta.env.VITE_APP_TITLE || '创欧云'`，「创欧云」为项目既定值（另见 AuthShell.vue:81、Header.vue:194、checkout-resume/index.vue:86、store/modules/user.ts:18）。
   - 「theme.css:737-742 的 padding:0 可能不生效」——已按类名与 DOM 层级实测：LayoutContent.vue:75 生成 class="tdesign-starter-content-layout"，与 theme.css:737-739 选择器链一致，对 client- 前缀页与 .record-page 生效；**不生效的是另外 10 个非前缀页面**（见 C-01）。
3. **node_modules 未安装**：所有依赖 TDesign 运行时事件语义的结论（分页双 emit、t-button loading 是否阻断 click、t-textarea 的 enter 事件、t-select clearable 清空值类型、t-form-item 是否输出 aria-required、t-tabs 是否惰性挂载）一律标注「待确认」，不作最终定性。

## 三、优化修复建议（按投入产出比排序）

排序原则：**先修「会造成错误结果 / 数据错乱 / 崩溃」的低成本项，再修「系统性体力活」，最后做「需要重构的大工程」**。每一档内部按「改动行数 ÷ 收益面」从高到低排列。投入用 S（≤半小时，改 1–10 行）、M（半天，改 1–2 个文件）、L（1–3 天，跨文件重构）、XL（≥3 天，架构级）标注。

### 3.1 第一档：立即修（S 投入，正确性/资金/崩溃级收益）

| # | 对应问题 | 修复方向 | 投入 | 收益 |
| --- | --- | --- | --- | --- |
| 1 | L-01 | `nextRouter` 增加兜底：`const nextRouter = tabRouters[options.index+1] \|\| tabRouters[options.index-1]; if (!nextRouter) { router.push('/client/dashboard'); return; }` | S | 消除关闭最后一个标签时的 TypeError 崩溃 |
| 2 | L-02 | `handleRemove` 内改用 store 原始数组下标：先 `const storeIdx = tabsRouterStore.tabRouters.findIndex(r => r.path === path)` 再传 `routeIdx: storeIdx` | S | 消除误删相邻标签（当前关闭 A 会删 B） |
| 3 | AC-19 / AC-21 | 把 `profileLoading` 拆为按动作的 `savingProfile / changingPassword / sendingCode` 独立标志；密码弹窗提交中禁用关闭（`closeOnOverlayClick=false` + `before-close` 检查）或成功后不再强退 | S | 消除「本来只想改手机号，全页按钮变灰」与「关窗后被踢下线」 |
| 4 | AC-22 | `loadProfile` 内 `userStore.getUserInfo()` 包 try/catch，onMounted 的 Promise.all 加 catch → 置 error 态并显示重试 | S | 消除空白表单无提示 |
| 5 | L-19 | `await markAllRead()` 后再提示成功；`useInbox.ts:67-75` 把 catch 由静默改为 throw/回滚 | S | 消除「失败也提示已全部已读」的假成功 |
| 6 | L-18 | `await markRead(item)` 后再关面板与 push；`item.link` 为空时提示「该消息无跳转」 | S | 消除已读状态与界面不一致 |
| 7 | T-08 | `nth-child(3)` 改 `nth-child(2)`（当前只有 chat/detail 两个 panel） | S | 恢复移动端「详情」视图 |
| 8 | T-09 | 删除 `display:none`，改为单行截断 + 返回按钮内显示工单号 | S | 恢复移动端页面标题 |
| 9 | SC-01 | 按 `detail.actions` 的真实字段渲染（power_on / power_off / reboot 三态按钮），中间态显示 disabled + 进行中文案；无 action 字段时按钮禁用而非显示「开机」 | S | 消除「正在重启却显示开机」的误操作入口 |
| 10 | SC-03 | 「应用」安全组补 `DialogPlugin.confirm`（与删除组/删除规则一致） | S | 危险写操作补二次确认 |
| 11 | SC-25 | 抽 `useConsoleDialog` 统一 `width`（建议 32rem）+ 表单形态 + 页脚；`NatForwardingDialog` 补 width、改 `onConfirm`、`label-width="96px"` | S | 消除 9 个弹窗四种宽度 |
| 12 | L-22 | `Header.vue:59、:78` 的手写 role=button 补 `aria-label`（或直接换 t-button） | S | 屏幕阅读器可读 |
| 13 | C-11 | 三张认证页提交按钮补 `:disabled="loading"`；`@submit` 内加 `if (loading) return` | S | 消除请求中回车重复提交 |
| 14 | W（充值页 767.68px） | `recharge/index.vue:276` 的 `useMediaQuery('(max-width: 47.98rem)')` 改为复用 `useDeviceLayout().isMobile` | S | 消除 767.69–767.98px 区间形态错配 |
| 15 | PR-41 | `useMediaQuery` 的 `matches` 初值改为 `window.matchMedia(query).matches` | S | 消除首帧分支翻转与 QrcodeVue 重复实例化 |
| 16 | PR-36 | `avatarUrl` 接 `userInfo.avatar`（或删 computed 与 :image 绑定） | S | 修复头像永远为空 |
| 17 | PR-37 | 引入 `now` ref + 1min tick 作为 greetingText / todayDateText / currentYearLabel 的依赖 | S | 跨天不刷新 |
| 18 | T-14 | 工单详情不再输出 `user_id`（或仅超管端保留） | S | 内部标识外露 |
| 19 | PB-05 | `vite.config.ts:465` 删除 `'vue-i18n'`；`'tdesign-icons-vue-next'` 改按需或移出 include | S | dev 预打包省 189 KB + deps 目录显著瘦身 |
| 20 | PB-17 / PB-15 / PB-16 | 删除 `vite-svg-loader`（或落地真实 svg 导入）、删 `src/utils/charts.ts`、`src/utils/date.ts` 与 package.json 的 dayjs 直接依赖 | S | 去 3 个空转依赖 |
| 21 | PB-13 | `layout.less:1` 删除对 font-family.less 的 @import（只由 index.less 引入） | S | 首屏 CSS 省 4,128 B 且去重复声明 |
| 22 | PB-09 / PB-14 | 关闭 TDesign 默认主题变量全量注入并删 dark 块；precompress 阈值 1024 → 4096、扩展名只留 js/css/html/svg | S | 首屏 CSS 省约 16 KB；压缩副产物省约 30% |
| 23 | PB-21 | favicon 换 32×32 ico + 现代 png/svg | S | 首访省约 15 KB |
| 24 | C-08 | `recharge/index.less:173-177` 删除 `outline:0`，改为 `:focus-visible` 自定义描边（沿用 reset.less 的令牌） | S | 恢复键盘焦点可见性 |
| 25 | N-08 / E-05 / P-12 / AC-17 / C-19（死规则项） | 删除已确认的死规则/死样式/死组件：ContentListPage.vue:344-349、404/index.vue:56-61 的重复 weight、referral:338-342、profile 的 .agent-list、SC-26 的两份 IP 弹窗 | S | 降低误导成本 |

### 3.2 第二档：低成本高收益（M 投入）

| # | 对应问题 | 修复方向 | 投入 | 收益 |
| --- | --- | --- | --- | --- |
| 26 | C-12 | 取消订单/充值下单改为「打开确认框前先 `cancelingIds.add(id)` / `submitting.value = true`，onConfirm 内 `setConfirmLoading(true)`，finally 释放」；移动端 `await handleCreateOrder()` 后再 `dialog.destroy()` | M | 消除连点并发下单/取消（资金风险） |
| 27 | I-01 | `useInvoices.ts:565-585` 在「选支付宝 + 余额足额」分支前插入确认框，按钮文案区分「余额支付」与「支付宝支付」 | M | 消除资金预期错位 |
| 28 | W-04（充值移动支付方式） | 移动分支补 `:disabled/:loading`，下单中禁止切换渠道；`useRecharge.ts:460-464` 的 `clearPaymentPayload` 只在真正切换并取消旧单后调用 | M | 消除二维码与订单脱钩 |
| 29 | 6（checkout-resume retrying） | `retrying` 在 `resumeCheckout` 入口置 true、finally 置 false；补重入守卫 | M | 恢复重试按钮反馈 |
| 30 | 7（invoice-detail 支付卡） | `v-if="showPayActions"` 与 `hasPayMethods` 分离：先渲染卡片，卡内 `v-if="!hasPayMethods"` 显示「暂无可用支付方式」 | M | 消除无解释死界面 |
| 31 | SC-02 / S-09 | `loadRenewPreview` 拆为「首次加载用服务端默认值」与「用户变更后只刷新价格」两条路径：给 `loadRenewPreview(opts)` 加 `applyDefaults:boolean`，@change 时传 false，只回写价格/折扣字段 | M | 恢复周期与优惠券可改 |
| 32 | D-01 | 六个分区各自持有 `ready` 状态：改为 `results[i].status === 'fulfilled' ? ready=true : ready=false`，失败分区显示分区级错误 + 重试 | M | 消除「0 元/0 条」假数据 |
| 33 | N-02 | `handleMarkAllRead` 包 try/catch，失败显示 toast + 保持按钮可用 | M | 消除静默失败 |
| 34 | T-19 / C-10 | `useTickets`、`invoice-detail-view`、`payment-detail` 照抄 `useRecords.ts:295/:300/:330-337` 的 `shallowRef + requestSeq` | M | 消除详情页旧响应覆盖新数据 |
| 35 | P-16 | 提现前端补最小提现额与余额上限校验（阈值从后端配置接口取） | M | 消除必然失败的前端提交 |
| 36 | SC-28 | `isExpiringSoon` 加 `expiresAt > now` 前置条件；已过期走「已过期」文案 | M | 消除过期服务显示「将于…到期」 |
| 37 | SC-20 | `can_delete === false` 时按钮 `disabled` + tooltip 说明原因 | M | 消除静默无效点击 |
| 38 | SC-18 | 「购买流量包」的 loading 与弹窗加载分离；弹窗打开期间主页按钮恢复常态 | M | 消除误以为按钮在忙 |
| 39 | AC-06 / AC-07 / AC-08 | 二维码刷新与重新认证拆两个 loading；二维码态保留「取消并重新开始」明确文案；倒计时归零补 toast | M | 消除两个操作互相干扰 |
| 40 | AC-20 | 五个「发送验证码」按钮补 `:loading` | M | 补进行中反馈 |
| 41 | C-09 | `role="link"` 卡片统一抽 `useRecordCardNav`：绑 `@keydown.enter.self` + `@keydown.space.self.prevent`，内嵌按钮加 `.stop`；`payments/index.vue:94-98` 的 a 补 href 或改 button | M | 恢复键盘可达性 |
| 42 | C-06 | 统一断点常量：把 8 处误用的 `@screen-sm-max` 改为 `@screen-mobile-max`（或反之，按实际形态意图逐个确认），JS 侧统一走 `useDeviceLayout` | M | 消除平板/手机形态判断分裂 |
| 43 | C-01 | 10 个非 client- 前缀页面的根类统一加 `client-` 前缀（或在 theme.css 补等价选择器） | M | 一次性修 10 个页面的移动双重内边距 |
| 44 | C-07 | 在 theme.css 增加 `button:not(.t-button)` 的最小触控基线（或抽 `useTouchTarget` 混入），逐项补齐 6 处过小热区 | M | 触控可用性 |
| 45 | P-01 | `.coupon-item__detail` 改为整卡可点（`@click` 挂卡片 + 内嵌按钮 `.stop`），热区从约 13px 提升到整卡 | M | 消除「点卡片没反应」 |
| 46 | P-02 / N-09 / T-01 / AC-12 | 详见 #43（同批处理），另：`coupons/index.vue:576-583`、`ContentDetailPage.vue:276-279`、`tickets/index.vue:421-428` 删除页面自加 padding | M | 消除双重内边距 |
| 47 | D-03 | `.bar-chart__value` 取消 `opacity:0` 的 hover-only 逻辑，改为常显或图例汇总；触屏必须有值可达 | M | 恢复触屏可读金额 |
| 48 | D-02 / SC-10 / AC-02 | 三处「色板当语义」统一改：状态徽标改浅底 + 深色文字（`--td-*-color-1` 背景 + `--td-*-color-7` 文字）；正文类禁用 `--td-warning-color`；财务金额改中性正文色 + 额外「支出」标识 | M | 对比度达 AA，语义不串 |
| 49 | S-01 | 「开通中」改用 brand 色系（`--td-brand-color`），错误色只留给失败 | M | 状态可辨 |
| 50 | SC-09 | `.console-sidebar-item` 的 hover 与 is-active 区分（active 用 brand-light 底 + brand 文字 + 左侧条），security-group-card 同改 | S | 恢复当前 tab 可辨识 |
| 51 | S-03 | ≤767.98px 不隐藏筛选与视图切换，改为折叠进「筛选」抽屉 | M | 恢复移动端可用操作 |
| 52 | T-01 / T-02 | 上传区改为 t-upload（去掉 button 内嵌 button），删除按钮移出热区 | M | 消除非法嵌套与误删 |
| 53 | N-10 / PR-14 | TOC 构建移到 markdown AST 阶段（渲染时顺带收集标题），不再 `querySelectorAll` 回读 DOM；正文按 h2 分块分批挂载 | L | 长文首帧卡顿显著下降 |
| 54 | PR-02 / PR-04 / SC-14 / SC-15 | 监控图表：`loadMonitor` 按 range 做等分降采样（上限 120 点）；`buildMonitorChartView` 改在数据落地时一次性构建 + `shallowRef`；tip 数据改单次 computed；`@mousemove` 加 40–60ms 节流 + 二分查找最近点 | L | 消除悬停卡顿与重算风暴 |
| 55 | PR-03 | `Math.min/max(...values)` 全部改 for 循环或 reduce | S | 消除栈溢出白屏 |
| 56 | PR-05 / SC-04 | VNC 面板改 `v-show` 常驻（iframe 提到 tab 容器外），或在 `onBeforeUnmount` 主动清理并提示「连接已断开」 | M | 消除切 tab 掉线 |
| 57 | PR-06 / PR-07 | `fetchConnectionInfo` 不再整体替换 detail；删掉 entry 的重复 detail 请求（用列表页已拿到的 product_type 分发）；remote status 走独立 ref | M | 打开一次控制台从 ≥3 次降到 1 次 |
| 58 | PR-08 / S-07 | `watch(activeTab)` 改 `Promise.all` 并发 + 200ms 防抖 + AbortController；三个 t-select 加 debounce | M | 快速切 tab 从 10+ 请求降到 5 个以内 |
| 59 | PR-09 / D-06 | 图表改后端聚合接口；无法改后端时改并发翻页 + AbortController + 5–10 分钟 sessionStorage 缓存；`invoicesLoaded/balanceLogsLoaded` 分开控制 | L | dashboard 进入成本从 11–20 次 HTTP 降到 2–3 次 |
| 60 | PR-10 / D-05 | 把 referralOverview、couponsSummary 下沉到 `requestIdleCallback` 后再拉 | S | 首屏少 2 个并发、少挤占连接 |
| 61 | PR-11 / C-18 | 财务三页改 `useDeviceLayout().isMobile` 的 v-if 二选一（对齐 recharge/index.vue:5/:136 的正例） | M | 每页少渲染一半 DOM，模板函数调用减半 |
| 62 | PR-12 / PR-13 / S-10 | services 列表建 `listViewModels = computed`，模板只读 vm 字段（含 actionOptions 预算） | M | 每卡约 12 次调用降到 0 次计算 |
| 63 | PR-18 / PR-19 / PR-20 / PR-21 / PR-22 | 财务/充值/返利页同理：行视图模型 computed + gatewayView 预算 `paymentOptionKey` | M | 单文件最高 11 次重复调用归零 |
| 64 | PR-27 / AC-23 | 五个 1s 定时器合并为一个 tick：`now` ref 每秒 ++，各倒计时改 `computed(() => deadline - now)` | M | 每秒 5 次响应式更新降到 1 次 |
| 65 | PR-01 / L-10 | keep-alive 改白名单（`meta.cache` 显式开启）；`tabs-router.ts:38-44` 加数量上限（20）并同步淘汰；轮询页在 `onDeactivated` 停表 | L | 全仓最系统性风险；配合 #64 一并解决 |

### 3.3 第三档：体系化治理（L/XL 投入，需排期）

| # | 对应问题 | 修复方向 | 投入 | 收益 |
| --- | --- | --- | --- | --- |
| 66 | C-02 / C-03 / C-15 / C-21 / C-24 | 在 theme.css 增补 `--app-card-shadow` / `--app-border-hairline` / `--app-touch-min` 等语义令牌，并出一份「卡片/边线/触控/圆角」四张映射表；把 26 处自绘卡片与 59 处 `:bordered="false"` 逐批替换 | XL | 令牌体系真正落地（docs/DESIGN.md 的核心要求） |
| 67 | C-13 / C-14 / C-17 / C-19 | 抽 `PageHeader.vue`（采纳 theme.css:440-483 已提供的三件套）、`EmptyState.vue`（统一 2.16 节的所有空态）、`ConsoleDialog` 契约；删除死 CSS 与死样式 | L | 消除「每页一套头部/空态/弹窗」 |
| 68 | C-04 / C-05 / AC-05 / SC-16 | 统一三态与加载语言：`DataState` 扩展为 loading 支持 skeleton / spinner 两种，铺到 verification、compute、nat 等缺项页面 | L | 满足 FRONTEND.md:119 与 DESIGN.md:202 |
| 69 | SC-07 | 抽 `ConsoleWorkbench.vue`，compute 与 nat 只保留 tab 集合差异 | L | 消除逐行同构的双维护（已实际出现只改一处） |
| 70 | PB-01 / PB-02 / PB-03 | `manualChunks` 把 tdesign 按「首屏必需」与「页面级」分桶；`src/api/client.ts` 拆 core + 按域子模块（core 静态引，其余 `import()`）；`RecordQuickFilters` 的 date-picker 改异步 | XL | 入口 435 KB 与边际 55–69 万 B 都会显著下降（静态测算：入口可降至约 260–300 KB） |
| 71 | PB-04 / PB-07 | `RecordQuickFilters` 的日期选择器改 `defineAsyncComponent` + 用户点击时加载；compute/nat 的 `<style src>` 改为在 `<script setup>` 内 import | M | 消 130 KB 共享桶 + 105 KB 样式 JS chunk |
| 72 | PB-08 / PB-06 | 首屏 tdesign 样式按需（只引 button 需要的 variant）；给 tdesign-icons 单列 vendor 桶 | L | 首屏 CSS 可再降 2–3 万 B；消 5 个单图标碎片 |
| 73 | PB-10 / PB-12 | `public/vnc` 剥离到独立静态目录（后端已按 URL 取）；删除 `src/assets` 19 个零引用文件（1.86 MB） | M | 发布体积瘦 1.86 MB + 614 KB |
| 74 | PB-11 | 门禁增两类预算：单路由边际体积、入口 chunk 占 js 总量比例；现有预算下调到实测 1.2 倍内 | M | 让退化可被发现 |
| 75 | PB-18 / PB-19 / PB-20 / PB-22 | `build.target` 提到 `es2020`（需确认浏览器支持清单）；<1 KB 碎片并入 `vendor-shared`；同源也输出 preconnect/dns-prefetch | M | 体积与首屏 RTT 双优化 |
| 76 | PR-16 / PR-17 / PR-23 / PR-24 / PR-25 / PR-26 / PR-28 至 PR-35 | 逐条实施：InfoCell 换 ResizeObserver、parseAttachments 预解析、tab key 去掉 index、popup-props 记忆化、querySelector 判空、主题令牌移到调用时读取、单一 tooltip 实例、请求序号全覆盖、geetest 监听显式移除 | L | 中危性能债清空 |
| 77 | PR-29 / PR-30 / PR-31 / PR-34 | 控制台 tab 生命周期与 `loadedTabs` 解耦；rules 表格与卡片二选一；流量包报价懒加载 + requestSeq；分页控件统一 | M | 控制台内部一致性 |
| 78 | 2.9 T-15 / T-16 / SC-29 / SC-30 / C-23 | 魔法数与令牌混用逐条归位（`calc(100vh - 20rem)` 改令牌组合、`--td-comp-size-xxxl/xxxxl/xxxxxl` 统一为一档、裸 px 改令牌） | M | 视觉一致性收口 |
| 79 | D-12 / PR-36 | dashboard 的 `resolveProductMeta` 改为按产品类型/产品码映射，不再用标题中文 includes | M | 消除业务耦合与图标错配 |

### 3.4 建议的整体推进顺序

1. **第 1 周**：3.1 全档（25 项，多为 1–10 行）→ 先把崩溃、资金风险、假成功、可访问性硬伤清掉。这一档**不改架构、不动接口**，可以按页面并行分派。
2. **第 2 周**：3.2 的 #26–#52（资金与状态类 + 视觉语义类）→ 这批用户能直接感知。
3. **第 3–4 周**：3.2 的 #53–#65（渲染性能）→ 与第 4 步的 keep-alive 改造绑定排期。
4. **第 5 周起**：3.3 的 #66–#69（体系化组件）、#70–#75（构建体积）。
5. **#76–#79** 作为随改随清的长期清单，纳入技术债看板滚动处理。

---

## 四、汇总摘要

### 4.1 整体质量评价

**结论：基础架构正确，细节纪律失守。**

这个项目的「骨架」是合格的：路由按业务域分组清晰（7 组菜单分组 + 1 组首页分组、32 个具名路由中 14 条隐藏）、状态按端独立、TDesign 按需引入确已生效（123 条映射、48 个 t-* 全命中、零整包引入、跨 chunk 零重复）、`docs/FRONTEND.md` 要求的 `shared/user-v3` 三态组件与 `requestSeq` 竞态防护在 `useRecords.ts` 里有正确样板、`utils/request.ts` 的 401 防重入与报错去重做得很扎实、159 个文件的 `v-for` 缺 key 为 0 处、`watch deep` 为 0 处、定时器与监听绝大多数有清理。这些都是「有人认真做过」的证据。

但「最后一公里」的系统性失守同样明显，可以归为四条主线：

1. **交互契约不统一**：同一件事有 2–4 种实现 —— 空态两套（DataState 与自写 `.detail-empty`）、加载四种（LoadingState / skeleton / t-loading text / 裸 t-loading）、弹窗 9 个四种宽度、卡片 26 处自绘、1px 两套写法、断点两套语义、支付方式选择器三套、金额单元格四份复制。这不是「个别页面偷懒」，而是**缺少一份可执行的前端契约 + 缺少 lint/评审门禁**。
2. **状态机不严谨**：中间态被压成二态（电源按钮把 starting/stopping/rebooting 全归为「开机」）、失败被压成成功（dashboard 六路 allSettled 后无条件 ready、`markAllRead` 未 await 就报成功、`useInbox` 失败静默）、必填状态被压成可选（充值下单不占位、确认框无 setConfirmLoading）、请求被压成无序号（三个详情页无 requestSeq）。**这批问题直接对应资金与数据可信度**。
3. **性能生命周期失控**：keep-alive 默认全量常驻 + tab 无上限，把「每个页面都可能长期活着」变成事实，于是所有正确的清理逻辑都失效；叠加上 6 路并发首屏、11–20 次串行翻页、桌面/移动双渲染、模板里每帧 11 次函数调用，性能问题不是「某处写法不好」，而是**缺少取消层 + 缺少视图模型层**。
4. **令牌体系名存实亡**：theme.css 提供了完整令牌，但 `AuthShell.vue` 的间距令牌数是 **0**、`invoice-detail/index.less` 裸圆角 24 处、`service-console/components/styles.less` 文件头自称「禁止 !important」实测 8 处、theme.css 提供的 page-header 三件套在两个用户端命中 0。**规范写了但没被执行，也没有机制保证执行**。

从维度分布看：**交互（117 条）与视觉（110 条）是问题最密集的两层，设计（45 条）集中在「结构重复与层级缺失」，性能（90 条 = 构建 28 + 渲染 62）则高度集中在少数几个根因上** —— 性能的 90 条里有 25 条高严重（构建 3 + 渲染 22），其中 8 条可归因到 keep-alive、取消层、视图模型三个根因。

严重程度分布（去重后）：**高 80 条、中 199 条、低 64 条，另有待确认 9 条、正例/基线与已核实无问题 10 条**（原始 8 份报告合计 427 行，维度级高 61 / 中 197 / 低 169）。高严重里有 12 条属于「会导致错误操作、错误数据或崩溃」，必须在下一迭代内清掉（见 4.2）。

### 4.2 高优先级问题 Top 12（按风险 × 修复成本排序）

| 排名 | 问题 | 编号 | 风险类别 | 为什么必须先修 |
| --- | --- | --- | --- | --- |
| 1 | 关闭最后一个标签时读 `nextRouter.path` 崩溃 | L-01 | 可用性崩溃 | 用户正常操作即触发 TypeError，白屏且无法自恢复；修复仅需 3 行 |
| 2 | 过滤后下标传给原始数组，误删相邻标签 | L-02 | 数据/状态错乱 | 关 A 删 B，且被删标签的页面状态一并丢失；修复仅需 5 行 |
| 3 | keep-alive 全量常驻 + tab 无上限 | PR-01 / L-10 | 内存与运行时 | 全仓最系统性风险：让所有正确的清理逻辑失效，是所有定时器/监听类问题的放大器 |
| 4 | 取消订单 / 充值下单无防重入 | C-12 | **资金** | 连点即并发下单，可能产生多张充值单与重复扣款 |
| 5 | 选支付宝 + 余额足额时静默全额扣余额、无二次确认 | I-01 | **资金** | 用户以为走支付宝，实际全额扣余额，资金预期完全错位 |
| 6 | 电源按钮把中间态显示为「开机」且可再次下发 | SC-01 | **误操作** | 对正在重启的实例下发开机，可能造成实例状态异常 |
| 7 | dashboard 六路 allSettled 后无条件 ready | D-01 | 数据可信度 | 接口失败显示「0 元/0 条」，用户以为是真实余额与账单 |
| 8 | 认证三页提交按钮无 :disabled，@submit 不检查 loading | C-11 | 资金/安全 | 回车可重复提交登录/注册/找回密码请求 |
| 9 | 续费周期与优惠券选择被服务端默认值覆盖 | SC-02 / S-09 | **资金** | 用户改不动周期与券，且自己不知道被改回去了 |
| 10 | 服务端写操作失败仍提示成功（inbox / 安全组 / 自动续费） | L-19 / SC-27 | 数据可信度 | 「已全部标记已读」「已开启自动续费」都是假的，用户不会复查 |
| 11 | `profileLoading` 单一标志 + 关窗后仍强退 | AC-19 / AC-21 | 可用性 | 改手机号会禁用全页按钮；关掉密码弹窗仍被踢下线 |
| 12 | 移动端工单「详情」视图因 `nth-child(3)` 永不匹配而失效 | T-08 | 功能缺失 | 手机上工单详情不可达，且没有任何报错提示 |

> 说明：第 4、5、9 三条涉及资金语义，**建议在修代码前先与后端确认接口幂等性**（充值建单、取消订单、余额扣减是否已有服务端幂等键）。若服务端已有幂等，前端修复优先级可降为 P1；若没有，必须前后端同批上线。

### 4.3 建议的处理顺序（按四个 Sprint）

| Sprint | 主题 | 覆盖编号 | 交付判据 |
| --- | --- | --- | --- |
| **S1（1 周）** | 止血：崩溃 / 资金 / 假成功 | 3.1 全档 25 项 + Top12 的 #1/#2/#4/#6/#8/#10/#12 | 关闭标签不崩、关 A 不删 B、连点不产生多单、电源中间态不误操作、回车不重复提交、写操作失败有提示、移动端工单详情可达 |
| **S2（2 周）** | 状态机与语义 | 3.2 的 #26–#52（资金与状态类 + 视觉语义类） | 余额/支付宝支付有确认、充值渠道切换不脱钩、六分区独立失败态、续费可改、提现有前端校验、状态色与正文色不再串语义、10 个页面移动双重内边距消失 |
| **S3（2 周）** | 渲染性能 | 3.2 的 #53–#65 + 3.3 的 #76–#77 | 悬停监控不卡、VNC 不掉线、控制台打开 1 次请求、dashboard 2–3 次请求、财务三页单套模板、行视图模型落地、5 个定时器合 1 个、keep-alive 白名单 + tab 上限 |
| **S4（3 周）** | 体系与体积 | 3.3 的 #66–#75 + #78–#79 | 卡片/边线/触控/圆角四张映射表落地、PageHeader/EmptyState/ConsoleDialog 三个公共件上线、ConsoleWorkbench 抽离、入口 chunk 降至 300 KB 内、边际路由降至 40 万 B 内、门禁能拦住退化 |

**验收口径建议**：每个 Sprint 结束时跑一次 `npm run build` 记录 dist 总量与首屏 gzip，与 2.15 节的基线（dist 4,431,412 B / 首屏 gzip 207,059 B / 入口 js 435,665 B）对比；同时按 AGENTS.md 的要求，前端变更追加对应 e2e 测试，重构追加 `npm run verify:refactor`。

### 4.4 审查方法说明与局限

- **覆盖方式**：8 位专家按「设计 / 视觉 / 交互 / 性能」四维度、按「交易钱包+认证」/「服务内容账户布局」两半区并行，逐文件逐行只读审查；主进程对 17 条关键结论做了独立实读复核（13 条成立、4 条撤销或部分撤销，见附录 B），并对报告引用的 116 个路径逐个做了存在性校验（零缺失）。
- **覆盖完整性**：41 个路由节点（30 个挂页面组件、1 个布局壳、10 个纯重定向，其中 32 条为具名路由且 14 条 `hidden: true`）、55 个 `.vue` 页面、`src/layouts/**`、`src/style/*.less`、`theme.css`、`shared/user-v3` 依赖组件、`vite.config.ts`、`dist` 产物均在覆盖表（1.2.1、1.2.2 节）中逐项列出，未抽样。
- **主要局限**：
  1. `node_modules` 未安装，所有依赖 TDesign 运行时事件语义的结论标为「待确认」，未做运行时验证；
  2. 未启动 dev server，无真实浏览器截图与 Lighthouse 数据，视觉结论基于 CSS 值与 DOM 结构静态推导（对比度按 WCAG 公式手算）；
  3. 未做优化后 A/B 构建，体积收益为静态测算；
  4. 未读后端实现，凡涉及接口语义（`payments.type`、`can_pay` 与 `payment_window_expired` 判据是否同源、充值建单幂等性）均标「待确认」。

---

## 五、待确认清单（需运行时或业务侧确认，不作最终定性）

| # | 待确认项 | 位置 | 确认方式 |
| --- | --- | --- | --- |
| 1 | `t-pagination` 改页大小时是否同时 emit `change`（4 处双绑） | services/index.vue:266-276、tickets/index.vue:79-89、coupons/index.vue:74-84 与 :137-147 | 装依赖后读 TDesign pagination 源码或运行时打点 |
| 2 | `t-button :loading` 是否阻断 click | 全站 179 处 t-button | 同上 |
| 3 | `t-textarea` 是否 emit `enter`（工单回车发送） | ticket-detail/index.vue:194 | 同上 |
| 4 | `t-select clearable` 清空后值的类型（''/null/undefined） | services、records 等筛选区 | 同上 |
| 5 | `t-form-item` 是否输出 `aria-required` / `aria-invalid` | 认证三页 + profile + referral | 同上 |
| 6 | `t-tabs` 面板是否惰性挂载（影响 referral 4 张表首屏负载） | referral/index.vue:60/:81/:114/:137 | 运行时观察 onMounted 触发时机 |
| 7 | keep-alive 实际缓存命中率（两份注释结论互斥） | Content.vue:5-14 vs useInvoices.ts:681-682 | DevTools 观察 onActivated 是否触发 |
| 8 | 监控各区间（3h/1d/7d/30d）的真实点数（决定 spread 栈溢出阈值） | useConsoleMonitor.ts:143-153 | 抓一次后端响应统计点数 |
| 9 | `payments.type` 字段语义（是否等同支付渠道） | payments/index.vue:19-21 绑 `PAYMENT_GATEWAY_OPTIONS` | 读 backend 接口定义 |
| 10 | `can_pay` 与 `payment_window_expired` 是否同源判据 | invoice-detail-view/index.vue:208-215 vs useInvoices.ts:115-117 | 读后端账单状态机 |
| 11 | 充值建单 / 取消订单 / 余额扣减是否有服务端幂等键 | useRecharge.ts、useOrders.ts、useInvoices.ts | 读 backend Service 层；决定 Top12 第 4、5 项的优先级 |
| 12 | 是否存在需支持的旧浏览器清单（决定 `build.target` 能否提到 es2020） | vite.config.ts:446 | 运维/产品确认 |
| 13 | `public/img/os` 4 个未引用 svg（3,019 B）是否由后端下发 | public/img/os | 读 backend 下发逻辑 |
| 14 | `t-icon` 默认图标库是否真触发跨域脚本请求 | Header.vue:19、LayoutContent.vue:32 等 8 处 | 网络面板抓包 |
| 15 | dashboard 顶部指标卡是否受 `docs/DESIGN.md` §5.2「禁止指标卡片」约束 | dashboard/index.vue:48-71 | 设计侧裁决（该条款列举的是记录页） |
| 16 | 公告/帮助列表页 Hero 是否受「禁止官网式大 Hero」约束 | ContentListPage.vue:5-31 | 设计侧裁决 |
| 17 | `catalog` 是否为过渡页（若是，应 hidden 或补功能） | catalog/index.vue、router/modules/client.ts:95-100 | 产品侧确认 |
| 18 | 平板（768–991px）是否应有表格视图 | record-page.less:303-311、record-page.less:273 | 产品侧确认 |
| 19 | 页内面包屑与布局层面包屑是否设计允许双存在 | ContentDetailPage.vue:3-9、ConsoleBreadcrumb.vue:2-8、config/style.ts:1-14 | 设计侧确认 |
| 20 | `invoice-detail` 的 sticky 汇总列在实际滚动中是否遮挡内容 | invoice-detail/index.less:151-171 | 浏览器实测 |
| 21 | `dashboard` 的 `available_coupons` 字段语义（数量还是列表） | dashboard/index.vue:783-785 | 读后端响应样例 |

---

## 附录 A · 专家报告索引

| 角色 | 维度 | 范围 | 条数（高/中/低） | 原始文件 |
| --- | --- | --- | --- | --- |
| ux-architect-a | 设计 | 交易钱包域 + 认证域 | 见 1.1 | `review-ux-architect-a.md` |
| ux-architect-b | 设计 | 服务/内容/账户/布局/异常页 | 见 1.1 | `review-ux-architect-b.md` |
| ui-designer-a | 视觉 | 交易钱包域 + 认证域 | 见 1.1 | `review-ui-designer-a.md` |
| ui-designer-b | 视觉 | 服务/内容/账户/布局/异常页 | 见 1.1 | `review-ui-designer-b.md` |
| persona-a | 交互 | 交易钱包域 + 认证域 | 见 1.1 | `review-persona-a.md` |
| persona-b | 交互 | 服务/内容/账户/布局/异常页 | 见 1.1 | `review-persona-b.md` |
| perf-build | 性能（构建与体积） | 全站 + dist 产物 | 26（3/8/15） | `review-perf-build.md` |
| perf-render | 性能（渲染与运行时） | 全站 159 个 .vue/.ts | 46（15/20/11） | `review-perf-render.md` |

> 8 位专家的原始报告存放于系统临时目录（`C:/Users/cerbo/AppData/Local/Temp/review-*.md`），不纳入仓库。本报告已合并其全部可定位结论，并对关键项做了主进程独立复核；撤销的初期误判见 2.17 节。

## 附录 B · 复核记录（本报告对专家结论的独立验证）

| 结论 | 复核文件:行 | 结果 |
| --- | --- | --- |
| PasswordToggle 键盘双触发 | src/components/auth/PasswordToggle.vue:1-11 | 成立（原生 button 的合成 click + 显式 keydown 双 emit） |
| 续费选择被覆盖 | useConsoleRenew.ts:40-80 | 成立 |
| keep-alive 与 tab 上限矛盾 | Content.vue:1-25、tabs-router.ts:38-44 | 成立 |
| nextRouter 崩溃 / 下标错位 | LayoutContent.vue:96-126 | 均成立 |
| 电源按钮中间态 | ConsoleHeader.vue:38-66 | 成立 |
| dashboard 分区假数据 | dashboard/index.vue:730-790 | 成立 |
| catalog 为死入口 | catalog/index.vue:1-20 + router:95-100 | 成立 |
| 工单移动端详情失效 | ticket-detail/index.vue:61-62、:552-560 | 成立（应为 nth-child(2)） |
| 充值断点 767.68px | recharge/index.vue:270-280 | 成立 |
| checkout-resume retrying 永不置 true | checkout-resume/index.vue:18、:52 | 成立 |
| 支付宝静默全额扣余额 | useInvoices.ts:565-585 | 成立 |
| 无支付方式时整卡不渲染 | invoice-detail/index.vue:216 + useInvoices.ts:237、:269 | 成立 |
| 取消订单无防重入 | useOrders.ts:82-110 | 成立 |
| `--td-comp-size-xxxxl/xxxxxl` 不存在 | theme.css vs dist CSS | **撤销**：两者均为 TDesign 合法令牌（td-size-15 / td-size-16），真实问题是三量级混用 |
| v-for 缺 key | 159 个文件全量扫描 | **撤销**：0 处 |
| 品牌名待确认 | site-branding.ts:9 等 5 处 | **撤销**：「创欧云」为项目既定值 |
| theme.css 移动归零不生效 | theme.css:737-742 + LayoutContent.vue:75 类名链 | **部分撤销**：对 client- 前缀页与 .record-page 生效；对另外 10 个非前缀页面不生效（见 C-01） |

---

*本报告为只读审查产物：审查过程未修改、回滚或格式化仓库中任何非本任务文件；未提交、未推送任何变更。报告状态 needs-review，待前端负责人复核后转入执行计划。*
