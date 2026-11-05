/// 用户端 API 服务集合。所有方法返回后端 `data` 字段（已由 ApiClient 解包）。
///
/// 路径统一以 `/v2/client/` 开头（ApiClient baseUrl 已带 `/api`）。
library;

import 'package:dio/dio.dart';

class AuthApi {
  AuthApi(this._client);

  final dynamic _client;

  /// 是否需要图形验证码（Geetest）。首版 App 不做 Geetest 渲染。
  Future<Map<String, dynamic>> captchaConfig() async {
    final data = await _client.get('/v2/client/auth/captcha-config');
    return _asMap(data);
  }

  /// 账号密码登录。
  Future<Map<String, dynamic>> login({
    required String account,
    required String password,
    String? captcha,
  }) async {
    final data = await _client.post(
      '/v2/client/login',
      data: {
        'account': account,
        'password': password,
        if (captcha != null && captcha.isNotEmpty) 'captcha': captcha,
      },
    );
    return _asMap(data);
  }

  /// 发送短信验证码。
  Future<void> sendPhoneCode(String phone, {String purpose = 'login'}) async {
    await _client.post(
      '/v2/client/auth/phone-code',
      data: {'phone': phone, 'purpose': purpose},
    );
  }

  /// 发送邮箱验证码。
  Future<void> sendEmailCode(String email, {String purpose = 'login'}) async {
    await _client.post(
      '/v2/client/auth/email-code',
      data: {'email': email, 'purpose': purpose},
    );
  }

  /// 验证码登录。
  Future<Map<String, dynamic>> loginByCode({
    required String account,
    required String code,
  }) async {
    final data = await _client.post(
      '/v2/client/auth/login-by-code',
      data: {'account': account, 'code': code},
    );
    return _asMap(data);
  }

  /// 注册。
  Future<Map<String, dynamic>> register({
    required String account,
    required String code,
    required String password,
    required String passwordConfirmation,
    String? nickname,
  }) async {
    final data = await _client.post(
      '/v2/client/register',
      data: {
        'account': account,
        'code': code,
        'password': password,
        'password_confirmation': passwordConfirmation,
        if (nickname != null && nickname.isNotEmpty) 'nickname': nickname,
      },
    );
    return _asMap(data);
  }

