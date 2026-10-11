---
status: current
updated: 2026-10-09
owner: frontend-platform
---

# 视觉与交互设计规范

## 1. 设计价值观与原则

本规范以 **TDesign** 官方设计体系为唯一基准，遵循 TDesign 的四大核心设计价值观与四大设计原则，作为前端架构、组件选型、界面呈现与交互实现的唯一裁决标准：

### 1.1 核心价值观 (Values)

- **包容 (Inclusive)**：面向多元场景与多元用户，提供清晰、无障碍（WCAG AA）的普惠体验。
- **多元 (Diverse)**：支持多业务形态、多设备端协同，保持系统弹性和拓展力。
- **进化 (Evolving)**：设计资产与工程实现持续迭代演进，保持现代化生命力。
- **自然 (Natural)**：符合用户直觉的界面映射、视觉层级与交互动效，降低认知负荷。

### 1.2 设计原则 (Principles)

- **清晰 (Clear)**：结构层级分明，核心信息与操作焦点一目了然，去除冗余修饰。
- **高效 (Efficient)**：操作路径简短直达，支持批量与快捷操作，加速业务决策流转。
- **一致 (Consistent)**：统一的 Design Tokens、组件交互模型、文案风格和反馈机制。
- **自适应 (Adaptive)**：良好的屏幕自适应能力与断点栅格响应，保证跨端跨视口可用性。

---

## 2. 总体定位与端划分

本规范仅适用于使用 **TDesign Vue Next** 体系的控制台端（`frontend-admin-v3` 与 `frontend-user-v4-console`）。系统视觉基线为**浅色企业级云服务控制台**，专注于信息传达效率与业务处理：

- **主题模式约束**：**系统仅提供浅色（Light）模式，暂时不提供暗黑（Dark）主题**。严禁把控制台做成深色大屏仪表盘，严禁添加纯营销化发光渐变与玻璃拟态修饰。
- **`frontend-admin-v3`（管理端）**：采用 `TDesign Vue Next`。服务于内部运营、财务及平台管理人员，侧重高密度数据处理、严谨的权限与状态呈现。
- **`frontend-user-v4-console`（用户控制台）**：采用 `TDesign Vue Next`。服务于终端租户及开发者，重视资产与资源扫描效率、直观的实例管控与轻量清晰的账单呈现。
- **`frontend-user-v3-www`（官网与用户入口）**：**暂不纳入本规范约束范围**，继续沿用独立的 Element Plus 视觉体系与规范，后续若有改造需求再另行对齐。

---

## 3. 设计令牌 (Design Tokens)

所有颜色、字体、间距、圆角与阴影必须严格映射至 TDesign 标准 CSS 变量体系（定义于 `theme.css`），严禁在页面或组件内编写非标硬编码数值。

### 3.1 色彩体系 (Colors)

