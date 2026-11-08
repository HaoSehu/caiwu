import 'package:flutter/widgets.dart';

/// 屏幕尺寸断点，口径对齐 `docs/DESIGN.md` §7（Flutter 侧等价实现）。
///
/// - `compact`：手机竖屏（< 600）
/// - `medium`：大屏手机 / 小平板竖屏（600–840）
/// - `expanded`：平板横屏 / 桌面（>= 840）
enum WindowSize { compact, medium, expanded }

/// 断点常量。与 Web 端 768/992/1200 不同，这里采用 Material 3 的
/// 600/840，因为移动端要覆盖手机横屏与平板分屏，600 更符合触控设备实际。
class Breakpoints {
  Breakpoints._();

  static const double medium = 600;
  static const double expanded = 840;

  /// 内容区最大宽度：超大屏（平板横屏）时居中收窄，避免行宽过长。
  static const double contentMaxWidth = 840;

  /// 表单页最大宽度：表单字段过宽会难以扫读。
  static const double formMaxWidth = 560;

  /// 手机横屏的内容最大宽度。
  ///
  /// 手机横屏宽度会超过 600，但设备仍是手机：版式必须保持单列 + 底部 Tab，
  /// 只把内容收窄到这一宽度居中，避免单行过长。
  static const double phoneLandscapeMaxWidth = 640;

  /// 纯按宽度分档。
  ///
  /// 页面布局请统一用 [resolve]：只看宽度会把手机横屏误判成平板。
  /// 这里保留是因为 [resolve] 在通过短边校验后仍需按宽度选档。
  static WindowSize of(double width) {
    if (width >= expanded) return WindowSize.expanded;
    if (width >= medium) return WindowSize.medium;
    return WindowSize.compact;
  }

  /// 设备级断点：以短边判定设备类别，再按宽度分档。
  ///
  /// 只看宽度会把手机横屏（如 844×390）误判为平板，导致登录页切到横屏分栏、
  /// 列表改双栏、底部 Tab 换成侧边导航——设备并没有变，只是转了方向。
  /// 短边 < 600 一律按手机处理，与阿里云 App 的手机横屏行为一致。
  static WindowSize resolve(Size size) {
    if (size.shortestSide < medium) return WindowSize.compact;
    return of(size.width);
  }
}

/// 读取当前窗口尺寸的 InheritedWidget，供深层组件按断点取布局参数。
class WindowSizeProvider extends InheritedWidget {
  const WindowSizeProvider({
    super.key,
    required this.size,
    required this.width,
    this.isLandscapePhone = false,
    required super.child,
  });

  final WindowSize size;
  final double width;

  /// 手机横屏：宽度已超过 600，但设备是手机。
  final bool isLandscapePhone;

  /// 未包裹时按 compact 处理，保证单独预览组件不会崩。
  static WindowSize of(BuildContext context) {
    final provider =
        context.dependOnInheritedWidgetOfExactType<WindowSizeProvider>();
    return provider?.size ?? WindowSize.compact;
  }

  static double widthOf(BuildContext context) {
    final provider =
        context.dependOnInheritedWidgetOfExactType<WindowSizeProvider>();
    return provider?.width ?? MediaQuery.sizeOf(context).width;
  }

  static bool isTablet(BuildContext context) =>
      of(context) != WindowSize.compact;

  static bool isLandscapePhoneOf(BuildContext context) =>
      context
          .dependOnInheritedWidgetOfExactType<WindowSizeProvider>()
          ?.isLandscapePhone ??
      false;

  @override
  bool updateShouldNotify(WindowSizeProvider oldWidget) =>
      size != oldWidget.size ||
      isLandscapePhone != oldWidget.isLandscapePhone;
}

/// 便捷扩展：`context.windowSize` / `context.isTablet`。
extension WindowSizeContext on BuildContext {
  WindowSize get windowSize => WindowSizeProvider.of(this);
  bool get isTablet => WindowSizeProvider.of(this) != WindowSize.compact;
  bool get isExpanded => WindowSizeProvider.of(this) == WindowSize.expanded;

  /// 手机横屏：保持手机版式，内容限宽居中以控制行宽。
  bool get isLandscapePhone => WindowSizeProvider.isLandscapePhoneOf(this);

  /// 横向可用宽度：手机横屏时按 [Breakpoints.phoneLandscapeMaxWidth] 收窄。
  bool get needsLandscapeLimiter =>
      isLandscapePhone && WindowSizeProvider.widthOf(this) > Breakpoints.phoneLandscapeMaxWidth;

  /// 统一的页面水平内边距：手机 16，平板 24。
  double get pagePadding =>
      WindowSizeProvider.of(this) == WindowSize.compact ? 16 : 24;

  /// 布局列数：手机 2，平板 3，平板横屏 4。
  int get gridColumns => switch (WindowSizeProvider.of(this)) {
        WindowSize.compact => 2,
        WindowSize.medium => 3,
        WindowSize.expanded => 4,
      };

  /// 详情页字段网格列数：与 [gridColumns] 相同，但手机横屏仍取 2。
  ///
  /// 手机横屏虽然宽，字段多了会挤成一条条窄字，反而更难扫读。
  int get fieldColumns => isLandscapePhone ? 2 : gridColumns;
}

/// 把子组件限制在最大宽度内并水平居中，用于平板/超宽屏。
///
/// 手机竖屏原样返回，不引入任何额外布局节点；手机横屏收窄到
/// [Breakpoints.phoneLandscapeMaxWidth]，因为此时宽度已超过 600 但行宽仍不宜过长。
class ContentWidthLimiter extends StatelessWidget {
  const ContentWidthLimiter({
    super.key,
    required this.child,
    this.maxWidth = Breakpoints.contentMaxWidth,
  });

  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    if (!context.isTablet) {
      if (!context.needsLandscapeLimiter) return child;
      return Center(
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxWidth: maxWidth < Breakpoints.phoneLandscapeMaxWidth
                ? maxWidth
                : Breakpoints.phoneLandscapeMaxWidth,
          ),
          child: child,
        ),
      );
    }
    return Center(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: child,
      ),
    );
  }
}

/// 双栏列表的分栏切分：按奇偶交替分到左右两栏。
///
/// **不能用「前半/后半」**：列表是分页追加的，中点切分时每多加载一页中点就
/// 右移，已渲染的行会整批从左栏跳到右栏。奇偶切分下第 i 条永远落在同一栏，
/// 追加只会出现在两栏末尾。见 `test/two_column_split_test.dart`。
({List<T> left, List<T> right}) splitIntoTwoColumns<T>(List<T> items) {
  final left = <T>[];
  final right = <T>[];
  for (var index = 0; index < items.length; index++) {
    (index.isEven ? left : right).add(items[index]);
  }
  return (left: left, right: right);
}