  /// 重置密码。
  Future<void> resetPassword({
    required String account,
    required String code,
    required String password,
    required String passwordConfirmation,
  }) async {
    await _client.post(
      '/v2/client/auth/reset-password',
      data: {
        'account': account,
        'code': code,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
  }

  /// 当前用户信息。
  Future<Map<String, dynamic>> info() async {
    final data = await _client.get('/v2/client/auth/info');
    return _asMap(data);
  }

  /// 更新个人资料（当前仅支持昵称）。
  Future<Map<String, dynamic>> updateProfile({required String nickname}) async {
    final data = await _client.put(
      '/v2/client/auth/profile',
      data: {'nickname': nickname},
    );
    return _asMap(data);
  }

  /// 退出登录。
  Future<void> logout() async {
    await _client.post('/v2/client/auth/logout');
  }

  /// 修改密码。后端字段为 camelCase：oldPassword/newPassword/confirmPassword。
  Future<void> updatePassword({
    required String oldPassword,
    required String newPassword,
    required String confirmPassword,
  }) async {
    await _client.put(
      '/v2/client/password',
      data: {
        'oldPassword': oldPassword,
        'newPassword': newPassword,
        'confirmPassword': confirmPassword,
      },
    );
  }
}

/// 服务（VPS）相关。
class ServiceApi {
  ServiceApi(this._client);

  final dynamic _client;

  /// 服务列表（分页）。
  Future<Map<String, dynamic>> list({
    int page = 1,
    int pageSize = 15,
    String? keyword,
    int? status,
    String? statusScope,
    String? quickFilter,
    String? catalogType,
  }) async {
    final data = await _client.get(
      '/v2/client/services',
      query: {
        'page': page,
        'page_size': pageSize,
        if (keyword != null && keyword.isNotEmpty) 'keyword': keyword,
        if (status != null) 'status': status,
        if (statusScope != null && statusScope.isNotEmpty)
          'status_scope': statusScope,
        if (quickFilter != null && quickFilter.isNotEmpty)
          'quick_filter': quickFilter,
        if (catalogType != null && catalogType.isNotEmpty)
          'catalog_type': catalogType,
      },
    );
    return _asMap(data);
  }

  /// 分组概览（首页/服务页卡片）。
  Future<Map<String, dynamic>> groupedOverview() async {
    final data = await _client.get('/v2/client/services/grouped-overview');
    return _asMap(data);
  }

  /// 服务详情。
  /// 服务详情。`refresh=true` 穿透后端 30 秒详情缓存（刷新按钮必须传）。
  Future<Map<String, dynamic>> detail(int id, {bool refresh = false}) async {
    final data = await _client.get(
      '/v2/client/services/$id',
      query: {if (refresh) 'refresh': 1},
    );
    return _asMap((_asMap(data))['service']);
  }

  /// 运行态（状态/电源/流量/动作能力）。
  Future<Map<String, dynamic>> runtime(int id) async {
    final data = await _client.get('/v2/client/services/$id/runtime');
    return _asMap((_asMap(data))['runtime']);
  }

  /// 电源状态（开机/关机/重启按钮可用性）。
  Future<Map<String, dynamic>> powerStatus(int id) async {
    final data = await _client.get('/v2/client/services/$id/power-status');
    return _asMap(data);
  }

  /// 连接凭据（SSH 终端入口）。
  Future<Map<String, dynamic>> connection(int id) async {
    final data = await _client.get('/v2/client/services/$id/connection');
    return _asMap((_asMap(data))['connection']);
  }

  /// 电源操作：on/off/reboot/hard_off/hard_reboot。
  Future<Map<String, dynamic>> powerAction(int id, String action) async {
    final data = await _client.post(
      '/v2/client/services/$id/power-actions',
      data: {'action': action},
    );
    return _asMap(data);
  }

  /// 重装系统可选镜像。
  Future<Map<String, dynamic>> reinstallOptions(int id) async {
    final data = await _client.get(
      '/v2/client/services/$id/reinstallations/options',
    );
    return _asMap(data);
  }

  /// 提交重装。
  Future<Map<String, dynamic>> reinstall(int id, String osId) async {
    final data = await _client.post(
      '/v2/client/services/$id/reinstallations',
      data: {'os_id': osId},
    );
    return _asMap(data);
  }

  /// 重置服务器密码。
  Future<Map<String, dynamic>> resetPassword(
    int id,
    String password,
    String confirmation,
  ) async {
    final data = await _client.post(
      '/v2/client/services/$id/password-resets',
      data: {'password': password, 'password_confirmation': confirmation},
    );
    return _asMap(data);
  }

  /// 续费预览（可选周期与价格）。
  Future<Map<String, dynamic>> renewPreview(
    int id, {
    String? billingCycle,
  }) async {
    final data = await _client.get(
      '/v2/client/services/$id/renewals',
      query: {
        if (billingCycle != null && billingCycle.isNotEmpty)
          'billing_cycle': billingCycle,
      },
    );
    return _asMap(data);
  }

  /// 生成续费账单。
  Future<Map<String, dynamic>> createRenewOrder(
    int id, {
    required String billingCycle,
    int? userCouponId,
  }) async {
    final data = await _client.post(
      '/v2/client/services/$id/renewals',
      data: {
        'billing_cycle': billingCycle,
        if (userCouponId != null) 'user_coupon_id': userCouponId,
      },
    );
    return _asMap(data);
  }

  /// 自动续费开关。
  Future<void> updateAutoRenew(int id, bool enabled) async {
    await _client.put(
      '/v2/client/services/$id/renewals/auto',
      data: {'auto_renew': enabled ? 1 : 0},
    );
  }

  /// 监控图表。
  Future<Map<String, dynamic>> monitor(
    int id, {
    String range = '3h',
    String? type,
    int? start,
    int? end,
  }) async {
    final data = await _client.get(
      '/v2/client/services/$id/monitor',
      query: {
        'range': range,
        if (type != null && type.isNotEmpty) 'type': type,
        if (start != null) 'start': start,
        if (end != null) 'end': end,
      },
    );
    return _asMap(data);
  }

  /// 操作日志。
  Future<Map<String, dynamic>> operationLogs(
    int id, {
    int page = 1,
    int pageSize = 15,
    String? keyword,
    String? category,
  }) async {
    final data = await _client.get(
      '/v2/client/services/$id/operation-logs',
      query: {
        'page': page,
        'page_size': pageSize,
        if (keyword != null && keyword.isNotEmpty) 'keyword': keyword,
        if (category != null && category.isNotEmpty) 'category': category,
      },
    );
    return _asMap(data);
  }

  /// 修改服务名称。
  Future<void> updateName(int id, String name) async {
    await _client.put('/v2/client/services/$id/name', data: {'name': name});
  }

  /// 修改备注。
  Future<void> updateRemark(int id, String remark) async {
    await _client.put(
      '/v2/client/services/$id/remark',
      data: {'remark': remark},
    );
  }

  /// VNC 链接与凭据。
  Future<Map<String, dynamic>> vnc(int id) async {
    final data = await _client.get('/v2/client/services/$id/vnc');
    return _asMap(data);
  }

  /// 公开 token 换 relay token（无鉴权）。
  Future<Map<String, dynamic>> vncToken(String token) async {
    final data = await _client.get('/v2/client/vnc-tokens/$token');
    return _asMap(data);
  }
}

/// 工单。
class TicketApi {
  TicketApi(this._client);

  final dynamic _client;

  Future<Map<String, dynamic>> list({
    int page = 1,
    int pageSize = 15,
    String? keyword,
    String? status,
  }) async {
    final data = await _client.get(
      '/v2/client/tickets',
      query: {
        'page': page,
        'page_size': pageSize,
        if (keyword != null && keyword.isNotEmpty) 'keyword': keyword,
        if (status != null && status.isNotEmpty) 'status': status,
      },
    );
    return _asMap(data);
  }

  Future<Map<String, dynamic>> detail(int id) async {
    final data = await _client.get('/v2/client/tickets/$id');
    return _asMap((_asMap(data))['ticket']);
  }

  Future<Map<String, dynamic>> replies(
    int id, {
    int page = 1,
    int pageSize = 50,
  }) async {
    final data = await _client.get(
      '/v2/client/tickets/$id/replies',
      query: {'page': page, 'page_size': pageSize},
    );
    return _asMap(data);
  }

  Future<Map<String, dynamic>> create({
    required String subject,
    required String department,
    required String content,
    int? priority,
    int? serviceId,
    List<String>? attachments,
  }) async {
    final data = await _client.post(
      '/v2/client/tickets',
      data: {
        'subject': subject,
        'department': department,
        'content': content,
        if (priority != null) 'priority': priority,
        if (serviceId != null) 'service_id': serviceId,
        if (attachments != null && attachments.isNotEmpty)
          'attachments': attachments,
      },
    );
    return _asMap(data);
  }

  Future<void> reply(
    int id, {
    required String content,
    List<String>? attachments,
  }) async {
    await _client.post(
      '/v2/client/tickets/$id/replies',
      data: {
        'content': content,
        if (attachments != null && attachments.isNotEmpty)
          'attachments': attachments,
      },
    );
  }

  Future<void> close(int id, {String? reason}) async {
    await _client.post(
      '/v2/client/tickets/$id/closures',
      data: {if (reason != null && reason.isNotEmpty) 'close_reason': reason},
    );
  }

  /// 图片上传（multipart，字段名 `file`）。
  ///
  /// 后端返回单个附件对象 `{id, name, path, url, size, mime_type, type}`；
  /// 工单创建/回复的 `attachments[]` 传这里的 `path`（上传引用 token）。
  Future<Map<String, dynamic>> uploadImage(FormData formData) async {
    final data = await _client.upload(
      '/v2/client/tickets/upload-images',
      formData,
    );
    return _asMap(data);
  }
}

/// 财务：账单、订单、充值、流水。
class FinanceApi {
  FinanceApi(this._client);

  final dynamic _client;

  Future<Map<String, dynamic>> invoices({
    int page = 1,
    int pageSize = 15,
    String? status,
    String? startDate,
    String? endDate,
    String? keyword,
  }) async {
    final data = await _client.get(
      '/v2/client/invoices',
      query: {
        'page': page,
        'page_size': pageSize,
        if (status != null && status.isNotEmpty) 'status': status,
        if (startDate != null) 'start_date': startDate,
        if (endDate != null) 'end_date': endDate,
        if (keyword != null && keyword.isNotEmpty) 'keyword': keyword,
      },
    );
    return _asMap(data);
  }

  /// 账单汇总：`unpaid` 为待支付张数，`unpaid_amount` 为待支付金额。
  Future<Map<String, dynamic>> invoiceSummary() async {
    final data = await _client.get('/v2/client/invoices/summary');
    return _asMap(data);
  }

  /// 账单详情（含 payment_options.payment_security.session_token）。
  Future<Map<String, dynamic>> invoiceDetail(int id) async {
    final data = await _client.get('/v2/client/invoices/$id');
    final map = _asMap(data);
    final invoice = map['invoice'];
    if (invoice is Map) return _asMap(invoice);
    return map;
  }

  /// 余额支付。paymentSessionToken 来自账单详情的
  /// `payment_options.payment_security.session_token`。
  Future<Map<String, dynamic>> payInvoiceByBalance(
    int id, {
    required String paymentSessionToken,
  }) async {
    final data = await _client.post(
      '/v2/client/invoices/$id/pay/balance',
      data: {'payment_session_token': paymentSessionToken},
    );
    return _asMap(data);
  }

  /// 第三方网关支付：返回支付链接（`qr_code`）与支付单号。
  ///
  /// 路由名沿用 `pay/alipay`，但 [gateway] 与 [paymentType] 由后端解析，
  /// 可覆盖支付宝、微信支付等所有第三方渠道。
  Future<Map<String, dynamic>> payInvoiceByAlipay(
    int id, {
    required String paymentSessionToken,
    String? gateway,
    String? paymentType,
  }) async {
    final data = await _client.post(
      '/v2/client/invoices/$id/pay/alipay',
      data: {
        'payment_session_token': paymentSessionToken,
        if (gateway != null && gateway.isNotEmpty) 'gateway': gateway,
        if (paymentType != null && paymentType.isNotEmpty)
          'payment_type': paymentType,
      },
    );
    return _asMap(data);
  }

  /// 支付宝支付单状态查询。
  ///
  /// 后端强制要求 `payment_no` 与 `poll_token`（20–120 位），两者都由
  /// [payInvoiceByAlipay] 的响应下发，下单后必须保存再查询。
  Future<Map<String, dynamic>> alipayStatus(
    int id, {
    required String paymentNo,
    required String pollToken,
    String? gateway,
  }) async {
    final data = await _client.get(
      '/v2/client/invoices/$id/pay/alipay/status',
      query: {
        'payment_no': paymentNo,
        'poll_token': pollToken,
        if (gateway != null && gateway.isNotEmpty) 'gateway': gateway,
      },
    );
    return _asMap(data);
  }

  Future<void> cancelInvoice(int id, {String? reason}) async {
    await _client.post(
      '/v2/client/invoices/$id/cancellations',
      data: {if (reason != null && reason.isNotEmpty) 'reason': reason},
    );
  }

  Future<Map<String, dynamic>> orders({
    int page = 1,
    int pageSize = 15,
    String? status,
  }) async {
    final data = await _client.get(
      '/v2/client/orders',
      query: {
        'page': page,
        'page_size': pageSize,
        if (status != null && status.isNotEmpty) 'status': status,
      },
    );
    return _asMap(data);
  }

  Future<Map<String, dynamic>> orderDetail(int id) async {
    final data = await _client.get('/v2/client/orders/$id');
    return _asMap((_asMap(data))['order'] ?? data);
  }

  Future<Map<String, dynamic>> payments({
    int page = 1,
    int pageSize = 15,
  }) async {
    final data = await _client.get(
      '/v2/client/payments',
      query: {'page': page, 'page_size': pageSize},
    );
    return _asMap(data);
  }

  Future<Map<String, dynamic>> paymentDetail(int id) async {
    final data = await _client.get('/v2/client/payments/$id');
    return _asMap((_asMap(data))['payment'] ?? data);
  }

  Future<Map<String, dynamic>> balanceLogs({
    int page = 1,
    int pageSize = 15,
    String? eventType,
  }) async {
    final data = await _client.get(
      '/v2/client/balance-logs',
      query: {
        'page': page,
        'page_size': pageSize,
        if (eventType != null && eventType.isNotEmpty) 'event_type': eventType,
      },
    );
    return _asMap(data);
  }

  Future<Map<String, dynamic>> rechargeGateways() async {
    final data = await _client.get('/v2/client/recharge/gateways');
    return _asMap(data);
  }

  /// 创建充值支付单。
  ///
  /// 同一插件可能同时开启支付宝与微信支付，此时 `gateway` 相同、
  /// 由 `payment_type`（`alipay` / `wxpay`）区分为两条通道。
  Future<Map<String, dynamic>> recharge({
    required String amount,
    required String gateway,
    String? paymentType,
  }) async {
    final data = await _client.post(
      '/v2/client/recharge',
      data: {
        'amount': amount,
        'gateway': gateway,
        if (paymentType != null && paymentType.isNotEmpty)
          'payment_type': paymentType,
      },
    );
    return _asMap(data);
  }

  Future<Map<String, dynamic>> rechargeStatus(String paymentNo) async {
    final data = await _client.get('/v2/client/recharge/$paymentNo/status');
    return _asMap(data);
  }
}

/// 公告与通知。
class ContentApi {
  ContentApi(this._client);

  final dynamic _client;

  Future<Map<String, dynamic>> notices({
    int page = 1,
    int pageSize = 12,
  }) async {
    final data = await _client.get(
      '/v2/client/notices',
      query: {'page': page, 'page_size': pageSize},
    );
    return _asMap(data);
  }

  Future<Map<String, dynamic>> noticeDetail(int id) async {
    final data = await _client.get('/v2/client/notices/$id');
    return _asMap((_asMap(data))['article'] ?? data);
  }

  Future<int> noticeUnreadCount() async {
    final data = await _client.get('/v2/client/notices/unread-count');
    return _asInt((_asMap(data))['count']);
  }

  Future<void> markAllNoticesRead() async {
    await _client.post('/v2/client/notices/mark-all-read');
  }

  Future<void> markNoticeRead(int id, {bool read = true}) async {
    await _client.put(
      '/v2/client/notices/$id/read-state',
      data: {'read': read},
    );
  }

  /// 通知流（一次上限 30 条，不分页）。列表页需要翻页时用 [notifications]。
  Future<Map<String, dynamic>> notificationFeed({int limit = 30}) async {
    final data = await _client.get(
      '/v2/client/notifications/feed',
      query: {'limit': limit.clamp(1, 30)},
    );
    return _asMap(data);
  }

  /// 通知列表（支持 page/page_size/unread_only）。
  Future<Map<String, dynamic>> notifications({
    int page = 1,
    int pageSize = 15,
    bool unreadOnly = false,
  }) async {
    final data = await _client.get(
      '/v2/client/notifications',
      query: {
        'page': page,
        'page_size': pageSize.clamp(1, 50),
        if (unreadOnly) 'unread_only': 1,
      },
    );
    return _asMap(data);
  }

  Future<int> notificationUnreadCount() async {
    final data = await _client.get('/v2/client/notifications/unread-count');
    return _asInt((_asMap(data))['count']);
  }

  Future<void> markNotificationRead(int id, {bool read = true}) async {
    await _client.put(
      '/v2/client/notifications/$id/read-state',
      data: {'read': read},
    );
  }

  Future<void> markAllNotificationsRead() async {
    await _client.post('/v2/client/notifications/mark-all-read');
  }
}

// ── 类型安全的小工具（后端字段是 snake_case 的 JSON） ─────────────────

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((k, v) => MapEntry('$k', v));
  return <String, dynamic>{};
}

int _asInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('${value ?? ''}') ?? 0;
}
