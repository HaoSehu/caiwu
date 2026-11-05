import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/net/app_services.dart';
import '../../core/store/session.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/util/format.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import '../../widgets/data_state.dart';
import '../ssh/session_registry.dart';

/// 后端 `status_tone` → TTag 预设色的导出映射（其它页面可能引用）。
///
/// 统一委托给 [AppStatusTag.presetOf]，保证全端状态配色口径一致。
TTagColorPreset resolveTagPreset(String tone) => AppStatusTag.presetOf(tone);

/// 首页（底部 Tab «控制台»）。
///
/// 布局对齐阿里云 App：顶部问候 → 工具宫格（SSH 工具在最左）→ 概览页签 →
/// 待处理事件 → 费用监控 → 我的服务 → 公告。
class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  static const int _statusDeleted = 3;

  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _groups = const [];
  List<Map<String, dynamic>> _notices = const [];
  int _unread = 0;

  int _serviceTotal = 0;
  int _ticketOngoing = 0;
  int _invoiceUnpaid = 0;

  double? _balanceDelta;
  bool _refreshingBalance = false;

  /// 概览页签：0 概览 / 1 运维监控 / 2 费用成本 / 3 我的订阅。
  int _tabIndex = 0;

  int _seq = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _refresh();
    });
  }

  Future<void> _refresh() async {
    await Future.wait<void>([
      _loadOverview(),
      _loadUnread(),
      _loadQuickStats(),
      _loadBalanceHint(),
    ]);
  }

  Future<void> _loadOverview() async {
    final seq = ++_seq;
    final api = AppServices.of(context);
    try {
      final results = await Future.wait<Map<String, dynamic>>([
        api.services.groupedOverview(),
        api.content.notices(pageSize: 3),
      ]);
      if (!mounted || seq != _seq) return;
      // 首页只展示未删除的服务：组内 items 过滤 status=3，
      // 过滤后为空的分组整体不展示（与「服务」页默认视图口径一致）。
      final groups = <Map<String, dynamic>>[];
      for (final group in listOf(results[0]['list'])) {
        final items = listOf(group['items'])
            .where((item) => intOf(item, 'status') != _statusDeleted)
            .toList();
        if (items.isEmpty) continue;
        groups.add({...group, 'items': items});
        if (groups.length >= 3) break;
      }
      final notices = listOf(results[1]['list']).take(3).toList();
      setState(() {
        _groups = groups;
        _notices = notices;
        _error = null;
        _loading = false;
      });
    } catch (error) {
      if (!mounted || seq != _seq) return;
      final message = AppServices.errorText(error);
      final hasData = _groups.isNotEmpty || _notices.isNotEmpty;
      setState(() {
        _loading = false;
        if (!hasData) _error = message;
      });
      if (hasData) AppFeedback.toast(context, message);
    }
  }

  Future<void> _loadUnread() async {
    final content = AppServices.of(context).content;
    final count = await _safe(content.notificationUnreadCount);
    if (!mounted) return;
    setState(() => _unread = count ?? 0);
  }

  Future<void> _loadQuickStats() async {
    final api = AppServices.of(context);
    final results = await Future.wait<Object?>([
      _safe(() => api.services.list(pageSize: 1)),
      _safe(() => api.services.list(pageSize: 1, status: _statusDeleted)),
      _safe(() => api.tickets.list(pageSize: 1, status: 'ongoing')),
      _safe(api.finance.invoiceSummary),
    ]);
    if (!mounted) return;
    Map<String, dynamic> asMap(Object? value) =>
        value is Map<String, dynamic> ? value : const <String, dynamic>{};
    final visible =
        intOf(asMap(results[0]), 'total') - intOf(asMap(results[1]), 'total');
    setState(() {
      _serviceTotal = visible < 0 ? 0 : visible;
      _ticketOngoing = intOf(asMap(results[2]), 'total');
      _invoiceUnpaid = intOf(asMap(results[3]), 'unpaid');
    });
  }

  Future<T?> _safe<T>(Future<T> Function() fetch) async {
    try {
      return await fetch();
    } catch (_) {
      return null;
    }
  }

  Future<void> _loadBalanceHint() async {
    final api = AppServices.of(context);
    final session = context.read<Session>();
    final result = await _safe(
      () => api.finance.balanceLogs(pageSize: 1, eventType: 'recharge'),
    );
    if (!mounted) return;
    final logs =
        result == null ? const <Map<String, dynamic>>[] : listOf(result['list']);
    if (logs.isEmpty) {
      if (_balanceDelta != null) setState(() => _balanceDelta = null);
      return;
    }
    final entry = logs.first;
    final after = double.tryParse(strOf(entry, 'balance_after'));
    final change = double.tryParse(strOf(entry, 'change_amount'));
    final current = double.tryParse(session.balance);
    if (after == null || change == null || current == null) return;
    final delta = current - (after - change);
    setState(() => _balanceDelta = delta.abs() < 0.005 ? null : delta);
  }

  Future<void> _refreshBalance() async {
    if (_refreshingBalance) return;
    setState(() => _refreshingBalance = true);
    final services = AppServices.of(context);
    final session = context.read<Session>();
    try {
      final info = await services.auth.info();
      final user = info['user'] is Map ? mapOf(info['user']) : info;
      if (user.isNotEmpty) await session.updateUser(user);
      if (!mounted) return;
      AppFeedback.toast(context, '余额已更新');
      await _loadBalanceHint();
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _refreshingBalance = false);
    }
  }

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 6) return '凌晨好';
    if (hour < 12) return '早上好';
    if (hour < 14) return '中午好';
    if (hour < 18) return '下午好';
    return '晚上好';
  }

  // ── 构建 ────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    final session = context.watch<Session>();
    // 平板区块间距 32，手机 24。
    final sectionGap = context.isTablet ? 32.0 : AppSpacing.gapXl;
    return AppScaffold(
      showBack: false,
      body: Column(
        children: [
          _buildHeader(theme, session),
          Expanded(
            child: RefreshIndicator(
              color: theme.brandColor,
              onRefresh: _refresh,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                // 通栏列表（TCellGroup）直接铺在页面底色上，只有内容区块单独留边。
                padding: const EdgeInsets.only(
                  top: AppSpacing.gapS,
                  bottom: AppSpacing.pageBottom,
                ),
                children: [
                  _padded(_buildToolGrid(theme)),
                  const SizedBox(height: AppSpacing.gapS),
                  _buildTabs(theme),
                  const SizedBox(height: AppSpacing.gapL),
                  if (_tabIndex == 0) ...[
                    _buildPendingEvents(theme),
                    SizedBox(height: sectionGap),
                    _buildCostMonitor(theme, session),
                    SizedBox(height: sectionGap),
                    DataState(
                      loading: _loading,
                      error: _error,
                      retry: _refresh,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _buildServicesSection(theme),
                          SizedBox(height: sectionGap),
                          _buildNoticesSection(theme),
                        ],
                      ),
                    ),
                  ] else if (_tabIndex == 1)
                    _buildOpsTab(theme)
                  else if (_tabIndex == 2)
                    _buildCostTab(theme, session)
                  else
                    _buildSubscriptionTab(theme),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// 内容区块的左右留白：通栏 Cell 组保持贴边，其余内容统一套这一层。
  Widget _padded(Widget child) {
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: context.pagePadding),
      child: child,
    );
  }

  /// 区块标题：抵消 [AppSectionHeader] 自带的 4px 内缩，使文字与 Cell 内容左缘对齐。
  Widget _sectionHeader(Widget header) {
    return Padding(
      padding: EdgeInsets.symmetric(
        horizontal: context.pagePadding - AppSpacing.gapXs,
      ),
      child: header,
    );
  }

  Widget _buildHeader(TThemeData theme, Session session) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        context.pagePadding,
        AppSpacing.gapM,
        4,
        AppSpacing.gapS,
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('控制台', style: AppType.pageTitle(theme).copyWith(fontSize: 22)),
                const SizedBox(height: 2),
                Text(
                  '${_greeting()}，${session.displayName}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppType.caption(theme),
                ),
              ],
            ),
          ),
          TBadge(
            label: _unread > 99 ? '99+' : '$_unread',
            showZero: false,
            offset: const Offset(4, -4),
            child: IconButton(
              tooltip: '消息',
              onPressed: () => context.push('/notices'),
              icon: Icon(TIcons.mail, size: 24, color: theme.textColorPrimary),
            ),
          ),
          IconButton(
            tooltip: '打开的会话',
            onPressed: () => context.push('/sessions'),
            icon: Icon(TIcons.grid_view, size: 24, color: theme.textColorPrimary),
          ),
        ],
      ),
    );
  }

  /// 工具宫格：固定 5 列，手机 2 行、平板 2 行。
  ///
  /// 参考阿里云/微信小程序宫格：只有 `TIcon` 图标 + 12px 文案，没有色块底。
  /// 工具共 10 个，用 5 列正好排满两行，任何宽度下都不会出现末行残缺
  /// （此前按断点算列数，平板 6 列/8 列会得到 6+4、8+2 的行尾空洞）。
  Widget _buildToolGrid(TThemeData theme) {
    final tools = <_ToolItem>[
      _ToolItem('SSH 工具', TIcons.terminal, _openSshTool),
      _ToolItem('工单', TIcons.ticket, () => context.go('/tickets')),
      _ToolItem('账单', TIcons.bill, () => context.push('/finance')),
      _ToolItem('充值', TIcons.wallet, () => context.push('/finance/recharge')),
      _ToolItem('全部工具', TIcons.app, _showAllTools),
      _ToolItem('云服务器', TIcons.cloud, () => context.go('/services')),
      _ToolItem('我的服务', TIcons.server, () => context.go('/services')),
      _ToolItem(
        '进行中工单',
        TIcons.notification,
        () => context.go('/tickets'),
        badge: _ticketOngoing > 0 ? '$_ticketOngoing' : null,
      ),
      _ToolItem('我的账号', TIcons.user, () => context.go('/profile')),
      _ToolItem('更多', TIcons.more, _showAllTools),
    ];
    final tablet = context.isTablet;
    const columns = 5;
    final spacing = tablet ? AppSpacing.gapL : 0.0;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapM),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final tileWidth =
              (constraints.maxWidth - spacing * (columns - 1)) / columns;
          return Wrap(
            spacing: spacing,
            runSpacing: tablet ? AppSpacing.gapL : AppSpacing.gapXl,
            children: [
              for (final item in tools)
                SizedBox(
                  width: tileWidth,
                  child: _buildToolTile(theme, item),
                ),
            ],
          );
        },
      ),
    );
  }

  void _openSshTool() {
    final hostCount = AppServices.of(context).sshHosts.hosts.length;
    if (hostCount == 0) {
      _showSshIntro();
      return;
    }
    context.push('/ssh');
  }

  Future<void> _showSshIntro() async {
    final theme = context.tTheme;
    final addNew = await TDialog.show<bool>(
      context,
      barrierDismissible: true,
      dialog: TDialog(
        title: const Text('SSH 工具'),
        content: Text(
          '添加自己的服务器，可在手机上直接打开 SSH 终端，支持密码或私钥登录、跳板机与多会话。',
          style: AppType.secondary(theme),
        ),
        actions: const [
          TDialogAction(child: Text('查看已有服务器'), result: false),
          TDialogAction(
            child: Text('添加服务器'),
            result: true,
            role: TDialogActionRole.primary,
          ),
        ],
      ),
    );
    if (!mounted || addNew == null) return;
    await context.push(addNew ? '/ssh/host/new' : '/ssh');
  }

  /// 全部工具：使用 TDesign 官方宫格动作面板（4 列 2 行）。
  void _showAllTools() {
    final tools = <_ToolItem>[
      _ToolItem('SSH 工具', TIcons.terminal, _openSshTool),
      _ToolItem('云服务器', TIcons.cloud, () => context.go('/services')),
      _ToolItem('工单', TIcons.ticket, () => context.go('/tickets')),
      _ToolItem('账单与订单', TIcons.bill, () => context.push('/finance')),
      _ToolItem('充值', TIcons.wallet, () => context.push('/finance/recharge')),
      _ToolItem('消息公告', TIcons.notification, () => context.push('/notices')),
      _ToolItem('打开的会话', TIcons.grid_view, () => context.push('/sessions')),
      _ToolItem('我的账号', TIcons.user, () => context.go('/profile')),
    ];
    _ToolItem? selected;
    TActionSheet.showGrid<_ToolItem>(
      context,
      items: [
        for (final tool in tools)
          TActionSheetItem<_ToolItem>(
            value: tool,
            label: tool.label,
            icon: TIcon(tool.icon, size: 24),
          ),
      ],
      cancelText: '取消',
      // 面板关闭后再导航，避免打断 TActionSheet 自身的退场动画。
      onSelected: (item) => selected = item.value,
      onClosed: () {
        if (!mounted) return;
        selected?.onTap();
      },
    );
  }

  Widget _buildToolTile(TThemeData theme, _ToolItem item) {
    final badge = item.badge;
    final tablet = context.isTablet;
    final icon = TIcon(
      item.icon,
      size: tablet ? 28 : 25,
      color: theme.brandColor,
    );
    return InkWell(
      borderRadius: BorderRadius.circular(theme.radiusLarge),
      onTap: item.onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Column(
          children: [
            badge == null
                ? icon
                : TBadge(
                    label: badge,
                    showZero: false,
                    offset: const Offset(10, -6),
                    child: icon,
                  ),
            const SizedBox(height: 8),
            Text(
              item.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppType.caption(theme).copyWith(
                color: theme.textColorPrimary,
                fontSize: tablet ? 13 : null,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTabs(TThemeData theme) {
    return AppSegmentedTabs(
      labels: const ['概览', '运维监控', '费用成本', '我的订阅'],
      current: _tabIndex,
      onChanged: (index) => setState(() => _tabIndex = index),
    );
  }

  /// 待处理事件：一张卡片内等宽 `TCell`，数字用 `AppType.stat`。
  ///
  /// 手机横滑（每张固定宽度，不挤压数字）；平板一行铺满、每张等宽。
  Widget _buildPendingEvents(TThemeData theme) {
    final items = <_EventItem>[
      _EventItem(
        label: '我的服务',
        value: '$_serviceTotal',
        hint: '在用实例',
        onTap: () => context.go('/services'),
      ),
      _EventItem(
        label: '待支付订单',
        value: '$_invoiceUnpaid',
        hint: _invoiceUnpaid > 0 ? '点击去支付' : '暂无待付',
        onTap: () => context.push('/finance'),
      ),
      _EventItem(
        label: '进行中工单',
        value: '$_ticketOngoing',
        hint: _ticketOngoing > 0 ? '等待回复' : '暂无进行中',
        onTap: () => context.go('/tickets'),
      ),
      _EventItem(
        label: '未读消息',
        value: '$_unread',
        hint: _unread > 0 ? '点击查看' : '全部已读',
        onTap: () => context.push('/notices'),
      ),
    ];
    final tablet = context.isTablet;

    Widget cellOf(_EventItem item) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(theme.radiusLarge),
        child: TCell(
          title: Text(
            item.value,
            style: AppType.stat(
              theme,
              size: 24,
              color: item.value == '0'
                  ? theme.textColorPrimary
                  : theme.warningColor,
            ),
          ),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Text(
              '${item.label}\n${item.hint}',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: AppType.caption(theme),
            ),
          ),
          onTap: item.onTap,
        ),
      );
    }

    Widget content;
    if (tablet) {
      content = LayoutBuilder(
        builder: (context, constraints) {
          final width =
              (constraints.maxWidth - AppSpacing.gapM * (items.length - 1)) /
                  items.length;
          return Wrap(
            spacing: AppSpacing.gapM,
            runSpacing: AppSpacing.gapM,
            children: [
              for (final item in items)
                SizedBox(width: width, child: cellOf(item)),
            ],
          );
        },
      );
    } else {
      content = SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        physics: const BouncingScrollPhysics(),
        child: Row(
          children: [
            for (final item in items) ...[
              SizedBox(width: 132, child: cellOf(item)),
              const SizedBox(width: AppSpacing.gapS),
            ],
          ],
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          AppSectionHeader(
            title: '待处理事件',
            actionText: '全部',
            onAction: () => context.push('/finance'),
          ),
        ),
        // 四张等宽卡片并排：手机横滑、平板一行铺满。
        _padded(content),
      ],
    );
  }

  /// 费用监控：一张卡片内「余额大字 + TCell 明细」。
  ///
  /// 手机竖排；平板时余额与明细两列并排（中间竖分隔线）。
  Widget _buildCostMonitor(TThemeData theme, Session session) {
    final delta = _balanceDelta;
    final tablet = context.isTablet;

    final balanceBlock = Padding(
      padding: EdgeInsets.fromLTRB(
        AppSpacing.cellHorizontal,
        AppSpacing.gapM,
        AppSpacing.cellHorizontal,
        tablet ? AppSpacing.gapM : 0,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment:
            tablet ? MainAxisAlignment.center : MainAxisAlignment.start,
        children: [
          Text('账户余额（元）', style: AppType.caption(theme)),
          const SizedBox(height: AppSpacing.gapXs),
          Text(
            formatMoney(session.balance),
            style: AppType.stat(theme, size: 30),
          ),
          if (delta != null) ...[
            const SizedBox(height: AppSpacing.gapXs),
            Text(
              '${delta > 0 ? '+' : '-'}${formatMoneyText(delta.abs())} 较上次充值前',
              style: AppType.caption(theme).copyWith(
                color: delta > 0 ? theme.successColor : theme.errorColor,
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.gapS),
        ],
      ),
    );

    final detailBlock = Theme(
      data: Theme.of(context).mergeExtension(
        const TCellThemeData(backgroundColor: Colors.transparent),
      ),
      child: TCellGroup(
        cells: [
          TCell(
            title: const Text('待支付'),
            note: Text('$_invoiceUnpaid 张'),
            arrow: true,
            onTap: () => context.push('/finance'),
          ),
          TCell(
            title: const Text('进行中工单'),
            note: Text('$_ticketOngoing 个'),
            arrow: true,
            onTap: () => context.go('/tickets'),
          ),
          TCell(
            title: const Text('我的服务'),
            note: Text('$_serviceTotal 台'),
            arrow: true,
            onTap: () => context.go('/services'),
          ),
        ],
      ),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          AppSectionHeader(
            title: '费用监控',
            actionText: '前往充值',
            onAction: () => context.push('/finance/recharge'),
            trailing: IconButton(
              tooltip: '刷新余额',
              onPressed: _refreshingBalance ? null : _refreshBalance,
              icon: _refreshingBalance
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Icon(TIcons.refresh, size: 18, color: theme.textColorSecondary),
            ),
          ),
        ),
        _padded(
          AppCard(
            padding: EdgeInsets.zero,
            // 整卡保留「点击进入账单」的旧行为；内部 Cell 行各自覆盖自己的跳转。
            onTap: () => context.push('/finance'),
            child: tablet
                // 平板两列并排：右列自带左边框作为分隔线，高度随内容自然撑开，
                // 不引入 IntrinsicHeight（TCell 内部使用 LayoutBuilder，无法查询固有尺寸）。
                ? Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Expanded(flex: 4, child: balanceBlock),
                      Expanded(
                        flex: 5,
                        child: Container(
                          decoration: BoxDecoration(
                            border: Border(
                              left: BorderSide(
                                color: theme.borderLevel1Color,
                                width: 0.5,
                              ),
                            ),
                          ),
                          padding: const EdgeInsets.only(left: AppSpacing.gapL),
                          child: detailBlock,
                        ),
                      ),
                    ],
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      balanceBlock,
                      // 余额与明细之间用 TDesign 分割线，外边距归零以贴合卡片内边距。
                      Theme(
                        data: Theme.of(context).mergeExtension(
                          const TDividerThemeData(
                            margin: EdgeInsets.only(bottom: AppSpacing.gapXs),
                            indent: AppSpacing.cellHorizontal,
                            endIndent: AppSpacing.cellHorizontal,
                          ),
                        ),
                        child: const TDivider(),
                      ),
                      detailBlock,
                    ],
                  ),
          ),
        ),
      ],
    );
  }

  Widget _buildOpsTab(TThemeData theme) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(AppSectionHeader(title: '运行概况')),
        TCellGroup(
          cells: [
            _buildOpsRow('在用服务', '$_serviceTotal 台', TIcons.server,
                () => context.go('/services')),
            _buildOpsRow('进行中工单', '$_ticketOngoing 个', TIcons.ticket,
                () => context.go('/tickets')),
            _buildOpsRow(
              '打开的 SSH 会话',
              '${SshSessionRegistry.instance.count} 个',
              TIcons.terminal,
              () => context.push('/sessions'),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.gapXl),
        _sectionHeader(AppSectionHeader(title: '服务状态')),
        if (_groups.isEmpty)
          _buildEmptyState('暂无服务', '前往服务列表', () => context.go('/services'))
        else
          for (final group in _groups) ...[
            _buildGroupCard(theme, group),
            const SizedBox(height: AppSpacing.gapL),
          ],
      ],
    );
  }

  TCell _buildOpsRow(
    String label,
    String value,
    IconData icon,
    VoidCallback onTap,
  ) {
    return TCell(
      prefix: TIcon(icon, size: 20, color: context.tTheme.brandColor),
      title: Text(label),
      note: Text(value),
      arrow: true,
      onTap: onTap,
    );
  }

  Widget _buildCostTab(TThemeData theme, Session session) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(AppSectionHeader(title: '账户')),
        TCellGroup(
          cells: [
            _buildOpsRow('账户余额', formatMoneyText(session.balance), TIcons.wallet,
                () => context.push('/finance/recharge')),
            _buildOpsRow('待支付账单', '$_invoiceUnpaid 张', TIcons.bill,
                () => context.push('/finance')),
            _buildOpsRow('充值', '去充值', TIcons.money,
                () => context.push('/finance/recharge')),
          ],
        ),
        const SizedBox(height: AppSpacing.gapXl),
        _sectionHeader(AppSectionHeader(title: '快捷操作')),
        _padded(
          Row(
            children: [
              Expanded(
                child: TButton(
                  size: TButtonSize.large,
                  colorPreset: TButtonColorPreset.primary,
                  onPressed: () => context.push('/finance/recharge'),
                  child: const Text('立即充值'),
                ),
              ),
              const SizedBox(width: AppSpacing.gapM),
              Expanded(
                child: TButton(
                  size: TButtonSize.large,
                  variant: TButtonVariant.outline,
                  onPressed: () => context.push('/finance'),
                  child: const Text('查看账单'),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildSubscriptionTab(TThemeData theme) {
    final hosts = AppServices.of(context).sshHosts.hosts;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          AppSectionHeader(
            title: '我的服务器',
            actionText: '管理',
            onAction: () => context.push('/ssh'),
          ),
        ),
        if (hosts.isEmpty)
          _buildEmptyState('还没有添加服务器', '添加服务器',
              () => context.push('/ssh/host/new'))
        else
          TCellGroup(
            cells: [
              for (final host in hosts.take(5))
                TCell(
                  prefix: TIcon(TIcons.server, size: 20, color: theme.brandColor),
                  title: Text(host.displayLabel),
                  subtitle: Text(host.endpoint),
                  arrow: true,
                  onTap: () => context.push('/ssh/session/${host.id}'),
                ),
            ],
          ),
      ],
    );
  }

  // ── 服务与公告 ──────────────────────────────────────────────────────

  Widget _buildServicesSection(TThemeData theme) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          AppSectionHeader(
            title: '我的服务',
            actionText: '查看全部',
            onAction: () => context.go('/services'),
          ),
        ),
        if (_groups.isEmpty)
          _buildEmptyState('暂无服务实例', '前往服务列表', () => context.go('/services'))
        else
          for (final group in _groups) ...[
            _buildGroupCard(theme, group),
            const SizedBox(height: AppSpacing.gapL),
          ],
      ],
    );
  }

  /// 服务分组：`TCellGroup(title: 分组名)` 通栏列表，每项一个 `TCell`。
  Widget _buildGroupCard(TThemeData theme, Map<String, dynamic> group) {
    final title = strOf(group, 'title').isEmpty ? strOf(group, 'name') : strOf(group, 'title');
    final items = listOf(group['items']);
    return Theme(
      // 分组标题与 Cell 左缘对齐（默认组标题有 16 的独立内边距，这里直接沿用）。
      data: Theme.of(context).mergeExtension(
        const TCellThemeData(
          titlePadding: EdgeInsets.fromLTRB(
            AppSpacing.cellHorizontal,
            AppSpacing.gapM,
            AppSpacing.cellHorizontal,
            AppSpacing.gapXs,
          ),
        ),
      ),
      child: TCellGroup(
        title: Row(
          children: [
            Expanded(child: Text(title.isEmpty ? '未分类' : title)),
            Text('${items.length} 个', style: AppType.caption(theme)),
          ],
        ),
        cells: [
          for (final item in items.take(2)) _buildServiceRow(theme, item),
          if (items.length > 2)
            TCell(
              title: Text(
                '查看全部 ${items.length} 个',
                style: AppType.caption(theme).copyWith(color: theme.brandColor),
              ),
              arrow: true,
              onTap: () => context.go('/services'),
            ),
        ],
      ),
    );
  }

  TCell _buildServiceRow(TThemeData theme, Map<String, dynamic> item) {
    final name =
        strOf(item, 'name').isEmpty ? strOf(item, 'product_name') : strOf(item, 'name');
    final statusLabel = strOf(item, 'status_label');
    final tone = strOf(item, 'status_tone');
    final expires = formatDate(item['expires_at']);
    return TCell(
      title: Text(name),
      subtitle: Text('到期：$expires'),
      trailing: AppStatusTag(label: statusLabel, preset: AppStatusTag.presetOf(tone)),
      arrow: true,
      onTap: () => context.push('/services/${intOf(item, 'id')}'),
    );
  }

  Widget _buildNoticesSection(TThemeData theme) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          AppSectionHeader(
            title: '公告',
            actionText: '查看全部',
            onAction: () => context.push('/notices'),
          ),
        ),
        if (_notices.isEmpty)
          _buildEmptyState('暂无公告', '刷新', _refresh)
        else
          TCellGroup(
            cells: [
              for (final notice in _notices) _buildNoticeRow(notice),
            ],
          ),
      ],
    );
  }

  TCell _buildNoticeRow(Map<String, dynamic> notice) {
    final title = strOf(notice, 'title').isEmpty ? '(无标题)' : strOf(notice, 'title');
    final time = strOf(notice, 'publish_at').isNotEmpty
        ? strOf(notice, 'publish_at')
        : strOf(notice, 'created_at');
    return TCell(
      title: Text(title),
      subtitle: Text(formatDate(time)),
      arrow: true,
      onTap: () => context.push('/notices/${intOf(notice, 'id')}'),
    );
  }

  /// 空态：通栏 `TCell`（文案 + 右侧操作按钮），与列表观感一致。
  Widget _buildEmptyState(
    String text,
    String action,
    VoidCallback onTap,
  ) {
    return TCellGroup(
      cells: [
        TCell(
          title: Text(text),
          trailing: TButton(
            size: TButtonSize.small,
            variant: TButtonVariant.outline,
            colorPreset: TButtonColorPreset.primary,
            onPressed: onTap,
            child: Text(action),
          ),
        ),
      ],
    );
  }
}

class _ToolItem {
  const _ToolItem(this.label, this.icon, this.onTap, {this.badge});

  final String label;
  final IconData icon;
  final VoidCallback onTap;
  final String? badge;
}

class _EventItem {
  const _EventItem({
    required this.label,
    required this.value,
    required this.hint,
    required this.onTap,
  });

  final String label;
  final String value;
  final String hint;
  final VoidCallback onTap;
}
