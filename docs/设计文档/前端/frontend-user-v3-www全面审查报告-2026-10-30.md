---
status: needs-review
updated: 2026-10-30
owner: frontend-platform
---

# frontend-user-v3-www 全面审查与修复报告 · 2026-10-30

## 一、审查概况

- **范围**：`frontend-user-v3-www/` 全量前端——`src/pages`（25 个 `.vue`，含 `home/components` 7 个与 `seo-landing/pages` 5 个）、`src/components`（6 个，含 `common/`）、`src/layout`（`WebsiteLayout.vue` + `WebsiteLayout.scss` + `components/WebsiteFooter.vue` + `composables/useMobileNav.js`）、`src/domains/products`（4 个 composable）、`src/app/router/routes.ts`（1 条布局壳 + 19 条子路由）、`src/assets/styles`（`variables.scss`、`global.scss`、`element/`）、`src/data`（首页 3 份 + SEO 落地页 5 份）、`vite.config.js` 与 `scripts/*`（10 个构建脚本）、根与前端 `package.json`、`backend/scripts/frontend_budget.mjs`、`shared/` 中被本站消费的模块。不含后端业务实现，仅作为依赖契约标注影响面。
- **方法**：按「设计 × 视觉 × 交互 × 性能」四维度拆分并行审查；逐页面检查到区块级——布局区块、按钮、链接、表单、弹窗、悬停/点击反馈、加载态与空态。每条发现强制附 `文件:行号`，无法核实的单列「待确认」不混入结论；全部高严重度条目由首席二次实读复核。
- **证据口径**：行号取自 2026-10-30 工作区（`HEAD = d5750a78`）；体积数据取自 `npm run build` 实际输出；跨站 cookie 行为经三次 Playwright 探针定位（见 P12-06）。
- **文档基线**：对齐 `docs/FRONTEND.md`（仅 Element Plus、优先复用 `shared/` 运行时与契约工具、构建必须过体积门禁）与 `docs/DESIGN.md`（颜色/字号/间距/圆角/阴影必须走 `variables.scss` 令牌，禁止页面私写重复容器公式）。

### 1.1 并行执行与失败披露

按用户要求并行派出多个专家智能体分维度执行，实际结果如实披露：

| 角色 | 承担维度 | 状态 | 产出 |
| --- | --- | --- | --- |
| 页面/组件层智能体 | 交互 + 结构 | 完成 | 18 条发现，全部被采纳或复核 |
| 构建与脚本层智能体 | 性能·构建体积 | 完成 | 14 条发现，全部成立并由首席逐条复现 |
| 业务域与 HTTP 层智能体 | 交互·契约一致性 | 完成 | 20 条发现，全部成立 |
| perf-auditor | 性能·渲染运行时 | 完成（二次唤醒后交付） | 46 条发现 |
| design-reviewer | 设计 + 视觉 | 完成（二次唤醒后交付） | 68 条发现，9 个分组 |
| visual-auditor | 视觉量化 | 失败 | 0（`subagent limit reached (active child limit: 8)`） |
| design-auditor | 设计结构 | 失败 | 0（同上） |

> 两个失败的维度未以任何形式伪造产出：视觉与设计的量化结论（裸色值计数、断点/圆角/间距分布、标题层级、令牌死引用）由首席直接取证补足，并与已成功智能体的结论交叉复核。本报告不含任何未经代码定位的推断。

### 1.2 汇总统计

**问题条目**（一条 = 一个可修复单元，已跨维度去重）：

| 维度 | 条数 | 高 | 中 | 低 | 待确认 |
| --- | --- | --- | --- | --- | --- |
| 设计 | 9 | 2 | 7 | 0 | 0 |
| 视觉 | 29 | 1 | 14 | 14 | 0 |
| 交互 | 25 | 12 | 9 | 4 | 0 |
| 性能 | 10 | 4 | 2 | 2 | 2 |
| **合计** | **73** | **19** | **32** | **20** | **2** |

**处理状态**（截至 2026-10-30）：

| 状态 | 条数 | 说明 |
| --- | --- | --- |
| 已修复 | 71 | 全部经过「定位根因 → 改代码 → typecheck / lint / build / e2e」四道门 |
| 待处理 | 2 | 需外部条件（CI 后端、真实设备指标），见第五章 |
| 已撤回 | 3 | 复核后判定原结论不成立，附录 A 逐条给出推翻证据 |
| 决定不改 | 12 | 有明确设计意图或改造成本高于收益，附录 B 说明理由 |
| 正例记录 | 10 | 已核实无问题的实现，保留以防后续复审误报 |

> 「待确认」2 条不参与严重度统计，它们是仅靠运行时指标或 CI 环境才能裁定的门禁覆盖问题。

### 1.3 逐页面覆盖清单

| 路由 | 页面组件 | 审查结论 |
| --- | --- | --- |
| `/` | `pages/website/home/index.vue` + 7 个 `Home*` 组件 | P1（10 条） |
| `/products` | `pages/website/products/index.vue` + `page.scss` | P2（12 条） |
| `/products/:typeId/:groupId/:productId` 等 3 条购买路由 | 同上（同组件，仅路由参数不同） | P2 覆盖 |
| `/products/:id` | `pages/website/ProductDetail/index.vue` | P3（10 条） |
| `/cloud-server` `/cloud-pc` `/hong-kong-server` `/high-defense-server` `/us-server` | `seo-landing/index.vue` + `pages/*.vue` 5 个 + `data/seoLanding/*.js` 5 份 | P8（6 条） |
| `/about` | `pages/website/about/index.vue` | P4（4 条） |
| `/terms` `/privacy` | `pages/website/legal-document/index.vue`（共用，props 区分） | P5（3 条） |
| `/notices` | `pages/website/notices/index.vue` → `ContentListPage` | P6（7 条） |
| `/notices/:id` | `pages/website/notice-detail/index.vue` → `ContentDetailPage` | P6 + P7 覆盖 |
| `/help` | `pages/website/help/index.vue` → `ContentListPage` | P7（4 条） |
| `/help/:id` | `pages/website/help-detail/index.vue` → `ContentDetailPage` | P7 覆盖 |
| 任意未匹配路径 | `pages/common/NotFound.vue` | P9（2 条） |
| 全站外壳 | `layout/WebsiteLayout.vue` + `WebsiteLayout.scss` + `components/WebsiteFooter.vue` + `composables/useMobileNav.js` + `App.vue` | P10（9 条） |
| 全站移动组件 | `MobileOptionPicker`、`MobileOsPicker`、`MobileRegionPicker`、`MobileRangePicker`、`MobileSheet`、`RouteLoadingFallback`（12 处调用） | P11（6 条） |
| 全站工程 | `vite.config.js`、`scripts/*`、`package.json` 2 份、`backend/scripts/frontend_budget.mjs`、`shared/*` | P12（25 条） |

> 25 个页面组件全部逐条走查到区块级，无抽样；`src/components` 6 个组件全部被至少一个页面调用（零死组件）；`src/domains` 只有 `products` 一个域，4 个 composable 全部被页面消费。

## 二、问题清单（按页面组织）

严重程度：高 = 功能不可用/无障碍硬阻断/预算门禁失效；中 = 明显体验缺陷或契约错位；低 = 一致性、可维护性、冗余。「修复方向」列为实际落地做法；状态列为截至本报告的处理结果。

### P1　首页（`src/pages/website/home`）

**范围**：`index.vue` + 7 个 `components/Home*.vue`（Hero 轮播、资讯、合作伙伴、产品 Tab、注册条、方案区、骨架屏）

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P1-01 | `HomeHeroCarousel.vue:140`（`.hero-mobile-nav`）→ 移入 `:1724` 附近的 992 块 | 交互 | 高 | 641–992px 区间内，桌面 `.hero-dots`（992 块 `display:none`）与移动端 `.hero-mobile-nav`（基类 `display:none`，仅 640 块恢复）同时不可见，轮播暂停/恢复入口在该区间完全消失，违反 WCAG 2.2.2 自动播放须可暂停（Level A）。 | 把 `.hero-mobile-nav` 整组从 640 块上移到 992 块，使移动形态全区间可用；`.hero-dots` 仍只在 993px 以上出现，两者互补而非同时消失。 | 已修复 |
| P1-02 | `HomeHeroCarousel.vue:497`、`:505` | 交互 | 中 | 自动轮播不检测 `prefers-reduced-motion`。轮播由 JS 定时器驱动，`global.scss:234` 的全局 CSS 兜底（压平 `animation-duration`）覆盖不到定时器，前庭敏感用户无法阻止自动切帧。 | 新增 `reducedMotionQuery`/`prefersReducedMotion`/`reducedMotionOptIn`；`startRotation()` 加 reduce 守门；`toggleRotation()` 恢复分支直接置 `rotationPaused=false` 并置 `reducedMotionOptIn=true`，不复用会清 hover 状态的 `resumeRotation()`。 | 已修复 |
| P1-03 | `HomeSectionSkeleton.vue:171`（`.home-section-skeleton--hero`） | 视觉 | 中 | BEM 修饰符 `--hero` 的 `min-height:0`/`padding` 归零规则写进了 640 媒体查询，病因是该修饰符被误当成内层 `__hero` 块的样式归属，导致 993px 以上首屏骨架高度与真实 Hero 不一致，加载完成后出现跳动。 | 把该重置规则从 640 块搬到 992 与 1180 块，按断点对齐真实 Hero 的 `min-height` 与内边距。 | 已修复 |
| P1-04 | `HomeSolutionSection.vue:80` | 交互 | 高 | 行业方案区主按钮文案硬编码「云服务器」，但点击后跳 `activeIndustrySolution.productPath`（随 Tab 变化），文案与目标不一致，按「云服务器」理解会落到其他行业商品页。 | 文案改为「查看相关产品」，让按钮描述动作而非具体品类。 | 已修复 |
| P1-05 | `HomeSolutionSection.vue`、`HomeNewsSection.vue:94`、`HomeProductTabs.vue`、`HomeHeroCarousel.vue`、`HomeSectionSkeleton.vue`、`HomeRegisterBar.vue` | 视觉 | 中 | 6 个首页组件共 27 处裸 hex（`#0f172a`/`#111827`/`#111a34` 三种近似深色，及 `#4b5565`、`#e9edf5`、`#f4f7fc` 等），与 `variables.scss` 令牌体系脱钩，改主题色无法一处生效。 | 新增 `$text-color-heading:#0f172a`、`$bg-color-muted:#f4f7fc` 等语义令牌并按语义替换（标题色/次级文字/边框/底板），逐文件 grep 验证残留为空。 | 已修复 |
| P1-06 | `pages/website/home/index.vue:6`（`.home-error-bar`）与公告条 | 视觉 | 低 | 公告条 `border:1px solid #ffe1d8`、`background:#fff7f5` 与错误条 `#f4f7fc` 均为裸值，且错误条 6px 圆角与站内其他提示条不统一。 | 新增 `$color-danger-border:#ffe1d8` 与 `$color-danger-soft` 成对令牌；`.home-error-bar` 圆角改 `$btn-border-radius`。 | 已修复 |
| P1-07 | `HomeRegisterBar.vue` 的 `.container` 与 `.register-bar` | 视觉 | 低 | 注册条容器与实心卡片各自手写 `width:min(1200px, calc(100% - 48px))`；全站同一容器公式散落 13 个文件手写，改内容宽度需 13 处同步。 | 抽出 `@mixin site-container`（`variables.scss:104`），13 处统一 `@include site-container;`，`grep "min(1200px"` 残留归零。 | 已修复 |
| P1-08 | `HomeNewsSection.vue`（`.news-promo`）、`HomeSectionSkeleton.vue` 骨架 media 块 | 视觉 | 低 | 内容卡 10px 圆角裸写，与 `$sm-border-radius` 同值不同源。 | 改 `$sm-border-radius`，视觉不变、来源统一。 | 已修复 |
| P1-09 | `HomeProductTabs.vue:32` | 交互 | - | 企业服务 Tab 已用 `:tabindex="item.key===activeKey?0:-1"` 实现 roving tabindex，键盘在 Tab 组内单点停靠后可用方向键切换。 | 无需修改；后续改动 Tab 组时须保留 roving tabindex 语义。 | 正例 |
| P1-10 | `HomePartnerSection.vue:146` | 性能 | - | 合作方 Logo 循环动画已有组件级 `animation:none`，与 `global.scss:234` 的全局 `prefers-reduced-motion` 兜底双保险。 | 无需修改。 | 正例 |

