# Caiwu App（Flutter 原生移动端）

面向 caiwu 用户的 Android 原生 App，对标阿里云 App 形态：服务管理、SSH 终端、VNC 远程桌面、工单、账单订单、消息。

> **独立构建目录**：本目录是仓库内的第四端，跨模块定位见 `docs/ARCHITECTURE.md` §3.5，
> 工程约束见 `docs/FRONTEND.md` §9，构建与测试入口见 `docs/参考资料/运维/本地启动指南.md` 与 `docs/参考资料/运维/测试指南.md` §10.3。
> **不接入根 npm workspace**，依赖与构建全部独立。

## 技术栈

| 能力 | 选型 | 说明 |
| --- | --- | --- |
| UI | `tdesign_flutter ^1.1.0` | 腾讯 TDesign 官方 Flutter 组件库，唯一 UI 框架 |
| 网络 | `dio ^5.7.0` | 统一外层 `{code,message,data,timestamp}` 解包 |
| 路由 | `go_router ^14.6.2` | 声明式路由 + 登录守卫 |
| 状态 | `provider ^6.1.2` | 会话与依赖注入 |
| SSH | `dartssh2 ^4.1.0` + `xterm ^4.0.0` | 纯 Dart SSH 客户端 + 终端渲染，App 直连服务器 22 端口；支持独立主机管理、私钥认证、跳板机、保活 |
| VNC | 自研 RFB（`lib/features/vnc/rfb/`） | 原生 RFB 3.8 over WebSocket，经后端 relay，**不嵌网页** |
| 存储 | `flutter_secure_storage` + `shared_preferences` | Token 进 Keystore，用户资料进偏好 |

## 响应式与平板适配

断点定义在 `lib/core/layout/responsive.dart`（Material 3 口径）：

| 断点 | 宽度 | 典型设备 |
| --- | --- | --- |
| `compact` | < 600 | 手机竖屏 |
| `medium` | 600–839 | 大屏手机 / 平板竖屏 |
| `expanded` | >= 840 | 平板横屏 / 桌面 |

在页面里直接用扩展：

```dart
context.isTablet        // width >= 600
context.pagePadding     // 手机 16 / 平板 24
context.gridColumns     // 2 / 3 / 4
ContentWidthLimiter(child:)   // 平板限宽 840 居中（AppScaffold 已内置）
Breakpoints.formMaxWidth      // 表单限宽 560
```

各页的平板形态：

| 页面 | 手机 | 平板 |
| --- | --- | --- |
| 底部导航 | 底部 TTabBar | **左侧导航栏**（88 宽，图标+文字） |
| 首页 | 工具 5 列、事件横滑 | 工具 6/8 列、事件一行铺满、费用卡左右分栏 |
| 服务列表 | 单栏 | 两栏 TCellGroup、搜索框限宽 400 |
| 服务详情 | 上下堆叠 | 左信息操作 / 右页签分栏 |
| 工单 | 单栏 | 列表两栏；详情左信息 320 + 右对话 |
| 财务 | 单栏 | 详情页信息与金额并排、按钮限宽 360 靠右 |
| 我的 | 竖排 | 左头像卡 320 + 右功能列表 |
| 认证三页 | 原版式 | 限宽 420 居中；横屏左品牌右表单 |

验证：`test/responsive_layout_test.dart`（断点与限宽单测）+ `test/responsive_test.dart`
（390/834/1194/320 四种尺寸下 App 构建无溢出）。

## 首页布局（对齐阿里云 App）

底部 Tab 第一项为「控制台」，页面结构：

1. **顶部**：标题「控制台」+ 问候语 + 消息角标 + 打开会话入口
2. **工具宫格**（两行 × 5）：**SSH 工具**（最左）、工单、账单、充值、全部工具 / 云服务器、我的服务、进行中工单、我的账号、更多
3. **概览页签**：概览 / 运维监控 / 费用成本 / 我的订阅
4. **概览内容**：待处理事件（横向卡片）→ 费用监控（余额 + 三项指标）→ 我的服务 → 公告

数据口径：首页只展示**未删除**的服务（status≠3），分组计数按过滤后的可见条目计算，
与「服务」页默认视图一致。

## SSH 功能（独立于 caiwu 服务）

