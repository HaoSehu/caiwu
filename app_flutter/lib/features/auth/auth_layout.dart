import 'dart:math' as math;

import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/theme/app_tokens.dart';

/// 认证三页共用的响应式版式：品牌头 + 表单卡片 + 底部链接。
///
/// - 手机（compact）：沿用原版式，品牌头在上、表单撑满内容宽度。
/// - 平板（medium）：整组限宽 420 并垂直居中，不贴顶、不被拉宽。
/// - 横屏（expanded）：品牌头在左、表单在右，横向居中。
///
/// 只负责布局，表单内部逻辑仍由各页面自己维护。
class AuthLayout extends StatelessWidget {
  const AuthLayout({
    super.key,
    required this.brand,
    required this.form,
    required this.footer,
  });

  /// 品牌头原始内容（图标 + 标题 + 副标题），不含外边距。
  final Widget brand;

  /// 表单卡片（页面传入 [AppCard]）。
  final Widget form;

  /// 卡片下方链接区。
  final Widget footer;

  static const double _formWidth = 420;
  static const double _expandedBrandMaxWidth = 400;
  static const double _expandedGap = 72;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    // 键盘弹出时收起品牌头：手机竖屏品牌区（图标 + 标题 + 副标题 + 上下留白）
    // 约占 180 高，加上键盘后表单会被挤出可视区，只剩 Logo 占满首屏。
    //
    // 阈值取 80 而不是 0：部分 ROM 在键盘弹出瞬间会先报一个很小的 inset，
    // 用 0 作判据会让品牌区闪一下再消失。
    final keyboardOpen = MediaQuery.viewInsetsOf(context).bottom > 80;
    return Scaffold(
      backgroundColor: theme.bgColorPage,
      body: SafeArea(
        child: GestureDetector(
          behavior: HitTestBehavior.translucent,
          onTap: () => FocusManager.instance.primaryFocus?.unfocus(),
          child: context.isExpanded
              ? _buildExpanded(context)
              : context.isTablet
              ? _buildCentered(context)
              : _buildCompact(context, keyboardOpen: keyboardOpen),
        ),
      ),
    );
  }

  /// 手机：保持原有「上下堆叠 + 整页滚动」版式。
  ///
  /// 键盘弹出时整块品牌区收起，把可视高度全让给表单
  /// （只留图标仍要占约 90 高，在 5 寸机上加键盘后表单仍会被挤出去）。
  Widget _buildCompact(BuildContext context, {required bool keyboardOpen}) {
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(
        context.pagePadding,
        0,
        context.pagePadding,
        AppSpacing.pageBottom,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: EdgeInsets.only(
              top: keyboardOpen ? AppSpacing.gapL : 56,
              bottom: keyboardOpen ? AppSpacing.gapM : 32,
            ),
            child: keyboardOpen
                ? const SizedBox.shrink()
                : brand,
          ),
          form,
          const SizedBox(height: AppSpacing.gapL),
          footer,
        ],
      ),
    );
  }

  /// 平板竖屏：表单限宽 420，整组在可用高度内垂直居中。
  Widget _buildCentered(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          padding: EdgeInsets.symmetric(
            horizontal: context.pagePadding,
            vertical: AppSpacing.gapXl,
          ),
          child: ConstrainedBox(
            constraints: BoxConstraints(
              minHeight: math.max(
                0,
                constraints.maxHeight - AppSpacing.gapXl * 2,
              ),
            ),
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: _formWidth),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Center(
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 32),
                        child: brand,
                      ),
                    ),
                    form,
                    const SizedBox(height: AppSpacing.gapL),
                    footer,
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  /// 平板横屏：品牌头在左、表单在右；高度不足时整体可滚动。
  Widget _buildExpanded(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          padding: EdgeInsets.symmetric(
            horizontal: context.pagePadding,
            vertical: AppSpacing.gapXl,
          ),
          child: ConstrainedBox(
            constraints: BoxConstraints(
              minHeight: math.max(
                0,
                constraints.maxHeight - AppSpacing.gapXl * 2,
              ),
            ),
            child: Center(
              child: Row(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  // 横屏平板：品牌叙事左对齐（与右侧表单左缘呼应），
                  // 居中的「Logo + 两行字」在大屏上会显得松散无力。
                  Flexible(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(
                        maxWidth: _expandedBrandMaxWidth,
                      ),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: brand,
                      ),
                    ),
                  ),
                  const SizedBox(width: _expandedGap),
                  SizedBox(
                    width: _formWidth,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        form,
                        const SizedBox(height: AppSpacing.gapL),
                        footer,
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
