---
status: current
updated: 2026-11-06
owner: frontend-platform
---

# 前端项目规范

- 读者画像：维护当前四个前端（三个 Vue 端与 Flutter App）与 `shared` 的开发者和代理

## 1. 当前前端项目

- `frontend-admin-v3`：管理端，Vue 3 + Vite + TypeScript + TDesign Vue Next。
- `frontend-user-v3-www`：官网与用户入口，Vue 3 + Vite + TDesign Vue Next。
- `frontend-user-v4-console`：新版用户控制台，Vue 3 + Vite + TypeScript + TDesign Vue Next。
- `app_flutter`：Android 原生 App，Flutter + TDesign Flutter，独立构建、不接入根 npm workspace。
- `shared`：跨端共享包，导出状态、runtime、content 和用户控制台基础组件。

旧路径 `frontend-admin`、`frontend-client`、`frontend-user-v3-console`、`frontend-www-v2`、`frontend-console-v2` 当前不存在，不要新增引用。完整废弃目录清单以 [AGENTS.md](../AGENTS.md) 为唯一真源（另含根 `scripts`、`.kiro`）。

## 2. 通用规则

- 使用 Vue 3 `script setup` + Composition API。
  - **豁免**：布局壳层文件（`layouts/blank.vue` 纯模板；`FrameBlank.vue` / `FrameContent.vue` / `frame/index.vue` 等 keep-alive 包装器使用 `defineComponent`）不受此条约束。
- HTTP 请求走各端现有 `src/api/*` 与 request/runtime 工具，不直接创建 axios 实例。
- Token、session、站点信息走现有 auth/runtime/store，不直接读写 localStorage 键。
- 复杂页面逻辑下沉到同目录 `composables`、`domains`、`features`、`services` 或 `utils`。
- **列表/详情加载必须做请求竞态防护（requestSeq 模式）**：自增序号 + 响应返回时比对，旧响应不得覆盖新数据（参照控制台的 `frontend-user-v4-console/src/domains/finance/useRecords.ts`、`frontend-user-v4-console/src/domains/services/console/useConsoleTabs.ts`，管理端 `src/domains/` 当前只有 `finance/dateFilters.ts`，照控制台的范式写，不要引用不存在的管理端路径）；懒加载标签以“加载成功”作为已加载置位条件，失败后允许重拉。
- 用户可见文案使用简体中文，错误提示优先读取后端 `message` 和 `data.errors`，不要展示英文异常或第三方原始错误。
- 状态展示先复用 `@caiwu/shared`。
- **状态管理按端独立，不跨端合并同名 store**：`frontend-admin-v3/src/store/modules/` 与 `frontend-user-v4-console/src/store/modules/` 下的同名模块（`permission`、`setting`、`tabs-router`、`user`）分别服务管理端与控制台两种产品形态，**不是重复代码，不做合并**：
  - `permission.ts`：管理端（419 行）持有 `ADMIN_MENU_GROUPS` 静态菜单组配置，并按权限码（`hasPermissionInList`）过滤路由，因为管理端菜单由权限动态生成；控制台（59 行）只把静态路由表 `homepageRouterList`/`fixedRouterList` 转成菜单，用户端无按钮级权限码，登录态由 `user` store 与路由守卫控制。
  - `setting.ts`：管理端含品牌色阶生成（`tvision-color`）与设置面板 UI 态；控制台按 `docs/DESIGN.md` §2 只提供浅色模式。
  - `tabs-router.ts`：管理端带标签数上限（`MAX_TAB_COUNT = 20`）与显式序列化字段；控制台直接 `persist: true`。
  - `user.ts`：管理端含双因素登录（`mfaRequired` / `challengeToken`）；控制台含验证码登录与资料归一。
  - 真正需要共享的会话、路由加载与请求能力走 `shared/runtime`（`session`、`routeLoading`、`http/*`），不复制到任一端的 store。

## 3. `frontend-admin-v3`

目录约定：

