import 'dart:async';

import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../../core/layout/responsive.dart';
import '../../../core/net/app_services.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/util/format.dart';
import '../../../widgets/app_feedback.dart';
import '../../../widgets/app_kit.dart';
import '../../../widgets/data_state.dart';
import 'ticket_labels.dart';

/// 工单列表（底部 Tab 页，无返回键）。
///
/// - 列表使用 TDesign `TCellGroup` + `TCell`：主题为标题、
///   部门/优先级/时间为副标题、状态标签在右侧。
/// - 筛选：全部 / 进行中（ongoing）/ 已关闭（3），使用 [AppChip]。
/// - 列表支持下拉刷新与触底加载更多，三态交给 [DataState]，空态用 TDesign `TEmpty`。
class TicketsPage extends StatefulWidget {
  const TicketsPage({super.key});

  @override
  State<TicketsPage> createState() => _TicketsPageState();
}

class _TicketsPageState extends State<TicketsPage> {
  static const int _pageSize = 15;

  static const List<({String label, String? status})> _filters = [
    (label: '全部', status: null),
    (label: '进行中', status: 'ongoing'),
    (label: '已关闭', status: '3'),
  ];

  final ScrollController _scrollController = ScrollController();
  final List<Map<String, dynamic>> _tickets = [];

