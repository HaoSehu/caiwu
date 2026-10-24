<?php

use App\Http\Controllers\Admin\V2\AuthController;
use App\Http\Controllers\Admin\V2\ContentArticleController;
use App\Http\Controllers\Admin\V2\ContentCategoryController;
use App\Http\Controllers\Admin\V2\CouponCampaignController;
use App\Http\Controllers\Admin\V2\CouponController;
use App\Http\Controllers\Admin\V2\CouponProductGroupController;
use App\Http\Controllers\Admin\V2\CpuModelCatalogController;
use App\Http\Controllers\Admin\V2\DashboardController;
use App\Http\Controllers\Admin\V2\DatabaseStatusController;
use App\Http\Controllers\Admin\V2\FinanceLedgerController;
use App\Http\Controllers\Admin\V2\FinanceMenuController;
use App\Http\Controllers\Admin\V2\HomeHeroController;
use App\Http\Controllers\Admin\V2\InstanceSpecCatalogController;
use App\Http\Controllers\Admin\V2\IntegrationPluginController;
use App\Http\Controllers\Admin\V2\InvoiceController;
use App\Http\Controllers\Admin\V2\LogController;
use App\Http\Controllers\Admin\V2\MarketingProductGroupController;
use App\Http\Controllers\Admin\V2\MediaFileController;
use App\Http\Controllers\Admin\V2\MemberLevelController;
use App\Http\Controllers\Admin\V2\MemberLevelGroupDiscountController;
use App\Http\Controllers\Admin\V2\OrderController;
use App\Http\Controllers\Admin\V2\ProductController;
use App\Http\Controllers\Admin\V2\ProductGroupController;
use App\Http\Controllers\Admin\V2\ProductTypeController;
use App\Http\Controllers\Admin\V2\PromotionAmbassadorController;
use App\Http\Controllers\Admin\V2\ReferralController;
use App\Http\Controllers\Admin\V2\ReferralWithdrawalController;
use App\Http\Controllers\Admin\V2\RoleController;
use App\Http\Controllers\Admin\V2\ScheduleTaskController;
use App\Http\Controllers\Admin\V2\ServiceController;
use App\Http\Controllers\Admin\V2\SettingController;
use App\Http\Controllers\Admin\V2\StaffController;
use App\Http\Controllers\Admin\V2\SupplierController;
use App\Http\Controllers\Admin\V2\SystemReadinessController;
use App\Http\Controllers\Admin\V2\TicketController;
use App\Http\Controllers\Admin\V2\UserController;
use App\Http\Controllers\Admin\V2\UserServiceController;
use App\Http\Controllers\Admin\V2\VerificationController;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1,v2-admin-login');

// 双因素第二步独立限流：按 IP 每分钟 10 次，既容许输错重试，又不给 6 位码留枚举空间。
Route::post('/login/mfa', [AuthController::class, 'verifyMfa'])->middleware('throttle:10,1,v2-admin-login-mfa');