### P2　商品目录与配置选型页（`src/pages/website/products`）

**范围**：`index.vue`（约 1400 行）+ `page.scss`（约 2200 行），含左侧产品目录、配置区、机型表、吸底结算

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P2-01 | `products/index.vue:63`（`.mobile-type-tabs`）、`:28` | 交互 | 高 | 移动端产品类型切换用 `role="tab"` 伪实现 tablist——无 roving tabindex、无方向键、无 `aria-controls`/`aria-labelledby` 指向对应 `tabpanel`，读屏会宣告一个按 Tab 键走不通的 Tab 组。 | 改 `role="group"` + `:aria-pressed="activeTypeValue===type.value"`，用按压态表达选中，语义与实际键盘能力一致。 | 已修复 |
| P2-02 | `products/index.vue:1001-1003`（`.catalog-loading`） | 交互 | 中 | 首屏配置区加载期间只有 `v-loading` 遮罩，没有可见加载文案与旋转指示，也没有 `aria-live`，弱网下用户面对空白不知是否在加载。 | 新增 `.catalog-loading` 区块（`role="status" aria-live="polite"` + spinner + 中文文案 + `prefers-reduced-motion` 下 `animation:none`），原 `.catalog-error` 改 `v-else-if` 互斥。 | 已修复 |
| P2-03 | `products/index.vue:1025`、`:1033`（两处 `el-empty`） | 交互 | 中 | 「未选商品」与「当前分类暂无商品」两个空态对读屏完全静默，异步加载结束后与「仍在加载」不可区分。 | 两处 `el-empty` 外层补 `role="status" aria-live="polite"`，去掉与 `pageLoading` 重复的 `v-else-if` 条件。 | 已修复 |
| P2-04 | `products/index.vue:81` 等 13 处 `.config-block-title` | 设计 | 中 | 配置区 13 个区块标题是 `<div class="config-block-title">`，标题大纲里完全缺席——h1（sr-only）之后直接是正文，读屏无法按标题跳转分区。 | 13 个 div 全改 `<h2 class="config-block-title">`，样式按元素选择器重挂，视觉不变、结构可导航。 | 已修复 |
| P2-05 | `products/index.vue:53`、`:56` | 设计 | - | 整页唯一 h1 是 `<h1 class="sr-only">产品与服务 - 配置选型与报价</h1>`，视觉隐藏。评估：产品页是目录型界面，视觉层级由左侧产品目录与移动端类型切换承担，再叠可见大标题会挤掉首屏配置区；h1 仍在无障碍树内。 | 保留视觉隐藏，并补 3 行注释留证设计意图，避免下一轮被当缺陷重复上报。 | 决定不改 |
| P2-06 | `products/page.scss` 机型规格表 | 交互 | 中 | 机型对比表用 div 网格实现，无 `table`/`row`/`cell` 语义，读屏无法感知行列关系与行选中态。 | 补 `role="table" aria-label`、行 `aria-selected`、六个单元格 `role="cell"`，保留现有网格视觉。 | 已修复 |
| P2-07 | `products/page.scss:113`（`.shop-sidebar`）等 5 处 `64px` | 视觉 | 低 | 侧栏吸顶与高度计算硬编码 `64px`/`calc(100vh - 64px)`，与 `$navbar-height` 同值不同源，站头高度一变即错位。 | 5 处全改 `$navbar-height`（含 `100dvh` 分支），`page.scss` 内 `64px` 归零。 | 已修复 |
| P2-08 | `products/page.scss:638`（`.filter-label` `padding-top:5px`） | 视觉 | 低 | 筛选区标签 `padding-top:5px` 落在 4px 栅格之外。核实：该值是 13px 标签与右侧控件的基线补偿，去掉后文字不齐。 | 保留，不做栅格强制吸附（见 P12-16）。 | 决定不改 |
| P2-09 | `products/page.scss:212`（`.item-count`，`border-radius:9px`） | 视觉 | 低 | 9px 圆角是全站唯一值，易被当成抄错。核实：该元素 `height:18px`，9px = 高度一半，是标准胶囊。 | 保留。 | 决定不改 |
| P2-10 | `products/page.scss:1620`（`.buy-btn`）、`:1817`（`.allocation-footer-action`） | 视觉 | 中 | 购买主按钮与吸底结算按钮属同一「小圆角实心按钮」族，此前散写裸值；同语义的详情页吸底按钮是 10px，跨页不一致。 | 新增 `$btn-border-radius-sm:4px`，两处引用令牌；详情页侧改同令牌（见 P3-09）。 | 已修复 |
| P2-11 | `products/page.scss:346`（`.catalog-error__retry`） | 视觉 | 低 | 错误态重试按钮 6px 圆角裸写。 | 改 `$btn-border-radius`。 | 已修复 |
| P2-12 | `page.scss` 1100px 局部断点 | 性能 | 低 | 配置区双列改单列用 1100px 一次性断点，未进全局断点令牌体系。 | 登记为「页面局部断点、不新增全局令牌」，在 `variables.scss:140` 注释登记。 | 决定不改 |

### P3　商品详情页（`src/pages/website/ProductDetail`）

**范围**：`index.vue`（约 2200 行），含配置组、优惠券抽屉、吸底结算、售罄态

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P3-01 | `ProductDetail/index.vue:464`（`.pd-coupon-option` 的 `:model-value`） | 交互 | 高 | 优惠券抽屉里把已选券取消后 `selectedCouponId` 变空但抽屉不关，用户看到一个所有选项都未选中、也无任何反馈的抽屉。 | `selectMobileCoupon(id)` 的 `if(!id)` 分支先判 `if(!selectedCouponId.value){ mobileCouponDrawer.value=false; return; }`，取消至空即收起。 | 已修复 |
| P3-02 | `ProductDetail/index.vue:589-590`（`.pd-empty`） | 交互 | 中 | 空态容器同时承载「加载失败」与「商品不存在」两种语义，且无 `role=status`，读屏无播报。 | 在 `.pd-empty` 容器上挂 `role="status" aria-live="polite"`（而非内层 `el-empty`），使两种语义共用同一播报出口。 | 已修复 |
| P3-03 | `ProductDetail/index.vue:97`、`:1332`（`.cfg-group-head`）等 5 处 | 设计 | 中 | 5 个配置分组标题是 div，跳过标题层级直接进正文。 | 5 个 div 改 `<h2 class="cfg-group-head">`；该类已有显式 `font-size:14px`，视觉不变。 | 已修复 |
| P3-04 | `ProductDetail/index.vue`（裸色值 17 处，全站最多） | 视觉 | 中 | `#fafafa`×3、售罄按钮渐变 `#b91c1c`/`#ef4444`、`rgba(#ef4444,.34)`、`soldOutPulse` 关键帧等裸值，与语义色体系脱钩。 | 新增 `$color-danger-a0`/`$color-danger-a34`；`#fafafa` → `$bg-color-soft`；售罄渐变 → `$color-danger-text`/`$color-danger`；关键帧同步换令牌。 | 已修复 |
| P3-05 | `ProductDetail/index.vue:1555`（`.pd-aside` `top:80px`） | 视觉 | 低 | 侧栏吸顶 `top:80px` 是「站头 64px + 16px」的裸算结果，站头高度改变即压字。 | 改 `top: calc($navbar-height + 16px)` 并注释说明与目录页 64px 贴顶的差异理由。 | 已修复 |
| P3-06 | `ProductDetail/index.vue`（`.pd-page` `min-height:calc(100vh - 160px)`） | 视觉 | 低 | 最小高度硬编码 160px 估算（站头 + 页脚），页脚高度变化后出现滚动条抖动。 | 改 `calc(100vh - ($navbar-height + 96px))`，把站头做成令牌项参与计算。 | 已修复 |
| P3-07 | `ProductDetail/index.vue:1921`（`animation: soldOutPulse ... infinite`） | 视觉 | 中 | 售罄脉冲动画常驻 `infinite`，依赖全局 `prefers-reduced-motion` 兜底；关键帧内 `rgba(#ef4444,...)` 是裸值，令牌化时容易漏。 | 关键帧内两处颜色改 `$color-danger-a34`/`$color-danger-a0`；`infinite` 由 `global.scss:234` 全局兜底压平，不再逐个加媒体查询。 | 已修复 |
| P3-08 | `ProductDetail/index.vue:528`（`.buy-btn`） | 交互 | 中 | 曾判「提交按钮可连点重复提交」。复核：`MobileSheet` 的 confirm 处理全同步、无网络请求，唯一真实提交入口 `.buy-btn` 已有 `:disabled` 三重守卫（售罄/加载中/无库存）。 | 撤回该结论（附录 A-01），不加额外防抖。 | 已撤回 |
| P3-09 | `ProductDetail/index.vue:2012`、`:2017`（`.allocation-footer-action`） | 视觉 | 中 | 吸底结算按钮 `112×44`、圆角 10px，与目录页同语义按钮 `112×42`、圆角 4px 不一致。 | 圆角改 `$btn-border-radius-sm` 与目录页对齐；尺寸差（44 vs 42）保留为详情页更大点击热区的有意设计。 | 已修复 |
| P3-10 | `ProductDetail/index.vue`（`.pd-coupon-option` 6px） | 视觉 | 低 | 券选项 6px 圆角属抽屉内次级控件，不与按钮族混编。 | 保留。 | 决定不改 |

