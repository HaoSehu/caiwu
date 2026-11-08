import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../core/layout/responsive.dart';
import '../core/theme/app_tokens.dart';

/// 页面骨架：TDesign `TNavBar` + 内容区。
///
/// 顶部栏、背景色、分割全部交给 TDesign 官方组件；
/// 这里只负责「标题栏 + body + 底部固定操作区」的组合与返回兜底。
class AppScaffold extends StatelessWidget {
  const AppScaffold({
    super.key,
    this.title,
    this.actions,
    this.showBack = true,
    this.onBack,
    this.body,
    this.bottomBar,
    this.backgroundColor,
  });

  final String? title;
  final List<Widget>? actions;
  final bool showBack;
  final VoidCallback? onBack;
  final Widget? body;
  final Widget? bottomBar;
  final Color? backgroundColor;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return Scaffold(
      backgroundColor: backgroundColor ?? theme.bgColorPage,
      body: Column(
        children: [
          if (title != null || actions != null)
            TNavBar(
              title: title == null ? null : Text(title!),
              useSafeArea: true,
              useDefaultBack: showBack,
              onBack: showBack ? (onBack ?? () => _popOrHome(context)) : null,
              actions: actions
                  ?.map((widget) => TNavBarItem(customWidget: widget))
                  .toList(),
              useBorderStyle: true,
            )
          else
            SizedBox(height: MediaQuery.paddingOf(context).top),
          Expanded(
            child: body == null
                ? const SizedBox.shrink()
                // 平板/手机横屏时限制内容宽度并居中，避免行宽过长难以扫读。
                : ContentWidthLimiter(child: body!),
          ),
        ],
      ),
      bottomNavigationBar:
          bottomBar == null ? null : _buildBottomBar(context, theme),
    );
  }

  /// 底部固定操作区：平板/手机横屏时按钮限宽居中，避免通栏按钮拉得过长。
  Widget _buildBottomBar(BuildContext context, TThemeData theme) {
    final bar = Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.page,
        AppSpacing.gapM,
        AppSpacing.page,
        AppSpacing.gapM,
      ),
      child: bottomBar!,
    );
    final limited = !context.isTablet && !context.needsLandscapeLimiter
        ? bar
        : Center(
            child: ConstrainedBox(
              constraints:
                  const BoxConstraints(maxWidth: Breakpoints.formMaxWidth),
              child: bar,
            ),
          );
    return Container(
      decoration: BoxDecoration(
        color: theme.bgColorContainer,
        border: Border(top: BorderSide(color: theme.borderLevel1Color)),
      ),
      child: SafeArea(top: false, child: limited),
    );
  }

  void _popOrHome(BuildContext context) {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go('/home');
    }
  }
}

/// 白色内容块。
///
/// 移动端卡片靠「白底 + 极淡描边」与页面底色区分，不加投影：
/// 投影会让每个块都像浮起的卡片，堆多了就是模板感。
/// [flat] 用于通栏块（工具宫格、分组列表），直接铺白底、不带圆角与描边。
class AppCard extends StatelessWidget {
  const AppCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.cellHorizontal),
    this.onTap,
    this.margin,
    this.highlight = false,
    this.flat = false,
    this.radius,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry? margin;
  final bool highlight;

  /// 通栏块：无圆角、无描边，只有白底。
  final bool flat;

  /// 覆盖默认圆角。
  final double? radius;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    final shape = flat
        ? BorderRadius.zero
        : BorderRadius.circular(radius ?? theme.radiusLarge);
    final card = Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: highlight ? theme.brandColorLight : theme.bgColorContainer,
        borderRadius: shape,
        border: flat
            ? null
            : Border.all(
                color: highlight ? theme.brandColorFocus : theme.borderLevel1Color,
              ),
      ),
      child: child,
    );
    if (onTap == null) return card;
    return Material(
      color: Colors.transparent,
      borderRadius: shape,
      clipBehavior: Clip.antiAlias,
      child: InkWell(onTap: onTap, child: card),
    );
  }
}

/// 区块标题：`TText` 标题样式 + 可选右侧动作。
///
/// 右侧动作包成 44 高、最小 44 宽的命中区（视觉仍是 13 号字 + 小箭头），
/// 因为「全部 / 查看全部」出现在几乎每个区块上，是全 App 点击最频繁的目标之一。
class AppSectionHeader extends StatelessWidget {
  const AppSectionHeader({
    super.key,
    required this.title,
    this.actionText,
    this.onAction,
    this.trailing,
  });

