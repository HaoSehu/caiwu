import 'dart:async';

import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/net/app_services.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/util/format.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import '../../widgets/data_state.dart';

/// 服务状态筛选项。
///
/// 后端 `status` 只接受单个整数：
/// - 「默认」不传 status，客户端排除「已删除」(3)，只留 0/1/2/4；
/// - 「已暂停/异常」用两个状态值在客户端合并口径；
/// - 「已删除」显式传 status=3，只在用户手动筛选时出现。
/// 请求时按原始分页逐页拉取后过滤，保证不漏数据，并限制填充页数避免无限翻页。
class _ServiceFilter {
  const _ServiceFilter(
    this.label, {
    this.serverStatus,
    this.clientStatuses,
    this.excludeStatuses = const [],
  });

  final String label;

  /// 下发给后端的单值 status；为 null 时不传。
  final int? serverStatus;

  /// 多个状态在客户端合并（后端只接受单值）。
  final List<int>? clientStatuses;

  /// 客户端排除的状态（「默认」视图排除已删除）。
  final List<int> excludeStatuses;
}

const int _statusDeleted = 3;

const _serviceFilters = <_ServiceFilter>[
  _ServiceFilter('默认', excludeStatuses: [_statusDeleted]),
  _ServiceFilter('运行中', serverStatus: 1),
  _ServiceFilter('开通中', serverStatus: 0),
  _ServiceFilter('已暂停/异常', clientStatuses: [2, 4]),
  _ServiceFilter('已删除', serverStatus: _statusDeleted),
];

/// 更多筛选条件：后端 `quick_filter` 单值。
const _quickFilters = <(String value, String label)>[
  ('expiring_7d', '7 天内到期'),
  ('auto_renew_enabled', '自动续费已开启'),
  ('auto_renew_disabled', '自动续费已关闭'),
];

/// 服务页（底部 Tab）：搜索、状态筛选、服务列表。
class ServicesPage extends StatefulWidget {
  const ServicesPage({super.key});

  @override
  State<ServicesPage> createState() => _ServicesPageState();
}

class _ServicesPageState extends State<ServicesPage> {
  static const _pageSize = 15;
  static const _fillTarget = 15;

  /// 单次填充最多翻页数：客户端过滤（默认排除已删除）时，
  /// 避免只剩少量命中而逐页翻到最后一页。触底再续翻，不会漏数据。
  static const _maxFillPages = 25;

  final _searchController = TextEditingController();
  final _scrollController = ScrollController();
  Timer? _debounce;

  String _keyword = '';
  int _filterIndex = 0;

  /// 更多筛选：`quick_filter` 为空表示不限。
  String? _quickFilter;
  bool _filterExpanded = false;