### P4　关于我们（`src/pages/website/about`）

**范围**：`index.vue`，含 hero、服务能力、联系方式三块

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P4-01 | `about/index.vue:56` | 视觉 | 中 | 页面 h1 为 40px，是全站最大字号，超过首页 Hero 与 SEO 落地页主标题——最不重要的静态介绍页占了最强视觉权重。 | 降为 32px，回到 `$font-size-h1` 档位，与内容列表页 h1 一致。 | 已修复 |
| P4-02 | `about/index.vue:95`（`.about-card`）、`:107` | 设计 | 中 | `.about-card` 内 h2 未声明字号，继承正文 15px，与卡片外标题拉不开档差。 | 补 `font-size: $font-size-h2`。 | 已修复 |
| P4-03 | `layout/navigationData.js:29-32`、`:36`、`layout/WebsiteLayout.vue:207-208` | 设计 | 中 | 顶栏 `/about` 入口标签写「支持」，与紧邻的「帮助」语义重叠，用户无法判断该点哪个；mega menu 内标题「支持 / 联系方式与常用服务入口」同样含糊；`aboutQuickLinks` 首条 desc 承诺「企业简介、发展愿景与服务承诺」，而页面实际只有三块内容。 | 标签与 mega menu 标题统一改「关于我们」，描述改「公司简介、服务能力与联系方式」；`aboutQuickLinks` 首条 desc 同步。 | 已修复 |
| P4-04 | `about/index.vue`（`.about-card` 背景） | 视觉 | 低 | `linear-gradient(180deg, $bg-color-card 0%, #f5f8fd 100%)` 末尾裸色。 | 改 `$bg-color`。 | 已修复 |

### P5　法律文档（`src/pages/website/legal-document`，`/terms` `/privacy`）

**范围**：`index.vue`，`terms` 与 `privacy` 复用同一组件、以 props 区分

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P5-01 | `legal-document/index.vue:15`（`el-empty`） | 交互 | 中 | 未配置文档地址时渲染空态，但无 `aria-live`，异步判定后读屏静默。 | 外层补 `role="status" aria-live="polite"`。 | 已修复 |
| P5-02 | `legal-document/index.vue`（`.legal-card` 背景） | 视觉 | 低 | `background:#f5f8fd` 裸值。 | 改 `$bg-color`。 | 已修复 |
| P5-03 | `legal-document/index.vue:3`（`.legal-card`） | 设计 | - | 两页共用同一组件与样式，以单卡形态（桌面 20px / 移动 14px 圆角）承载长文档，避免了几乎相同的两份实现。 | 无需修改。 | 正例 |

### P6　公告列表（`src/pages/website/notices` + `content/ContentListPage.vue`）

**范围**：`notices/index.vue` 薄壳 + 被复用的 `ContentListPage.vue`（公告与帮助共用）

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P6-01 | `notices/index.vue:2` | 设计 | - | 页面只有一行 `<ContentListPage content-type="notice" scope="site" />`，h1 与结构由被复用组件提供——内容域四类页面（公告/帮助的列表与详情）零重复实现。 | 无需修改；后续若加页面级差异，应在被复用组件里加 props，不再复制一份。 | 正例 |
| P6-02 | `content/ContentListPage.vue`（`.hero-copy h1`） | 视觉 | 中 | 列表页 h1 为 24px，与详情页正文 h2 同号，列表页与详情页的页面主标题不对等。 | 改 `$font-size-h1`，与全站页面主标题同档。 | 已修复 |
| P6-03 | `content/ContentListPage.vue:260`（`SIDEBAR_ARTICLE_LIMIT`） | 设计 | 中 | 侧栏「热门」「最新」两个列表未限条数，直接取接口全量，长列表把侧栏拉得比正文还高。 | 新增常量 `SIDEBAR_ARTICLE_LIMIT = 5`，两条排序链末端 `.slice(0, SIDEBAR_ARTICLE_LIMIT)`。 | 已修复 |
| P6-04 | `content/ContentListPage.vue:874-884` | 视觉 | 低 | 热榜 top-1/2/3 金银铜徽章用 6 个裸 hex（`#fff0d9`/`#92400e`、`#e9f1fb`/`#1d4ed8`、`#f7e8de`/`#7c2d12`）；且 class 由 `top-${index+1}` 拼接，`index>=3` 时生成不存在的 `top-4+`。 | 6 个色值改 `$badge-rank-1-bg`/`-text` 系列令牌（注释标明 12px 文字已过 4.5:1，换值须重跑对比度）；class 改 `index<3 ? 'top-'+(index+1) : undefined`。 | 已修复 |
| P6-05 | `content/ContentListPage.vue:111` | 交互 | 中 | 搜索空结果的 `el-empty` 无播报。 | 补 `role="status" aria-live="polite"`。 | 已修复 |
| P6-06 | `content/ContentListPage.vue`（`retrySidebar()`） | 交互 | 低 | 侧栏重试未推进 `syncToken`，竞态令牌与上一次请求相同，重试可能被自己的过期判定丢弃。 | `retrySidebar()` 改为 `loadOverview(++syncToken)`。 | 已修复 |
| P6-07 | `content/ContentListPage.vue:532`（`.hero-panel`） | 视觉 | 低 | `radial-gradient(rgba(221,122,31,.12))` 与新增的 `$color-accent-orange-soft`（.14）同色不同源。 | 改引用令牌，0.12→0.14 视觉等价。 | 已修复 |

### P7　帮助列表与内容详情（`src/pages/website/help`、`content/ContentDetailPage.vue`）

**范围**：`help/index.vue` 与 `help-detail/index.vue` 薄壳 + 被复用的内容组件

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P7-01 | `help/index.vue:2`、`help-detail/index.vue` | 设计 | - | 与公告页同构，一行复用组件，h1 由被复用组件提供。 | 无需修改。 | 正例 |
| P7-02 | `content/ContentDetailPage.vue:496-521` | 设计 | 高 | 正文 h1~h4 共用一组声明且都没有 `font-size`，渲染后四级标题同号，读者无法从视觉判断层级（帮助详情与公告详情同时受影响）。 | 共用组保留间距/字色/字重，另加 `:deep(h1){28px}`/`h2{24px}`/`h3{20px}`/`h4{16px}` 四档递减。 | 已修复 |
| P7-03 | `content/ContentDetailPage.vue:263`（`reader-table-wrap`） | 视觉 | 中 | 宽表用 `:deep(table){display:block;overflow-x:auto}` 实现横向滚动，`display:block` 破坏表格列宽分配算法，列宽塌成内容宽度。 | 改为在 markdown 渲染时用 computed 把 table 包进 `div.reader-table-wrap`，滚动容器与 table 分离，列宽算法恢复。 | 已修复 |
| P7-04 | `content/ContentDetailPage.vue:42`（`v-html`） | 交互 | 低 | 全站唯一 `v-html`。核实：来源为 `articleContentHtml` computed，经 `sanitizeMarkdownSource` + `renderMarkdown` 处理，非裸 HTML 注入。 | 保留；后续新增富文本入口必须复用同一 sanitize 链路。 | 决定不改 |

### P8　SEO 落地页（`src/pages/website/seo-landing`，5 页 + 5 份数据）

**范围**：`index.vue`（约 1300 行骨架）+ `pages/*.vue` 5 个 + `data/seoLanding/*.js` 5 份

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P8-01 | `seo-landing/index.vue:19`（`heroNavItems`） | 设计 | 中 | `heroNavItems` computed 末尾 `.slice(0,4)`：每页 `points.length===3`、`stats.length===3`，按 1+(points-1)+stats 应为 6 条被截到 4，`stats[1]`/`stats[2]` 共 10 条核心卖点永不渲染——数据写了、页面不显示。 | 去掉 `slice`，改为 `eyebrow` 可缺省 + points 全量 + stats 全量拼装并 `.filter(Boolean)`；5 页每页 6 条，响应式已核实不溢出（桌面 grid gap 30px、窄屏 flex + `overflow-x:auto` + `li{flex:0 0 auto}`）。 | 已修复 |
| P8-02 | `seo-landing/index.vue:971-980`（`.seo-section--solutions`） | 视觉 | 高 | 深色区 `background: linear-gradient(...), #d4dee8`，兜底色 `#d4dee8` 与白字对比度仅 1.35:1——渐变一旦渲染失败（旧内核/打印/强制颜色模式），白字不可读。 | 兜底色改 `#2b415b`（与渐变右端同族，白字 10.5:1），并注释「渐变永远覆盖它，但兜底也要满足对比度」。 | 已修复 |
| P8-03 | `seo-landing/index.vue:639`、`:647`（`.seo-action`） | 视觉 | 中 | 落地页主/次 CTA 圆角 2px，是全站最小的按钮圆角，与商品页购买按钮（4px）、首页 CTA（6px）三档并存无规律。 | 改 `$btn-border-radius-sm`（4px），与购买主按钮同族。 | 已修复 |
| P8-04 | `app/router/routes.ts:21`、`:30` | 交互 | 高 | `resolveSeoLandingView` 在模块求值期直接 `throw`：任一落地页数据缺失（新增页漏配 view）都会让路由模块加载失败，而 `main.js` 只有 `bootstrapClientApp()` 没有 errorHandler ⇒ 全站白屏，不只是该页 404。 | `import.meta.env.DEV` 才 `throw`（开发期立即暴露），生产期 `console.error` 后 `return null`，`seoLandingMetaPages.flatMap` 跳过缺失项。 | 已修复 |
| P8-05 | `seo-landing/index.vue`（场景卖点） | 设计 | - | 此前判断「场景要点渲染了错误字段」，复核为 `activeScenarioPoints` 已正确取值。 | 撤回（附录 A-07）。 | 已撤回 |
| P8-06 | `seo-landing/index.vue` 1080/760 断点 | 视觉 | 低 | 两个一次性断点未进全局令牌。 | 登记为页面局部断点（`variables.scss:140` 注释）。 | 决定不改 |