  final String title;
  final String? actionText;
  final VoidCallback? onAction;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.gapXs,
        AppSpacing.sectionHeaderTop,
        AppSpacing.gapXs,
        AppSpacing.sectionHeaderBottom,
      ),
      child: Row(
        children: [
          Expanded(child: Text(title, style: AppType.section(theme))),
          if (trailing != null) trailing!,
          if (actionText != null)
            InkWell(
              onTap: onAction,
              borderRadius: BorderRadius.circular(theme.radiusDefault),
              child: ConstrainedBox(
                constraints: const BoxConstraints(
                  minHeight: AppSpacing.minTapTarget,
                  minWidth: AppSpacing.minTapTarget,
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      actionText!,
                      style: AppType.caption(theme).copyWith(
                        color: theme.brandColor,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    Icon(TIcons.chevron_right, size: 16, color: theme.brandColor),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// 键值行，用于详情页字段。
class AppKeyValue extends StatelessWidget {
  const AppKeyValue({
    super.key,
    required this.label,
    required this.value,
    this.valueStyle,
    this.valueColor,
  });

  final String label;
  final String value;
  final TextStyle? valueStyle;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 88, child: Text(label, style: AppType.secondary(theme))),
          Expanded(
            child: Text(
              value,
              style: valueStyle ?? AppType.body(theme).copyWith(color: valueColor),
            ),
          ),
        ],
      ),
    );
  }
}

/// 字段网格：上标签（12 次色）+ 下取值（14 主色）。
///
/// 默认列数随断点走：手机 2 列、平板 3 列、平板横屏 4 列。
/// 详情页字段在平板上继续排两列会留下大片空白，与阿里云 App 的信息密度不符。
class AppFieldGrid extends StatelessWidget {
  const AppFieldGrid({
    super.key,
    required this.items,
    this.columns,
  });

  final List<(String label, String value)> items;

  /// 显式列数；不传时按窗口断点自动取 2 / 3 / 4。
  final int? columns;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    final visible = items
        .where((item) => item.$2.trim().isNotEmpty && item.$2.trim() != '--')
        .toList();
    if (visible.isEmpty) return const SizedBox.shrink();
    final count = columns ?? context.fieldColumns;
    return LayoutBuilder(
      builder: (context, constraints) {
        // 极窄容器（嵌套在分栏的窄列里、或平板分屏的小窗）下，
        // (maxWidth - 间距) 可能为负，SizedBox(width: 负数) 会直接抛断言。
        // 退化成一列，宽度取容器宽度。
        final available =
            constraints.maxWidth - (count - 1) * AppSpacing.gapL;
        final width = available <= 0 ? constraints.maxWidth : available / count;
        return Wrap(
          spacing: AppSpacing.gapL,
          runSpacing: AppSpacing.gapL,
          children: [
            for (final item in visible)
              SizedBox(
                width: width < 0 ? 0 : width,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.$1, style: AppType.caption(theme)),
                    const SizedBox(height: 2),
                    Text(
                      item.$2,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: AppType.body(theme),
                    ),
                  ],
                ),
              ),
          ],
        );
      },
    );
  }
}

/// 状态标签：直接使用 TDesign `TTag`（浅底、小圆角）。
class AppStatusTag extends StatelessWidget {
  const AppStatusTag({
    super.key,
    required this.label,
    this.preset = TTagColorPreset.defaultTheme,
    this.size = TTagSize.small,
  });

  final String label;
  final TTagColorPreset preset;
  final TTagSize size;

  static TTagColorPreset presetOf(String tone) {
    final value = tone.toLowerCase();
    if (value.contains('success') ||
        value.contains('active') ||
        value.contains('running')) {
      return TTagColorPreset.success;
    }
    if (value.contains('warning') ||
        value.contains('pending') ||
        value.contains('suspend')) {
      return TTagColorPreset.warning;
    }
    if (value.contains('danger') ||
        value.contains('error') ||
        value.contains('abnormal') ||
        value.contains('fail')) {
      return TTagColorPreset.danger;
    }
    return TTagColorPreset.defaultTheme;
  }

  @override
  Widget build(BuildContext context) {
    if (label.trim().isEmpty) return const SizedBox.shrink();
    return TTag(
      label,
      size: size,
      variant: TTagVariant.light,
      colorPreset: preset,
    );
  }
}

/// 状态圆点。
class AppDot extends StatelessWidget {
  const AppDot({super.key, required this.color, this.size = 8});

  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
  }
}

/// 页内分段器：文字页签，选中项用下划线标记。
///
/// 不用 `TTabBar` 的文字型：它的固定条高 56 且自带上下留白，
/// 放在列表页顶部会把内容推得过远；这里按移动端常见规格（44 高、均分、
/// 下划线 2px）自绘，视觉与手势都更贴合原生。
class AppSegmentedTabs extends StatelessWidget {
  const AppSegmentedTabs({
    super.key,
    required this.labels,
    required this.current,
    required this.onChanged,
  });

  final List<String> labels;
  final int current;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    final tabs = Row(
      children: [
        for (var index = 0; index < labels.length; index++)
          Expanded(
            child: InkWell(
              onTap: () => onChanged(index),
              child: SizedBox(
                // 与 AppSpacing.minTapTarget 保持一致：页签是常点目标，
                // 不能因为「看起来更紧凑」把命中区压到 40。
                height: AppSpacing.minTapTarget,
                child: Container(
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    // 下划线取整列宽度：文字宽度各列不同，取文字宽会让
                    // 选中条长度忽长忽短，看起来像没对齐。
                    border: Border(
                      bottom: BorderSide(
                        color: index == current
                            ? theme.brandColor
                            : Colors.transparent,
                        width: 2,
                      ),
                    ),
                  ),
                  child: Text(
                    labels[index],
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppType.body(theme).copyWith(
                      fontWeight: index == current
                          ? FontWeight.w600
                          : FontWeight.w400,
                      color: index == current
                          ? theme.brandColor
                          : theme.textColorSecondary,
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
    return Container(color: theme.bgColorContainer, child: tabs);
  }
}

/// 可选胶囊：TDesign `TTag` 的选中/未选中两种形态。
///
/// 命中区由外层 InkWell 兜底到 44 高（TTag 本体只有 24–32 高），
/// 视觉尺寸不变，但手指点得中。
class AppChip extends StatelessWidget {
  const AppChip({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
    this.icon,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  /// 可选前置图标（如「筛选」的漏斗）。
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.pill),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: AppSpacing.minTapTarget),
        child: Center(
          widthFactor: 1,
          heightFactor: 1,
          child: TTag(
            label,
            icon: icon,
            size: TTagSize.large,
            variant: selected ? TTagVariant.light : TTagVariant.lightOutline,
            colorPreset:
                selected ? TTagColorPreset.primary : TTagColorPreset.defaultTheme,
            shape: TTagShape.round,
          ),
        ),
      ),
    );
  }
}
