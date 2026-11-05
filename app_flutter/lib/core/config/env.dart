import 'dart:io';

/// 运行期配置。全部通过 `--dart-define` 覆盖，不写死生产地址。
///
/// 默认值面向本机联调：Android 模拟器访问宿主机 `php artisan app:serve`（127.0.0.1:8000）
/// 必须用 10.0.2.2；真机联调时传 `--dart-define=API_BASE=http://<局域网IP>:8000/api`。
class Env {
  Env._();

  /// 后端 API 基地址，包含 `/api` 前缀。
  static const String apiBase = String.fromEnvironment(
    'API_BASE',
    defaultValue: 'http://10.0.2.2:8000/api',
  );

  /// VNC Relay 的 WebSocket 地址推导来源。
  /// 后端 `GET /services/{id}/vnc` 会下发 `api_base` 与 `relay_path`，优先使用下发值；
  /// 该常量只作为兜底（下发缺失时）。
  static const String wsBase = String.fromEnvironment(
    'WS_BASE',
    defaultValue: '',
  );

  /// VNC Relay 握手必须携带的 Origin；需与后端 `idc.vnc_relay.app_origins`
  /// （默认 `caiwu-app`，后端会补 https scheme 归一）保持一致。
  /// 必须是完整 Origin（scheme://host），WebSocket 握手头不接受裸值。
  static const String vncOrigin = String.fromEnvironment(
    'VNC_ORIGIN',
    defaultValue: 'https://caiwu-app',
  );

  /// 是否为调试构建。
  static bool get isDebug => !const bool.fromEnvironment('dart.vm.product');

  /// 把 API 基地址转换为 WebSocket 基地址的兜底推导。
  static String resolveWsBase() {
    if (wsBase.isNotEmpty) return wsBase;
    final uri = Uri.parse(apiBase);
    final scheme = uri.scheme == 'https' ? 'wss' : 'ws';
    return Uri(scheme: scheme, host: uri.host, port: uri.hasPort ? uri.port : null)
        .toString();
  }

  /// 解析后端下发的 api_base（可能是 https://host）为 WebSocket 基地址。
  static String wsBaseFromApiBase(String apiBaseUrl) {
    final uri = Uri.tryParse(apiBaseUrl);
    if (uri == null || uri.host.isEmpty) return resolveWsBase();
    final scheme = uri.scheme == 'https' ? 'wss' : 'ws';
    final port = uri.hasPort ? ':${uri.port}' : '';
    return '$scheme://${uri.host}$port';
  }

  /// 设备是否运行在 Android 模拟器（用于默认地址提示）。
  static Future<bool> isAndroidEmulator() async {
    if (!Platform.isAndroid) return false;
    final fingerprint = await _readAndroidFingerprint();
    return fingerprint.contains('generic') || fingerprint.contains('emulator');
  }

  static Future<String> _readAndroidFingerprint() async {
    try {
      final result = await Process.run('getprop', ['ro.build.fingerprint']);
      return (result.stdout as String).trim();
    } catch (_) {
      return '';
    }
  }
}
