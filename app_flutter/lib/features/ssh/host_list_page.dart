import 'dart:async';

import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/util/format.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import 'host_store.dart';
import 'session_registry.dart';

/// SSH 主机列表（独立功能，不依赖 caiwu 服务）。
///
/// 主机配置存本地 [SshHostStore]，密码/私钥单独存 Keystore。路由由主控统一
/// 接入，页面不依赖全局 Provider；从新增/编辑/连接页返回后重新 `load()`，
/// 保证列表与持久层一致。
class SshHostListPage extends StatefulWidget {
  const SshHostListPage({super.key});

  @override
  State<SshHostListPage> createState() => _SshHostListPageState();
}

class _SshHostListPageState extends State<SshHostListPage> {
  final SshHostStore _store = SshHostStore();
  final TextEditingController _searchController = TextEditingController();

  String _keyword = '';
  bool _loadDone = false;

  @override
  void initState() {
    super.initState();
    _store.addListener(_onStoreChanged);
    unawaited(_boot());
  }

  @override
  void dispose() {
    _store.removeListener(_onStoreChanged);
    // load() 尚未结束时不能在 dispose 里销毁 store，否则完成回调 notifyListeners
    // 会命中已销毁的 ChangeNotifier；改由 _boot 在完成后补一次销毁。
    if (_loadDone) _store.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _boot() async {
    await _store.load();
    _loadDone = true;
    if (!mounted) _store.dispose();
  }

  void _onStoreChanged() {
    if (mounted) setState(() {});
  }

  List<SshHost> get _filtered {
    final keyword = _keyword.trim().toLowerCase();
    if (keyword.isEmpty) return _store.hosts;
    return _store.hosts.where((host) {
      return host.displayLabel.toLowerCase().contains(keyword) ||
          host.host.toLowerCase().contains(keyword) ||
          host.username.toLowerCase().contains(keyword);
    }).toList();
  }

  // ── 导航 ─────────────────────────────────────────────────────────────

  Future<void> _openCreate() async {
    await context.push('/ssh/host/new');
    if (!mounted) return;
    unawaited(_store.load());
  }

  Future<void> _openEdit(SshHost host) async {
    await context.push('/ssh/host/${host.id}');
    if (!mounted) return;
    unawaited(_store.load());
  }

  Future<void> _openSession(SshHost host) async {
    await context.push('/ssh/session/${host.id}');
    if (!mounted) return;
    unawaited(_store.load());
  }

  // ── 菜单与操作 ───────────────────────────────────────────────────────

  Future<void> _showMenu(SshHost host) async {
    final theme = context.tTheme;
    final navigator = Navigator.of(context, rootNavigator: true);
    final result = await TDialog.show<String>(
      context,
      dialog: TDialog(
        title: Text(host.displayLabel),
        content: SizedBox(
          width: double.infinity,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _menuRow(
                theme,
                icon: TIcons.edit,
                label: '编辑',
                onTap: () => navigator.pop('edit'),
              ),
              _menuRow(
                theme,
                icon: TIcons.copy,
                label: '复制',
                onTap: () => navigator.pop('copy'),
              ),
              _menuRow(
                theme,
                icon: TIcons.delete,
                label: '删除',
                color: theme.errorColor,
                showDivider: false,
                onTap: () => navigator.pop('delete'),
              ),
            ],
          ),
        ),
        actions: const [TDialogAction(child: Text('取消'))],
      ),
    );
    if (!mounted || result == null) return;
    if (result == 'edit') {
      await _openEdit(host);
    } else if (result == 'copy') {
      await _copyHost(host);
    } else if (result == 'delete') {
      await _deleteHost(host);
    }
  }

  Widget _menuRow(
    TThemeData theme, {
    required IconData icon,
    required String label,
    required VoidCallback onTap,
    Color? color,
    bool showDivider = true,
  }) {
    final useColor = color ?? theme.textColorPrimary;
    return Column(
      children: [
        InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Row(
              children: [
                Icon(icon, size: 18, color: useColor),
                const SizedBox(width: AppSpacing.gapM),
                Expanded(
                  child: Text(
                    label,
                    style: AppType.body(theme).copyWith(color: useColor),
                  ),
                ),
              ],
            ),
          ),
        ),
        if (showDivider)
          Divider(height: 1, thickness: 0.5, color: theme.borderLevel1Color),
      ],
    );
  }

  Future<void> _copyHost(SshHost host) async {
    final newId = DateTime.now().microsecondsSinceEpoch.toString();
    final copied = SshHost(
      id: newId,
      label: '${host.displayLabel} 副本',
      host: host.host,
      port: host.port,
      username: host.username,
      authType: host.authType,
      remark: host.remark,
      jumpHostId: host.jumpHostId,
      keepAliveSeconds: host.keepAliveSeconds,
      createdAt: DateTime.now(),
    );
    final secret = await _store.readSecret(host.id);
    await _store.save(copied);
    if (secret.isNotEmpty) {
      await _store.saveSecret(newId, secret);
    }
    if (!mounted) return;
    AppFeedback.success(context, '已复制服务器');
  }

  Future<void> _deleteHost(SshHost host) async {
    final confirmed = await AppFeedback.confirm(
      context,
      title: '删除服务器',
      content: '将删除「${host.displayLabel}」及其保存的密码/私钥，删除后无法恢复。',
      confirmText: '删除',
      danger: true,
    );
    if (!confirmed || !mounted) return;
    await _store.remove(host.id);
    if (!mounted) return;
    AppFeedback.success(context, '已删除服务器');
  }

  // ── 构建 ─────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return AppScaffold(
      title: '我的服务器',
      actions: [
        // 有打开的会话时，顶栏显示入口与数量。
        ListenableBuilder(
          listenable: SshSessionRegistry.instance,
          builder: (context, _) {
            final count = SshSessionRegistry.instance.count;
            if (count == 0) return const SizedBox.shrink();
            return TextButton(
              onPressed: () => context.push('/sessions'),
              child: Text('会话 $count'),
            );
          },
        ),
        Padding(
          padding: const EdgeInsets.only(right: 8),
          child: TButton(
            size: TButtonSize.small,
            variant: TButtonVariant.fill,
            colorPreset: TButtonColorPreset.primary,
            icon: const Icon(TIcons.add, size: 16),
            onPressed: _openCreate,
            child: const Text('新增'),
          ),
        ),
      ],
      body: Column(
        children: [
          Padding(
            padding: EdgeInsets.fromLTRB(
              context.pagePadding,
              AppSpacing.gapS,
              context.pagePadding,
              0,
            ),
            child: TInput(
              controller: _searchController,
              hintText: '搜索名称/主机/用户名',
              clearButtonMode: TInputClearButtonMode.always,
              onChanged: (value) => setState(() => _keyword = value),
              // 点击穿透：prefix 槽的 Center 会吞掉点击，图标附近点不聚焦。
              prefix: IgnorePointer(
                child: Padding(
                  padding: const EdgeInsets.only(left: 12, right: 8),
                  child: Icon(
                    TIcons.search,
                    size: 18,
                    color: theme.textColorSecondary,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gapM),
          Expanded(child: _buildBody(theme)),
        ],
      ),
    );
  }

  Widget _buildBody(TThemeData theme) {
    if (!_store.loaded) {
      return const Center(
        child: SizedBox(
          width: 28,
          height: 28,
          child: CircularProgressIndicator(strokeWidth: 2.5),
        ),
      );
    }
    if (_store.hosts.isEmpty) return _buildEmpty(theme);

    final hosts = _filtered;
    if (hosts.isEmpty) {
      return Center(
        child: TEmpty(
          emptyText: '没有匹配的服务器',
          operation: TButton(
            size: TButtonSize.small,
            variant: TButtonVariant.outline,
            onPressed: () {
              _searchController.clear();
              setState(() => _keyword = '');
            },
            child: const Text('清除搜索'),
          ),
        ),
      );
    }

    return RefreshIndicator(
      color: theme.brandColor,
      onRefresh: _store.load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(
          context.pagePadding,
          0,
          context.pagePadding,
          AppSpacing.pageBottom,
        ),
        children: [
          if (context.isTablet)
            _buildTwoColumnGroups(theme, hosts)
          else
            _buildHostGroup(theme, hosts),
        ],
      ),
    );
  }

  /// 平板双栏：按奇偶拆分，保证列表刷新/重新加载后点位稳定不漂移。
  Widget _buildTwoColumnGroups(TThemeData theme, List<SshHost> hosts) {
    final left = <SshHost>[];
    final right = <SshHost>[];
    for (var i = 0; i < hosts.length; i++) {
      (i.isEven ? left : right).add(hosts[i]);
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: _buildHostGroup(theme, left)),
        const SizedBox(width: AppSpacing.gapL),
        Expanded(child: _buildHostGroup(theme, right)),
      ],
    );
  }

  Widget _buildEmpty(TThemeData theme) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(
          horizontal: 32,
          vertical: AppSpacing.gapXl,
        ),
        child: TEmpty(
          icon: TIcons.server,
          emptyText: '还没有服务器',
          operation: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '也可以从服务详情页的 SSH 入口连接',
                textAlign: TextAlign.center,
                style: AppType.secondary(theme),
              ),
              const SizedBox(height: AppSpacing.gapL),
              TButton(
                size: TButtonSize.large,
                variant: TButtonVariant.fill,
                colorPreset: TButtonColorPreset.primary,
                icon: const Icon(TIcons.add, size: 18),
                onPressed: _openCreate,
                child: const Text('添加服务器'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// 主机列表：TDesign 官方单元格组，整组包在 9 圆角白底卡片里。
  Widget _buildHostGroup(TThemeData theme, List<SshHost> hosts) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: theme.borderLevel1Color),
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: theme.bgColorContainer,
        child: TCellGroup(
          cells: [for (final host in hosts) _buildHostCell(theme, host)],
        ),
      ),
    );
  }

  TCell _buildHostCell(TThemeData theme, SshHost host) {
    final hasJump = (host.jumpHostId ?? '').isNotEmpty;
    return TCell(
      prefix: Icon(TIcons.server, size: 22, color: theme.brandColor),
      title: Row(
        children: [
          Flexible(child: Text(host.displayLabel)),
          if (hasJump) ...[
            const SizedBox(width: AppSpacing.gapS),
            const AppStatusTag(label: '经跳板机', preset: TTagColorPreset.primary),
          ],
        ],
      ),
      subtitle: Text(
        '${host.username}@${host.host}:${host.port}',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      note: Text(
        '上次连接 ${_relativeTime(host.lastConnectedAt)}',
        style: AppType.caption(theme),
      ),
      trailing: TButton(
        size: TButtonSize.small,
        variant: TButtonVariant.fill,
        colorPreset: TButtonColorPreset.primary,
        onPressed: () => _openSession(host),
        child: const Text('连接'),
      ),
      arrow: true,
      align: TCellAlign.center,
      onTap: () => _openEdit(host),
      // 列表不再放「更多」按钮，长按提供编辑/复制/删除完整菜单。
      onLongPress: () => unawaited(_showMenu(host)),
    );
  }

  /// 相对时间：刚刚 / N 分钟前 / N 小时前 / N 天前；超过一周回落绝对时间。
  String _relativeTime(DateTime? time) {
    if (time == null) return '从未连接';
    final diff = DateTime.now().difference(time);
    if (diff.isNegative || diff.inMinutes < 1) return '刚刚';
    if (diff.inMinutes < 60) return '${diff.inMinutes} 分钟前';
    if (diff.inHours < 24) return '${diff.inHours} 小时前';
    if (diff.inDays < 7) return '${diff.inDays} 天前';
    return formatDateTime(time);
  }
}
