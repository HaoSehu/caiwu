# Goal Mode Prompt：创欧云用户控制台 TDesign Starter 重构

> **完整技术方案见**：`C:\Users\USER125536\Desktop\caiwu\文档\前端\frontend-user-v3-console-TDesign-Starter重构开发文档.md`
>
> 下面的约束和流程是执行的核心红线。遇到细节问题时回查完整方案。

---

## ROLE

你是资深前端架构师，精通 Vue 3 + TypeScript + Vite + TDesign Vue Next + TDesign Starter。你要将一个 Element Plus 旧项目完整迁移到 TDesign Starter 底座。

## GOAL

在 `C:\Users\USER125536\Desktop\caiwu\` 下新建 `frontend-user-v4-console`，用 TDesign Vue Next Starter 完全替代 Element Plus，保留全部 24 个 `/client/*` 路由的业务功能。品牌：**创欧云**。

## CONTEXT

- **v3（源）**：`frontend-user-v3-console` — Vue 3 + Vite + Element Plus，只读对照，不删不改
- **v4（目标）**：`frontend-user-v4-console` — TDesign Starter 底座，新建
- **shared**：`shared/user-v3/components/` 已有 9 个 TDesign 化组件可用；`shared/components/` 有 Element Plus 残留需注意
- **www**：`frontend-user-v3-www` — SEO 官网，不纳入本次重构

## CONSTRAINTS（红线，违反即失败）

**绝对禁止**：
1. 不删、不改、不覆盖 `frontend-user-v3-console`
2. 不保留任何 Element Plus（`element-plus`、`@element-plus/icons-vue`、`ElMessage`、`ElMessageBox`、`<el-*>`）
3. **不自建导航布局** — 页面壳直接用 TDesign Starter 模板的 `layouts/`，只替换 logo（创欧云）+ 菜单项
4. 不改后端接口（URL、method、请求参数、响应结构 `{ code, message, data, timestamp }` 全不变）
5. 不给控制台做 SEO（`<meta name="robots" content="noindex,nofollow">` 必须保留）
6. 不引入第三套 UI 库
7. 不做 AI 味装饰（大 Hero、渐变、玻璃拟态、无意义插画）
8. 不给 TDesign 组件包 `BaseButton`/`BaseCard` 等纯转发封装

**必须做到**：
1. 品牌名统一为 "创欧云"，从 `siteBranding` store 读取
2. 组件复用优先级：Starter 原生 > TDesign 组件 > shared/user-v3 > 业务域 > 新增
3. 视觉全走 `var(--td-*)` token，不写固定 px
4. 危险操作有二次确认，异步操作有 loading/成功/失败
5. **每页面迁移后必须做对照测试**（见下方流程）

## EXECUTION PLAN

按 8 个 Phase 顺序执行，每 Phase 验收通过再进入下一个。完整细节见 `C:\Users\USER125536\Desktop\caiwu\文档\前端\frontend-user-v3-console-TDesign-Starter重构开发文档.md` 的 §13。

| Phase | 做什么 | 验收标准 |
|---|---|---|
| **0 盘点** | 遍历 v3 输出路由清单、Element Plus 使用清单、views/ 目录、service-console 子组件链、shared 残留 | 所有路由有迁移归属，服务控制台引用链完整 |
| **1 基底** | `td-starter init frontend-user-v4-console`（备用：git clone Starter 仓库） | `npm install && npm run build` 成功 |
| **2 骨架** | 接入 shared、配代理/预压缩/资源 base、清 Element Plus 依赖 | 空路由可运行，零 Element Plus |
| **3 runtime** | 迁移 request.ts（ElMessage→MessagePlugin）、session、network、store、路由+守卫 | 登录/401/动态 import 失败行为正确 |
| **4 布局壳** | **直接用 Starter 的 `layouts/`**，替换 logo+站点名+菜单项+账户区，不手写 layout | 三断点导航可用，与 Starter 模板一致 |
| **5 P0 页面** | 逐页迁移 9 组核心页面（登录→控制台→服务→账单→充值→下单→工单→实名认证） | **每页对照测试通过** |
| **6 P1/P2** | 补齐订单/充值记录/余额流水/优惠券/推荐/产品目录/公告/帮助/工具 | 24 路由全可访问 |
| **7 清理** | 全局扫 Element Plus 残留、扩展健康检查、迁移测试、全量构建验证 | `typecheck && test && build && verify:refactor` 通过 |

## ⚠️ 页面对照测试流程（逐页必做，不可跳过）

每迁移完一个页面（从 Phase 5 开始），执行以下测试：

```
1. 启动两个项目并排对照
   - 左窗口：frontend-user-v3-console  (npm run dev，端口 5177)
   - 右窗口：frontend-user-v4-console  (npm run dev，端口 5178)

2. 打开同一路由页面，逐项对比：
   ✓ 布局 — 标题、面包屑、导航状态
   ✓ 数据 — 列表/卡片/详情字段完整，空态/错误态正确
   ✓ 表单 — 输入、校验、提交、loading、成功/失败反馈
   ✓ 异步 — 支付轮询、充值查询、VNC 连接、文件上传
   ✓ 移动端 — 缩到 <768px，验证表格卡片化/抽屉全屏/按钮可用
   ✓ 错误 — 断网、401/422/429/500 表现

3. 差异记录：不一致处判定是「有意改进」还是「退化」，记录在验收报告中

4. 通过条件：功能可用、无报错、无白屏、关键路径走通
```

## KEY REFERENCES

执行时随时查阅：

- **完整方案**：`C:\Users\USER125536\Desktop\caiwu\文档\前端\frontend-user-v3-console-TDesign-Starter重构开发文档.md`
  - §5 — 路由清单（24 个 `/client/*`）
  - §8 — Element Plus → TDesign 组件替换矩阵
  - §9 — 每个页面的迁移要求（保留功能 + TDesign 化重点）
  - §10 — 导航菜单结构
  - §11 — 设计系统（token、间距、断点）
  - §12 — API 合约
  - §14 — 验收标准
  - §15 — 风险与对策
- **v3 源码**：`C:\Users\USER125536\Desktop\caiwu\frontend-user-v3-console/`
- **shared 组件**：`C:\Users\USER125536\Desktop\caiwu\shared/user-v3/components/`

---

从 Phase 0 开始。每阶段完成后报告验收结果，确认后再进入下一阶段。
