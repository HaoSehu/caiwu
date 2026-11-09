import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../config/web_preview.dart';

/// Web 预览用的 HTTP 适配器：不发出任何真实请求，直接返回演示数据。
///
/// 只有 [WebPreview.useMockApi] 为真时才会被装上（需显式 `--dart-define`
/// 开启的 Web 预览），Android 原生构建恒不启用。未登记的路径返回统一空壳，
/// 并在控制台打印 `[preview-mock] 未登记接口: <path>`，方便补齐演示数据。
class MockHttpAdapter implements HttpClientAdapter {
  MockHttpAdapter();

  static const String _okWrapMessage = 'ok';

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    final path = options.uri.path;
    final query = options.uri.queryParameters;
    final data = _resolve(path, query);
    if (data == null) {
      _record(path);
    }
    return ResponseBody.fromString(
      jsonEncode({
        'code': 0,
        'message': _okWrapMessage,
        'data': data ?? <String, dynamic>{},
        'timestamp': 1762387200,
      }),
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}

  // ── 演示数据 ────────────────────────────────────────────────────────

  Map<String, dynamic> _serviceSummary(int id, String name, String status,
      String statusLabel, String tone) {
    return {
      'id': id,
      'name': name,
      'product_name': name,
      'product_display_name': '云服务器 · 2 核 2G',
      'product_full_path': '云服务器/美国/三网精品/$name',
      // 与后端一致：0 开通中 / 1 运行中 / 2 已暂停 / 4 异常。
      'status': status == 'running' ? 1 : (status == 'pending' ? 0 : 2),
      'status_label': statusLabel,
      'status_tone': tone,
      'expires_at': '2026-12-06 03:37:27',
      'amount': '25.00',
      'billing_cycle': 'monthly',
    };
  }