  int _filterIndex = 0;
  int _page = 1;
  int _seq = 0;
  bool _loading = true;
  bool _loadingMore = false;
  bool _hasMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    unawaited(_fetchFirst(showLoading: true));
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_scrollController.hasClients) return;
    final position = _scrollController.position;
    if (position.pixels >= position.maxScrollExtent - 160) {
      unawaited(_loadMore());
    }
  }

  Future<void> _fetchFirst({required bool showLoading}) async {
    final seq = ++_seq;
    final api = AppServices.of(context).tickets;
    if (showLoading) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final data = await api.list(
        page: 1,
        pageSize: _pageSize,
        status: _filters[_filterIndex].status,
      );
      if (!mounted || seq != _seq) return;
      final list = listOf(data['list']);
      final total = intOf(data, 'total');
      setState(() {
        _tickets
          ..clear()
          ..addAll(list);
        _page = 1;
        _hasMore = total > 0
            ? _tickets.length < total
            : list.length >= _pageSize;
        _loading = false;
        _loadingMore = false;
        _error = null;
      });
    } catch (error) {
      if (!mounted || seq != _seq) return;
      final message = AppServices.errorText(error);
      final hadData = _tickets.isNotEmpty;
      setState(() {
        _loading = false;
        _loadingMore = false;
        if (!hadData) _error = message;
      });
      if (hadData) AppFeedback.error(context, message);
    }
  }

  Future<void> _loadMore() async {
    if (_loading || _loadingMore || !_hasMore) return;
    final seq = _seq;
    final api = AppServices.of(context).tickets;
    setState(() => _loadingMore = true);
    try {
      final data = await api.list(
        page: _page + 1,
        pageSize: _pageSize,
        status: _filters[_filterIndex].status,
      );
      if (!mounted || seq != _seq) return;
      final list = listOf(data['list']);
      final total = intOf(data, 'total');
      setState(() {
        _page += 1;
        _tickets.addAll(list);
        _hasMore = total > 0
            ? _tickets.length < total
            : list.length >= _pageSize;
        _loadingMore = false;
      });
    } catch (error) {
      if (!mounted || seq != _seq) return;
      setState(() => _loadingMore = false);
      AppFeedback.error(context, AppServices.errorText(error));
    }
  }

  void _switchFilter(int index) {
    if (index == _filterIndex) return;
    setState(() {
      _filterIndex = index;
      _tickets.clear();
      _hasMore = false;
      _error = null;
    });
    unawaited(_fetchFirst(showLoading: true));
  }

  Future<void> _openCreate() async {
    final created = await context.push<bool>('/tickets/create');
    if (!mounted || created != true) return;
    await _fetchFirst(showLoading: false);
  }

  Future<void> _openDetail(int ticketId) async {
    await context.push<void>('/tickets/$ticketId');
    if (!mounted) return;
    await _fetchFirst(showLoading: false);
  }

  TTagColorPreset _statusPreset(int status) {
    switch (status) {
      case 0:
        return TTagColorPreset.primary;
      case 1:
        return TTagColorPreset.warning;
      case 2:
        return TTagColorPreset.success;
      default:
        return TTagColorPreset.defaultTheme;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    final padding = context.pagePadding;
    return AppScaffold(
      showBack: false,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: EdgeInsets.fromLTRB(
              padding,
              AppSpacing.gapM,
              padding,
              AppSpacing.gapS,
            ),
            child: Row(
              children: [
                Expanded(child: Text('工单', style: AppType.pageTitle(theme))),
                TButton(
                  size: TButtonSize.small,
                  colorPreset: TButtonColorPreset.primary,
                  icon: const Icon(TIcons.add, size: 16),
                  onPressed: _openCreate,
                  child: const Text('新建工单'),
                ),
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.symmetric(horizontal: padding),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (var i = 0; i < _filters.length; i++)
                    Padding(
                      padding: EdgeInsets.only(
                        right: i == _filters.length - 1 ? 0 : AppSpacing.gapS,
                      ),
                      child: AppChip(
                        label: _filters[i].label,
                        selected: i == _filterIndex,
                        onTap: () => _switchFilter(i),
                      ),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gapM),
          Expanded(child: _buildBody(context)),
        ],
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    final theme = context.tTheme;
    final padding = context.pagePadding;
    return RefreshList(
      onRefresh: () => _fetchFirst(showLoading: false),
      child: CustomScrollView(
        controller: _scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          if (_loading && _tickets.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: DataState(loading: true, child: SizedBox.shrink()),
            )
          else if (_error != null && _tickets.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: DataState(
                loading: false,
                error: _error,
                retry: () => unawaited(_fetchFirst(showLoading: true)),
                child: const SizedBox.shrink(),
              ),
            )
          else if (_tickets.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: Padding(
                  padding: EdgeInsets.all(padding),
                  child: TEmpty(
                    emptyText: '暂无工单',
                    operation: TButton(
                      size: TButtonSize.small,
                      colorPreset: TButtonColorPreset.primary,
                      onPressed: _openCreate,
                      child: const Text('新建工单'),
                    ),
                  ),
                ),
              ),
            )
          else ...[
            SliverPadding(
              padding: EdgeInsets.symmetric(horizontal: padding),
              sliver: SliverToBoxAdapter(
                // 少于 4 条不拆栏：两栏各一条会让右半边空出大片，
                // 单栏通栏读起来更顺。
                child: context.isTablet && _tickets.length >= 4
                    ? _buildTwoColumnList(context)
                    : TCellGroup(
                        cells: [
                          for (final ticket in _tickets)
                            _buildTicketCell(ticket),
                        ],
                      ),
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapL),
                child: Center(
                  child: _loadingMore
                      ? const TLoading(size: 20)
                      : Text(
                          _hasMore ? '上拉加载更多' : '没有更多了',
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
  Widget _buildTwoColumnList(BuildContext context) {
    final left = <Map<String, dynamic>>[];
    final right = <Map<String, dynamic>>[];
    for (var i = 0; i < _tickets.length; i++) {
      (i.isEven ? left : right).add(_tickets[i]);
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: TCellGroup(
            cells: [for (final ticket in left) _buildTicketCell(ticket)],
          ),
        ),
        const SizedBox(width: AppSpacing.gapL),
        Expanded(
          child: right.isEmpty
              ? const SizedBox.shrink()
              : TCellGroup(
                  cells: [for (final ticket in right) _buildTicketCell(ticket)],
                ),
        ),
      ],
    );
  }

  /// 工单行：主题为标题，部门 · 优先级 · 更新时间为副标题，右侧状态标签。
  TCell _buildTicketCell(Map<String, dynamic> ticket) {
    final theme = context.tTheme;
    final status = intOf(ticket, 'status');
    final subject = strOf(ticket, 'subject');
    final department = ticketDepartmentLabel(ticket);
    final priority = ticketPriorityLabel(ticket);
    final updatedAt = formatDateTime(strOf(ticket, 'updated_at'));
    return TCell(
      title: Text(
        subject.isEmpty ? '无主题工单' : subject,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: AppType.cardTitle(theme),
      ),
      subtitle: Text(
        [
          department,
          '优先级$priority',
          updatedAt,
        ].where((text) => text.trim().isNotEmpty).join(' · '),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: AppType.caption(theme),
      ),
      trailing: AppStatusTag(
        label: ticketStatusLabel(ticket),
        preset: _statusPreset(status),
      ),
      onTap: () => _openDetail(intOf(ticket, 'id')),
    );
  }
}
