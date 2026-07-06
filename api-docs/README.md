# API 接口文档

## 文档用途与重构边界

本目录是现有接口快照，用于审查、联调、回查旧接口行为和 API 直接重构前的代码路径核对。

- 本目录记录的是当前 `/api/admin/*`、`/api/client/*`、`/api/site/*` 旧接口，不代表 v2 新接口目标结构。
- API 直接重构以 `文档/开发文档/后端/API直接重构方案.md` 和 `文档/开发文档/后端/API格式规范.md` 为准。
- 旧接口保留冻结，不为了 v2 重构做结构兼容、双字段兼容或参数兼容。
- 新接口优先落到 `/api/v2/admin/*`、`/api/v2/client/*`、`/api/v2/site/*`；执行前必须同时回查本目录单接口文档、路由、Controller、FormRequest、Resource、Service。
- 本目录中的真实调用返回示例已经脱敏，但不能据此认定接口可继续暴露敏感字段；v2 新接口必须按字段白名单重新设计。

## 本地开发环境地址

- 后端 API：`http://127.0.0.1:8000`

## 全局鉴权方式说明

- 管理端接口：`Authorization: Bearer {admin_token}`
- 用户端接口：`Authorization: Bearer {client_token}`
- 公共官网接口：默认无需登录；个别受控资源仍可能需要签名参数。
- 请求头默认包含：`Accept: application/json`、`Content-Type: application/json`

## 本轮执行策略

- 能安全验证的接口已使用本地服务真实调用，并回填真实状态码、返回参数和返回示例。
- 写操作、删除操作、支付/退款/回调、服务控制、重装、开通、通知发送、密钥配置、上游/插件动作等接口未真实调用，已按对应路由、控制器、FormRequest、Resource、Service 代码补充。
- 所有真实调用返回中的 token、password、secret、key、authorization 等敏感字段已脱敏。
- 21 个真实调用异常接口已按对应代码文件补齐正常请求参数、成功返回参数和成功返回示例，并在接口文件中保留真实异常调用记录。

## 当前覆盖状态

| 状态 | 数量 |
|---|---:|
| ✅ 通过 | 158 |
| ⚠️ 异常 | 21 |
| ⬜ 待调试 / 源码补充 | 179 |
| 合计 | 358 |

## 本轮处理统计

| 项目 | 数量 |
|---|---:|
| 已真实调用 | 179 |
| 真实调用通过 | 158 |
| 真实调用异常 | 21 |
| 高风险未调用，按源码补充 | 176 |
| 缺少样例 ID，按源码补充 | 3 |
| 未匹配当前路由 | 0 |
| 更新时间 | 2026-07-05 16:35:45 |

## 各端接口总数统计

| 端 | 接口数 |
|---|---:|
| admin | 213 |
| client | 123 |
| official | 22 |
| total | 358 |

## 目录结构说明

- `api-docs/admin/`：管理端接口文档。
- `api-docs/client/`：用户端接口文档。
- `api-docs/official/`：官网与公开接口文档。
- 文件路径与接口 URL 路径保持一致；同一路径多方法时使用 `{路径}-{方法}.md`。

## 文件树

