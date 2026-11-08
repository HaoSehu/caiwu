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

  /// 触控最小热区（Material / WCAG 建议 44×44，iOS HIG 48×48）。
  ///
  /// 视觉尺寸可以更小，但命中区不得低于此值：图标按钮、文字链、
  /// 胶囊标签都必须用它兜底。
  static const double minTapTarget = 44;

  /// 区块之间的纵向间距：手机 20，平板 28。
  ///
  /// 列表流页面里，区块之间是一段页面底色带；24/32 在实机上看会让页面
  /// 显得断裂、需要更多滚动，收到 20/28 后节奏更紧、同屏信息更多。
  static double sectionGap(bool tablet) => tablet ? 28 : 20;

  /// 列表分组标题的上下留白（标题紧贴自己的白色块，不额外撑开灰带）。
  static const double sectionHeaderTop = gapS;
  static const double sectionHeaderBottom = gapXs;
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

  /// 辅助文字/时间戳：`fontBodySmall`（12/20）+ 次色。
  ///
  /// 时间、到期日、状态说明都属于可读信息，必须用次色（对比度 4.5:1 以上）；
  /// 占位色只留给真正的输入占位文本，否则小字在灰底上糊成一片。
  static TextStyle caption(TThemeData theme) => TextStyle(
        fontSize: theme.fontBodySmall?.size ?? 12,
        height: theme.fontBodySmall?.height ?? 20 / 12,
        color: theme.textColorSecondary,
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

  /// 等宽通用样式：IP、指纹、序列号等技术标识。
  ///
  /// 与 [money] 同为等宽数字，但字重正常——金额用中等字重强调，
  /// 技术标识只需要对齐，不需要加粗。
  static TextStyle mono(
    TThemeData theme, {
    double size = 14,
    Color? color,
    FontWeight weight = FontWeight.w400,
  }) =>
      TextStyle(
        fontSize: size,
        color: color ?? theme.textColorPrimary,
        fontWeight: weight,
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

  /// 金额阶梯：页面级大额（账单应付、订单实付）。
  static TextStyle amountDisplay(TThemeData theme, {Color? color}) =>
      stat(theme, size: 32, color: color);

  /// 金额阶梯：卡片内主金额（余额、页头余额）。
  static TextStyle amountLarge(TThemeData theme, {Color? color}) =>
      stat(theme, size: 24, color: color);

  /// 金额阶梯：列表行金额。
  static TextStyle amountRow(TThemeData theme, {Color? color}) =>
      money(theme, size: 16, color: color);

  /// 金额阶梯：辅助金额（小字备注、折扣后价格）。
  static TextStyle amountSmall(TThemeData theme, {Color? color}) =>
      money(theme, size: 14, color: color);
}