- `src/main.ts`：应用入口。
- `src/permission.ts`：路由守卫。
- `src/router/modules/`：按业务域拆分路由。
- `src/store/modules/`：Pinia 状态。
- `src/pages/`：页面，按业务域组织。
- `src/api/`：管理端 API 封装。
- `src/style/`：Less 样式与主题变量。
- `src/layouts/`：管理端布局。

实现约束：

- UI 只用 TDesign Vue Next。
- 图标只用 `tdesign-icons-vue-next`。
- 权限码与后端 `App\Support\AdminPermissions` 对齐。
- 页面不新增非 TDesign 的 UI 组件、样式或图标，也不保留旧组件体系的兼容层。防回退守卫现状：官网有 `scripts/check-no-legacy-ui.mjs` + `check-tdesign-resolver.mjs`（已接入 CI 与 `verify:refactor`），控制台有 `scripts/verify-refactor.mjs` 的 `forbiddenTerms`；管理端从未引入过 Element Plus（`git log --all -S'"element-plus"'` 为空），故未设等价守卫，新增 UI 框架时需先在管理端补一条同等检查。
- 管理列表页不做说明型页头大卡片，优先筛选区、指标区、工具栏、表格卡片。
- 管理端页面禁止新增独立“头部说明卡片”。
- **多标签页与页面缓存**：`src/config/style.ts` 的 `isUseTabsRouter` 默认开启，标签栏、页面缓存与「关闭标签刷新」都依赖它。
  - KeepAlive 的 `include` 取自标签的 `meta.keepAliveName || route.name`，与实际组件名比对；`<script setup>` 未声明 `defineOptions({ name })` 时推断名是文件名（大量为 `index`），缓存会静默失效。**新增可缓存页面必须同时声明 `defineOptions({ name })` 并在路由 `meta.keepAliveName` 写明同一个名字。**
  - 多个路由共用一个组件时（列表不同 tab、详情不同 id），`meta.keepAliveName` 统一指向该组件名，页面内部按 `route.path` / `route.query` 自行同步，否则切标签不重载会显示上一个入口的数据。
  - 标签缓存键是 `route.path`，不要改成 `fullPath`：页内切 query 会把同一标签缓存成多份。
  - **控制台（frontend-user-v4-console）口径与本节相反**：`src/config/style.ts` 的 `isUseTabsRouter` 默认 `false`，不用 `defineOptions({ name })` + `meta.keepAliveName` 那套 include 匹配；`src/layouts/components/Content.vue` 直接按 `route.name ?? route.path` 做 key 缓存 `keepAlive !== false` 的页面，上限与页签上限同源（`MAX_TAB_COUNT`）。改缓存策略时两处分别对照，别把一端的约定套到另一端。

验证：

```bash
cd frontend-admin-v3
npm run build
```

## 4. `frontend-user-v3-www`

目录约定：

- `src/main.js`：应用入口。
- `src/app/`：bootstrap、router、runtime、stores 等核心能力。
- `src/api/`：站点与用户 API。
- `src/pages/website/`：官网页面。
- `src/pages/common/`：公共页面（当前只有 `NotFound.vue`；登录、注册在 `src/pages/website/` 下，用户中心页面由 `frontend-user-v4-console` 承担）。
- `src/domains/`：产品、服务等领域逻辑。
- `src/composables/`：跨页面组合逻辑。
- `src/components/`：局部业务组件（没有 `src/features/` 目录）。
- `src/assets/styles/`：Sass token（`variables.scss`）与站点全局基线样式（`global.scss`）；TDesign 设计令牌来自仓库根 `theme.css`，本目录不再存 UI 框架主题入口。

实现约束：

