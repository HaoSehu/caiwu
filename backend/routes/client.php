<?php

use App\Http\Controllers\Client\AuthController;
use App\Http\Controllers\Client\BlackholeController;
use App\Http\Controllers\Client\ContentController;
use App\Http\Controllers\Client\CouponController;
use App\Http\Controllers\Client\FinanceController;
use App\Http\Controllers\Client\FinanceLedgerController;
use App\Http\Controllers\Client\InvoiceController;
use App\Http\Controllers\Client\NotificationController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Client\PaymentCallbackController;
use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\Client\RechargeController;
use App\Http\Controllers\Client\ReferralController;
use App\Http\Controllers\Client\ServiceController;
use App\Http\Controllers\Client\TicketController as ClientTicketController;
use App\Http\Controllers\Client\VerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 客户端 API 路由
|--------------------------------------------------------------------------
*/

// 客户登录/注册（无需认证）
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1,client-auth-login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1,client-auth-register');
Route::get('/auth/captcha-config', [AuthController::class, 'captchaConfig']);
Route::get('/auth/captcha-script', [AuthController::class, 'captchaScript']);
Route::post('/auth/login-as/exchange', [AuthController::class, 'exchangeLoginAsCode'])->middleware('throttle:10,1,client-auth-login-as');
Route::post('/auth/phone-code', [AuthController::class, 'sendPhoneCode'])->middleware('throttle:6,1,client-auth-phone-code');
Route::post('/auth/email-code', [AuthController::class, 'sendEmailCode'])->middleware('throttle:6,1,client-auth-email-code');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1,client-auth-reset-password');
Route::post('/auth/login-by-code', [AuthController::class, 'loginByCode'])->middleware('throttle:5,1,client-auth-login-by-code');
Route::match(['GET', 'POST'], '/verification/callback', [VerificationController::class, 'callback'])->middleware('verify.callback');
Route::get('/verification/scan', [VerificationController::class, 'scan']);

// 支付宝异步通知（无需认证，支付宝服务器回调）
Route::post('/payment/alipay/notify', [PaymentCallbackController::class, 'alipayNotify'])
    ->middleware('verify.alipay.callback');

// VNC Token 验证（无需认证，供VNC页面独立访问）
Route::get('/vnc-tokens/{token}', [ServiceController::class, 'vncToken'])->middleware('throttle:30,1,client-vnc-token');

