import 'dart:async';

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

/// 账单与订单：余额一行 + 4 个分段页签（账单 / 订单 / 支付记录 / 余额流水）。
///
/// - 顶部页签为 TDesign `TTabBar`（[AppSegmentedTabs]），不是底部 TTabBar。
/// - 列表全部使用 TDesign `TCellGroup` + `TCell`：标题为单号、副标题为商品与
///   时间、右侧为金额与状态标签；余额行同样是单个 `TCell`。
/// - 每个页签独立保存列表数据、分页游标、筛选与滚动位置：切回页签不重新拉取，
///   只有下拉刷新、切换筛选或从详情返回时才刷新当前页签。
/// - 账单 / 订单支持「全部 / 待支付 / 已支付 / 已取消」状态筛选，直接透传后端 status。
/// - 每页 15 条、触底加载、`seq` 竞态防护与 [DataState] 三态保留；
///   空态使用 TDesign `TEmpty`。
/// - 财务记录页禁止统计/指标卡片（DESIGN.md 红线），余额只作为账户信息收敛成一行。
class FinancePage extends StatefulWidget {
  const FinancePage({super.key});

  @override
  State<FinancePage> createState() => _FinancePageState();
}

/// 单个页签的列表状态：数据、分页、筛选与滚动控制器都按页签隔离。
class _FinanceTabState {
  _FinanceTabState(this.index) : controller = ScrollController();

  final int index;
  final ScrollController controller;
  final List<Map<String, dynamic>> items = [];

  int page = 1;
  int filter = 0;
  int seq = 0;

  /// 是否已经拉取过首屏（空结果也算，切回时不再请求）。
  bool started = false;
  bool loading = false;
  bool loadingMore = false;
  bool hasMore = false;
  String? error;

  void dispose() => controller.dispose();
}

class _FinancePageState extends State<FinancePage> {
  static const int _pageSize = 15;

  static const List<String> _tabs = ['账单', '订单', '支付记录', '余额流水'];

  static const List<String> _emptyTexts = ['暂无账单', '暂无订单', '暂无支付记录', '暂无流水'];

  /// 账单 / 订单的状态筛选：`(标签, 后端 status)`，null 表示不传。
  /// 支付记录与余额流水没有「待处理 / 已完成」的产品口径，不渲染筛选条。
  static const List<List<(String, String?)>> _statusFilters = [
    [('全部', null), ('待支付', '0'), ('已支付', '1'), ('已取消', '4')],
    [('全部', null), ('待支付', '0'), ('已支付', '1'), ('已取消', '4')],
  ];

  final List<_FinanceTabState> _tabStates = [];
  int _tab = 0;

  @override
  void initState() {
    super.initState();
    for (var i = 0; i < _tabs.length; i++) {
      final state = _FinanceTabState(i);
      state.controller.addListener(() => _onScroll(state));
      _tabStates.add(state);
    }
    unawaited(_fetchFirst(_tabStates[_tab], showLoading: true));
  }

  @override
  void dispose() {
    for (final state in _tabStates) {
      state.dispose();
    }
    super.dispose();
  }

  void _onScroll(_FinanceTabState state) {
    if (state.index != _tab || !state.controller.hasClients) return;
    final position = state.controller.position;
    if (position.pixels >= position.maxScrollExtent - 160) {
      unawaited(_loadMore(state));
    }
  }

  String? _statusParam(_FinanceTabState state) {
    if (state.index > 1 || state.filter == 0) return null;
    return _statusFilters[state.index][state.filter].$2;
  }

  Future<Map<String, dynamic>> _fetch(_FinanceTabState state, int page) {
    final api = AppServices.of(context).finance;
    final status = _statusParam(state);
    return switch (state.index) {
      0 => api.invoices(page: page, pageSize: _pageSize, status: status),
      1 => api.orders(page: page, pageSize: _pageSize, status: status),
      2 => api.payments(page: page, pageSize: _pageSize),
      _ => api.balanceLogs(page: page, pageSize: _pageSize),
    };
  }

  Future<void> _fetchFirst(
    _FinanceTabState state, {
    required bool showLoading,
  }) async {
    final seq = ++state.seq;
    if (showLoading) {
      setState(() {
        state.loading = true;
        state.error = null;
      });
    }
    try {
      final data = await _fetch(state, 1);
      if (!mounted || seq != state.seq) return;
      final list = listOf(data['list']);
      final total = intOf(data, 'total');
      setState(() {
        state.items
          ..clear()
          ..addAll(list);
        state.page = 1;
        state.hasMore = total > 0
            ? state.items.length < total
            : list.length >= _pageSize;
        state.started = true;
        state.loading = false;
        state.loadingMore = false;
        state.error = null;
      });
    } catch (error) {
      if (!mounted || seq != state.seq) return;
      final message = AppServices.errorText(error);
      final hadData = state.items.isNotEmpty;
      setState(() {
        state.started = true;
        state.loading = false;
        state.loadingMore = false;
        if (!hadData) state.error = message;
      });
      if (hadData) AppFeedback.error(context, message);
    }
  }

