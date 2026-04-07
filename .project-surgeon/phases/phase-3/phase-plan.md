# Phase 3: 性能与结构收敛

> Parent: [Project Plan](../../project-plan.md)
> Status: Draft

---

## Objective

在关键业务已稳定后，收敛热路径性能问题和结构性边界问题，减少后续维护成本。

## Prerequisites

- [ ] Phase 2 的安全与关键业务修复已通过回归测试
- [ ] 关键链路日志与接口契约已稳定，可安全进行结构收敛

## Libraries & Dependencies

| Library | GitHub Repo | Used For |
|---------|-------------|----------|
| Laravel Framework | laravel/framework | Eloquent 预加载、分页、资源转换、模型事件与服务重构 |
| Laravel Sanctum | laravel/sanctum | 保持管理端/客户端接口鉴权契约稳定 |
| PHPUnit | sebastianbergmann/phpunit | 覆盖分页、权限、读取热路径和模型副作用迁移 |

## Finding Coverage

- `D4-001`
- `D4-002`
- `D4-003`
- `D7-002`
- `D7-003`
- `Bug Fixer review-1 / H-1`
- `Bug Fixer review-1 / M-1`

## Task List

| # | Task | Description | Files | Est. Steps |
|---|------|-------------|-------|------------|
| 1 | move-coupon-pagination-and-summary-to-database | 把优惠券用户列表与公开列表的分页/统计下推到数据库层 | `backend/app/Services/CouponService.php`, `backend/app/Http/Controllers/Client/CouponController.php`, `backend/tests/Feature/ClientCouponPaginationTest.php` | 16 |
| 2 | remove-user-read-side-effects-from-hot-paths | 移除 User 访问器中的 schema 探测和惰性加载副作用 | `backend/app/Models/User.php`, `backend/app/Services/UserService.php`, `backend/app/Http/Resources/UserResource.php`, `backend/app/Http/Resources/AdminUserListResource.php`, `backend/tests/Feature/UserReadAggregateLoadingTest.php` | 20 |
| 3 | eliminate-ticket-admin-permission-n-plus-one | 修复工单指派人列表的权限解析 N+1 | `backend/app/Http/Controllers/Admin/TicketController.php`, `backend/app/Models/AdminUser.php`, `backend/tests/Feature/AdminTicketAssignableUsersTest.php` | 12 |
| 4 | externalize-payment-and-invoice-projection-side-effects | 把 Payment / Invoice 的投影同步副作用移出模型保存事件 | `backend/app/Models/Payment.php`, `backend/app/Models/Invoice.php`, `backend/app/Services/PaymentService.php`, `backend/app/Services/InvoiceService.php`, `backend/tests/Feature/PaymentInvoiceProjectionSyncTest.php` | 20 |
| 5 | externalize-admin-role-bridge-side-effects | 把 AdminUser 与 `admin_user_roles` 的桥表维护从模型钩子中显式化 | `backend/app/Models/AdminUser.php`, `backend/app/Services/AdminRoleBridgeService.php`, `backend/tests/Feature/AdminUserRoleBridgeTest.php` | 14 |
| 6 | thin-finance-referral-verification-read-controllers | 把 Finance / Referral / Verification 读接口的聚合查询下沉到专用服务 | `backend/app/Http/Controllers/Client/FinanceController.php`, `backend/app/Http/Controllers/Admin/ReferralController.php`, `backend/app/Http/Controllers/Admin/VerificationController.php`, `backend/app/Services/ClientFinanceQueryService.php`, `backend/app/Services/AdminReferralOverviewService.php`, `backend/app/Services/AdminVerificationQueryService.php`, `backend/tests/Feature/ReadControllerQueryServiceBoundaryTest.php` | 20 |
| 7 | extract-site-product-read-quote-and-website-quantity-submit | 把站点商品读接口和报价拼装从控制器中收敛到站点服务，并补齐前台数量下单契约 | `backend/app/Http/Controllers/SiteProductController.php`, `backend/app/Services/SiteProductReadService.php`, `backend/app/Services/SiteProductQuoteService.php`, `frontend-client/src/views/website/products/useWebsiteProductCheckout.js`, `frontend-client/src/views/website/ProductDetail/index.vue`, `backend/tests/Feature/SiteProductReadServiceTest.php` | 20 |
| 8 | stabilize-admin-vnc-reconnect-flow | 让管理员打开的 VNC 页面在 token 一次性消费后仍能基于 admin token 与 admin user id 自愈重连 | `backend/app/Http/Controllers/Admin/UserController.php`, `frontend-admin/src/views/admin/UserDetail/index.vue`, `frontend-client/public/vnc/vnc.html`, `backend/tests/Feature/AdminVncReconnectFlowTest.php` | 18 |

## Deliverables

- [ ] 热路径查询与分页不再依赖 PHP 全量装载
- [ ] 模型副作用、控制器拼装和读取边界更清晰

## Verification Checklist

- [ ] 热点接口的查询次数与响应时间有可比较基线
- [ ] 结构收敛后接口返回结构保持兼容

## Phase-Specific Risks

| Risk | Mitigation |
|------|------------|
| 访问器与模型钩子调整后，隐式兼容行为可能丢失 | 在改造前后补对等测试，并保留短期显式适配层 |
| 控制器下沉服务时容易改坏响应结构 | 先抽查询服务，再用现有资源类/特征测试锁住响应 |

---

> Detailed task instructions are in the `tasks/` subdirectory.
