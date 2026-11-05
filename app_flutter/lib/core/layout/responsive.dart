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

  static WindowSize of(double width) {
    if (width >= expanded) return WindowSize.expanded;
    if (width >= medium) return WindowSize.medium;
    return WindowSize.compact;
  }
}

/// 读取当前窗口尺寸的 InheritedWidget，供深层组件按断点取布局参数。
class WindowSizeProvider extends InheritedWidget {
  const WindowSizeProvider({
    super.key,
    required this.size,
    required this.width,
    required super.child,
  });

  final WindowSize size;
  final double width;

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

  @override
  bool updateShouldNotify(WindowSizeProvider oldWidget) => size != oldWidget.size;
}

/// 便捷扩展：`context.windowSize` / `context.isTablet`。
extension WindowSizeContext on BuildContext {
  WindowSize get windowSize => WindowSizeProvider.of(this);
  bool get isTablet => WindowSizeProvider.of(this) != WindowSize.compact;
  bool get isExpanded => WindowSizeProvider.of(this) == WindowSize.expanded;

  /// 统一的页面水平内边距：手机 16，平板 24。
  double get pagePadding =>
      WindowSizeProvider.of(this) == WindowSize.compact ? 16 : 24;

  /// 布局列数：手机 2，平板 3。
  int get gridColumns => switch (WindowSizeProvider.of(this)) {
        WindowSize.compact => 2,
        WindowSize.medium => 3,
        WindowSize.expanded => 4,
      };
}

/// 把子组件限制在最大宽度内并水平居中，用于平板/超宽屏。
///
/// 手机端原样返回，不引入任何额外布局节点。
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
    if (!context.isTablet) return child;
    return Center(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: child,
      ),
    );
  }
}