- UI 使用 TDesign Vue Next 与 `tdesign-icons-vue-next`；组件按需解析见 `frontend-user-v3-www/scripts/tdesign-resolver.mjs`，禁止从 `tdesign-vue-next` 包入口（全量 barrel）引入组件。
- 图标一律显式 `import { XxxIcon } from "tdesign-icons-vue-next"`，不使用 `<t-icon name>`：后者走 svg-sprite，需从 `tdesign.gtimg.com` 拉取雪碧图脚本，本站 CSP 不放行且首屏不应引第三方脚本。
- 函数式反馈走子路径插件：`MessagePlugin` 从 `tdesign-vue-next/es/message/plugin`、`DialogPlugin` 从 `tdesign-vue-next/es/dialog/plugin`；两者的样式已由 `src/app/bootstrap.ts` 补齐，业务文件不要再引 CSS。`tdesign-vue-next` 包入口与 `es/style/index.css` 之外的基础样式同样由该文件统一引。
- 模板里的 `<t-xxx>` 必须收录进 `scripts/tdesign-resolver.mjs` 的 `TDESIGN_COMPONENT_DIRS`/`TDESIGN_PLUGIN_DIRS`，否则会静默渲染成空自定义元素且构建不报错；`npm run verify:refactor` 通过 `scripts/check-tdesign-resolver.mjs` 拦截。
- 旧组件体系不得回流：依赖、`<el-xxx>` 标签、`--el-*` 变量、`.el-*` 选择器与旧命令式 API 任一出现即视为残留，`npm run verify:refactor` 通过 `scripts/check-no-legacy-ui.mjs` 拦截（扫 `src`/`scripts`/`e2e` 与构建配置，并断言 TDesign 两包在场）；构建后再以 `--dist` 模式复扫产物 `html`/`css`/`js`，覆盖经 `node_modules` 间接路径打进 `dist` 的旧样式。CI 的 `verify` job 也会在构建前后各执行一次（源码守卫在构建前、`--dist` 守卫在构建后），不依赖开发者本地是否跑过 `verify:refactor`。
- 官网首页、产品页、登录页可以有更强视觉表现；用户中心业务页保持浅色控制台结构。
- 购买、结算、优惠券、恢复下单优先复用 `src/domains/products/*` 与现有 composables。
- SEO、sitemap、prerender 逻辑放脚本和构建流程，不在页面里硬拼。

验证：

```bash
cd frontend-user-v3-www
npm run build
npm run verify:refactor
```

只做小改时至少执行 `npm run build`；涉及重构或共享逻辑时追加 `verify:refactor`。

## 5. `frontend-user-v4-console`

目录约定：

- `src/main.ts`：应用入口。
- `src/permission.ts`：路由守卫。
- `src/router/`：控制台路由。
- `src/store/`：Pinia 状态。
- `src/pages/client/`：控制台页面。
- `src/domains/`：账户、财务、服务、工单、内容、营销、工具等领域逻辑。
- `src/composables/`：控制台组合逻辑。
- `src/api/`：用户端 API 封装。
- `src/style/`：Less 样式和 TDesign token。

实现约束：

- UI 只用 TDesign Vue Next。
- 图标只用 `tdesign-icons-vue-next`。
- 优先复用 `shared/user-v3` 的 `DataState`（loading/error/empty 三态与重试、空态行动插槽）、`LoadingState`、`StatusTag`；对话框与抽屉的移动端形态由根 `theme.css` 统一适配，不在页面内重复实现。
- 控制台页面优先稳定信息架构，不做官网式 Hero、深色大屏或装饰优先布局。
- `/client/*` 控制台业务能力与后端 `/api/v2/client/*` 对齐。
- 财务记录页面（账单、订单、充值的列表和详情页）禁止使用统计/指标卡片。
- 财务记录列表页（订单/账单/充值）统一复用 `domains/finance/useRecords.ts` 的 `useRecordList` 引擎与 `pages/client/components/RecordQuickFilters.vue`，不在页面内自造列表逻辑；筛选口径：快捷标签（全部/最近7天/本月/待支付）与自定义日期范围选择器并存，`quick` 仅表达标签语义、日期恒以 `start_date`/`end_date` 显式写 URL，两者**可以同时出现**（手选区间恰好命中某个快捷折算时），读取时显式日期优先、标签选中态由区间反查回写。
- 页面边距由 Starter 布局层统一提供：桌面 `20px 24px`，`≤991px` 统一 `12px`；页面根元素不要自带 padding，禁止使用 `paddingLR-s` 或 `paddingTB-m` 承载页面边距。

