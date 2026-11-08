import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';

/// 底部四 Tab 壳：控制台 / 服务 / 工单 / 我的。
///
/// - 手机（compact）：底部 `TTabBar`，符合移动端拇指可达。
/// - 平板（>=600）：改为**左侧导航栏**，避免平板上底部 Tab 显得空荡；
///   用 TDesign `TSideBar` 保持与官方组件库一致。
class HomeShell extends StatelessWidget {
  const HomeShell({super.key, required this.shell});

  final StatefulNavigationShell shell;

  static const _labels = ['控制台', '服务', '工单', '我的'];
  static const _icons = [
    TIcons.dashboard,
    TIcons.server,
    TIcons.ticket,
    TIcons.user,
  ];
  static const _selectedIcons = [
    TIcons.dashboard_filled,
    TIcons.server_filled,
    TIcons.ticket_filled,
    TIcons.user_filled,
  ];

  void _goBranch(int index, {bool initialLocation = false}) {
    if (index == shell.currentIndex && !initialLocation) return;
    HapticFeedback.selectionClick();
    shell.goBranch(index, initialLocation: initialLocation);
  }

  @override
  Widget build(BuildContext context) {
    if (context.isTablet) {
      return Scaffold(
        body: Row(
          children: [
            _buildSideBar(context),
            Expanded(child: shell),
          ],
        ),
      );
    }
    return Scaffold(body: shell, bottomNavigationBar: _buildBottomBar(context));
  }

  /// 平板左侧导航。
  Widget _buildSideBar(BuildContext context) {
    final theme = context.tTheme;
    return Container(
      width: 88,
      decoration: BoxDecoration(
        color: theme.bgColorContainer,
        border: Border(right: BorderSide(color: theme.borderLevel1Color)),
      ),
      child: SafeArea(
        right: false,
        child: Column(
          children: [
            const SizedBox(height: AppVertical.brandTop),
            // 品牌标识
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: theme.brandColor,
                borderRadius: BorderRadius.circular(theme.radiusLarge),
              ),
              child: const Icon(TIcons.cloud, color: Colors.white, size: 22),
            ),
            const SizedBox(height: 20),
            for (var i = 0; i < _labels.length; i++)
              _SideBarItem(
                label: _labels[i],
                icon: _icons[i],
                selectedIcon: _selectedIcons[i],
                selected: shell.currentIndex == i,
                onTap: () => _goBranch(i),
              ),
            const Spacer(),
            _SideBarItem(
              label: '会话',
              icon: TIcons.grid_view,
              selectedIcon: TIcons.grid_view_filled,
              selected: false,
              onTap: () => context.push('/sessions'),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  /// 手机底部标签栏：TDesign 图文 TabBar（图标在上、文字在下）。
  ///
  /// 选中态与未选中态都使用**线条图标**，只在品牌色与中性色之间切换；
  /// 采用 [TTabBarItemStyle.normal]：选中项只改变前景色，不绘制胶囊背景；
  /// 容器样式保持 [TTabBarStyle.filled]，不使用悬浮胶囊外观；
  /// 不设置任何 badge，因此没有红点或数字角标；
  /// 背景色、顶部 0.5px 分割线与底部安全区由组件自行绘制。
  Widget _buildBottomBar(BuildContext context) {
    return TTabBar(
      type: TTabBarType.iconText,
      itemStyle: TTabBarItemStyle.normal,
      style: TTabBarStyle.filled,
      useSafeArea: true,
      needInkWell: true,
      value: shell.currentIndex,
      onChanged: _goBranch,
      navigationTabs: [
        for (var i = 0; i < _labels.length; i++)
          TTabBarItemConfig(
            tabText: _labels[i],
            // 选中态用实心图标：TTabBar 的 normal 样式不画胶囊底，
            // 若两态图标相同，选中项只剩颜色差异，在灰底上很难分辨。
            selectedIcon: Icon(_selectedIcons[i]),
            unselectedIcon: Icon(_icons[i]),
            // 重复点击当前 Tab 时回到该分支的根页面。
            notifyOnReselect: true,
            onTap: () {
              if (i == shell.currentIndex) {
                _goBranch(i, initialLocation: true);
              }
            },
          ),
      ],
    );
  }
}

/// 左侧导航项：图标 + 文案，选中态用品牌浅底胶囊。
class _SideBarItem extends StatelessWidget {
  const _SideBarItem({
    required this.label,
    required this.icon,
    required this.selectedIcon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(theme.radiusDefault),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 9),
          decoration: BoxDecoration(
            color: selected ? theme.brandColorLight : Colors.transparent,
            borderRadius: BorderRadius.circular(theme.radiusDefault),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                selected ? selectedIcon : icon,
                size: 22,
                color: selected ? theme.brandColor : theme.textColorSecondary,
              ),
              const SizedBox(height: 3),
              Text(
                label,
                style: TextStyle(
                  fontSize: context.tTheme.fontSizeBodySmall,
                  height: 1.3,
                  fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
                  color: selected ? theme.brandColor : theme.textColorSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// 侧栏内部使用的布局常量。
class AppVertical {
  AppVertical._();

  static const double brandTop = 20;
}