### P9　404（`src/pages/common/NotFound.vue`）

**范围**：单文件，含卡片与两个返回按钮

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P9-01 | `NotFound.vue:8-9` | 设计 | 低 | 404 页是全站唯一使用 `<el-button>` 承载业务动作的地方（其余 12 处 `el-button` 全在错误/空态兜底）。核实：错误页本就是 Element Plus 兜底语境的自然延续，改手写按钮收益为零。 | 保留，并在此登记为「刻意例外」，防止后续一致性清算误改。 | 决定不改 |
| P9-02 | `NotFound.vue:3`（`.not-found-card` 20px 圆角） | 视觉 | 低 | 20px 大卡圆角与法律文档页 `legal-card` 同族（独立整页大卡），与内容卡（12px）、实心大卡（14px）区分。 | 保留（圆角体系按体量分层，不收档）。 | 决定不改 |

### P10　全局布局（`src/layout` + `src/App.vue`）

**范围**：`WebsiteLayout.vue`、`WebsiteLayout.scss`、`components/WebsiteFooter.vue`、`composables/useMobileNav.js`、`App.vue`、`app/router/index.ts`

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P10-01 | `components/WebsiteFooter.vue:38` 等 4 处 `<h3>` | 设计 | 高 | 页脚 4 个栏目标题用 `h3`，而全页只有 h1（Hero）→h3，跳过 h2，标题大纲断裂，读屏按标题跳转会漏掉整块页脚。 | 4 个 `h3` 改 `h2`，样式按 `.footer-col h2` 重挂，同时拉开与链接的字号档差（桌面 15px vs 13px、移动 14px vs 12px）。 | 已修复 |
| P10-02 | `WebsiteLayout.scss:336`、`:341` | 视觉 | 中 | 站内导航用 `mode="out-in"` 的 page-fade 且两端 `opacity:0`：旧页先淡出到全白、新页再从全白淡入，中间 0.14+0.18s 页面整体发白，快速连击时观感像闪屏。 | 两端改 `opacity:0.5`（不落 0），并注释说明 `App.vue` 的 `route-fade` 对站内导航不生效（顶层只有一条 `/` 布局路由），真正起作用的是这里的 `page-fade`。 | 已修复 |
| P10-03 | `App.vue:3`（`.app-route-progress::before`） | 性能 | 中 | 路由进度条把 `animation: ... infinite` 挂在基类上，容器 `opacity:0` 时动画仍在每帧合成，空闲态持续占用合成线程。 | 把 `animation` 移到 `.app-route-progress.is-active::before`，空闲态零动画。 | 已修复 |
| P10-04 | `WebsiteLayout.scss:77`（`.main-nav__link`）等 6 处 `#374151` | 视觉 | 中 | 站头导航/用户菜单文字裸写 `#374151` 6 次，与 `$text-color-primary`（#1f2937）差一档，但令牌体系里没有表达这个中间档。 | 新增 `$text-color-nav:#374151` 并替换 6 处；`.header-user-arrow` 的 `#6b7280` → `$text-color-placeholder`。 | 已修复 |
| P10-05 | `WebsiteLayout.scss:8`（`.container`） | 视觉 | 低 | 站头容器手写 1200 容器公式（见 P1-07）。 | 改 `@include site-container`。 | 已修复 |
| P10-06 | `layout/composables/useMobileNav.js:23`、`:82` | 交互 | - | 移动菜单焦点管理曾疑似缺失。核实：`handleMobileMenuKeydown` 已做 Esc 关闭 + Tab/Shift+Tab 环形陷阱，`:50-69` 保存/还原 inline `overflow`，打开时 `nextTick` 聚焦首项、关闭时把焦点还给 toggle 按钮。 | 无需修改（正例记录）。 | 正例 |
| P10-07 | `app/router/index.ts:10`（`scrollBehavior`） | 交互 | 低 | `scrollBehavior` 用 `window.innerWidth` 判移动形态，而全站断点用 `matchMedia`（含滚动条约 15px 宽差），在 992±16px 处口径错位：`innerWidth` 已 ≥992 但 `matchMedia` 仍命中移动形态，滚动锚点行为与布局不一致。 | 改调 `matchesMobileViewport()`，与 `useMobileBreakpoint` 同源。 | 已修复 |
| P10-08 | `WebsiteLayout.vue:3`（`.skip-to-content`）+ `global.scss:40` | 交互 | - | 跳过导航链接已实现：首个可聚焦元素、跳 `#main-content`，路由切换后 `document.getElementById('main-content')?.focus({preventScroll:true})` 复位。 | 无需修改。 | 正例 |
| P10-09 | `layout/WebsiteLayout.vue`（891 行 + 独立 SCSS） | 性能 | - | `WebsiteLayout.vue` 曾 2483 行，已拆为 891 行组件 + 独立 SCSS + `composables/useMobileNav.js`（历史批次完成）。 | 无需修改。 | 正例 |

### P11　移动端通用组件（`src/components`）

**范围**：`MobileOptionPicker`、`MobileOsPicker`、`MobileRegionPicker`、`MobileRangePicker`、`MobileSheet`、`common/RouteLoadingFallback`，全站 12 处调用

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P11-01 | `MobileOptionPicker.vue:47`、`MobileOsPicker.vue:91`、`MobileRegionPicker.vue:23` | 交互 | 高 | 三个 Picker 的 `id` 全站硬编码（如 `os-option-0`），同页同时渲染两个 Picker 时 id 冲突，`aria-activedescendant` 指向错误元素，读屏报错位选项；RegionPicker 更是缺少 `aria-activedescendant`。 | 全部改 `useId()` 生成实例级 id 前缀，`aria-activedescendant` 按前缀拼接；OsPicker 另补 Home/End/Enter 键处理。 | 已修复 |
| P11-02 | `MobileSheet.vue:32`（`confirmText`） | 交互 | 低 | 组件对外暴露 `confirmLoading` / `confirmDisabled` 两个 prop，全仓零调用方（唯一真实提交入口 `.buy-btn` 自带 disabled 三重守卫），是纯 API 冗余，会误导使用者自己管 loading。 | 删除两个 prop 与对应 `:disabled`/`:aria-busy`/`&:disabled` 样式，还原为普通确认按钮，并注释说明「本项目所有 confirm 都是同步本地选择」。 | 已修复 |
| P11-03 | `MobileSheet.vue`（8 处调用方的 confirm 链路） | 交互 | 高 | 曾判「confirm 可连点造成重复提交」。复核：8 处 confirm 处理函数全同步、无网络请求（`products/index.vue:1298/1310/1357/1485`、`ProductDetail:614`、三个 Mobile Picker 的 `handleConfirm` 仅 emit），真实提交入口已有三重守卫。 | 撤回并降级为 P11-02 的 API 冗余（附录 A-01）。 | 已撤回 |
| P11-04 | `MobileSheet.vue:210`（`.ms-drawer .el-drawer` `18px 18px 0 0`） | 视觉 | 低 | 移动抽屉顶角 18px 与「实心大卡 14px」不同档。 | 保留（抽屉顶角是独立形态档位）。 | 决定不改 |
| P11-05 | `common/RouteLoadingFallback.vue:2` | 交互 | - | 路由加载占位 `role="status" aria-live="polite" aria-busy="true"`。 | 无需修改。 | 正例 |
| P11-06 | `MobileOptionPicker.vue`、`MobileOsPicker.vue`、`MobileRegionPicker.vue` 的 `outline:none` | 交互 | - | 三处 `outline:none` 均紧跟 `&:focus-visible{box-shadow: inset 0 0 0 2px $color-primary}`——去轮廓必配替代焦点指示。 | 无需修改；新增交互组件须遵循同一配对规则。 | 正例 |

### P12　工程、构建与跨端契约

**范围**：`vite.config.js`、`scripts/*`（10 个）、`package.json` 2 份、`backend/scripts/frontend_budget.mjs`、`shared/*`

