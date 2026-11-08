import 'package:caiwu_app/core/theme/app_theme.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

/// 主题令牌的守门测试。
///
/// `TThemeData.copyWith` 不继承 `light`/`dark`，而 `context.tTheme` 读的是
/// `Theme.of(context)` 上挂的那个实例——覆盖写错位置时不会报错，只会静默失效。
void main() {
  group('AppTheme.tokenTheme', () {
    test('品牌色阶生效在 tTheme 实际读取的实例上', () {
      final theme = AppTheme.tokenTheme();
      expect(theme.brandColor, AppTheme.brand6);
      expect(theme.brandColor.toARGB32(), 0xFF165DFF);
      expect(theme.bgColorPage.toARGB32(), 0xFFF5F7FB);
      // 分割线收紧到 grayColor2：成片列表里不能出现深灰分隔。
      expect(theme.borderLevel1Color.toARGB32(), 0xFFE5EAF3);
      expect(theme.componentStroke.toARGB32(), 0xFFE5EAF3);
    });

    test('中文字体栈挂在返回的实例上，而不是只改 light', () {
      final theme = AppTheme.tokenTheme();
      final family = theme.fontFamily;
      expect(family, isNotNull, reason: 'fontFamilyMap.fontFamily 缺失');
      expect(family!.fontFamily, 'HarmonyOS Sans SC');
      expect(family.fallback, contains('Noto Sans CJK SC'));
      // 默认栈是 PingFang SC（iOS 字体），Android 上会解析成 Roboto。
      expect(family.fontFamily, isNot('PingFang SC'));
      expect(theme.fontFamilyMedium?.fontFamily, 'HarmonyOS Sans SC');
    });

    test('light 指向自身，不会把裸默认主题漏给组件', () {
      final theme = AppTheme.tokenTheme();
      // copyWith 之后 light 必须仍能在 tTheme 上取到（组件内部会读 theme.light）。
      expect(theme.light, isNotNull);
      expect(theme.light.brandColor, AppTheme.brand6);
    });

    test('全局尺寸与字号令牌未被改动', () {
      final theme = AppTheme.tokenTheme();
      expect(theme.radiusLarge, 9);
      expect(theme.fontTitleLarge?.size, 18);
      expect(theme.fontBodyMedium?.size, 14);
      expect(theme.fontBodySmall?.size, 12);
    });
  });
}