  Future<void> _loadMore(_FinanceTabState state) async {
    if (state.loading || state.loadingMore || !state.hasMore) return;
    final seq = state.seq;
    setState(() => state.loadingMore = true);
    try {
      final data = await _fetch(state, state.page + 1);
      if (!mounted || seq != state.seq) return;
      final list = listOf(data['list']);
      final total = intOf(data, 'total');
      setState(() {
        state.page += 1;
        state.items.addAll(list);
        state.hasMore = total > 0
            ? state.items.length < total
            : list.length >= _pageSize;
        state.loadingMore = false;
      });
    } catch (error) {
      if (!mounted || seq != state.seq) return;
      setState(() => state.loadingMore = false);
      AppFeedback.error(context, AppServices.errorText(error));
    }
  }

  void _switchTab(int index) {
    if (index == _tab) return;
    final state = _tabStates[index];
    setState(() => _tab = index);
    if (!state.started) {
      unawaited(_fetchFirst(state, showLoading: true));
    }
  }

  void _changeFilter(_FinanceTabState state, int index) {
    if (state.filter == index) return;
    setState(() {
      state.filter = index;
      state.items.clear();
      state.page = 1;
      state.hasMore = false;
      state.started = false;
      state.error = null;
    });
    if (state.controller.hasClients) state.controller.jumpTo(0);
    unawaited(_fetchFirst(state, showLoading: true));
  }

  Future<void> _openDetail(String path) async {
    await context.push<void>(path);
    if (!mounted) return;
    await _fetchFirst(_tabStates[_tab], showLoading: false);
  }

  Future<void> _openRecharge() async {
    await context.push<void>('/finance/recharge');
    if (!mounted) return;
    // 充值可能带来新的支付记录与流水，返回时静默刷新当前页签。
    await _fetchFirst(_tabStates[_tab], showLoading: false);
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final padding = context.pagePadding;
    return AppScaffold(
      title: '账单与订单',
      body: Column(
        children: [
          Padding(
            padding: EdgeInsets.fromLTRB(padding, AppSpacing.gapS, padding, 0),
            child: _buildBalanceRow(context, session),
          ),
          Padding(
            padding: EdgeInsets.symmetric(horizontal: padding),
            child: AppSegmentedTabs(
              labels: _tabs,
              current: _tab,
              onChanged: _switchTab,
            ),
          ),
          _buildFilterBar(context, _tabStates[_tab]),
          Expanded(child: _buildBody(context, _tabStates[_tab])),
        ],
      ),
    );
  }

  /// 余额是账户信息，收敛成一行：TDesign `TCell` 展示余额并进入充值。
  Widget _buildBalanceRow(BuildContext context, Session session) {
    final theme = context.tTheme;
    return TCellGroup(
      cells: [
        TCell(
          prefix: Icon(
            TIcons.wallet,
            size: 22,
            color: theme.textColorSecondary,
          ),
          title: const Text('账户余额'),
          note: Text(
            formatMoneyText(session.balance),
            style: AppType.money(theme, size: 17),
          ),
          trailing: Text(
            '充值',
            style: AppType.secondary(theme).copyWith(color: theme.brandColor),
          ),
          arrow: true,
          onTap: _openRecharge,
        ),
      ],
    );
  }

