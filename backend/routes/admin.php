<?php

use App\Http\Controllers\Admin\AdminPermissionCatalogController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ContentArticleController;
use App\Http\Controllers\Admin\ContentCategoryController;
use App\Http\Controllers\Admin\CouponCampaignController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CpuModelCatalogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceLedgerController;
use App\Http\Controllers\Admin\FinanceMenuController;
use App\Http\Controllers\Admin\HomeHeroController;
use App\Http\Controllers\Admin\InstanceSpecCatalogController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\MediaFileController;
use App\Http\Controllers\Admin\MemberLevelController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductTypeController;
use App\Http\Controllers\Admin\ReferralAccountLogController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\ReferralRewardController;
use App\Http\Controllers\Admin\ReferralWithdrawalController;
use App\Http\Controllers\Admin\ScheduleTaskController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationController;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 管理端 API 路由
|--------------------------------------------------------------------------
*/

// 管理员登录（无需认证）
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// 需要认证的路由
Route::middleware(['auth:sanctum', 'ensure.admin'])->group(function () {

    // 认证信息
    Route::get('/auth/info', [AuthController::class, 'info']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // 仪表盘
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:'.AdminPermissions::DASHBOARD_VIEW);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->middleware('permission:'.AdminPermissions::DASHBOARD_VIEW);
    Route::get('/dashboard/recent-invoices', [DashboardController::class, 'recentInvoices'])->middleware('permission:'.AdminPermissions::DASHBOARD_VIEW);
    Route::get('/dashboard/monthly-revenue', [DashboardController::class, 'monthlyRevenue'])->middleware('permission:'.AdminPermissions::DASHBOARD_VIEW);

    // 用户管理 - 读
    Route::middleware(['permission:'.AdminPermissions::USER_LIST])->group(function () {
        Route::get('/users', [UserController::class, 'index']);
    });

    Route::middleware(['permission:'.AdminPermissions::USER_DETAIL])->group(function () {
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::get('/users/{user}/services', [UserController::class, 'services']);
        Route::post('/users/{user}/services/refresh-statuses', [UserController::class, 'refreshServiceStatuses']);
        Route::get('/users/{user}/services/{serviceId}/base', [UserController::class, 'serviceBaseDetail']);
        Route::get('/users/{user}/services/{serviceId}/remote-status', [UserController::class, 'serviceRemoteStatus']);
        Route::get('/users/{user}/services/{serviceId}', [UserController::class, 'serviceDetail']);
        Route::get('/users/{user}/services/{serviceId}/module-status', [UserController::class, 'serviceModuleStatus']);
        Route::get('/users/{user}/services/{serviceId}/reinstall/options', [UserController::class, 'serviceReinstallOptions']);
        Route::get('/users/{user}/invoices', [UserController::class, 'invoices']);
        Route::get('/users/{user}/invoices/{invoice}', [UserController::class, 'invoiceDetail']);
        Route::get('/users/{user}/balance-logs', [UserController::class, 'balanceLogs']);
        Route::get('/users/{user}/tickets', [UserController::class, 'tickets']);
        Route::get('/users/{user}/operation-logs', [UserController::class, 'operationLogs']);
        Route::get('/users/{user}/sms-logs', [UserController::class, 'smsLogs']);
        Route::get('/users/{user}/email-logs', [UserController::class, 'emailLogs']);
    });

    Route::middleware(['permission:'.AdminPermissions::VERIFICATION_LIST])->group(function () {
        Route::get('/verifications', [VerificationController::class, 'index']);
        Route::get('/verifications/summary', [VerificationController::class, 'summary']);
        Route::get('/verifications/{user}/history', [VerificationController::class, 'history']);
        Route::get('/verifications/{user}', [VerificationController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::VERIFICATION_UNBIND])->group(function () {
        Route::post('/verifications/{user}/unbind', [VerificationController::class, 'unbind']);
    });

    // 用户管理 - 写
    Route::middleware(['permission:'.AdminPermissions::USER_MANAGE])->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('/users/{user}/login-as', [UserController::class, 'loginAs']);
        Route::post('/users/{user}/services', [UserController::class, 'storeService']);
        Route::delete('/users/{user}/services/{serviceId}', [UserController::class, 'destroyService']);
        Route::put('/users/{user}/services/{serviceId}/meta', [UserController::class, 'updateServiceMeta']);
        Route::put('/users/{user}/services/{serviceId}/manual-provision', [UserController::class, 'manualProvisionService']);
        Route::post('/users/{user}/services/{serviceId}/power', [UserController::class, 'servicePower']);
        Route::put('/users/{user}/services/{serviceId}/password/reset', [UserController::class, 'serviceResetPassword']);
        Route::put('/users/{user}/services/{serviceId}/reinstall', [UserController::class, 'serviceReinstall']);
        Route::post('/users/{user}/services/{serviceId}/refund', [UserController::class, 'refundService']);
        Route::post('/services/custom-hostnames/batch', [ServiceController::class, 'batchUpdateCustomHostnames']);
        Route::get('/os-options', [UserController::class, 'osOptions']);
    });

    Route::middleware(['permission:'.AdminPermissions::USER_RECHARGE])->group(function () {
        Route::post('/users/{user}/recharge', [UserController::class, 'recharge']);
    });

    Route::middleware(['permission:'.AdminPermissions::INVOICE_MANAGE])->group(function () {
        Route::post('/users/{user}/invoices/{invoice}/manual-entry', [UserController::class, 'manualInvoiceEntry']);
        Route::post('/users/{user}/invoices/{invoice}/send-email', [UserController::class, 'sendInvoiceEmail']);
        Route::post('/users/{user}/invoices/{invoice}/refund', [UserController::class, 'refundInvoice']);
    });

    // 订单管理（内部履约订单）
    Route::middleware(['permission:'.AdminPermissions::ORDER_LIST])->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
    });
    Route::middleware(['permission:'.AdminPermissions::ORDER_DETAIL])->group(function () {
        Route::get('/orders/{id}', [OrderController::class, 'show']);
    });

    // 账单管理（主实体）
    Route::middleware(['permission:'.AdminPermissions::INVOICE_LIST])->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/finance/recharges', [FinanceMenuController::class, 'recharges']);
        Route::get('/finance/renewal-orders', [FinanceMenuController::class, 'renewalOrders']);
        Route::get('/finance/addon-orders', [FinanceMenuController::class, 'addonOrders']);
        Route::get('/finance/ledger', [FinanceLedgerController::class, 'index']);
        Route::get('/finance/ledger/summary', [FinanceLedgerController::class, 'summary']);
    });
    Route::middleware(['permission:'.AdminPermissions::INVOICE_DETAIL])->group(function () {
        Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
        Route::get('/finance/ledger/{id}', [FinanceLedgerController::class, 'show']);
    });
    Route::middleware(['permission:'.AdminPermissions::INVOICE_MANAGE])->group(function () {
        Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel']);
    });

    Route::middleware(['permission:'.AdminPermissions::FINANCE_REPORT])->group(function () {
        Route::get('/finance/new-customer-daily-summary', [FinanceMenuController::class, 'newCustomerDailySummary']);
        Route::get('/finance/product-income-summary', [FinanceMenuController::class, 'productIncomeSummary']);
    });

    // 工单管理
    Route::middleware(['permission:'.AdminPermissions::PRODUCT_LIST])->group(function () {
        Route::get('/products/summary', [ProductController::class, 'summary']);
        Route::post('/products/traffic-packages/pull', [ProductController::class, 'pullTrafficPackageCatalog']);
        Route::get('/instance-spec-catalog', [InstanceSpecCatalogController::class, 'index']);
        Route::get('/cpu-model-catalog', [CpuModelCatalogController::class, 'index']);
        Route::get('/product-types', [ProductTypeController::class, 'index']);
        Route::get('/product-groups', [ProductCategoryController::class, 'index']);
        Route::get('/product-categories', [ProductCategoryController::class, 'index']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/coupons/product-tree', [CouponController::class, 'productTree']);
        Route::get('/coupons/summary', [CouponController::class, 'summary']);
        Route::get('/coupons', [CouponController::class, 'index']);
        Route::get('/coupon-campaigns/summary', [CouponCampaignController::class, 'summary']);
        Route::get('/coupon-campaigns', [CouponCampaignController::class, 'index']);
        Route::get('/products/{product}/owners', [ProductController::class, 'owners']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::get('/services', [ServiceController::class, 'index']);
    });

    Route::middleware(['permission:'.AdminPermissions::TICKET_LIST])->group(function () {
        Route::get('/tickets/summary', [TicketController::class, 'summary']);
        Route::get('/tickets/admin-users', [TicketController::class, 'adminUsers']);
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::TICKET_REPLY])->group(function () {
        Route::post('/tickets/upload-image', [TicketController::class, 'uploadImage']);
        Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply']);
        Route::post('/tickets/{ticket}/replies/{replyId}/recall', [TicketController::class, 'recall']);
    });

    Route::middleware(['permission:'.AdminPermissions::TICKET_MANAGE])->group(function () {
        Route::post('/tickets/{ticket}/close', [TicketController::class, 'close']);
        Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign']);
    });

    // 商品/供应商管理 - 写
    Route::middleware(['permission:'.AdminPermissions::PRODUCT_MANAGE])->group(function () {
        Route::post('/instance-spec-catalog', [InstanceSpecCatalogController::class, 'update']);
        Route::post('/cpu-model-catalog', [CpuModelCatalogController::class, 'update']);
        Route::post('/product-types/reorder', [ProductTypeController::class, 'reorder']);
        Route::post('/product-types', [ProductTypeController::class, 'store']);
        Route::put('/product-types/{productType}', [ProductTypeController::class, 'update']);
        Route::delete('/product-types/{productType}', [ProductTypeController::class, 'destroy']);
        Route::post('/products/batch-sync', [ProductController::class, 'batchSync']);
        Route::post('/products/split-preview', [ProductController::class, 'splitPreview']);
        Route::post('/products/split', [ProductController::class, 'split']);
        Route::post('/products/provision-hostname/batch', [ProductController::class, 'batchUpdateProvisionHostname']);
        Route::post('/products/category/batch', [ProductController::class, 'batchUpdateCategory']);
        Route::post('/products/reorder', [ProductController::class, 'reorder']);
        Route::post('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus']);
        Route::put('/products/{product}/sort-order', [ProductController::class, 'updateSortOrder']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::post('/coupons', [CouponController::class, 'store']);
        Route::put('/coupons/{coupon}', [CouponController::class, 'update']);
        Route::post('/coupons/{coupon}/toggle-status', [CouponController::class, 'toggleStatus']);
        Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy']);
        Route::post('/coupon-campaigns', [CouponCampaignController::class, 'store']);
        Route::put('/coupon-campaigns/{couponCampaign}', [CouponCampaignController::class, 'update']);
        Route::post('/coupon-campaigns/{couponCampaign}/toggle-status', [CouponCampaignController::class, 'toggleStatus']);
        Route::post('/coupon-campaigns/{couponCampaign}/trigger', [CouponCampaignController::class, 'trigger']);
        Route::delete('/coupon-campaigns/{couponCampaign}', [CouponCampaignController::class, 'destroy']);
        Route::post('/product-groups/reorder', [ProductCategoryController::class, 'reorder']);
        Route::post('/product-groups', [ProductCategoryController::class, 'store']);
        Route::put('/product-groups/{productCategory}', [ProductCategoryController::class, 'update']);
        Route::delete('/product-groups/{productCategory}', [ProductCategoryController::class, 'destroy']);
        Route::post('/product-categories/reorder', [ProductCategoryController::class, 'reorder']);
        Route::post('/product-categories', [ProductCategoryController::class, 'store']);
        Route::put('/product-categories/{productCategory}', [ProductCategoryController::class, 'update']);
        Route::delete('/product-categories/{productCategory}', [ProductCategoryController::class, 'destroy']);
        Route::get('/suppliers/summary', [SupplierController::class, 'summary']);
        Route::get('/suppliers/provider-types', [SupplierController::class, 'providerTypes']);
        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::get('/suppliers/{supplier}/balance', [SupplierController::class, 'balance']);
        Route::get('/suppliers/{supplier}/products', [SupplierController::class, 'products']);
        Route::post('/suppliers/{supplier}/products/batch-connect', [SupplierController::class, 'bulkConnectProducts']);
        Route::get('/suppliers/{supplier}/products/{productId}/config-template', [SupplierController::class, 'productConfigTemplate']);
        Route::post('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus']);
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);
    });

    // 系统配置
    Route::middleware(['permission:'.AdminPermissions::SETTINGS_MANAGE])->group(function () {
        Route::get('/settings', [SettingController::class, 'index']);
        Route::post('/settings', [SettingController::class, 'update']);
        Route::get('/schedules/overview', [ScheduleTaskController::class, 'overview']);
        Route::post('/schedules/trigger', [ScheduleTaskController::class, 'trigger']);
        Route::get('/site/home-hero', [HomeHeroController::class, 'show']);
        Route::post('/site/home-hero', [HomeHeroController::class, 'update']);
    });

    // 员工管理
    Route::middleware(['permission:'.AdminPermissions::STAFF_LIST])->group(function () {
        Route::get('/staff', [AdminStaffController::class, 'index']);
        Route::get('/staff/roles', [AdminStaffController::class, 'roles']);
        Route::get('/staff/{staff}', [AdminStaffController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::STAFF_MANAGE])->group(function () {
        Route::post('/staff', [AdminStaffController::class, 'store']);
        Route::put('/staff/{staff}', [AdminStaffController::class, 'update']);
        Route::post('/staff/{staff}/toggle-status', [AdminStaffController::class, 'toggleStatus']);
        Route::post('/staff/{staff}/reset-password', [AdminStaffController::class, 'resetPassword']);
    });

    // 角色与权限管理
    Route::middleware(['permission:'.AdminPermissions::PERMISSION_LIST])->group(function () {
        Route::get('/permissions', [AdminPermissionCatalogController::class, 'index']);
    });

    Route::middleware(['permission:'.AdminPermissions::ROLE_LIST])->group(function () {
        Route::get('/roles', [AdminRoleController::class, 'index']);
        Route::get('/roles/{role}', [AdminRoleController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::ROLE_MANAGE])->group(function () {
        Route::post('/roles', [AdminRoleController::class, 'store']);
        Route::put('/roles/{role}', [AdminRoleController::class, 'update']);
        Route::delete('/roles/{role}', [AdminRoleController::class, 'destroy']);
        Route::post('/roles/{role}/copy', [AdminRoleController::class, 'copy']);
    });

    // 日志管理
    Route::middleware(['permission:'.AdminPermissions::LOG_LIST])->group(function () {
        Route::get('/logs/sms', [LogController::class, 'smsLogs']);
        Route::get('/logs/sms/summary', [LogController::class, 'smsLogsSummary']);
        Route::get('/logs/email', [LogController::class, 'emailLogs']);
        Route::get('/logs/email/summary', [LogController::class, 'emailLogsSummary']);
        Route::get('/logs/api', [LogController::class, 'apiLogs']);
        Route::get('/logs/tasks', [LogController::class, 'taskLogs']);
        Route::get('/logs/tasks/summary', [LogController::class, 'taskLogsSummary']);
        Route::get('/logs/system', [LogController::class, 'systemLogs']);
        Route::get('/logs/system/summary', [LogController::class, 'systemLogsSummary']);
        Route::get('/logs/admin-logins', [LogController::class, 'adminLoginLogs']);
        Route::get('/logs/gateway', [LogController::class, 'gatewayLogs']);
        Route::get('/logs/activity', [LogController::class, 'activityLogs']);
        Route::get('/logs/schedule', [LogController::class, 'scheduleLogs']);
        Route::get('/logs/schedule/health', [LogController::class, 'scheduleHealth']);
        Route::get('/logs/cleanup/overview', [LogController::class, 'cleanupOverview']);
    });
    Route::middleware(['permission:'.AdminPermissions::LOG_MANAGE])->group(function () {
        Route::post('/logs/cleanup', [LogController::class, 'cleanup']);
    });

    // 推荐奖励
    Route::middleware(['permission:'.AdminPermissions::REFERRAL_LIST])->group(function () {
        Route::get('/referral/overview', [ReferralController::class, 'overview']);
        Route::get('/referral/rewards', [ReferralRewardController::class, 'index']);
        Route::get('/referral/account-logs', [ReferralAccountLogController::class, 'index']);
    });

    // 会员等级
    Route::middleware(['permission:'.AdminPermissions::MEMBER_LEVEL_MANAGE])->group(function () {
        Route::get('/member-levels', [MemberLevelController::class, 'index']);
        Route::post('/member-levels', [MemberLevelController::class, 'store']);
        Route::put('/member-levels/{memberLevel}', [MemberLevelController::class, 'update']);
        Route::delete('/member-levels/{memberLevel}', [MemberLevelController::class, 'destroy']);
    });

    // 推荐奖励提现
    Route::middleware(['permission:'.AdminPermissions::FINANCE_WITHDRAW])->group(function () {
        Route::get('/referral-withdrawals', [ReferralWithdrawalController::class, 'index']);
        Route::post('/referral-withdrawals/{withdrawal}/approve', [ReferralWithdrawalController::class, 'approve']);
        Route::post('/referral-withdrawals/{withdrawal}/reject', [ReferralWithdrawalController::class, 'reject']);
    });

    // 内容中心
    Route::middleware(['permission:'.AdminPermissions::CONTENT_LIST])->group(function () {
        Route::get('/content/summary', [ContentArticleController::class, 'summary']);
        Route::get('/content/categories', [ContentCategoryController::class, 'index']);
        Route::get('/content/articles', [ContentArticleController::class, 'index']);
        Route::get('/content/articles/{article}', [ContentArticleController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::CONTENT_MANAGE])->group(function () {
        Route::post('/content/categories', [ContentCategoryController::class, 'store']);
        Route::put('/content/categories/{category}', [ContentCategoryController::class, 'update']);
        Route::delete('/content/categories/{category}', [ContentCategoryController::class, 'destroy']);
        Route::post('/content/articles', [ContentArticleController::class, 'store']);
        Route::put('/content/articles/{article}', [ContentArticleController::class, 'update']);
        Route::delete('/content/articles/{article}', [ContentArticleController::class, 'destroy']);
        Route::post('/content/upload-image', [ContentArticleController::class, 'uploadImage']);
        Route::get('/media-files', [MediaFileController::class, 'index']);
        Route::post('/media-files', [MediaFileController::class, 'store']);
        Route::delete('/media-files/{mediaFile}', [MediaFileController::class, 'destroy']);
    });
});
