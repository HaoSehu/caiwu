# Caiwu 项目修复执行方案（Agent 版）

- 文档性质：执行文档
- 对齐时间：`2026-06-20`
- 适用对象：Codex、Claude Code、仓库协作代理
- 本轮目标：按项目级修复顺序收敛 Caiwu 当前版本，使其达到可发布、可回归、可解释状态

## 1. 已确认事实

- 当前现役目录只有 `backend`、`frontend-admin-v3`、`frontend-user-v3-www`、`frontend-user-v4-console`、`shared`。
- `frontend-user-v3-www` 负责官网与购买入口，`frontend-user-v4-console` 负责 `/client/*` 用户控制台。
- `backend` 是 Laravel 12，支付、账单、充值、服务开通、工单、实名、返佣能力都已存在。
- 异步任务由 `schedule:run` 驱动，最坏延迟约 60 秒。
- 当前工作区很脏，存在大量改动、删除和未跟踪文件，不能默认把全部内容当成本轮修复范围。

## 2. 执行目标

- 建立本轮修复的发布边界。
- 修正文档和路径真相，消除旧前端命名干扰。
- 优先修复并验通账单支付主链路。
- 再修复支付后的服务开通与控制台主链路。
- 最后梳理管理端权限矩阵与共享状态文案。

## 3. 硬约束

- 只使用当前真实目录，不新增对 `frontend-admin`、`frontend-client`、`frontend-user-v3-console` 的当前引用。
- 不回滚不是自己造成的改动。
- 不做无关重构，不顺手统一格式，不扩大到历史迁移治理。
- 后端必须保持：`code = 0` 成功响应、`payments` 不物理删除、余额支付/手工开通/免费单不生成 Payment、回调幂等、第三方调用不直接写在 Controller。
- 前端必须保持：TDesign 项目不用 Element Plus，Element Plus 项目不用 TDesign，用户可见文案统一简体中文。
- 文档自动生成物遵守仓库规则，未被明确要求时不手工改 API 清单。
- 后端启动说明使用 `php artisan app:serve`，不要写回 `php artisan serve`。

## 4. 任务顺序

### Task 0：基线冻结与范围盘点

- 先读取 `git status --short`，建立本轮修复范围清单。
- 把文件分成四类：`发布内改动`、`暂不发布改动`、`异常删除`、`生成物/临时产物`。
- 这一阶段只做盘点、归类和必要文档说明，不盲目恢复或清理所有文件。

#### 产出

- 一份可执行的发布范围清单。

#### 完成标准

- 后续每个任务都能明确自己可改哪些文件，哪些文件只能观察不能碰。

### Task 1：文档与路径真相修复

#### 建议优先检查文件

- `文档/README.md`
- `文档/目录说明.md`
- `文档/开发文档/产品/产品总览.md`
- `文档/开发文档/架构/架构现状说明.md`
- `文档/开发文档/治理/目录治理规则.md`
- `文档/开发文档/集成/客户端工具页黑洞查询集成说明.md`
- `frontend-user-v3-www/README.md`
- `frontend-user-v3-www/docs/final-acceptance-report.md`

#### 动作要求

- 恢复缺失的文档入口或用当前结构重建。
- 把旧前端路径修正到现役目录。
- 明确说明 `frontend-user-v3-www` 与 `frontend-user-v4-console` 的职责边界。
- 保留历史说明时，必须显式标注“历史路径”或“旧结构”。

#### 完成标准

- 搜索旧前端名时，只剩历史语境。
- 新人只读文档即可知道当前真实结构和前后端分工。

### Task 2：财务主链路修复

#### 后端范围

- `backend/routes/client.php`
- `backend/routes/admin.php`
- `backend/app/Http/Controllers/Client/InvoiceController.php`
- `backend/app/Http/Controllers/Client/PaymentController.php`
- `backend/app/Http/Controllers/Client/RechargeController.php`
- `backend/app/Services/Finance/CheckoutService.php`
- `backend/app/Services/Finance/InvoiceService.php`
- `backend/app/Services/Finance/PaymentService.php`
- `backend/app/Services/Finance/FinanceLedgerQueryService.php`
- 相关 `FormRequest`、`Resource`、财务测试文件

#### 前端范围

- `frontend-user-v3-www` 中与选品、报价、下单续接相关页面和领域逻辑
- `frontend-user-v4-console/src/pages/client/invoices/`
- `frontend-user-v4-console/src/pages/client/invoice-detail/`
- `frontend-user-v4-console/src/pages/client/recharge/`
- `frontend-user-v4-console/src/pages/client/payments/`
- `frontend-user-v4-console/src/pages/client/checkout-resume/`
- `frontend-user-v4-console/src/pages/client/order-create/`
- 相关 `src/api/`、`src/domains/`、`src/router/modules/client.ts`

#### 必测场景

