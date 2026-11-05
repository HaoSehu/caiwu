import 'package:tdesign_flutter/tdesign_flutter.dart';

/// 语义常量层：只补充 TDesign Token 没有、但页面反复需要的布局常量。
///
/// **圆角、字号、颜色一律取自 `context.tTheme`**（TDesign 设计令牌），
/// 这里不重复定义，避免与官方规范漂移。
class AppSpacing {
  AppSpacing._();

  /// 页面左右内边距。TDesign 移动端常用 16。
  static const double page = 16;

  /// Cell 通栏贴边时用于内容的内边距。
  static const double cellHorizontal = 16;

  static const double gapXs = 4;
  static const double gapS = 8;
  static const double gapM = 12;
  static const double gapL = 16;
  static const double gapXl = 24;

  /// 页面底部留白。
  static const double pageBottom = 24;

  /// 兼容别名：卡片内边距（与 Cell 左右内边距一致）。
  static const double cardPadding = cellHorizontal;
}

/// 兼容别名：值对齐 TDesign 官方圆角令牌。
///
/// 新代码请直接用 `context.tTheme.radiusSmall/radiusDefault/radiusLarge/radiusExtraLarge`。
class AppRadius {
  AppRadius._();

  /// = TDesign `radiusLarge`（9）。
  static const double card = 9;

  /// = TDesign `radiusExtraLarge`（12）。
  static const double sheet = 12;

  static const double pill = 999;
}

/// 文字样式：直接从 TDesign 字体令牌派生，不再自造字号体系。
class AppType {
  AppType._();

  /// 页面标题：TDesign `fontTitleLarge`（18/26 600）。
  static TextStyle pageTitle(TThemeData theme) => TextStyle(
        fontSize: theme.fontTitleLarge?.size ?? 18,
        height: theme.fontTitleLarge?.height ?? 26 / 18,
        fontWeight: theme.fontTitleLarge?.fontWeight ?? FontWeight.w600,
        color: theme.textColorPrimary,
      );

  /// 区块标题：TDesign `fontTitleSmall`（14/22 600）。
  static TextStyle section(TThemeData theme) => TextStyle(
        fontSize: theme.fontTitleSmall?.size ?? 14,
        height: theme.fontTitleSmall?.height ?? 22 / 14,
        fontWeight: theme.fontTitleSmall?.fontWeight ?? FontWeight.w600,
        color: theme.textColorPrimary,
      );

  /// 卡片/列表主文案：TDesign 正文 `fontBodyLarge`（16/24）。
  static TextStyle cardTitle(TThemeData theme) => TextStyle(
        fontSize: theme.fontBodyLarge?.size ?? 16,
        height: theme.fontBodyLarge?.height ?? 24 / 16,
        fontWeight: FontWeight.w500,
        color: theme.textColorPrimary,
      );

  /// 正文：`fontBodyMedium`（14/22）。
  static TextStyle body(TThemeData theme) => TextStyle(
        fontSize: theme.fontBodyMedium?.size ?? 14,
        height: theme.fontBodyMedium?.height ?? 22 / 14,
        color: theme.textColorPrimary,
      );

  /// 次要说明：`fontBodyMedium` + 次色。
  static TextStyle secondary(TThemeData theme) => TextStyle(
        fontSize: theme.fontBodyMedium?.size ?? 14,
        height: theme.fontBodyMedium?.height ?? 22 / 14,
        color: theme.textColorSecondary,
      );

  /// 辅助文字/时间戳：`fontBodySmall`（12/20）。
  static TextStyle caption(TThemeData theme) => TextStyle(
        fontSize: theme.fontBodySmall?.size ?? 12,
        height: theme.fontBodySmall?.height ?? 20 / 12,
        color: theme.textColorPlaceholder,
      );

  /// 数值强调（等宽数字，刷新不抖动）。
  static TextStyle stat(TThemeData theme, {double size = 24, Color? color}) =>
      TextStyle(
        fontSize: size,
        height: 1.2,
        fontWeight: FontWeight.w600,
        color: color ?? theme.textColorPrimary,
        fontFeatures: const [FontFeature.tabularFigures()],
      );

  /// 金额（等宽数字）。
  static TextStyle money(
    TThemeData theme, {
    double size = 16,
    Color? color,
    FontWeight weight = FontWeight.w500,
  }) =>
      TextStyle(
        fontSize: size,
        fontWeight: weight,
        color: color ?? theme.textColorPrimary,
        fontFeatures: const [FontFeature.tabularFigures()],
      );
}