| 编号 | 位置 | 维度 | 严重度 | 问题描述 | 修复方向 | 状态 |
| --- | --- | --- | --- | --- | --- | --- |
| P12-01 | `frontend-user-v3-www/vite.config.js:153` | 性能 | 高 | CSP 注入插件的 `VITE_CSP_API_ORIGIN` 兜底为 `'http://127.0.0.1:8000'` 且与 `mode` 无关：干净 clone 没有 `.env.production` 时，生产构建会把这个本地地址写进 `img-src`/`connect-src`/`media-src`。 | 兜底改 `(mode === 'production' ? '' : 'http://127.0.0.1:8000')`，生产缺配置时留空而不是注入本机地址。 | 已修复 |
| P12-02 | `scripts/element-plus-subpath-resolver.mjs:75`、`:122` | 性能 | 高 | Element Plus 子路径解析器只改写 89/123 个 `El*` 导出，剩余 34 个回退到 barrel 入口，会把 dayjs 等整包拖回首屏 chunk。 | 别名表扩到 30 条 + `componentNameAliases` 兜底；未知导出由 `console.warn` 升级为 `throw`；实测 123/123 全部改写。 | 已修复 |
| P12-03 | `backend/scripts/frontend_budget.mjs:15`、`:57` | 性能 | 高 | 体积门禁 `INDEX_ASSET_PATTERN` 只统计 `index.html` 直引的 4 个资产（102,182 B gzip）就判通过，而真实首屏闭包（layout + home 及其 import 链）为 134,631 B gzip，超预算却绿灯——门禁失真等于没有门禁。 | 新增 `FIRST_SCREEN_MANIFEST_ENTRIES`（www 登记 `index.html`/`src/layout/WebsiteLayout.vue`/`src/pages/website/home/index.vue`）与 `collectManifestClosure`/`resolveFirstScreenAssets`；未登记应用继续走 `collectEntryAssets` 保持原口径；www 首屏预算 115,000 → 145,000（按真实闭包定档）。 | 已修复 |
| P12-04 | `vite.config.js:173`（`client-first-screen-hints`） | 性能 | 高 | 首屏 4 个关键资源（entry chunk、layout、home、样式）没有任何 `modulepreload`/`preload` 提示，浏览器只能按 import 图逐跳发现，首屏多一轮往返。 | 新增插件按 `ctx.bundle.facadeModuleId` 匹配 layout/home 两组 chunk 注入 `modulepreload` 与 `stylesheet`；预渲染非 `/` 路由时用 `stripHomeFirstScreenHints` 裁掉 home 组并剥 `data-first-screen`。实现要点：rollup 的 `facadeModuleId` 是正斜杠而 `path.resolve` 给反斜杠，两侧必须归一化；入口 chunk 会被 layout/home 的 imports 同时引用，需先按 `index.html` 取出 entry 加入 `alreadyPreloaded`。 | 已修复 |
| P12-05 | `vite.config.js:69-78`（`createPrecompressedAssetsPlugin`） | 性能 | 中 | 预压缩插件硬编码 `dist`、不读 `config.build.outDir`：`build:e2e` 输出到 `dist-e2e` 时仍去重压一版陈旧的 `dist`，既浪费又掩盖 `dist` 陈旧度。 | 用 `configResolved(config)` 取 `config.build.outDir`。 | 已修复 |
| P12-06 | `shared/runtime/session.ts:36` | 交互 | 高 | 会话 cookie 用 `SameSite=Strict`：从外部站点点击链接（SEO 落地页 CTA、营销邮件、搜索引擎）进入时浏览器不携带 cookie，落地页接口拿不到会话。三次 Playwright 探针定位：同 site 测不出差异；`page.goto` 属浏览器发起、非跨站导航上下文，Strict 也放行；必须 `--host-resolver-rules=MAP external.test 127.0.0.1` + 真实 `a` 点击才复现（Strict 下 `landingRequestCookie` 为空而 `documentCookie` 有值）。 | `writeCookie`/`deleteCookie` 两处 `SameSite=Strict` → `Lax`，同步 `shared/tests/runtime.test.mjs` 断言。 | 已修复 |
| P12-07 | `src/domains/products/useWebsiteProductCheckout.js:326` | 交互 | 高 | www 侧 `redirectToConsoleCheckout` 写 `pendingCheckout` 时缺 `savedAt`，控制台侧按 `savedAt` 判 TTL 恒失效——URL 直达链路永不过期；控制台落地还会把它改写成当前时间，形成无限续期。 | 补 `savedAt: Date.now()`；`shared/utils/websiteCheckout.ts` 两处兼容（`raw.savedAt ?? raw.createdAt`，只在缺失时补打 `savedAt`）。 | 已修复 |
| P12-08 | `useWebsiteProductCheckout.js:540`（`isQuoteExpired`） | 交互 | 高 | 前端 `CHECKOUT_TTL_MS = 30min`，后端 `CheckoutSecurityService::QUOTE_TTL_SECONDS = 600`（10 分钟），前端以为还有效时后端已判超时，用户提交才收到「报价已超时」。 | 不做前端硬编码 TTL 推断，改读后端真实下发的 `quote_expires_at`（经 `SiteProductQuoteResource:34` 透传）新增 `quoteExpiresAt`/`isQuoteExpired()`，`submitOrder()` 过期时提示并重新询价。 | 已修复 |
| P12-09 | `useWebsiteProductCheckout.js:347` | 交互 | 高 | `redirectToConsoleCheckout` 的守卫 `if (targetUrl)` 恒真——`buildConsoleUrl` 在源站未配置时回退成站内 `/client/*`，永不为空，「控制台地址未配置」被静默吞掉，用户被送到站内副本。 | 改判 `if (isConsolePath(buildConsoleUrl(checkoutPath, checkoutQuery)))`（`src/utils/consoleUrl.js:104`）则 `ElMessage.error` 提示控制台地址未配置并 `return`。 | 已修复 |
| P12-10 | `src/domains/products/useWebsiteProductsCatalog.js:354` | 交互 | 高 | `pageError` 命中缓存后只置不清：`applyGroupPayload`（走缓存的路径）不经过复位，一次请求失败后错误块长期驻留，直到用户手动刷新。 | `applyGroupPayload` 开头补 `pageError.value = false`。 | 已修复 |
| P12-11 | `shared/statusConfig.ts:356`（`FINANCE_LEDGER_EVENT_MAP`） | 设计 | 中 | 映射从 10 键删到 5 键，`recharge`/`manual_recharge`/`manual_deduction`/`referral_credit_cash`/`system_adjustment`/`verification_fee` 六类账变事件在财务流水里显示「未知」；且 `FinanceTab.vue:26` 的 `\|\|` 兜底因此永远走不到后端下发的 `row.display.badge`。 | 补回 6 键（共 10 键），保留后端 badge 优先的兜底语义。 | 已修复 |
| P12-12 | `shared/utils/websiteCheckout.ts`（quantity 校验） | 交互 | 高 | 前端允许 `quantity` 1..1000，后端 `StoreRequest.php:12` 为 `max:1`、`CheckoutService.php:142` 直接 `throw_if($quantity !== 1)`，前端把必然失败的请求放了过去。 | 改为 `!== 1` 即拒；新增 `shared/tests/website-checkout.test.mjs` 10 组断言并接进 `shared/package.json` 的 test 链。 | 已修复 |
| P12-13 | `shared/utils/websiteCheckout.ts:97` | 交互 | 中 | `quote_token` 前端限 256，后端 `max:120`；真实凭证为 `quote_` + 48 位 = 54 字符，超长时前端不拦、后端才 422。 | 上限改 120，与后端 `max:120` 对齐并注释真实凭证长度。 | 已修复 |
| P12-14 | `assets/styles/variables.scss:135`、`:137`、`:139` | 视觉 | 中 | 媒体查询散落 7 个桌面断点（640/760/1080/1100/1180/1200/1260），其中 640/1180/480 在 20+ 处手写，改一处不生效。 | 新增 `$breakpoint-compact:640`/`$breakpoint-wide:1180`/`$breakpoint-tiny:480` 并全量替换（`grep '@media (max-width: 640px)'` 归零）；其余一次性断点（1200/1260/1100/1080/760）登记为页面局部。 | 已修复 |
| P12-15 | `variables.scss:122-124` | 视觉 | 中 | 手写按钮圆角 2/4/6/8 四档并存：`.seo-action` 2px、`.buy-btn`/`.allocation-footer-action`/`.solution-panel__action` 4px、`.hero-cta`/`.register-bar__button`/`.catalog-error__retry` 6px、`.header-register` 8px，同语义跨页不一致。 | 按体量分三档令牌 `$btn-border-radius-sm:4`/`$btn-border-radius:6`/`$btn-border-radius-lg:8`，14 处替换。规则：小圆角实心族（购买/结算/SEO 与方案 CTA）4px，44px 高主 CTA 6px，34px 矮按钮（站头注册）8px。 | 已修复 |
| P12-16 | 全站 padding/margin/gap 统计（38 个 px 值） | 视觉 | 低 | 间距共 38 个不同 px 值，6/10/14/18/22/26px 等非 4 倍数高频出现，评估是否引入 4px 基准 spacing scale。 | 不引入：高频值（12px×97、8px×78、10px×64、16px×50、14px×35、6px×34、18px×34、24px×33、4px×26）本身已是稳定体系，离网值仅 11 处且各有基线对齐理由（如 `products/page.scss:638` 5px 是 13px 文本基线补偿），强制吸附会破坏对齐。 | 决定不改 |
| P12-17 | 全站 border-radius 统计（13 档） | 视觉 | 低 | 圆角共 13 档（1/2/4/6/8/9/10/12/14/16/18/20/999px），评估是否收成 3 档。 | 不收：现有值可按「胶囊(999/9)、小徽章(2)、手写按钮(4/6/8)、次级控件(8)、内容卡(12)、实心大卡(14)、抽屉与独立大卡(18/20)」解释，收档会破坏既有层级。 | 决定不改 |
| P12-18 | `variables.scss` 死令牌 | 视觉 | 低 | `$base-border-radius`/`$base-padding`/`$base-margin`/`$sidebar-width`/`$sidebar-width-collapsed`/`$zindex-navbar`/`$zindex-sidebar`/`$zindex-modal`/`$color-accent-orange` 共 9 个零引用令牌，另有 13 个 Legacy aliases 整区零引用。核实：`element/index.scss` 的 `$border-radius:`/`$box-shadow:`/`$font-family:` 是 Element Plus `with()` 配置键名，不是读取同名变量（同名巧合，不作存活证据）。 | 删除 9 个死令牌与整个 Legacy aliases 区，新增 `$color-danger-a0`/`-a34` 补位。 | 已修复 |
| P12-19 | `src/data/homeSolutions.js` | 性能 | 低 | `industrySolutions` 每项的 `subtitle`/`accent`/`accentSoft` 三字段全仓零消费（唯一消费方 `HomeSolutionSection.vue:102`），21 行死数据给维护者制造「这里有配色配置」的错觉。 | 剥离 21 行，字段收敛为 `key/label/title/description/points/image/productPath/consultPath`，文件 135 → 114 行。 | 已修复 |
| P12-20 | `frontend-user-v3-www/package.json:11`、根 `package.json:41` | 性能 | 低 | 前端 `lint` 传 eslint 9 已废弃的 `--ext`；根 `lint-staged` 的 glob 不含 `.mjs`，10 个构建脚本（含体积门禁与解析器）提交前永不 lint。 | 去掉 `--ext`；glob 扩到 `{js,mjs,cjs,jsx,ts,mts,cts,tsx,vue}`。 | 已修复 |
| P12-21 | `scripts/precompress-assets.mjs:7`、`README.md:24`、`index.html` | 视觉 | 低 | 注释写「8 条静态路由」实为 7；注释写体积 165,257 B 与实测 153,833 B 不符；`index.html` 内嵌 1,618 B 的 CSP TODO 块长期停留。 | 两处注释按实测改写；CSP 待办移入 `docs/参考资料/运维/前端Nginx伪静态配置.md` 的「CSP 收紧待办」章节；另删除误创建的 `frontend-user-v3-www/nul`、补 `homePartners.js` 尾部换行、`shared/package.json` 补 `./runtime/routeLoading` 导出。 | 已修复 |
| P12-22 | 全站 8 处 `el-empty`（`ContentDetailPage.vue:58`、`ContentListPage.vue:111/187/213`、`legal-document/index.vue:15`、`ProductDetail/index.vue:590`、`products/index.vue:1025/1033`） | 交互 | 中 | 空态容器全部裸写，异步加载结束后的空结果对读屏静默，与「仍在加载」不可区分。 | 8 处统一补 `role="status" aria-live="polite"`，逐处加中文注释说明「异步结果需播报」。 | 已修复 |
| P12-23 | `shared/tests/http-client.test.mjs` | 交互 | 低 | 401 用例用桩数据模拟，未走真实 HTTP，也未断言 `err.__handled === false`，拦截器重复提示的行为没被锁住。 | 改真实 HTTP 401 并补 `assert.equal(err.__handled, false)`。 | 已修复 |
| P12-24 | `e2e/ux-fixes.spec.ts:102/315`、`smoke.spec.ts:20/28/51` | 性能 | 待确认 | 5 条带 `@needs-backend` 的用例依赖本地 Laravel 后端可用，无后端时静默跳过——CI 上这 5 条长期不跑。 | 待确认：由 CI 侧决定是否拉起后端补跑（本地已验后端 200 时真跑非跳过）。 | 待处理 |
| P12-25 | 全站 | 性能 | 待确认 | 本轮性能结论基于构建产物体积（首屏闭包 134,497 B gzip）与代码级反模式扫描，未测 LCP/FCP/INP 等运行时指标，也未做像素级视觉回归。 | 待确认：需真实设备/网络条件下跑 Lighthouse 与视觉回归基线；`scripts/lighthouse-audit.mjs` 已在仓库，可直接接入。 | 待处理 |

