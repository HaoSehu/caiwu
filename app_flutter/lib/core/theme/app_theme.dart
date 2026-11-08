import 'package:tdesign_flutter/tdesign_flutter.dart';

/// Caiwu App 主题。
///
/// 色值来源：`docs/DESIGN.md` §3.1（TDesign 设计令牌），主色 #165DFF 及同级色阶；
/// 其余语义色（成功/警告/错误/文字）继续使用 TDesign 默认 token，页面内不允许硬编码色值。
class AppTheme {
  AppTheme._();

  /// 品牌主色 6 级（DESIGN.md `--td-brand-color-6`）。
  static const Color brand6 = Color(0xFF165DFF);

  /// 中文字体栈：Android 优先 HarmonyOS Sans / MiSans（两家国产 ROM 自带且
  /// 中文优化好），再回落到系统默认与 Roboto。
  ///
  /// TDesign 默认栈是 `PingFang SC`（iOS 字体），在 Android 上会解析为
  /// Roboto，中文字形由系统兜底，字重与行高在不同 ROM 上不一致。显式给
  /// Android 一套可用栈，保证同一份设计稿在三家 ROM 上观感接近。
  static const List<String> _cjkFallback = <String>[
    'HarmonyOS Sans SC',
    'MiSans',
    'Source Han Sans SC',
    'Noto Sans CJK SC',
    'Roboto',
  ];

  /// 组装全局 Token：在 TDesign 默认主题基础上替换品牌色阶与中文字体栈。
  ///
  /// `TThemeData.copyWith` 不会继承 `light`/`dark`（新实例的 `light` 指向自身），
  /// 而 `context.tTheme` 读的正是 `Theme.of(context)` 上挂的这个实例。
  /// 所以字体与色板必须一起写进返回的那个 copyWith，不能只改 `light`。
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
        'bgColorContainer': const Color(0xFFFFFFFF),
        'textColorPrimary': const Color(0xFF1F2937),
        'textColorSecondary': const Color(0xFF5B6B82),
        'textColorPlaceholder': const Color(0xFF606D80),
        // 分割线：默认的 grayColor3(#CFD5DE) 在成片列表里像一道道灰线，
        // 收紧到 grayColor2，只作为「同组内不同项」的极淡分隔。
        'componentStroke': const Color(0xFFE5EAF3),
        'borderLevel1Color': const Color(0xFFE5EAF3),
      });
    return base.copyWith(
      colorMap: colorMap,
      fontFamilyMap: {
        ...light.fontFamilyMap,
        'fontFamily': FontFamily(
          fontFamily: 'HarmonyOS Sans SC',
          fallback: _cjkFallback,
        ),
        'fontFamilyMedium': FontFamily(
          fontFamily: 'HarmonyOS Sans SC',
          fallback: _cjkFallback,
        ),
      },
    );
  }

  /// 供 MaterialApp.theme 使用的完整 ThemeData。
  static ThemeData lightTheme() => TThemeBuilder.light(tokenTheme());
}
