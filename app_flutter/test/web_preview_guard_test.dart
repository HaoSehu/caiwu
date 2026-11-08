import 'package:caiwu_app/core/config/web_preview.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';

/// 预览开关的守卫：Web 预览会注入假登录态并接管所有请求，
/// 必须在非 Web 构建（Android 发布包）里彻底关闭，且默认值不能是「开」。
void main() {
  test('非 Web 环境下预览与 mock API 全部关闭', () {
    expect(kIsWeb, isFalse);
    expect(WebPreview.enabled, isFalse);
    expect(WebPreview.useMockApi, isFalse);
  });

  test('默认关闭：不传 ENABLE_WEB_PREVIEW 时预览开关为假', () {
    // 默认值为 false 是这道防线的关键——否则任何 `flutter build web`
    // 产物部署到公网，都会出现一个假余额、假 root 密码的控制台。
    const requested = bool.fromEnvironment('ENABLE_WEB_PREVIEW');
    if (requested) {
      // 有人显式用该 define 跑测试时，只断言「非 Web 仍然关闭」，
      // 不能因此判失败（否则 CI 换个命令就红）。
      expect(kIsWeb, isFalse);
      expect(WebPreview.enabled, isFalse);
      return;
    }
    expect(WebPreview.enabled, isFalse);
  });

  test('演示用户字段与 auth/info 口径一致', () {
    expect(WebPreview.demoUser['id'], isA<int>());
    expect(WebPreview.demoUser['display_name'], isNotEmpty);
    expect(WebPreview.demoUser['cash_balance'], isNotEmpty);
  });
}