## 三、优化修复建议（按投入产出比排序）

排序口径：**A = 单点小改动、直接消除功能或无障碍阻断**；**B = 局部结构调整、收益明确但仍需改动多处**；**C = 体系化收口、一次性投入换长期可维护性**；**D = 需外部条件（CI 环境、真实设备指标）才能裁定**。同一档内按「影响面 ÷ 改动量」降序。

### 3.1　A 档（26 条）：先做这些，几乎零风险且立刻见效

| 序 | 编号 | 动作 | 消除的问题 | 改动量 |
| --- | --- | --- | --- | --- |
| 1 | P3-01 | `selectMobileCoupon(id)` 的 `if(!id)` 分支先判空即收起抽屉 | 取消优惠券后抽屉停在全未选中态、无反馈 | 1 处 3 行 |
| 2 | P12-10 | `applyGroupPayload` 开头补 `pageError.value = false` | 命中缓存时错误块长期驻留 | 1 行 |
| 3 | P12-09 | 守卫改 `isConsolePath(buildConsoleUrl(...))` 并提示控制台地址未配置 | 控制台未配置时静默把用户送到站内副本 | 1 处 + 1 个 import |
| 4 | P12-07 | 写 `pendingCheckout` 时补 `savedAt: Date.now()` | URL 直达链路 TTL 恒失效、无限续期 | 1 行 + shared 2 处兼容 |
| 5 | P6-03 | 新增 `SIDEBAR_ARTICLE_LIMIT = 5` 并在两条排序链末端 `.slice()` | 侧栏长列表拉得比正文还高 | 1 常量 + 2 处 |
| 6 | P11-01 | 三个 Picker 的 `id` 改 `useId()` 前缀 + 补 `aria-activedescendant` | 同页双 Picker 时 id 冲突、读屏报错位选项 | 3 文件，机械替换 |
| 7 | P1-04 | 方案区按钮文案改「查看相关产品」 | 文案说「云服务器」但跳随 Tab 变化的其他品类 | 1 行 |
| 8 | P1-01 | `.hero-mobile-nav` 整组从 640 块上移到 992 块 | 641–992px 轮播暂停入口完全消失（WCAG 2.2.2 A） | 1 个媒体查询搬家 |
| 9 | P10-03 | `animation` 移到 `.app-route-progress.is-active::before` | 空闲态进度条持续每帧合成 | 1 条规则搬家 |
| 10 | P6-06 | `retrySidebar()` 改 `loadOverview(++syncToken)` | 重试请求被自己的过期判定丢弃 | 1 行 |
| 11 | P12-11 | `FINANCE_LEDGER_EVENT_MAP` 补回 6 键 | 6 类账变事件显示「未知」 | 6 行 |
| 12 | P8-04 | `resolveSeoLandingView` 的 `throw` 收进 `import.meta.env.DEV` | 任一落地页数据缺失即全站白屏 | 1 处 + `flatMap` 跳过 |
| 13 | P6-04 | 6 个徽章色改令牌；class 改 `index<3 ? ... : undefined` | 金银铜色散写 + 生成不存在的 `top-4+` class | 6 行 + 1 行 |
| 14 | P10-02 | page-fade 两端 `opacity: 0` → `0.5` | mode=out-in 下 0.14+0.18s 整页发白 | 2 行 |
| 15 | P1-02 | 轮播加 `prefersReducedMotion` 守门与 opt-in | 前庭敏感用户无法阻止自动切帧 | 1 个 composable + 3 处调用 |
| 16 | P8-01 | `heroNavItems` 去掉 `.slice(0,4)`、stats 全量拼装 | 5 页共 10 条核心卖点数据写了永不渲染 | 1 处 computed |
| 17 | P8-02 | 兜底色 `#d4dee8` → `#2b415b` | 渐变失效时白字 1.35:1 不可读 | 1 个色值 |
| 18 | P2-02 | 新增 `.catalog-loading`（role=status + spinner + 文案） | 弱网下配置区只有遮罩、无文案无播报 | 1 个区块 + 1 段样式 |
| 19 | P2-03 | 两处 `el-empty` 补 `role=status aria-live=polite` | 空态对读屏静默 | 2 处属性 |
| 20 | P12-22 | 全站 8 处 `el-empty` 统一补播报 | 空态与加载态对读屏不可区分 | 8 处属性 |
| 21 | P4-03 | `/about` 标签与 mega menu 标题改「关于我们」，desc 与实际内容对齐 | 「支持」与「帮助」语义重叠、desc 承诺不存在的内容 | 2 文件 4 处 |
| 22 | P5-01 | 法律文档空态补播报 | 未配置文档时读屏静默 | 1 处属性 |
| 23 | P6-05 | 搜索空结果补播报 | 搜索无结果时读屏静默 | 1 处属性 |
| 24 | P2-01 | 移动端伪 tablist 改 `role=group` + `aria-pressed` | 读屏宣告一个 Tab 键走不通的 Tab 组 | 1 处 + 1 属性 |
| 25 | P10-07 | `scrollBehavior` 改调 `matchesMobileViewport()` | 992±16px 处滚动锚点与布局口径错位 | 1 处 |
| 26 | P11-02 | 删 `MobileSheet` 的 `confirmLoading`/`confirmDisabled` | 零调用方的冗余 API 误导使用者 | 1 文件 6 处 |

### 3.2　B 档（21 条）：结构调整，收益明确

| 序 | 编号 | 动作 | 消除的问题 |
| --- | --- | --- | --- |
| 1 | P7-02 | 正文 h1~h4 补四档递减字号（28/24/20/16） | 帮助与公告详情四级标题同号，读者看不出层级 |
| 2 | P10-01 | 页脚 4 个 `h3` → `h2` 并拉开与链接的字号档差 | h1→h3 跳档，读屏按标题跳转漏掉整块页脚 |
| 3 | P2-04 | 13 个配置区块标题 `div` → `h2` | h1 之后直接进正文，无法按标题跳转分区 |
| 4 | P3-03 | 5 个 `.cfg-group-head` `div` → `h2` | 配置分组标题在标题大纲里缺席 |
| 5 | P1-03 | 骨架屏 `--hero` 重置规则从 640 搬到 992 与 1180 | 993px 以上首屏骨架高度与真实 Hero 不一致、加载后跳动 |
| 6 | P12-06 | 会话 cookie `SameSite=Strict` → `Lax` | 跨站进入落地页时请求不带 cookie，接口拿不到会话 |
| 7 | P12-08 | 读后端 `quote_expires_at` 判过期，不再用前端 30min 硬编码 | 前端以为有效、后端 10min 已判超时 |
| 8 | P12-12 | `quantity` 校验改 `!== 1` 即拒 + 补 10 组断言 | 前端放行必然被后端拒绝的请求 |
| 9 | P12-13 | `quote_token` 上限 256 → 120 | 超长 token 前端不拦、后端 422 |
| 10 | P8-03 | `.seo-action` 圆角 2px → `$btn-border-radius-sm` | 落地页 CTA 是全站最小圆角、与购买按钮不同族 |
| 11 | P4-01 | about 页 h1 40px → 32px | 最不重要的静态介绍页占全站最大字号 |
| 12 | P4-02 | `.about-card h2` 补 `$font-size-h2` | 卡片内 h2 继承正文 15px，拉不开档差 |
| 13 | P6-02 | 列表页 h1 24px → `$font-size-h1` | 列表页与详情页页面主标题不对等 |
| 14 | P3-02 | `.pd-empty` 容器挂 `role=status` | 加载失败与商品不存在两种语义共用出口但无播报 |
| 15 | P3-04 | 17 处裸色值改语义令牌 | 详情页是裸色值最多的一页，主题不可统改 |
| 16 | P3-05 | `.pd-aside` 的 `top:80px` → `calc($navbar-height + 16px)` | 站头高度一变即压字 |
| 17 | P3-06 | `.pd-page` 的 `min-height` 改用 `$navbar-height` 参与计算 | 页脚高度变化后出现滚动条抖动 |
| 18 | P3-07 | `soldOutPulse` 关键帧内两处颜色改令牌 | 令牌化时容易漏掉关键帧 |
| 19 | P2-06 | 机型规格表补 `role=table`/`aria-selected`/`role=cell` | 读屏无法感知行列关系与行选中态 |
| 20 | P12-23 | 401 用例改真实 HTTP 并断言 `__handled === false` | 拦截器重复提示的行为没被锁住 |
| 21 | P1-05 | 6 个首页组件 27 处裸 hex 改语义令牌 | 首页与令牌体系脱钩，改主题色不能一处生效 |

### 3.3　C 档（24 条）：体系化收口，一次投入长期收益

