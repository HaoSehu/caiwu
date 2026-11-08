import 'package:flutter/foundation.dart';

/// 仅 Web 预览用的演示会话与 mock 数据开关。
///
/// **默认关闭**。因为它会注入一个假登录态并接管全部请求，一旦随正式 Web
/// 产物发布出去，公网上就会出现一个「假余额、假 root 密码」的控制台。
/// 本地做 UI 走查时必须显式开启：
///
/// ```bash
/// cd app_flutter
/// flutter build web --dart-define=ENABLE_WEB_PREVIEW=true
/// python -m http.server 8099 --directory build/web
/// ```
///
/// 三层约束：
/// 1. 编译期 `ENABLE_WEB_PREVIEW` 默认为 false，正式构建不传即为关闭；
/// 2. 运行期 `kIsWeb` 保证 Android/iOS 原生包里恒不生效；
/// 3. `test/web_preview_guard_test.dart` 断言非 Web 构建下所有开关为假。
class WebPreview {
  WebPreview._();

  static const bool _requested =
      bool.fromEnvironment('ENABLE_WEB_PREVIEW', defaultValue: false);

  /// 是否为 Web 预览模式。
  static bool get enabled => kIsWeb && _requested;

  /// 预览 token：只用来让 `Session.isLoggedIn` 为真，
  /// 所有数据由本地 mock 适配器返回。
  static const String demoToken = 'web-preview-token';

  /// 预览用户：字段口径与 `/v2/client/auth/info` 的 `user` 对象一致。
  static const Map<String, dynamic> demoUser = <String, dynamic>{
    'id': 1001,
    'nickname': '李维佳',
    'display_name': '李维佳',
    'email': 'demo@caiwu.test',
    'phone': '13800000000',
    'cash_balance': '99785.40',
    'created_at': '2026-01-12 09:30:00',
  };

  /// 是否走本地 mock 适配器（预览态即走 mock，不发任何真实请求）。
  static bool get useMockApi => enabled;
}
