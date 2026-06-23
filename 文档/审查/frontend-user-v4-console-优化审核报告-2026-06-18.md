# frontend-user-v4-console 优化审核报告

> 审核对象：`frontend-user-v4-console`（Vue 3 + Vite 6 + TypeScript + TDesign Vue Next 用户控制台，路由 `/client/*`）
> 审核维度：页面性能 / UI 与样式系统 / 组件统一化（不重复造轮子）/ PC·平板·手机多端适配
> 审核日期：2026-06-18 ｜ 代码规模：49 个 `.vue` + 71 个 `.ts`，`src` 约 24,600 行
> 方法：静态走读全部 22 个 `client` 页面 + 4 个 `client-auth` 页面 + 布局壳 + `domains` 组合式 + 构建产物 `dist`，所有结论均标注 `文件:行号` 证据

---

## 一、整体结论

这套控制台的**工程底座是健康的**：路由按页懒加载、vendor 手动分包合理、请求层已内置去重/重试/取消、大数据载荷普遍用 `shallowRef`、markdown-it 为模块级单例、图标统一走 `tdesign-icons-vue-next`、无 emoji 充当功能图标、设计令牌（`theme.css`）+ 暗色模式基础设施完整。这些不需要返工。

真正的问题集中在四个层面，按投入产出排序：

1. **TDesign 全量注册**（`main.ts:21` `app.use(TDesign)`）——这是体积和首屏解析的头号杠杆，单点改造即可让 vendor 包砍掉一半以上。
2. **共享组件库形同虚设**——`shared/user-v3/components` 暴露 9 个组件，实际只有 `DataState` 被 8 个页面使用，`PageScaffold / DetailScaffold / StatusTag / AppDialog / AppDrawer / ResponsiveActionBar / MotionWrapper / SideNavShell` 全部 0 引用。页面各自手搓状态标签、列表脚手架、弹窗，违反 `AGENTS.md` 的复用要求。
3. **样式硬编码与令牌系统脱节**——以 `services/index.vue` 为最，30+ 处硬编码 hex 颜色构成"第二套调色板"，且与品牌蓝冲突、暗色模式直接失效。
4. **响应式三端只做了两端**——手机（<768px）有抽屉式侧边栏，PC 正常，但**平板（768–1024px）落入"小桌面"**：仍是完整侧边栏；同时 `useDeviceLayout` 组合式是死代码（0 页面引用），JS 断点与 CSS 断点（散落 8 个非系统值）各行其是。

下面给出**严重度分级**（P0 必改 / P1 应改 / P2 可改）与可落地的改造项。

### 严重度速览

| 维度 | P0 | P1 | P2 |
|---|---|---|---|
| 性能 | TDesign 全量注册（体积/解析） | 充值汇总请求扇出、qrcode 静态引入、keep-alive 无上限 + 定时器泄漏、发票 1s 定时器重渲染、服务列表逐行计算 | console detail 改 shallowRef、批量未读数、lodash-es、监控悬浮节流、拆大组件 |
| 组件统一 | StatusTag 0 引用、列表脚手架复制 6 份、formatMoney 定义 5 次 | 弹窗/抽屉/加载态各搓一套、PageScaffold 未用、复制助手 5 份、日期格式化重复 | 死组件清理、InfoCell 提升共享 |
| UI/样式 | services 硬编码调色板、coupons 纯黑边框、services 状态用高饱和实色、硬编码致暗色模式破裂 | content-list 营销 Hero、字号字面量、裸 box-shadow、空态三套写法、裸 `<button>` | 死 radius 字面量、低对比正文 |
| 多端适配 | 无（无整类设备不可用） | 平板退化为桌面、Services 表格无移动兜底、8 个非系统断点、VNC 手机不可用、useDeviceLayout 死代码 | 双渲染 DOM 浪费、rem/px 断点families冲突、触控目标偏小 |

---

## 二、页面性能

### P0｜TDesign 全量注册，无按需引入（体积 / 首屏解析头号问题）