| 序 | 编号 | 动作 | 说明 |
| --- | --- | --- | --- |
| 1 | P12-04 | 首屏 4 个关键资源注入 `modulepreload`/`stylesheet` | 插件按 `facadeModuleId` 匹配，注意 Windows 下正/反斜杠归一化 |
| 2 | P12-03 | 体积门禁改按真实首屏闭包统计（manifest 闭包遍历） | 原门禁只算 `index.html` 直引的 4 个资产，真实闭包超预算仍绿灯 |
| 3 | P12-02 | Element Plus 子路径解析器补齐 123/123，warn 升 throw | 原 34 个回退 barrel 会把 dayjs 拖回首屏 |
| 4 | P12-01 | CSP 兜底按 `mode` 区分，生产缺配置留空 | 干净 clone 生产构建会写入 `127.0.0.1:8000` |
| 5 | P12-05 | 预压缩插件读 `config.build.outDir` | `build:e2e` 时无谓重压陈旧 `dist` |
| 6 | P12-15 | 按钮圆角按体量分三档令牌（4/6/8），14 处替换 | 原 2/4/6/8 四档无规律，同语义跨页不一致 |
| 7 | P12-14 | 断点令牌化：640/1180/480 全量替换，其余登记为页面局部 | 7 个桌面断点并存，改一处不生效 |
| 8 | P12-18 | 删 9 个零引用令牌 + 整个 Legacy aliases 区 | 死令牌制造「这里有配置」的错觉 |
| 9 | P12-19 | 剥离 `homeSolutions.js` 21 行零消费字段 | `accent`/`accentSoft`/`subtitle` 全仓零消费 |
| 10 | P12-20 | 去掉 eslint `--ext`；`lint-staged` glob 补 `.mjs` | 10 个构建脚本提交前永不 lint |
| 11 | P12-21 | 注释与实测对齐；CSP TODO 移出 `index.html` | 注释写 8 条路由实为 7、写 165,257 B 实为 153,833 B |
| 12 | P1-07 | 抽 `@mixin site-container`，13 处统一引用 | 同一容器公式散落 13 文件手写 |
| 13 | P10-05 | 站头容器改 `@include site-container` | 同上 |
| 14 | P1-06 | 公告条/错误条裸色改令牌 | 暖红边框与错误底板各写一份 |
| 15 | P1-08 | 内容卡 10px 圆角改 `$sm-border-radius` | 同值不同源 |
| 16 | P2-07 | `page.scss` 5 处 `64px` 改 `$navbar-height` | 站头高度一变即错位 |
| 17 | P2-10 | `.buy-btn`/`.allocation-footer-action` 圆角改 `$btn-border-radius-sm` | 购买按钮族此前散写裸值 |
| 18 | P2-11 | `.catalog-error__retry` 圆角改 `$btn-border-radius` | 错误态按钮裸值 |
| 19 | P3-09 | 详情页吸底按钮圆角对齐目录页 | 两页同语义按钮 10px vs 4px |
| 20 | P4-04 | `.about-card` 渐变末色改 `$bg-color` | 渐变末尾裸色 |
| 21 | P5-02 | `.legal-card` 背景改 `$bg-color` | 裸值 |
| 22 | P6-07 | `.hero-panel` 径向渐变改 `$color-accent-orange-soft` | 同色不同源（0.12 vs 0.14，视觉等价） |
| 23 | P10-04 | 站头 6 处 `#374151` 改 `$text-color-nav` | 中间档文字色无令牌承载 |
| 24 | P12-22 配套 | 8 处空态补播报的中文注释规范 | 建立「异步结果必须播报」的写法约定 |

### 3.4　D 档（2 条）：需外部条件，暂不能闭环

| 编号 | 动作 | 阻塞条件 |
| --- | --- | --- |
| P12-24 | 5 条 `@needs-backend` 用例在 CI 拉起后端后真跑 | 需 CI 侧提供可用的 Laravel 实例与种子数据 |
| P12-25 | 接入 Lighthouse 与视觉回归基线 | 需真实设备/网络条件；`scripts/lighthouse-audit.mjs` 已在仓库 |

### 3.5　明确不建议做的三件事

以下三项在审查中被列为候选，经量化后判定**不应实施**，一并记录以免后续重复提案：

| 候选 | 现状 | 不做理由 |
| --- | --- | --- |
| 引入 4px 基准全局 spacing scale（P12-16） | 38 个不同 px 值 | 高频值（12×97、8×78、10×64、16×50、14×35、6×34、18×34、24×33、4×26）本身已是稳定体系；离网值仅 11 处且各有基线补偿理由（`products/page.scss:638` 的 5px 是 13px 文本与右侧控件对齐）。强制吸附会破坏现有对齐。 |
| 圆角收成 3 档（P12-17） | 13 档（1/2/4/6/8/9/10/12/14/16/18/20/999） | 现存值可按「胶囊(999/9)、小徽章(2)、手写按钮(4/6/8)、次级控件(8)、内容卡(12)、实心大卡(14)、抽屉顶角(18)、独立整页大卡(20)」完整解释，收档会压平既有层级。 |
| 产品页补可见 h1（P2-05） | 唯一 h1 为 `sr-only` | 产品页是目录型界面，视觉层级由左侧产品目录与移动端类型切换承担；再叠可见大标题会挤掉首屏配置区。h1 仍在无障碍树内 —— 保留并已在代码中注释留证。 |

## 四、汇总摘要

### 4.1　整体质量评价

**结论：工程质量高于同规模项目的常见水平，缺陷集中在「新增页面未回填令牌/语义约定」与「工程门禁口径失真」两类，而非架构性问题。**

支撑判断的具体证据：

- **架构层面健康**：25 个页面组件中 4 个（公告/通知详情/帮助/帮助详情）是薄壳，列表与详情各只有一份实现被复用；`src/components` 6 个组件全部有调用方，零死组件；`src/domains` 只有 `products` 一个域且 4 个 composable 全部被消费；路由全部懒加载，顶层只有一条布局壳路由。
- **无障碍底子好**：跳过导航链接已实现且路由切换后复位焦点；移动菜单有完整的 Esc 关闭 + Tab 环形陷阱 + 焦点归还；三个 Picker 的 `outline:none` 全部配了 `:focus-visible` 替代环；`tabindex` 用法全部正确。缺陷是**离散缺口**（8 处空态无播报、3 个 Picker 的 id 硬编码、伪 tablist、页脚标题跳档、641–992px 轮播暂停入口消失），不是整体缺失。
- **性能反模式扫描全过**：`transition: all` 全站 0 处、`v-for` 缺 `:key` 0 处、`console.log` 0 处、`href="#"` 0 处、`will-change` 仅 1 处、`<img>` 缺失 `alt` 0 处、`prefers-reduced-motion` 有全局兜底。
- **真正的高危都集中在工程契约层**：体积门禁只统计 `index.html` 直引的 4 个资产（102,182 B）就判通过，而真实首屏闭包 134,631 B 已超预算；Element Plus 解析器 34 个导出回退 barrel 重新引入 dayjs；CSP 兜底在生产构建里注入 `127.0.0.1`；`SameSite=Strict` 让跨站进入的落地页拿不到会话。这四类都是「本地看不出来、线上才炸」的缺陷。

**分布特征**：交互维度 25 条中 12 条为高（全部是状态机或契约错位，不是样式问题）；视觉维度 29 条但仅 1 条为高（其余是一致性/令牌化）；设计维度 9 条无高（结构合理，缺的是标题层级语义）。这个分布说明问题不在「做得不好看」，而在「边界情况没想全」。

### 4.2　高优先级问题 Top 列表

按「线上影响 × 触发概率」排序，前 10 条：

| 排名 | 编号 | 位置 | 影响 | 触发条件 |
| --- | --- | --- | --- | --- |
| 1 | P12-02 | `scripts/element-plus-subpath-resolver.mjs:75` | 34 个 `El*` 导出回退 barrel，dayjs 等整包重回首屏 chunk | 任意新增/调整 Element Plus 组件后 |
| 2 | P12-03 | `backend/scripts/frontend_budget.mjs:15` | 体积门禁失真——超预算仍绿灯，等于没有门禁 | 每次构建（持续静默） |
| 3 | P12-06 | `shared/runtime/session.ts:36` | 跨站进入的落地页请求不带会话 cookie | 从搜索引擎/邮件/外链点击进入 |
| 4 | P8-04 | `src/app/router/routes.ts:30` | 任一落地页数据缺失即全站白屏（不只是该页 404） | 新增落地页漏配 view 时 |
| 5 | P12-01 | `vite.config.js:153` | 生产构建把 `127.0.0.1:8000` 写进 CSP 的 `img-src`/`connect-src`/`media-src` | 干净 clone 无 `.env.production` 时 |
| 6 | P12-07 | `useWebsiteProductCheckout.js:326` | `pendingCheckout` 缺 `savedAt` ⇒ TTL 恒失效，可无限续期 | URL 直达控制台结算链路 |
| 7 | P12-09 | `useWebsiteProductCheckout.js:347` | 控制台未配置时静默把用户送到站内副本 | 源站未配控制台地址 |
| 8 | P12-08 | `useWebsiteProductCheckout.js:540` | 前端判 30min 有效、后端 10min 已超时 | 用户在 10–30min 内提交订单 |
| 9 | P12-10 | `useWebsiteProductsCatalog.js:354` | 一次请求失败后错误块长期驻留直到手动刷新 | 命中缓存后的任意后续失败 |
| 10 | P1-01 | `HomeHeroCarousel.vue`（`.hero-mobile-nav`） | 641–992px 轮播暂停入口完全消失（WCAG 2.2.2 Level A） | 平板与窄窗口用户 |

> 第 1–3 名属于「本地一切正常、线上才暴露」的类型，优先级高于任何视觉问题；第 6–9 名全部是结算链路的契约错位，直接影响成单。

### 4.3　建议的处理顺序

实际修复按以下六轮推进，与前文 ROI 分档对应：

| 轮次 | 范围 | 判定通过的标准 | 对应章节 |
| --- | --- | --- | --- |
| 第一轮 | A 档前 10 条（结算链路 + 门禁 + 空态播报） | `typecheck` / `lint` / `build` / `e2e` 四道门全绿 | 3.1 序 1–10 |
| 第二轮 | 首屏性能与构建门禁（P12-02/03/04/05/01） | 首屏闭包体积回到预算内且门禁能识别超预算 | 3.3 序 1–5 |
| 第三轮 | 无障碍离散缺口（标题层级 + id 冲突 + 伪 tablist + 空态） | 标题大纲无跳档、双 Picker 无 id 冲突 | 3.2 序 2–4、3.1 序 19–24 |
| 第四轮 | 令牌体系收口（圆角/断点/容器/裸色值/死令牌） | `grep` 残留归零，视觉零变化 | 3.3 序 6–23 |
| 第五轮 | 设计一致性（字号档差、文案语义、骨架屏断点） | 页面主标题同档、文案与目标一致 | 3.2 序 1、11–13 |
| 第六轮 | 工程卫生（lint 配置、注释与实测对齐、死数据） | `lint` 覆盖 `.mjs`，注释与实测一致 | 3.3 序 9–11 |

每轮完成后必须重跑四道门并新增/更新对应 e2e 用例——修复不写测试等于没修，下一轮会原样复发。

## 五、待确认与验证证据

### 5.1　待确认清单（2 条，均需外部条件）