| 语义角色 | CSS 变量 | 默认色值 | 用途与约束 |
| :--- | :--- | :--- | :--- |
| **品牌主色 (Brand)** | `--td-brand-color` (`--td-brand-color-6`) | `#165DFF` | 主要操作按钮、选中态、聚焦高亮、主链接 |
| 主色 Hover | `--td-brand-color-hover` (`--td-brand-color-5`) | `#2A7DFF` | 按钮/链接悬浮态 |
| 主色 Active | `--td-brand-color-active` (`--td-brand-color-7`) | `#0E4FCC` | 点击激活/按下态 |
| 主色 Focus | `--td-brand-color-focus` (`--td-brand-color-2`) | `#C9DBFF` | 键盘聚焦轮廓阴影 |
| 主色 Disabled | `--td-brand-color-disabled` (`--td-brand-color-2`) | `#C9DBFF` | 禁用态背景（文字为白色） |
| 主色浅底 | `--td-brand-color-light` (`--td-brand-color-1`) | `#E8F1FF` | 浅色主色标签背景、选中行底色 |
| **成功色 (Success)** | `--td-success-color` (`--td-success-color-6`) | `#12B76A` | 成功反馈、正常运行、已完成状态 |
| 成功色浅底/文字 | `--td-success-color-1` / `--td-success-color-9` | `#EAFBF3` / `#097A43` | 浅底成功标签背景 / 浅底深色字（满足 WCAG AA 4.5:1） |
| **警告色 (Warning)** | `--td-warning-color` (`--td-warning-color-6`) | `#F59E0B` | 警告提示、待处理、审核中状态 |
| 警告色浅底/文字 | `--td-warning-color-1` / `--td-warning-color-10` | `#FFF6E5` / `#805200` | 浅底警告标签背景 / 浅底深色字（满足 WCAG AA 4.5:1） |
| **错误/危险色 (Error)** | `--td-error-color` (`--td-error-color-6`) | `#F04438` | 错误阻断、异常失败、危险操作按钮 |
| 错误色浅底/文字 | `--td-error-color-1` / `--td-error-color-8` | `#FFF1F0` / `#BF3128` | 浅底错误标签背景 / 浅底深色字（满足 WCAG AA 4.5:1） |
| **页面底色** | `--td-bg-color-page` | `#F5F7FB` | 页面最底层背景底色 |
| **容器底色** | `--td-bg-color-container` | `#FFFFFF` | 卡片、表格、对话框、浮层背景 |
| **次级容器底色** | `--td-bg-color-secondarycontainer` | `#F3F4F7` (`--td-gray-color-1`) | 灰底卡片、表头底色、输入框禁用底色 |
| **组件底色** | `--td-bg-color-component` | `#E5EAF3` (`--td-gray-color-2`) | 胶囊底色、开关未激活底色 |
| **一级边框/分割线** | `--td-border-level-1-color` / `--td-component-stroke` | `#E5EAF3` (`--td-gray-color-2`) | 卡片边框、表格分割线、面板边框 |
| **二级边框** | `--td-border-level-2-color` / `--td-component-border` | `#CFD5DE` (`--td-gray-color-3`) | 输入控件默认边框、深色分割线 |
| **主要文字** | `--td-text-color-primary` | `#1F2937` (`--td-font-gray-1`) | 标题、主要正文、重点数值 |
| **次要文字** | `--td-text-color-secondary` | `#5B6B82` (`--td-font-gray-2`) | 字段 Label、次要辅助文本、说明文字 |
| **占位文字** | `--td-text-color-placeholder` | `#606D80` | 输入框占位符（白底满足 WCAG AA 4.5:1） |
| **禁用文字** | `--td-text-color-disabled` | `rgba(0, 0, 0, 0.25)` | 禁用选项、禁用按钮文案 |
| **反色文字** | `--td-text-color-anti` | `#FFFFFF` | 纯色填充按钮、深色标签内文字 |

### 3.2 字体与字阶 (Typography)

- **字体族栈**：`--td-font-family: PingFang SC, Microsoft YaHei, Arial, sans-serif;`
- **字阶规范**：
  - **页面主标题 (Title Large)**：`20px`（Line-height: `28px`，Font-weight: 600 / Semi-bold）
  - **卡片/分组标题 (Title Medium)**：`16px`（Line-height: `24px`，Font-weight: 600 / Semi-bold）
  - **默认正文/控件 (Body Regular)**：`14px`（Line-height: `22px`，Font-weight: 400 / Normal）
  - **辅助说明/小标签 (Caption)**：`12px`（Line-height: `20px`，Font-weight: 400 / Normal）
  - **关键指标数字 (Stat Numbers)**：`24px` - `32px`，必须开启 CSS 属性 `font-variant-numeric: tabular-nums`，确保等宽数字排版稳定。

### 3.3 间距与栅格 (Spacing & Grid)

- **基准步进**：基于 TDesign 8px 基础网格步进系统，微调使用 4px：
  - 极小间距 (`--td-comp-margin-xs`)：`4px`
  - 小间距 (`--td-comp-margin-s`)：`8px`
  - 标准组件间距 (`--td-comp-margin-m` / `--td-comp-margin-l`)：`12px` / `16px`
  - 区块/容器外间距 (`--td-comp-margin-xl` / `--td-comp-margin-xxl`)：`20px` / `24px`
- **页面边缘内边距硬约束**：
  - 桌面端（视口宽度 > 991px）：统一使用 Starter 布局层内边距 `20px 24px`。
  - 移动端（视口宽度 ≤ 991px）：统一收敛为 `12px`。
  - **红线约束**：业务页面根元素禁止自设额外 `padding`，禁止使用 `paddingLR-s` 或 `paddingTB-m` 覆写全局页面边距。

### 3.4 圆角与投影 (Radius & Elevation)

- **圆角体系 (Radius)**：**完全采用 TDesign 官方同款圆角规范**，增强现代企业级亲和力：
  - **小圆角 (`--td-radius-small`)**：`2px`（适用于 Tag 标签、小尺寸按钮等微型控件）
  - **默认/基础圆角 (`--td-radius-default`)**：`3px`（适用于标准输入框 Input、选择器 Select、普通按钮 Button）
  - **中圆角 (`--td-radius-medium`)**：`6px`（适用于卡片容器 Card、下拉面板、气泡框）
  - **大圆角 (`--td-radius-large`)**：`9px`（适用于对话框 Dialog、抽屉 Drawer、模态浮层）
  - **超大圆角 (`--td-radius-extraLarge`)**：`12px`（适用于特殊展示型容器）
  - **圆形/胶囊 (`--td-radius-round` / `--td-radius-circle`)**：`999px` / `50%`（适用于头像 Avatar、胶囊型 Tag/Badge）
