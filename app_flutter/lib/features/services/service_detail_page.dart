import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/net/app_services.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/util/format.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import '../../widgets/data_state.dart';

/// 服务详情：顶部概览卡 + 能力操作区 + 流量卡 + 概览/监控/日志/账单页签。
///
/// 呈现层使用 [AppCard] 堆叠与页内分段器；详情/监控/日志三处请求各自带
/// `_seq` 序号，只接受最后一次响应。电源操作后以有界轮询刷新运行态。
class ServiceDetailPage extends StatefulWidget {
  const ServiceDetailPage({super.key, required this.serviceId});

  final int serviceId;

  @override
  State<ServiceDetailPage> createState() => _ServiceDetailPageState();
}

class _ServiceDetailPageState extends State<ServiceDetailPage> {
  Map<String, dynamic>? _detail;
  bool _loading = true;
  String? _error;

  /// 详情请求序号：只接受最后一次请求的结果，防止快速刷新时旧响应覆盖新数据。
  int _seq = 0;

  int _tabIndex = 3;

  bool _actionBusy = false;
  bool _autoRenewBusy = false;

  /// 登录凭据（connection 接口）。仅在具备远程控制台能力的实例上拉取。
  Map<String, dynamic>? _connection;

  /// 凭据请求序号：只接受最后一次响应，避免刷新时旧数据覆盖新数据。
  int _connectionSeq = 0;

  /// 密码是否临时以明文展示（默认遮蔽，离开页面即复位）。
  bool _showPassword = false;

  // ── 监控 ─────────────────────────────────────────────────────────────
  String _monitorRange = '3h';
  String? _monitorType;
  List<Map<String, dynamic>> _monitorOptions = const [];
  Map<String, dynamic>? _monitorData;
  bool _monitorLoading = false;
  String? _monitorError;
  int _monitorSeq = 0;

  // ── 操作日志 ─────────────────────────────────────────────────────────
  List<Map<String, dynamic>> _logs = const [];
  int _logPage = 1;
  int _logTotal = 0;
  bool _logLoading = false;
  String? _logError;
  int _logSeq = 0;

  static const int _logPageSize = 15;

