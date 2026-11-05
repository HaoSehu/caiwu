import 'dart:async';

import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/theme/app_tokens.dart';
import '../../core/util/format.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import '../../widgets/data_state.dart';
import 'session_registry.dart';

/// 「打开的会话」页：列出当前进程内已建立的 SSH 会话，支持切换与关闭。
///
/// 只读取 [SshSessionRegistry] 的注册信息，不持有任何连接对象；
/// 会话的建立/销毁由终端侧负责注册与注销，这里监听注册表自动刷新。
class SshSessionManagerPage extends StatefulWidget {
  const SshSessionManagerPage({super.key});

  @override
  State<SshSessionManagerPage> createState() => _SshSessionManagerPageState();
}

class _SshSessionManagerPageState extends State<SshSessionManagerPage> {
  final SshSessionRegistry _registry = SshSessionRegistry.instance;

  /// 定时刷新「已连接时长」，30 秒一次即可，避免每分钟整页重建。
  Timer? _ticker;

  @override
  void initState() {
    super.initState();
    _ticker = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  // ── 操作 ────────────────────────────────────────────────────────────

  void _switchTo(SshSessionInfo info) {
    _registry.markActive(info.id);
    context.go(info.route);
  }

  void _close(SshSessionInfo info) {
    _registry.unregister(info.id);
    AppFeedback.toast(context, '已关闭「${info.title}」');
  }

  Future<void> _disconnectAll() async {
    final sessions = _registry.sessions;
    if (sessions.isEmpty) return;
    final confirmed = await AppFeedback.confirm(
      context,
      title: '全部断开',
      content: '将关闭当前全部 ${sessions.length} 个会话，确定继续吗',
      confirmText: '全部断开',
      danger: true,
    );
    if (!confirmed || !mounted) return;
    for (final session in sessions) {
      _registry.unregister(session.id);
    }
    AppFeedback.success(context, '已断开全部会话');
  }

  // ── 构建 ────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return AppScaffold(
      title: '打开的会话',
      showBack: true,
      body: ListenableBuilder(
        listenable: _registry,
        builder: (context, _) {
          final sessions = _registry.sessions;
          return Column(
            children: [
              _buildSummary(theme, sessions.length),
              Expanded(
                child: sessions.isEmpty
                    ? _buildEmpty(theme)
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(
                          AppSpacing.page,
                          AppSpacing.gapS,
                          AppSpacing.page,
                          AppSpacing.pageBottom,
                        ),
                        itemCount: sessions.length,
                        itemBuilder: (context, index) =>
                            _buildSessionCard(theme, sessions[index]),
                        separatorBuilder: (context, index) =>
                            const SizedBox(height: AppSpacing.gapM),
                      ),
              ),
            ],
          );
        },
      ),
    );
  }

  /// 顶部统计行：「共 n 个会话」+ 右侧「全部断开」。
  Widget _buildSummary(TThemeData theme, int count) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.page,
        AppSpacing.gapS,
        AppSpacing.page,
        AppSpacing.gapS,
      ),
      child: Row(
        children: [
          Expanded(
            child: Text('共 $count 个会话', style: AppType.secondary(theme)),
          ),
          if (count > 0)
            TButton(
              size: TButtonSize.small,
              variant: TButtonVariant.text,
              colorPreset: TButtonColorPreset.danger,
              onPressed: _disconnectAll,
              child: const Text('全部断开'),
            ),
        ],
      ),
    );
  }

  Widget _buildEmpty(TThemeData theme) {
    return DataState(
      loading: false,
      empty: true,
      emptyText: '当前没有打开的会话',
      emptyAction: TButton(
        size: TButtonSize.small,
        variant: TButtonVariant.outline,
        colorPreset: TButtonColorPreset.primary,
        onPressed: () => context.go('/ssh'),
        child: const Text('去服务器列表'),
      ),
      child: const SizedBox.shrink(),
    );
  }

  Widget _buildSessionCard(TThemeData theme, SshSessionInfo info) {
    final active = _registry.activeId == info.id;
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              AppDot(
                color: active ? theme.brandColor : theme.borderLevel2Color,
              ),
              const SizedBox(width: AppSpacing.gapM),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            info.title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppType.cardTitle(theme),
                          ),
                        ),
                        if (active) ...[
                          const SizedBox(width: AppSpacing.gapS),
                          Text(
                            '当前',
                            style: AppType.caption(theme).copyWith(
                              color: theme.brandColor,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      info.subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppType.secondary(theme),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.gapS),
              TButton(
                size: TButtonSize.small,
                variant: TButtonVariant.outline,
                colorPreset: TButtonColorPreset.primary,
                child: const Text('切换'),
                onPressed: () => _switchTo(info),
              ),
              const SizedBox(width: AppSpacing.gapXs),
              Semantics(
                button: true,
                label: '关闭 ${info.title}',
                child: InkResponse(
                  onTap: () => _close(info),
                  radius: 20,
                  child: SizedBox(
                    width: 40,
                    height: 40,
                    child: Icon(
                      TIcons.close,
                      size: 20,
                      color: theme.textColorSecondary,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.gapS),
          Divider(height: 1, thickness: 0.5, color: theme.borderLevel1Color),
          const SizedBox(height: AppSpacing.gapS),
          Row(
            children: [
              Expanded(
                child: Text(
                  '开始于 ${formatDateTime(info.startedAt)}',
                  style: AppType.caption(theme),
                ),
              ),
              Text(
                _connectedDuration(info.startedAt),
                style: AppType.caption(theme),
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// 已连接时长：不足 1 分钟显示「刚刚连接」，之后按时/分/天递进。
  String _connectedDuration(DateTime startedAt) {
    final duration = DateTime.now().difference(startedAt);
    if (duration.isNegative || duration.inMinutes < 1) return '刚刚连接';
    if (duration.inHours < 1) return '已连接 ${duration.inMinutes} 分钟';
    if (duration.inDays < 1) {
      final hours = duration.inHours;
      final minutes = duration.inMinutes % 60;
      return minutes == 0 ? '已连接 $hours 小时' : '已连接 $hours 小时 $minutes 分钟';
    }
    final hours = duration.inHours % 24;
    return hours == 0
        ? '已连接 ${duration.inDays} 天'
        : '已连接 ${duration.inDays} 天 $hours 小时';
  }
}
