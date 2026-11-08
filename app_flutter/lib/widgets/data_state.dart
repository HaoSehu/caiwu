import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../core/theme/app_tokens.dart';

/// 统一的加载/错误/空三态容器，对齐 v4-console 的 DataState 语义：
/// 空态必须提供行动路径（例如「暂无服务实例，[前往选购]」）。
class DataState extends StatelessWidget {
  const DataState({
    super.key,
    required this.loading,
    this.error,
    this.empty = false,
    this.emptyText = '暂无数据',
    this.emptyAction,
    this.retry,
    required this.child,
  });

  final bool loading;
  final String? error;
  final bool empty;
  final String emptyText;
  final Widget? emptyAction;
  final VoidCallback? retry;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    if (loading) {
      return Center(
        child: SizedBox(
          width: 28,
          height: 28,
          child: const CircularProgressIndicator(strokeWidth: 2.5),
        ),
      );
    }
    if (error != null && error!.isNotEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.gapXl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                TIcons.error_circle,
                size: 32,
                color: theme.textColorPlaceholder,
              ),
              const SizedBox(height: AppSpacing.gapS),
              Text(
                error!,
                textAlign: TextAlign.center,
                style: AppType.body(theme)
                    .copyWith(color: theme.textColorSecondary),
              ),
              const SizedBox(height: AppSpacing.gapL),
              if (retry != null)
                TButton(
                  // 重试是错误态的主动作，触控高度必须够。
                  size: TButtonSize.medium,
                  variant: TButtonVariant.outline,
                  onPressed: retry,
                  child: const Text('重试'),
                ),
            ],
          ),
        ),
      );
    }
    if (empty) {
      // TDesign 官方空态组件：图标、文案、操作位都由组件排版。
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.gapXl),
          child: TEmpty(
            emptyText: emptyText,
            operation: emptyAction,
          ),
        ),
      );
    }
    return child;
  }
}

/// 通用滚动刷新容器：下拉刷新。
class RefreshList extends StatelessWidget {
  const RefreshList({
    super.key,
    required this.onRefresh,
    required this.child,
  });

  final Future<void> Function() onRefresh;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: child,
    );
  }
}

/// 取出字符串字段的小工具，避免页面里到处 `map['x'] as String?`。
String strOf(Map<String, dynamic>? map, String key) => '${map?[key] ?? ''}';

int intOf(Map<String, dynamic>? map, String key) {
  final value = map?[key];
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('${value ?? ''}') ?? 0;
}

Map<String, dynamic> mapOf(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((k, v) => MapEntry('$k', v));
  return <String, dynamic>{};
}

List<Map<String, dynamic>> listOf(dynamic value) {
  if (value is List) {
    return value.map(mapOf).where((e) => e.isNotEmpty).toList();
  }
  return const [];
}
