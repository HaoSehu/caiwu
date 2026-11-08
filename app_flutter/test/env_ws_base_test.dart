import 'package:caiwu_app/core/config/env.dart';
import 'package:flutter_test/flutter_test.dart';

/// 后端下发的 `api_base` 可能指向服务端自己的本机地址（`APP_URL` 未改成公网值时）。
/// 真机上 `127.0.0.1` 指向设备自身，WebSocket 必然连不上，表现为 VNC 卡在
/// "正在建立安全通道"。因此回环地址必须回落到编译期注入的 `API_BASE`。
void main() {
  group('Env.wsBaseFromApiBase', () {
    test('正常域名按 scheme 转换', () {
      expect(
        Env.wsBaseFromApiBase('https://api.example.com/api'),
        'wss://api.example.com',
      );
      expect(
        Env.wsBaseFromApiBase('http://192.168.1.10:8000'),
        'ws://192.168.1.10:8000',
      );
    });

    test('回环地址回落到编译期 API_BASE，不使用服务端本机地址', () {
      final fallback = Env.resolveWsBase();
      expect(Env.wsBaseFromApiBase('http://127.0.0.1:8000'), fallback);
      expect(Env.wsBaseFromApiBase('http://127.0.0.1:5173'), fallback);
      expect(Env.wsBaseFromApiBase('http://localhost:8000'), fallback);
      expect(Env.wsBaseFromApiBase('https://localhost'), fallback);
      expect(Env.wsBaseFromApiBase('http://0.0.0.0:8000'), fallback);
      expect(Env.wsBaseFromApiBase('http://[::1]:8000'), fallback);
    });

    test('127.0.0.1 的任何末位网段都视为回环', () {
      final fallback = Env.resolveWsBase();
      expect(Env.wsBaseFromApiBase('http://127.1.2.3:8000'), fallback);
    });

    test('空值与非法值回落到编译期 API_BASE', () {
      final fallback = Env.resolveWsBase();
      expect(Env.wsBaseFromApiBase(''), fallback);
      expect(Env.wsBaseFromApiBase('not a url'), fallback);
    });

    test('回退目标本身不是回环地址（API_BASE 配置正确时）', () {
      // 测试环境 API_BASE 用默认值 10.0.2.2（模拟器访问宿主机），不是回环地址。
      expect(Env.resolveWsBase(), isNot(contains('127.0.0.1')));
    });
  });
}