- **投影分级 (Elevation)**：
  - **一级投影 (`--td-shadow-1`)**：`0 1px 2px rgba(25, 35, 50, 0.06)`（卡片容器、普通下拉菜单）
  - **二级投影 (`--td-shadow-2`)**：`0 2px 6px rgba(25, 35, 50, 0.08)`（悬浮菜单、下拉气泡、Tooltip）
  - **三级投影 (`--td-shadow-3`)**：`0 4px 12px rgba(25, 35, 50, 0.1)`（对话框 Dialog、抽屉 Drawer、全局消息通知）
- **禁止全局覆写**：禁止以 `border-radius: 0 !important` 等手段全局清零组件圆角或投影（2026-10-11 裁决：废除历史直角覆写，回归 TDesign 官方圆角/投影基线）。

---

## 4. 组件规范与映射 (Components)

所有管理端与控制台组件必须使用 TDesign Vue Next 官方组件与 `tdesign-icons-vue-next` 官方图标库，杜绝别名或第三方混用。

### 4.1 按钮 (Button / `t-button`)

- **变体 (theme & variant)**：
  - **主操作**：`theme="primary" variant="base"`（纯色品牌蓝底白字）。每个独立视图或卡片内主操作按钮严禁超过 1 个。
  - **次操作**：`theme="default" variant="outline"` 或 `variant="base"`（描边或中性灰底）。
  - **危险操作**：`theme="danger" variant="base"` 或 `variant="outline"`，用于删除、终止等不可逆破坏性动作，必须联动二次确认对话框。
  - **文字操作**：`theme="primary" variant="text"`，常用于表格操作列或行内动作。
- **尺寸 (size)**：
  - 工具栏与普通表单推荐 `medium`（高度 32px）或常规高度 `36px - 40px`。
  - 表格操作列与紧凑场景使用 `small`（高度 24px - 28px）。
- **状态 (state)**：
  - 异步提交必须绑定 `loading` 属性，加载期间自动禁用防止重复点击。
  - 无权限或条件不满足时置为 `disabled`，必要时外层包裹 `t-tooltip` 提示不可用原因。

### 4.2 标签 (Tag / `t-tag`) 与状态体系

- **形态规范**：统一采用浅底轻量标签 `variant="light"`，禁止使用高饱和纯色深底标签作为常规状态，禁止裸用无背景浅灰纯文本展示状态。
- **状态映射标准**：
  - **成功/已支付/正常运行**：`theme="success" variant="light"`
  - **警告/待支付/处理中/待审核**：`theme="warning" variant="light"`
  - **失败/异常/已退款/已违规**：`theme="danger" variant="light"`
  - **已取消/已关闭/草稿/中性状态**：`theme="default" variant="light"`
- **状态组件**：控制台与跨端状态必须优先使用 `@caiwu/shared/components/StatusTag.vue`，统一维护状态文案与语义颜色映射。

### 4.3 表格 (Table / `t-table`)

- **视觉要求**：
  - 表头采用次级容器浅灰底（`--td-bg-color-secondarycontainer`），表头文字加粗居左对齐。
  - 金额、数值及统计列必须右对齐，并使用 `tabular-nums` 字体特性；金额列**完整显示、不得截断或省略**（必要时缩窄其他列）；状态、操作列居中或按规范对齐。
  - 斑马纹/边框：默认开启底部水平边框分割线，行高保持紧凑均匀（`size="medium"` 或 `size="small"`）。
- **加载与空状态**：
  - 数据拉取中必须启用 `t-table` 原生 `loading` 骨架或加载指示器。
  - 无数据时使用 `empty` 插槽挂载 `DataState` 或 `t-empty`，区分“暂无数据”与“未检索到匹配结果”。
- **分页 (`t-pagination`)**：
  - 列表超过一页时统一在表格卡片底部右对齐放置 `t-pagination`，提供页面大小切换（`pageSize`）。

### 4.4 表单与输入 (Form & Inputs)