// 需要认证的路由
Route::middleware(['auth:sanctum', 'ensure.client'])->group(function () {

    // 认证信息
    Route::get('/auth/info', [AuthController::class, 'info']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/password', [AuthController::class, 'updatePassword']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::get('/auth/alipay-account', [AuthController::class, 'alipayAccount']);
    Route::put('/auth/alipay-account', [AuthController::class, 'updateAlipayAccount']);
    Route::get('/auth/notification-preferences', [AuthController::class, 'notificationPreferences']);
    Route::put('/auth/notification-preferences', [AuthController::class, 'updateNotificationPreferences']);
    Route::put('/auth/phone', [AuthController::class, 'updatePhone']);
    Route::put('/auth/email', [AuthController::class, 'updateEmail']);

    // 实名认证
    Route::get('/verification/fee-config', [VerificationController::class, 'feeConfig']);
    Route::post('/verification/init', [VerificationController::class, 'init']);
    Route::post('/verification/qrcode', [VerificationController::class, 'qrcode']);
    Route::get('/verification/status', [VerificationController::class, 'status']);
    Route::post('/verification/restart', [VerificationController::class, 'restart']);

    // 财务管理
    Route::get('/balance-logs', [FinanceController::class, 'balanceLogs']);
    Route::get('/balance-logs/summary', [FinanceController::class, 'balanceLogsSummary']);
    Route::get('/finance/ledger', [FinanceLedgerController::class, 'index']);
    Route::get('/finance/ledger/summary', [FinanceLedgerController::class, 'summary']);
    Route::get('/finance/ledger/{id}', [FinanceLedgerController::class, 'show']);
    Route::get('/coupons', [CouponController::class, 'index']);
    Route::get('/coupons/summary', [CouponController::class, 'summary']);
    Route::get('/coupons/public', [CouponController::class, 'publicIndex']);
    Route::get('/coupons/public/summary', [CouponController::class, 'publicSummary']);
    Route::post('/coupons/{couponId}/claim', [CouponController::class, 'claim'])->middleware('throttle:6,1,client-coupons-claim');

    // 内容中心
    Route::get('/content/overview', [ContentController::class, 'overview']);
    Route::get('/notices', [ContentController::class, 'notices']);
    Route::get('/notices/unread-count', [ContentController::class, 'noticeUnreadCount']);
    Route::post('/notices/mark-all-read', [ContentController::class, 'markAllNoticesRead']);
    Route::get('/notices/{articleId}', [ContentController::class, 'noticeDetail']);
    Route::post('/notices/{articleId}/mark-read', [ContentController::class, 'markNoticeRead']);
    Route::get('/help-articles', [ContentController::class, 'helpArticles']);
    Route::get('/help-articles/{articleId}', [ContentController::class, 'helpDetail']);

    // 站内信（铃铛：公告 + 个性化通知聚合）
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('/notifications/feed', [NotificationController::class, 'feed']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markRead']);

    // 推荐奖励
    Route::get('/referral/overview', [ReferralController::class, 'overview']);
    Route::get('/referral/rewards', [ReferralController::class, 'rewards']);
    Route::get('/referral/account-logs', [ReferralController::class, 'accountLogs']);
    Route::get('/referral/withdrawals', [ReferralController::class, 'withdrawals']);
    Route::post('/referral/withdrawals', [ReferralController::class, 'applyWithdrawal'])->middleware('throttle:3,1,client-referral-withdraw');

    // 账单与支付（主实体）
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/summary', [InvoiceController::class, 'summary']);
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('throttle:8,1,client-invoices-store');
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->middleware('throttle:10,1,client-invoices-cancel');
    Route::post('/invoices/{id}/pay/balance', [InvoiceController::class, 'payByBalance'])->middleware('throttle:10,1,client-invoices-pay-balance');
    Route::post('/invoices/{id}/pay/mix', [InvoiceController::class, 'payByBalanceAndAlipay'])->middleware('throttle:10,1,client-invoices-pay-mix');
    Route::post('/invoices/{id}/pay/alipay', [InvoiceController::class, 'payByAlipay'])->middleware('throttle:12,1,client-invoices-pay-alipay');
    Route::get('/invoices/{id}/pay/alipay/status', [InvoiceController::class, 'queryAlipayStatus'])->middleware('throttle:30,1,client-invoices-pay-alipay-status');

    // 支付记录
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/summary', [PaymentController::class, 'summary']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);

    // 订单
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/summary', [OrderController::class, 'summary']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->middleware('throttle:10,1,client-orders-cancel');

    // 充值
    Route::post('/recharge', [RechargeController::class, 'store'])->middleware('throttle:6,1,client-recharge-store');
    Route::get('/recharge/{paymentNo}/status', [RechargeController::class, 'status'])->middleware('throttle:30,1,client-recharge-status');

    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/services/grouped-overview', [ServiceController::class, 'groupedOverview']);
    Route::get('/services/{id}/base', [ServiceController::class, 'baseDetail']);
    Route::get('/services/{id}/remote-status', [ServiceController::class, 'remoteStatus']);
    Route::get('/services/{id}', [ServiceController::class, 'show']);
    Route::get('/services/{id}/config', [ServiceController::class, 'config']);
    Route::get('/services/{id}/operation-logs', [ServiceController::class, 'operationLogs']);
    Route::put('/services/{id}/name', [ServiceController::class, 'updateName']);
    Route::put('/services/{id}/remark', [ServiceController::class, 'updateRemark']);
    Route::get('/services/{id}/traffic-packages', [ServiceController::class, 'trafficPackages']);
    Route::post('/services/{id}/traffic-packages/quote', [ServiceController::class, 'quoteTrafficPackage'])->middleware('throttle:12,1,client-service-traffic-package-quote');
    Route::post('/services/{id}/traffic-packages/order', [ServiceController::class, 'createTrafficPackageOrder'])->middleware('throttle:6,1,client-service-traffic-package-order');
    Route::get('/services/{id}/upgrade', [ServiceController::class, 'hostUpgradePreview']);
    Route::post('/services/{id}/upgrade/quote', [ServiceController::class, 'quoteHostUpgrade'])->middleware('throttle:12,1,client-service-host-upgrade-quote');
    Route::post('/services/{id}/upgrade/order', [ServiceController::class, 'createHostUpgradeOrder'])->middleware('throttle:6,1,client-service-host-upgrade-order');
    Route::get('/services/{id}/renew', [ServiceController::class, 'renewPreview']);
    Route::post('/services/{id}/renew', [ServiceController::class, 'createRenewOrder'])->middleware('throttle:6,1,client-service-renew');
    Route::put('/services/{id}/renew/auto', [ServiceController::class, 'updateAutoRenew'])->middleware('throttle:6,1,client-service-renew-auto');
    Route::get('/services/{id}/module-status', [ServiceController::class, 'moduleStatus']);
    Route::get('/services/{id}/reinstall/options', [ServiceController::class, 'reinstallOptions']);
    Route::get('/services/{id}/nat-forwardings', [ServiceController::class, 'natForwardings']);
    Route::post('/services/{id}/nat-forwardings', [ServiceController::class, 'createNatForwarding'])->middleware('throttle:10,1,client-service-nat-create');
    Route::delete('/services/{id}/nat-forwardings/{forwardingId}', [ServiceController::class, 'deleteNatForwarding'])->middleware('throttle:10,1,client-service-nat-delete');
    Route::get('/services/{id}/monitor', [ServiceController::class, 'monitor']);
    Route::get('/services/{id}/monitor/batch', [ServiceController::class, 'monitorBatch']);
    Route::post('/services/{id}/power', [ServiceController::class, 'power'])->middleware('throttle:10,1,client-service-power');
    Route::put('/services/{id}/password/reset', [ServiceController::class, 'resetPassword'])->middleware('throttle:6,1,client-service-password-reset');
    Route::put('/services/{id}/reinstall', [ServiceController::class, 'reinstall'])->middleware('throttle:6,1,client-service-reinstall');
    Route::get('/services/{id}/security-groups', [ServiceController::class, 'securityGroups']);
    Route::post('/services/{id}/security-groups', [ServiceController::class, 'createSecurityGroup'])->middleware('throttle:10,1,client-service-security-group-create');
    Route::get('/services/{id}/security-groups/{groupId}/rules', [ServiceController::class, 'securityGroupRules']);
    Route::post('/services/{id}/security-groups/{groupId}/apply', [ServiceController::class, 'applySecurityGroup'])->middleware('throttle:10,1,client-service-security-group-apply');
    Route::delete('/services/{id}/security-groups/{groupId}', [ServiceController::class, 'deleteSecurityGroup'])->middleware('throttle:10,1,client-service-security-group-delete');
    Route::post('/services/{id}/security-groups/{groupId}/rules', [ServiceController::class, 'createSecurityRule'])->middleware('throttle:10,1,client-service-security-rule-create');
    Route::delete('/services/{id}/security-groups/{groupId}/rules/{ruleId}', [ServiceController::class, 'deleteSecurityRule'])->middleware('throttle:10,1,client-service-security-rule-delete');
    Route::get('/services/{id}/vnc', [ServiceController::class, 'vnc']);

    // 工单
    Route::get('/tickets', [ClientTicketController::class, 'index']);
    Route::get('/tickets/service-options', [ClientTicketController::class, 'serviceOptions']);
    Route::post('/tickets', [ClientTicketController::class, 'store'])->middleware('throttle:10,1,client-ticket-store');
    Route::post('/tickets/upload-image', [ClientTicketController::class, 'uploadImage'])->middleware('throttle:12,1,client-ticket-upload-image');
    Route::get('/tickets/{id}', [ClientTicketController::class, 'show']);
    Route::post('/tickets/{id}/reply', [ClientTicketController::class, 'reply'])->middleware('throttle:10,1,client-ticket-reply');
    Route::post('/tickets/{id}/replies/{replyId}/recall', [ClientTicketController::class, 'recall'])->middleware('throttle:10,1,client-ticket-recall');
    Route::post('/tickets/{id}/close', [ClientTicketController::class, 'close'])->middleware('throttle:10,1,client-ticket-close');

    // 管理工具
    Route::post('/blackhole/query', [BlackholeController::class, 'query']);
    Route::post('/blackhole/ningbo/whitelist', [BlackholeController::class, 'addNingboWhitelist'])->middleware('throttle:6,1,client-blackhole-write');
    Route::post('/blackhole/shiyan/layer7/toggle', [BlackholeController::class, 'setShiyanLayer7Rule'])->middleware('throttle:6,1,client-blackhole-write');
    Route::post('/blackhole/shiyan/layer4/add', [BlackholeController::class, 'addShiyanLayer4Rule'])->middleware('throttle:6,1,client-blackhole-write');
    Route::post('/blackhole/shiyan/layer4/delete', [BlackholeController::class, 'deleteShiyanLayer4Rule'])->middleware('throttle:6,1,client-blackhole-write');
});
