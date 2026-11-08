---
status: current
updated: 2026-11-08
owner: docs-governance
---

# 工作规则

## 基本原则

- 使用 CMD 且保持 UTF-8；先读与任务相关的文档和现有代码，再修改。
- 保持最小改动，不做无关重构；无调用方的废弃代码不保留兼容层。
- 工作区可能是脏的，绝不回滚或覆盖不是当前任务造成的改动。
- 临时内容仅放系统临时目录并在结束时清理；禁止用输出重定向把临时结果写入仓库。
- 密钥、密码、Token、私钥和生产数据不得进入文档、提交或日志；测试凭据通过受控渠道取得。

## API 变更必须经 Apifox MCP

Apifox 项目「caiwu 创欧云 API」（projectId `9019820`）是接口文档的唯一载体。任何接口新增、删除、修改——包括路由、FormRequest 参数、Resource 响应字段、错误码、鉴权方案——都必须经 Apifox MCP 落到该项目，代码与接口文档两侧都完成才算完成，不允许只改后端代码或只改 Apifox 其中一侧。

### 新增前先查重

新增页面、功能或接口前，先经 Apifox MCP 只读查询既有接口，避免为同一能力重复建接口：

1. `listEndpoints`（`projectId` `9019820` + `branchId` `8830235`）用 `nameContains` / `pathContains` 按接口名或路径检索，也可用 `folderId`（管理端 `99233685` / 客户端 `99233686` / 站点 `99233687`）限定范围。
2. 命中候选后 `getEndpoint` 读参数与响应结构，`getDataSchema` 读对应数据模型字段。
3. 能复用或扩展现有接口满足需求的，不新建；必须新增时，在改动摘要里说明查过哪些接口、为何都不能复用。

数据模型与服务层同样先按此流程查重，再决定新增。

| 动作 | MCP 方法 | 约束 |
| --- | --- | --- |
| 新增接口 | `createEndpoint` | `GET\|POST` 必须拆成两条；项目默认鉴权会自动继承 |
| 修改接口 | `getEndpoint` → `updateEndpoint` | 先读全量再改；`responses` / `responseExamples` 是整体替换而非增量 |
| 删除接口 | `deleteEndpoint` | 只能走 `deleteMethod` 执行器，`writeMethod` 会拒绝 destructive 方法 |
| 数据模型 | `createDataSchema` / `updateDataSchema` / `deleteDataSchema` | 删除前必须查引用方，Apifox 不做级联清理 |
| 响应组件、安全方案、公共参数 | 对应 component 方法 | 同上，改前先查引用 |

执行顺序：代码或契约确定 → `discoverMethods` 确认方法名 → 读对应 workflow skill（`endpoint-authoring` / `component-payload-authoring`）→ 读 `schemaUri` → `writeMethod` 或 `deleteMethod` → `getEndpoint` 回读确认。

映射规则、目录 ID 与既有口径见 [Apifox 接口文档项目](../参考资料/接口/Apifox接口文档项目.md)；鉴权与默认模板见 [Apifox 鉴权与默认模板](../参考资料/接口/Apifox鉴权与默认模板.md)；数据模型见 [Apifox 数据模型与组件库](../参考资料/接口/Apifox数据模型与组件库.md)。同步失败或 MCP 不可用时，必须在改动摘要里明确说明哪几条接口未同步及原因，不得静默跳过。

## 事实与优先级

冲突时依次以运行代码与测试、数据库 `information_schema`、自动生成文档、当前设计/规格、参考资料为准。视觉实现还受 [DESIGN.md](../DESIGN.md) 约束。

## 修改边界

- 后端控制器保持薄层；上游调用进入专用 Service、Driver 或插件，禁止 Controller 直接 `Http::*`。
- 支付记录只改状态，不物理删除；迁移只新增，不补跑历史激进迁移。
- 插件特有逻辑、路由、调度、中间件和语言文件必须收敛在 `backend/plugins/{domain}/{slug}/`，不得污染系统级扩展点。
- 三端 UI 框架统一为 TDesign Vue Next；不得引入 Element Plus 等其它 UI 框架。
- 管理端不加头部说明卡片；v4-console 财务页面不加指标卡片。
- 自动生成 API 清单不得手工编辑；修改路由后运行生成脚本。

## 验证与提交

- 文档改动运行 `npm run docs:check`（提交涉及 `docs/` 时 `.husky/pre-commit` 也会自动执行）；必要时运行 `npm run docs:freshness`。新建文档从 `docs/模板/` 起手并填写 frontmatter，改状态要同步 `docs/catalog.json` 与对应索引行。
- 后端改动运行 `composer analyse` 与 `composer format:check`；前端按受影响应用运行 `npm run build`，重构按各应用规范补充 `verify:refactor`。
- 接口改动先按上文写入 Apifox 并回读确认，再运行受影响验证；提交摘要必须同时列出代码与 Apifox 两侧的变更。
- 每个子任务先展示改动与验证摘要；得到用户确认后独立提交，格式为 `Fix:中文描述`、`Feat:中文描述` 或 `Refactor:中文描述`。

具体工程约束从 [BACKEND.md](../BACKEND.md)、[FRONTEND.md](../FRONTEND.md)、[DATABASE.md](../DATABASE.md) 和 [参考索引](../参考资料/README.md) 按需进入。