- **组件**：`t-form`、`t-form-item`、`t-input`、`t-select`、`t-date-range-picker`、`t-switch` 等。
- **排版与对齐**：
  - 管理端与控制台表单标签统一左对齐或右对齐（`label-align="right"`），保持宽度一致；**弹窗/抽屉内表单必须使用右对齐或左对齐**。
  - **页面级表单允许 `label-align="top"`**（移动优先的纵向排版，见个人资料/工单/推广等页面），同一表单内不得混用对齐方式。（对应裁决 F13）
  - 必填项显示星号标识（`required` 属性由校验规则驱动）。
- **校验反馈**：
  - 采用行内即时失焦校验（`trigger: 'blur'`）与提交汇总拦截校验。
  - 错误提示文字必须精准说明修正建议，避免含糊的“格式错误”。
- **禁用与只读**：
  - 编辑态下不可变更的主键或关键标识必须设置 `readonly` 或 `disabled`，并附带置灰视觉样式。

### 4.5 反馈与浮层 (Feedback & Overlay)

- **轻量反馈**：
  - 操作成功、快速提示使用全局消息通知 `MessagePlugin.success(...)` / `MessagePlugin.warning(...)` / `MessagePlugin.error(...)`。
- **二次确认与破坏性阻断**：
  - 核心危险动作必须调用对话框 `DialogPlugin.confirm(...)` 或声明式 `t-dialog`。
  - 标题必须明确指出动作（如“确认删除该服务实例？”），正文提示后果（如“删除后数据不可恢复，请谨慎操作”），确认按钮设置为危险主题（`theme="danger"`）。
- **侧边抽屉 (Drawer / `t-drawer`)**：
  - 悬浮上下文的详情穿透（如优惠券领取、列表页快速查看）统一采用抽屉展示，避免打断当前主工作流。
  - **详情页的关联子列表允许两种合法形态**：页内 `t-tabs` 平铺（信息密度更高，见账单/订单详情页）或 `t-drawer` 原地穿透；同一详情页内保持一种形态。
- **气泡与提示 (Tooltip & Popconfirm / `t-tooltip` & `t-popconfirm`)**：
  - 轻量字段说明使用 `t-tooltip`；表格行内删除等轻量确认可使用 `t-popconfirm`。

---

## 5. 页面布局与信息架构模式

### 5.1 管理端页面结构 (`frontend-admin-v3`)

1. **禁止项**：
   - 普通管理列表页严禁新增说明型页头大卡片，严禁使用“大 Hero 装饰横幅 + 三卡片”公式。
   - 严禁整页使用高饱和渐变、深色大屏仪表盘或无意义装饰卡片。
2. **列表页结构**：
   - **顶部筛选区**：使用 `t-card` 承载紧凑型 `t-form`，包含搜索框、状态下拉、快捷日期选择；**提交式筛选区**右侧收敛“查询/重置”操作，**即时筛选型工具栏（即改即查 + URL 状态同步）可豁免“查询/重置”按钮**，但必须提供可逆的重置途径（如再次点击快捷标签）。（对应裁决 G9）
   - **工具栏**：支持批量操作、新建按钮、导出按钮与列表刷新。
   - **表格卡片区**：承载 `t-table` 及底部分页器 `t-pagination`。
3. **详情页与记录四层骨架**：
   - **第一层（紧凑工具栏）**：返回按钮 + 右对齐业务快捷动作组与刷新按钮，高度 `36px - 40px`。
   - **第二层（实体概览卡片）**：左侧为主标题/单号 + 快捷复制按钮 + 语义化 `t-tag`；右侧为关键经营/财务指标微卡片组，大数值 + `tabular-nums`。
   - **第三层（业务信息主体卡片）**：标准 `t-tabs` 选项卡切换，内边距 `20px - 24px`；表单或字段采用 4 列自适应网格（Desktop 4 列，Tablet 2 列，Mobile 1 列），字段单元格采用“上标签（12px 浅灰次文本）+ 下取值（14px 主文本加粗）”。
   - **第四层（抽屉/弹窗穿透）**：关联账单、订单通过页内 `t-tabs` 平铺或 `t-drawer` 原地唤起（两种形态均合规，取其一），形成无缝穿透闭环。（对应裁决 F17）

### 5.2 用户控制台页面结构 (`frontend-user-v4-console`)

1. **原则与约束**：
   - 重视扫描效率：清晰导航、稳定列表、明确状态、紧凑操作。
   - **禁止项**：财务记录页面（账单、订单、充值的列表和详情页）禁止使用统计/指标卡片；禁止官网式大 Hero 横幅与纯装饰卡片。