验证：

```bash
cd frontend-user-v4-console
npm run build
npm run verify:refactor
```

只做小改时至少执行 `npm run build`；涉及重构或共享逻辑时追加 `verify:refactor`。

## 6. `shared`

主要导出：

- `@caiwu/shared/status`
- `@caiwu/shared/runtime`
- `@caiwu/shared/content`
- `@caiwu/shared/user-v3`（含 `components/StatusTag.vue`、`components/DataState.vue`）

规则：

- 状态文案、颜色和标签不要在页面里另写一套。
- runtime 能力优先从 `shared/runtime` 或各端已有 runtime 封装接入。
- 修改 `shared` 后至少执行：

```bash
npm run typecheck:shared
npm run test:shared
```

并按影响范围执行对应前端 build。

## 7. 根 workspace 命令

```bash
npm run dev:admin-v3
npm run dev:user-v3-www
npm run dev:user-v4-console

npm run build:frontends
npm run build:frontends:dry
npm run lint:frontends
npm run typecheck:frontends
npm run test:shared
npm run test:build-config
npm run verify:frontends
```

根 `package.json` 只保留当前真实 workspace。新增前端项目时，必须同步更新 `package.json`、`package-lock.json`、`AGENTS.md`、`docs/参考资料/运维/本地启动指南.md` 和本规范。

`app_flutter` 是唯一的例外：Flutter 工程有自己的 `pubspec.yaml` 依赖体系，**不进入根 workspace**，也不受上列 `npm run` 命令覆盖，验证命令见 §9。

## 8. 依赖版本口径

- `unplugin-vue-components` 三端统一锁 `^0.27.5`（当前 `frontend-user-v3-www` 仍声明 `^0.27.0`，属待对齐的漂移项，锁文件会 dedupe 到同一份实现）。`frontend-user-v4-console` 曾声明 `^28.8.0`，但该大版本改写了 `ComponentResolverObject` 契约与解析时机，与两端现有自定义 `createTDesignResolver()` 实现不匹配；两端各装一个主版本还会让 Vite 按 workspace 分别解析，出现「同一份 `vite.config.ts` 在 admin 生效、在 console 失效」的难查故障。降回 0.27.x 后三端 dedupe 到同一份实现，按需引入行为一致。
- `vue-tsc` 锁 `3.3.11`（不写 `^`）。`vue-tsc` 的次版本会改变类型推导严格度，放开版本会让 CI 与本地在不同时间得出不同结果。
- `@playwright/test` 三端统一 `^1.62.1`；e2e 产物目录（`dist-e2e/`、`test-results/`、`playwright-report/`）一律不入库。
- 体积门禁预算在 `backend/scripts/frontend_budget.mjs`：首屏 gzip admin 560,000 B、www 145,000 B（闭包口径）、console 420,000 B；单分片 raw www 180,000 B、样式分片 raw www 70,000 B。改动构建配置（按需引入、manualChunks、依赖增删）后必须跑 `npm run build:frontends`，超预算即失败。

## 9. `app_flutter`（Android 原生 App）

定位：面向 caiwu 终端用户的 Android App，对标阿里云 App 形态；业务面与 `frontend-user-v4-console` 的客户端能力对齐，但按移动端原生交互重新实现，不是网页套壳。

技术基线：

- Flutter（Dart SDK `^3.8.0`，本机验证 Flutter 3.47.9 / Dart 3.13.7）、`tdesign_flutter ^1.1.0`（唯一 UI 框架）。
- `dio`（统一外层 `{code,message,data,timestamp}` 解包）、`go_router`（声明式路由 + 登录守卫）、`provider`（会话与依赖注入）。
- `flutter_secure_storage`（Token 进 Keystore）+ `shared_preferences`（用户资料）。
- SSH：`dartssh2` + `xterm`，App 直连服务器 22 端口；VNC：自研 RFB 3.8 over WebSocket，经后端 relay 转发。

