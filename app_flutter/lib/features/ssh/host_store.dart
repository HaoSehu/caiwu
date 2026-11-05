import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// 一条 SSH 主机配置（不依赖 caiwu 服务，可手动添加任意服务器）。
///
/// 敏感字段（密码、私钥）单独存 Keystore，不随列表落明文偏好。
@immutable
class SshHost {
  const SshHost({
    required this.id,
    required this.label,
    required this.host,
    required this.port,
    required this.username,
    this.authType = SshAuthType.password,
    this.remark = '',
    this.jumpHostId,
    this.keepAliveSeconds = 30,
    this.createdAt,
    this.lastConnectedAt,
  });

  final String id;

  /// 显示名（默认取 host:port）。
  final String label;
  final String host;
  final int port;
  final String username;
  final SshAuthType authType;
  final String remark;

  /// 跳板机（另一条 SshHost 的 id）。
  final String? jumpHostId;

  /// 保活间隔（秒），0 表示关闭。
  final int keepAliveSeconds;

  final DateTime? createdAt;
  final DateTime? lastConnectedAt;

  String get endpoint => '$host:$port';

  String get displayLabel => label.trim().isNotEmpty ? label.trim() : endpoint;

  SshHost copyWith({
    String? label,
    String? host,
    int? port,
    String? username,
    SshAuthType? authType,
    String? remark,
    String? jumpHostId,
    bool clearJumpHost = false,
    int? keepAliveSeconds,
    DateTime? lastConnectedAt,
  }) {
    return SshHost(
      id: id,
      label: label ?? this.label,
      host: host ?? this.host,
      port: port ?? this.port,
      username: username ?? this.username,
      authType: authType ?? this.authType,
      remark: remark ?? this.remark,
      jumpHostId: clearJumpHost ? null : (jumpHostId ?? this.jumpHostId),
      keepAliveSeconds: keepAliveSeconds ?? this.keepAliveSeconds,
      createdAt: createdAt,
      lastConnectedAt: lastConnectedAt ?? this.lastConnectedAt,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'label': label,
    'host': host,
    'port': port,
    'username': username,
    'authType': authType.name,
    'remark': remark,
    'jumpHostId': jumpHostId,
    'keepAliveSeconds': keepAliveSeconds,
    'createdAt': createdAt?.toIso8601String(),
    'lastConnectedAt': lastConnectedAt?.toIso8601String(),
  };

  static SshHost fromJson(Map<String, dynamic> json) {
    return SshHost(
      id: '${json['id'] ?? ''}',
      label: '${json['label'] ?? ''}',
      host: '${json['host'] ?? ''}',
      port: _int(json['port'], 22),
      username: '${json['username'] ?? ''}',
      authType: SshAuthType.values.firstWhere(
        (type) => type.name == '${json['authType'] ?? ''}',
        orElse: () => SshAuthType.password,
      ),
      remark: '${json['remark'] ?? ''}',
      jumpHostId: (json['jumpHostId'] as String?)?.trim().isEmpty ?? true
          ? null
          : '${json['jumpHostId']}',
      keepAliveSeconds: _int(json['keepAliveSeconds'], 30),
      createdAt: DateTime.tryParse('${json['createdAt'] ?? ''}'),
      lastConnectedAt: DateTime.tryParse('${json['lastConnectedAt'] ?? ''}'),
    );
  }

  static int _int(Object? value, int fallback) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('${value ?? ''}') ?? fallback;
  }
}

enum SshAuthType { password, privateKey }

/// SSH 主机仓库：列表存 SharedPreferences，密码/私钥存 secure storage。
class SshHostStore extends ChangeNotifier {
  SshHostStore({FlutterSecureStorage? secureStorage})
    : _secureStorage = secureStorage ?? const FlutterSecureStorage();

  static const _listKey = 'ssh.hosts';
  static const _secretPrefix = 'ssh.secret.';

  final FlutterSecureStorage _secureStorage;

  List<SshHost> _hosts = const [];
  bool _loaded = false;

  List<SshHost> get hosts => List.unmodifiable(_hosts);
  bool get loaded => _loaded;

  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_listKey);
      if (raw != null && raw.isNotEmpty) {
        final decoded = jsonDecode(raw);
        if (decoded is List) {
          _hosts =
              decoded
                  .whereType<Map<Object?, Object?>>()
                  .map(
                    (item) => SshHost.fromJson(Map<String, dynamic>.from(item)),
                  )
                  .toList()
                ..sort(_byRecent);
        }
      }
    } catch (_) {
      _hosts = const [];
    } finally {
      _loaded = true;
      notifyListeners();
    }
  }

  static int _byRecent(SshHost a, SshHost b) {
    final at = a.lastConnectedAt;
    final bt = b.lastConnectedAt;
    if (at == null && bt == null) {
      return a.displayLabel.compareTo(b.displayLabel);
    }
    if (at == null) return 1;
    if (bt == null) return -1;
    return bt.compareTo(at);
  }

  SshHost? byId(String id) {
    for (final host in _hosts) {
      if (host.id == id) return host;
    }
    return null;
  }

  Future<void> save(SshHost host) async {
    final index = _hosts.indexWhere((item) => item.id == host.id);
    final next = List<SshHost>.from(_hosts);
    if (index >= 0) {
      next[index] = host;
    } else {
      next.add(host);
    }
    _hosts = next..sort(_byRecent);
    await _persist();
    notifyListeners();
  }

  Future<void> remove(String id) async {
    _hosts = _hosts.where((host) => host.id != id).toList();
    await _persist();
    await _secureStorage.delete(key: '$_secretPrefix$id');
    notifyListeners();
  }

  Future<void> touchConnected(String id) async {
    final host = byId(id);
    if (host == null) return;
    await save(host.copyWith(lastConnectedAt: DateTime.now()));
  }

  /// 写入密码或私钥（按 host id 隔离）。
  Future<void> saveSecret(String id, String secret) async {
    if (secret.isEmpty) {
      await _secureStorage.delete(key: '$_secretPrefix$id');
    } else {
      await _secureStorage.write(key: '$_secretPrefix$id', value: secret);
    }
  }

  Future<String> readSecret(String id) async {
    try {
      return await _secureStorage.read(key: '$_secretPrefix$id') ?? '';
    } catch (_) {
      return '';
    }
  }

  Future<bool> hasSecret(String id) async => (await readSecret(id)).isNotEmpty;

  Future<void> _persist() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _listKey,
      jsonEncode(_hosts.map((host) => host.toJson()).toList()),
    );
  }
}
