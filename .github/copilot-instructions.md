# Caiwu 项目 GitHub Copilot 指令

## 项目概述

Caiwu 是一个云服务/IDC 财务计费平台，包含：
- `backend`：Laravel 12 后端（PHP 8.2+），承载认证、支付、订单、账单、工单等
- `frontend-admin-v3`：管理端，Vue 3 + TDesign Vue Next
- `frontend-user-v3-www`：官网与用户入口，Vue 3 + Element Plus
- `frontend-user-v4-console`：新版用户控制台，Vue 3 + TDesign Vue Next
- `shared`：跨端共享组件与配置

## 编码规范

### 通用
- 编码始终 UTF-8
- 以瞎猜接口为耻，以认真查询为荣
- 以模糊执行为耻，以寻求确认为荣
- 以创造接口为耻，以复用现有为荣
- 以跳过验证为耻，以主动测试为荣
- 新增能力前先复用现有模块、服务、样式入口和共享配置
- 业务代码按领域聚合，不按"临时功能"堆放
- 公共逻辑优先抽到同目录 `composables`、`utils`、`services` 或 `shared`

### 后端
- 控制器保持薄层：参数接收、鉴权、调用服务、返回响应
- 参数校验用 `FormRequest`；响应用 `Resource`
- 统一通过 `App\Traits\ApiResponse` 返回 JSON，成功 `code = 0`
- 分页结构：`list`、`total`、`page`、`page_size`
- 业务逻辑放 `app/Services`，常量/枚举放 `app/Constants`
- 调用上游/第三方必须走 `app/Services` 下的专用客户端
- 回调接口必须走签名中间件，业务处理必须幂等

### 前端
- 统一 Vue 3 `script setup` + Composition API
- 接口请求走各端 `src/utils/request.js`，认证走 `src/utils/auth.js`
- 领域请求收敛到 `src/api/*`，视图层只消费明确的 API 方法
- 管理端禁止混用 Element Plus；Element Plus 端禁止引入 TDesign
- 图标：TDesign 端用 `tdesign-icons-vue-next`，Element Plus 端用 `@element-plus/icons-vue`
- 禁止在管理端页面新增独立的"头部说明卡片"
- 用户控制台财务记录页面（账单/订单/充值的列表和详情页）禁止使用统计/指标卡片
- 控制台页面间距：页面根元素 `padding: var(--td-comp-paddingTB-l) var(--td-comp-paddingLR-l)`（12px），叠加 Starter 布局 12px，卡片边缘距屏幕 24px，内容距屏幕 36px。新增页面沿用此结构
- 手机端所有页面 padding 必须统一为 12px，禁止使用 `paddingLR-s`（8px）

### 数据库
- 表名、字段名 `snake_case`
- 迁移必须新增文件，不改历史迁移
- Payment 记录只允许修改状态，禁止物理删除

## 开发流程
- 多步骤改造先形成执行计划，按当前代码和文档自动审查计划
- 修改以最小必要范围为原则，不做无关重构
- 每完成一个子任务都执行受影响范围的小测试
- 改前端执行 `npm run build`，改后端执行 `php artisan test`

## 可用 Skills
项目已安装 `addyosmani/agent-skills` 的全部 Skills，位于 `.github/skills/` 目录。Copilot 将根据任务自动激活对应技能。