- 新购创建账单
- 余额支付
- 支付宝支付
- 余额 + 支付宝混合支付
- 充值
- 取消未支付账单
- 重复支付回调
- 超时或补发回调
- 退款与反向流水
- 账单状态、支付状态、余额流水状态同步

#### 产品验收标准

- 用户端以“账单”为主语，不再把历史“订单”当成当前主要交互对象。
- `payments` 只反映第三方真实入金。
- 余额支付、手工开通、免费流程不创建 Payment 记录。
- 回调幂等，重复通知不会重复记账或重复开通。
- 前端不直接展示第三方原始错误。

### Task 3：开通与服务实例链路修复

#### 后端范围

- `backend/app/Http/Controllers/Client/ServiceController.php`
- `backend/app/Services/Provisioning/ProvisionService.php`
- `backend/app/Services/Provisioning/ServiceRenewService.php`
- `backend/app/Services/ClientServiceConsole/`
- `backend/app/Jobs/ProcessPaidOrderFulfillmentJob.php`
- 相关服务状态、续费、升级、流量包、VNC、NAT、安全组测试

#### 前端范围

- `frontend-user-v4-console/src/pages/client/services/`
- `frontend-user-v4-console/src/pages/client/service-console/`
- `frontend-user-v4-console/src/pages/client/dashboard/`
- 与续费、升级、实例操作相关的页面、API 和状态组件
- `shared/statusConfig.js`
- `shared/user-v3/components/StatusTag.vue`
- `shared/user-v3/components/DataState.vue`

#### 必测场景

- 支付成功后进入待开通
- 开通中状态展示
- 开通成功后生成实例并可进入控制台
- 开通失败后可识别并可补偿
- 续费、升级、流量包购买后状态正确变化
- 电源、重装、密码重置、VNC、NAT、安全组等能力与实例状态一致

#### 产品验收标准

- 页面状态必须真实表达异步过程，不能承诺即时完成。
- 用户能看懂自己当前处于哪一步。
- 管理端或运营侧有明确接手失败工单/失败开通的路径。

### Task 4：管理端权限与运营闭环修复

#### 后端范围

- `backend/app/Support/AdminPermissions.php`
- `backend/routes/admin.php`
- 用户、账单、服务、工单、设置、供应商相关控制器和服务

#### 前端范围

- `frontend-admin-v3/src/router/modules/admin/`
- `frontend-admin-v3/src/pages/users/`
- `frontend-admin-v3/src/pages/finance/`
- `frontend-admin-v3/src/pages/services/`
- `frontend-admin-v3/src/pages/tickets/`
- `frontend-admin-v3/src/pages/system/`

#### 动作要求

- 按岗位梳理权限矩阵，不只按技术码值对页面做显隐。
- 重点核对退款、人工开通、登录即用户、余额调整、供应商配置、工单接管等高风险动作。
- 确保菜单、按钮、接口权限是一致的，不出现“前端可见但后端无权”或“前端不可见但可直调接口”。

#### 产品验收标准

- 每个角色看到和能操作的范围能用业务语言讲清楚。

### Task 5：术语与体验统一

#### 范围

- `shared/statusConfig.js`
- `shared/user-v3/components/StatusTag.vue`
- `frontend-user-v3-www`
- `frontend-user-v4-console`
- 相关产品文档与对外说明

#### 动作要求

- 用户侧统一使用“账单”而非“订单”。
- 统一处理中、失败、空态、无权限、重试提示。
- 固定官网到控制台的跳转规则，不再依赖旧路径或旧 README 表述。

#### 产品验收标准

- 客服、运营、前端、后端描述同一个状态时，使用同一套术语。

## 5. 验证命令

### 文档修复

- 文档改动后，自检当前结构、路径和职责说明是否与代码现实一致。

### 后端

```bash
cd backend
php artisan test
```

### 管理端

```bash
cd frontend-admin-v3
npm run build
```

### 官网与购买入口

```bash
cd frontend-user-v3-www
npm run build
```

结构性收口时再补：

```bash
npm run verify:refactor
```

### 用户控制台

```bash
cd frontend-user-v4-console
npm run build
```

结构性收口时再补：

```bash
npm run verify:refactor
```

### 共享层

```bash
npm run typecheck:shared
npm run test:shared
```

## 6. 执行原则

- 一个主链路一个批次，不要多条主链路并行乱改。
- 每批改完立刻跑最小相关验证，再进入下一批。
- 所有修复都以当前代码和当前路由事实为准，不以旧文档和旧命名为准。
- 遇到脏工作区冲突，优先保护已有未归档改动，必要时先缩小任务范围。

## 7. 最终完成定义

- 发布范围清楚。
- 文档真相清楚。
- 财务主链路可回归。
- 服务开通主链路可回归。
- 管理端权限可解释。
- 用户侧术语和状态口径统一。