  static const List<String> _tabLabels = ['概览', '监控', '操作日志', '账单'];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _load();
    });
  }

  void _selectTab(int index) {
    if (index == _tabIndex) return;
    setState(() => _tabIndex = index);
    if (index == 1 && _monitorData == null && !_monitorLoading) {
      _loadMonitor();
    }
    if (index == 2 && _logs.isEmpty && !_logLoading) {
      _loadLogs(page: 1);
    }
  }

  // ── 加载 ─────────────────────────────────────────────────────────────

  Future<void> _load({bool refresh = false}) async {
    final seq = ++_seq;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final services = AppServices.of(context);
      final detail = await services.services.detail(
        widget.serviceId,
        refresh: refresh,
      );
      if (!mounted || seq != _seq) return;
      setState(() {
        _detail = detail;
        _loading = false;
        _showPassword = false;
      });
      unawaited(_loadConnection(detail));
    } catch (error) {
      if (!mounted || seq != _seq) return;
      setState(() {
        _loading = false;
        _error = AppServices.errorText(error);
      });
      if (_detail != null) {
        AppFeedback.error(context, AppServices.errorText(error));
      }
    }
  }

  Future<void> _refresh() async {
    // 主动刷新必须带 refresh=1 穿透后端 30 秒详情缓存，否则看不到外部状态变化。
    await _load(refresh: true);
    if (!mounted) return;
    if (_monitorData != null) await _loadMonitor();
    if (!mounted) return;
    if (_logs.isNotEmpty) await _loadLogs(page: _logPage);
  }

  String get _serviceName {
    final name = strOf(_detail, 'name').trim();
    return name.isEmpty ? '服务详情' : name;
  }

  // ── 页面骨架 ─────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final detail = _detail;
    return AppScaffold(
      title: _serviceName,
      body: DataState(
        loading: _loading && detail == null,
        error: detail == null ? _error : null,
        retry: _load,
        child: detail == null ? const SizedBox.shrink() : _buildContent(detail),
      ),
    );
  }

  /// 页面水平内边距：手机 16，平板 24（`context.pagePadding`）。
  double get _pageGutter => context.pagePadding;

  /// 页签内容内边距：手机用页面边距；平板页签内容放在右栏内，再让出
  /// 一点列内边距（右栏本身已在分栏中留了页面边距），避免贴边。
  double get _sliverGutter =>
      context.isTablet ? AppSpacing.gapS : AppSpacing.page;

  /// 卡片之间的纵向间距：平板加大一档，避免分栏后显得拥挤。
  double get _sectionGap =>
      context.isTablet ? AppSpacing.gapL : AppSpacing.gapM;

  Widget _buildContent(Map<String, dynamic> detail) {
    // 平板：左侧信息/操作区（flex 3）+ 右侧页签内容（flex 4），两列独立滚动。
    if (context.isTablet) {
      return RefreshList(
        onRefresh: _refresh,
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            _pageGutter,
            AppSpacing.gapM,
            _pageGutter,
            0,
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(flex: 3, child: _buildSideColumn(detail)),
              const SizedBox(width: AppSpacing.gapXl),
              Expanded(flex: 4, child: _buildTabPanel(detail)),
            ],
          ),
        ),
      );
    }
    // 手机：上下堆叠，信息区随整页滚动，页签吸顶。
    return RefreshList(
      onRefresh: _refresh,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverPadding(
            padding: EdgeInsets.fromLTRB(
              _pageGutter,
              AppSpacing.gapM,
              _pageGutter,
              0,
            ),
            sliver: SliverList.list(
              children: [
                _buildSummaryCard(detail),
                if (_hasLoginInfo) ...[
                  SizedBox(height: _sectionGap),
                  _buildLoginCard(),
                ],
                SizedBox(height: _sectionGap),
                _buildActionsCard(detail),
                if (_hasTraffic(detail)) ...[
                  SizedBox(height: _sectionGap),
                  _buildTrafficCard(detail),
                ],
              ],
            ),
          ),
          SliverPersistentHeader(
            pinned: true,
            delegate: _PinnedTabsDelegate(
              height: 46,
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: _pageGutter),
                child: AppSegmentedTabs(
                  labels: _tabLabels,
                  current: _tabIndex,
                  onChanged: _selectTab,
                ),
              ),
            ),
          ),
          ..._buildTabSlivers(detail),
          const SliverToBoxAdapter(
            child: SizedBox(height: AppSpacing.pageBottom),
          ),
        ],
      ),
    );
  }

  /// 平板左栏：概览信息、能力操作、流量，独立滚动。
  Widget _buildSideColumn(Map<String, dynamic> detail) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: AppSpacing.pageBottom),
      children: [
        _buildSummaryCard(detail),
        if (_hasLoginInfo) ...[
          SizedBox(height: _sectionGap),
          _buildLoginCard(),
        ],
        SizedBox(height: _sectionGap),
        _buildActionsCard(detail),
        if (_hasTraffic(detail)) ...[
          SizedBox(height: _sectionGap),
          _buildTrafficCard(detail),
        ],
      ],
    );
  }

  /// 平板右栏：页签固定在栏顶，内容独立滚动。
  Widget _buildTabPanel(Map<String, dynamic> detail) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.card),
            border: Border.all(color: context.tTheme.borderLevel1Color),
            color: context.tTheme.bgColorContainer,
          ),
          clipBehavior: Clip.antiAlias,
          child: AppSegmentedTabs(
            labels: _tabLabels,
            current: _tabIndex,
            onChanged: _selectTab,
          ),
        ),
        const SizedBox(height: AppSpacing.gapM),
        Expanded(
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              ..._buildTabSlivers(detail),
              const SliverToBoxAdapter(
                child: SizedBox(height: AppSpacing.pageBottom),
              ),
            ],
          ),
        ),
      ],
    );
  }

  List<Widget> _buildTabSlivers(Map<String, dynamic> detail) {
    return switch (_tabIndex) {
      1 => _buildMonitorSlivers(),
      2 => _buildLogSlivers(),
      3 => _buildBillingSlivers(detail),
      _ => _buildOverviewSlivers(detail),
    };
  }

  // ── 概览：顶部信息 / 能力操作 / 流量 ─────────────────────────────────

  Widget _buildSummaryCard(Map<String, dynamic> detail) {
    final theme = context.tTheme;
    final upstream = mapOf(detail['upstream']);
    final runtime = mapOf(detail['runtime']);
    final statusLabel = strOf(detail, 'status_label').trim();
    final productPath = strOf(detail, 'product_full_path').trim();
    final ip = strOf(upstream, 'dedicated_ip').trim();
    // domain 在上游未分配 IP 时是实例 ID（如 ser123），不能当 IP 展示。
    final looksLikeIp = RegExp(r'^\d{1,3}(\.\d{1,3}){3}$').hasMatch(ip);
    String textOrDash(String value) {
      final text = value.trim();
      return text.isEmpty ? '--' : text;
    }

    final powerLabel = strOf(runtime, 'power_label').trim();

    return _cellGroupCard([
      TCell(
        title: Text(_serviceName),
        subtitle: productPath.isEmpty
            ? null
            : Text(productPath, maxLines: 2, overflow: TextOverflow.ellipsis),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            AppStatusTag(
              label: statusLabel.isEmpty ? '未知' : statusLabel,
              preset: _tonePreset(strOf(detail, 'status_tone')),
            ),
            InkResponse(
              onTap: _rename,
              radius: 20,
              child: Padding(
                padding: const EdgeInsets.all(6),
                child: Icon(
                  TIcons.edit,
                  size: 18,
                  color: theme.textColorSecondary,
                ),
              ),
            ),
          ],
        ),
      ),
      TCell(
        title: const Text('IP 地址'),
        note: Text(looksLikeIp ? ip : '--', style: AppType.money(theme)),
        trailing: looksLikeIp
            ? InkResponse(
                onTap: () => _copyIp(ip),
                radius: 20,
                child: Padding(
                  padding: const EdgeInsets.all(6),
                  child: Icon(TIcons.copy, size: 18, color: theme.brandColor),
                ),
              )
            : null,
        onTap: looksLikeIp ? () => _copyIp(ip) : null,
      ),
      TCell(
        title: const Text('操作系统'),
        note: Text(textOrDash(strOf(upstream, 'os'))),
      ),
      TCell(
        title: const Text('到期时间'),
        note: Text(formatDateTime(detail['expires_at'])),
      ),
      TCell(
        title: const Text('计费周期'),
        note: Text(textOrDash(strOf(detail, 'billing_cycle_label'))),
      ),
      TCell(
        title: const Text('金额'),
        note: Text(
          formatMoneyText(detail['amount']),
          style: AppType.money(theme),
        ),
      ),
      TCell(title: const Text('电源状态'), note: Text(textOrDash(powerLabel))),
    ]);
  }

  /// TDesign 单元格组卡片：白色圆角块内直接铺 [TCellGroup]。
  ///
  /// 与 [AppCard] 观感一致（9 圆角、1px 描边），但行高、分割线与内边距
  /// 全部交给 TDesign；圆角通过 `ClipRRect` 保证行背景不越过描边。
  Widget _cellGroupCard(List<TCell> cells, {Widget? title}) {
    final theme = context.tTheme;
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: theme.borderLevel1Color),
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: theme.bgColorContainer,
        child: TCellGroup(title: title, cells: cells),
      ),
    );
  }

  Future<void> _copyIp(String ip) async {
    await Clipboard.setData(ClipboardData(text: ip));
    if (mounted) AppFeedback.toast(context, 'IP 已复制');
  }

  // ── 登录信息 ─────────────────────────────────────────────────────────

  /// 拉取登录凭据。只有内置远程控制台（具备 power 能力）的实例才有真实账号密码，
  /// 其他类型不请求，避免多余的上游同步。
  Future<void> _loadConnection(Map<String, dynamic> detail) async {
    final services = AppServices.of(context).services;
    if (mapOf(detail['actions'])['power'] != true) {
      if (_connection != null) setState(() => _connection = null);
      return;
    }
    final seq = ++_connectionSeq;
    try {
      final connection = await services.connection(widget.serviceId);
      if (!mounted || seq != _connectionSeq) return;
      setState(() => _connection = connection);
    } catch (_) {
      // 凭据属于附加信息，失败不打断详情页；SSH 终端入口会再次校验并给出提示。
      if (!mounted || seq != _connectionSeq) return;
      setState(() => _connection = null);
    }
  }

  bool get _hasLoginInfo {
    final connection = _connection;
    if (connection == null) return false;
    return strOf(connection, 'username').trim().isNotEmpty ||
        strOf(connection, 'password').isNotEmpty ||
        connection['has_password'] == true;
  }

  /// 登录信息卡：密码默认遮蔽，点眼睛临时查看明文，用户名/密码均支持复制。
  Widget _buildLoginCard() {
    final theme = context.tTheme;
    final connection = _connection;
    if (connection == null || !_hasLoginInfo) return const SizedBox.shrink();
    final username = strOf(connection, 'username').trim();
    final password = strOf(connection, 'password');
    final hasPassword =
        password.isNotEmpty || connection['has_password'] == true;
    final port = int.tryParse('${connection['port'] ?? ''}') ?? 0;

    return _cellGroupCard([
      TCell(
        title: const Text('用户名'),
        note: Text(
          username.isEmpty ? '--' : username,
          style: AppType.body(theme),
        ),
        trailing: username.isEmpty
            ? null
            : _credentialAction(
                icon: TIcons.copy,
                label: '复制用户名',
                color: theme.brandColor,
                onTap: () => _copyCredential(username, '用户名已复制'),
              ),
        onTap: username.isEmpty
            ? null
            : () => _copyCredential(username, '用户名已复制'),
      ),
      TCell(
        title: const Text('密码'),
        note: Text(
          !hasPassword
              ? '--'
              : _showPassword
              ? password
              : '••••••••',
          style: AppType.money(theme),
        ),
        trailing: !hasPassword
            ? null
            : Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _credentialAction(
                    icon: _showPassword ? TIcons.browse : TIcons.browse_off,
                    label: _showPassword ? '隐藏密码' : '显示密码',
                    color: theme.textColorSecondary,
                    onTap: () => setState(() => _showPassword = !_showPassword),
                  ),
                  if (password.isNotEmpty)
                    _credentialAction(
                      icon: TIcons.copy,
                      label: '复制密码',
                      color: theme.brandColor,
                      onTap: () => _copyCredential(password, '密码已复制'),
                    ),
                ],
              ),
        onTap: hasPassword
            ? () => setState(() => _showPassword = !_showPassword)
            : null,
      ),
      if (port > 0) TCell(title: const Text('端口'), note: Text('$port')),
    ], title: const Text('登录信息'));
  }

  Widget _credentialAction({
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Semantics(
      button: true,
      label: label,
      child: InkResponse(
        onTap: onTap,
        radius: 20,
        child: Padding(
          padding: const EdgeInsets.all(6),
          child: Icon(icon, size: 18, color: color),
        ),
      ),
    );
  }

  Future<void> _copyCredential(String value, String message) async {
    await Clipboard.setData(ClipboardData(text: value));
    if (mounted) AppFeedback.toast(context, message);
  }

  Widget _buildActionsCard(Map<String, dynamic> detail) {
    final theme = context.tTheme;
    final actions = mapOf(detail['actions']);
    final available = actions['available'] is List
        ? (actions['available'] as List).map((item) => '$item').toSet()
        : <String>{};
    final powerEnabled = actions['power'] == true;
    final busy = _actionBusy;
    final powerState = strOf(
      mapOf(detail['runtime']),
      'power_state',
    ).toLowerCase();

    // 开机/关机合并为一个三态按钮：运行中→关机，已关机→开机，中间态只读展示
    // 真实状态文案，与 v4 控制台 ConsoleHeader 的电源口径保持一致。
    final powerLabel = strOf(mapOf(detail['runtime']), 'power_label').trim();
    final running = powerState == 'on' || powerState == 'running';
    final transitional =
        powerState == 'reboot' ||
        powerState == 'rebooting' ||
        powerState == 'task' ||
        powerState == 'starting' ||
        powerState == 'booting' ||
        powerState == 'stopping' ||
        powerState == 'shutting_down';
    final canPowerOn = available.contains('on');
    final canPowerOff = available.contains('off');

    final powerCells = <Widget>[];
    if (transitional) {
      powerCells.add(
        _actionButton(
          label: powerLabel.isEmpty ? '处理中' : powerLabel,
          onTap: () {},
          enabled: false,
          icon: const Icon(TIcons.refresh, size: 16),
        ),
      );
    } else if (running && canPowerOff) {
      powerCells.add(
        _actionButton(
          label: '关机',
          onTap: () => _runPowerAction('off', '关机'),
          enabled: powerEnabled && !busy,
          variant: TButtonVariant.fill,
          preset: TButtonColorPreset.primary,
          icon: const Icon(TIcons.poweroff, size: 16),
        ),
      );
    } else if (canPowerOn) {
      powerCells.add(
        _actionButton(
          label: '开机',
          onTap: () => _runPowerAction('on', '开机'),
          enabled: powerEnabled && !busy,
          variant: TButtonVariant.fill,
          preset: TButtonColorPreset.primary,
          icon: const Icon(TIcons.play, size: 16),
        ),
      );
    } else if (canPowerOff) {
      powerCells.add(
        _actionButton(
          label: '关机',
          onTap: () => _runPowerAction('off', '关机'),
          enabled: powerEnabled && !busy,
          variant: TButtonVariant.fill,
          preset: TButtonColorPreset.primary,
          icon: const Icon(TIcons.poweroff, size: 16),
        ),
      );
    }

    for (final action in const [
      ('reboot', '重启', TIcons.refresh),
      ('hard_off', '强制关机', TIcons.poweroff),
      ('hard_reboot', '强制重启', TIcons.refresh),
    ]) {
      if (!available.contains(action.$1)) continue;
      final danger = action.$1 == 'hard_off' || action.$1 == 'hard_reboot';
      powerCells.add(
        _actionButton(
          label: action.$2,
          onTap: () => _runPowerAction(action.$1, action.$2),
          enabled: powerEnabled && !busy,
          preset: danger
              ? TButtonColorPreset.danger
              : TButtonColorPreset.defaultTheme,
          icon: Icon(action.$3, size: 16),
        ),
      );
    }

    final manageCells = <Widget>[];
    if (actions['reinstall'] == true) {
      manageCells.add(
        _actionButton(
          label: '重装系统',
          onTap: _reinstall,
          enabled: !busy,
          preset: TButtonColorPreset.danger,
          icon: const Icon(TIcons.system_2, size: 16),
        ),
      );
    }
    if (actions['password_reset'] == true) {
      manageCells.add(
        _actionButton(
          label: '重置密码',
          onTap: _resetPassword,
          enabled: !busy,
          icon: const Icon(TIcons.lock_on, size: 16),
        ),
      );
    }
    manageCells.add(
      _actionButton(
        label: '续费',
        onTap: _openRenew,
        enabled: !busy,
        icon: const Icon(TIcons.calendar, size: 16),
      ),
    );

    final consoleCells = <Widget>[];
    if (actions['power'] == true) {
      consoleCells.add(
        _actionButton(
          label: 'SSH 终端',
          onTap: () => context.push('/services/${widget.serviceId}/ssh'),
          preset: TButtonColorPreset.primary,
          icon: const Icon(TIcons.terminal, size: 16),
        ),
      );
    }
    if (actions['vnc'] == true) {
      consoleCells.add(
        _actionButton(
          label: 'VNC 控制台',
          onTap: () => context.push('/services/${widget.serviceId}/vnc'),
          preset: TButtonColorPreset.primary,
          icon: const Icon(TIcons.desktop, size: 16),
        ),
      );
    }

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('实例操作', style: AppType.cardTitle(theme)),
          if (powerCells.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.gapM),
            _actionGroupLabel('电源', theme),
            const SizedBox(height: AppSpacing.gapS),
            _actionWrap(powerCells),
          ],
          if (manageCells.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.gapL),
            _actionGroupLabel('系统维护', theme),
            const SizedBox(height: AppSpacing.gapS),
            _actionWrap(manageCells),
          ],
          if (consoleCells.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.gapL),
            _actionGroupLabel('远程控制台', theme),
            const SizedBox(height: AppSpacing.gapS),
            _actionWrap(consoleCells),
          ],
          if (busy) ...[
            const SizedBox(height: AppSpacing.gapM),
            Row(
              children: [
                const SizedBox(
                  width: 14,
                  height: 14,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
                const SizedBox(width: AppSpacing.gapS),
                Text('正在同步实例状态…', style: AppType.caption(theme)),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _actionGroupLabel(String label, TThemeData theme) {
    return Text(
      label,
      style: AppType.secondary(theme).copyWith(fontWeight: FontWeight.w500),
    );
  }

  /// 能力操作按钮：TDesign 规范尺寸（medium 40 高），文案单行不换行。
  Widget _actionButton({
    required String label,
    required VoidCallback onTap,
    bool enabled = true,
    TButtonVariant variant = TButtonVariant.outline,
    TButtonColorPreset preset = TButtonColorPreset.defaultTheme,
    Widget? icon,
  }) {
    return TButton(
      size: TButtonSize.medium,
      variant: variant,
      colorPreset: preset,
      icon: icon,
      onPressed: enabled ? onTap : null,
      child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
    );
  }

  /// 操作按钮组：Wrap 自然排布，间距使用 TDesign 8 栅格。
  ///
  /// 平板按可用宽度均分，最多 4 个一行，按钮等宽更整齐；
  /// 手机保持按钮自身宽度自然换行。
  Widget _actionWrap(List<Widget> cells) {
    if (!context.isTablet) {
      return Wrap(
        spacing: AppSpacing.gapS,
        runSpacing: AppSpacing.gapS,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: cells,
      );
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        // 按钮 medium 高 40、左右内边距各 16，可行宽度至少 88 才不截断两字文案。
        const minItemWidth = 88.0;
        final columns =
            ((constraints.maxWidth + AppSpacing.gapS) /
                    (minItemWidth + AppSpacing.gapS))
                .floor()
                .clamp(1, 4);
        final itemWidth =
            (constraints.maxWidth - (columns - 1) * AppSpacing.gapS) / columns;
        return Wrap(
          spacing: AppSpacing.gapS,
          runSpacing: AppSpacing.gapS,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            for (final cell in cells) SizedBox(width: itemWidth, child: cell),
          ],
        );
      },
    );
  }

  bool _hasTraffic(Map<String, dynamic> detail) {
    final traffic = mapOf(detail['traffic']);
    return strOf(traffic, 'usage_label').trim().isNotEmpty ||
        strOf(traffic, 'limit_label').trim().isNotEmpty ||
        strOf(traffic, 'remaining_label').trim().isNotEmpty ||
        _trafficProgress(traffic) != null;
  }

  double? _trafficProgress(Map<String, dynamic> traffic) {
    final percent = double.tryParse('${traffic['usage_percent'] ?? ''}');
    if (percent == null) return null;
    return (percent / 100).clamp(0.0, 1.0).toDouble();
  }

  Widget _buildTrafficCard(Map<String, dynamic> detail) {
    final theme = context.tTheme;
    final traffic = mapOf(detail['traffic']);
    final usageLabel = strOf(traffic, 'usage_label').trim();
    final limitLabel = strOf(traffic, 'limit_label').trim();
    final remainingLabel = strOf(traffic, 'remaining_label').trim();
    final percentValue = double.tryParse('${traffic['usage_percent'] ?? ''}');
    final progress = _trafficProgress(traffic);

    final trafficCells = <TCell>[
      if (usageLabel.isNotEmpty)
        TCell(
          title: const Text('已用'),
          note: Text(usageLabel, style: AppType.money(theme)),
        ),
      if (limitLabel.isNotEmpty)
        TCell(
          title: const Text('总量'),
          note: Text(limitLabel, style: AppType.money(theme)),
        ),
      if (remainingLabel.isNotEmpty)
        TCell(
          title: const Text('剩余'),
          note: Text(remainingLabel, style: AppType.money(theme)),
        ),
    ];

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: theme.borderLevel1Color),
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: theme.bgColorContainer,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.cellHorizontal,
                AppSpacing.gapL,
                AppSpacing.cellHorizontal,
                AppSpacing.gapM,
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text('流量使用', style: AppType.cardTitle(theme)),
                  ),
                  if (percentValue != null)
                    Text(
                      _percentText(percentValue),
                      style: AppType.money(
                        theme,
                        size: 14,
                        color: theme.textColorSecondary,
                      ),
                    ),
                ],
              ),
            ),
            if (progress != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.cellHorizontal,
                  0,
                  AppSpacing.cellHorizontal,
                  AppSpacing.gapM,
                ),
                child: TProgress.linear(
                  value: progress,
                  status: _trafficStatus(progress, traffic),
                ),
              ),
            if (trafficCells.isNotEmpty) TCellGroup(cells: trafficCells),
          ],
        ),
      ),
    );
  }

  // ── 概览页签：规格 + 服务设置 ────────────────────────────────────────

  List<Widget> _buildOverviewSlivers(Map<String, dynamic> detail) {
    final specs = <TCell>[];
    for (final item in listOf(detail['specs'])) {
      final label = strOf(item, 'label').trim();
      final value = strOf(item, 'value').trim();
      if (label.isEmpty || value.isEmpty || value == '--') continue;
      specs.add(TCell(title: Text(label), note: Text(value)));
    }

    return [
      SliverPadding(
        padding: EdgeInsets.fromLTRB(
          _sliverGutter,
          AppSpacing.gapM,
          _sliverGutter,
          0,
        ),
        sliver: SliverList.list(
          children: [
            if (specs.isEmpty)
              AppCard(
                child: Text('暂无规格信息', style: AppType.secondary(context.tTheme)),
              )
            else
              _cellGroupCard(specs, title: const Text('服务规格')),
            const SizedBox(height: AppSpacing.gapM),
            _buildSettingsCard(detail),
          ],
        ),
      ),
    ];
  }

  Widget _buildSettingsCard(Map<String, dynamic> detail) {
    final theme = context.tTheme;
    final autoRenew = detail['auto_renew'] == true || detail['auto_renew'] == 1;
    final remark = strOf(detail, 'remark').trim();

    return _cellGroupCard([
      TCell(
        title: const Text('自动续费'),
        trailing: TSwitch(
          value: autoRenew,
          loading: _autoRenewBusy,
          size: TSwitchSize.small,
          onChanged: _autoRenewBusy ? null : _toggleAutoRenew,
        ),
      ),
      TCell(
        title: const Text('备注'),
        note: Text(
          remark.isEmpty ? '未设置备注' : remark,
          style: AppType.secondary(theme),
        ),
        arrow: true,
        onTap: _editRemark,
      ),
    ]);
  }

  // ── 电源操作 ─────────────────────────────────────────────────────────

  Future<void> _runPowerAction(String action, String label) async {
    if (_actionBusy) return;
    final danger = action != 'on' && action != 'reboot';
    final confirmed = await AppFeedback.confirm(
      context,
      title: '确认$label',
      content: '确定要对实例执行「$label」操作吗',
      danger: danger,
    );
    if (!confirmed || !mounted) return;

    final services = AppServices.of(context);
    final loading = AppFeedback.loading(context, '正在执行$label…');
    try {
      await services.services.powerAction(widget.serviceId, action);
    } catch (error) {
      AppFeedback.dismissLoading(loading);
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
      return;
    }
    AppFeedback.dismissLoading(loading);
    if (!mounted) return;
    AppFeedback.success(context, '「$label」指令已下发，正在同步实例状态');
    setState(() => _actionBusy = true);
    await _pollRuntime();
  }

  /// 操作后轮询运行态：逐步刷新电源状态 / 流量 / 能力位。
  Future<void> _pollRuntime() async {
    final services = AppServices.of(context);
    for (var i = 0; i < 6; i++) {
      await Future<void>.delayed(const Duration(seconds: 2));
      if (!mounted) return;
      try {
        final runtime = await services.services.runtime(widget.serviceId);
        if (!mounted) return;
        setState(() => _applyRuntime(runtime));
      } catch (_) {
        // 单次轮询失败不打断，继续下一次。
      }
    }
    if (!mounted) return;
    setState(() => _actionBusy = false);
  }

  void _applyRuntime(Map<String, dynamic> runtime) {
    final detail = _detail;
    if (detail == null) return;
    for (final key in ['status', 'status_label', 'status_tone', 'expires_at']) {
      if (runtime.containsKey(key)) detail[key] = runtime[key];
    }
    for (final key in ['upstream', 'runtime', 'traffic', 'actions', 'specs']) {
      final value = runtime[key];
      if (value is Map) detail[key] = Map<String, dynamic>.from(value);
    }
  }

  // ── 重装系统 ─────────────────────────────────────────────────────────

  Future<void> _reinstall() async {
    if (_actionBusy) return;
    final services = AppServices.of(context);
    final loading = AppFeedback.loading(context, '正在获取镜像列表…');
    List<Map<String, dynamic>> entries;
    try {
      final payload = await services.services.reinstallOptions(
        widget.serviceId,
      );
      entries = _reinstallEntries(payload);
    } catch (error) {
      AppFeedback.dismissLoading(loading);
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
      return;
    }
    AppFeedback.dismissLoading(loading);
    if (!mounted) return;
    if (entries.isEmpty) {
      AppFeedback.toast(context, '暂无可重装的系统镜像');
      return;
    }

    final selected = await TDialog.show<Map<String, dynamic>>(
      context,
      dialog: TDialog(
        title: const Text('选择操作系统'),
        content: SizedBox(
          width: double.infinity,
          height: math.min(entries.length * 56.0 + 8, 360),
          child: ListView.separated(
            padding: EdgeInsets.zero,
            itemCount: entries.length,
            separatorBuilder: (_, __) =>
                Container(height: 0.5, color: context.tTheme.borderLevel1Color),
            itemBuilder: (context, index) {
              final entry = entries[index];
              return InkWell(
                onTap: () =>
                    Navigator.of(context, rootNavigator: true).pop(entry),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 4,
                    vertical: 10,
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              strOf(entry, 'name'),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            if (strOf(entry, 'group_name').isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.only(top: 2),
                                child: Text(
                                  strOf(entry, 'group_name'),
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: context.tTheme.textColorSecondary,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                      Icon(
                        TIcons.chevron_right,
                        size: 18,
                        color: context.tTheme.textColorSecondary,
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
        actions: const [TDialogAction(child: Text('取消'))],
      ),
    );
    if (selected == null || !mounted) return;

    final osId = strOf(selected, 'os_id');
    final osName = strOf(selected, 'name');
    if (osId.isEmpty) return;
    final confirmed = await AppFeedback.confirm(
      context,
      title: '确认重装系统',
      content: '将重新安装「$osName」，系统盘数据将全部清空且无法恢复',
      confirmText: '确认重装',
      danger: true,
    );
    if (!confirmed || !mounted) return;

    final services2 = AppServices.of(context);
    final loading2 = AppFeedback.loading(context, '正在提交重装…');
    try {
      await services2.services.reinstall(widget.serviceId, osId);
    } catch (error) {
      AppFeedback.dismissLoading(loading2);
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
      return;
    }
    AppFeedback.dismissLoading(loading2);
    if (!mounted) return;
    AppFeedback.success(context, '重装任务已提交');
    setState(() => _actionBusy = true);
    await _pollRuntime();
  }

  List<Map<String, dynamic>> _reinstallEntries(Map<String, dynamic> payload) {
    final result = <Map<String, dynamic>>[];
    final groups = listOf(payload['groups']);
    if (groups.isNotEmpty) {
      for (final group in groups) {
        final groupName = strOf(group, 'group_name').trim();
        var children = <Map<String, dynamic>>[];
        for (final key in ['options', 'os', 'list', 'children']) {
          final list = listOf(group[key]);
          if (list.isNotEmpty) {
            children = list;
            break;
          }
        }
        if (children.isEmpty) {
          if (strOf(group, 'os_id').isEmpty) continue;
          result.add({
            'os_id': strOf(group, 'os_id'),
            'name': strOf(group, 'name'),
            'group_name': groupName.isEmpty ? '默认分组' : groupName,
          });
          continue;
        }
        for (final child in children) {
          if (strOf(child, 'os_id').isEmpty) continue;
          final childGroup = strOf(child, 'group_name').trim();
          result.add({
            'os_id': strOf(child, 'os_id'),
            'name': strOf(child, 'name'),
            'group_name': childGroup.isNotEmpty
                ? childGroup
                : (groupName.isEmpty ? '默认分组' : groupName),
          });
        }
      }
      return result;
    }

    var flat = <Map<String, dynamic>>[];
    for (final key in ['options', 'list', 'os']) {
      final list = listOf(payload[key]);
      if (list.isNotEmpty) {
        flat = list;
        break;
      }
    }
    for (final item in flat) {
      if (strOf(item, 'os_id').isEmpty) continue;
      final groupName = strOf(item, 'group_name').trim();
      result.add({
        'os_id': strOf(item, 'os_id'),
        'name': strOf(item, 'name'),
        'group_name': groupName.isEmpty ? '默认分组' : groupName,
      });
    }
    return result;
  }

  // ── 重置密码 ─────────────────────────────────────────────────────────

  Future<void> _resetPassword() async {
    if (_actionBusy) return;
    final passwordController = TextEditingController();
    final confirmController = TextEditingController();
    final confirmed = await TDialog.show<bool>(
      context,
      dialog: TDialog(
        title: const Text('重置服务器密码'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TInput(
              controller: passwordController,
              hintText: '新密码（至少 8 位）',
              obscureText: true,
              showPasswordToggle: true,
            ),
            const SizedBox(height: 12),
            TInput(
              controller: confirmController,
              hintText: '确认新密码',
              obscureText: true,
              showPasswordToggle: true,
            ),
          ],
        ),
        actions: const [
          TDialogAction(child: Text('取消'), result: false),
          TDialogAction(
            child: Text('确定'),
            result: true,
            role: TDialogActionRole.primary,
          ),
        ],
      ),
    );
    final password = passwordController.text;
    final confirmation = confirmController.text;
    passwordController.dispose();
    confirmController.dispose();
    if (confirmed != true || !mounted) return;

    if (password.length < 8) {
      AppFeedback.error(context, '密码至少 8 位');
      return;
    }
    if (password != confirmation) {
      AppFeedback.error(context, '两次输入的密码不一致');
      return;
    }

    final services = AppServices.of(context);
    final loading = AppFeedback.loading(context, '正在重置密码…');
    Map<String, dynamic> result;
    try {
      result = await services.services.resetPassword(
        widget.serviceId,
        password,
        confirmation,
      );
    } catch (error) {
      AppFeedback.dismissLoading(loading);
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
      return;
    }
    AppFeedback.dismissLoading(loading);
    if (!mounted) return;
    // 上游要求二次验证时密码并未真正生效（后端返回 second_verify），不能提示"已重置"。
    final detail = mapOf(result['detail']);
    final needsSecondVerify =
        result['second_verify_required'] == true ||
        detail['second_verify_required'] == true ||
        (result['second_verify'] is List &&
            (result['second_verify'] as List).isNotEmpty);
    if (needsSecondVerify) {
      AppFeedback.toast(context, '已提交，需在服务商控制台完成二次验证后密码才生效');
      return;
    }
    AppFeedback.success(context, '密码已重置');
  }

  // ── 续费 ─────────────────────────────────────────────────────────────

  Future<void> _openRenew() async {
    if (_actionBusy) return;
    final services = AppServices.of(context);
    final loading = AppFeedback.loading(context, '正在获取续费信息…');
    Map<String, dynamic> preview;
    try {
      preview = await services.services.renewPreview(widget.serviceId);
    } catch (error) {
      AppFeedback.dismissLoading(loading);
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
      return;
    }
    AppFeedback.dismissLoading(loading);
    if (!mounted) return;

    final cycles = _renewCycles(preview);
    if (cycles.isEmpty) {
      AppFeedback.toast(context, '暂无可续费周期');
      return;
    }

    final selected = await TDialog.show<Map<String, dynamic>>(
      context,
      dialog: TDialog(
        title: const Text('选择续费周期'),
        content: SizedBox(
          width: double.infinity,
          height: math.min(cycles.length * 56.0 + 8, 360),
          child: ListView.separated(
            padding: EdgeInsets.zero,
            itemCount: cycles.length,
            separatorBuilder: (_, __) =>
                Container(height: 0.5, color: context.tTheme.borderLevel1Color),
            itemBuilder: (context, index) {
              final cycle = cycles[index];
              final label = strOf(cycle, 'billing_cycle_label').isNotEmpty
                  ? strOf(cycle, 'billing_cycle_label')
                  : strOf(cycle, 'billing_cycle');
              final amount =
                  cycle['amount'] ??
                  cycle['effective_amount'] ??
                  cycle['base_amount'];
              return InkWell(
                onTap: () =>
                    Navigator.of(context, rootNavigator: true).pop(cycle),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 4,
                    vertical: 12,
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          label,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                      Text(
                        formatMoneyText(amount),
                        style: TextStyle(
                          fontSize: 14,
                          color: context.tTheme.textColorPrimary,
                        ),
                      ),
                      const SizedBox(width: 4),
                      Icon(
                        TIcons.chevron_right,
                        size: 18,
                        color: context.tTheme.textColorSecondary,
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
        actions: const [TDialogAction(child: Text('取消'))],
      ),
    );
    if (selected == null || !mounted) return;

    final billingCycle = strOf(selected, 'billing_cycle');
    if (billingCycle.isEmpty) return;

    final loading2 = AppFeedback.loading(context, '正在生成续费账单…');
    try {
      await services.services.createRenewOrder(
        widget.serviceId,
        billingCycle: billingCycle,
      );
    } catch (error) {
      AppFeedback.dismissLoading(loading2);
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
      return;
    }
    AppFeedback.dismissLoading(loading2);
    if (!mounted) return;

    final goPay = await TDialog.show<bool>(
      context,
      dialog: TDialog(
        title: const Text('续费账单已生成'),
        content: const Text('可前往财务中心完成支付'),
        actions: const [
          TDialogAction(child: Text('稍后'), result: false),
          TDialogAction(
            child: Text('去支付'),
            result: true,
            role: TDialogActionRole.primary,
          ),
        ],
      ),
    );
    if (goPay == true && mounted) {
      unawaited(context.push('/finance'));
    }
  }

  List<Map<String, dynamic>> _renewCycles(Map<String, dynamic> preview) {
    for (final key in ['cycles', 'billing_cycles', 'list']) {
      final list = listOf(preview[key]);
      if (list.isNotEmpty) {
        return list
            .where((cycle) => strOf(cycle, 'billing_cycle').isNotEmpty)
            .toList();
      }
    }
    return const [];
  }

  // ── 自动续费 / 名称 / 备注 ────────────────────────────────────────────

  Future<void> _toggleAutoRenew(bool enabled) async {
    final detail = _detail;
    if (detail == null) return;
    final services = AppServices.of(context);
    setState(() => _autoRenewBusy = true);
    try {
      await services.services.updateAutoRenew(widget.serviceId, enabled);
      if (!mounted) return;
      setState(() => detail['auto_renew'] = enabled ? 1 : 0);
      AppFeedback.success(context, enabled ? '已开启自动续费' : '已关闭自动续费');
    } catch (error) {
      if (!mounted) return;
      AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _autoRenewBusy = false);
    }
  }

  Future<void> _rename() async {
    final detail = _detail;
    if (detail == null) return;
    final name = await _promptText(
      title: '修改服务名称',
      initial: strOf(detail, 'name'),
      hint: '请输入服务名称',
    );
    if (name == null || !mounted) return;
    if (name.isEmpty) {
      AppFeedback.error(context, '服务名称不能为空');
      return;
    }
    final services = AppServices.of(context);
    final loading = AppFeedback.loading(context, '正在保存…');
    try {
      await services.services.updateName(widget.serviceId, name);
    } catch (error) {
      AppFeedback.dismissLoading(loading);
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
      return;
    }
    AppFeedback.dismissLoading(loading);
    if (!mounted) return;
    setState(() => detail['name'] = name);
    AppFeedback.success(context, '名称已更新');
  }

  Future<void> _editRemark() async {
    final detail = _detail;
    if (detail == null) return;
    final remark = await _promptText(
      title: '修改备注',
      initial: strOf(detail, 'remark'),
      hint: '请输入备注',
      maxLines: 3,
    );
    if (remark == null || !mounted) return;
    final services = AppServices.of(context);
    final loading = AppFeedback.loading(context, '正在保存…');
    try {
      await services.services.updateRemark(widget.serviceId, remark);
    } catch (error) {
      AppFeedback.dismissLoading(loading);
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
      return;
    }
    AppFeedback.dismissLoading(loading);
    if (!mounted) return;
    setState(() => detail['remark'] = remark);
    AppFeedback.success(context, '备注已更新');
  }

  Future<String?> _promptText({
    required String title,
    required String initial,
    String? hint,
    int maxLines = 1,
  }) async {
    final controller = TextEditingController(text: initial);
    final confirmed = await TDialog.show<bool>(
      context,
      dialog: TDialog(
        title: Text(title),
        content: TInput(
          controller: controller,
          hintText: hint,
          maxLines: maxLines,
        ),
        actions: const [
          TDialogAction(child: Text('取消'), result: false),
          TDialogAction(
            child: Text('确定'),
            result: true,
            role: TDialogActionRole.primary,
          ),
        ],
      ),
    );
    final text = controller.text.trim();
    controller.dispose();
    return confirmed == true ? text : null;
  }

  // ── 监控页签 ─────────────────────────────────────────────────────────

  Future<void> _loadMonitor() async {
    final seq = ++_monitorSeq;
    setState(() {
      _monitorLoading = true;
      _monitorError = null;
    });
    try {
      final services = AppServices.of(context);
      final data = await services.services.monitor(
        widget.serviceId,
        range: _monitorRange,
        type: _monitorType,
      );
      if (!mounted || seq != _monitorSeq) return;
      final options = listOf(data['options']);
      final selectedType = strOf(data, 'selected_type').trim();
      setState(() {
        _monitorData = data;
        _monitorLoading = false;
        if (options.isNotEmpty) _monitorOptions = options;
        if ((_monitorType ?? '').isEmpty && selectedType.isNotEmpty) {
          _monitorType = selectedType;
        }
      });
    } catch (error) {
      if (!mounted || seq != _monitorSeq) return;
      setState(() {
        _monitorLoading = false;
        _monitorError = AppServices.errorText(error);
      });
    }
  }

  List<Widget> _buildMonitorSlivers() {
    return [
      SliverPadding(
        padding: EdgeInsets.fromLTRB(
          _sliverGutter,
          AppSpacing.gapM,
          _sliverGutter,
          0,
        ),
        sliver: SliverList.list(
          children: [
            _buildMonitorControlsCard(),
            const SizedBox(height: AppSpacing.gapM),
            // TDesign 无图表组件，CustomPaint 保留；外层用白底 + 1px 描边卡片。
            AppCard(
              child: DataState(
                loading: _monitorLoading && _monitorData == null,
                error: _monitorError,
                retry: _loadMonitor,
                child: _buildMonitorChartContent(),
              ),
            ),
          ],
        ),
      ),
    ];
  }

  Widget _buildMonitorControlsCard() {
    final theme = context.tTheme;
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('统计区间', style: AppType.cardTitle(theme)),
          const SizedBox(height: AppSpacing.gapS),
          Wrap(
            spacing: AppSpacing.gapS,
            runSpacing: AppSpacing.gapS,
            children: [
              for (final item in const [
                ('3h', '近 3 小时'),
                ('24h', '近 24 小时'),
                ('7d', '近 7 天'),
                ('30d', '近 30 天'),
              ])
                AppChip(
                  label: item.$2,
                  selected: item.$1 == _monitorRange,
                  onTap: () {
                    if (item.$1 == _monitorRange) return;
                    setState(() => _monitorRange = item.$1);
                    _loadMonitor();
                  },
                ),
            ],
          ),
          if (_monitorOptions.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.gapL),
            Text('监控指标', style: AppType.cardTitle(theme)),
            const SizedBox(height: AppSpacing.gapS),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (var i = 0; i < _monitorOptions.length; i++) ...[
                    if (i > 0) const SizedBox(width: AppSpacing.gapS),
                    AppChip(
                      label: strOf(_monitorOptions[i], 'label'),
                      selected:
                          strOf(_monitorOptions[i], 'value') ==
                          (_monitorType ?? ''),
                      onTap: () {
                        final value = strOf(_monitorOptions[i], 'value');
                        if (value.isEmpty || value == _monitorType) return;
                        setState(() => _monitorType = value);
                        _loadMonitor();
                      },
                    ),
                  ],
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildMonitorChartContent() {
    final data = _monitorData;
    if (data == null) return const SizedBox.shrink();
    if (data['supported'] != true) {
      final message = strOf(data, 'message');
      return _monitorEmptyBox(message.isEmpty ? '当前服务暂不支持监控图表' : message);
    }
    final chart = mapOf(data['chart']);
    final points = _monitorPoints(chart);
    if (points.isEmpty) {
      final message = strOf(data, 'message');
      return _monitorEmptyBox(message.isEmpty ? '当前时间范围内暂无监控数据' : message);
    }
    final values = <double>[];
    for (final point in points) {
      final value = point['value'];
      if (value is num) {
        values.add(value.toDouble());
      } else {
        final parsed = double.tryParse('${value ?? ''}');
        if (parsed != null) values.add(parsed);
      }
    }
    if (values.isEmpty) {
      return _monitorEmptyBox('当前时间范围内暂无监控数据');
    }

    final theme = context.tTheme;
    final unit = strOf(chart, 'unit');
    final summary = mapOf(data['summary']);
    final summaryLatest = mapOf(summary['latest']);
    final selectedLabel = strOf(data, 'selected_label');
    final startTime = strOf(points.first, 'time');
    final endTime = strOf(points.last, 'time');
    // 最新值：优先用后端 summary.latest.text（已按单位格式化），否则取末点数值。
    final latestText = strOf(summaryLatest, 'text').trim();
    final latestDisplay = latestText.isNotEmpty
        ? latestText
        : _formatMonitorValue(values.last, unit);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                selectedLabel.isEmpty ? '监控图表' : selectedLabel,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppType.cardTitle(theme),
              ),
            ),
            if (unit.isNotEmpty)
              Text('单位 $unit', style: AppType.caption(theme)),
          ],
        ),
        const SizedBox(height: AppSpacing.gapM),
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(latestDisplay, style: AppType.stat(theme, size: 30)),
            const SizedBox(width: AppSpacing.gapXs),
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Text('最新', style: AppType.caption(theme)),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.gapS),
        SizedBox(
          // 平板视野更宽，图表给足高度；手机维持紧凑。
          height: context.isTablet ? 260 : 180,
          width: double.infinity,
          child: CustomPaint(
            painter: _MonitorChartPainter(
              values: values,
              lineColor: theme.brandColor,
              gridColor: theme.borderLevel1Color,
            ),
          ),
        ),
        if (startTime.isNotEmpty || endTime.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.gapXs),
          Row(
            children: [
              Text(startTime, style: AppType.caption(theme)),
              const Spacer(),
              Text(endTime, style: AppType.caption(theme)),
            ],
          ),
        ],
        if (summary.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.gapM),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _summaryTile('最新', mapOf(summary['latest']), theme),
              _summaryTile('平均', mapOf(summary['average']), theme),
              _summaryTile('峰值', mapOf(summary['peak']), theme),
              _summaryTile('最低', mapOf(summary['lowest']), theme),
            ],
          ),
        ],
      ],
    );
  }

  List<Map<String, dynamic>> _monitorPoints(Map<String, dynamic> chart) {
    final list = listOf(chart['list']);
    if (list.isNotEmpty) return list;
    final series = listOf(chart['series']);
    if (series.isNotEmpty) return listOf(series.first['list']);
    return const [];
  }

  Widget _summaryTile(
    String label,
    Map<String, dynamic> item,
    TThemeData theme,
  ) {
    final text = strOf(item, 'text');
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: AppType.caption(theme)),
          const SizedBox(height: 2),
          Text(
            text.isEmpty ? '--' : text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppType.money(theme, size: 13),
          ),
        ],
      ),
    );
  }

  /// 无 summary.text 时的兜底格式化，与后端 formatMonitorValue 口径一致。
  String _formatMonitorValue(double value, String unit) {
    String number;
    if (value == value.roundToDouble()) {
      number = value.toStringAsFixed(0);
    } else {
      final text = value.toStringAsFixed(2);
      number = text.replaceFirst(RegExp(r'\.?0+$'), '');
    }
    if (unit.isEmpty) return number;
    return unit == '%' || unit == 'ms' ? '$number$unit' : '$number $unit';
  }

  Widget _monitorEmptyBox(String message) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 24),
      child: TEmpty(emptyText: message),
    );
  }

  // ── 操作日志页签 ─────────────────────────────────────────────────────

  Future<void> _loadLogs({required int page}) async {
    final seq = ++_logSeq;
    setState(() {
      _logLoading = true;
      _logError = null;
      _logPage = page;
    });
    try {
      final services = AppServices.of(context);
      final data = await services.services.operationLogs(
        widget.serviceId,
        page: page,
        pageSize: _logPageSize,
      );
      if (!mounted || seq != _logSeq) return;
      setState(() {
        _logs = listOf(data['list']);
        _logTotal = intOf(data, 'total');
        _logLoading = false;
      });
    } catch (error) {
      if (!mounted || seq != _logSeq) return;
      setState(() {
        _logLoading = false;
        _logError = AppServices.errorText(error);
      });
    }
  }

  List<Widget> _buildLogSlivers() {
    final pageCount = _logTotal <= 0
        ? 1
        : (_logTotal + _logPageSize - 1) ~/ _logPageSize;

    if (_logLoading && _logs.isEmpty) {
      return const [
        SliverFillRemaining(
          hasScrollBody: false,
          child: DataState(loading: true, child: SizedBox.shrink()),
        ),
      ];
    }
    if (_logError != null && _logs.isEmpty) {
      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: DataState(
            loading: false,
            error: _logError,
            retry: () => _loadLogs(page: _logPage),
            child: const SizedBox.shrink(),
          ),
        ),
      ];
    }
    if (_logs.isEmpty) {
      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: DataState(
            loading: false,
            empty: true,
            emptyText: '暂无操作日志',
            emptyAction: TButton(
              size: TButtonSize.small,
              variant: TButtonVariant.outline,
              onPressed: () => _loadLogs(page: 1),
              child: const Text('刷新'),
            ),
            child: const SizedBox.shrink(),
          ),
        ),
      ];
    }

    return [
      SliverPadding(
        padding: EdgeInsets.fromLTRB(
          _sliverGutter,
          AppSpacing.gapM,
          _sliverGutter,
          0,
        ),
        sliver: SliverToBoxAdapter(child: _buildLogTable()),
      ),
      if (pageCount > 1) SliverToBoxAdapter(child: _buildLogPager(pageCount)),
    ];
  }

  /// 操作日志表格：时间 / 操作 / 摘要。
  Widget _buildLogTable() {
    return TTable<Map<String, dynamic>>(
      columns: [
        TTableColumn<Map<String, dynamic>>(
          id: 'created_at',
          header: const Text('时间'),
          minWidth: 168,
          cellBuilder: (_, log, __) => Text(formatDateTime(log['created_at'])),
        ),
        TTableColumn<Map<String, dynamic>>(
          id: 'action',
          header: const Text('操作'),
          minWidth: 96,
          cellBuilder: (_, log, __) {
            final label = strOf(log, 'action_label').trim();
            return Text(label.isEmpty ? '--' : label);
          },
        ),
        TTableColumn<Map<String, dynamic>>(
          id: 'summary',
          header: const Text('摘要'),
          minWidth: 120,
          cellBuilder: (_, log, __) {
            final summary = strOf(log, 'summary').trim();
            return Text(summary.isEmpty ? '--' : summary);
          },
        ),
      ],
      data: _logs,
      bordered: true,
      stripe: true,
    );
  }

  Widget _buildLogPager(int pageCount) {
    final theme = context.tTheme;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        _sliverGutter,
        AppSpacing.gapM,
        _sliverGutter,
        0,
      ),
      child: Row(
        children: [
          TButton(
            size: TButtonSize.small,
            variant: TButtonVariant.text,
            onPressed: _logLoading || _logPage <= 1
                ? null
                : () => _loadLogs(page: _logPage - 1),
            child: const Text('上一页'),
          ),
          Expanded(
            child: Text(
              '第 $_logPage / $pageCount 页 · 共 $_logTotal 条',
              textAlign: TextAlign.center,
              style: AppType.caption(theme),
            ),
          ),
          TButton(
            size: TButtonSize.small,
            variant: TButtonVariant.text,
            onPressed: _logLoading || _logPage >= pageCount
                ? null
                : () => _loadLogs(page: _logPage + 1),
            child: const Text('下一页'),
          ),
        ],
      ),
    );
  }

  // ── 账单页签 ─────────────────────────────────────────────────────────

  List<Widget> _buildBillingSlivers(Map<String, dynamic> detail) {
    final invoice = mapOf(detail['invoice']);
    final invoiceId = intOf(invoice, 'id');
    final invoiceNo = strOf(invoice, 'invoice_no').trim();

    if (invoiceId <= 0 && invoiceNo.isEmpty) {
      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: DataState(
            loading: false,
            empty: true,
            emptyText: '暂无账单信息',
            emptyAction: TButton(
              size: TButtonSize.small,
              variant: TButtonVariant.outline,
              onPressed: () => context.push('/finance'),
              child: const Text('查看全部账单'),
            ),
            child: const SizedBox.shrink(),
          ),
        ),
      ];
    }

    void openInvoice() {
      if (invoiceId > 0) {
        unawaited(context.push('/finance/invoices/$invoiceId'));
      }
    }

    return [
      SliverPadding(
        padding: EdgeInsets.fromLTRB(
          _sliverGutter,
          AppSpacing.gapM,
          _sliverGutter,
          0,
        ),
        sliver: SliverToBoxAdapter(
          child: TCellGroup(
            cells: [
              _buildInvoiceCard(
                detail,
                invoice,
                invoiceId > 0 ? openInvoice : null,
              ),
            ],
          ),
        ),
      ),
    ];
  }

  /// 服务账单卡：与「我的 > 账单与订单」的账单行同构——
  /// 标题为账单编号，副标题为商品与「订单号 · 支付时间」，
  /// 右侧为金额、账期与状态标签。
  TCell _buildInvoiceCard(
    Map<String, dynamic> detail,
    Map<String, dynamic> invoice,
    VoidCallback? onOpen,
  ) {
    final theme = context.tTheme;
    final status = intOf(invoice, 'status');
    final invoiceNo = strOf(invoice, 'invoice_no').trim();
    final orderNo = strOf(invoice, 'order_no').trim();
    final productName = strOf(detail, 'combined_display_name').trim();
    final cycleLabel = strOf(detail, 'billing_cycle_label').trim();
    final meta = [
      if (orderNo.isNotEmpty) '订单 $orderNo',
      '支付 ${formatDateTime(invoice['paid_at'])}',
    ].join(' · ');

    return TCell(
      title: Text(
        invoiceNo.isEmpty ? '--' : invoiceNo,
        style: AppType.cardTitle(
          theme,
        ).copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (productName.isNotEmpty) ...[
            Text(
              productName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppType.body(theme),
            ),
            const SizedBox(height: AppSpacing.gapXs),
          ],
          Text(
            meta,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppType.caption(theme),
          ),
        ],
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                formatMoneyText(detail['amount']),
                style: AppType.money(theme),
              ),
              if (cycleLabel.isNotEmpty)
                Text(cycleLabel, style: AppType.caption(theme)),
            ],
          ),
          const SizedBox(width: AppSpacing.gapS),
          AppStatusTag(
            label: _invoiceStatusLabel(status),
            preset: _invoiceStatusPreset(status),
          ),
        ],
      ),
      onTap: onOpen,
    );
  }

  /// 后端不带 `invoice.status_label`，按 InvoiceStatus 常量在前端映射。
  String _invoiceStatusLabel(int status) {
    return switch (status) {
      1 => '已支付',
      4 => '已取消',
      5 => '已退款',
      _ => '待支付',
    };
  }

  TTagColorPreset _invoiceStatusPreset(int status) {
    return switch (status) {
      1 => TTagColorPreset.success,
      4 => TTagColorPreset.defaultTheme,
      5 => TTagColorPreset.danger,
      _ => TTagColorPreset.warning,
    };
  }

  // ── 工具 ─────────────────────────────────────────────────────────────

  String _percentText(double percent) {
    final rounded = percent.roundToDouble();
    final text = (percent - rounded).abs() < 0.05
        ? rounded.toStringAsFixed(0)
        : percent.toStringAsFixed(1);
    return '$text%';
  }

  TProgressStatus _trafficStatus(
    double progress,
    Map<String, dynamic> traffic,
  ) {
    // limited 的语义是"存在流量上限"（后端 limit>0），不是"已超限"；
    // 只有用量比例接近/超过上限才标红。
    if (progress >= 0.9) {
      return TProgressStatus.error;
    }
    if (progress >= 0.75) return TProgressStatus.warning;
    return TProgressStatus.normal;
  }

  TTagColorPreset _tonePreset(String tone) {
    return switch (tone) {
      'success' => TTagColorPreset.success,
      'warning' => TTagColorPreset.warning,
      'danger' => TTagColorPreset.danger,
      'info' => TTagColorPreset.primary,
      _ => TTagColorPreset.defaultTheme,
    };
  }
}

