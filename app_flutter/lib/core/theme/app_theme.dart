import 'package:tdesign_flutter/tdesign_flutter.dart';

/// Caiwu App 主题。
///
/// 色值来源：`docs/DESIGN.md` §3.1（TDesign 设计令牌），主色 #165DFF 及同级色阶；
/// 其余语义色（成功/警告/错误/文字）继续使用 TDesign 默认 token，页面内不允许硬编码色值。
class AppTheme {
  AppTheme._();

  /// 品牌主色 6 级（DESIGN.md `--td-brand-color-6`）。
  static const Color brand6 = Color(0xFF165DFF);

  /// 组装全局 Token：在 TDesign 默认主题基础上替换品牌色阶。
  static TThemeData tokenTheme() {
    final base = TThemeData.defaultData();
    final light = base.light;
    final colorMap = Map<String, Color>.from(light.colorMap)
      ..addAll({
        // 品牌蓝色阶（TDesign 蓝，主色 #165DFF）
        'brandColor1': const Color(0xFFE8F1FF),
        'brandColor2': const Color(0xFFC9DBFF),
        'brandColor3': const Color(0xFF9DBEFF),
        'brandColor4': const Color(0xFF6EA0FF),
        'brandColor5': const Color(0xFF2A7DFF),
        'brandColor6': brand6,
        'brandColor7': const Color(0xFF0E4FCC),
        'brandColor8': const Color(0xFF093A99),
        'brandColor9': const Color(0xFF062966),
        'brandColor10': const Color(0xFF041A40),
        // 语义别名（DESIGN.md §3.1）
        'brandColor': brand6,
        'brandColorLight': const Color(0xFFE8F1FF),
        'brandColorFocus': const Color(0xFFC9DBFF),
        'brandColorDisabled': const Color(0xFFC9DBFF),
        'brandColorActive': const Color(0xFF0E4FCC),
        // 页面底色与文字（DESIGN.md §3.1）
        'bgColorPage': const Color(0xFFF5F7FB),
        'textColorPrimary': const Color(0xFF1F2937),
        'textColorSecondary': const Color(0xFF5B6B82),
        'textColorPlaceholder': const Color(0xFF606D80),
      });
    final themed = light.copyWith(colorMap: colorMap);
    return base.copyWith(colorMap: colorMap)
      ..light = themed;
  }

  /// 供 MaterialApp.theme 使用的完整 ThemeData。
  static ThemeData lightTheme() => TThemeBuilder.light(tokenTheme());
}
