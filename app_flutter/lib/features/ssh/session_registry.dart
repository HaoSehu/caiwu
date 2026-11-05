import 'package:flutter/foundation.dart';

/// 一条已打开 SSH 会话的轻量描述。
///
/// 会话管理页只依赖这份注册信息，不直接访问连接实现（`SshSession`），
/// 由会话创建方在连上/断开时注册与注销，避免两端互相耦合。
@immutable
class SshSessionInfo {
  const SshSessionInfo({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.startedAt,
    required this.kind,
    this.serviceId,
    this.hostId,
  });

  /// 会话唯一 id。
  final String id;

  /// 显示名（主机名或服务名）。
  final String title;

  /// 副标题，通常是 `user@host:port`。
  final String subtitle;

  /// 会话建立时间，用于计算「已连接 xx 分钟」。
  final DateTime startedAt;

  /// 会话来源：`service`（caiwu 服务）或 `host`（手动添加的主机）。
  final String kind;

  /// 来源为服务时对应的服务 id。
  final int? serviceId;

  /// 来源为主机时对应的主机 id。
  final String? hostId;

  /// 「切换」时跳转的路由；缺少来源信息时回落到主机列表。
  String get route {
    final host = hostId?.trim() ?? '';
    if (host.isNotEmpty) return '/ssh/session/$host';
    final service = serviceId;
    if (service != null && service > 0) return '/services/$service/ssh';
    return '/ssh';
  }
}

/// 进程内 SSH 会话注册表（仅内存态，不持久化，退出 App 即清空）。
///
/// 作为单例供终端页与「打开的会话」页共用：终端页在连接成功后
/// [register]，断开时 [unregister]；会话管理页监听变化自动刷新。
class SshSessionRegistry extends ChangeNotifier {
  SshSessionRegistry._();

  static final SshSessionRegistry instance = SshSessionRegistry._();

  final List<SshSessionInfo> _sessions = <SshSessionInfo>[];
  String? _activeId;

  /// 当前打开的会话，最新注册的排在最前。
  List<SshSessionInfo> get sessions => List.unmodifiable(_sessions);

  /// 当前正在前台展示的会话 id。
  String? get activeId => _activeId;

  int get count => _sessions.length;

  /// 注册（或更新）一条会话；首个会话自动成为当前会话。
  void register(SshSessionInfo info) {
    final index = _sessions.indexWhere((session) => session.id == info.id);
    if (index >= 0) {
      _sessions[index] = info;
    } else {
      _sessions.insert(0, info);
    }
    _activeId ??= info.id;
    notifyListeners();
  }

  /// 注销会话；若注销的是当前会话，自动切到剩余会话中最新的一条。
  void unregister(String id) {
    final index = _sessions.indexWhere((session) => session.id == id);
    if (index < 0) return;
    _sessions.removeAt(index);
    if (_activeId == id) {
      _activeId = _sessions.isEmpty ? null : _sessions.first.id;
    }
    notifyListeners();
  }

  /// 标记当前会话；id 不在注册表中时忽略。
  void markActive(String id) {
    if (_activeId == id) return;
    if (!_sessions.any((session) => session.id == id)) return;
    _activeId = id;
    notifyListeners();
  }
}