  /// 状态筛选胶囊：只有账单 / 订单有后端 status 维度，其余页签不占位。
  Widget _buildFilterBar(BuildContext context, _FinanceTabState state) {
    if (state.index > 1) return const SizedBox.shrink();
    final options = _statusFilters[state.index];
    final padding = context.pagePadding;
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: EdgeInsets.fromLTRB(
        padding,
        AppSpacing.gapS,
        padding,
        AppSpacing.gapXs,
      ),
      child: Row(
        children: [
          for (var i = 0; i < options.length; i++) ...[
            if (i > 0) const SizedBox(width: AppSpacing.gapS),
            AppChip(
              label: options[i].$1,
              selected: state.filter == i,
              onTap: () => _changeFilter(state, i),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildBody(BuildContext context, _FinanceTabState state) {
    final theme = context.tTheme;
    final padding = context.pagePadding;
    // 财务记录行 trailing 同时有金额与状态标签，比工单行更宽：
    // 两栏仅在横屏平板（>= 840）启用，600–839 仍保持单栏避免单号被挤压。
    final twoColumn = context.isExpanded;
    return RefreshList(
      onRefresh: () => _fetchFirst(state, showLoading: false),
      child: CustomScrollView(
        // 每个页签独立的 PageStorageKey：切回时恢复各自的滚动位置。
        key: PageStorageKey<String>('finance-tab-${state.index}'),
        controller: state.controller,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          if (state.loading && state.items.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: DataState(loading: true, child: SizedBox.shrink()),
            )
          else if (state.error != null && state.items.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: DataState(
                loading: false,
                error: state.error,
                retry: () => unawaited(_fetchFirst(state, showLoading: true)),
                child: const SizedBox.shrink(),
              ),
            )
          else if (state.items.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: Padding(
                  padding: EdgeInsets.all(padding),
                  child: TEmpty(
                    emptyText: _emptyTexts[state.index],
                    // 空态按页签给出下一步：账单/订单去逛服务，支付记录与流水去充值。
                    operation: TButton(
                      size: TButtonSize.small,
                      colorPreset: TButtonColorPreset.primary,
                      onPressed: state.index <= 1
                          ? () => context.go('/services')
                          : _openRecharge,
                      child: Text(state.index <= 1 ? '查看服务' : '去充值'),
                    ),
                  ),
                ),
              ),
            )
          else ...[
            SliverPadding(
              padding: EdgeInsets.fromLTRB(
                padding,
                AppSpacing.gapM,
                padding,
                0,
              ),
              sliver: SliverToBoxAdapter(
                child: twoColumn
                    ? _buildTwoColumnList(context, state)
                    : TCellGroup(
                        cells: [
                          for (final item in state.items)
                            _buildRecordCell(context, state.index, item),
                        ],
                      ),
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapL),
                child: Center(
                  child: state.loadingMore
                      ? const TLoading(size: 20)
                      : Text(
                          state.hasMore ? '上拉加载更多' : '没有更多了',
                          style: AppType.caption(theme),
                        ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  /// 平板横屏两栏列表：左右各一个 `TCellGroup`，按奇偶交替分栏。
  /// 这样按行阅读即是原始时间顺序（左 1、右 2、左 3…），
  /// 触底追加的新数据只会出现在两列末尾，已有条目不会换列。
  Widget _buildTwoColumnList(BuildContext context, _FinanceTabState state) {
    final left = <Map<String, dynamic>>[];
    final right = <Map<String, dynamic>>[];
    for (var i = 0; i < state.items.length; i++) {
      (i.isEven ? left : right).add(state.items[i]);
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: TCellGroup(
            cells: [
              for (final item in left)
                _buildRecordCell(context, state.index, item),
            ],
          ),
        ),
        const SizedBox(width: AppSpacing.gapL),
        Expanded(
          child: right.isEmpty
              ? const SizedBox.shrink()
              : TCellGroup(
                  cells: [
                    for (final item in right)
                      _buildRecordCell(context, state.index, item),
                  ],
                ),
        ),
      ],
    );
  }

  TCell _buildRecordCell(
    BuildContext context,
    int tab,
    Map<String, dynamic> item,
  ) {
    return switch (tab) {
      0 => _buildInvoiceCell(context, item),
      1 => _buildOrderCell(context, item),
      2 => _buildPaymentCell(context, item),
      _ => _buildLedgerCell(context, item),
    };
  }

  /// 列表项骨架：TDesign `TCell`，标题单号、副标题商品与时间、
  /// 右侧金额（等宽数字）+ 金额性质 + 状态标签。
  TCell _buildRecordCellData(
    BuildContext context, {
    required String title,
    String description = '',
    String meta = '',
    required String amount,
    String? amountNote,
    String statusLabel = '',
    TTagColorPreset preset = TTagColorPreset.defaultTheme,
    Color? amountColor,
    VoidCallback? onTap,
  }) {
    final theme = context.tTheme;
    final hasDescription = description.trim().isNotEmpty;
    final hasMeta = meta.trim().isNotEmpty;
    return TCell(
      title: Text(
        title,
        style: AppType.cardTitle(
          theme,
        ).copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
      ),
      subtitle: !hasDescription && !hasMeta
          ? null
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (hasDescription)
                  Text(
                    description,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppType.body(theme),
                  ),
                if (hasDescription && hasMeta)
                  const SizedBox(height: AppSpacing.gapXs),
                if (hasMeta)
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
              Text(amount, style: AppType.money(theme, color: amountColor)),
              if (amountNote != null && amountNote.isNotEmpty)
                Text(amountNote, style: AppType.caption(theme)),
            ],
          ),
          if (statusLabel.trim().isNotEmpty) ...[
            const SizedBox(width: AppSpacing.gapS),
            AppStatusTag(label: statusLabel, preset: preset),
          ],
        ],
      ),
      onTap: onTap,
    );
  }

  /// 账单：invoice_no、状态、商品组合、创建时间、应付/实付金额。
  TCell _buildInvoiceCell(BuildContext context, Map<String, dynamic> item) {
    final status = intOf(item, 'status');
    return _buildRecordCellData(
      context,
      title: strOf(item, 'invoice_no'),
      description: strOf(item, 'combined_display_name'),
      meta: formatDateTime(item['created_at']),
      statusLabel: strOf(item, 'status_label'),
      preset: _invoicePreset(status),
      amount: formatMoneyText(
        // 金额按状态取：待支付=应付；已支付=实付；已取消/已退款=原账单金额，
        // 避免取消后显示 ¥0.00 让用户看不出取消了什么。
        switch (status) {
          0 => item['payable_amount'],
          1 || 2 => item['paid_amount'],
          _ => item['amount'],
        },
      ),
      amountNote: switch (status) {
        0 => '应付',
        1 || 2 => '实付',
        _ => '原价',
      },
      onTap: () => _openDetail('/finance/invoices/${intOf(item, 'id')}'),
    );
  }

  /// 订单：order_no、状态、商品 · 类型、创建时间、金额。
  TCell _buildOrderCell(BuildContext context, Map<String, dynamic> item) {
    final status = intOf(item, 'status');
    return _buildRecordCellData(
      context,
      title: strOf(item, 'order_no'),
      description: [
        strOf(item, 'product_name'),
        strOf(item, 'type_label'),
      ].where((text) => text.trim().isNotEmpty).join(' · '),
      meta: formatDateTime(item['created_at']),
      statusLabel: strOf(item, 'status_label'),
      preset: _orderPreset(status),
      amount: formatMoneyText(
        status == 1 ? item['paid_amount'] : item['amount'],
      ),
      amountNote: switch (status) {
        0 => '应付',
        1 => '实付',
        _ => '原价',
      },
      onTap: () => _openDetail('/finance/orders/${intOf(item, 'id')}'),
    );
  }

  /// 支付记录：payment_no、状态、渠道 · 支付时间、金额。
  TCell _buildPaymentCell(BuildContext context, Map<String, dynamic> item) {
    final status = intOf(item, 'status');
    final channel = strOf(item, 'channel_name').isNotEmpty
        ? strOf(item, 'channel_name')
        : strOf(item, 'gateway_label');
    return _buildRecordCellData(
      context,
      title: strOf(item, 'payment_no'),
      description: channel,
      meta: formatDateTime(item['paid_at']),
      statusLabel: strOf(item, 'status_label'),
      preset: _paymentPreset(status),
      amount: formatMoneyText(item['amount']),
      amountNote: switch (status) {
        0 => '应付',
        1 => '实付',
        _ => '原价',
      },
    );
  }

  /// 余额流水：事项名称、备注、时间与流水后余额，金额正绿负红。
  TCell _buildLedgerCell(BuildContext context, Map<String, dynamic> item) {
    final theme = context.tTheme;
    final change = double.tryParse(strOf(item, 'change_amount')) ?? 0;
    final positive = change >= 0;
    return _buildRecordCellData(
      context,
      title: strOf(item, 'event_type_label'),
      description: strOf(item, 'remark'),
      meta:
          '${formatDateTime(item['occurred_at'])} · 余额 ${formatMoneyText(item['balance_after'])}',
      amount: formatMoneyText(item['change_amount']),
      amountColor: positive ? theme.successColor : theme.errorColor,
      amountNote: positive ? '入账' : '扣款',
    );
  }

  /// 账单状态：0 待支付 / 1 已支付 / 2 部分已付 / 4 已取消 / 5 已退款。
  TTagColorPreset _invoicePreset(int status) {
    switch (status) {
      case 1:
        return TTagColorPreset.success;
      case 2:
        return TTagColorPreset.warning;
      case 4:
        return TTagColorPreset.defaultTheme;
      case 5:
        return TTagColorPreset.danger;
      default:
        return TTagColorPreset.warning;
    }
  }

  /// 订单状态：0 待支付 / 1 已支付 / 4 已取消 / 5 已退款。
  TTagColorPreset _orderPreset(int status) => _paymentPreset(status);

  /// 支付状态：0 待支付 / 1 已支付 / 4 已取消 / 5 已退款。
  TTagColorPreset _paymentPreset(int status) {
    switch (status) {
      case 1:
        return TTagColorPreset.success;
      case 4:
        return TTagColorPreset.defaultTheme;
      case 5:
        return TTagColorPreset.danger;
      default:
        return TTagColorPreset.warning;
    }
  }
}