  dynamic _resolve(String path, Map<String, String> query) {
    final page = int.tryParse(query['page'] ?? '1') ?? 1;
    final pageSize = int.tryParse(query['page_size'] ?? '15') ?? 15;

    // 认证
    if (path.endsWith('/auth/info')) {
      return {'user': WebPreview.demoUser};
    }
    if (path.endsWith('/auth/logout')) return <String, dynamic>{};

    // 首页 / 服务
    if (path.endsWith('/services/grouped-overview')) {
      return {
        'list': [
          {
            'title': '云服务器',
            'name': '云服务器',
            'items': [
              _serviceSummary(8801, 'gscs-2vcpu-2gib', 'running', '运行中',
                  'success'),
              _serviceSummary(8802, 'gscs-4vcpu-4gib', 'pending', '开通中',
                  'warning'),
              _serviceSummary(8803, 'gscs-1vcpu-1gib', 'stopped', '已暂停',
                  'default'),
            ],
          },
          {
            'title': '独立服务器',
            'name': '独立服务器',
            'items': [
              _serviceSummary(8811, 'dedi-e5-32g', 'running', '运行中',
                  'success'),
            ],
          },
        ],
      };
    }
    if (path.endsWith('/services')) {
      // 已删除视图（status=3）单独计数：首页用它从总数里扣掉删除项。
      if (query['status'] == '3') {
        return {
          'list': <Map<String, dynamic>>[],
          'total': 0,
          'page': page,
          'page_size': pageSize,
        };
      }
      // 精选 4 个示例（含一个已暂停）覆盖三种状态配色。
      const statuses = <(String, String, String)>[
        ('running', '运行中', 'success'),
        ('pending', '开通中', 'warning'),
        ('stopped', '已暂停', 'default'),
        ('running', '运行中', 'success'),
      ];
      final items = List.generate(
        pageSize > statuses.length ? statuses.length : pageSize,
        (index) => _serviceSummary(
          9000 + index,
          'gscs-demo-${index + 1}',
          statuses[index].$1,
          statuses[index].$2,
          statuses[index].$3,
        ),
      );
      return {
        'list': items,
        'total': statuses.length,
        'page': page,
        'page_size': pageSize,
      };
    }
    final serviceDetail = RegExp(r'/services/(\d+)$').firstMatch(path);
    if (serviceDetail != null) {
      final id = int.tryParse(serviceDetail.group(1)!) ?? 8801;
      return {
        'service': {
          ..._serviceSummary(id, 'gscs-2vcpu-2gib', 'running', '运行中',
              'success'),
          'created_at': '2026-11-06 03:37:27',
          'ip': '198.51.100.24',
          'combined_display_name': '云服务器 · 2 核 2G / gscs-2vcpu-2gib',
          'billing_cycle_label': '按月',
          'remark': '演示实例',
          'upstream': {
            'dedicated_ip': '198.51.100.24',
            'os': 'Ubuntu 24.04 LTS',
            'hostname': 'gscs-2vcpu-2gib',
            'region': '美国 · 三网精品',
          },
          'runtime': {
            'power_state': 'on',
            'power_label': '运行中',
            'traffic_used': '128.6 GB',
            'traffic_limit': '1000 GB',
          },
          'actions': {'power': true, 'reinstall': true, 'renew': true},
          'traffic': {'used': '128.6', 'limit': '1000', 'unit': 'GB'},
          'invoice': {
            'id': 5001,
            'invoice_no': 'INV202611060001',
            'amount': '25.00',
            'status_label': '已支付',
            'created_at': '2026-11-06 03:37:27',
          },
        },
      };
    }
    if (RegExp(r'/services/\d+/runtime$').hasMatch(path)) {
      return {
        'runtime': {
          'power_state': 'on',
          'can_start': false,
          'can_stop': true,
          'can_reboot': true,
        },
      };
    }
    if (RegExp(r'/services/\d+/connection$').hasMatch(path)) {
      return {
        'connection': {
          'host': '198.51.100.24',
          'port': 22,
          'username': 'root',
          'password': 'Demo#Passw0rd',
          'protocol': 'ssh',
        },
      };
    }
    if (RegExp(r'/services/\d+/power-status$').hasMatch(path)) {
      return {'power_state': 'on'};
    }
    if (RegExp(r'/services/\d+/operation-logs$').hasMatch(path)) {
      return {
        'list': [
          {
            'created_at': '2026-11-06 03:37:27',
            'action_label': '创建实例',
            'summary': '系统自动创建实例并安装操作系统',
          },
          {
            'created_at': '2026-11-06 04:10:02',
            'action_label': '开机',
            'summary': '用户手动开机',
          },
        ],
        'total': 2,
        'page': page,
        'page_size': pageSize,
      };
    }
    if (RegExp(r'/services/\d+/monitor$').hasMatch(path)) {
      return {'points': []};
    }

    // 工单
    if (path.endsWith('/tickets')) {
      return {
        'list': [
          {
            'id': 7001,
            'subject': '服务器无法 SSH 登录',
            'status': 1,
            'status_label': '处理中',
            'priority_label': '中',
            'department_label': '技术支持',
            'created_at': '2026-11-05 10:22:11',
            'updated_at': '2026-11-06 09:02:44',
          },
          {
            'id': 7002,
            'subject': '账单金额疑问',
            'status': 2,
            'status_label': '已完成',
            'priority_label': '低',
            'department_label': '财务',
            'created_at': '2026-10-28 15:40:00',
            'updated_at': '2026-10-29 11:12:00',
          },
        ],
        'total': 2,
        'page': page,
        'page_size': pageSize,
      };
    }
    final ticketDetail = RegExp(r'/tickets/(\d+)$').firstMatch(path);
    if (ticketDetail != null) {
      return {
        'ticket': {
          'id': int.tryParse(ticketDetail.group(1)!) ?? 7001,
          'subject': '服务器无法 SSH 登录',
          'content': '从昨天开始无法通过 SSH 连接到实例，端口 22 超时。',
          'status': 1,
          'status_label': '处理中',
          'priority_label': '中',
          'department_label': '技术支持',
          'created_at': '2026-11-05 10:22:11',
          'updated_at': '2026-11-06 09:02:44',
        },
      };
    }
    if (RegExp(r'/tickets/\d+/replies$').hasMatch(path)) {
      return {
        'list': [
          {
            'id': 1,
            'content': '已收到，正在检查实例网络与安全组配置。',
            'is_staff': true,
            'author': '技术支持',
            'created_at': '2026-11-05 11:00:00',
          },
          {
            'id': 2,
            'content': '好的，麻烦尽快，业务受影响。',
            'is_staff': false,
            'author': '李维佳',
            'created_at': '2026-11-05 11:12:30',
          },
        ],
        'total': 2,
        'page': page,
        'page_size': pageSize,
      };
    }

    // 财务
    if (path.endsWith('/invoices/summary')) {
      return {'unpaid': 0, 'paid': 6, 'total_amount': '150.00'};
    }
    // 账单详情：结构必须与 ClientInvoiceDetailResource 一致的嵌套白名单
    // （basic/display/financial/payment_options/payment_chain），
    // 此前未登记，预览里点进详情只拿到空壳、金额与支付入口全空（M-28④）。
    final invoiceDetail = RegExp(r'/invoices/(\d+)$').firstMatch(path);
    if (invoiceDetail != null) {
      final id = int.tryParse(invoiceDetail.group(1)!) ?? 5002;
      final isPaid = id != 5002;
      return {
        'invoice': {
          'id': id,
          'basic': {
            'invoice_no': 'INV20261106000$id',
            'type': 'new',
            'type_label': '新购',
            'status': isPaid ? 1 : 0,
            'status_label': isPaid ? '已支付' : '待支付',
            'billing_cycle': 'monthly',
            'quantity': 1,
            'due_date': '2026-11-13',
          },
          'display': {
            'product_spec_snapshot': 'gscs-2vcpu-2gib',
            'product_spec_display': '云服务器 · 2 核 2G',
            'product_display_name': '云服务器 · 2 核 2G',
            'combined_display_name': '云服务器 · 2 核 2G / gscs-2vcpu-2gib',
            'product_full_path': '云服务器/美国/三网精品/gscs-2vcpu-2gib',
            'member_discount_amount': '0.00',
            'member_discount_snapshot': null,
            'coupon_code': '',
            'coupon_name': '',
            'summary': {'headline': '云服务器 · 2 核 2G'},
          },
          'financial': {
            'amount': isPaid ? '25.00' : '699.00',
            'discount': '0.00',
            'paid_amount': isPaid ? '25.00' : '0.00',
            'payable_amount': isPaid ? '0.00' : '699.00',
            'paid_at': isPaid ? '2026-11-06 03:38:10' : null,
          },
          'order': {
            'id': 6001,
            'order_no': 'ORD202611060001',
            'status': isPaid ? 1 : 0,
            'type': 'new',
          },
          'product': {'id': 74, 'name': '云服务器 · 2 核 2G', 'product_type': 'vps'},
          'service': isPaid
              ? {'id': 8801, 'name': 'gscs-2vcpu-2gib', 'status': 1, 'expires_at': '2026-12-06 03:37:27'}
              : null,
          'scene': <String, dynamic>{},
          'configuration': {
            'config_snapshot': <String, dynamic>{},
            'config_pricing_snapshot': <String, dynamic>{},
            'coupon': null,
          },
          'payment_chain': {
            'payment_summary': isPaid
                ? {
                    'id': 6101,
                    'payment_no': 'PAY202611060001',
                    'gateway': 'alipay',
                    'gateway_key': 'alipay',
                    'gateway_label': '支付宝',
                    'channel_name': '支付宝',
                    'amount': '25.00',
                    'status': 1,
                    'status_label': '已支付',
                    'paid_at': '2026-11-06 03:38:10',
                  }
                : null,
            'payments': isPaid
                ? [
                    {
                      'id': 6101,
                      'payment_no': 'PAY202611060001',
                      'gateway': 'alipay',
                      'gateway_key': 'alipay',
                      'gateway_label': '支付宝',
                      'channel_name': '支付宝',
                      'amount': '25.00',
                      'status': 1,
                      'status_label': '已支付',
                      'paid_at': '2026-11-06 03:38:10',
                    },
                  ]
                : <Map<String, dynamic>>[],
          },
          'payment_options': {
            // 待支付账单给一条可点的余额支付；第三方渠道在预览里不拉起真实支付
            'pay_methods': isPaid
                ? <Map<String, dynamic>>[]
                : [
                    {
                      'key': 'balance',
                      'name': '余额支付',
                      'label': '账户余额',
                      'option_key': 'balance',
                      'plugin_name': '',
                      'channel_name': '余额支付',
                    },
                  ],
            'payment_security': {
              'can_pay': !isPaid,
              'session_token': isPaid ? '' : 'preview_inv_session_token_0001',
              'expires_at': null,
            },
            'can_cancel': !isPaid,
            'service_id': isPaid ? 8801 : 0,
          },
          'items': <Map<String, dynamic>>[],
          'logs': <Map<String, dynamic>>[],
          'timestamps': {
            'created_at': '2026-11-06 03:37:27',
            'updated_at': '2026-11-06 03:38:10',
          },
        },
      };
    }
    if (path.endsWith('/invoices')) {
      return {
        'list': [
          {
            'id': 5001,
            'invoice_no': 'INV202611060001',
            'status': 1,
            'status_label': '已支付',
            'combined_display_name': '云服务器 · 2 核 2G / gscs-2vcpu-2gib',
            'created_at': '2026-11-06 03:37:27',
            'amount': '25.00',
            'payable_amount': '25.00',
            'paid_amount': '25.00',
          },
          {
            'id': 5002,
            'invoice_no': 'INV202611060002',
            'status': 0,
            'status_label': '待支付',
            'combined_display_name': '独立服务器 / dedi-e5-32g',
            'created_at': '2026-11-06 08:15:00',
            'amount': '699.00',
            'payable_amount': '699.00',
            'paid_amount': '0.00',
          },
        ],
        'total': 2,
        'page': page,
        'page_size': pageSize,
      };
    }
    if (path.endsWith('/orders')) {
      return {
        'list': [
          {
            'id': 6001,
            'order_no': 'ORD202611060001',
            'status': 1,
            'status_label': '已完成',
            'combined_display_name': '云服务器 · 2 核 2G',
            'created_at': '2026-11-06 03:37:27',
            'amount': '25.00',
          },
        ],
        'total': 1,
        'page': page,
        'page_size': pageSize,
      };
    }
    // 订单详情：与 ClientOrderQueryService::detail 的字段对齐（此前未登记，
    // 预览里点进订单详情只拿到空壳，M-28④）
    final orderDetail = RegExp(r'/orders/(\d+)$').firstMatch(path);
    if (orderDetail != null) {
      final id = int.tryParse(orderDetail.group(1)!) ?? 6001;
      return {
        'order': {
          'id': id,
          'order_no': 'ORD202611060001',
          'type': 'new',
          'type_label': '新购',
          'status': 1,
          'status_label': '已支付',
          'amount': '25.00',
          'paid_amount': '25.00',
          'discount': '0.00',
          'member_discount_amount': '0.00',
          'coupon_code': '',
          'coupon_name': '',
          'billing_cycle': 'monthly',
          'quantity': 1,
          'product_name': '云服务器 · 2 核 2G',
          'product_full_path': '云服务器/美国/三网精品/gscs-2vcpu-2gib',
          'service_name': 'gscs-2vcpu-2gib',
          'gateway_label': '支付宝',
          'channel_name': '支付宝',
          'paid_at': '2026-11-06 03:38:10',
          'finished_at': '2026-11-06 03:38:10',
          'created_at': '2026-11-06 03:37:27',
          'invoice': {
            'id': 5001,
            'invoice_no': 'INV202611060001',
            'status': 1,
            'amount': '25.00',
            'payment_window_expired': false,
          },
        },
      };
    }
    if (path.endsWith('/payments')) {
      return {
        'list': [
          {
            'id': 6101,
            'payment_no': 'PAY202611060001',
            'status_label': '成功',
            'channel_label': '支付宝',
            'created_at': '2026-11-06 03:38:10',
            'amount': '25.00',
          },
        ],
        'total': 1,
        'page': page,
        'page_size': pageSize,
      };
    }
    if (path.endsWith('/ledger')) {
      return {
        'list': [
          {
            'id': 6201,
            'event_label': '充值',
            'change_amount': '99785.40',
            'balance_after': '99785.40',
            'created_at': '2026-11-06 03:40:00',
          },
        ],
        'total': 1,
        'page': page,
        'page_size': pageSize,
      };
    }
    if (path.endsWith('/recharge/gateways')) {
      // 与后端 ClientRechargeWorkflowService::gatewayOptions() 同构：
      // 返回键是 list，每项带 key/channel_name/payment_type/option_key（M-28④）
      return {
        'list': [
          {
            'key': 'alipay',
            'label': '支付宝',
            'channel_name': '支付宝',
            'plugin_name': '支付宝',
            'plugin_id': 0,
            'plugin_key': 'alipay_f2f',
            'option_key': 'alipay',
          },
          {
            'key': 'yi_pay',
            'label': '微信支付',
            'channel_name': '微信支付',
            'plugin_name': '易支付',
            'plugin_id': 0,
            'plugin_key': 'yi_pay',
            'option_key': 'yi_pay:wxpay',
            'payment_type': 'wxpay',
          },
        ],
      };
    }
    if (RegExp(r'/recharge/[^/]+/status$').hasMatch(path)) {
      // 与 ClientRechargeWorkflowService::status() 的字段对齐（paid/status/status_label）。
      // 预览不做假到账：paid 恒 false，页面轮询会一直等到用户手动刷新。
      return {'paid': false, 'status': 0, 'status_label': '待支付'};
    }
    if (RegExp(r'/invoices/\d+/pay/balance$').hasMatch(path)) {
      // 余额支付只回结算摘要（invoice 由页面刷新详情再取），预览不虚构扣款结果
      return {
        'gateway': 'balance',
        'amount': '0.00',
        'paid_at': null,
        'cash_balance': '${WebPreview.demoUser['cash_balance'] ?? '0.00'}',
      };
    }
    if (RegExp(r'/invoices/\d+/pay/alipay$').hasMatch(path)) {
      // 预览环境不能拉起真实支付：回一个不可扫的占位链接，提示走「我已完成支付」
      return {
        'gateway': 'alipay',
        'gateway_key': 'alipay',
        'gateway_label': '支付宝',
        'channel_name': '支付宝',
        'payment_no': 'PREVIEW-PAY-0001',
        'poll_token': 'preview_poll_token_00000001',
        'qr_code': '',
        'amount': '0.00',
      };
    }
    if (RegExp(r'/invoices/\d+/pay/alipay/status$').hasMatch(path)) {
      return {'paid': false, 'status': 0, 'status_label': '待支付', 'message': '预览环境不会真正支付'};
    }

    // 消息公告
    if (path.endsWith('/notices')) {
      return {
        'list': [
          {
            'id': 3001,
            'title': '计费系统升级维护通知',
            'summary': '11 月 12 日 02:00–04:00 进行计费系统升级，期间充值可能延迟到账。',
            'type_label': '系统公告',
            'publish_at': '2026-11-05 09:00:00',
            'read': false,
          },
          {
            'id': 3002,
            'title': '新增美国三网精品机房',
            'summary': '美国三网精品线路已上线，低延迟直连中国大陆。',
            'type_label': '产品动态',
            'publish_at': '2026-11-02 14:20:00',
            'read': true,
          },
        ],
        'total': 2,
        'page': page,
        'page_size': pageSize,
      };
    }
    final noticeDetail = RegExp(r'/notices/(\d+)$').firstMatch(path);
    if (noticeDetail != null) {
      return {
        'article': {
          'id': int.tryParse(noticeDetail.group(1)!) ?? 3001,
          'title': '计费系统升级维护通知',
          'content': '为提升计费系统稳定性，我们将于 2026-11-12 02:00–04:00 进行升级维护。'
              '维护期间充值到账可能延迟，实例运行不受影响。',
          'publish_at': '2026-11-05 09:00:00',
          'type_label': '系统公告',
        },
      };
    }
    if (path.endsWith('/notices/unread-count') ||
        path.endsWith('/notifications/unread-count')) {
      return {'count': 1};
    }
    if (path.endsWith('/notifications/feed')) {
      return {'list': <Map<String, dynamic>>[], 'total': 0};
    }

    return null;
  }

  /// 打印未命中的路径，方便补齐演示数据。
  void _record(String path) {
    // ignore: avoid_print
    print('[preview-mock] 未登记接口: $path');
  }
}