除了从服务详情页进入 SSH，App 还有一套**独立的 SSH 主机管理**（「我的」→「我的服务器」）：

| 能力 | 说明 |
| --- | --- |
| 主机管理 | 手动添加任意服务器（名称/地址/端口/用户名/备注），搜索、编辑、复制、删除 |
| 认证方式 | 密码或 OpenSSH 私钥（`SSHKeyPair.fromPem`） |
| 跳板机 | 选择另一台已保存主机作为跳板，用 `forwardLocal` 建 direct-tcpip 通道 |
| 保活 | 可选 0/30/60/120 秒，定时 `ping` 防止空闲断连 |
| 多会话 | 同时打开多个会话，`/sessions` 里查看、切换、断开 |
| 快捷命令 | 31 条常用命令（系统/网络/进程/Docker），终端内一键插入 |
| 安全 | 密码与私钥存 Android Keystore；host key 首次确认 + 变化时人工复核 |

相关文件：`lib/features/ssh/`
- `host_store.dart` 主机数据模型与存储
- `host_list_page.dart` / `host_edit_page.dart` 主机管理
- `ssh_session.dart` 连接层（ServiceSshTarget / HostSshTarget）
- `ssh_page.dart` 终端界面
- `session_registry.dart` / `session_manager_page.dart` 多会话
- `quick_commands.dart` 快捷命令