Route::middleware(['auth:sanctum', 'ensure.admin'])->group(function (): void {
    Route::get('/auth/info', [AuthController::class, 'info']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // 双因素绑定/解绑：绑定本身不敏感，但解绑等于削弱账号安全，故单独限流。
    // setup 也在限流之列：每次调用都会生成新密钥并覆盖待启用状态，不限流的话
    // 一个被盗用的会话可以反复刷写，把管理员正在扫码绑定的密钥挤掉。
    Route::post('/auth/mfa/setup', [AuthController::class, 'setupMfa'])->middleware('throttle:10,1,v2-admin-mfa-setup');
    Route::post('/auth/mfa/enable', [AuthController::class, 'enableMfa'])->middleware('throttle:10,1,v2-admin-mfa-enable');
    Route::post('/auth/mfa/disable', [AuthController::class, 'disableMfa'])->middleware('throttle:10,1,v2-admin-mfa-disable');

    Route::middleware(['permission:'.AdminPermissions::DASHBOARD_VIEW])->group(function (): void {
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/recent-invoices', [DashboardController::class, 'recentInvoices']);
        Route::get('/dashboard/monthly-revenue', [DashboardController::class, 'monthlyRevenue']);
    });

    Route::middleware(['permission:'.AdminPermissions::ORDER_LIST])->group(function (): void {
        Route::get('/orders', [OrderController::class, 'index']);
    });

    Route::middleware(['permission:'.AdminPermissions::ORDER_DETAIL])->group(function (): void {
        Route::get('/orders/{order}', [OrderController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::INVOICE_LIST])->group(function (): void {
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/finance/ledger', [FinanceLedgerController::class, 'index']);
        Route::get('/finance/ledger/summary', [FinanceLedgerController::class, 'summary']);
        Route::get('/finance/recharges', [FinanceMenuController::class, 'recharges']);
        Route::get('/finance/renewal-orders', [FinanceMenuController::class, 'renewalOrders']);
        Route::get('/finance/upgrade-orders', [FinanceMenuController::class, 'upgradeOrders']);
    });

    Route::middleware(['permission:'.AdminPermissions::INVOICE_DETAIL])->group(function (): void {
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::get('/finance/ledger/{id}', [FinanceLedgerController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::INVOICE_MANAGE])->group(function (): void {
        Route::post('/invoices/{invoice}/cancellations', [InvoiceController::class, 'cancel'])
            ->middleware('throttle:20,1,admin-invoice-cancel');
    });

    // 补录为资金写入入口，用默认不下发的专用权限码把关，与 invoice.manage/order.manage 隔离。
    Route::middleware(['permission:'.AdminPermissions::INVOICE_MANUAL_ENTRY])->group(function (): void {
        Route::post('/users/{user}/manual-invoices', [UserController::class, 'storeManualInvoice'])
            ->middleware('throttle:20,1,admin-manual-invoice');
    });

    Route::middleware(['permission:'.AdminPermissions::ORDER_MANUAL_ENTRY])->group(function (): void {
        Route::post('/users/{user}/manual-orders', [UserController::class, 'storeManualOrder'])
            ->middleware('throttle:20,1,admin-manual-order');
    });

    // 补录红字冲抵：已入账补录账单的纠错通道，独立权限码把关，与 invoice.manage/补录权限隔离。
    Route::middleware(['permission:'.AdminPermissions::FINANCE_MANUAL_REVERSAL])->group(function (): void {
        Route::post('/users/{user}/invoices/{invoice}/manual-reversal', [UserController::class, 'storeManualReversal'])
            ->middleware('throttle:20,1,admin-manual-reversal');
    });

    // 退款到余额（唯一通道，动资金：加余额 + 台账 + 批次 + 红字账单 + 充值凭证）。
    // 2026-10-20 裁决：移除 manual_mark 纯标记通道，账单侧与订单侧统一走本组。
    Route::middleware(['permission:'.AdminPermissions::FINANCE_REFUND])->group(function (): void {
        Route::post('/invoices/{invoice}/refunds', [InvoiceController::class, 'refundToBalance'])
            ->middleware('throttle:20,1,admin-invoice-refund-balance');
        Route::post('/orders/{order}/refunds', [OrderController::class, 'refundToBalance'])
            ->middleware('throttle:20,1,admin-order-refund-balance');
    });

    Route::middleware(['permission:'.AdminPermissions::FINANCE_REPORT])->group(function (): void {
        Route::get('/finance/new-customer-daily-summary', [FinanceMenuController::class, 'newCustomerDailySummary']);
        Route::get('/finance/product-income-summary', [FinanceMenuController::class, 'productIncomeSummary']);
    });

    Route::middleware(['permission:'.AdminPermissions::USER_LIST])->group(function (): void {
        Route::get('/users', [UserController::class, 'index']);
    });

    Route::middleware(['permission:'.AdminPermissions::USER_DETAIL])->group(function (): void {
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::get('/users/{user}/services', [UserServiceController::class, 'index']);
        Route::get('/users/{user}/invoices', [UserController::class, 'invoices']);
        Route::get('/users/{user}/invoices/{invoice}', [UserController::class, 'invoiceDetail']);
        Route::get('/users/{user}/orders', [UserController::class, 'orders']);
        Route::get('/users/{user}/recharge-records', [UserController::class, 'rechargeRecords']);
        Route::get('/users/{user}/tickets', [UserController::class, 'tickets']);
        Route::get('/users/{user}/operation-logs', [UserController::class, 'operationLogs']);
        Route::get('/users/{user}/sms-logs', [UserController::class, 'smsLogs']);
        Route::get('/users/{user}/email-logs', [UserController::class, 'emailLogs']);
        Route::get('/users/{user}/services/{service}/connection', [UserServiceController::class, 'connection']);
        Route::get('/users/{user}/services/{service}/remote-status', [UserServiceController::class, 'remoteStatus']);
        Route::get('/users/{user}/services/{service}', [UserServiceController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::USER_MANAGE])->group(function (): void {
        Route::get('/os-options', [UserController::class, 'osOptions']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
        Route::patch('/users/{user}/member-level', [UserController::class, 'adjustMemberLevel']);
        Route::patch('/users/{user}/promotion-ambassador', [UserController::class, 'adjustPromotionAmbassador']);
        Route::post('/services/custom-hostnames/batch', [ServiceController::class, 'batchUpdateCustomHostnames']);
        Route::post('/users/{user}/services', [UserServiceController::class, 'store']);
        Route::post('/users/{user}/services/refresh-statuses', [UserServiceController::class, 'refreshStatuses']);
        Route::put('/users/{user}/services/{service}/meta', [UserServiceController::class, 'updateMeta']);
        Route::put('/users/{user}/services/{service}/manual-provision', [UserServiceController::class, 'manualProvision']);
        Route::delete('/users/{user}/services/{service}', [UserServiceController::class, 'destroy']);
        Route::post('/users/{user}/services/{service}/power-actions', [UserController::class, 'servicePower']);
        Route::post('/users/{user}/services/{service}/password-resets', [UserController::class, 'resetServicePassword']);
    });

    Route::middleware(['permission:'.AdminPermissions::USER_LOGIN_AS])->group(function (): void {
        Route::post('/users/{user}/login-as', [UserController::class, 'loginAs']);
    });

    Route::middleware(['permission:'.AdminPermissions::USER_RECHARGE])->group(function (): void {
        // 余额调整是最重的资金写口（入账 + 台账 + 批次 + 账单 + 凭证），单独限流，
        // 与 Client 端「全部财务写均有命名限流器」对齐；保留原全局 api 兜底。
        Route::post('/users/{user}/recharges', [UserController::class, 'recharge'])
            ->middleware('throttle:10,1,admin-user-recharge');
    });

    Route::middleware(['permission:'.AdminPermissions::VERIFICATION_LIST])->group(function (): void {
        Route::get('/verifications', [VerificationController::class, 'index']);
        Route::get('/verifications/summary', [VerificationController::class, 'summary']);
        Route::get('/verifications/{user}/history', [VerificationController::class, 'history']);
        Route::get('/verifications/{user}', [VerificationController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::VERIFICATION_UNBIND])->group(function (): void {
        Route::post('/verifications/{user}/unbindings', [VerificationController::class, 'unbind']);
    });

    Route::middleware(['permission:'.AdminPermissions::TICKET_LIST])->group(function (): void {
        Route::get('/tickets/summary', [TicketController::class, 'summary']);
        Route::get('/tickets/admin-users', [TicketController::class, 'adminUsers']);
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/{ticket}/replies', [TicketController::class, 'replies']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::TICKET_MANAGE])->group(function (): void {
        Route::post('/tickets/{ticket}/closures', [TicketController::class, 'close']);
        Route::post('/tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->middleware('throttle:10,1,admin-ticket-reopen');
        Route::put('/tickets/{ticket}/assignment', [TicketController::class, 'assign']);
    });

    Route::middleware(['permission:'.AdminPermissions::TICKET_REPLY])->group(function (): void {
        Route::post('/tickets/upload-images', [TicketController::class, 'uploadImage']);
        Route::post('/tickets/{ticket}/replies', [TicketController::class, 'reply']);
        Route::post('/tickets/{ticket}/replies/{replyId}/recalls', [TicketController::class, 'recall']);
    });

    Route::middleware(['permission:'.AdminPermissions::PRODUCT_LIST])->group(function (): void {
        Route::get('/instance-spec-catalog', [InstanceSpecCatalogController::class, 'index']);
        Route::get('/cpu-model-catalog', [CpuModelCatalogController::class, 'index']);

        Route::get('/coupon-product-groups', [CouponProductGroupController::class, 'index']);
        Route::get('/coupon-product-groups/{group}/children', [CouponProductGroupController::class, 'children']);
        Route::get('/coupon-product-groups/{group}/products', [CouponProductGroupController::class, 'products']);
        Route::post('/coupon-product-groups/batch-products', [CouponProductGroupController::class, 'batchProducts']);

        Route::get('/product-groups', [ProductGroupController::class, 'index']);
        Route::get('/product-groups/tree', [ProductGroupController::class, 'tree']);
        Route::get('/product-groups/{group}/children', [ProductGroupController::class, 'children']);
        Route::get('/product-groups/{group}', [ProductGroupController::class, 'show']);
        Route::get('/product-types', [ProductTypeController::class, 'index']);

        Route::get('/products/summary', [ProductController::class, 'summary']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}/owners', [ProductController::class, 'owners']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::get('/services', [ServiceController::class, 'index']);
    });

    // 券读取与商品目录解耦：只读岗持 coupon.list 即可查看券/活动列表与汇总
    Route::middleware(['permission:'.AdminPermissions::COUPON_LIST])->group(function (): void {
        Route::get('/coupons/summary', [CouponController::class, 'summary']);
        Route::get('/coupons', [CouponController::class, 'index']);
        Route::get('/coupon-campaigns/summary', [CouponCampaignController::class, 'summary']);
        Route::get('/coupon-campaigns', [CouponCampaignController::class, 'index']);
    });

    // 券与活动生命周期写操作迁入 coupon.manage（原与商品目录共用 PRODUCT_MANAGE）；
    // 定向发放 coupon.grant / 高额发放 coupon.high_value_grant 暂预留常量、无独立路由入口
    Route::middleware(['permission:'.AdminPermissions::COUPON_MANAGE])->group(function (): void {
        Route::post('/coupons', [CouponController::class, 'store']);
        Route::put('/coupons/{coupon}', [CouponController::class, 'update']);
        Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy']);
        Route::patch('/coupons/{coupon}/status', [CouponController::class, 'updateStatus']);
        Route::post('/coupon-campaigns', [CouponCampaignController::class, 'store']);
        Route::put('/coupon-campaigns/{couponCampaign}', [CouponCampaignController::class, 'update']);
        Route::delete('/coupon-campaigns/{couponCampaign}', [CouponCampaignController::class, 'destroy']);
        Route::patch('/coupon-campaigns/{couponCampaign}/status', [CouponCampaignController::class, 'updateStatus']);
        Route::post('/coupon-campaigns/{couponCampaign}/tasks', [CouponCampaignController::class, 'runTask'])
            ->middleware('throttle:10,1,admin-coupon-campaign-task');
    });

    Route::middleware(['permission:'.AdminPermissions::PRODUCT_MANAGE])->group(function (): void {
        Route::post('/instance-spec-catalog', [InstanceSpecCatalogController::class, 'update']);
        Route::post('/cpu-model-catalog', [CpuModelCatalogController::class, 'update']);
        Route::post('/product-types/reorders', [ProductTypeController::class, 'reorder']);
        Route::post('/product-types', [ProductTypeController::class, 'store']);
        Route::put('/product-types/{productType}', [ProductTypeController::class, 'update']);
        Route::delete('/product-types/{productType}', [ProductTypeController::class, 'destroy']);
        Route::post('/product-groups/reorders', [ProductGroupController::class, 'reorder']);
        Route::post('/product-groups', [ProductGroupController::class, 'store']);
        Route::put('/product-groups/{group}', [ProductGroupController::class, 'update']);
        Route::delete('/product-groups/{group}', [ProductGroupController::class, 'destroy']);
        Route::get('/marketing-product-groups', [MarketingProductGroupController::class, 'index']);
        Route::get('/marketing-product-groups/{marketingProductGroup}', [MarketingProductGroupController::class, 'show']);
        Route::post('/marketing-product-groups', [MarketingProductGroupController::class, 'store']);
        Route::put('/marketing-product-groups/{marketingProductGroup}', [MarketingProductGroupController::class, 'update']);
        Route::delete('/marketing-product-groups/{marketingProductGroup}', [MarketingProductGroupController::class, 'destroy']);
        Route::put('/marketing-product-groups/{marketingProductGroup}/products', [MarketingProductGroupController::class, 'syncProducts']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::post('/products/{product}/restorations', [ProductController::class, 'restore']);
        Route::delete('/products/{product}/force', [ProductController::class, 'forceDelete']);
        Route::post('/products/reorders', [ProductController::class, 'reorder']);
        Route::post('/products/split-previews', [ProductController::class, 'splitPreview']);
        Route::post('/products/splits', [ProductController::class, 'split']);
        Route::post('/products/category-batches', [ProductController::class, 'batchUpdateCategory']);
        Route::post('/products/provision-hostname-batches', [ProductController::class, 'batchUpdateProvisionHostname']);
        Route::patch('/products/{product}/status', [ProductController::class, 'updateStatus']);
    });

    Route::middleware(['permission:'.AdminPermissions::PRODUCT_SYNC])->group(function (): void {
        Route::post('/products/traffic-package-pulls', [ProductController::class, 'pullTrafficPackageCatalog']);
    });

    Route::middleware(['permission:'.AdminPermissions::LOG_LIST])->group(function (): void {
        Route::get('/logs/{channel}', [LogController::class, 'index']);
        Route::get('/logs/{channel}/{log}', [LogController::class, 'show']);
        Route::get('/log-summaries/{channel}', [LogController::class, 'summary']);
        Route::get('/log-cleanups/overview', [LogController::class, 'cleanupOverview']);
    });

    Route::middleware(['permission:'.AdminPermissions::LOG_MANAGE])->group(function (): void {
        Route::post('/log-cleanups', [LogController::class, 'cleanup']);
    });

    Route::middleware(['permission:'.AdminPermissions::SCHEDULE_VIEW])->group(function (): void {
        Route::get('/schedules/overview', [ScheduleTaskController::class, 'overview']);
        Route::get('/schedule-runs', [ScheduleTaskController::class, 'index']);
        Route::get('/schedule-runs/{run}', [ScheduleTaskController::class, 'show']);
        Route::get('/schedule-queue-jobs', [ScheduleTaskController::class, 'queueJobs']);
    });

    Route::middleware(['permission:'.AdminPermissions::SCHEDULE_TRIGGER])->group(function (): void {
        Route::post('/schedule-triggers', [ScheduleTaskController::class, 'trigger']);
    });

    Route::middleware(['permission:'.AdminPermissions::SCHEDULE_RETRY])->group(function (): void {
        Route::post('/schedule-runs/{run}/retry', [ScheduleTaskController::class, 'retry']);
    });

    Route::middleware(['permission:'.AdminPermissions::SCHEDULE_QUEUE_MANAGE])->group(function (): void {
        Route::delete('/schedule-queue-jobs/{queue}/{jobId}', [ScheduleTaskController::class, 'deleteQueueJob'])
            ->where(['queue' => 'pending|failed', 'jobId' => '[0-9]+']);
        Route::delete('/schedule-queue-jobs/{queue}', [ScheduleTaskController::class, 'clearQueueJobs'])
            ->where('queue', 'pending|failed');
    });

    // 站点首页外观独立于文章内容管理：横幅与视频由 site.* 把关，避免内容管理员越权修改首页
    Route::middleware(['permission:'.AdminPermissions::SITE_MANAGE])->group(function (): void {
        Route::post('/site/home-hero', [HomeHeroController::class, 'update']);
    });

    Route::middleware(['permission:'.AdminPermissions::SITE_VIEW])->group(function (): void {
        Route::get('/site/home-hero', [HomeHeroController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::CONTENT_MANAGE])->group(function (): void {
        Route::post('/content/categories', [ContentCategoryController::class, 'store']);
        Route::put('/content/categories/{category}', [ContentCategoryController::class, 'update']);
        Route::delete('/content/categories/{category}', [ContentCategoryController::class, 'destroy']);
        Route::post('/content/articles', [ContentArticleController::class, 'store']);
        Route::put('/content/articles/{article}', [ContentArticleController::class, 'update']);
        Route::delete('/content/articles/{article}', [ContentArticleController::class, 'destroy']);
        Route::post('/media-files', [MediaFileController::class, 'store']);
        Route::delete('/media-files/{mediaFile}', [MediaFileController::class, 'destroy']);
        Route::post('/media-file-reindexes', [MediaFileController::class, 'reindex']);
    });

    Route::middleware(['permission:'.AdminPermissions::CONTENT_LIST])->group(function (): void {
        Route::get('/content/summary', [ContentArticleController::class, 'summary']);
        Route::get('/content/categories', [ContentCategoryController::class, 'index']);
        Route::get('/content/articles', [ContentArticleController::class, 'index']);
        Route::get('/content/articles/{article}', [ContentArticleController::class, 'show']);
        Route::get('/media-files', [MediaFileController::class, 'index']);
        Route::get('/media-files/{mediaFile}/references', [MediaFileController::class, 'references']);
    });

    Route::middleware(['permission:'.AdminPermissions::MEMBER_LEVEL_LIST])->group(function (): void {
        Route::get('/member-levels', [MemberLevelController::class, 'index']);
        Route::get('/member-levels/{memberLevel}/group-discounts', [MemberLevelGroupDiscountController::class, 'index']);
    });

    Route::middleware(['permission:'.AdminPermissions::MEMBER_LEVEL_MANAGE])->group(function (): void {
        Route::post('/member-levels', [MemberLevelController::class, 'store']);
        Route::put('/member-levels/{memberLevel}', [MemberLevelController::class, 'update']);
        Route::delete('/member-levels/{memberLevel}', [MemberLevelController::class, 'destroy']);
        Route::put('/member-levels/{memberLevel}/group-discounts', [MemberLevelGroupDiscountController::class, 'sync']);
    });

    Route::middleware(['permission:'.AdminPermissions::PROMOTION_AMBASSADOR_LIST])->group(function (): void {
        Route::get('/promotion-ambassadors', [PromotionAmbassadorController::class, 'index']);
    });

    Route::middleware(['permission:'.AdminPermissions::PROMOTION_AMBASSADOR_MANAGE])->group(function (): void {
        Route::post('/promotion-ambassadors', [PromotionAmbassadorController::class, 'store']);
        Route::put('/promotion-ambassadors/{promotionAmbassador}', [PromotionAmbassadorController::class, 'update']);
        Route::delete('/promotion-ambassadors/{promotionAmbassador}', [PromotionAmbassadorController::class, 'destroy']);
    });

    Route::middleware(['permission:'.AdminPermissions::INTEGRATION_PLUGIN_VIEW])->group(function (): void {
        Route::get('/integration-plugins', [IntegrationPluginController::class, 'index']);
        Route::get('/integration-plugins/{plugin}', [IntegrationPluginController::class, 'show']);
        Route::get('/integration-plugins/{plugin}/schema', [IntegrationPluginController::class, 'schema']);
    });

    Route::middleware(['permission:'.AdminPermissions::INTEGRATION_PLUGIN_MANAGE])->group(function (): void {
        Route::post('/integration-plugins', [IntegrationPluginController::class, 'install']);
        Route::post('/integration-plugin-scans', [IntegrationPluginController::class, 'scan']);
        Route::put('/integration-plugins/{plugin}/config', [IntegrationPluginController::class, 'updateConfig']);
        Route::patch('/integration-plugins/{plugin}/status', [IntegrationPluginController::class, 'updateStatus']);
        Route::delete('/integration-plugins/{plugin}', [IntegrationPluginController::class, 'destroy']);
        // 升级会重写插件清单与配置结构（涉及资金插件的密钥/网关配置），
        // 控制器与 Service 早已实现但此前漏注册路由，前端无法触达；限流防止误连点。
        Route::post('/integration-plugins/{plugin}/upgrades', [IntegrationPluginController::class, 'upgrade'])
            ->middleware('throttle:10,1,admin-integration-plugin-upgrade');
    });

    Route::middleware(['permission:'.AdminPermissions::INTEGRATION_PLUGIN_TEST])->group(function (): void {
        // 测试发送会真实触发短信/邮件，需节流防止对任意收件人轰炸（与密钥 reveal 同级限流）。
        Route::post('/integration-plugins/{plugin}/tasks', [IntegrationPluginController::class, 'runTask'])->middleware('throttle:10,1');
    });

    Route::middleware(['permission:'.AdminPermissions::INTEGRATION_PLUGIN_SECRET_REVEAL])->group(function (): void {
        Route::get('/integration-plugins/{plugin}/secrets/{key}', [IntegrationPluginController::class, 'revealSecret'])->middleware('throttle:10,1');
    });

    Route::middleware(['permission:'.AdminPermissions::SUPPLIER_LIST])->group(function (): void {
        Route::get('/suppliers/summary', [SupplierController::class, 'summary']);
        Route::get('/suppliers/provider-types', [SupplierController::class, 'providerTypes']);
        Route::get('/suppliers', [SupplierController::class, 'index']);
    });

    Route::middleware(['permission:'.AdminPermissions::SUPPLIER_DETAIL])->group(function (): void {
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::SUPPLIER_MANAGE])->group(function (): void {
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);
        Route::patch('/suppliers/{supplier}/status', [SupplierController::class, 'updateStatus']);
    });

    Route::middleware(['permission:'.AdminPermissions::SUPPLIER_SYNC])->group(function (): void {
        Route::get('/suppliers/{supplier}/balance', [SupplierController::class, 'balance']);
        Route::get('/suppliers/{supplier}/products', [SupplierController::class, 'products']);
        Route::get('/suppliers/{supplier}/products/{productId}/config-template', [SupplierController::class, 'productConfigTemplate']);
        Route::post('/suppliers/{supplier}/tasks', [SupplierController::class, 'runTask']);
    });

    Route::middleware(['permission:'.AdminPermissions::SUPPLIER_SECRET_REVEAL])->group(function (): void {
        Route::get('/suppliers/{supplier}/secrets/{key}', [SupplierController::class, 'revealSecret'])->middleware('throttle:10,1');
    });

    Route::middleware(['permission:'.AdminPermissions::SETTINGS_VIEW])->group(function (): void {
        Route::get('/notification-templates', [SettingController::class, 'notificationTemplates']);
        Route::get('/notification-templates/sms/provider-template/list', [SettingController::class, 'listSmsProviderTemplates']);
        Route::get('/settings', [SettingController::class, 'index']);
    });

    Route::middleware(['permission:'.AdminPermissions::SETTINGS_MANAGE])->group(function (): void {
        Route::post('/notification-templates/test-send', [SettingController::class, 'testNotificationTemplate']);
        Route::post('/notification-templates/sms/provider-sync', [SettingController::class, 'syncSmsProviderTemplates'])->middleware('throttle:20,1');
        Route::post('/notification-templates/sms/provider-template/import-defaults', [SettingController::class, 'importDefaultSmsProviderTemplates'])->middleware('throttle:20,1');
        Route::post('/notification-templates/sms/provider-template/submit-all', [SettingController::class, 'submitAllSmsProviderTemplates'])->middleware('throttle:20,1');
        Route::post('/notification-templates/sms/provider-template', [SettingController::class, 'createSmsProviderTemplate']);
        Route::post('/notification-templates/sms/provider-template/{id}/update', [SettingController::class, 'updateSmsProviderTemplate']);
        Route::post('/notification-templates/sms/provider-template/{id}/submit', [SettingController::class, 'submitSmsProviderTemplateForReview'])->middleware('throttle:20,1');
        Route::post('/notification-templates/sms/provider-template/{id}/refresh', [SettingController::class, 'refreshSmsProviderTemplateById'])->middleware('throttle:20,1');
        Route::post('/notification-templates/sms/provider-template/{id}/deletion', [SettingController::class, 'deleteSmsProviderTemplate']);
        Route::post('/notification-templates/sms/{code}/provider-link', [SettingController::class, 'linkSmsProviderTemplate']);
        Route::post('/settings', [SettingController::class, 'update']);
    });

    Route::middleware(['permission:'.AdminPermissions::SETTINGS_SECRET_REVEAL])->group(function (): void {
        Route::get('/settings/{group}/secrets/{key}', [SettingController::class, 'revealSecret'])->middleware('throttle:10,1');
    });

    Route::middleware(['permission:'.AdminPermissions::DATABASE_VIEW])->group(function (): void {
        Route::get('/database/status', [DatabaseStatusController::class, 'status']);
        Route::get('/system/readiness', [SystemReadinessController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::DATABASE_MANAGE])->group(function (): void {
        Route::post('/database/optimizations', [DatabaseStatusController::class, 'optimize']);
        Route::post('/database/backups', [DatabaseStatusController::class, 'exportBackup']);
        Route::get('/database/backups/{taskId}', [DatabaseStatusController::class, 'showBackup'])
            ->where('taskId', '[0-9a-fA-F-]{36}');
        Route::get('/database/backups/{taskId}/download', [DatabaseStatusController::class, 'downloadBackup'])
            ->where('taskId', '[0-9a-fA-F-]{36}');
    });

    Route::middleware(['permission:'.AdminPermissions::STAFF_LIST])->group(function (): void {
        Route::get('/staff', [StaffController::class, 'index']);
        Route::get('/staff/roles', [StaffController::class, 'roles']);
        Route::get('/staff/{staff}', [StaffController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::STAFF_MANAGE])->group(function (): void {
        Route::post('/staff', [StaffController::class, 'store']);
        Route::put('/staff/{staff}', [StaffController::class, 'update']);
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);
        Route::patch('/staff/{staff}/status', [StaffController::class, 'updateStatus']);
        Route::post('/staff/{staff}/password-resets', [StaffController::class, 'resetPassword']);
    });

    Route::middleware(['permission:'.AdminPermissions::PERMISSION_LIST])->group(function (): void {
        Route::get('/permissions', [RoleController::class, 'permissions']);
    });

    Route::middleware(['permission:'.AdminPermissions::ROLE_LIST])->group(function (): void {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
    });

    Route::middleware(['permission:'.AdminPermissions::ROLE_MANAGE])->group(function (): void {
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        Route::post('/roles/{role}/copies', [RoleController::class, 'copy']);
    });

    Route::middleware(['permission:'.AdminPermissions::REFERRAL_LIST])->group(function (): void {
        Route::get('/referral/overview', [ReferralController::class, 'overview']);
        Route::get('/referral/rewards', [ReferralController::class, 'rewards']);
    });

    Route::middleware(['permission:'.AdminPermissions::REFERRAL_WITHDRAWAL_LIST])->group(function (): void {
        Route::get('/referral-withdrawals', [ReferralWithdrawalController::class, 'index']);
    });

    Route::middleware(['permission:'.AdminPermissions::FINANCE_WITHDRAW])->group(function (): void {
        Route::post('/referral-withdrawals/{withdrawal}/approvals', [ReferralWithdrawalController::class, 'approve'])
            ->middleware('throttle:10,1,admin-withdraw-approve');
        Route::post('/referral-withdrawals/{withdrawal}/rejections', [ReferralWithdrawalController::class, 'reject'])
            ->middleware('throttle:10,1,admin-withdraw-reject');
        // 打款确认不可撤销：限流防止误双击与脚本重放造成重复出款确认。
        Route::post('/referral-withdrawals/{withdrawal}/payment-confirmations', [ReferralWithdrawalController::class, 'confirmPayment'])
            ->middleware('throttle:10,1,admin-withdraw-confirm');
    });
});
