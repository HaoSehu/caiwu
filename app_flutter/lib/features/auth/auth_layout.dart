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
    return Scaffold(
      backgroundColor: theme.bgColorPage,
      body: SafeArea(
        child: GestureDetector(
          behavior: HitTestBehavior.translucent,
          onTap: () => FocusScope.of(context).unfocus(),
          child: context.isExpanded
              ? _buildExpanded(context)
              : context.isTablet
              ? _buildCentered(context)
              : _buildCompact(context),
        ),
      ),
    );
  }

  /// 手机：保持原有「上下堆叠 + 整页滚动」版式。
  Widget _buildCompact(BuildContext context) {
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
            padding: const EdgeInsets.only(top: 56, bottom: 32),
            child: brand,
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
                  Flexible(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(
                        maxWidth: _expandedBrandMaxWidth,
                      ),
                      child: brand,
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
