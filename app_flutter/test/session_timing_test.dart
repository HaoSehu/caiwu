import 'package:caiwu_app/core/store/session.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// 会话恢复时序的守门测试。
///
/// `/home` 是路由的 initialLocation：若 `Session.loaded` 在恢复完成前就为真，
/// 未登录用户会先看到一帧空壳首页再被弹回登录页。
///
/// 用内存实现替代平台存储，测试环境没有 Keystore 通道。
class _MemoryStorage implements FlutterSecureStorage {
  final Map<String, String> _values = {};

  @override
  Future<String?> read({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async =>
      _values[key];

  @override
  Future<void> write({
    required String key,
    required String? value,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async {
    if (value == null) {
      _values.remove(key);
    } else {
      _values[key] = value;
    }
  }

  @override
  Future<void> delete({
    required String key,
    IOSOptions? iOptions,
    AndroidOptions? aOptions,
    LinuxOptions? lOptions,
    WebOptions? webOptions,
    MacOsOptions? mOptions,
    WindowsOptions? wOptions,
  }) async {
    _values.remove(key);
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => throw UnimplementedError();
}

void main() {
  setUp(() {
    // 用户资料走 SharedPreferences，测试环境需要内存实现。
    SharedPreferences.setMockInitialValues(<String, Object>{});
  });

  test('初始状态未恢复完成', () {
    final session = Session(secureStorage: _MemoryStorage());
    expect(session.loaded, isFalse);
    expect(session.isLoggedIn, isFalse);
    session.dispose();
  });

  test('applyLogin 之后视为已恢复（Web 预览依赖这一点）', () async {
    final session = Session(secureStorage: _MemoryStorage());
    await session.applyLogin('token', {'id': 1, 'display_name': '测试'});
    expect(session.loaded, isTrue);
    expect(session.isLoggedIn, isTrue);
    session.dispose();
  });

  test('clear 之后仍视为已恢复，路由不会卡在等待态', () async {
    final session = Session(secureStorage: _MemoryStorage());
    await session.applyLogin('token', {'id': 1});
    await session.clear();
    expect(session.loaded, isTrue);
    expect(session.isLoggedIn, isFalse);
    session.dispose();
  });
}