2. **状态与异常三态**：
   - 优先复用 `shared/user-v3` 的 `DataState`（统一承载 loading、error、empty 三态与重试、空态操作插槽）。
   - 空数据时必须提供引导路径（例如“暂无服务实例，[前往选购]”）。
3. **财务记录筛选口径**：
   - 统一复用 `domains/finance/useRecords.ts` 的 `useRecordList` 引擎与 `RecordQuickFilters.vue`。
   - 筛选口径：快捷标签（全部/最近7天/本月/待支付）与自定义日期范围选择器并存。`quick` 仅表达标签语义，日期恒以 `start_date`/`end_date` 显式写入 URL；两者**可以同时出现**（手选区间恰好命中某个快捷折算时）。读取时**显式日期优先**，标签选中态由区间反查回写（`matchQuickFilterByRange`）——若只按 `quick` 重算，跨天后区间会漂移。

---

## 6. 文案与术语规范 (Copywriting & Terms)

为保持系统严谨度与专业性，所有页面必须严格对齐 TDesign 官方文案与术语标准：

| 场景 / 类别 | 推荐文案 / 术语 (TDesign 规范) | 严禁使用的不一致文案 | 规范说明 |
| :--- | :--- | :--- | :--- |
| **操作动作** | **确定**、**取消** | 确认（作为对话框确认按钮时）、取消掉、好的 | 对话框与表单提交操作统一使用“确定 / 取消” |
| **查询操作** | **查询**、**重置** | 搜索（在结构化表单筛选区中）、清空、复位 | 结构化过滤项使用“查询/重置”；即时筛选型工具栏（即改即查）可无查询按钮，模糊文本搜索框使用“搜索” |
| **新建/编辑** | **新建**、**编辑**、**删除** | 添加/增加、修改、移除（实体层面） | 统一实体全生命周期动词为“新建 / 编辑 / 删除” |
| **数据加载** | **加载中...** / **数据加载中** | 读取中、拼命加载中、请稍候 | 保持克制、专业、中性语调 |
| **无数据状态** | **暂无数据**、**未找到匹配结果** | 空空如也、没有任何内容、查无此人 | 语义清晰客观，不使用卖萌或非正式词汇 |
| **组件命名** | **对话框 (Dialog)**、**抽屉 (Drawer)** | 弹窗、模态框 (Modal)、侧边栏 | 代码与设计文档一律使用 TDesign 官方组件定名 |
| **提示组件** | **消息 (Message)**、**通知 (Notification)** | Toast、全局提示、Alert 弹窗 | 明确区分瞬时居中反馈（Message）与右侧持久通知（Notification） |
| **气泡组件** | **文字提示 (Tooltip)**、**气泡确认框 (Popconfirm)** | 气泡、Tips、Popover 确认框 | 对齐 TDesign 官方命名体系 |
| **货币与金额** | 半角符号 `¥` + 数值（如 `¥128.00`） | RMB、￥（全角）、元、¥ 128 (带多余空格) | 金额符号紧贴数值，统一使用半角人民币符号 |
| **标点符号** | 提示信息句尾不加句号，解释性段落加全角句号 | 随意省略或混用半角中文字符标点 | 对齐企业级文案排版规范 |

---

## 7. 响应式与可访问性 (Responsive & Accessibility)

1. **响应式断点**：
   - `xs`: `< 768px`（移动端：单列流式布局，隐藏次要列，工具栏收起为操作菜单）
   - `sm`: `>= 768px`（平板端竖屏：2 列网格）
   - `md`: `>= 992px`（桌面端：3 列网格，页面边距切换为 20px 24px）
   - `lg`: `>= 1200px`（标准宽屏：4 列网格）
   - `xl`: `>= 1400px`（超大屏：内容区维持最大阅读宽度或自适应扩展）
2. **无障碍访问性 (WCAG AA)**：
   - 文本与背景对比度必须达到 4.5:1（大文本至少 3:1）。浅底标签中的彩色文字必须采用深色阶（如 `--td-success-color-9`、`--td-warning-color-10`、`--td-error-color-8`）。
   - 所有仅包含图标的按钮必须显式提供 `aria-label` 或外层 `t-tooltip` 说明用途。
   - 键盘 Tab 键焦点导航必须保持逻辑清晰，获得焦点时展示 `--td-brand-color-focus` 高亮聚焦环。
   - 可点击元素（含自定义按钮）最小可交互热区 `44px × 44px`（视觉尺寸可小，但命中区不得小于该值）。
   - 必须尊重 `prefers-reduced-motion: reduce`：装饰性动画（如趋势图描绘、悬浮位移）在减弱动态偏好下禁用或降级。

