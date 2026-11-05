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
                // 平板/横屏时限制内容宽度并居中，避免行宽过长难以扫读。
                : ContentWidthLimiter(child: body!),
          ),
        ],
      ),
      bottomNavigationBar: bottomBar == null
          ? null
          : Container(
              decoration: BoxDecoration(
                color: theme.bgColorContainer,
                border: Border(top: BorderSide(color: theme.borderLevel1Color)),
              ),
              child: SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.page,
                    AppSpacing.gapM,
                    AppSpacing.page,
                    AppSpacing.gapM,
                  ),
                  child: bottomBar,
                ),
              ),
            ),
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

/// 白色内容块：圆角与描边取自 TDesign Token（`radiusLarge` / `borderLevel1`）。
///
/// 这是 TDesign「卡片」的默认观感：**9 圆角、1px 描边、无投影**。
class AppCard extends StatelessWidget {
  const AppCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.cellHorizontal),
    this.onTap,
    this.margin,
    this.highlight = false,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry? margin;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    final radius = BorderRadius.circular(theme.radiusLarge);
    final card = Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: highlight ? theme.brandColorLight : theme.bgColorContainer,
        borderRadius: radius,
        border: Border.all(
          color: highlight ? theme.brandColorFocus : theme.borderLevel1Color,
        ),
      ),
      child: child,
    );
    if (onTap == null) return card;
    return Material(
      color: Colors.transparent,
      borderRadius: radius,
      clipBehavior: Clip.antiAlias,
      child: InkWell(onTap: onTap, child: card),
    );
  }
}

/// 区块标题：`TText` 标题样式 + 可选右侧动作。
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
        AppSpacing.gapS,
        AppSpacing.gapXs,
        AppSpacing.gapS,
      ),
      child: Row(
        children: [
          Expanded(child: Text(title, style: AppType.section(theme))),
          if (trailing != null) trailing!,
          if (actionText != null)
            GestureDetector(
              onTap: onAction,
              behavior: HitTestBehavior.opaque,
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  children: [
                    Text(
                      actionText!,
                      style: AppType.secondary(theme).copyWith(
                        color: theme.brandColor,
                        fontSize: 13,
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

/// 两列字段网格：上标签（12 次色）+ 下取值（14 主色）。
class AppFieldGrid extends StatelessWidget {
  const AppFieldGrid({
    super.key,
    required this.items,
    this.columns = 2,
  });

  final List<(String label, String value)> items;
  final int columns;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    final visible = items
        .where((item) => item.$2.trim().isNotEmpty && item.$2.trim() != '--')
        .toList();
    if (visible.isEmpty) return const SizedBox.shrink();
    return LayoutBuilder(
      builder: (context, constraints) {
        final width =
            (constraints.maxWidth - (columns - 1) * AppSpacing.gapL) / columns;
        return Wrap(
          spacing: AppSpacing.gapL,
          runSpacing: AppSpacing.gapL,
          children: [
            for (final item in visible)
              SizedBox(
                width: width,
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

/// 页内分段器：TDesign `TTabBar`（text 型），外观与官方 Tab 一致。
///
/// 用于详情页页签、列表筛选等场景，替代自绘下划线。
class AppSegmentedTabs extends StatelessWidget {
  const AppSegmentedTabs({
    super.key,
    required this.labels,
    required this.current,
    required this.onChanged,
    this.scrollable = false,
  });

  final List<String> labels;
  final int current;
  final ValueChanged<int> onChanged;
  final bool scrollable;

  @override
  Widget build(BuildContext context) {
    final tabs = TTabBar(
      type: TTabBarType.text,
      value: current,
      onChanged: onChanged,
      useSafeArea: false,
      navigationTabs: [
        for (final label in labels) TTabBarItemConfig(tabText: label),
      ],
    );
    return Container(
      color: context.tTheme.bgColorContainer,
      child: tabs,
    );
  }
}

/// 可选胶囊：TDesign `TTag` 的选中/未选中两种形态。
class AppChip extends StatelessWidget {
  const AppChip({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: TTag(
        label,
        size: TTagSize.large,
        variant: selected ? TTagVariant.light : TTagVariant.lightOutline,
        colorPreset:
            selected ? TTagColorPreset.primary : TTagColorPreset.defaultTheme,
        shape: TTagShape.round,
      ),
    );
  }
}
