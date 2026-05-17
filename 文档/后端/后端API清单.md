# 后端 API 清单

- 生成时间: `2026-04-22 23:56:04`
- API 总数: `268`
- 分组统计: `admin=154, client=93, site/public=21`

> **自动生成**，由 `backend/scripts/export_api_inventory.php` 扫描 Laravel 路由表导出，**不要手工编辑**。
>
> 需要更新本文件时，直接在项目根目录执行：`php backend/scripts/export_api_inventory.php`。
> 需要看业务分组、核心业务流程映射等人类可读导航，请查看 `文档/后端/API清单导航.md`。

| 分组 | 方法 | 路径 | 控制器动作 | 鉴权 | 中间件 |
| --- | --- | --- | --- | --- | --- |
| admin | `GET` | `/api/admin/auth/info` | `App\Http\Controllers\Admin\AuthController@info` | `admin` | `api, auth:sanctum, ensure.admin` |
| admin | `POST` | `/api/admin/auth/logout` | `App\Http\Controllers\Admin\AuthController@logout` | `admin` | `api, auth:sanctum, ensure.admin` |
| admin | `PUT` | `/api/admin/auth/profile` | `App\Http\Controllers\Admin\AuthController@updateProfile` | `admin` | `api, auth:sanctum, ensure.admin` |
| admin | `GET` | `/api/admin/content/articles` | `App\Http\Controllers\Admin\ContentArticleController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.list` |
| admin | `POST` | `/api/admin/content/articles` | `App\Http\Controllers\Admin\ContentArticleController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `DELETE` | `/api/admin/content/articles/{article}` | `App\Http\Controllers\Admin\ContentArticleController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/admin/content/articles/{article}` | `App\Http\Controllers\Admin\ContentArticleController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.list` |
| admin | `PUT` | `/api/admin/content/articles/{article}` | `App\Http\Controllers\Admin\ContentArticleController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/admin/content/categories` | `App\Http\Controllers\Admin\ContentCategoryController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.list` |
| admin | `POST` | `/api/admin/content/categories` | `App\Http\Controllers\Admin\ContentCategoryController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `DELETE` | `/api/admin/content/categories/{category}` | `App\Http\Controllers\Admin\ContentCategoryController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `PUT` | `/api/admin/content/categories/{category}` | `App\Http\Controllers\Admin\ContentCategoryController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/admin/content/summary` | `App\Http\Controllers\Admin\ContentArticleController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.list` |
| admin | `POST` | `/api/admin/content/upload-image` | `App\Http\Controllers\Admin\ContentArticleController@uploadImage` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/admin/coupon-campaigns` | `App\Http\Controllers\Admin\CouponCampaignController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/admin/coupon-campaigns` | `App\Http\Controllers\Admin\CouponCampaignController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/coupon-campaigns/summary` | `App\Http\Controllers\Admin\CouponCampaignController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `DELETE` | `/api/admin/coupon-campaigns/{couponCampaign}` | `App\Http\Controllers\Admin\CouponCampaignController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PUT` | `/api/admin/coupon-campaigns/{couponCampaign}` | `App\Http\Controllers\Admin\CouponCampaignController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/coupon-campaigns/{couponCampaign}/toggle-status` | `App\Http\Controllers\Admin\CouponCampaignController@toggleStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/coupon-campaigns/{couponCampaign}/trigger` | `App\Http\Controllers\Admin\CouponCampaignController@trigger` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/coupons` | `App\Http\Controllers\Admin\CouponController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/admin/coupons` | `App\Http\Controllers\Admin\CouponController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/coupons/product-tree` | `App\Http\Controllers\Admin\CouponController@productTree` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `GET` | `/api/admin/coupons/summary` | `App\Http\Controllers\Admin\CouponController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `DELETE` | `/api/admin/coupons/{coupon}` | `App\Http\Controllers\Admin\CouponController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PUT` | `/api/admin/coupons/{coupon}` | `App\Http\Controllers\Admin\CouponController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/coupons/{coupon}/toggle-status` | `App\Http\Controllers\Admin\CouponController@toggleStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/dashboard` | `App\Http\Controllers\Admin\DashboardController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:dashboard.view` |
| admin | `GET` | `/api/admin/dashboard/recent-invoices` | `App\Http\Controllers\Admin\DashboardController@recentInvoices` | `admin` | `api, auth:sanctum, ensure.admin, permission:dashboard.view` |
| admin | `GET` | `/api/admin/dashboard/stats` | `App\Http\Controllers\Admin\DashboardController@stats` | `admin` | `api, auth:sanctum, ensure.admin, permission:dashboard.view` |
| admin | `GET` | `/api/admin/invoices` | `App\Http\Controllers\Admin\InvoiceController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.list` |
| admin | `GET` | `/api/admin/invoices/{id}` | `App\Http\Controllers\Admin\InvoiceController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.detail` |
| admin | `POST` | `/api/admin/invoices/{id}/cancel` | `App\Http\Controllers\Admin\InvoiceController@cancel` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.manage` |
| admin | `POST` | `/api/admin/login` | `App\Http\Controllers\Admin\AuthController@login` | `public` | `api, throttle:5,1` |
| admin | `GET` | `/api/admin/logs/admin-logins` | `App\Http\Controllers\Admin\LogController@adminLoginLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/logs/api` | `App\Http\Controllers\Admin\LogController@apiLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `POST` | `/api/admin/logs/cleanup` | `App\Http\Controllers\Admin\LogController@cleanup` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/logs/cleanup/overview` | `App\Http\Controllers\Admin\LogController@cleanupOverview` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/logs/email` | `App\Http\Controllers\Admin\LogController@emailLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/logs/email/summary` | `App\Http\Controllers\Admin\LogController@emailLogsSummary` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/logs/sms` | `App\Http\Controllers\Admin\LogController@smsLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/logs/sms/summary` | `App\Http\Controllers\Admin\LogController@smsLogsSummary` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/logs/system` | `App\Http\Controllers\Admin\LogController@systemLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/logs/system/summary` | `App\Http\Controllers\Admin\LogController@systemLogsSummary` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/logs/tasks` | `App\Http\Controllers\Admin\LogController@taskLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/logs/tasks/summary` | `App\Http\Controllers\Admin\LogController@taskLogsSummary` | `admin` | `api, auth:sanctum, ensure.admin, permission:log.list` |
| admin | `GET` | `/api/admin/media-files` | `App\Http\Controllers\Admin\MediaFileController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `POST` | `/api/admin/media-files` | `App\Http\Controllers\Admin\MediaFileController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `DELETE` | `/api/admin/media-files/{mediaFile}` | `App\Http\Controllers\Admin\MediaFileController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:content.manage` |
| admin | `GET` | `/api/admin/member-levels` | `App\Http\Controllers\Admin\MemberLevelController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:member_level.manage` |
| admin | `POST` | `/api/admin/member-levels` | `App\Http\Controllers\Admin\MemberLevelController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:member_level.manage` |
| admin | `DELETE` | `/api/admin/member-levels/{memberLevel}` | `App\Http\Controllers\Admin\MemberLevelController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:member_level.manage` |
| admin | `PUT` | `/api/admin/member-levels/{memberLevel}` | `App\Http\Controllers\Admin\MemberLevelController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:member_level.manage` |
| admin | `GET` | `/api/admin/product-categories` | `App\Http\Controllers\Admin\ProductCategoryController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/admin/product-categories` | `App\Http\Controllers\Admin\ProductCategoryController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/product-categories/reorder` | `App\Http\Controllers\Admin\ProductCategoryController@reorder` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `DELETE` | `/api/admin/product-categories/{productCategory}` | `App\Http\Controllers\Admin\ProductCategoryController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PUT` | `/api/admin/product-categories/{productCategory}` | `App\Http\Controllers\Admin\ProductCategoryController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/product-groups` | `App\Http\Controllers\Admin\ProductCategoryController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/admin/product-groups` | `App\Http\Controllers\Admin\ProductCategoryController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/product-groups/reorder` | `App\Http\Controllers\Admin\ProductCategoryController@reorder` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `DELETE` | `/api/admin/product-groups/{productCategory}` | `App\Http\Controllers\Admin\ProductCategoryController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PUT` | `/api/admin/product-groups/{productCategory}` | `App\Http\Controllers\Admin\ProductCategoryController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/product-types` | `App\Http\Controllers\Admin\ProductTypeController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/admin/product-types` | `App\Http\Controllers\Admin\ProductTypeController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/product-types/reorder` | `App\Http\Controllers\Admin\ProductTypeController@reorder` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `DELETE` | `/api/admin/product-types/{productType}` | `App\Http\Controllers\Admin\ProductTypeController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PUT` | `/api/admin/product-types/{productType}` | `App\Http\Controllers\Admin\ProductTypeController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/products` | `App\Http\Controllers\Admin\ProductController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/admin/products` | `App\Http\Controllers\Admin\ProductController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/products/batch-sync` | `App\Http\Controllers\Admin\ProductController@batchSync` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/products/category/batch` | `App\Http\Controllers\Admin\ProductController@batchUpdateCategory` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/products/provision-hostname/batch` | `App\Http\Controllers\Admin\ProductController@batchUpdateProvisionHostname` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/products/reorder` | `App\Http\Controllers\Admin\ProductController@reorder` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/products/summary` | `App\Http\Controllers\Admin\ProductController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/admin/products/traffic-packages/pull` | `App\Http\Controllers\Admin\ProductController@pullTrafficPackageCatalog` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `DELETE` | `/api/admin/products/{product}` | `App\Http\Controllers\Admin\ProductController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/products/{product}` | `App\Http\Controllers\Admin\ProductController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `PUT` | `/api/admin/products/{product}` | `App\Http\Controllers\Admin\ProductController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/products/{product}/owners` | `App\Http\Controllers\Admin\ProductController@owners` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `PUT` | `/api/admin/products/{product}/sort-order` | `App\Http\Controllers\Admin\ProductController@updateSortOrder` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/products/{product}/toggle-status` | `App\Http\Controllers\Admin\ProductController@toggleStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/referral-withdrawals` | `App\Http\Controllers\Admin\ReferralWithdrawalController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:finance.withdraw` |
| admin | `POST` | `/api/admin/referral-withdrawals/{withdrawal}/approve` | `App\Http\Controllers\Admin\ReferralWithdrawalController@approve` | `admin` | `api, auth:sanctum, ensure.admin, permission:finance.withdraw` |
| admin | `POST` | `/api/admin/referral-withdrawals/{withdrawal}/reject` | `App\Http\Controllers\Admin\ReferralWithdrawalController@reject` | `admin` | `api, auth:sanctum, ensure.admin, permission:finance.withdraw` |
| admin | `GET` | `/api/admin/referral/account-logs` | `App\Http\Controllers\Admin\ReferralAccountLogController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:referral.list` |
| admin | `GET` | `/api/admin/referral/overview` | `App\Http\Controllers\Admin\ReferralController@overview` | `admin` | `api, auth:sanctum, ensure.admin, permission:referral.list` |
| admin | `GET` | `/api/admin/referral/rewards` | `App\Http\Controllers\Admin\ReferralRewardController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:referral.list` |
| admin | `GET` | `/api/admin/schedules/overview` | `App\Http\Controllers\Admin\ScheduleTaskController@overview` | `admin` | `api, auth:sanctum, ensure.admin, permission:settings.manage` |
| admin | `POST` | `/api/admin/schedules/trigger` | `App\Http\Controllers\Admin\ScheduleTaskController@trigger` | `admin` | `api, auth:sanctum, ensure.admin, permission:settings.manage` |
| admin | `GET` | `/api/admin/services` | `App\Http\Controllers\Admin\ServiceController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.list` |
| admin | `POST` | `/api/admin/services/custom-hostnames/batch` | `App\Http\Controllers\Admin\ServiceController@batchUpdateCustomHostnames` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/admin/settings` | `App\Http\Controllers\Admin\SettingController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:settings.manage` |
| admin | `POST` | `/api/admin/settings` | `App\Http\Controllers\Admin\SettingController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:settings.manage` |
| admin | `GET` | `/api/admin/site/home-hero` | `App\Http\Controllers\Admin\HomeHeroController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:settings.manage` |
| admin | `POST` | `/api/admin/site/home-hero` | `App\Http\Controllers\Admin\HomeHeroController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:settings.manage` |
| admin | `GET` | `/api/admin/suppliers` | `App\Http\Controllers\Admin\SupplierController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/suppliers` | `App\Http\Controllers\Admin\SupplierController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/suppliers/summary` | `App\Http\Controllers\Admin\SupplierController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `DELETE` | `/api/admin/suppliers/{supplier}` | `App\Http\Controllers\Admin\SupplierController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/suppliers/{supplier}` | `App\Http\Controllers\Admin\SupplierController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `PUT` | `/api/admin/suppliers/{supplier}` | `App\Http\Controllers\Admin\SupplierController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/suppliers/{supplier}/balance` | `App\Http\Controllers\Admin\SupplierController@balance` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/suppliers/{supplier}/products` | `App\Http\Controllers\Admin\SupplierController@products` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/suppliers/{supplier}/products/batch-connect` | `App\Http\Controllers\Admin\SupplierController@bulkConnectProducts` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/suppliers/{supplier}/products/{productId}/config-template` | `App\Http\Controllers\Admin\SupplierController@productConfigTemplate` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `POST` | `/api/admin/suppliers/{supplier}/toggle-status` | `App\Http\Controllers\Admin\SupplierController@toggleStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:product.manage` |
| admin | `GET` | `/api/admin/tickets` | `App\Http\Controllers\Admin\TicketController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.list` |
| admin | `GET` | `/api/admin/tickets/admin-users` | `App\Http\Controllers\Admin\TicketController@adminUsers` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.list` |
| admin | `GET` | `/api/admin/tickets/summary` | `App\Http\Controllers\Admin\TicketController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.list` |
| admin | `POST` | `/api/admin/tickets/upload-image` | `App\Http\Controllers\Admin\TicketController@uploadImage` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.reply` |
| admin | `GET` | `/api/admin/tickets/{ticket}` | `App\Http\Controllers\Admin\TicketController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.list` |
| admin | `POST` | `/api/admin/tickets/{ticket}/assign` | `App\Http\Controllers\Admin\TicketController@assign` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.manage` |
| admin | `POST` | `/api/admin/tickets/{ticket}/close` | `App\Http\Controllers\Admin\TicketController@close` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.manage` |
| admin | `POST` | `/api/admin/tickets/{ticket}/reply` | `App\Http\Controllers\Admin\TicketController@reply` | `admin` | `api, auth:sanctum, ensure.admin, permission:ticket.reply` |
| admin | `GET` | `/api/admin/users` | `App\Http\Controllers\Admin\UserController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.list` |
| admin | `POST` | `/api/admin/users` | `App\Http\Controllers\Admin\UserController@store` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `DELETE` | `/api/admin/users/{user}` | `App\Http\Controllers\Admin\UserController@destroy` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/admin/users/{user}` | `App\Http\Controllers\Admin\UserController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `PUT` | `/api/admin/users/{user}` | `App\Http\Controllers\Admin\UserController@update` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/admin/users/{user}/balance-logs` | `App\Http\Controllers\Admin\UserController@balanceLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/admin/users/{user}/email-logs` | `App\Http\Controllers\Admin\UserController@emailLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/admin/users/{user}/invoices` | `App\Http\Controllers\Admin\UserController@invoices` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/admin/users/{user}/invoices/{invoice}` | `App\Http\Controllers\Admin\UserController@invoiceDetail` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `POST` | `/api/admin/users/{user}/invoices/{invoice}/manual-entry` | `App\Http\Controllers\Admin\UserController@manualInvoiceEntry` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.manage` |
| admin | `POST` | `/api/admin/users/{user}/invoices/{invoice}/refund` | `App\Http\Controllers\Admin\UserController@refundInvoice` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.manage` |
| admin | `POST` | `/api/admin/users/{user}/invoices/{invoice}/send-email` | `App\Http\Controllers\Admin\UserController@sendInvoiceEmail` | `admin` | `api, auth:sanctum, ensure.admin, permission:invoice.manage` |
| admin | `POST` | `/api/admin/users/{user}/login-as` | `App\Http\Controllers\Admin\UserController@loginAs` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/admin/users/{user}/operation-logs` | `App\Http\Controllers\Admin\UserController@operationLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `POST` | `/api/admin/users/{user}/recharge` | `App\Http\Controllers\Admin\UserController@recharge` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.recharge` |
| admin | `GET` | `/api/admin/users/{user}/services` | `App\Http\Controllers\Admin\UserController@services` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `POST` | `/api/admin/users/{user}/services` | `App\Http\Controllers\Admin\UserController@storeService` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `POST` | `/api/admin/users/{user}/services/refresh-statuses` | `App\Http\Controllers\Admin\UserController@refreshServiceStatuses` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `DELETE` | `/api/admin/users/{user}/services/{serviceId}` | `App\Http\Controllers\Admin\UserController@destroyService` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/admin/users/{user}/services/{serviceId}` | `App\Http\Controllers\Admin\UserController@serviceDetail` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/admin/users/{user}/services/{serviceId}/base` | `App\Http\Controllers\Admin\UserController@serviceBaseDetail` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `PUT` | `/api/admin/users/{user}/services/{serviceId}/manual-provision` | `App\Http\Controllers\Admin\UserController@manualProvisionService` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `PUT` | `/api/admin/users/{user}/services/{serviceId}/meta` | `App\Http\Controllers\Admin\UserController@updateServiceMeta` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/admin/users/{user}/services/{serviceId}/module-status` | `App\Http\Controllers\Admin\UserController@serviceModuleStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `PUT` | `/api/admin/users/{user}/services/{serviceId}/password/reset` | `App\Http\Controllers\Admin\UserController@serviceResetPassword` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `POST` | `/api/admin/users/{user}/services/{serviceId}/power` | `App\Http\Controllers\Admin\UserController@servicePower` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `POST` | `/api/admin/users/{user}/services/{serviceId}/refund` | `App\Http\Controllers\Admin\UserController@refundService` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `PUT` | `/api/admin/users/{user}/services/{serviceId}/reinstall` | `App\Http\Controllers\Admin\UserController@serviceReinstall` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/admin/users/{user}/services/{serviceId}/reinstall/options` | `App\Http\Controllers\Admin\UserController@serviceReinstallOptions` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/admin/users/{user}/services/{serviceId}/remote-status` | `App\Http\Controllers\Admin\UserController@serviceRemoteStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/admin/users/{user}/sms-logs` | `App\Http\Controllers\Admin\UserController@smsLogs` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `GET` | `/api/admin/users/{user}/tickets` | `App\Http\Controllers\Admin\UserController@tickets` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.detail` |
| admin | `POST` | `/api/admin/users/{user}/toggle-status` | `App\Http\Controllers\Admin\UserController@toggleStatus` | `admin` | `api, auth:sanctum, ensure.admin, permission:user.manage` |
| admin | `GET` | `/api/admin/verifications` | `App\Http\Controllers\Admin\VerificationController@index` | `admin` | `api, auth:sanctum, ensure.admin, permission:verification.list` |
| admin | `GET` | `/api/admin/verifications/summary` | `App\Http\Controllers\Admin\VerificationController@summary` | `admin` | `api, auth:sanctum, ensure.admin, permission:verification.list` |
| admin | `GET` | `/api/admin/verifications/{user}` | `App\Http\Controllers\Admin\VerificationController@show` | `admin` | `api, auth:sanctum, ensure.admin, permission:verification.list` |
| admin | `GET` | `/api/admin/verifications/{user}/history` | `App\Http\Controllers\Admin\VerificationController@history` | `admin` | `api, auth:sanctum, ensure.admin, permission:verification.list` |
| admin | `POST` | `/api/admin/verifications/{user}/unbind` | `App\Http\Controllers\Admin\VerificationController@unbind` | `admin` | `api, auth:sanctum, ensure.admin, permission:verification.unbind` |
| client | `GET` | `/api/client/auth/alipay-account` | `App\Http\Controllers\Client\AuthController@alipayAccount` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/client/auth/alipay-account` | `App\Http\Controllers\Client\AuthController@updateAlipayAccount` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/auth/captcha-config` | `App\Http\Controllers\Client\AuthController@captchaConfig` | `public` | `api` |
| client | `GET` | `/api/client/auth/captcha-script` | `App\Http\Controllers\Client\AuthController@captchaScript` | `public` | `api` |
| client | `PUT` | `/api/client/auth/email` | `App\Http\Controllers\Client\AuthController@updateEmail` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/auth/email-code` | `App\Http\Controllers\Client\AuthController@sendEmailCode` | `public` | `api, throttle:6,1,client-auth-email-code` |
| client | `GET` | `/api/client/auth/info` | `App\Http\Controllers\Client\AuthController@info` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/auth/login-as/exchange` | `App\Http\Controllers\Client\AuthController@exchangeLoginAsCode` | `public` | `api, throttle:10,1,client-auth-login-as` |
| client | `POST` | `/api/client/auth/logout` | `App\Http\Controllers\Client\AuthController@logout` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/client/auth/notification-preferences` | `App\Http\Controllers\Client\AuthController@updateNotificationPreferences` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/client/auth/phone` | `App\Http\Controllers\Client\AuthController@updatePhone` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/auth/phone-code` | `App\Http\Controllers\Client\AuthController@sendPhoneCode` | `public` | `api, throttle:6,1,client-auth-phone-code` |
| client | `PUT` | `/api/client/auth/profile` | `App\Http\Controllers\Client\AuthController@updateProfile` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/auth/reset-password` | `App\Http\Controllers\Client\AuthController@resetPassword` | `public` | `api, throttle:5,1,client-auth-reset-password` |
| client | `GET` | `/api/client/balance-logs` | `App\Http\Controllers\Client\FinanceController@balanceLogs` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/balance-logs/summary` | `App\Http\Controllers\Client\FinanceController@balanceLogsSummary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/blackhole/query` | `App\Http\Controllers\Client\BlackholeController@query` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/content/overview` | `App\Http\Controllers\Client\ContentController@overview` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/coupons` | `App\Http\Controllers\Client\CouponController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/coupons/public` | `App\Http\Controllers\Client\CouponController@publicIndex` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/coupons/public/summary` | `App\Http\Controllers\Client\CouponController@publicSummary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/coupons/summary` | `App\Http\Controllers\Client\CouponController@summary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/coupons/{couponId}/claim` | `App\Http\Controllers\Client\CouponController@claim` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-coupons-claim` |
| client | `GET` | `/api/client/help-articles` | `App\Http\Controllers\Client\ContentController@helpArticles` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/help-articles/{articleId}` | `App\Http\Controllers\Client\ContentController@helpDetail` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/invoices` | `App\Http\Controllers\Client\InvoiceController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/invoices` | `App\Http\Controllers\Client\InvoiceController@store` | `client` | `api, auth:sanctum, ensure.client, throttle:8,1,client-invoices-store` |
| client | `GET` | `/api/client/invoices/summary` | `App\Http\Controllers\Client\InvoiceController@summary` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/invoices/{id}` | `App\Http\Controllers\Client\InvoiceController@show` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/invoices/{id}/cancel` | `App\Http\Controllers\Client\InvoiceController@cancel` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-invoices-cancel` |
| client | `POST` | `/api/client/invoices/{id}/pay/alipay` | `App\Http\Controllers\Client\InvoiceController@payByAlipay` | `client` | `api, auth:sanctum, ensure.client, throttle:12,1,client-invoices-pay-alipay` |
| client | `GET` | `/api/client/invoices/{id}/pay/alipay/status` | `App\Http\Controllers\Client\InvoiceController@queryAlipayStatus` | `client` | `api, auth:sanctum, ensure.client, throttle:30,1,client-invoices-pay-alipay-status` |
| client | `POST` | `/api/client/invoices/{id}/pay/balance` | `App\Http\Controllers\Client\InvoiceController@payByBalance` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-invoices-pay-balance` |
| client | `POST` | `/api/client/login` | `App\Http\Controllers\Client\AuthController@login` | `public` | `api, throttle:5,1,client-auth-login` |
| client | `GET` | `/api/client/notices` | `App\Http\Controllers\Client\ContentController@notices` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/notices/{articleId}` | `App\Http\Controllers\Client\ContentController@noticeDetail` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/client/password` | `App\Http\Controllers\Client\AuthController@updatePassword` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/payment/alipay/notify` | `App\Http\Controllers\Client\PaymentCallbackController@alipayNotify` | `public` | `api` |
| client | `POST` | `/api/client/recharge` | `App\Http\Controllers\Client\RechargeController@store` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-recharge-store` |
| client | `GET` | `/api/client/recharge/{paymentNo}/status` | `App\Http\Controllers\Client\RechargeController@status` | `client` | `api, auth:sanctum, ensure.client, throttle:30,1,client-recharge-status` |
| client | `GET` | `/api/client/referral/account-logs` | `App\Http\Controllers\Client\ReferralController@accountLogs` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/referral/overview` | `App\Http\Controllers\Client\ReferralController@overview` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/referral/rewards` | `App\Http\Controllers\Client\ReferralController@rewards` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/referral/withdrawals` | `App\Http\Controllers\Client\ReferralController@withdrawals` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/referral/withdrawals` | `App\Http\Controllers\Client\ReferralController@applyWithdrawal` | `client` | `api, auth:sanctum, ensure.client, throttle:3,1,client-referral-withdraw` |
| client | `POST` | `/api/client/register` | `App\Http\Controllers\Client\AuthController@register` | `public` | `api, throttle:5,1,client-auth-register` |
| client | `GET` | `/api/client/services` | `App\Http\Controllers\Client\ServiceController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/services/grouped-overview` | `App\Http\Controllers\Client\ServiceController@groupedOverview` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/services/{id}` | `App\Http\Controllers\Client\ServiceController@show` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/services/{id}/base` | `App\Http\Controllers\Client\ServiceController@baseDetail` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/services/{id}/config` | `App\Http\Controllers\Client\ServiceController@config` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/services/{id}/module-status` | `App\Http\Controllers\Client\ServiceController@moduleStatus` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/services/{id}/monitor` | `App\Http\Controllers\Client\ServiceController@monitor` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/services/{id}/monitor/batch` | `App\Http\Controllers\Client\ServiceController@monitorBatch` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/services/{id}/nat-forwardings` | `App\Http\Controllers\Client\ServiceController@natForwardings` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/services/{id}/nat-forwardings` | `App\Http\Controllers\Client\ServiceController@createNatForwarding` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-nat-create` |
| client | `DELETE` | `/api/client/services/{id}/nat-forwardings/{forwardingId}` | `App\Http\Controllers\Client\ServiceController@deleteNatForwarding` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-nat-delete` |
| client | `GET` | `/api/client/services/{id}/operation-logs` | `App\Http\Controllers\Client\ServiceController@operationLogs` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/client/services/{id}/password/reset` | `App\Http\Controllers\Client\ServiceController@resetPassword` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-password-reset` |
| client | `POST` | `/api/client/services/{id}/power` | `App\Http\Controllers\Client\ServiceController@power` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-power` |
| client | `PUT` | `/api/client/services/{id}/reinstall` | `App\Http\Controllers\Client\ServiceController@reinstall` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-reinstall` |
| client | `GET` | `/api/client/services/{id}/reinstall/options` | `App\Http\Controllers\Client\ServiceController@reinstallOptions` | `client` | `api, auth:sanctum, ensure.client` |
| client | `PUT` | `/api/client/services/{id}/remark` | `App\Http\Controllers\Client\ServiceController@updateRemark` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/services/{id}/remote-status` | `App\Http\Controllers\Client\ServiceController@remoteStatus` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/services/{id}/renew` | `App\Http\Controllers\Client\ServiceController@renewPreview` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/services/{id}/renew` | `App\Http\Controllers\Client\ServiceController@createRenewOrder` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-renew` |
| client | `PUT` | `/api/client/services/{id}/renew/auto` | `App\Http\Controllers\Client\ServiceController@updateAutoRenew` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-renew-auto` |
| client | `GET` | `/api/client/services/{id}/security-groups` | `App\Http\Controllers\Client\ServiceController@securityGroups` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/services/{id}/security-groups` | `App\Http\Controllers\Client\ServiceController@createSecurityGroup` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-security-group-create` |
| client | `DELETE` | `/api/client/services/{id}/security-groups/{groupId}` | `App\Http\Controllers\Client\ServiceController@deleteSecurityGroup` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-security-group-delete` |
| client | `POST` | `/api/client/services/{id}/security-groups/{groupId}/apply` | `App\Http\Controllers\Client\ServiceController@applySecurityGroup` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-security-group-apply` |
| client | `GET` | `/api/client/services/{id}/security-groups/{groupId}/rules` | `App\Http\Controllers\Client\ServiceController@securityGroupRules` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/services/{id}/security-groups/{groupId}/rules` | `App\Http\Controllers\Client\ServiceController@createSecurityRule` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-security-rule-create` |
| client | `DELETE` | `/api/client/services/{id}/security-groups/{groupId}/rules/{ruleId}` | `App\Http\Controllers\Client\ServiceController@deleteSecurityRule` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-service-security-rule-delete` |
| client | `GET` | `/api/client/services/{id}/traffic-packages` | `App\Http\Controllers\Client\ServiceController@trafficPackages` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/services/{id}/traffic-packages/order` | `App\Http\Controllers\Client\ServiceController@createTrafficPackageOrder` | `client` | `api, auth:sanctum, ensure.client, throttle:6,1,client-service-traffic-package-order` |
| client | `POST` | `/api/client/services/{id}/traffic-packages/quote` | `App\Http\Controllers\Client\ServiceController@quoteTrafficPackage` | `client` | `api, auth:sanctum, ensure.client, throttle:12,1,client-service-traffic-package-quote` |
| client | `GET` | `/api/client/services/{id}/vnc` | `App\Http\Controllers\Client\ServiceController@vnc` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/tickets` | `App\Http\Controllers\Client\TicketController@index` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/tickets` | `App\Http\Controllers\Client\TicketController@store` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-ticket-store` |
| client | `GET` | `/api/client/tickets/service-options` | `App\Http\Controllers\Client\TicketController@serviceOptions` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/tickets/upload-image` | `App\Http\Controllers\Client\TicketController@uploadImage` | `client` | `api, auth:sanctum, ensure.client, throttle:12,1,client-ticket-upload-image` |
| client | `GET` | `/api/client/tickets/{id}` | `App\Http\Controllers\Client\TicketController@show` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/tickets/{id}/close` | `App\Http\Controllers\Client\TicketController@close` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-ticket-close` |
| client | `POST` | `/api/client/tickets/{id}/reply` | `App\Http\Controllers\Client\TicketController@reply` | `client` | `api, auth:sanctum, ensure.client, throttle:10,1,client-ticket-reply` |
| client | `GET|POST` | `/api/client/verification/callback` | `App\Http\Controllers\Client\VerificationController@callback` | `public` | `api, verify.callback` |
| client | `GET` | `/api/client/verification/fee-config` | `App\Http\Controllers\Client\VerificationController@feeConfig` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/verification/init` | `App\Http\Controllers\Client\VerificationController@init` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/verification/qrcode` | `App\Http\Controllers\Client\VerificationController@qrcode` | `client` | `api, auth:sanctum, ensure.client` |
| client | `POST` | `/api/client/verification/restart` | `App\Http\Controllers\Client\VerificationController@restart` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/verification/scan` | `App\Http\Controllers\Client\VerificationController@scan` | `public` | `api` |
| client | `GET` | `/api/client/verification/status` | `App\Http\Controllers\Client\VerificationController@status` | `client` | `api, auth:sanctum, ensure.client` |
| client | `GET` | `/api/client/vnc-tokens/{token}` | `App\Http\Controllers\Client\ServiceController@vncToken` | `public` | `api, throttle:30,1,client-vnc-token` |
| site/public | `GET` | `/api/health` | `Closure` | `public` | `api` |
| site/public | `GET` | `/api/secure-assets/view` | `App\Http\Controllers\SecureAssetController@show` | `public` | `web, signed:relative` |
| site/public | `GET` | `/api/site/config` | `App\Http\Controllers\SiteConfigController@index` | `public` | `api` |
| site/public | `GET` | `/api/site/content/overview` | `App\Http\Controllers\SiteContentController@overview` | `public` | `api` |
| site/public | `GET` | `/api/site/help-articles` | `App\Http\Controllers\SiteContentController@helpArticles` | `public` | `api` |
| site/public | `GET` | `/api/site/help-articles/{articleId}` | `App\Http\Controllers\SiteContentController@helpDetail` | `public` | `api` |
| site/public | `GET` | `/api/site/home` | `App\Http\Controllers\SiteHomeController@index` | `public` | `api` |
| site/public | `GET` | `/api/site/home-hero` | `App\Http\Controllers\SiteHomeController@hero` | `public` | `api` |
| site/public | `GET` | `/api/site/notices` | `App\Http\Controllers\SiteContentController@notices` | `public` | `api` |
| site/public | `GET` | `/api/site/notices/{articleId}` | `App\Http\Controllers\SiteContentController@noticeDetail` | `public` | `api` |
| site/public | `GET` | `/api/site/product-categories` | `App\Http\Controllers\SiteProductController@productGroups` | `public` | `api` |
| site/public | `GET` | `/api/site/product-categories/{groupId}/catalog` | `App\Http\Controllers\SiteProductController@groupCatalog` | `public` | `api` |
| site/public | `GET` | `/api/site/product-categories/{groupId}/children` | `App\Http\Controllers\SiteProductController@childGroups` | `public` | `api` |
| site/public | `GET` | `/api/site/product-groups` | `App\Http\Controllers\SiteProductController@productGroups` | `public` | `api` |
| site/public | `GET` | `/api/site/product-groups/{groupId}/catalog` | `App\Http\Controllers\SiteProductController@groupCatalog` | `public` | `api` |
| site/public | `GET` | `/api/site/product-groups/{groupId}/children` | `App\Http\Controllers\SiteProductController@childGroups` | `public` | `api` |
| site/public | `GET` | `/api/site/product-types` | `App\Http\Controllers\SiteProductController@productTypes` | `public` | `api` |
| site/public | `GET` | `/api/site/products` | `App\Http\Controllers\SiteProductController@index` | `public` | `api` |
| site/public | `GET` | `/api/site/products/{productId}` | `App\Http\Controllers\SiteProductController@show` | `public` | `api` |
| site/public | `POST` | `/api/site/products/{productId}/quote` | `App\Http\Controllers\SiteProductController@quote` | `public` | `api, throttle:60,1` |
| site/public | `GET` | `/api/site/products/{productId}/stock` | `App\Http\Controllers\SiteProductController@stock` | `public` | `api` |