> 设计与思路参考了 [ServerBox](https://github.com/lollipopkit/flutter_server_box)（AGPL-3.0），
> 但**未复制其任何代码**，实现完全独立，仅使用同一批开源上游库（dartssh2 / xterm.dart）。

## 硬性约束（反目标）

- **禁止任何 WebView / 网页嵌套**：`tool/check_no_webview.dart` 守卫，CI/提交前运行。
- 不混用其它 UI 框架；视觉遵循 `docs/DESIGN.md`（主色 `#165DFF`、浅色企业控制台、财务页无指标卡片）。
- 不改后端接口结构（唯一后端改动：VNC relay 的 App Origin 白名单）。

## 目录结构

```text
lib/
├── core/
│   ├── config/env.dart          # API_BASE / WS_BASE / VNC_ORIGIN（--dart-define 覆盖）
│   ├── net/                     # ApiClient、异常、领域 API、AppServices 依赖根
│   ├── router/router.dart       # 全部路由与登录守卫
│   ├── store/session.dart       # 会话持久化
│   ├── theme/
│   │   ├── app_theme.dart       # TDesign Token 覆写（品牌色阶）
│   │   └── app_tokens.dart      # 间距/圆角/字阶语义令牌（AppSpacing/AppRadius/AppType）
│   └── util/                    # format（金额时间）、account（账号判定）
├── widgets/
│   ├── app_kit.dart             # 设计系统组件：AppScaffold/AppCard/AppSectionHeader/
│   │                            # AppFieldGrid/AppStatusTag/AppSegmentedTabs/AppChip
│   ├── data_state.dart          # loading/error/empty 三态容器
│   └── app_feedback.dart        # Toast/确认弹窗/加载提示
├── features/                    # 按业务域组织（见下）
└── main.dart
```

### 设计系统

所有页面必须使用 `lib/widgets/app_kit.dart` 的组件与 `lib/core/theme/app_tokens.dart` 的令牌，
不要各页自拼间距、字号、卡片与状态标签：

| 需求 | 用 |
| --- | --- |
| 页面骨架 / 顶部栏 / 底部固定按钮 | `AppScaffold` |
| 内容分组卡片 | `AppCard`（可点自动带水波纹，`highlight` 用于余额类强调） |
| 区块标题 + 右侧动作 | `AppSectionHeader` |
| 两列字段网格 / 单行键值 | `AppFieldGrid` / `AppKeyValue` |
| 状态标签 | `AppStatusTag`（`presetOf(tone)` 统一后端 `status_tone` 映射） |
| 页内分段器 / 筛选胶囊 | `AppSegmentedTabs` / `AppChip` |
| 间距 / 圆角 / 文字样式 | `AppSpacing` / `AppRadius` / `AppType` |

规则：数值一律 `AppType.money`/`AppType.stat`（内部开启等宽数字，刷新不抖动）；错误就近显示在
出错字段下方；加载中的按钮必须 `onPressed: null`；破坏性操作走 `AppFeedback.confirm(danger: true)`。

## 本地运行

### 前置

- Flutter SDK ≥ 3.32（本仓库验证 3.47.9 / Dart 3.13.7）
- Android SDK（compileSdk 36）、JDK 17+
- 本机后端：在 `backend/` 执行 `php artisan app:serve`

### 环境变量（国内镜像）

```bash
export JAVA_HOME="C:/Program Files/Amazon Corretto/jdk21.0.13_0"
export PATH="/d/dev/flutter/bin:$PATH"
export PUB_HOSTED_URL="https://pub.flutter-io.cn"
export FLUTTER_STORAGE_BASE_URL="https://storage.flutter-io.cn"
```

### 启动

```bash
cd app_flutter
flutter pub get

# Android 模拟器（默认指向宿主机 10.0.2.2:8000）
flutter run --dart-define=API_BASE=http://10.0.2.2:8000/api

# 真机（换成局域网 IP）
flutter run --dart-define=API_BASE=http://192.168.x.x:8000/api

# 生产构建
flutter build apk --release --dart-define=API_BASE=https://api.example.com/api
```

### 推送到平板（USB 真机）

```bash
export ANDROID_HOME="D:/dev/android-sdk"
ADB="$ANDROID_HOME/platform-tools/adb.exe"

# 1. 确认平板已连接（产品型号 PA2353 / DPD2305 等）
"$ADB" devices -l

# 2. 只出 arm64，覆盖安装到指定设备
flutter build apk --release --target-platform android-arm64 \
  --dart-define=API_BASE=https://api.coyjs.cn/api

# 3. 安装并启动
"$ADB" -s <serial> install -r -d build/app/outputs/flutter-apk/app-release.apk
"$ADB" -s <serial> shell monkey -p com.caiwu.caiwu_app \
  -c android.intent.category.LAUNCHER 1
```

> 国内镜像 `pub.flutter-io.cn` 缺少 `image_picker_ios`，Android 构建时需走官方源，
> 即 `flutter pub get` 前 `unset PUB_HOSTED_URL FLUTTER_STORAGE_BASE_URL`。

### 后端配套（VNC 必需）

VNC relay 默认只允许 console 域 Origin，App 用固定 Origin 连接，需要放行：

```env
# backend/.env
VNC_RELAY_APP_ORIGINS=caiwu-app
```

App 端默认发送 `Origin: https://caiwu-app`（`Env.vncOrigin`），后端会把裸值 `caiwu-app`
补成 `https://caiwu-app` 后比对，两边默认值即可对上。自定义时两端同步修改
`--dart-define=VNC_ORIGIN=https://your-origin` 与 `VNC_RELAY_APP_ORIGINS`。

relay 进程需以 `php artisan vnc:relay` 独立运行（生产见部署指南）。

## 验证

```bash
flutter analyze                              # 静态分析，0 error
flutter test                                 # 单测（RFB 协议、格式化口径、App 可构建）
dart run tool/check_no_webview.dart          # 原生渲染守卫
flutter build apk --debug                    # 构建
```

### 当前验证结果（2026-11-04）

| 检查 | 结果 |
| --- | --- |
| `flutter analyze` | No issues found（0 error / 0 warning / 0 info） |
| `flutter test` | 20 个用例全部通过（含 RFB 握手回归、DES 标准向量、格式化口径） |
| `dart run tool/check_no_webview.dart` | 未发现 WebView 依赖或调用 |
| `flutter build apk --debug` | 构建成功（`build/app/outputs/flutter-apk/app-debug.apk`） |
| 接口联调（ID=1 账号真实 token） | 26 个接口全部 `code=0` |
| 登录生命周期 | 登录 → `/auth/info` → 未认证 401 → 登出 → 旧 token 401，全部符合预期 |
| 后端 VNC 改动回归 | `VncOriginPolicyTest` + `PublicVncTokenSecurityTest` 12 个用例通过；PHPStan 0 错误 |

**构建环境备注**：本机 `android/gradle.properties` 关闭了 Kotlin 增量编译（`kotlin.incremental=false`）。
Windows + 非系统盘组合下，Kotlin 增量缓存文件句柄有时无法释放，表现为
`Could not close incremental caches`；关闭后构建稳定，产物一致。

## 代码审查与修复记录（2026-11-04）

三路并行审查（核心网络/认证、服务与 SSH、VNC 与工单财务），对照后端真实实现逐项核对。
以下为**已修复**的问题：

### 严重（曾导致功能 100% 失败）

| 问题 | 影响 | 修复 |
| --- | --- | --- |
| RFB 3.3 安全类型只取首字节 | UltraVNC/RealVNC 3.3 全部连不上 | 改读完整 32 位大端整数 |
| RFB 3.8 + None 未消费 SecurityResult | 无密码 VNC（QEMU 等）永远停在"正在连接" | 3.8+ None 分支补读 4 字节结果 |
| 通知页签发 `page/page_size` | 后端 `prohibited`，每次 422 | 改用支持分页的 `/notifications` |
| 支付宝查单缺 `payment_no`/`poll_token` | 「我已完成支付」必然 422 | 从下单响应保存两值再查询 |
| 声明 CopyRect 却不实现 | 滚动区域画面永久残缺 | 只声明 Raw 编码 |
| VNC 渲染丢帧 + 内存泄漏 | 繁忙桌面画面持续残缺、原生内存泄漏 | 改为本地帧缓冲 + 16ms 合并合成 |
| SSH 握手超时 25s 掐断指纹确认 | 首次连接基本必然失败 | 放宽至 5 分钟 |
| SSH 指纹不一致无任何出路 | 重装/IP 回收后永久锁死 | 增加"信任新指纹"确认流程 + 按 serviceId 分键 |

### 中等（已修复）

- 流量 `limited`（表示"有限额"）被误当超限，所有限量实例恒红 → 只按用量比例判红
- NAT 实例 SSH 用错地址/端口 → 优先 `nat_remote_address` + `nat_remote_port`
- 重置密码忽略 `second_verify` → 二次验证时明确提示"尚未生效"
- 刷新按钮不带 `refresh=1` → 30 秒缓存导致看不到变化
- SSH 输出逐帧解码跨帧中文乱码 → 改流式 UTF-8 解码
- 工单列表后端不返回 `*_label` → 本地映射（`ticket_labels.dart`）
- 指针坐标漏掉 `BoxFit.contain` 居中留白 → 点击偏移
- 已支付账单显示 `¥0.00` → 按状态展示实付/应付
- 后端 `/client/*` 链接直接 push 进错误页 → 路径映射 + `errorBuilder` 兜底
- 启动时 `/auth/info` 解包错误（无 `user` 包装）→ 资料刷新生效
- 40300（账号禁用）不清会话 → 启动校验时清理
- 未读数重复计算公告 → 只取 `/notifications/unread-count`
- 手机号判定与后端不一致（`+86`、空格）→ 共用 `core/util/account.dart`
- Android 备份会带走明文 PII → `allowBackup=false` + `dataExtractionRules`

### 已知限制

- **Geetest 行为验证**：后端已启用（`captcha-config.enabled=true`），且密码失败 1 次即触发。
  按约定 App 不内嵌网页验证；触发时提示「请改用密码登录 / 完成行为验证后重试」。
- VNC 仅支持 None / VNC Authentication 安全类型（macOS ARD、VeNCrypt 暂不支持）。
- 公告正文按纯文本渲染（后端下发 Markdown，首版未引入 Markdown 渲染库）。

### 尚未做真实链路验证

- SSH 连真实服务器：ID=1 账号的 5 个服务均为「已删除」状态，无 IP/端口/可用密码。
- VNC 连真实 relay：同上，且需 `php artisan vnc:relay` 运行。
- 支付宝/充值跳转：需真实网关配置与可支付账单。

## 已知边界（首版）

- 仅 Android；iOS 代码跨端，构建适配后补。
- 不含：产品选购下单、实名认证、推荐返利、优惠券领取、帮助中心、NAT/安全组编辑、推送、深色模式。
- Geetest 图形验证不内嵌（与"禁止网页嵌套"冲突）；触发时提示改用密码登录。
- VNC 只实现 Raw/CopyRect 编码（RFB 规范要求服务端支持 Raw）；服务端若强制 Tight/ZRLE 需后续扩展。
- Release 签名当前复用 debug keystore，上线前需替换正式 keystore。