/// 吸顶页签：滚动时保持页签可见，底色与页面一致避免内容透出。
class _PinnedTabsDelegate extends SliverPersistentHeaderDelegate {
  _PinnedTabsDelegate({required this.height, required this.child});

  final double height;
  final Widget child;

  @override
  double get minExtent => height;

  @override
  double get maxExtent => height;

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    return Container(
      color: context.tTheme.bgColorPage,
      alignment: Alignment.bottomCenter,
      child: child,
    );
  }

  @override
  bool shouldRebuild(covariant _PinnedTabsDelegate oldDelegate) {
    return oldDelegate.height != height || oldDelegate.child != child;
  }
}

/// 自绘折线图：网格 + 面积渐变 + 折线（不引入图表库）。
class _MonitorChartPainter extends CustomPainter {
  _MonitorChartPainter({
    required this.values,
    required this.lineColor,
    required this.gridColor,
  });

  final List<double> values;
  final Color lineColor;
  final Color gridColor;

  @override
  void paint(Canvas canvas, Size size) {
    if (values.length < 2 || size.width <= 0 || size.height <= 0) return;

    final gridPaint = Paint()
      ..color = gridColor
      ..strokeWidth = 0.5;
    for (var i = 0; i <= 4; i++) {
      final y = size.height * i / 4;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), gridPaint);
    }
    for (var i = 0; i <= 3; i++) {
      final x = size.width * i / 3;
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), gridPaint);
    }

    var minValue = values.reduce(math.min);
    var maxValue = values.reduce(math.max);
    final span = maxValue - minValue;
    if (span < 1e-9) {
      maxValue = minValue + 1;
    } else {
      minValue -= span * 0.1;
      maxValue += span * 0.1;
    }
    final range = maxValue - minValue;

    Offset pointAt(int index) {
      final x = size.width * index / (values.length - 1);
      final ratio = (values[index] - minValue) / range;
      final y = (size.height - ratio * size.height)
          .clamp(0.0, size.height)
          .toDouble();
      return Offset(x, y);
    }

    final linePath = Path();
    for (var i = 0; i < values.length; i++) {
      final point = pointAt(i);
      if (i == 0) {
        linePath.moveTo(point.dx, point.dy);
      } else {
        linePath.lineTo(point.dx, point.dy);
      }
    }

    final areaPath = Path.from(linePath)
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    final areaPaint = Paint()
      ..shader = LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [
          lineColor.withValues(alpha: 0.26),
          lineColor.withValues(alpha: 0.02),
        ],
      ).createShader(Offset.zero & size);
    canvas.drawPath(areaPath, areaPaint);

    final linePaint = Paint()
      ..color = lineColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    canvas.drawPath(linePath, linePaint);
  }

  @override
  bool shouldRepaint(covariant _MonitorChartPainter oldDelegate) {
    return oldDelegate.values != values ||
        oldDelegate.values.length != values.length ||
        oldDelegate.lineColor != lineColor ||
        oldDelegate.gridColor != gridColor;
  }
}
