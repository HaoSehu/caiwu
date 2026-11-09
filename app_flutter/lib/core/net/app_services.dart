import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../features/ssh/host_store.dart';
import '../store/session.dart';
import 'api_client.dart';
import 'api_exception.dart';
import 'api_services.dart';

/// 依赖注入根：持有 ApiClient、Session 与各领域 API，并负责 401 时的统一登出跳转。
class AppServices {
  AppServices({
    required this.session,
    required this.onUnauthorized,
  }) {
    client = ApiClient(
      tokenProvider: () => session.token,
      onUnauthorized: onUnauthorized,
    );
    auth = AuthApi(client);
    services = ServiceApi(client);
    tickets = TicketApi(client);
    finance = FinanceApi(client);
    content = ContentApi(client);
  }

  final Session session;
  final void Function() onUnauthorized;

  late final ApiClient client;
  late final AuthApi auth;
  late final ServiceApi services;
  late final TicketApi tickets;
  late final FinanceApi finance;
  late final ContentApi content;

  /// 独立 SSH 主机（本地保存，不依赖后端账号）。
  final SshHostStore sshHosts = SshHostStore();

  /// 便捷提供者：页面里 `context.read<AppServices>()` 或
  /// `AppServices.of(context)` 获取领域 API。
  static AppServices of(BuildContext context) {
    return context.read<AppServices>();
  }

  /// 统一错误文案：ApiException 用后端 message，网络异常用自身 message。
  static String errorText(Object error) {
    if (error is ApiException) return error.message;
    if (error is NetworkException) return error.message;
    return '操作失败，请稍后重试';
  }
}
