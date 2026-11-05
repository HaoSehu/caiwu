import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// 登录会话：Bearer Token 存 Android Keystore（secure storage），用户资料存普通偏好。
class Session extends ChangeNotifier {
  Session({FlutterSecureStorage? secureStorage})
      : _secureStorage = secureStorage ?? const FlutterSecureStorage();

  static const _tokenKey = 'caiwu.token';
  static const _userKey = 'caiwu.user';

  final FlutterSecureStorage _secureStorage;

  String? _token;
  Map<String, dynamic>? _user;
  bool _loaded = false;

  String? get token => _token;
  Map<String, dynamic>? get user => _user;
  bool get loaded => _loaded;
  bool get isLoggedIn => (_token ?? '').isNotEmpty;

  int get userId => _toInt(_user?['id']);
  String get displayName {
    final display = (_user?['display_name'] as String?)?.trim() ?? '';
    if (display.isNotEmpty) return display;
    final nickname = (_user?['nickname'] as String?)?.trim() ?? '';
    if (nickname.isNotEmpty) return nickname;
    final email = (_user?['email'] as String?)?.trim() ?? '';
    if (email.isNotEmpty) return email;
    final phone = (_user?['phone'] as String?)?.trim() ?? '';
    return phone.isNotEmpty ? phone : '用户';
  }

  String get accountLabel {
    final email = (_user?['email'] as String?)?.trim() ?? '';
    if (email.isNotEmpty) return email;
    return (_user?['phone'] as String?)?.trim() ?? '';
  }

  String get balance => (_user?['cash_balance'] as String?) ?? '0.00';

  /// App 启动时恢复本地会话。
  Future<void> restore() async {
    try {
      _token = await _secureStorage.read(key: _tokenKey);
      _user = await _readUser();
    } catch (_) {
      _token = null;
      _user = null;
    } finally {
      _loaded = true;
      notifyListeners();
    }
  }

  /// 登录成功后写入。
  Future<void> applyLogin(String token, Map<String, dynamic> user) async {
    _token = token;
    _user = user;
    await _secureStorage.write(key: _tokenKey, value: token);
    await _writeUser(user);
    notifyListeners();
  }

  /// `GET /auth/info` 刷新用户资料。
  Future<void> updateUser(Map<String, dynamic> user) async {
    _user = user;
    await _writeUser(user);
    notifyListeners();
  }

  /// 退出登录：清 token 与用户资料。无论存储是否抛异常，都要通知路由刷新。
  Future<void> clear() async {
    _token = null;
    _user = null;
    try {
      await _secureStorage.delete(key: _tokenKey);
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_userKey);
    } catch (_) {
      // 存储清理失败不应阻断登出（内存态已清空）。
    } finally {
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> _readUser() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_userKey);
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      return decoded is Map<String, dynamic> ? decoded : null;
    } catch (_) {
      return null;
    }
  }

  Future<void> _writeUser(Map<String, dynamic> user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_userKey, jsonEncode(user));
  }

  static int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('${value ?? ''}') ?? 0;
  }
}