目录约定：

- `lib/main.dart`、`lib/app.dart`：入口、会话恢复与 `AppServices` 挂载。
- `lib/core/config/env.dart`：`API_BASE` / `WS_BASE` / `VNC_ORIGIN`，全部由 `--dart-define` 覆盖，禁止写死地址。
- `lib/core/net/`：`ApiClient`、异常、`*Api` 领域接口、`AppServices` 依赖根。
- `lib/core/router/router.dart`：全部路由与登录守卫（`/home`、`/services`、`/tickets`、`/profile` 四个 Tab，另有 `/finance`、`/notices`、`/ssh`、`/sessions`）。
- `lib/core/theme/`：`app_theme.dart`（TDesign Token 覆写品牌色阶）、`app_tokens.dart`（`AppSpacing` / `AppRadius` / `AppType`）。
- `lib/core/layout/responsive.dart`：断点 `compact < 600`、`medium 600–839`、`expanded >= 840`（Material 3 口径，与 Vue 端 768/992/1200 不同，见下方说明）。
- `lib/widgets/`：`app_kit.dart` 设计系统组件、`data_state.dart` 三态容器、`app_feedback.dart` 反馈。
- `lib/features/`：按业务域组织（`auth`、`home`、`services`、`tickets`、`finance`、`notices`、`profile`、`ssh`、`vnc`、`shell`）。

实现约束：

- UI 只用 `tdesign_flutter`；页面骨架、卡片、区块标题、字段网格、状态标签、分段器与胶囊统一走 `app_kit.dart`，间距/圆角/字阶只取 `app_tokens.dart` 与 `context.tTheme`，不各页自拼。
- 数值一律 `AppType.money` / `AppType.stat`（内部等宽数字，刷新不抖动）；加载中的按钮 `onPressed: null`；破坏性操作走 `AppFeedback.confirm(danger: true)`。
- **禁止任何 WebView / 网页嵌套**，由 `tool/check_no_webview.dart` 守卫（扫描 `pubspec.yaml` 依赖与源码 import），提交前运行。
- 视觉遵循 [DESIGN.md](DESIGN.md)：浅色、主色 `#165DFF`、页面底 `#F5F7FB`；断点用 Material 3 的 600/840 而非 Web 端 768，因为移动端要覆盖手机横屏与平板分屏，600 更贴合触控设备。
- 后端契约只消费 `/api/v2/client/*` 与站点公开接口，不改接口结构；唯一后端配套改动是 VNC relay 的 App Origin 白名单（`backend/config/idc.php` 的 `vnc_relay.app_origins`，默认 `caiwu-app`，两端默认值即可对上）。
- 依赖与构建完全独立：不接入根 npm workspace，也不参与 `npm run build:frontends` / `verify:frontends`。

验证：

```bash
cd app_flutter
flutter analyze
flutter test
dart run tool/check_no_webview.dart
flutter build apk --debug
```

影响 `backend/` 的改动（如 VNC Origin 白名单）仍按后端规则跑 `composer analyse && composer format:check`。

已知边界：

- 仅 Android 为当前目标平台（工程含 `web/` 目录但不作为发布形态）；iOS 需补构建适配。
- 首版不含产品选购下单、实名认证、推荐返利、优惠券领取、帮助中心、NAT/安全组编辑、推送、深色模式。
- Geetest 图形验证不内嵌网页，触发时提示改用密码登录（与"禁止网页嵌套"红线一致）。
- VNC 仅支持 None / VNC Authentication 安全类型；只声明 Raw 编码。
- Release 签名当前复用 debug keystore，上线前需替换正式 keystore。

模块自己的安装、运行与审查记录在 [app_flutter/README.md](../app_flutter/README.md)，跨模块的架构位置见 [ARCHITECTURE.md](ARCHITECTURE.md) §3.5。