- `api-docs/admin/api/admin/auth/info.md`
- `api-docs/admin/api/admin/auth/logout.md`
- `api-docs/admin/api/admin/auth/password.md`
- `api-docs/admin/api/admin/auth/profile.md`
- `api-docs/admin/api/admin/content/articles-GET.md`
- `api-docs/admin/api/admin/content/articles-POST.md`
- `api-docs/admin/api/admin/content/articles/{article}-DELETE.md`
- `api-docs/admin/api/admin/content/articles/{article}-GET.md`
- `api-docs/admin/api/admin/content/articles/{article}-PUT.md`
- `api-docs/admin/api/admin/content/categories-GET.md`
- `api-docs/admin/api/admin/content/categories-POST.md`
- `api-docs/admin/api/admin/content/categories/{category}-DELETE.md`
- `api-docs/admin/api/admin/content/categories/{category}-PUT.md`
- `api-docs/admin/api/admin/content/summary.md`
- `api-docs/admin/api/admin/content/upload-image.md`
- `api-docs/admin/api/admin/coupon-campaigns-GET.md`
- `api-docs/admin/api/admin/coupon-campaigns-POST.md`
- `api-docs/admin/api/admin/coupon-campaigns/summary.md`
- `api-docs/admin/api/admin/coupon-campaigns/{couponCampaign}-DELETE.md`
- `api-docs/admin/api/admin/coupon-campaigns/{couponCampaign}-PUT.md`
- `api-docs/admin/api/admin/coupon-campaigns/{couponCampaign}/toggle-status.md`
- `api-docs/admin/api/admin/coupon-campaigns/{couponCampaign}/trigger.md`
- `api-docs/admin/api/admin/coupons-GET.md`
- `api-docs/admin/api/admin/coupons-POST.md`
- `api-docs/admin/api/admin/coupons/product-tree.md`
- `api-docs/admin/api/admin/coupons/summary.md`
- `api-docs/admin/api/admin/coupons/{coupon}-DELETE.md`
- `api-docs/admin/api/admin/coupons/{coupon}-PUT.md`
- `api-docs/admin/api/admin/coupons/{coupon}/toggle-status.md`
- `api-docs/admin/api/admin/cpu-model-catalog-GET.md`
- `api-docs/admin/api/admin/cpu-model-catalog-POST.md`
- `api-docs/admin/api/admin/dashboard.md`
- `api-docs/admin/api/admin/dashboard/monthly-revenue.md`
- `api-docs/admin/api/admin/dashboard/recent-invoices.md`
- `api-docs/admin/api/admin/dashboard/stats.md`
- `api-docs/admin/api/admin/finance/ledger.md`
- `api-docs/admin/api/admin/finance/ledger/summary.md`
- `api-docs/admin/api/admin/finance/ledger/{id}.md`
- `api-docs/admin/api/admin/finance/new-customer-daily-summary.md`
- `api-docs/admin/api/admin/finance/product-income-summary.md`
- `api-docs/admin/api/admin/finance/recharges.md`
- `api-docs/admin/api/admin/finance/renewal-orders.md`
- `api-docs/admin/api/admin/finance/upgrade-orders.md`
- `api-docs/admin/api/admin/instance-spec-catalog-GET.md`
- `api-docs/admin/api/admin/instance-spec-catalog-POST.md`
- `api-docs/admin/api/admin/integration-plugins.md`
- `api-docs/admin/api/admin/integration-plugins/install.md`
- `api-docs/admin/api/admin/integration-plugins/scan.md`
- `api-docs/admin/api/admin/integration-plugins/{plugin}-DELETE.md`
- `api-docs/admin/api/admin/integration-plugins/{plugin}-GET.md`
- `api-docs/admin/api/admin/integration-plugins/{plugin}/config-secret/{key}.md`
- `api-docs/admin/api/admin/integration-plugins/{plugin}/config.md`
- `api-docs/admin/api/admin/integration-plugins/{plugin}/disable.md`
- `api-docs/admin/api/admin/integration-plugins/{plugin}/enable.md`
- `api-docs/admin/api/admin/integration-plugins/{plugin}/health-check.md`
- `api-docs/admin/api/admin/integration-plugins/{plugin}/test-email.md`
- `api-docs/admin/api/admin/integration-plugins/{plugin}/test-sms.md`
- `api-docs/admin/api/admin/invoices.md`
- `api-docs/admin/api/admin/invoices/{id}.md`
- `api-docs/admin/api/admin/invoices/{id}/cancel.md`
- `api-docs/admin/api/admin/login.md`
- `api-docs/admin/api/admin/logs/activity.md`
- `api-docs/admin/api/admin/logs/admin-logins.md`
- `api-docs/admin/api/admin/logs/api.md`
- `api-docs/admin/api/admin/logs/cleanup.md`
- `api-docs/admin/api/admin/logs/cleanup/overview.md`
- `api-docs/admin/api/admin/logs/email.md`
- `api-docs/admin/api/admin/logs/email/summary.md`
- `api-docs/admin/api/admin/logs/gateway.md`
- `api-docs/admin/api/admin/logs/runtime.md`
- `api-docs/admin/api/admin/logs/runtime/summary.md`
- `api-docs/admin/api/admin/logs/schedule.md`
- `api-docs/admin/api/admin/logs/schedule/health.md`
- `api-docs/admin/api/admin/logs/sms.md`
- `api-docs/admin/api/admin/logs/sms/summary.md`
- `api-docs/admin/api/admin/logs/system.md`
- `api-docs/admin/api/admin/logs/system/summary.md`
- `api-docs/admin/api/admin/logs/tasks.md`
- `api-docs/admin/api/admin/logs/tasks/summary.md`
- `api-docs/admin/api/admin/media-files-GET.md`
- `api-docs/admin/api/admin/media-files-POST.md`
- `api-docs/admin/api/admin/media-files/reindex.md`
- `api-docs/admin/api/admin/media-files/{mediaFile}.md`
- `api-docs/admin/api/admin/member-levels-GET.md`
- `api-docs/admin/api/admin/member-levels-POST.md`
- `api-docs/admin/api/admin/member-levels/{memberLevel}-DELETE.md`
- `api-docs/admin/api/admin/member-levels/{memberLevel}-PUT.md`
- `api-docs/admin/api/admin/orders.md`
- `api-docs/admin/api/admin/orders/{id}.md`
- `api-docs/admin/api/admin/os-options.md`
- `api-docs/admin/api/admin/permissions.md`
- `api-docs/admin/api/admin/product-categories-GET.md`
- `api-docs/admin/api/admin/product-categories-POST.md`
- `api-docs/admin/api/admin/product-categories/reorder.md`
- `api-docs/admin/api/admin/product-categories/{productCategory}-DELETE.md`
- `api-docs/admin/api/admin/product-categories/{productCategory}-PUT.md`
- `api-docs/admin/api/admin/product-groups-GET.md`
- `api-docs/admin/api/admin/product-groups-POST.md`
- `api-docs/admin/api/admin/product-groups/reorder.md`
- `api-docs/admin/api/admin/product-groups/{productCategory}-DELETE.md`
- `api-docs/admin/api/admin/product-groups/{productCategory}-PUT.md`
- `api-docs/admin/api/admin/product-types-GET.md`
- `api-docs/admin/api/admin/product-types-POST.md`
- `api-docs/admin/api/admin/product-types/reorder.md`
- `api-docs/admin/api/admin/product-types/{productType}-DELETE.md`
- `api-docs/admin/api/admin/product-types/{productType}-PUT.md`
- `api-docs/admin/api/admin/products-GET.md`
- `api-docs/admin/api/admin/products-POST.md`
- `api-docs/admin/api/admin/products/batch-sync.md`
- `api-docs/admin/api/admin/products/category/batch.md`
- `api-docs/admin/api/admin/products/provision-hostname/batch.md`
- `api-docs/admin/api/admin/products/reorder.md`
- `api-docs/admin/api/admin/products/split-preview.md`
- `api-docs/admin/api/admin/products/split.md`
- `api-docs/admin/api/admin/products/summary.md`
- `api-docs/admin/api/admin/products/traffic-packages/pull.md`
- `api-docs/admin/api/admin/products/{productId}/force.md`
- `api-docs/admin/api/admin/products/{productId}/restore.md`
- `api-docs/admin/api/admin/products/{product}-DELETE.md`
- `api-docs/admin/api/admin/products/{product}-GET.md`
- `api-docs/admin/api/admin/products/{product}-PUT.md`
- `api-docs/admin/api/admin/products/{product}/owners.md`
- `api-docs/admin/api/admin/products/{product}/sort-order.md`
- `api-docs/admin/api/admin/products/{product}/toggle-status.md`
- `api-docs/admin/api/admin/referral-withdrawals.md`
- `api-docs/admin/api/admin/referral-withdrawals/{withdrawal}/approve.md`
- `api-docs/admin/api/admin/referral-withdrawals/{withdrawal}/reject.md`
- `api-docs/admin/api/admin/referral/account-logs.md`
- `api-docs/admin/api/admin/referral/overview.md`
- `api-docs/admin/api/admin/referral/rewards.md`
- `api-docs/admin/api/admin/roles-GET.md`
- `api-docs/admin/api/admin/roles-POST.md`
- `api-docs/admin/api/admin/roles/{role}-DELETE.md`
- `api-docs/admin/api/admin/roles/{role}-GET.md`
- `api-docs/admin/api/admin/roles/{role}-PUT.md`
- `api-docs/admin/api/admin/roles/{role}/copy.md`
- `api-docs/admin/api/admin/schedules/overview.md`
- `api-docs/admin/api/admin/schedules/trigger.md`
- `api-docs/admin/api/admin/services.md`
- `api-docs/admin/api/admin/services/custom-hostnames/batch.md`
- `api-docs/admin/api/admin/settings-GET.md`
- `api-docs/admin/api/admin/settings-POST.md`
- `api-docs/admin/api/admin/settings/{group}/secret/{key}.md`
- `api-docs/admin/api/admin/site/home-hero-GET.md`
- `api-docs/admin/api/admin/site/home-hero-POST.md`
- `api-docs/admin/api/admin/staff-GET.md`
- `api-docs/admin/api/admin/staff-POST.md`
- `api-docs/admin/api/admin/staff/roles.md`
- `api-docs/admin/api/admin/staff/{staff}-DELETE.md`
- `api-docs/admin/api/admin/staff/{staff}-GET.md`
- `api-docs/admin/api/admin/staff/{staff}-PUT.md`
- `api-docs/admin/api/admin/staff/{staff}/reset-password.md`
- `api-docs/admin/api/admin/staff/{staff}/toggle-status.md`
- `api-docs/admin/api/admin/suppliers-GET.md`
- `api-docs/admin/api/admin/suppliers-POST.md`
- `api-docs/admin/api/admin/suppliers/provider-types.md`
- `api-docs/admin/api/admin/suppliers/summary.md`
- `api-docs/admin/api/admin/suppliers/{supplier}-DELETE.md`
- `api-docs/admin/api/admin/suppliers/{supplier}-GET.md`
- `api-docs/admin/api/admin/suppliers/{supplier}-PUT.md`
- `api-docs/admin/api/admin/suppliers/{supplier}/actions/{action}.md`
- `api-docs/admin/api/admin/suppliers/{supplier}/balance.md`
- `api-docs/admin/api/admin/suppliers/{supplier}/products.md`
- `api-docs/admin/api/admin/suppliers/{supplier}/products/{productId}/config-template.md`
- `api-docs/admin/api/admin/suppliers/{supplier}/secret/{key}.md`
- `api-docs/admin/api/admin/suppliers/{supplier}/toggle-status.md`
- `api-docs/admin/api/admin/tickets.md`
- `api-docs/admin/api/admin/tickets/admin-users.md`
- `api-docs/admin/api/admin/tickets/summary.md`
- `api-docs/admin/api/admin/tickets/upload-image.md`
- `api-docs/admin/api/admin/tickets/{ticket}.md`
- `api-docs/admin/api/admin/tickets/{ticket}/assign.md`
- `api-docs/admin/api/admin/tickets/{ticket}/close.md`
- `api-docs/admin/api/admin/tickets/{ticket}/replies/{replyId}/recall.md`
- `api-docs/admin/api/admin/tickets/{ticket}/reply.md`
- `api-docs/admin/api/admin/users-GET.md`
- `api-docs/admin/api/admin/users-POST.md`
- `api-docs/admin/api/admin/users/{user}-DELETE.md`
- `api-docs/admin/api/admin/users/{user}-GET.md`
- `api-docs/admin/api/admin/users/{user}-PUT.md`
- `api-docs/admin/api/admin/users/{user}/balance-logs.md`
- `api-docs/admin/api/admin/users/{user}/email-logs.md`
- `api-docs/admin/api/admin/users/{user}/invoices.md`
- `api-docs/admin/api/admin/users/{user}/invoices/{invoice}.md`
- `api-docs/admin/api/admin/users/{user}/invoices/{invoice}/manual-entry.md`
- `api-docs/admin/api/admin/users/{user}/invoices/{invoice}/refund.md`
- `api-docs/admin/api/admin/users/{user}/invoices/{invoice}/send-email.md`
- `api-docs/admin/api/admin/users/{user}/login-as.md`
- `api-docs/admin/api/admin/users/{user}/operation-logs.md`
- `api-docs/admin/api/admin/users/{user}/recharge.md`
- `api-docs/admin/api/admin/users/{user}/services-GET.md`
- `api-docs/admin/api/admin/users/{user}/services-POST.md`
- `api-docs/admin/api/admin/users/{user}/services/refresh-statuses.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}-DELETE.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}-GET.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}/base.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}/manual-provision.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}/meta.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}/module-status.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}/password/reset.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}/power.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}/refund.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}/reinstall.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}/reinstall/options.md`
- `api-docs/admin/api/admin/users/{user}/services/{serviceId}/remote-status.md`
- `api-docs/admin/api/admin/users/{user}/sms-logs.md`
- `api-docs/admin/api/admin/users/{user}/tickets.md`
- `api-docs/admin/api/admin/users/{user}/toggle-status.md`
- `api-docs/admin/api/admin/verifications.md`
- `api-docs/admin/api/admin/verifications/summary.md`
- `api-docs/admin/api/admin/verifications/{user}.md`
- `api-docs/admin/api/admin/verifications/{user}/history.md`
- `api-docs/admin/api/admin/verifications/{user}/unbind.md`
- `api-docs/client/api/client/auth/alipay-account-GET.md`
- `api-docs/client/api/client/auth/alipay-account-PUT.md`
- `api-docs/client/api/client/auth/captcha-config.md`
- `api-docs/client/api/client/auth/captcha-script.md`
- `api-docs/client/api/client/auth/email-code.md`
- `api-docs/client/api/client/auth/email.md`
- `api-docs/client/api/client/auth/info.md`
- `api-docs/client/api/client/auth/login-as/exchange.md`
- `api-docs/client/api/client/auth/login-by-code.md`
- `api-docs/client/api/client/auth/logout.md`
- `api-docs/client/api/client/auth/notification-preferences-GET.md`
- `api-docs/client/api/client/auth/notification-preferences-PUT.md`
- `api-docs/client/api/client/auth/phone-code.md`
- `api-docs/client/api/client/auth/phone.md`
- `api-docs/client/api/client/auth/profile.md`
- `api-docs/client/api/client/auth/reset-password.md`
- `api-docs/client/api/client/balance-logs.md`
- `api-docs/client/api/client/balance-logs/summary.md`
- `api-docs/client/api/client/content/overview.md`
- `api-docs/client/api/client/coupons.md`
- `api-docs/client/api/client/coupons/public.md`
- `api-docs/client/api/client/coupons/public/summary.md`
- `api-docs/client/api/client/coupons/summary.md`
- `api-docs/client/api/client/coupons/{couponId}/claim.md`
- `api-docs/client/api/client/finance/ledger.md`
- `api-docs/client/api/client/finance/ledger/summary.md`
- `api-docs/client/api/client/finance/ledger/{id}.md`
- `api-docs/client/api/client/help-articles.md`
- `api-docs/client/api/client/help-articles/{articleId}.md`
- `api-docs/client/api/client/invoices-GET.md`
- `api-docs/client/api/client/invoices-POST.md`
- `api-docs/client/api/client/invoices/summary.md`
- `api-docs/client/api/client/invoices/{id}.md`
- `api-docs/client/api/client/invoices/{id}/cancel.md`
- `api-docs/client/api/client/invoices/{id}/pay/alipay.md`
- `api-docs/client/api/client/invoices/{id}/pay/alipay/status.md`
- `api-docs/client/api/client/invoices/{id}/pay/balance.md`
- `api-docs/client/api/client/invoices/{id}/pay/mix.md`
- `api-docs/client/api/client/login.md`
- `api-docs/client/api/client/notices.md`
- `api-docs/client/api/client/notices/mark-all-read.md`
- `api-docs/client/api/client/notices/unread-count.md`
- `api-docs/client/api/client/notices/{articleId}.md`
- `api-docs/client/api/client/notices/{articleId}/mark-read.md`
- `api-docs/client/api/client/notifications.md`
- `api-docs/client/api/client/notifications/feed.md`
- `api-docs/client/api/client/notifications/mark-all-read.md`
- `api-docs/client/api/client/notifications/unread-count.md`
- `api-docs/client/api/client/notifications/{id}/mark-read.md`
- `api-docs/client/api/client/orders.md`
- `api-docs/client/api/client/orders/summary.md`
- `api-docs/client/api/client/orders/{id}.md`
- `api-docs/client/api/client/orders/{id}/cancel.md`
- `api-docs/client/api/client/password.md`
- `api-docs/client/api/client/payment/alipay/notify.md`
- `api-docs/client/api/client/payment/notify/{gateway}-GET.md`
- `api-docs/client/api/client/payment/notify/{gateway}-POST.md`
- `api-docs/client/api/client/payments.md`
- `api-docs/client/api/client/payments/summary.md`
- `api-docs/client/api/client/payments/{id}.md`
- `api-docs/client/api/client/recharge.md`
- `api-docs/client/api/client/recharge/gateways.md`
- `api-docs/client/api/client/recharge/{paymentNo}/status.md`
- `api-docs/client/api/client/referral/account-logs.md`
- `api-docs/client/api/client/referral/overview.md`
- `api-docs/client/api/client/referral/rewards.md`
- `api-docs/client/api/client/referral/withdrawals-GET.md`
- `api-docs/client/api/client/referral/withdrawals-POST.md`
- `api-docs/client/api/client/register.md`
- `api-docs/client/api/client/services.md`
- `api-docs/client/api/client/services/grouped-overview.md`
- `api-docs/client/api/client/services/{id}.md`
- `api-docs/client/api/client/services/{id}/base.md`
- `api-docs/client/api/client/services/{id}/config.md`
- `api-docs/client/api/client/services/{id}/module-status.md`
- `api-docs/client/api/client/services/{id}/monitor.md`
- `api-docs/client/api/client/services/{id}/monitor/batch.md`
- `api-docs/client/api/client/services/{id}/name.md`
- `api-docs/client/api/client/services/{id}/nat-forwardings-GET.md`
- `api-docs/client/api/client/services/{id}/nat-forwardings-POST.md`
- `api-docs/client/api/client/services/{id}/nat-forwardings/{forwardingId}.md`
- `api-docs/client/api/client/services/{id}/operation-logs.md`
- `api-docs/client/api/client/services/{id}/password/reset.md`
- `api-docs/client/api/client/services/{id}/power.md`
- `api-docs/client/api/client/services/{id}/reinstall.md`
- `api-docs/client/api/client/services/{id}/reinstall/options.md`
- `api-docs/client/api/client/services/{id}/remark.md`
- `api-docs/client/api/client/services/{id}/remote-status.md`
- `api-docs/client/api/client/services/{id}/renew-GET.md`
- `api-docs/client/api/client/services/{id}/renew-POST.md`
- `api-docs/client/api/client/services/{id}/renew/auto.md`
- `api-docs/client/api/client/services/{id}/security-groups-GET.md`
- `api-docs/client/api/client/services/{id}/security-groups-POST.md`
- `api-docs/client/api/client/services/{id}/security-groups/{groupId}.md`
- `api-docs/client/api/client/services/{id}/security-groups/{groupId}/apply.md`
- `api-docs/client/api/client/services/{id}/security-groups/{groupId}/rules-GET.md`
- `api-docs/client/api/client/services/{id}/security-groups/{groupId}/rules-POST.md`
- `api-docs/client/api/client/services/{id}/security-groups/{groupId}/rules/{ruleId}.md`
- `api-docs/client/api/client/services/{id}/traffic-packages.md`
- `api-docs/client/api/client/services/{id}/traffic-packages/order.md`
- `api-docs/client/api/client/services/{id}/traffic-packages/quote.md`
- `api-docs/client/api/client/services/{id}/upgrade.md`
- `api-docs/client/api/client/services/{id}/upgrade/order.md`
- `api-docs/client/api/client/services/{id}/upgrade/quote.md`
- `api-docs/client/api/client/services/{id}/vnc.md`
- `api-docs/client/api/client/tickets-GET.md`
- `api-docs/client/api/client/tickets-POST.md`
- `api-docs/client/api/client/tickets/service-options.md`
- `api-docs/client/api/client/tickets/upload-image.md`
- `api-docs/client/api/client/tickets/{id}.md`
- `api-docs/client/api/client/tickets/{id}/close.md`
- `api-docs/client/api/client/tickets/{id}/replies/{replyId}/recall.md`
- `api-docs/client/api/client/tickets/{id}/reply.md`
- `api-docs/client/api/client/verification/callback-GET.md`
- `api-docs/client/api/client/verification/callback-POST.md`
- `api-docs/client/api/client/verification/close.md`
- `api-docs/client/api/client/verification/fee-config.md`
- `api-docs/client/api/client/verification/init.md`
- `api-docs/client/api/client/verification/qrcode.md`
- `api-docs/client/api/client/verification/restart.md`
- `api-docs/client/api/client/verification/scan.md`
- `api-docs/client/api/client/verification/status.md`
- `api-docs/client/api/client/vnc-tokens/{token}.md`
- `api-docs/official/api/health.md`
- `api-docs/official/api/secure-assets/view.md`
- `api-docs/official/api/site/config.md`
- `api-docs/official/api/site/content/overview.md`
- `api-docs/official/api/site/help-articles.md`
- `api-docs/official/api/site/help-articles/{articleId}.md`
- `api-docs/official/api/site/home-hero.md`
- `api-docs/official/api/site/home.md`
- `api-docs/official/api/site/notices.md`
- `api-docs/official/api/site/notices/{articleId}.md`
- `api-docs/official/api/site/product-categories.md`
- `api-docs/official/api/site/product-categories/{groupId}/catalog.md`
- `api-docs/official/api/site/product-categories/{groupId}/children.md`
- `api-docs/official/api/site/product-groups.md`
- `api-docs/official/api/site/product-groups/{groupId}/catalog.md`
- `api-docs/official/api/site/product-groups/{groupId}/children.md`
- `api-docs/official/api/site/product-types.md`
- `api-docs/official/api/site/products.md`
- `api-docs/official/api/site/products/init.md`
- `api-docs/official/api/site/products/{productId}.md`
- `api-docs/official/api/site/products/{productId}/quote.md`
- `api-docs/official/api/site/products/{productId}/stock.md`
