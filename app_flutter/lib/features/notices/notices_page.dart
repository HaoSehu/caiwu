import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/net/app_services.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/util/format.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import '../../widgets/data_state.dart';

/// 消息中心：公告 / 通知两个页签。
///
/// 公告走 `content.notices`（支持分页），通知走 `content.notifications`
/// （同样分页，不使用 `notificationFeed`）。列表支持下拉刷新与触底加载，
/// 三态交给 [DataState]，空态提供「刷新」动作。
class NoticesPage extends StatefulWidget {
  const NoticesPage({super.key});

  @override
  State<NoticesPage> createState() => _NoticesPageState();
}

class _NoticesPageState extends State<NoticesPage> {
  static const int _pageSize = 15;

  int _tab = 0; // 0 公告 1 通知
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = const [];
  int _page = 1;
  bool _hasMore = true;
  bool _loadingMore = false;
  bool _markingAllRead = false;

  /// 当前页签的未读数：公告查 noticeUnreadCount，通知查 notificationUnreadCount。
  int _unread = 0;
  bool _unreadLoaded = false;
  int _seq = 0;

  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) => _load(reset: true));
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_hasMore || _loadingMore) return;
    if (!_scrollController.hasClients) return;
    if (_scrollController.position.pixels >
        _scrollController.position.maxScrollExtent - 160) {
      _load(reset: false);
    }
  }

  Future<void> _load({required bool reset}) async {
    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
        _hasMore = true;
        _unreadLoaded = false;
      });
    } else {
      if (_loadingMore || !_hasMore) return;
      setState(() => _loadingMore = true);
    }

    final seq = ++_seq;
    final page = reset ? 1 : _page;
    final services = AppServices.of(context);

    try {
      final data = _tab == 0
          ? await services.content.notices(page: page, pageSize: _pageSize)
          : await services.content.notifications(
              page: page,
              pageSize: _pageSize,
            );
      if (!mounted || seq != _seq) return;

      final list = listOf(data['list']);
      setState(() {
        _items = reset ? list : [..._items, ...list];
        _page = page + 1;
        _hasMore = list.length >= _pageSize;
        _loading = false;
        _loadingMore = false;
        _error = null;
      });
      if (reset) await _loadUnread();
    } catch (error) {
      if (!mounted || seq != _seq) return;
      setState(() {
        _loading = false;
        _loadingMore = false;
        if (reset) {
          _error = AppServices.errorText(error);
        } else {
          _hasMore = false;
        }
      });
    }
  }

  /// 拉取当前页签未读数，控制「全部已读」是否可用。
  ///
  /// 公告只有 `noticeUnreadCount` 可直接读取；通知侧 `notificationUnreadCount`
  /// 在本项目已包含公告未读，无法干净拆出，改为看当前页通知列表里是否还有未读
  /// （同一页内已读状态与后端 `read` 字段一致）。
  Future<void> _loadUnread() async {
    final tab = _tab;
    final content = AppServices.of(context).content;
    if (tab == 1) {
      final unread = _items.any((item) => item['read'] != true);
      if (!mounted || tab != _tab) return;
      setState(() {
        _unread = unread ? 1 : 0;
        _unreadLoaded = true;
      });
      return;
    }
    try {
      final count = await content.noticeUnreadCount();
      if (!mounted || tab != _tab) return;
      setState(() {
        _unread = count;
        _unreadLoaded = true;
      });
    } catch (_) {
      if (!mounted || tab != _tab) return;
      setState(() => _unreadLoaded = true);
    }
  }

  /// 「全部已读」是否可点：当前页签有未读才允许，全部已读后置灰。
  bool get _canMarkAllRead => _unreadLoaded && _unread > 0;

  Future<void> _markAllRead() async {
    if (_markingAllRead || !_canMarkAllRead) return;
    final services = AppServices.of(context);
    setState(() => _markingAllRead = true);
    try {
      if (_tab == 0) {
        await services.content.markAllNoticesRead();
      } else {
        await services.content.markAllNotificationsRead();
      }
      if (!mounted) return;
      AppFeedback.success(context, '已全部标为已读');
      await _load(reset: true);
    } catch (error) {
      if (!mounted) return;
      AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _markingAllRead = false);
    }
  }

  Future<void> _openItem(Map<String, dynamic> item) async {
    if (_tab == 0) {
      // 公告详情接口会标记已读；返回后刷新未读数，让「全部已读」及时置灰。
      final id = intOf(item, 'id');
      if (id > 0) await context.push('/notices/$id');
      if (mounted) await _loadUnread();
      return;
    }
    // 通知：有 raw_id 时标记已读
    final rawId = intOf(item, 'raw_id');
    if (rawId > 0) {
      try {
        await AppServices.of(context).content.markNotificationRead(rawId);
      } catch (_) {}
    }
    final link = strOf(item, 'link');
    // 后端下发的 link 是网页端路径（/client/...），必须映射到 App 路由，
    // 否则 GoRouter 会落到"no routes for location"错误页。
    final appPath = _mapWebLink(link);
    if (appPath != null && mounted) {
      await context.push(appPath);
    } else if (link.isNotEmpty && mounted) {
      AppFeedback.toast(context, '该消息请在网页端查看');
    }
    if (mounted) await _load(reset: true);
  }

  /// 网页端路径 → App 路由；无法映射时返回 null。
  String? _mapWebLink(String link) {
    final raw = link.trim();
    if (raw.isEmpty) return null;
    if (raw.startsWith('/notices/') ||
        raw.startsWith('/tickets/') ||
        raw.startsWith('/finance/')) {
      return raw;
    }
    final notice = RegExp(r'^/client/notices/(\d+)').firstMatch(raw);
    if (notice != null) return '/notices/${notice.group(1)}';
    final ticket = RegExp(r'^/client/tickets/(\d+)').firstMatch(raw);
    if (ticket != null) return '/tickets/${ticket.group(1)}';
    final service = RegExp(r'^/client/services/(\d+)').firstMatch(raw);
    if (service != null) return '/services/${service.group(1)}';
    final invoice = RegExp(r'^/client/invoices/(\d+)').firstMatch(raw);
    if (invoice != null) return '/finance/invoices/${invoice.group(1)}';
    final order = RegExp(r'^/client/orders/(\d+)').firstMatch(raw);
    if (order != null) return '/finance/orders/${order.group(1)}';
    if (raw == '/client/invoices' || raw == '/client/orders') return '/finance';
    if (raw == '/client/services') return '/services';
    if (raw == '/client/tickets') return '/tickets';
    return null;
  }

  void _switchTab(int index) {
    if (index == _tab) return;
    setState(() {
      _tab = index;
      _items = const [];
      _error = null;
      _hasMore = true;
    });
    if (_scrollController.hasClients) _scrollController.jumpTo(0);
    _load(reset: true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    final enabled = _canMarkAllRead && !_markingAllRead;
    return AppScaffold(
      title: '消息中心',
      actions: [
        TextButton(
          onPressed: enabled ? _markAllRead : null,
          child: Text(
            '全部已读',
            style: AppType.secondary(theme).copyWith(
              color: enabled ? theme.brandColor : theme.textColorDisabled,
            ),
          ),
        ),
      ],
      body: Column(
        children: [
          Padding(
            padding: EdgeInsets.symmetric(horizontal: context.pagePadding),
            child: AppSegmentedTabs(
              labels: const ['公告', '通知'],
              current: _tab,
              onChanged: _switchTab,
            ),
          ),
          Expanded(child: _buildBody(theme)),
        ],
      ),
    );
  }

  Widget _buildBody(TThemeData theme) {
    if (_loading) {
      return const DataState(loading: true, child: SizedBox.shrink());
    }
    if (_error != null) {
      return DataState(
        loading: false,
        error: _error,
        retry: () => _load(reset: true),
        child: const SizedBox.shrink(),
      );
    }
    if (_items.isEmpty) {
      return RefreshIndicator(
        color: theme.brandColor,
        onRefresh: () => _load(reset: true),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(top: 64),
          children: [
            TEmpty(
              emptyText: _tab == 0 ? '暂无公告' : '暂无通知',
              operation: TButton(
                size: TButtonSize.small,
                variant: TButtonVariant.outline,
                colorPreset: TButtonColorPreset.primary,
                onPressed: () => _load(reset: true),
                child: const Text('刷新'),
              ),
            ),
          ],
        ),
      );
    }
    return RefreshIndicator(
      color: theme.brandColor,
      onRefresh: () => _load(reset: true),
      child: ListView(
        controller: _scrollController,
        padding: EdgeInsets.fromLTRB(
          context.pagePadding,
          AppSpacing.gapM,
          context.pagePadding,
          0,
        ),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          // 少于 4 条不拆栏：两栏各一两条会让半屏空白，单栏通栏更耐读。
          // 双栏只在横屏平板启用：竖屏两栏下摘要只剩十来个字，没有阅读价值。
          if (context.isExpanded && _items.length >= 4)
            _buildTwoColumnGroups(theme)
          else
            _buildCellGroup(theme, _items),
          _buildFooter(theme),
        ],
      ),
    );
  }

  /// 平板双栏：按奇偶拆成左右两组，加载更多追加时已有点位不发生列漂移。
  Widget _buildTwoColumnGroups(TThemeData theme) {
    final left = <Map<String, dynamic>>[];
    final right = <Map<String, dynamic>>[];
    for (var i = 0; i < _items.length; i++) {
      (i.isEven ? left : right).add(_items[i]);
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: _buildCellGroup(theme, left)),
        const SizedBox(width: AppSpacing.gapL),
        Expanded(child: _buildCellGroup(theme, right)),
      ],
    );
  }

  /// 消息列表：TDesign 官方单元格组，整组包在 9 圆角白底卡片里。
  Widget _buildCellGroup(TThemeData theme, List<Map<String, dynamic>> items) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(context.tTheme.radiusLarge),
        border: Border.all(color: theme.borderLevel1Color),
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: theme.bgColorContainer,
        child: TCellGroup(
          cells: [for (final item in items) _buildCell(theme, item)],
        ),
      ),
    );
  }

  TCell _buildCell(TThemeData theme, Map<String, dynamic> item) {
    final rawTitle = strOf(item, 'title').trim();
    final title = rawTitle.isEmpty ? '(无标题)' : rawTitle;
    final summary = strOf(item, 'summary').trim().isNotEmpty
        ? strOf(item, 'summary')
        : strOf(item, 'excerpt');
    final time = _tab == 0
        ? (strOf(item, 'publish_at').isNotEmpty
              ? strOf(item, 'publish_at')
              : strOf(item, 'created_at'))
        : strOf(item, 'created_at');
    // 公告下发 type_label（公告类型）；通知下发 type_label（消息类型）。
    final typeLabel = strOf(item, 'type_label').trim();
    final unread = _tab == 1 && item['read'] != true;

    return TCell(
      // 固定宽度占位：未读圆点出现与否都不改变标题左缘对齐。
      prefix: SizedBox(
        width: 8,
        child: unread ? AppDot(color: theme.brandColor) : null,
      ),
      title: Text(
        title,
        style: AppType.cardTitle(theme).copyWith(
          fontWeight: unread ? FontWeight.w600 : FontWeight.w400,
          color: theme.textColorPrimary,
        ),
      ),
      subtitle: Text(
        [
          if (summary.trim().isNotEmpty) summary,
          if (typeLabel.isNotEmpty) typeLabel,
        ].join(' · '),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      note: Text(formatDateTime(time), style: AppType.caption(theme)),
      arrow: true,
      align: TCellAlign.top,
      onTap: () => _openItem(item),
    );
  }

  Widget _buildFooter(TThemeData theme) {
    if (_loadingMore) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapL),
        child: Center(
          child: Row(
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
          ),
        ),
      );
    }
    if (_items.isEmpty || _hasMore) {
      return const SizedBox(height: AppSpacing.gapS);
    }
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapL),
      child: Center(child: Text('没有更多了', style: AppType.caption(theme))),
    );
  }
}