  List<Map<String, dynamic>> _items = const [];
  bool _loading = true;
  String? _error;
  bool _loadingMore = false;
  bool _hasMore = true;
  int _nextPage = 1;
  int _seq = 0;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _reloadList();
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_scrollController.hasClients) return;
    final position = _scrollController.position;
    if (position.pixels >= position.maxScrollExtent - 200) {
      _loadMore();
    }
  }

  // ── 列表 ────────────────────────────────────────────────────

  /// 拉取一页服务器数据并按当前筛选口径过滤。
  ///
  /// 「默认」与「已暂停/异常」两类口径需要在客户端过滤，因此逐页拉取后
  /// 以 `_fillTarget` 条为下限继续翻页；`maxPages` 防止只剩少量命中时无限翻页。
  Future<({List<Map<String, dynamic>> items, bool hasMore})> _fetchPage(
    int page,
  ) async {
    final filter = _serviceFilters[_filterIndex];
    final data = await AppServices.of(context).services.list(
      page: page,
      pageSize: _pageSize,
      keyword: _keyword,
      status: filter.serverStatus,
      quickFilter: _quickFilter,
    );
    final raw = listOf(data['list']);
    final clientStatuses = filter.clientStatuses;
    final excludeStatuses = filter.excludeStatuses;
    final matched = raw.where((item) {
      final status = intOf(item, 'status');
      if (excludeStatuses.contains(status)) return false;
      if (clientStatuses != null) return clientStatuses.contains(status);
      return true;
    }).toList();
    final total = intOf(data, 'total');
    final pageSize = intOf(data, 'page_size');
    final effectiveSize = pageSize > 0 ? pageSize : _pageSize;
    return (items: matched, hasMore: page * effectiveSize < total);
  }

  Future<void> _reloadList() async {
    final seq = ++_seq;
    setState(() {
      _loading = true;
      _loadingMore = false;
      _hasMore = true;
      _nextPage = 1;
    });
    try {
      final collected = <Map<String, dynamic>>[];
      var page = 1;
      var hasMore = true;
      var fetched = 0;
      while (hasMore &&
          collected.length < _fillTarget &&
          fetched < _maxFillPages) {
        final result = await _fetchPage(page);
        if (!mounted || seq != _seq) return;
        collected.addAll(result.items);
        hasMore = result.hasMore;
        page += 1;
        fetched += 1;
      }
      if (!mounted || seq != _seq) return;
      setState(() {
        _items = collected;
        _nextPage = page;
        _hasMore = hasMore;
        _error = null;
        _loading = false;
      });
    } catch (error) {
      if (!mounted || seq != _seq) return;
      final message = AppServices.errorText(error);
      setState(() {
        _loading = false;
        if (_items.isEmpty) _error = message;
      });
      if (_items.isNotEmpty) AppFeedback.toast(context, message);
    }
  }

  Future<void> _loadMore() async {
    if (_loading || _loadingMore || !_hasMore) return;
    final seq = _seq;
    setState(() => _loadingMore = true);
    try {
      final collected = <Map<String, dynamic>>[];
      var page = _nextPage;
      var hasMore = _hasMore;
      var fetched = 0;
      while (hasMore &&
          collected.length < _fillTarget &&
          fetched < _maxFillPages) {
        final result = await _fetchPage(page);
        if (!mounted || seq != _seq) return;
        collected.addAll(result.items);
        hasMore = result.hasMore;
        page += 1;
        fetched += 1;
      }
      if (!mounted || seq != _seq) return;
      setState(() {
        _items = [..._items, ...collected];
        _nextPage = page;
        _hasMore = hasMore;
        _loadingMore = false;
      });
    } catch (error) {
      if (!mounted || seq != _seq) return;
      setState(() => _loadingMore = false);
      AppFeedback.toast(context, AppServices.errorText(error));
    }
  }

  // ── 交互 ────────────────────────────────────────────────────

  void _onKeywordChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      if (!mounted) return;
      _applyKeyword(value);
    });
  }

  void _applyKeyword(String value) {
    final keyword = value.trim();
    if (keyword == _keyword) return;
    _keyword = keyword;
    _reloadList();
  }

  void _onFilterTap(int index) {
    if (index == _filterIndex) return;
    setState(() => _filterIndex = index);
    _reloadList();
  }

  void _toggleFilterPanel() {
    setState(() => _filterExpanded = !_filterExpanded);
  }

  void _onQuickFilterTap(String? value) {
    if (value == _quickFilter) return;
    setState(() => _quickFilter = value);
    _reloadList();
  }

  Future<void> _onRefresh() => _reloadList();

  // ── 构建 ────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return AppScaffold(
      showBack: false,
      body: Column(
        children: [
          _buildHeader(theme),
          const SizedBox(height: AppSpacing.gapS),
          _buildSearchRow(theme),
          const SizedBox(height: AppSpacing.gapM),
          _buildFilters(theme),
          if (_filterExpanded) ...[
            const SizedBox(height: AppSpacing.gapS),
            _buildMoreFilters(theme),
          ],
          const SizedBox(height: AppSpacing.gapXs),
          Expanded(
            child: RefreshIndicator(
              color: theme.brandColor,
              onRefresh: _onRefresh,
              child: _buildListScroll(theme),
            ),
          ),
        ],
      ),
    );
  }

  /// 页头：只保留标题，视图切换已移除，标题不再被分段控件挤压。
  Widget _buildHeader(TThemeData theme) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        context.pagePadding,
        AppSpacing.gapM,
        context.pagePadding,
        0,
      ),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Text('服务', style: AppType.pageTitle(theme)),
      ),
    );
  }

  /// 搜索行：输入框 + 「筛选」入口。
  ///
  /// 「筛选」不放状态胶囊行：胶囊行本来就要横向滚动，按钮压在右端
  /// 会把最后一个胶囊切出半个字。放在搜索行右侧后胶囊行能占满整行宽度。
  Widget _buildSearchRow(TThemeData theme) {
    final field = _buildSearchField(theme);
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: context.pagePadding),
      child: context.isTablet
          // 平板：输入框限宽 400 并左对齐，「筛选」紧跟其后，不拉到两边。
          ? Align(
              alignment: Alignment.centerLeft,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  SizedBox(width: 400, child: field),
                  const SizedBox(width: AppSpacing.gapS),
                  _buildFilterToggle(),
                ],
              ),
            )
          : Row(
              children: [
                Expanded(child: field),
                const SizedBox(width: AppSpacing.gapS),
                _buildFilterToggle(),
              ],
            ),
    );
  }

  Widget _buildSearchField(TThemeData theme) {
    return TInput(
      controller: _searchController,
      hintText: '搜索服务名称/域名/IP',
      clearButtonMode: TInputClearButtonMode.always,
      onChanged: _onKeywordChanged,
      onSubmitted: _applyKeyword,
      // 点击穿透：prefix 槽的 Center 会吞掉点击，图标附近点不聚焦。
      prefix: IgnorePointer(
        child: Padding(
          padding: const EdgeInsets.only(left: 12, right: 8),
          child: Icon(TIcons.search, size: 18, color: theme.textColorSecondary),
        ),
      ),
    );
  }

  /// 状态筛选：横向胶囊（选中品牌浅底），整行可横向滑动。
  ///
  /// 胶囊行独占一整行、不再被右侧按钮压缩，最后一个胶囊可以完整滑进视口。
  Widget _buildFilters(TThemeData theme) {
    final chips = Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var index = 0; index < _serviceFilters.length; index++)
          Padding(
            padding: EdgeInsets.only(
              right: index < _serviceFilters.length - 1 ? AppSpacing.gapS : 0,
            ),
            child: AppChip(
              label: _serviceFilters[index].label,
              selected: index == _filterIndex,
              onTap: () => _onFilterTap(index),
            ),
          ),
      ],
    );
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: context.pagePadding),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: chips,
      ),
    );
  }

  /// 「筛选」胶囊：与状态胶囊同一套 `TTag` 形态，选中时品牌浅底填充。
  Widget _buildFilterToggle() {
    final active = _filterExpanded || _quickFilter != null;
    return Semantics(
      button: true,
      label: _filterExpanded ? '收起筛选' : '更多筛选',
      child: TTag(
        active && _quickFilter != null ? '筛选 · 1' : '筛选',
        icon: TIcons.filter,
        size: TTagSize.large,
        variant: active ? TTagVariant.light : TTagVariant.lightOutline,
        colorPreset: active
            ? TTagColorPreset.primary
            : TTagColorPreset.defaultTheme,
        shape: TTagShape.round,
        onTap: _toggleFilterPanel,
      ),
    );
  }

  /// 更多筛选面板：到期时间与自动续费（对应后端 quick_filter）。
  Widget _buildMoreFilters(TThemeData theme) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        context.pagePadding,
        0,
        context.pagePadding,
        AppSpacing.gapXs,
      ),
      child: Wrap(
        spacing: AppSpacing.gapS,
        runSpacing: AppSpacing.gapS,
        children: [
          AppChip(
            label: '不限条件',
            selected: _quickFilter == null,
            onTap: () => _onQuickFilterTap(null),
          ),
          for (final item in _quickFilters)
            AppChip(
              label: item.$2,
              selected: _quickFilter == item.$1,
              onTap: () => _onQuickFilterTap(item.$1),
            ),
        ],
      ),
    );
  }

  Widget _buildListScroll(TThemeData theme) {
    final slivers = <Widget>[];
    if (_loading) {
      slivers.add(_stateSliver(theme, loading: true));
    } else if (_error != null) {
      slivers.add(_stateSliver(theme, error: _error, retry: _reloadList));
    } else if (_items.isEmpty) {
      slivers.add(
        _stateSliver(theme, empty: true, emptyAction: _purchaseButton(theme)),
      );
    } else {
      // 全部条目放在同一个 TDesign Cell 组内：通栏、行高、分割线由组件决定。
      // 平板时按奇偶拆成左右两栏，两栏共用一个滚动视图，避免一列过长。
      // 条目少于 4 条时不拆栏：两栏各放不下两条会让右半边整片空白。
      final tablet = context.isTablet && _items.length >= 4;
      final group = tablet
          ? _buildTwoColumnList(theme)
          : _buildSingleColumnList(theme);
      slivers.add(
        SliverPadding(
          padding: EdgeInsets.only(
            top: AppSpacing.gapS,
            left: context.isTablet ? context.pagePadding : 0,
            right: context.isTablet ? context.pagePadding : 0,
          ),
          sliver: SliverToBoxAdapter(child: group),
        ),
      );
      if (_loadingMore || !_hasMore) {
        slivers.add(
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapL),
              child: Center(
                child: _loadingMore
                    ? Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const SizedBox(
                            width: 14,
                            height: 14,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                          const SizedBox(width: AppSpacing.gapS),
                          Text('加载中…', style: AppType.caption(theme)),
                        ],
                      )
                    : Text('没有更多了', style: AppType.caption(theme)),
              ),
            ),
          ),
        );
      }
    }
    return CustomScrollView(
      controller: _scrollController,
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: slivers,
    );
  }

  Widget _buildSingleColumnList(TThemeData theme) {
    return TCellGroup(
      cells: [for (final item in _items) _buildServiceCell(theme, item)],
    );
  }

  /// 平板双栏：按奇偶把条目分到左右两栏，每栏独立 `TCellGroup`。
  Widget _buildTwoColumnList(TThemeData theme) {
    final left = <Map<String, dynamic>>[];
    final right = <Map<String, dynamic>>[];
    for (var index = 0; index < _items.length; index++) {
      (index.isEven ? left : right).add(_items[index]);
    }
    Widget columnOf(List<Map<String, dynamic>> items) {
      if (items.isEmpty) return const SizedBox.shrink();
      return TCellGroup(
        cells: [for (final item in items) _buildServiceCell(theme, item)],
      );
    }

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: columnOf(left)),
        const SizedBox(width: AppSpacing.gapL),
        Expanded(child: columnOf(right)),
      ],
    );
  }

  Widget _stateSliver(
    TThemeData theme, {
    bool loading = false,
    String? error,
    bool empty = false,
    Widget? emptyAction,
    VoidCallback? retry,
  }) {
    return SliverFillRemaining(
      hasScrollBody: false,
      child: DataState(
        loading: loading,
        error: error,
        empty: empty,
        emptyText: '暂无服务实例',
        emptyAction: emptyAction,
        retry: retry,
        child: const SizedBox.shrink(),
      ),
    );
  }

  Widget _purchaseButton(TThemeData theme) {
    return TButton(
      size: TButtonSize.small,
      variant: TButtonVariant.outline,
      colorPreset: TButtonColorPreset.primary,
      child: const Text('前往官网选购'),
      onPressed: () => AppFeedback.toast(context, '请在官网或控制台选购'),
    );
  }

  // ── 列表项 ──────────────────────────────────────────────────

  /// 列表项：`TCell` 承载服务名、产品路径、到期时间，`trailing` 为状态标签 + 金额。
  TCell _buildServiceCell(TThemeData theme, Map<String, dynamic> item) {
    final id = intOf(item, 'id');
    final name = strOf(item, 'name');
    final displayName = strOf(item, 'product_display_name');
    final path = strOf(item, 'product_full_path');
    final statusLabel = strOf(item, 'status_label');
    final tone = strOf(item, 'status_tone');
    final title = name.isNotEmpty
        ? name
        : (displayName.isNotEmpty ? displayName : '服务 #$id');
    final cycle = _billingCycleSuffix(strOf(item, 'billing_cycle'));
    final amount = formatMoneyText(item['amount']);
    return TCell(
      title: Text(title),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (path.isNotEmpty)
            Text(path, maxLines: 1, overflow: TextOverflow.ellipsis),
          Text('到期：${formatDateTime(item['expires_at'])}'),
        ],
      ),
      trailing: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (statusLabel.isNotEmpty)
            AppStatusTag(
              label: statusLabel,
              preset: AppStatusTag.presetOf(tone),
            ),
          const SizedBox(height: AppSpacing.gapXs),
          Text(
            cycle.isEmpty ? amount : '$amount $cycle',
            style: AppType.money(theme, size: 14),
          ),
        ],
      ),
      arrow: true,
      onTap: () => context.push('/services/$id'),
    );
  }

  // ── 计费周期 ────────────────────────────────────────────────

  /// 计费周期后缀：`/ 月`、`/ 年`；未知或一次性周期不展示。
  String _billingCycleSuffix(String cycle) {
    final label = switch (cycle) {
      'monthly' => '月',
      'quarterly' => '季',
      'semiannually' => '半年',
      'annually' => '年',
      'biennially' => '2 年',
      'triennially' => '3 年',
      _ => '',
    };
    return label.isEmpty ? '' : '/ $label';
  }
}