证据：[main.ts:3](src/main.ts#L3) `import TDesign from 'tdesign-vue-next'` + [main.ts:21](src/main.ts#L21) `app.use(TDesign)`，并在 [main.ts:12](src/main.ts#L12) 全量引入 `tdesign-vue-next/es/style/index.css`。

影响：注册了整库组件与全部 CSS，无视实际使用，构建产物 `vendor-tdesign` 达 **1.36MB 原始 / 272KB brotli**，`index` 入口 409KB。`vite.config.ts` 的 manualChunks 只能分包，无法摇掉未用组件，因为整库都被 app 实例引用。

改造：引入 `unplugin-vue-components` + `TDesignResolver`（自动按需引入组件 JS+CSS），随后删除 `app.use(TDesign)` 与全量 CSS；`MessagePlugin/DialogPlugin/NotifyPlugin` 这类命令式 API 保留直接 import（现有 composable 已是此写法）。预期 vendor-tdesign 砍掉 50–70%。**这是单点收益最高的改造。**

### P1｜充值汇总：分页拉全量 + 逐服务续费预览请求

证据：[useRecharge.ts:243](src/domains/finance/useRecharge.ts#L243) `do { … clientApi.services({ page, page_size }) … } while (collectedServices.length < total)` 串行翻页拉全部服务，再 [useRecharge.ts:264](src/domains/finance/useRecharge.ts#L264) 对每个临期服务发一次 `serviceRenewPreview`（仅按 4 个分块并发）。

影响：实例多的账号，仅为渲染两张汇总卡就要几十次串行往返。

改造：理想方案是后端加聚合端点直接返回 `renew_needed_7d`；短期把 `page_size` 调大一次取回、提高并发分块数。

### P1｜`qrcode.vue` 被静态引入 3 个路由分包

证据：[recharge/index.vue:104](src/pages/client/recharge/index.vue#L104)、[verification/index.vue:117](src/pages/client/verification/index.vue#L117)、[invoice-detail/index.vue:286](src/pages/client/invoice-detail/index.vue#L286) 均 `import QrcodeVue from 'qrcode.vue'`，但只在条件弹窗里用（如 [invoice-detail/index.vue:264](src/pages/client/invoice-detail/index.vue#L264) `v-if="alipayQrCode"`）。

影响：余额支付/不扫码的用户也要解析二维码库。

改造：三处都改 `const QrcodeVue = defineAsyncComponent(() => import('qrcode.vue'))`，真正渲染二维码时才下载。

### P1｜keep-alive 无上限缓存 + 定时器在缓存态下泄漏

证据：[layouts/components/Content.vue:6](src/layouts/components/Content.vue#L6) `<keep-alive :include="aliveViews">`，`aliveViews`（[Content.vue:36-46](src/layouts/components/Content.vue#L36-L46)）默认缓存每个打开的 Tab，且**无 `:max`**。叠加各页定时器只在卸载钩子清理：充值 [useRecharge.ts:332](src/domains/finance/useRecharge.ts#L332) `onBeforeUnmount`、发票倒计时 [invoice-detail/index.vue:497](src/pages/client/invoice-detail/index.vue#L497) `onBeforeUnmount`、控制台状态轮询 `onUnmounted`。

影响：keep-alive 下 `onUnmounted/onBeforeUnmount` **不会**在切 Tab 时触发——被缓存的充值页继续 2s 轮询支付状态、发票页 1s 倒计时持续跑、控制台状态同步存活，后台空耗 CPU 与网络；缓存还随 Tab 数无限增长。

改造：给 `<keep-alive>` 加 `:max="10"` LRU 上限；重/瞬时页（service-console、invoice-detail、recharge）设 `meta.keepAlive=false`；需要缓存的页用 `onActivated/onDeactivated` 暂停/恢复定时器。

### P1｜发票详情：1s 定时器触发整页模板函数重算

证据：[invoice-detail/index.vue:469](src/pages/client/invoice-detail/index.vue#L469) `setInterval(() => { now.value = Date.now() }, 1000)`；模板中直接调用普通函数 `isRenewInvoice(detail)`、`renewInfoItems(detail)`、`pricingItems(detail)`（[invoice-detail/index.vue:85](src/pages/client/invoice-detail/index.vue#L85) 与 :89 调了两次）、`productPath(detail)`。

影响：每秒 `now` 变更触发整模板重估，递归 `flattenSnapshot`（经 `pricingItems`）每秒跑两遍。

改造：把这些函数改 `computed` 记忆化；倒计时抽成独立子组件，让 tick 只重渲染倒计时文本。

### P1｜服务列表：v-for 内逐行重复计算，图标解析跑两次

证据：[services/index.vue:56-63](src/pages/client/services/index.vue#L56-L63) 卡片对每项调 `isProvisioningService(item)` 两次、`shouldShowServiceOsIcon(item)` 内部又重算 `resolveServiceOsIcon(item)`（解析跑两遍）；规格助手 [services/index.vue:110-112](src/pages/client/services/index.vue#L110-L112) `findListSpecValue` ×2。这些助手都在扫 `OS_ICON_MAP`/`specs` 数组。

影响：`page_size` 最大 50，每次响应式变更就是上百次数组扫描。

改造：建一个派生 `computed` 视图模型数组，把每个 `ServiceInstance` 映射成 `{ name, osIcon, cpu, mem, bw, provisioning, statusLabel }` 平铺给模板；或对卡片加 `v-memo="[item.id, item.status, item.remark, item.expires_at]"`。

### P2｜其余性能项

- **console `detail` 深响应式**：[useConsoleDetail.ts:24](src/domains/services/console/useConsoleDetail.ts#L24) `ref<ConsoleServiceDetail>`，~25 个 computed 读它，但更新总是整对象替换 → 改 `shallowRef` 即可去掉深代理开销。
- **dashboard 未读数串行**：[dashboard/index.vue:639](src/pages/client/dashboard/index.vue#L639) 9 路 `Promise.allSettled` 后又 [dashboard/index.vue:694](src/pages/client/dashboard/index.vue#L694) 串行 `fetchUnreadCount`，应并入批量。
- **`@novnc/novnc` 是死依赖**：`package.json` 有依赖、`vite.config.ts:74` 有 `vendor-vnc` 分包规则，但 `grep novnc src/` 无命中——实际 VNC 走 `public/vnc/` 静态资源 + iframe。可移除依赖与分包规则（移除前确认 iframe 目标）。
- **lodash 命名空间引入**：[useFrameKeepAlive.ts:1](src/layouts/frame/useFrameKeepAlive.ts#L1) `import { uniqBy } from 'lodash'`、[Content.vue:16](src/layouts/components/Content.vue#L16) `isBoolean/isUndefined`——改 `lodash-es` 或原生 `typeof` 以利摇树。
- **监控 SVG 悬浮重算**：[service-console/index.vue:218](src/pages/client/service-console/index.vue#L218) `@mousemove` 每次移动扫所有点，模板里 `resolveActiveMonitorPoint(chart)` 每图每帧调 4+ 次——用 `requestAnimationFrame` 节流 + computed 记忆。
- **大 SFC 拆分**：service-console（1905 行）、dashboard（1266 行）可把各 tab/图表抽子组件，纯维护性收益（运行时因 `v-if` 已按需渲染，非热点）。

---

## 三、组件统一化（不重复造轮子）

**核心事实**：`shared/user-v3/components/` 已提供 9 个组件，但采用率极低——`DataState` 被 8 个页面用，其余 **8 个 0 引用**：`PageScaffold / DetailScaffold / StatusTag / AppDialog / AppDrawer / ResponsiveActionBar / MotionWrapper / SideNavShell`。"轮子"造好了却没人用，页面各自手搓等价物，直接违反 `AGENTS.md`"复用 `shared/user-v3` 控制台基础组件"的硬性要求。

### 现成的"轮子"清单

| 共享组件 | 能力 | 当前引用数 |
|---|---|---|
| `DataState` | `loading→t-loading` / `empty→t-empty` / 否则插槽 | 8 ✅ |
| `StatusTag` | 由 `getStatusConfig(statusMap, status)` 推出 `theme`+`label` 的标准状态标签 | **0** |
| `PageScaffold` | 列表页头（title+description+`#actions`）+ body | **0** |
| `DetailScaffold` | 详情页头（eyebrow+title+`#actions`）+ body | **0** |
| `AppDialog` | `t-dialog` 封装（520px，confirm/close） | **0** |
| `AppDrawer` | `t-drawer` 封装（默认 420px） | **0** |
| `ResponsiveActionBar` | 响应式操作按钮容器 | **0** |
| `MotionWrapper` | `motion-fade` 过渡 | **0** |
| `SideNavShell` | 固定品牌+客户端导航 | **0** |

### P0｜StatusTag 全无人用，每个页面手搓 `<t-tag :theme="resolveXxx()">`

这是分布最广的重复。每个领域都自造一个"状态→主题"解析器：
- 发票 `resolveInvoiceTagTheme`（[useInvoices.ts:133](src/domains/finance/useInvoices.ts#L133)）用于 [invoices/index.vue:57](src/pages/client/invoices/index.vue#L57)/:81/:133
- 支付 `resolvePaymentTagTheme`（[useRecords.ts:219](src/domains/finance/useRecords.ts#L219)）
- 工单 `resolveTicketTagTheme`/`resolvePriorityTheme`（[useTickets.ts:61](src/domains/support/useTickets.ts#L61)）
- 服务 `resolveTdesignStatusTheme`（[useServiceCenter.ts:154](src/domains/services/useServiceCenter.ts#L154)）
- 优惠券 `resolveStatusTheme`（[useCoupons.ts:36](src/domains/marketing/useCoupons.ts#L36)）

这些解析器重新编码了 `StatusTag.vue` + `statusConfig.js` 已有的逻辑。尤其 `resolveTdesignTagTheme`（tagType→TDesign theme）在 [useInvoices.ts:137](src/domains/finance/useInvoices.ts#L137) 与 [useRecords.ts:231](src/domains/finance/useRecords.ts#L231) **逐字节重复定义两次**。

改造：`<t-tag :theme="resolveXTheme(s)">{{ resolveXLabel(s) }}</t-tag>` → `<StatusTag :status-map="INVOICE_STATUS_MAP" :status="row.status" />`，随后删除上述全部解析器。

### P0｜列表页脚手架复制 6 份

证据：`balance-logs / invoices / payments / tickets / services / coupons` 的模板骨架近乎一致——筛选 `t-card` 工具栏 → `DataState` → 桌面 `t-table` → 移动 `record-mobile-card` 循环 → `record-pagination`。其中 `t-pagination` 块在 [balance-logs:49](src/pages/client/balance-logs/index.vue#L49)、[invoices:109](src/pages/client/invoices/index.vue#L109)、[payments:64](src/pages/client/payments/index.vue#L64)、[services:230](src/pages/client/services/index.vue#L230)、[tickets:67](src/pages/client/tickets/index.vue#L67) **逐字节相同**（`:page-size-options="[10,20,50]" show-total @change @page-size-change`）。

改造：提升一个 `RecordListScaffold`（或 `DataTablePage`）共享组件，接收 `columns/list/total/filters`，提供 `#toolbar`、`#mobile-card` 插槽，内部封装 `DataState`+表格+分页。状态侧 `useRecordList`（[useRecords.ts:257](src/domains/finance/useRecords.ts#L257)）已统一，缺的是模板侧统一。

### P0｜`formatMoney` 定义 5 次（逻辑相同）

证据：[useRecords.ts:30](src/domains/finance/useRecords.ts#L30)、[useInvoices.ts:68](src/domains/finance/useInvoices.ts#L68)、[useRecharge.ts:22](src/domains/finance/useRecharge.ts#L22)、[useServiceCenter.ts:74](src/domains/services/useServiceCenter.ts#L74) 函数体一致，外加 [dashboard/index.vue:302](src/pages/client/dashboard/index.vue#L302) 一份变体，`useCoupons.ts:30` 还有近亲 `formatCouponAmount`。

改造：收敛到单一 `shared/format` 工具的 `formatMoney`，全局 import。

### P1｜弹窗/抽屉/加载态/页头各搓一套

- **裸 `<t-dialog>`**（应用 `AppDialog`）：verification、profile、referral、tickets、ticket-detail、services、service-console、invoice-detail 共 11 处。注意多数需要 `width`/`confirmBtn`，`AppDialog` 需先补这两个 prop 再迁移。
- **裸 `<t-drawer>`**（应用 `AppDrawer`）：[invoices/index.vue:120](src/pages/client/invoices/index.vue#L120)、[payments/index.vue:75](src/pages/client/payments/index.vue#L75)、coupons。
- **裸 `<t-loading>`**（应用 `DataState`）：详情页 [ticket-detail/index.vue:11](src/pages/client/ticket-detail/index.vue#L11)、[invoice-detail/index.vue:15](src/pages/client/invoice-detail/index.vue#L15) 等——列表页用 `DataState`、详情页却用裸 loading，不一致。
- **手搓页头**（应用 `PageScaffold`/`DetailScaffold`）：`client-page-heading` 在 [coupons:3](src/pages/client/coupons/index.vue#L3)、[profile:3](src/pages/client/profile/index.vue#L3)、[referral:3](src/pages/client/referral/index.vue#L3)、[tools:3](src/pages/client/tools/index.vue#L3) 重复，对应 CSS 也在 4 个文件复制。

### P1｜复制粘贴的工具函数

- **剪贴板复制助手 5 份**：[useProfile.ts:115](src/domains/account/useProfile.ts#L115)、[useConsoleCore.ts:154](src/domains/services/console/useConsoleCore.ts#L154)、[useServiceCenter.ts:443](src/domains/services/useServiceCenter.ts#L443)、[useRecharge.ts:301](src/domains/finance/useRecharge.ts#L301)、[useReferral.ts:147](src/domains/marketing/useReferral.ts#L147)，都是 `navigator.clipboard.writeText` + `MessagePlugin`，仅提示文案不同。收敛为 `copyText(text, { successMsg })`（以 `useConsoleCore` 含空值守卫的版本为基）。
- **日期格式化重复且分叉**：`formatDateTime`（[useRecords.ts:40](src/domains/finance/useRecords.ts#L40)）手动 pad、`formatDate`（dashboard:313）同法、`formatTicketTime`（useTickets）。项目已依赖 dayjs 却到处手动补零——收敛单一工具。

### P2｜死组件与可提升项

- `ResponsiveActionBar`/`MotionWrapper`/`SideNavShell` 0 引用：要么采用，要么从 barrel 移除以免误导"已复用"。`SideNavShell` 硬编码导航，与 `src/layouts/components/SideNav.vue` 冲突。
- `InfoCell`（[service-console/components/InfoCell.ts](src/pages/client/service-console/components/InfoCell.ts)）是通用 label/value/可复制单元，却被困在单页；payments（[payments:86-117](src/pages/client/payments/index.vue#L86-L117)）、invoices 详情都需要同款——应提升到 `shared/user-v3/components`。

---

## 四、UI 与样式系统

**令牌真源**：设计令牌不在 `src/style/`（`variables.less` 只有断点+动画），而在仓库根 [theme.css](../theme.css)（849 行），经 [src/style/index.less:3](src/style/index.less#L3) `@import '../../../theme.css'` 引入。品牌色被覆写为蓝色体系（`--td-brand-color-6: #006eff`），且 **radius 全局重置为 0**（`theme.css:222-228` 各 `--td-radius-*: 0px` + `:275` `#app *{ border-radius:0 !important }`）——即扁平直角是有意的设计语言。这点很重要：页面里那些 `0.5rem/0.75rem/1rem` 的 radius 字面量是**被全局强制拍平的死代码**。

> 建议：在 `src/style/` 加一行注释指向 `theme.css` 为令牌真源，避免后人误以为"没有令牌"而继续硬编码。

### P0｜services/index.vue 硬编码出一整套平行调色板

证据：[services/index.vue](src/pages/client/services/index.vue) 样式块有 30+ 处裸 hex/rgba：
- 三种互不相同的"品牌蓝"`#3978ff`（:498/:644/:733）、`#256dff`（:656），**都不等于**应用品牌 `#006eff`/`--td-brand-color`。
- 文本灰 `#19263d`（:584）、`#21314c`（:778）应为 `--td-text-color-primary`；`#7d8aa0`/`#7c8aa1`/`#8e9bb0`（:619/:638/:767）应为 `--td-text-color-secondary/placeholder`。
- 填充 `#f4f7fb`（:600）、边框 `#dbe3f0`（:665）/`#edf1f7`（:708）应为 `--td-bg-color-component` / `--td-component-stroke`。

影响：第二套手写调色板，与品牌蓝冲突、对令牌系统不可见、暗色模式直接失效。

改造：蓝→`var(--td-brand-color)`，文本灰→`var(--td-text-color-*)`，填充→`var(--td-bg-color-component)`，边框→`var(--td-component-stroke)`。

### P0｜coupons 用纯黑 `#000` 边框；服务卡片状态用高饱和实色文字

- **纯黑边框**：[coupons/index.vue:471](src/pages/client/coupons/index.vue#L471) 起 `border: 0.0625rem solid #000`（:486/:497/:509/:510）+ `#fff` 填充（:520/:525）。纯黑 1px 网格比 `--td-component-border`（`#cfd5de`）重得多，显生硬，`#fff` 破暗色。→ `var(--td-component-border)` + `var(--td-bg-color-container)`。
- **状态高饱和实色**：[services/index.vue:712-757](src/pages/client/services/index.vue#L712-L757) `.service-status-text` 用 `#22945f`/`#ff8a00`/`#d71457`/`#3978ff`/`#eb135c` 加 `font-weight:700` 实色文字+圆点（模板 :122-126）。**违反**`AGENTS.md`"状态用浅底标签、不用高饱和纯色文本"。更糟的是同页表格视图（:195 `t-tag variant="light"`）已正确用浅底标签——**同一页两种状态渲染**。→ 卡片视图改用 `<t-tag variant="light" :theme>`，与表格统一。

### P0（聚合）｜硬编码颜色导致暗色模式破裂

证据：暗色基础设施是完整可用的（[setting.ts:52](src/store/modules/setting.ts#L52) `setAttribute('theme-mode','dark')`，`theme.css:126-219` 全套 `:root[theme-mode='dark']` 调色板）。但上面所有 `#fff`/`#f4f7fb`/`#000`/`#19263d` 等字面量在 `theme-mode='dark'` 下纹丝不动——services 卡片、coupons 网格、tickets 移动卡、console tooltip 会在深色页面（`--td-bg-color-page:#090a0f`）上呈现白底深字，不可读。§四的令牌替换即可一并修复。

### P1｜其余 UI 项

- **content-list 营销 Hero**：[ContentListPage.vue:5-12](src/pages/client/content-list/ContentListPage.vue#L5-L12) `hero-card` 两列布局（标题+营销描述+关键词 chips+预留插画列，CSS :158-188）。控制台禁止官网式 Hero。→ 降级为 `client-page-heading`（标题+单行说明）。
- **字号字面量**：service-console 32 处、services 21 处裸 `font-size`（含 `0.6875rem`≈11px，低于系统最小 body 令牌）。→ `font: var(--td-font-body-small/medium)`。
- **裸 box-shadow**：services（:461/:467）、tickets（:276）三套自定义阴影配方，应统一到 `--td-shadow-1/2`（service-console:1361 已正确示范）。
- **空态三套写法**：`DataState` 包裹（balance-logs/invoices/payments/coupons）、裸 `t-empty`（catalog/content-list/dashboard/profile/referral/invoice-detail/service-console）、自定义 `coupon-product-empty-grid` 并存。→ 统一 `DataState`。
- **裸 `<button>` 当操作按钮**：[services/index.vue:98](src/pages/client/services/index.vue#L98)/:104 `<button class="service-action-console">` 自定高度/边框/颜色，其余页面都用 `t-button`。→ 改 `<t-button size="small" variant="text|outline">`。

### P2｜可改

- **死 radius 字面量**：services/service-console/invoice-detail 多处 radius 声明被全局 `border-radius:0!important` 拍平——纯死代码，删除即可（注意 service-console:1768/:1849 带 `!important` 的两处可能反胜全局重置，需核查是否产生意外圆角）。
- **低对比正文**：services 用 `#91a0b6`/`#8e9bb0` 于 `0.6875rem` 承载真实内容（服务 ID、备注、IP），对比度偏低。→ `--td-text-color-secondary` + `--td-font-body-small`。
- **theme.css 表格/菜单 chrome 仍是字面量浅色**（`:300` th 背景 `#f6f8fb`、`:578` hover `#f8fbff`），无暗色对应——这是共享文件层面问题，建议换 `--td-bg-color-secondarycontainer` 等令牌。
- **auth 页（login/register 等）**：`AuthShell.vue` 的装饰性渐变/玻璃面板**可接受**（规则允许登录页更有表现力，且它自带 light+dark 两套 `--auth-*` 变量），仅建议把标题色 `#2563eb` 挂到 `--td-brand-color`。

---

## 五、PC / 平板 / 手机多端适配

**前置事实**：`useDeviceLayout`（定义 `isMobile<768 / isTablet 768-1024 / isDesktop≥1024 / isWide≥1200`）被 **0 个页面引用**——是死代码。所有响应式由 CSS `@media` + 布局壳里少量 JS 驱动。手机端抽屉侧边栏等关键逻辑实际在仓库根 `theme.css`（610-849 行）里，只读 `src/` 会漏掉。

### ✅ 已做好：手机抽屉侧边栏

[SideNav.vue:82](src/layouts/components/SideNav.vue#L82) `MOBILE_POINT=768`，[SideNav.vue:87](src/layouts/components/SideNav.vue#L87) 按 `innerWidth<=768` 判定，遮罩+抽屉（`theme.css:636-661` `width:min(84vw,288px); transform:translateX(-100%)`，0.24s 滑出），汉堡触发在 [Header.vue:9-12](src/layouts/components/Header.vue#L9-L12)。<768px 的手机导航是可用的。

### P1｜平板（768–1024px）退化为"小桌面"

证据：`isMobile` 是唯一开关（阈值 768），全壳无平板分支。768–1024px 间固定 232px 侧边栏（[layout.less:213](src/style/layout.less#L213)）仍展开，内容区只剩 ~536px；`theme.css` 的 sider 收起规则只在 `max-width:768px` 生效。

影响：平板竖屏体验是"被压扁的桌面"，而非平板优化视图。`useDeviceLayout.isTablet` 定义了却无人消费。

改造：768–1024 区间把侧边栏收为 compact（64px）或把抽屉阈值上提到 1024，并由 `useDeviceLayout().isTablet` 统一驱动。

### P1｜列表页表格→卡片的适配策略不统一，Services 无移动兜底

三种策略并存：
- **双渲染 + CSS 切换**：invoices/payments/balance-logs/tickets 同时渲染 `t-table` 和移动卡片，靠 `record-page.less:274-282` 在 768px `display:none` 切换（功能正确但 DOM 渲染两份）。
- **用户手动切换（非设备驱动）**：[services/index.vue:38](src/pages/client/services/index.vue#L38) 按钮切 `viewMode` 网格/表格，**无自动移动兜底**——手机用户若处于表格模式，会得到横向溢出的 `t-table`（其媒体查询 :898-919 只重排筛选栏与卡片网格，从不隐藏表格）。
- **永远卡片**：coupons 768px 收 1 列。

影响：Services 页缺少其他列表页都有的"表格→卡片"保护；且所有表格页的切换都在 768px，平板竖屏仍是完整桌面表格、宽表溢出。

改造：统一用 `useDeviceLayout` 驱动；Services 在平板断点以下强制卡片视图，无视 `viewMode`。

### P1｜散落 8 个非系统断点，且 1024 这一档从未被用

证据：系统只定义 3 个业务断点（`variables.less:24-26`：768/1024/1200），但页面 `@media` 实际出现了 **420/480/640/896/900/960/1080/1152/1180** 等非系统值（如 dashboard:1190 `40rem`、profile:222 `56rem`、services:898 `67.5rem`、content-detail:257 `72rem`）。而平板/桌面分界 `@screen-md-rem:64rem`（1024px）**被 0 条 `@media` 引用**——每页都挑了个相近但不同的值。

此外 `variables.less` 同时提供 px 家族（`@screen-sm:768px`）和 rem 家族（`@screen-sm-rem:48rem`）：壳/auth 用 px 家族（`@screen-sm-max=991`），业务页用 rem 家族（768）。结果 769–991px 间，页头以为是手机（[Header.vue:396](src/layouts/components/Header.vue#L396) 隐藏品牌/余额），内容页以为是桌面——隐藏/重排错位。

改造：以 rem 家族为唯一真源（组合式已对齐），删除/别名 px 家族，把 8 个 ad-hoc 值各自吸附到 `@screen-sm-rem/@screen-md-rem/@screen-lg-rem`，并真正用上 1024 这一档。

### P1｜VNC 控制台在手机上不可用；useDeviceLayout 死代码

- **VNC iframe**：[service-console/index.vue:507-516](src/pages/client/service-console/index.vue#L507-L516) `.vnc-frame` 固定 `height:35rem`，手机仅缩到 26.25rem。noVNC 远程桌面塞进 ~360px 宽 iframe 实际不可用，远端桌面不会重排。→ 平板以下改为"全屏打开/新窗口打开"CTA（`handleOpenVnc('window')` 路径 :502 已存在），或手机端 iframe 用 `100dvh` 全屏覆盖层。
- **useDeviceLayout 死代码**：0 页面/0 布局组件引用，唯一 JS 响应式（侧边栏抽屉）反而绕开它，硬编码 `MOBILE_POINT=768` 两份（SideNav:82、Header:143），recharge:109 又自写 `matchMedia('(max-width: 48rem)')`。`isTablet/isDesktop/isWide` 三档从未落地。

### ✅ 重页适配良好（invoice-detail / dashboard）

invoice-detail（1473 行）所有多列网格 960px 收 1 列（:1385-1396）、粘性列解除、按钮 768px 全宽，无溢出；dashboard（1266 行）summary 4→2 列、图表→1 列、quick-grid 480px 步进，重排完整。仅断点取值未走系统（见 P1）。

### P2｜其余适配项

- **双渲染 DOM 浪费**：表格+卡片两套 `v-for` 始终都跑，长列表节点翻倍。列表增大后可用 `v-if="isMobile"` 单边渲染减半。
- **触控目标偏小**：移动卡片操作按钮 `record-page.less:292` 已 `width:100%`，但仍是 `size="small"`（高 ~24-28px，低于 ~44px 触控指引）。→ 移动端用默认/中号高度。
- **分页横向滚动**：移动端 `t-pagination` 选择 `overflow-x:auto`（record-page.less:269）而非换行，是移动端唯一刻意引入的横向滚动，可接受。
- **弹窗/抽屉已正确全宽**：各处 `t-dialog`/`t-drawer` 已用 `width="min(30rem, calc(100vw - 2rem))"` 响应式宽度，叠加 theme.css:684-803 的 768px 全宽强制，不溢出。仅 footer 用 `repeat(2,1fr)` 网格，1 个或 3 个按钮时布局略歪（次要）。

---

## 六、落地路线图（按投入产出排序）

### 第一批：高收益低风险（建议优先）

1. **TDesign 按需引入**（性能 P0）：接 `unplugin-vue-components`+`TDesignResolver`，删 `app.use(TDesign)` 与全量 CSS。预期 vendor 包 -50~70%。改动集中在 `main.ts`+`vite.config.ts`，风险可控。
2. **抽 `shared/format` 工具模块**（组件 P0）：`formatMoney`（5→1）、`formatDateTime`/`formatDate`（合并）、`copyText`（5→1）。纯增量、删重复，无视觉影响。
3. **采用 `StatusTag`**（组件 P0）：替换全部 `<t-tag :theme="resolveXxx()">`，删除 5+ 个领域解析器与重复的 `resolveTdesignTagTheme`。顺带修复 services 卡片状态高饱和实色（UI P0）。
4. **services/index.vue 令牌化**（UI P0）：30+ 处硬编码 hex 换 `--td-*`，一并修复暗色模式破裂与裸 `<button>`。这是 UI 最大热点文件，单文件改造收益显著。

### 第二批：结构性改造（需测试覆盖）

5. **`RecordListScaffold` 共享组件**（组件 P0）：收敛 6 份列表页脚手架（工具栏+DataState+表格+移动卡+分页），配 `#toolbar`/`#mobile-card` 插槽。
6. **keep-alive 治理 + 定时器生命周期**（性能 P1）：`:max="10"`、重页 `meta.keepAlive=false`、定时器迁到 `onActivated/onDeactivated`。修复后台空耗。
7. **统一断点系统**（适配 P1）：rem 家族为唯一真源，删 px 家族，8 个 ad-hoc 值吸附三档，启用 1024 平板档；`useDeviceLayout` 接入 SideNav/Header 替换硬编码 768。
8. **平板适配**（适配 P1）：768–1024 收起侧边栏；Services 平板以下强制卡片。

### 第三批：细节优化

9. qrcode 异步化、充值汇总请求收敛、console `detail` 改 shallowRef、监控悬浮节流（性能 P1/P2）。
10. 弹窗/抽屉/加载态/页头采用 `AppDialog`/`AppDrawer`/`DataState`/`PageScaffold`（组件 P1，`AppDialog` 需先补 `width`/`confirmBtn`）。
11. content-list Hero 降级、字号/阴影令牌化、空态统一、死 radius 清理（UI P1/P2）。
12. VNC 手机端全屏 CTA、移动触控目标增高、清死组件与 `@novnc` 死依赖（适配/性能 P2）。

### 验证要求（依 AGENTS.md §10）

改动后须执行 `npm run build`（含 `vue-tsc --noEmit`）；涉及重构收口范围再跑 `npm run verify:refactor`。建议每批改完先跑构建，再人工核验暗色模式与三端断点（768/1024/1200）下的关键页面。

---

## 七、值得肯定（无需返工）

- 路由按页懒加载（`router/modules/client.ts` 全 `() => import()`）。
- vendor 手动分包合理（axios/vue/tdesign/vnc/content 分离）。
- 请求层内置去重+重试+取消（`utils/request.ts:131/170/175`）。
- 大数据载荷普遍 `shallowRef`，无 `{deep:true}` 滥用。
- markdown-it 模块级单例、图标统一 `tdesign-icons-vue-next`、无 emoji 功能图标。
- 设计令牌 + 暗色模式基础设施完整（`theme.css` + `setting.ts`）。
- 构建侧已做 brotli/gzip 预压缩、资源分目录、网络提示注入（`vite.config.ts`）。
- 弹窗/抽屉已普遍用响应式 `min()` 宽度；手机抽屉侧边栏完整可用。
- invoice-detail / dashboard 等重页的多列网格重排完整。

> 一句话总结：**底座扎实，问题集中在"没用好已有的轮子"**——按需引入、激活共享组件库、令牌化硬编码、补齐平板档，四件事做完，性能、统一度、视觉一致性、多端体验会同时上一个台阶。
