# 后端 API 清单

- 生成时间: `2026-07-06 04:55:11`
- API 总数: `330`
- 分组统计: `admin=188, client=123, site/public=19`

> **自动生成**，由 `backend/scripts/export_api_inventory.php` 扫描 Laravel 路由表导出，**不要手工编辑**。
>
> 需要更新本文件时，直接在项目根目录执行：`php backend/scripts/export_api_inventory.php`。
> 需要看业务分组、核心业务流程映射等人类可读导航，请查看 `文档/开发文档/后端/API清单导航.md`。

| 分组 | 方法 | 路径 | 控制器动作 | 鉴权 | 中间件 |
| --- | --- | --- | --- | --- | --- |
| admin | `GET` | `/api/v2/admin/auth/info` | `App\Http\Controllers\Admin\V2\AuthController@info` | `admin` | `api, auth:sanctum, ensure.admin` |
| admin | `POST` | `/api/v2/admin/auth/logout` | `App\Http\Controllers\Admin\V2\AuthController@logout` | `admin` | `api, auth:sanctum, ensure.admin` |
| admin | `PUT` | `/api/v2/admin/auth/password` | `App\Http\Controllers\Admin\V2\AuthController@updatePassword` | `admin` | `api, auth:sanctum, ensure.admin` |
| admin | `PUT` | `/api/v2/admin/auth/profile` | `App\Http\Controllers\Admin\V2\AuthController@updateProfile` | `admin` | `api, auth:sanctum, ensure.admin` |
| admin | `GET` | `/api/v2/admin/content/articles` | `App\Http\Controllers\Admin\V2\ContentArticleController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.list` |
| admin | `POST` | `/api/v2/admin/content/articles` | `App\Http\Controllers\Admin\V2\ContentArticleController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `DELETE` | `/api/v2/admin/content/articles/{article}` | `App\Http\Controllers\Admin\V2\ContentArticleController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/v2/admin/content/articles/{article}` | `App\Http\Controllers\Admin\V2\ContentArticleController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.list` |
| admin | `PUT` | `/api/v2/admin/content/articles/{article}` | `App\Http\Controllers\Admin\V2\ContentArticleController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/v2/admin/content/categories` | `App\Http\Controllers\Admin\V2\ContentCategoryController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.list` |
| admin | `POST` | `/api/v2/admin/content/categories` | `App\Http\Controllers\Admin\V2\ContentCategoryController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `DELETE` | `/api/v2/admin/content/categories/{category}` | `App\Http\Controllers\Admin\V2\ContentCategoryController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `PUT` | `/api/v2/admin/content/categories/{category}` | `App\Http\Controllers\Admin\V2\ContentCategoryController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/v2/admin/content/summary` | `App\Http\Controllers\Admin\V2\ContentArticleController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.list` |
| admin | `GET` | `/api/v2/admin/coupon-campaigns` | `App\Http\Controllers\Admin\V2\CouponCampaignController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/v2/admin/coupon-campaigns` | `App\Http\Controllers\Admin\V2\CouponCampaignController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/coupon-campaigns/summary` | `App\Http\Controllers\Admin\V2\CouponCampaignController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `DELETE` | `/api/v2/admin/coupon-campaigns/{couponCampaign}` | `App\Http\Controllers\Admin\V2\CouponCampaignController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PUT` | `/api/v2/admin/coupon-campaigns/{couponCampaign}` | `App\Http\Controllers\Admin\V2\CouponCampaignController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PATCH` | `/api/v2/admin/coupon-campaigns/{couponCampaign}/status` | `App\Http\Controllers\Admin\V2\CouponCampaignController@updateStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/v2/admin/coupon-campaigns/{couponCampaign}/tasks` | `App\Http\Controllers\Admin\V2\CouponCampaignController@runTask` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/coupon-product-groups` | `App\Http\Controllers\Admin\V2\CouponProductGroupController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `GET` | `/api/v2/admin/coupon-product-groups/{group}/children` | `App\Http\Controllers\Admin\V2\CouponProductGroupController@children` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `GET` | `/api/v2/admin/coupon-product-groups/{group}/products` | `App\Http\Controllers\Admin\V2\CouponProductGroupController@products` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `GET` | `/api/v2/admin/coupons` | `App\Http\Controllers\Admin\V2\CouponController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/v2/admin/coupons` | `App\Http\Controllers\Admin\V2\CouponController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/coupons/summary` | `App\Http\Controllers\Admin\V2\CouponController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `DELETE` | `/api/v2/admin/coupons/{coupon}` | `App\Http\Controllers\Admin\V2\CouponController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PUT` | `/api/v2/admin/coupons/{coupon}` | `App\Http\Controllers\Admin\V2\CouponController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PATCH` | `/api/v2/admin/coupons/{coupon}/status` | `App\Http\Controllers\Admin\V2\CouponController@updateStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/cpu-model-catalog` | `App\Http\Controllers\Admin\V2\CpuModelCatalogController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/v2/admin/cpu-model-catalog` | `App\Http\Controllers\Admin\V2\CpuModelCatalogController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/dashboard/monthly-revenue` | `App\Http\Controllers\Admin\V2\DashboardController@monthlyRevenue` | `admin` | `api, auth:sanctum, ensure.admin, permission:dashboard.view` |
| admin | `GET` | `/api/v2/admin/dashboard/recent-invoices` | `App\Http\Controllers\Admin\V2\DashboardController@recentInvoices` | `admin` | `api, auth:sanctum, ensure.admin, permission:dashboard.view` |
| admin | `GET` | `/api/v2/admin/dashboard/stats` | `App\Http\Controllers\Admin\V2\DashboardController@stats` | `admin` | `api, auth:sanctum, ensure.admin, permission:dashboard.view` |
| admin | `GET` | `/api/v2/admin/finance/ledger` | `App\Http\Controllers\Admin\V2\FinanceLedgerController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.list` |
| admin | `GET` | `/api/v2/admin/finance/ledger/summary` | `App\Http\Controllers\Admin\V2\FinanceLedgerController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.list` |
| admin | `GET` | `/api/v2/admin/finance/ledger/{id}` | `App\Http\Controllers\Admin\V2\FinanceLedgerController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.detail` |
| admin | `GET` | `/api/v2/admin/finance/new-customer-daily-summary` | `App\Http\Controllers\Admin\V2\FinanceMenuController@newCustomerDailySummary` | `admin` | `api, auth:sanctum, ensure.admin, permission:finance.report` |
| admin | `GET` | `/api/v2/admin/finance/product-income-summary` | `App\Http\Controllers\Admin\V2\FinanceMenuController@productIncomeSummary` | `admin` | `api, auth:sanctum, ensure.admin, permission:finance.report` |
| admin | `GET` | `/api/v2/admin/finance/recharges` | `App\Http\Controllers\Admin\V2\FinanceMenuController@recharges` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.list` |
| admin | `GET` | `/api/v2/admin/finance/renewal-orders` | `App\Http\Controllers\Admin\V2\FinanceMenuController@renewalOrders` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.list` |
| admin | `GET` | `/api/v2/admin/finance/upgrade-orders` | `App\Http\Controllers\Admin\V2\FinanceMenuController@upgradeOrders` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.list` |
| admin | `GET` | `/api/v2/admin/instance-spec-catalog` | `App\Http\Controllers\Admin\V2\InstanceSpecCatalogController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/v2/admin/instance-spec-catalog` | `App\Http\Controllers\Admin\V2\InstanceSpecCatalogController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/v2/admin/integration-plugin-scans` | `App\Http\Controllers\Admin\V2\IntegrationPluginController@scan` | `admin` | `api, auth:sanctum, ensure.admin, permission:integration_plugin.manage` |
| admin | `GET` | `/api/v2/admin/integration-plugins` | `App\Http\Controllers\Admin\V2\IntegrationPluginController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:integration_plugin.view` |
| admin | `POST` | `/api/v2/admin/integration-plugins` | `App\Http\Controllers\Admin\V2\IntegrationPluginController@install` | `admin` | `api, auth:sanctum, ensure.admin, permission:integration_plugin.manage` |
| admin | `DELETE` | `/api/v2/admin/integration-plugins/{plugin}` | `App\Http\Controllers\Admin\V2\IntegrationPluginController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:integration_plugin.manage` |
| admin | `GET` | `/api/v2/admin/integration-plugins/{plugin}` | `App\Http\Controllers\Admin\V2\IntegrationPluginController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:integration_plugin.view` |
| admin | `PUT` | `/api/v2/admin/integration-plugins/{plugin}/config` | `App\Http\Controllers\Admin\V2\IntegrationPluginController@updateConfig` | `admin` | `api, auth:sanctum, ensure.admin, permission:integration_plugin.manage` |
| admin | `GET` | `/api/v2/admin/integration-plugins/{plugin}/schema` | `App\Http\Controllers\Admin\V2\IntegrationPluginController@schema` | `admin` | `api, auth:sanctum, ensure.admin, permission:integration_plugin.view` |
| admin | `GET` | `/api/v2/admin/integration-plugins/{plugin}/secrets/{key}` | `App\Http\Controllers\Admin\V2\IntegrationPluginController@revealSecret` | `admin` | `api, auth:sanctum, ensure.admin, permission:integration_plugin.secret_reveal` |
| admin | `PATCH` | `/api/v2/admin/integration-plugins/{plugin}/status` | `App\Http\Controllers\Admin\V2\IntegrationPluginController@updateStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:integration_plugin.manage` |
| admin | `POST` | `/api/v2/admin/integration-plugins/{plugin}/tasks` | `App\Http\Controllers\Admin\V2\IntegrationPluginController@runTask` | `admin` | `api, auth:sanctum, ensure.admin, permission:integration_plugin.test` |
| admin | `GET` | `/api/v2/admin/invoices` | `App\Http\Controllers\Admin\V2\InvoiceController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.list` |
| admin | `GET` | `/api/v2/admin/invoices/{invoice}` | `App\Http\Controllers\Admin\V2\InvoiceController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.detail` |
| admin | `POST` | `/api/v2/admin/invoices/{invoice}/cancellations` | `App\Http\Controllers\Admin\V2\InvoiceController@cancel` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.manage` |
| admin | `POST` | `/api/v2/admin/log-cleanups` | `App\Http\Controllers\Admin\V2\LogController@cleanup` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.manage` |
| admin | `GET` | `/api/v2/admin/log-cleanups/overview` | `App\Http\Controllers\Admin\V2\LogController@cleanupOverview` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/v2/admin/log-summaries/{channel}` | `App\Http\Controllers\Admin\V2\LogController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `POST` | `/api/v2/admin/login` | `App\Http\Controllers\Admin\V2\AuthController@login` | `public` | `api, throttle:5,1,v2-admin-login` |
| admin | `GET` | `/api/v2/admin/logs/{channel}` | `App\Http\Controllers\Admin\V2\LogController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/v2/admin/logs/{channel}/{log}` | `App\Http\Controllers\Admin\V2\LogController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `POST` | `/api/v2/admin/media-file-reindexes` | `App\Http\Controllers\Admin\V2\MediaFileController@reindex` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/v2/admin/media-files` | `App\Http\Controllers\Admin\V2\MediaFileController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.list` |
| admin | `POST` | `/api/v2/admin/media-files` | `App\Http\Controllers\Admin\V2\MediaFileController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `DELETE` | `/api/v2/admin/media-files/{mediaFile}` | `App\Http\Controllers\Admin\V2\MediaFileController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/v2/admin/member-levels` | `App\Http\Controllers\Admin\V2\MemberLevelController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:member_level.list` |
| admin | `POST` | `/api/v2/admin/member-levels` | `App\Http\Controllers\Admin\V2\MemberLevelController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:member_level.manage` |
| admin | `DELETE` | `/api/v2/admin/member-levels/{memberLevel}` | `App\Http\Controllers\Admin\V2\MemberLevelController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:member_level.manage` |
| admin | `PUT` | `/api/v2/admin/member-levels/{memberLevel}` | `App\Http\Controllers\Admin\V2\MemberLevelController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:member_level.manage` |
| admin | `GET` | `/api/v2/admin/orders` | `App\Http\Controllers\Admin\V2\OrderController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:order.list` |
| admin | `GET` | `/api/v2/admin/orders/{order}` | `App\Http\Controllers\Admin\V2\OrderController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:order.detail` |
| admin | `GET` | `/api/v2/admin/os-options` | `App\Http\Controllers\Admin\V2\UserController@osOptions` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/v2/admin/permissions` | `App\Http\Controllers\Admin\V2\RoleController@permissions` | `admin` | `api, auth:sanctum, ensure.admin, permission:permission.list` |
| admin | `GET` | `/api/v2/admin/product-groups` | `App\Http\Controllers\Admin\V2\ProductGroupController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/v2/admin/product-groups` | `App\Http\Controllers\Admin\V2\ProductGroupController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/v2/admin/product-groups/reorders` | `App\Http\Controllers\Admin\V2\ProductGroupController@reorder` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `DELETE` | `/api/v2/admin/product-groups/{group}` | `App\Http\Controllers\Admin\V2\ProductGroupController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/product-groups/{group}` | `App\Http\Controllers\Admin\V2\ProductGroupController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `PUT` | `/api/v2/admin/product-groups/{group}` | `App\Http\Controllers\Admin\V2\ProductGroupController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/product-groups/{group}/children` | `App\Http\Controllers\Admin\V2\ProductGroupController@children` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `GET` | `/api/v2/admin/product-types` | `App\Http\Controllers\Admin\V2\ProductTypeController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/v2/admin/product-types` | `App\Http\Controllers\Admin\V2\ProductTypeController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/v2/admin/product-types/reorders` | `App\Http\Controllers\Admin\V2\ProductTypeController@reorder` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `DELETE` | `/api/v2/admin/product-types/{productType}` | `App\Http\Controllers\Admin\V2\ProductTypeController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PUT` | `/api/v2/admin/product-types/{productType}` | `App\Http\Controllers\Admin\V2\ProductTypeController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/products` | `App\Http\Controllers\Admin\V2\ProductController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/v2/admin/products` | `App\Http\Controllers\Admin\V2\ProductController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/v2/admin/products/category-batches` | `App\Http\Controllers\Admin\V2\ProductController@batchUpdateCategory` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/v2/admin/products/provision-hostname-batches` | `App\Http\Controllers\Admin\V2\ProductController@batchUpdateProvisionHostname` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/v2/admin/products/reorders` | `App\Http\Controllers\Admin\V2\ProductController@reorder` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/v2/admin/products/split-previews` | `App\Http\Controllers\Admin\V2\ProductController@splitPreview` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/v2/admin/products/splits` | `App\Http\Controllers\Admin\V2\ProductController@split` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/products/summary` | `App\Http\Controllers\Admin\V2\ProductController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/v2/admin/products/traffic-package-pulls` | `App\Http\Controllers\Admin\V2\ProductController@pullTrafficPackageCatalog` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.sync` |
| admin | `DELETE` | `/api/v2/admin/products/{product}` | `App\Http\Controllers\Admin\V2\ProductController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/products/{product}` | `App\Http\Controllers\Admin\V2\ProductController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `PUT` | `/api/v2/admin/products/{product}` | `App\Http\Controllers\Admin\V2\ProductController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `DELETE` | `/api/v2/admin/products/{product}/force` | `App\Http\Controllers\Admin\V2\ProductController@forceDelete` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/products/{product}/owners` | `App\Http\Controllers\Admin\V2\ProductController@owners` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/v2/admin/products/{product}/restorations` | `App\Http\Controllers\Admin\V2\ProductController@restore` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PATCH` | `/api/v2/admin/products/{product}/status` | `App\Http\Controllers\Admin\V2\ProductController@updateStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/v2/admin/referral-withdrawals` | `App\Http\Controllers\Admin\V2\ReferralWithdrawalController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:referral_withdrawal.list` |
| admin | `POST` | `/api/v2/admin/referral-withdrawals/{withdrawal}/approvals` | `App\Http\Controllers\Admin\V2\ReferralWithdrawalController@approve` | `admin` | `api, auth:sanctum, ensure.admin, permission:finance.withdraw` |
| admin | `POST` | `/api/v2/admin/referral-withdrawals/{withdrawal}/rejections` | `App\Http\Controllers\Admin\V2\ReferralWithdrawalController@reject` | `admin` | `api, auth:sanctum, ensure.admin, permission:finance.withdraw` |
| admin | `GET` | `/api/v2/admin/referral/overview` | `App\Http\Controllers\Admin\V2\ReferralController@overview` | `admin` | `api, auth:sanctum, ensure.admin, permission:referral.list` |
| admin | `GET` | `/api/v2/admin/referral/rewards` | `App\Http\Controllers\Admin\V2\ReferralController@rewards` | `admin` | `api, auth:sanctum, ensure.admin, permission:referral.list` |
| admin | `GET` | `/api/v2/admin/roles` | `App\Http\Controllers\Admin\V2\RoleController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:role.list` |
| admin | `POST` | `/api/v2/admin/roles` | `App\Http\Controllers\Admin\V2\RoleController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:role.manage` |
| admin | `DELETE` | `/api/v2/admin/roles/{role}` | `App\Http\Controllers\Admin\V2\RoleController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:role.manage` |
| admin | `GET` | `/api/v2/admin/roles/{role}` | `App\Http\Controllers\Admin\V2\RoleController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:role.list` |
| admin | `PUT` | `/api/v2/admin/roles/{role}` | `App\Http\Controllers\Admin\V2\RoleController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:role.manage` |
| admin | `POST` | `/api/v2/admin/roles/{role}/copies` | `App\Http\Controllers\Admin\V2\RoleController@copy` | `admin` | `api, auth:sanctum, ensure.admin, permission:role.manage` |
| admin | `POST` | `/api/v2/admin/schedule-triggers` | `App\Http\Controllers\Admin\V2\ScheduleTaskController@trigger` | `admin` | `api, auth:sanctum, ensure.admin, permission:schedule.trigger` |
| admin | `GET` | `/api/v2/admin/schedules/overview` | `App\Http\Controllers\Admin\V2\ScheduleTaskController@overview` | `admin` | `api, auth:sanctum, ensure.admin, permission:schedule.view` |
| admin | `GET` | `/api/v2/admin/services` | `App\Http\Controllers\Admin\V2\ServiceController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/v2/admin/services/custom-hostnames/batch` | `App\Http\Controllers\Admin\V2\ServiceController@batchUpdateCustomHostnames` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/v2/admin/settings` | `App\Http\Controllers\Admin\V2\SettingController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:settings.view` |
| admin | `POST` | `/api/v2/admin/settings` | `App\Http\Controllers\Admin\V2\SettingController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:settings.manage` |
| admin | `GET` | `/api/v2/admin/settings/{group}/secrets/{key}` | `App\Http\Controllers\Admin\V2\SettingController@revealSecret` | `admin` | `api, auth:sanctum, ensure.admin, permission:settings.secret_reveal` |
| admin | `GET` | `/api/v2/admin/site/home-hero` | `App\Http\Controllers\Admin\V2\HomeHeroController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.list` |
| admin | `POST` | `/api/v2/admin/site/home-hero` | `App\Http\Controllers\Admin\V2\HomeHeroController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/v2/admin/staff` | `App\Http\Controllers\Admin\V2\StaffController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:staff.list` |
| admin | `POST` | `/api/v2/admin/staff` | `App\Http\Controllers\Admin\V2\StaffController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:staff.manage` |
| admin | `GET` | `/api/v2/admin/staff/roles` | `App\Http\Controllers\Admin\V2\StaffController@roles` | `admin` | `api, auth:sanctum, ensure.admin, permission:staff.list` |
| admin | `DELETE` | `/api/v2/admin/staff/{staff}` | `App\Http\Controllers\Admin\V2\StaffController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:staff.manage` |
| admin | `GET` | `/api/v2/admin/staff/{staff}` | `App\Http\Controllers\Admin\V2\StaffController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:staff.list` |
| admin | `PUT` | `/api/v2/admin/staff/{staff}` | `App\Http\Controllers\Admin\V2\StaffController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:staff.manage` |
| admin | `POST` | `/api/v2/admin/staff/{staff}/password-resets` | `App\Http\Controllers\Admin\V2\StaffController@resetPassword` | `admin` | `api, auth:sanctum, ensure.admin, permission:staff.manage` |
| admin | `PATCH` | `/api/v2/admin/staff/{staff}/status` | `App\Http\Controllers\Admin\V2\StaffController@updateStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:staff.manage` |
| admin | `GET` | `/api/v2/admin/suppliers` | `App\Http\Controllers\Admin\V2\SupplierController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.list` |
| admin | `POST` | `/api/v2/admin/suppliers` | `App\Http\Controllers\Admin\V2\SupplierController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.manage` |
| admin | `GET` | `/api/v2/admin/suppliers/provider-types` | `App\Http\Controllers\Admin\V2\SupplierController@providerTypes` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.list` |
| admin | `GET` | `/api/v2/admin/suppliers/summary` | `App\Http\Controllers\Admin\V2\SupplierController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.list` |
| admin | `DELETE` | `/api/v2/admin/suppliers/{supplier}` | `App\Http\Controllers\Admin\V2\SupplierController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.manage` |
| admin | `GET` | `/api/v2/admin/suppliers/{supplier}` | `App\Http\Controllers\Admin\V2\SupplierController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.detail` |
| admin | `PUT` | `/api/v2/admin/suppliers/{supplier}` | `App\Http\Controllers\Admin\V2\SupplierController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.manage` |
| admin | `GET` | `/api/v2/admin/suppliers/{supplier}/balance` | `App\Http\Controllers\Admin\V2\SupplierController@balance` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.sync` |
| admin | `GET` | `/api/v2/admin/suppliers/{supplier}/products` | `App\Http\Controllers\Admin\V2\SupplierController@products` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.sync` |
| admin | `GET` | `/api/v2/admin/suppliers/{supplier}/products/{productId}/config-template` | `App\Http\Controllers\Admin\V2\SupplierController@productConfigTemplate` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.sync` |
| admin | `GET` | `/api/v2/admin/suppliers/{supplier}/secrets/{key}` | `App\Http\Controllers\Admin\V2\SupplierController@revealSecret` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.secret_reveal` |
| admin | `PATCH` | `/api/v2/admin/suppliers/{supplier}/status` | `App\Http\Controllers\Admin\V2\SupplierController@updateStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.manage` |
| admin | `POST` | `/api/v2/admin/suppliers/{supplier}/tasks` | `App\Http\Controllers\Admin\V2\SupplierController@runTask` | `admin` | `api, auth:sanctum, ensure.admin, permission:supplier.sync` |
| admin | `GET` | `/api/v2/admin/tickets` | `App\Http\Controllers\Admin\V2\TicketController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.list` |
| admin | `GET` | `/api/v2/admin/tickets/admin-users` | `App\Http\Controllers\Admin\V2\TicketController@adminUsers` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.list` |
| admin | `GET` | `/api/v2/admin/tickets/summary` | `App\Http\Controllers\Admin\V2\TicketController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.list` |
| admin | `POST` | `/api/v2/admin/tickets/upload-images` | `App\Http\Controllers\Admin\V2\TicketController@uploadImage` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.reply` |
| admin | `GET` | `/api/v2/admin/tickets/{ticket}` | `App\Http\Controllers\Admin\V2\TicketController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.list` |
| admin | `PUT` | `/api/v2/admin/tickets/{ticket}/assignment` | `App\Http\Controllers\Admin\V2\TicketController@assign` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.manage` |
| admin | `POST` | `/api/v2/admin/tickets/{ticket}/closures` | `App\Http\Controllers\Admin\V2\TicketController@close` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.manage` |
| admin | `GET` | `/api/v2/admin/tickets/{ticket}/replies` | `App\Http\Controllers\Admin\V2\TicketController@replies` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.list` |
| admin | `POST` | `/api/v2/admin/tickets/{ticket}/replies` | `App\Http\Controllers\Admin\V2\TicketController@reply` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.reply` |
| admin | `POST` | `/api/v2/admin/tickets/{ticket}/replies/{replyId}/recalls` | `App\Http\Controllers\Admin\V2\TicketController@recall` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.reply` |
| admin | `GET` | `/api/v2/admin/users` | `App\Http\Controllers\Admin\V2\UserController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.list` |
| admin | `POST` | `/api/v2/admin/users` | `App\Http\Controllers\Admin\V2\UserController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `DELETE` | `/api/v2/admin/users/{user}` | `App\Http\Controllers\Admin\V2\UserController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/v2/admin/users/{user}` | `App\Http\Controllers\Admin\V2\UserController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `PUT` | `/api/v2/admin/users/{user}` | `App\Http\Controllers\Admin\V2\UserController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/v2/admin/users/{user}/balance-logs` | `App\Http\Controllers\Admin\V2\UserController@balanceLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/v2/admin/users/{user}/email-logs` | `App\Http\Controllers\Admin\V2\UserController@emailLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/v2/admin/users/{user}/invoices` | `App\Http\Controllers\Admin\V2\UserController@invoices` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/v2/admin/users/{user}/invoices/{invoice}` | `App\Http\Controllers\Admin\V2\UserController@invoiceDetail` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `POST` | `/api/v2/admin/users/{user}/invoices/{invoice}/refunds` | `App\Http\Controllers\Admin\V2\UserController@refundInvoice` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.manage` |
| admin | `POST` | `/api/v2/admin/users/{user}/login-as` | `App\Http\Controllers\Admin\V2\UserController@loginAs` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.login_as` |
| admin | `GET` | `/api/v2/admin/users/{user}/operation-logs` | `App\Http\Controllers\Admin\V2\UserController@operationLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `POST` | `/api/v2/admin/users/{user}/recharges` | `App\Http\Controllers\Admin\V2\UserController@recharge` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.recharge` |
| admin | `GET` | `/api/v2/admin/users/{user}/services` | `App\Http\Controllers\Admin\V2\UserServiceController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `POST` | `/api/v2/admin/users/{user}/services` | `App\Http\Controllers\Admin\V2\UserServiceController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `POST` | `/api/v2/admin/users/{user}/services/refresh-statuses` | `App\Http\Controllers\Admin\V2\UserServiceController@refreshStatuses` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `DELETE` | `/api/v2/admin/users/{user}/services/{service}` | `App\Http\Controllers\Admin\V2\UserServiceController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/v2/admin/users/{user}/services/{service}` | `App\Http\Controllers\Admin\V2\UserServiceController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/v2/admin/users/{user}/services/{service}/connection` | `App\Http\Controllers\Admin\V2\UserServiceController@connection` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `PUT` | `/api/v2/admin/users/{user}/services/{service}/manual-provision` | `App\Http\Controllers\Admin\V2\UserServiceController@manualProvision` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `PUT` | `/api/v2/admin/users/{user}/services/{service}/meta` | `App\Http\Controllers\Admin\V2\UserServiceController@updateMeta` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `POST` | `/api/v2/admin/users/{user}/services/{service}/password-resets` | `App\Http\Controllers\Admin\V2\UserController@resetServicePassword` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `POST` | `/api/v2/admin/users/{user}/services/{service}/power-actions` | `App\Http\Controllers\Admin\V2\UserController@servicePower` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `POST` | `/api/v2/admin/users/{user}/services/{service}/refunds` | `App\Http\Controllers\Admin\V2\UserController@refundService` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/v2/admin/users/{user}/services/{service}/remote-status` | `App\Http\Controllers\Admin\V2\UserServiceController@remoteStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/v2/admin/users/{user}/sms-logs` | `App\Http\Controllers\Admin\V2\UserController@smsLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `PATCH` | `/api/v2/admin/users/{user}/status` | `App\Http\Controllers\Admin\V2\UserController@updateStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/v2/admin/users/{user}/tickets` | `App\Http\Controllers\Admin\V2\UserController@tickets` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/v2/admin/verifications` | `App\Http\Controllers\Admin\V2\VerificationController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:verification.list` |
| admin | `GET` | `/api/v2/admin/verifications/summary` | `App\Http\Controllers\Admin\V2\VerificationController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:verification.list` |
| admin | `GET` | `/api/v2/admin/verifications/{user}` | `App\Http\Controllers\Admin\V2\VerificationController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:verification.list` |
| admin | `GET` | `/api/v2/admin/verifications/{user}/history` | `App\Http\Controllers\Admin\V2\VerificationController@history` | `admin` | `api, auth:sanctum, ensure.admin, permission:verification.list` |
| admin | `POST` | `/api/v2/admin/verifications/{user}/unbindings` | `App\Http\Controllers\Admin\V2\VerificationController@unbind` | `admin` | `api, auth:sanctum, ensure.admin, permission:verification.unbind` |
| client | `GET` | `/api/v2/client/auth/alipay-account` | `App\Http\Controllers\Client\V2\AuthController@alipayAccount` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/v2/client/auth/alipay-account` | `App\Http\Controllers\Client\V2\AuthController@updateAlipayAccount` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/auth/captcha-config` | `App\Http\Controllers\Client\V2\AuthController@captchaConfig` | `public` | `api` |
| client | `GET` | `/api/v2/client/auth/captcha-script` | `App\Http\Controllers\Client\V2\AuthController@captchaScript` | `public` | `api` |
| client | `PUT` | `/api/v2/client/auth/email` | `App\Http\Controllers\Client\V2\AuthController@updateEmail` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/auth/email-code` | `App\Http\Controllers\Client\V2\AuthController@sendEmailCode` | `public` | `api, throttle:3,1,client-auth-email-code` |
| client | `GET` | `/api/v2/client/auth/info` | `App\Http\Controllers\Client\V2\AuthController@info` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/auth/login-as/exchange` | `App\Http\Controllers\Client\V2\AuthController@exchangeLoginAsCode` | `public` | `api, throttle:10,1,client-auth-login-as` |
| client | `POST` | `/api/v2/client/auth/login-by-code` | `App\Http\Controllers\Client\V2\AuthController@loginByCode` | `public` | `api, throttle:5,1,client-auth-login-by-code` |
| client | `POST` | `/api/v2/client/auth/logout` | `App\Http\Controllers\Client\V2\AuthController@logout` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/auth/notification-preferences` | `App\Http\Controllers\Client\V2\AuthController@notificationPreferences` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/v2/client/auth/notification-preferences` | `App\Http\Controllers\Client\V2\AuthController@updateNotificationPreferences` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/v2/client/auth/phone` | `App\Http\Controllers\Client\V2\AuthController@updatePhone` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/auth/phone-code` | `App\Http\Controllers\Client\V2\AuthController@sendPhoneCode` | `public` | `api, throttle:3,1,client-auth-phone-code` |
| client | `PUT` | `/api/v2/client/auth/profile` | `App\Http\Controllers\Client\V2\AuthController@updateProfile` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/auth/reset-password` | `App\Http\Controllers\Client\V2\AuthController@resetPassword` | `public` | `api, throttle:5,1,client-auth-reset-password` |
| client | `GET` | `/api/v2/client/balance-logs` | `App\Http\Controllers\Client\V2\FinanceController@balanceLogs` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/balance-logs/summary` | `App\Http\Controllers\Client\V2\FinanceController@balanceLogsSummary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/content/overview` | `App\Http\Controllers\Client\V2\ContentController@overview` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/coupons` | `App\Http\Controllers\Client\V2\CouponController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/coupons/public` | `App\Http\Controllers\Client\V2\CouponController@publicIndex` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/coupons/public/summary` | `App\Http\Controllers\Client\V2\CouponController@publicSummary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/coupons/summary` | `App\Http\Controllers\Client\V2\CouponController@summary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/coupons/{couponId}/claim` | `App\Http\Controllers\Client\V2\CouponController@claim` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-coupons-claim` |
| client | `GET` | `/api/v2/client/finance/ledger` | `App\Http\Controllers\Client\V2\FinanceLedgerController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/finance/ledger/summary` | `App\Http\Controllers\Client\V2\FinanceLedgerController@summary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/finance/ledger/{id}` | `App\Http\Controllers\Client\V2\FinanceLedgerController@show` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/help-articles` | `App\Http\Controllers\Client\V2\ContentController@helpArticles` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/help-articles/{article}` | `App\Http\Controllers\Client\V2\ContentController@helpDetail` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/invoices` | `App\Http\Controllers\Client\V2\InvoiceController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/invoices` | `App\Http\Controllers\Client\V2\InvoiceWorkflowController@store` | `client` | `api, auth:sanctum, ensure.client, throttle:8,1,client-invoices-store` |
| client | `GET` | `/api/v2/client/invoices/summary` | `App\Http\Controllers\Client\V2\InvoiceWorkflowController@summary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/invoices/{id}/pay/alipay` | `App\Http\Controllers\Client\V2\InvoiceWorkflowController@payByAlipay` | `client` | `api, auth:sanctum, ensure.client, throttle:12,1,client-invoices-pay-alipay` |
| client | `GET` | `/api/v2/client/invoices/{id}/pay/alipay/status` | `App\Http\Controllers\Client\V2\InvoiceWorkflowController@queryAlipayStatus` | `client` | `api, auth:sanctum, ensure.client, throttle:30,1,client-invoices-pay-alipay-status` |
| client | `POST` | `/api/v2/client/invoices/{id}/pay/balance` | `App\Http\Controllers\Client\V2\InvoiceWorkflowController@payByBalance` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-invoices-pay-balance` |
| client | `POST` | `/api/v2/client/invoices/{id}/pay/mix` | `App\Http\Controllers\Client\V2\InvoiceWorkflowController@payByBalanceAndAlipay` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-invoices-pay-mix` |
| client | `GET` | `/api/v2/client/invoices/{invoice}` | `App\Http\Controllers\Client\V2\InvoiceController@show` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/invoices/{invoice}/cancellations` | `App\Http\Controllers\Client\V2\ActionController@cancelInvoice` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-invoices-cancel` |
| client | `GET` | `/api/v2/client/ledger` | `App\Http\Controllers\Client\V2\LedgerController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/login` | `App\Http\Controllers\Client\V2\AuthController@login` | `public` | `api, throttle:5,1,client-auth-login` |
| client | `GET` | `/api/v2/client/notices` | `App\Http\Controllers\Client\V2\ContentController@notices` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/notices/mark-all-read` | `App\Http\Controllers\Client\V2\ContentController@markAllNoticesRead` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/notices/unread-count` | `App\Http\Controllers\Client\V2\ContentController@noticeUnreadCount` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/notices/{article}` | `App\Http\Controllers\Client\V2\ContentController@noticeDetail` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/v2/client/notices/{article}/read-state` | `App\Http\Controllers\Client\V2\ActionController@markNoticeRead` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/notifications` | `App\Http\Controllers\Client\V2\NotificationController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/notifications/feed` | `App\Http\Controllers\Client\V2\NotificationController@feed` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/notifications/mark-all-read` | `App\Http\Controllers\Client\V2\NotificationController@markAllRead` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/notifications/unread-count` | `App\Http\Controllers\Client\V2\NotificationController@unreadCount` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/v2/client/notifications/{notification}/read-state` | `App\Http\Controllers\Client\V2\ActionController@markNotificationRead` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/orders` | `App\Http\Controllers\Client\V2\OrderController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/orders/summary` | `App\Http\Controllers\Client\V2\OrderController@summary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/orders/{id}` | `App\Http\Controllers\Client\V2\OrderController@show` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/orders/{order}/cancellations` | `App\Http\Controllers\Client\V2\ActionController@cancelOrder` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-orders-cancel` |
| client | `PUT` | `/api/v2/client/password` | `App\Http\Controllers\Client\V2\AuthController@updatePassword` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/payment/alipay/notify` | `App\Http\Controllers\Client\V2\PaymentCallbackController@alipayNotify` | `public` | `api, verify.alipay.callback` |
| client | `GET&#124;POST` | `/api/v2/client/payment/notify/{gateway}` | `App\Http\Controllers\Client\V2\PaymentCallbackController@notify` | `public` | `api, verify.payment.callback` |
| client | `GET` | `/api/v2/client/payments` | `App\Http\Controllers\Client\V2\PaymentController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/payments/summary` | `App\Http\Controllers\Client\V2\PaymentController@summary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/payments/{id}` | `App\Http\Controllers\Client\V2\PaymentController@show` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/recharge` | `App\Http\Controllers\Client\V2\RechargeController@store` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-recharge-store` |
| client | `GET` | `/api/v2/client/recharge/gateways` | `App\Http\Controllers\Client\V2\RechargeController@gateways` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/recharge/{paymentNo}/status` | `App\Http\Controllers\Client\V2\RechargeController@status` | `client` | `api, auth:sanctum, ensure.client, throttle:30,1,client-recharge-status` |
| client | `GET` | `/api/v2/client/referral/account-logs` | `App\Http\Controllers\Client\V2\ReferralController@accountLogs` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/referral/overview` | `App\Http\Controllers\Client\V2\ReferralController@overview` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/referral/rewards` | `App\Http\Controllers\Client\V2\ReferralController@rewards` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/referral/withdrawals` | `App\Http\Controllers\Client\V2\ReferralController@withdrawals` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/referral/withdrawals` | `App\Http\Controllers\Client\V2\ReferralController@applyWithdrawal` | `client` | `api, auth:sanctum, ensure.client, throttle:3,1,client-referral-withdraw` |
| client | `POST` | `/api/v2/client/register` | `App\Http\Controllers\Client\V2\AuthController@register` | `public` | `api, throttle:5,1,client-auth-register` |
| client | `GET` | `/api/v2/client/services` | `App\Http\Controllers\Client\V2\ServiceConsoleController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/services/grouped-overview` | `App\Http\Controllers\Client\V2\ServiceConsoleController@groupedOverview` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/services/{id}/config` | `App\Http\Controllers\Client\V2\ServiceConsoleController@config` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/services/{id}/module-status` | `App\Http\Controllers\Client\V2\ServiceConsoleController@moduleStatus` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/services/{id}/monitor` | `App\Http\Controllers\Client\V2\ServiceConsoleController@monitor` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/services/{id}/monitor/batch` | `App\Http\Controllers\Client\V2\ServiceConsoleController@monitorBatch` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/v2/client/services/{id}/name` | `App\Http\Controllers\Client\V2\ServiceConsoleController@updateName` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/services/{id}/nat-forwardings` | `App\Http\Controllers\Client\V2\ServiceConsoleController@natForwardings` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/services/{id}/nat-forwardings` | `App\Http\Controllers\Client\V2\ServiceConsoleController@createNatForwarding` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-nat-create` |
| client | `DELETE` | `/api/v2/client/services/{id}/nat-forwardings/{forwardingId}` | `App\Http\Controllers\Client\V2\ServiceConsoleController@deleteNatForwarding` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-nat-delete` |
| client | `GET` | `/api/v2/client/services/{id}/operation-logs` | `App\Http\Controllers\Client\V2\ServiceConsoleController@operationLogs` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/services/{id}/reinstallations/options` | `App\Http\Controllers\Client\V2\ServiceConsoleController@reinstallOptions` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/v2/client/services/{id}/remark` | `App\Http\Controllers\Client\V2\ServiceConsoleController@updateRemark` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/services/{id}/renewals` | `App\Http\Controllers\Client\V2\ServiceConsoleController@renewPreview` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/services/{id}/renewals` | `App\Http\Controllers\Client\V2\ServiceConsoleController@createRenewOrder` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-renew` |
| client | `PUT` | `/api/v2/client/services/{id}/renewals/auto` | `App\Http\Controllers\Client\V2\ServiceConsoleController@updateAutoRenew` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-renew-auto` |
| client | `GET` | `/api/v2/client/services/{id}/security-groups` | `App\Http\Controllers\Client\V2\ServiceConsoleController@securityGroups` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/services/{id}/security-groups` | `App\Http\Controllers\Client\V2\ServiceConsoleController@createSecurityGroup` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-security-group-create` |
| client | `DELETE` | `/api/v2/client/services/{id}/security-groups/{groupId}` | `App\Http\Controllers\Client\V2\ServiceConsoleController@deleteSecurityGroup` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-security-group-delete` |
| client | `POST` | `/api/v2/client/services/{id}/security-groups/{groupId}/apply` | `App\Http\Controllers\Client\V2\ServiceConsoleController@applySecurityGroup` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-security-group-apply` |
| client | `GET` | `/api/v2/client/services/{id}/security-groups/{groupId}/rules` | `App\Http\Controllers\Client\V2\ServiceConsoleController@securityGroupRules` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/services/{id}/security-groups/{groupId}/rules` | `App\Http\Controllers\Client\V2\ServiceConsoleController@createSecurityRule` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-security-rule-create` |
| client | `DELETE` | `/api/v2/client/services/{id}/security-groups/{groupId}/rules/{ruleId}` | `App\Http\Controllers\Client\V2\ServiceConsoleController@deleteSecurityRule` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-security-rule-delete` |
| client | `GET` | `/api/v2/client/services/{id}/traffic-packages` | `App\Http\Controllers\Client\V2\ServiceConsoleController@trafficPackages` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/services/{id}/traffic-packages/orders` | `App\Http\Controllers\Client\V2\ServiceConsoleController@createTrafficPackageOrder` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-traffic-package-order` |
| client | `POST` | `/api/v2/client/services/{id}/traffic-packages/quote` | `App\Http\Controllers\Client\V2\ServiceConsoleController@quoteTrafficPackage` | `client` | `api, auth:sanctum, ensure.client, throttle:12,1,client-service-traffic-package-quote` |
| client | `GET` | `/api/v2/client/services/{id}/upgrades` | `App\Http\Controllers\Client\V2\ServiceConsoleController@hostUpgradePreview` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/services/{id}/upgrades/orders` | `App\Http\Controllers\Client\V2\ServiceConsoleController@createHostUpgradeOrder` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-host-upgrade-order` |
| client | `POST` | `/api/v2/client/services/{id}/upgrades/quotes` | `App\Http\Controllers\Client\V2\ServiceConsoleController@quoteHostUpgrade` | `client` | `api, auth:sanctum, ensure.client, throttle:12,1,client-service-host-upgrade-quote` |
| client | `GET` | `/api/v2/client/services/{id}/vnc` | `App\Http\Controllers\Client\V2\ServiceConsoleController@vnc` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/services/{service}` | `App\Http\Controllers\Client\V2\ServiceController@show` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/services/{service}/connection` | `App\Http\Controllers\Client\V2\ServiceController@connection` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/services/{service}/password-resets` | `App\Http\Controllers\Client\V2\ActionController@resetPassword` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-password-reset` |
| client | `POST` | `/api/v2/client/services/{service}/power-actions` | `App\Http\Controllers\Client\V2\ActionController@power` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-power` |
| client | `POST` | `/api/v2/client/services/{service}/reinstallations` | `App\Http\Controllers\Client\V2\ActionController@reinstall` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-reinstall` |
| client | `GET` | `/api/v2/client/services/{service}/runtime` | `App\Http\Controllers\Client\V2\ServiceController@runtime` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/tickets` | `App\Http\Controllers\Client\V2\TicketWorkflowController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/tickets` | `App\Http\Controllers\Client\V2\TicketWorkflowController@store` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-ticket-store` |
| client | `GET` | `/api/v2/client/tickets/service-options` | `App\Http\Controllers\Client\V2\TicketWorkflowController@serviceOptions` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/tickets/upload-images` | `App\Http\Controllers\Client\V2\TicketWorkflowController@uploadImage` | `client` | `api, auth:sanctum, ensure.client, throttle:12,1,client-ticket-upload-image` |
| client | `POST` | `/api/v2/client/tickets/{id}/closures` | `App\Http\Controllers\Client\V2\TicketWorkflowController@close` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-ticket-close` |
| client | `POST` | `/api/v2/client/tickets/{id}/replies` | `App\Http\Controllers\Client\V2\TicketWorkflowController@reply` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-ticket-reply` |
| client | `GET` | `/api/v2/client/tickets/{ticket}` | `App\Http\Controllers\Client\V2\TicketController@show` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/tickets/{ticket}/replies` | `App\Http\Controllers\Client\V2\TicketController@replies` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/tickets/{ticket}/replies/{reply}/recalls` | `App\Http\Controllers\Client\V2\ActionController@recallTicketReply` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-ticket-recall` |
| client | `GET&#124;POST` | `/api/v2/client/verification/callback` | `App\Http\Controllers\Client\V2\VerificationController@callback` | `public` | `api, verify.callback` |
| client | `POST` | `/api/v2/client/verification/close` | `App\Http\Controllers\Client\V2\VerificationController@close` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/verification/fee-config` | `App\Http\Controllers\Client\V2\VerificationController@feeConfig` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/verification/init` | `App\Http\Controllers\Client\V2\VerificationController@init` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/verification/qrcode` | `App\Http\Controllers\Client\V2\VerificationController@qrcode` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/v2/client/verification/restart` | `App\Http\Controllers\Client\V2\VerificationController@restart` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/verification/scan` | `App\Http\Controllers\Client\V2\VerificationController@scan` | `public` | `api` |
| client | `GET` | `/api/v2/client/verification/status` | `App\Http\Controllers\Client\V2\VerificationController@status` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/v2/client/vnc-tokens/{token}` | `App\Http\Controllers\Client\V2\ServiceConsoleController@vncToken` | `public` | `api, throttle:30,1,client-vnc-token` |
| site/public | `GET` | `/api/health` | `Closure` | `public` | `api` |
| site/public | `GET` | `/api/secure-assets/view` | `App\Http\Controllers\SecureAssetController@show` | `public` | `web, signed:relative` |
| site/public | `GET` | `/api/v2/site/config` | `App\Http\Controllers\Site\V2\HomeController@config` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/content/overview` | `App\Http\Controllers\Site\V2\ContentController@overview` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/help-articles` | `App\Http\Controllers\Site\V2\ContentController@helpArticles` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/help-articles/{article}` | `App\Http\Controllers\Site\V2\ContentController@helpDetail` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/home` | `App\Http\Controllers\Site\V2\HomeController@home` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/home-hero` | `App\Http\Controllers\Site\V2\HomeController@hero` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/notices` | `App\Http\Controllers\Site\V2\ContentController@notices` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/notices/{article}` | `App\Http\Controllers\Site\V2\ContentController@noticeDetail` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/product-groups` | `App\Http\Controllers\Site\V2\ProductGroupController@index` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/product-groups/{group}/children` | `App\Http\Controllers\Site\V2\ProductGroupController@children` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/product-groups/{group}/products` | `App\Http\Controllers\Site\V2\ProductGroupController@products` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/product-purchase-context` | `App\Http\Controllers\Site\V2\ProductController@purchaseContext` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/product-types` | `App\Http\Controllers\Site\V2\ProductController@types` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/products` | `App\Http\Controllers\Site\V2\ProductController@index` | `public` | `api` |
| site/public | `GET` | `/api/v2/site/products/{product}` | `App\Http\Controllers\Site\V2\ProductController@show` | `public` | `api` |
| site/public | `POST` | `/api/v2/site/products/{product}/quote` | `App\Http\Controllers\Site\V2\ProductController@quote` | `public` | `api, throttle:60,1` |
| site/public | `GET` | `/api/v2/site/products/{product}/stock` | `App\Http\Controllers\Site\V2\ProductController@stock` | `public` | `api` |