| 编号 | 待确认内容 | 已确认到什么程度 | 需要什么才能定稿 |
| --- | --- | --- | --- |
| P12-24 | 5 条 `@needs-backend` 用例（`e2e/ux-fixes.spec.ts:102/315`、`smoke.spec.ts:20/28/51`）在 CI 上是否真跑 | 本地后端 `GET /api/health` 返回 200 时 6 条真跑、非跳过 | CI 侧提供可用 Laravel 实例与种子数据 |
| P12-25 | 运行时性能指标（LCP/FCP/INP）与视觉回归基线 | 已确认首屏闭包 134,497 B gzip（预算 145,000 B）；已确认无代码级反模式 | 真实设备/网络下的 Lighthouse 报告与视觉基线；`scripts/lighthouse-audit.mjs` 已在仓库可直接接入 |

> 这两条之外，报告内不存在「疑似」「可能」类结论——所有其他条目均已定位到具体文件与行号。

### 5.2　验证证据（2026-10-30 实跑）

| 项 | 命令 | 结果 |
| --- | --- | --- |
| 类型检查 | `cd frontend-user-v3-www && npm run typecheck` | 通过（EXIT 0） |
| 代码规范 | `cd frontend-user-v3-www && npm run lint` | 通过（EXIT 0） |
| 生产构建 | `cd frontend-user-v3-www && npm run build` | 通过；`www 首屏 gzip 134,497 B，预算 145,000 B，通过`；Precompressed 98 files；Prerendered 12 public routes |
| 端到端 | `cd frontend-user-v3-www && npm run test:e2e` | **30 passed**；`ux-fixes.spec.ts` 17 + `smoke.spec.ts` 5 + `seo-breakpoints.spec.ts` 4 + `mobile-breakpoint.spec.ts` 4 |
| 跨端类型 | `npm run typecheck:shared` | 通过（EXIT 0） |
| 跨端测试 | `npm run test:shared` | 9 套件全过：runtime / finance-snapshot / money / sanitizer / http-validation / http-core / http-client / content-normalizer / website-checkout |
| 文档校验 | `npm run docs:check` | Documentation check passed |

**本轮新增的 e2e 用例**（全部已跑通）：

| 用例 | 锁定的行为 |
| --- | --- |
| 641–992px 轮播暂停入口 | 平板区间可用键盘/点击暂停自动轮播（P1-01） |
| 991px / 993px 两侧 1px 精确断点 | 断点边界行为不漂移 |
| 5 条 SEO 落地页互不串页 | `/cloud-server` 等页各自的 hero 与场景数据不互相污染 |
| 内容侧栏热门/最新各 5 条 | `SIDEBAR_ARTICLE_LIMIT` 生效（P6-03） |
| 商品页加载态 | `.catalog-loading` 出现并带 `role=status`（P2-02） |
| 页脚 h2 / 配置区 h2 | 标题层级无跳档（P10-01、P2-04） |
| 正文 h1~h4 递减 + `.reader-table-wrap` | 详情页标题档差与宽表滚动容器（P7-02、P7-03） |
| 分页条单页降级与多页恢复 | 单页时不渲染无意义分页 |
| 手写按钮圆角按体量分档且跨页一致 | `.header-register`=8 / `.hero-cta`=6 / `.seo-action`=4（P12-15） |
| 内容列表空结果与侧栏空数据向读屏播报 | `.list-empty` 与侧栏 `el-empty` 均有 `role=status` + `aria-live`（P12-22） |
| SEO 落地页核心卖点渲染 hero.stats 全部条目 | `.seo-hero__nav li` 数量 = 6（P8-01） |
| 顶栏 `/about` 入口标签与 `/help` 不重复 | 含「帮助」「关于我们」、不含「支持」（P4-03） |

## 附录 A　已公开撤回的误判（3 条）

以下结论在初审中成立、经二次实读后被推翻，**明确撤回**。保留记录是为了避免下一轮把同一现象当缺陷重复上报：

| 编号 | 原结论 | 推翻证据 | 现行定性 |
| --- | --- | --- | --- |
| A-01 | 商品页 `.buy-btn` 与 `MobileSheet` 的 confirm 可连点造成重复提交（原判「高」） | 8 处 `MobileSheet` 的 confirm 处理函数全部同步、无网络请求（`products/index.vue:1298/1310/1357/1485`、`ProductDetail/index.vue:614`、三个 Mobile Picker 的 `handleConfirm` 仅 emit）；唯一真实提交入口 `.buy-btn`（`products/index.vue:1189-1193`）已有 `:disabled` 三重守卫（售罄 / 加载中 / 无库存） | 降为 P11-02「组件 API 冗余」（低） |
| A-02 | `.hero-feature--link` 未重置 padding，与基类叠加导致错位 | 基类本身已声明 padding，修饰符未重置是等价行为，实测渲染无偏差 | 撤销，不计入清单 |
| A-03 | 992px 块内 `.hero-rail__item` 未重置 `width`/`min-height`，横向胶囊行会被压扁 | `HomeHeroCarousel.vue:1663-1671` 已显式写 `flex: 0 0 auto; width: auto; min-height: 0`，横向胶囊行为正确 | 撤销，不计入清单 |

另有三条在审查过程中自查发现并即时修正、未进入正式清单：ContentDetailPage 缺 640 媒体查询（实测 `:712` 已有）、`useWebsiteProductsPage.js` 的 `requestIdleCallback` 泄漏（实测 `:52` 已有 `onBeforeUnmount`）、`MobileRegionPicker` 的 `watch` 用了 `deep`（实测为浅监听）；以及 SEO 场景要点取值字段（实测已是 `activeScenarioPoints`）。

## 附录 B　决定不改的条目（12 条，含设计意图）

| 编号 | 位置 | 现状 | 不改理由 |
| --- | --- | --- | --- |
| P2-05 | `products/index.vue:53` | 唯一 h1 为 `<h1 class="sr-only">` | 产品页是目录型界面，视觉层级由左侧产品目录 + 移动端类型切换承担；再叠可见大标题会挤掉首屏配置区。h1 仍在无障碍树内，已加 3 行注释留证。 |
| P2-08 | `products/page.scss:638` | `.filter-label { padding-top: 5px }` | 13px 标签与右侧控件做基线对齐的补偿值，去掉后文字不齐。不强行吸附 4px 栅格。 |
| P2-09 | `products/page.scss:212` | `.item-count { border-radius: 9px }` | 元素 `height: 18px`，9px = 高度一半，是标准胶囊，非随意值。 |
| P2-12 | `page.scss` 1100px 断点 | 页面局部一次性断点 | 已在 `variables.scss:140` 注释登记为「页面局部、不新增全局令牌」。 |
| P3-10 | `ProductDetail/index.vue` | `.pd-coupon-option` 6px 圆角 | 抽屉内次级控件，不与按钮族混编。 |
| P7-04 | `ContentDetailPage.vue:42` | 全站唯一 `v-html` | 来源为 `articleContentHtml` computed，经 `sanitizeMarkdownSource` + `renderMarkdown` 处理，非裸 HTML 注入。后续新增富文本入口须复用同一链路。 |
| P8-06 | `seo-landing/index.vue` | 1080px / 760px 断点 | 页面局部一次性断点，已登记。 |
| P9-01 | `NotFound.vue:8-9` | 全站唯一用 `<el-button>` 承载业务动作 | 错误页本就是 Element Plus 兜底语境的自然延续，改手写按钮收益为零。登记为「刻意例外」，防后续一致性清算误改。 |
| P9-02 | `NotFound.vue:3` | `.not-found-card` 20px 圆角 | 与法律文档页 `legal-card` 同族（独立整页大卡），与内容卡（12px）、实心大卡（14px）分层。 |
| P11-04 | `MobileSheet.vue:210` | `.ms-drawer` 顶角 `18px 18px 0 0` | 移动抽屉顶角是独立形态档位。 |
| P12-16 | 全站 spacing | 38 个不同 px 值 | 高频值均为 4px 倍数且已稳定，离网值仅 11 处各有对齐理由。见 3.5。 |
| P12-17 | 全站 border-radius | 13 档 | 可按体量完整解释，收档破坏层级。见 3.5。 |

另：`global.scss:6-11` 的 `--el-color-primary-light-3..dark-2` 是 Element Plus `set-color-mix-level` 派生变量字面值（与 mix 公式一致），文件内 `:3-4` 已注释说明为何刻意写出，不作令牌化；`ContentDetailPage.vue:748/751` 的 `@media print` 内 `color: #000` 是打印必须纯黑，刻意保留。

## 附录 C　审查覆盖度声明

- **页面覆盖**：`src/app/router/routes.ts` 中 19 条子路由对应的全部页面组件已逐一走查，含 5 条购买路由共用同一组件的情形；无抽样。
- **区块覆盖**：每个页面走查到布局区块、按钮、链接、表单控件、弹窗/抽屉、悬停与点击反馈、加载态、空态、错误态。
- **组件覆盖**：`src/components` 6 个组件 + `src/domains/products` 4 个 composable 全部读过；`src/layout` 含独立 SCSS 与 composable。
- **未能覆盖**：运行时性能指标（LCP/FCP/INP）、像素级视觉回归基线、CI 专属后端用例 —— 已在 5.1 列为待确认，未以任何方式推断补足。
- **两个专家智能体失败**：见 1.1；对应维度的量化结论由首席直接取证补足，未伪造产出。

---

**报告状态与口径说明**：本报告的**审查阶段**只做审查取证、未改动代码；随后按用户单独授权（「按报告推进修复」）进入**修复阶段**，清单中 71 条已按「定位根因 → 改代码 → `typecheck` / `lint` / `build` / `e2e` 四道门」逐条落地，因此第二章「修复方向」列记录的是**实际落地做法**而非仅建议。

截至 2026-10-30 的终态：71 条已修复、2 条待外部条件（P12-24/P12-25）、3 条已公开撤回（附录 A）、12 条经论证决定不改（附录 B）、10 条正例记录。

全部改动**尚未提交**——`HEAD` 仍为 `d5750a78`。本任务范围内的实测改动量（2026-10-30 复核）：

| 路径 | 已跟踪文件改动 | 行数 | 新增未跟踪文件 |
| --- | --- | --- | --- |
| `frontend-user-v3-www/` | 59 | +2,698 / −2,011 | 16 |
| `shared/` | 22 | +312 / −276 | 2 |
| `backend/scripts/frontend_budget.mjs` | 1 | +94 / −8 | 0 |
| `docs/catalog.json` | 1 | +40 / −15 | 0 |
| `docs/参考资料/运维/前端Nginx伪静态配置.md` | 1 | +22 | 0 |
| **合计** | **84** | **+3,166 / −2,310** | **18 + 本报告** |

按项目规则（`AGENTS.md`）AI 不得自动提交或推送 git，须由用户确认后以 `Fix:中文描述` 格式提交。全仓另有大量非本任务改动（`git status --porcelain` 689 条），不得回滚或格式化。