import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/gestures.dart';
import 'package:image_picker/image_picker.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../../core/layout/responsive.dart';
import '../../../core/net/app_services.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/util/format.dart';
import '../../../widgets/app_feedback.dart';
import '../../../widgets/app_kit.dart';
import '../../../widgets/data_state.dart';
import 'ticket_labels.dart';

/// 工单详情：手机/竖屏分两页，平板横屏左右分栏。
///
/// - 第一页「沟通记录」：回复气泡列表 + 底部回复输入区；
/// - 第二页「工单信息」：主题、状态、部门、优先级与时间，**左滑或点标签**才进入，
///   不占用首屏视野；翻到信息页时收起输入区，避免输入框压在只读信息上；
/// - 详情与首屏回复并行加载（[Future.wait]），回复超过一页时继续翻页取全；
/// - 气泡按「发送者 + 分钟」分组：组内间距 4、组间 12，组末才显示一次时间；
///   气泡圆角使用 `theme.radiusLarge`，间距统一 [AppSpacing]；
/// - 回复支持纯文字、纯图片或图文：图片先上传拿 `path`，发送时随 `attachments` 提交；
/// - 已关闭工单隐藏输入区，改为「工单已关闭」提示条（TDesign `TNoticeBar`）；
/// - 空态使用 TDesign `TEmpty`。
class TicketDetailPage extends StatefulWidget {
  const TicketDetailPage({super.key, required this.ticketId});

  final int ticketId;

  @override
  State<TicketDetailPage> createState() => _TicketDetailPageState();
}

/// 待发送的回复图片：`path` 用于提交，`url` 用于缩略图展示。
class _ReplyImage {
  const _ReplyImage({required this.path, required this.url});

  final String path;
  final String url;
}

class _TicketDetailPageState extends State<TicketDetailPage> {
  static const int _replyPageSize = 50;

  /// 与后端 `attachments` 上限（9）保持一致。
  static const int _maxReplyImages = 9;

  /// 回复条缩略图边长。
  static const double _replyThumbSize = 56;

  final ScrollController _scrollController = ScrollController();
  final PageController _pageController = PageController();
  final TextEditingController _replyController = TextEditingController();
  final FocusNode _replyFocus = FocusNode();

  Map<String, dynamic>? _detail;
  List<Map<String, dynamic>> _replies = [];
  final List<_ReplyImage> _replyImages = [];

  int _seq = 0;
  bool _loading = true;
  String? _error;
  bool _sending = false;
  bool _closing = false;

  /// 0 = 沟通记录，1 = 工单信息。
  int _pageIndex = 0;

  bool _uploadingImage = false;
  int _uploadTotal = 0;
  int _uploadDone = 0;

  int get _status => intOf(_detail, 'status');
  bool get _closed => _status == 3;

  @override
  void initState() {
    super.initState();
    unawaited(_load(showLoading: true));
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _pageController.dispose();
    _replyController.dispose();
    _replyFocus.dispose();
    super.dispose();
  }

  Future<void> _load({required bool showLoading}) async {
    final seq = ++_seq;
    final api = AppServices.of(context).tickets;
    if (showLoading) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final results = await Future.wait([
        api.detail(widget.ticketId),
        api.replies(widget.ticketId, page: 1, pageSize: _replyPageSize),
      ]);
      final detail = results[0];
      final firstPage = results[1];
      final replies = listOf(firstPage['list']);
      final total = intOf(firstPage, 'total');

      // 回复超过一页时按顺序补齐，保证气泡按时间正序完整展示。
      var page = 1;
      while (replies.length < total && page < 10) {
        page += 1;
        final next = await api.replies(
          widget.ticketId,
          page: page,
          pageSize: _replyPageSize,
        );
        final nextList = listOf(next['list']);
        if (nextList.isEmpty) break;
        replies.addAll(nextList);
      }

      if (!mounted || seq != _seq) return;
      setState(() {
        _detail = detail;
        _replies = replies;
        _loading = false;
        _error = null;
      });
    } catch (error) {
      if (!mounted || seq != _seq) return;
      final message = AppServices.errorText(error);
      final hadData = _detail != null;
      setState(() {
        _loading = false;
        if (!hadData) _error = message;
      });
      if (hadData) AppFeedback.error(context, message);
    }
  }

  void _scrollToBottom({bool animate = true}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_scrollController.hasClients) return;
      final target = _scrollController.position.maxScrollExtent;
      if (animate) {
        unawaited(
          _scrollController.animateTo(
            target,
            duration: const Duration(milliseconds: 240),
            curve: Curves.easeOut,
          ),
        );
      } else {
        _scrollController.jumpTo(target);
      }
    });
  }

  Future<void> _sendReply() async {
    if (_sending || _closed) return;
    final content = _replyController.text.trim();
    if (content.isEmpty && _replyImages.isEmpty) {
      AppFeedback.toast(context, '请输入回复内容或上传图片');
      return;
    }
    setState(() => _sending = true);
    final api = AppServices.of(context).tickets;
    try {
      await api.reply(
        widget.ticketId,
        content: content,
        attachments: [for (final image in _replyImages) image.path],
      );
      if (!mounted) return;
      _replyController.clear();
      setState(() => _replyImages.clear());
      FocusScope.of(context).unfocus();
      await _load(showLoading: false);
      if (!mounted) return;
      _scrollToBottom();
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  // ── 回复图片 ────────────────────────────────────────────────

  /// 选图并逐张上传；上传完成才写入 [_replyImages]，发送时只带 `path`。
  Future<void> _pickReplyImages() async {
    if (_uploadingImage || _sending) return;
    final remaining = _maxReplyImages - _replyImages.length;
    if (remaining <= 0) {
      AppFeedback.toast(context, '最多上传 $_maxReplyImages 张图片');
      return;
    }
    final api = AppServices.of(context).tickets;
    List<XFile> picked;
    try {
      picked = await ImagePicker().pickMultiImage(
        imageQuality: 82,
        limit: remaining,
      );
    } catch (_) {
      if (mounted) AppFeedback.error(context, '选择图片失败，请重试');
      return;
    }
    if (!mounted || picked.isEmpty) return;
    if (picked.length > remaining) picked = picked.sublist(0, remaining);

    setState(() {
      _uploadingImage = true;
      _uploadTotal = picked.length;
      _uploadDone = 0;
    });
    try {
      for (final file in picked) {
        // 不用 MultipartFile.fromFile：它依赖 dart:io，Web 上会直接抛错。
        // 读字节再上传，移动端与 Web 行为一致。
        final formData = FormData.fromMap({
          'file': MultipartFile.fromBytes(
            await file.readAsBytes(),
            filename: file.name,
          ),
        });
        final data = await api.uploadImage(formData);
        final path = strOf(data, 'path');
        if (path.isEmpty) throw StateError('图片上传返回缺少 path');
        if (!mounted) return;
        setState(() {
          _replyImages.add(_ReplyImage(path: path, url: strOf(data, 'url')));
          _uploadDone += 1;
        });
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) {
        setState(() {
          _uploadingImage = false;
          _uploadTotal = 0;
          _uploadDone = 0;
        });
      }
    }
  }

  void _removeReplyImage(int index) {
    if (_sending) return;
    setState(() => _replyImages.removeAt(index));
  }

  /// 翻页：点标签与左滑共用同一份 [_pageIndex]。
  void _switchPage(int index) {
    if (index == _pageIndex) return;
    setState(() => _pageIndex = index);
    unawaited(
      _pageController.animateToPage(
        index,
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
      ),
    );
  }

  Future<void> _closeTicket() async {
    if (_closing || _closed) return;
    final confirmed = await AppFeedback.confirm(
      context,
      title: '关闭工单',
      content: '关闭后将无法继续回复，确定关闭该工单吗？',
      confirmText: '关闭工单',
      danger: true,
    );
    if (!confirmed || !mounted) return;
    setState(() => _closing = true);
    final api = AppServices.of(context).tickets;
    try {
      await api.close(widget.ticketId);
      if (!mounted) return;
      AppFeedback.success(context, '工单已关闭');
      await _load(showLoading: false);
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _closing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    // 平板横屏左右分栏时，回复输入区并入右栏底部，不再使用页面底部固定条。
    final split = _detail != null && context.isExpanded;
    final Widget? bottomBar;
    if (_detail == null || split) {
      bottomBar = null;
    } else if (_closed) {
      // 已关闭是状态说明，两页都保留。
      bottomBar = _buildClosedBar(context);
    } else if (_pageIndex == 0) {
      bottomBar = _buildReplyBar(context);
    } else {
      // 信息页只读，收起输入区。
      bottomBar = null;
    }
    return AppScaffold(
      title: '工单详情',
      actions: [if (_detail != null && !_closed) _buildCloseAction(context)],
      bottomBar: bottomBar,
      body: _buildBody(context),
    );
  }

  Widget _buildCloseAction(BuildContext context) {
    return TButton(
      size: TButtonSize.small,
      variant: TButtonVariant.text,
      colorPreset: TButtonColorPreset.primary,
      onPressed: _closing ? null : () => unawaited(_closeTicket()),
      child: Text(_closing ? '关闭中…' : '关闭工单'),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_detail == null) {
      return DataState(
        loading: _loading,
        error: _error,
        retry: () => unawaited(_load(showLoading: true)),
        child: const SizedBox.shrink(),
      );
    }
    final padding = context.pagePadding;
    // 区块间距：平板 32，手机 24。
    final sectionGap = context.isTablet ? 32.0 : AppSpacing.gapXl;
    final infoGroup = _buildInfoGroup(context);
    final conversation = _buildConversation(context);
    final inputBar = _closed
        ? _buildClosedBar(context)
        : _buildReplyBar(context);

    if (context.isExpanded) {
      // 平板横屏左右分栏：左侧信息卡固定 320，右侧沟通记录 + 输入区。
      return Padding(
        padding: EdgeInsets.fromLTRB(padding, AppSpacing.gapM, padding, 0),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 320,
              child: SingleChildScrollView(
                padding: const EdgeInsets.only(bottom: AppSpacing.pageBottom),
                child: infoGroup,
              ),
            ),
            SizedBox(width: sectionGap),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                    // 输入区在分栏内竖排底部，与页面底部安全区对齐。
                    child: RefreshIndicator(
                      onRefresh: () => _load(showLoading: false),
                      child: SingleChildScrollView(
                        controller: _scrollController,
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.only(bottom: AppSpacing.gapL),
                        child: conversation,
                      ),
                    ),
                  ),
                  SafeArea(
                    top: false,
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.gapM),
                      child: inputBar,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    // 手机/平板竖屏：两页横排，第一页沟通记录，左滑进入工单信息。
    return Column(
      children: [
        AppSegmentedTabs(
          labels: const ['沟通记录', '工单信息'],
          current: _pageIndex,
          onChanged: _switchPage,
        ),
        Expanded(
          child: PageView(
            controller: _pageController,
            // 默认滚动行为在桌面/网页端不接受鼠标拖动，左滑会失效；
            // 这里放开鼠标与触控板拖动，让浏览器预览和真机手势一致。
            scrollBehavior: ScrollConfiguration.of(context).copyWith(
              dragDevices: const {
                PointerDeviceKind.touch,
                PointerDeviceKind.mouse,
                PointerDeviceKind.trackpad,
                PointerDeviceKind.stylus,
              },
            ),
            onPageChanged: (index) {
              if (index == _pageIndex) return;
              setState(() => _pageIndex = index);
            },
            children: [
              // 第一页：沟通记录（可下拉刷新，发完回复自动滚到底）。
              RefreshIndicator(
                onRefresh: () => _load(showLoading: false),
                child: SingleChildScrollView(
                  controller: _scrollController,
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: EdgeInsets.fromLTRB(
                    padding,
                    AppSpacing.gapM,
                    padding,
                    AppSpacing.pageBottom,
                  ),
                  child: conversation,
                ),
              ),
              // 第二页：工单信息（左滑进入）。
              RefreshIndicator(
                onRefresh: () => _load(showLoading: false),
                child: SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: EdgeInsets.fromLTRB(
                    padding,
                    AppSpacing.gapM,
                    padding,
                    AppSpacing.pageBottom,
                  ),
                  child: infoGroup,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  /// 沟通记录：条数提示 + 气泡列表（按发送者与分钟分组）。
  Widget _buildConversation(BuildContext context) {
    final theme = context.tTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // 标题已由页签承担，这里只保留条数，避免同一屏出现两遍「沟通记录」。
        if (_replies.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.gapS),
            child: Text(
              '共 ${_replies.length} 条',
              textAlign: TextAlign.center,
              style: AppType.caption(theme),
            ),
          ),
        if (_replies.isEmpty)
          _buildEmptyReplies(context)
        else
          for (var i = 0; i < _replies.length; i++) ...[
            _buildReplyItem(
              context,
              _replies[i],
              firstInGroup: i == 0 || !_sameGroup(_replies[i - 1], _replies[i]),
              lastInGroup:
                  i == _replies.length - 1 ||
                  !_sameGroup(_replies[i], _replies[i + 1]),
            ),
            if (i < _replies.length - 1)
              SizedBox(
                height: _sameGroup(_replies[i], _replies[i + 1])
                    ? AppSpacing.gapXs
                    : AppSpacing.gapM,
              ),
          ],
      ],
    );
  }

  /// 同一发送者在同一分钟内连续发送的消息属于同一组。
  bool _sameGroup(Map<String, dynamic> a, Map<String, dynamic> b) {
    if (_senderKey(a) != _senderKey(b)) return false;
    final minuteA = _minuteKey(a);
    final minuteB = _minuteKey(b);
    if (minuteA.isEmpty || minuteB.isEmpty) return false;
    return minuteA == minuteB;
  }

  String _senderKey(Map<String, dynamic> reply) {
    return '${intOf(reply, 'is_staff')}|${strOf(reply, 'sender_name')}';
  }

  static final RegExp _chatTimePattern = RegExp(
    r'^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})',
  );

  String _minuteKey(Map<String, dynamic> reply) {
    final match = _chatTimePattern.firstMatch(
      strOf(reply, 'created_at').trim(),
    );
    if (match == null) return '';
    return '${match.group(1)}${match.group(2)}${match.group(3)}'
        '${match.group(4)}${match.group(5)}';
  }

  /// 聊天气泡时间：当天只显示时分，跨天补上日期；无法解析时回退原文。
  ///
  /// 直接用后端字符串里的日期与本地「今天」比较，避免服务器时区与设备不一致
  /// 导致 `DateTime.parse` 偏移一天。
  String _chatTimeLabel(Map<String, dynamic> reply) {
    final raw = strOf(reply, 'created_at').trim();
    if (raw.isEmpty) return '';
    final match = _chatTimePattern.firstMatch(raw);
    if (match == null) return raw;
    String two(int value) => value.toString().padLeft(2, '0');
    final now = DateTime.now();
    final today = '${now.year}-${two(now.month)}-${two(now.day)}';
    final date = '${match.group(1)}-${match.group(2)}-${match.group(3)}';
    final time = '${match.group(4)}:${match.group(5)}';
    return date == today ? time : '${match.group(2)}-${match.group(3)} $time';
  }

  /// 信息组：主题 + 状态 + 部门/优先级/时间。
  Widget _buildInfoGroup(BuildContext context) {
    final theme = context.tTheme;
    final detail = _detail!;
    return TCellGroup(
      cells: [
        TCell(
          title: Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Flexible(
                child: Text(
                  strOf(detail, 'subject').isEmpty
                      ? '无主题工单'
                      : strOf(detail, 'subject'),
                  style: AppType.section(theme),
                ),
              ),
              const SizedBox(width: AppSpacing.gapXs),
              Text('#${widget.ticketId}', style: AppType.caption(theme)),
            ],
          ),
          trailing: AppStatusTag(
            label: ticketStatusLabel(detail),
            preset: _statusPreset(_status),
          ),
        ),
        _fieldCell('部门', ticketDepartmentLabel(detail)),
        _fieldCell('优先级', ticketPriorityLabel(detail)),
        _fieldCell('创建时间', formatDateTime(strOf(detail, 'created_at'))),
        _fieldCell('更新时间', formatDateTime(strOf(detail, 'updated_at'))),
      ],
    );
  }

  TCell _fieldCell(String label, String value) {
    final theme = context.tTheme;
    return TCell(
      title: Text(label, style: AppType.secondary(theme)),
      note: Text(value, style: AppType.body(theme)),
    );
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
        // 已关闭用中性样式，避免与「开启/待处理」的彩色标签抢注意力。
        return TTagColorPreset.defaultTheme;
    }
  }

  Widget _buildEmptyReplies(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapXl),
        child: TEmpty(
          emptyText: _closed ? '工单已关闭，沟通记录为空' : '还没有回复，补充更多信息客服会尽快回复你',
          operation: _closed
              ? null
              : TButton(
                  size: TButtonSize.small,
                  variant: TButtonVariant.outline,
                  colorPreset: TButtonColorPreset.primary,
                  onPressed: () => _replyFocus.requestFocus(),
                  child: const Text('写第一条回复'),
                ),
        ),
      ),
    );
  }

  Widget _buildReplyItem(
    BuildContext context,
    Map<String, dynamic> reply, {
    required bool firstInGroup,
    required bool lastInGroup,
  }) {
    return LayoutBuilder(
      builder: (context, constraints) {
        // 气泡最大宽度按聊天列实际可用宽度计算：手机 72%，平板 60%。
        // 分栏后整页宽度远大于聊天列，不能用整页宽度做基数。
        final base = constraints.maxWidth.isFinite
            ? constraints.maxWidth
            : MediaQuery.sizeOf(context).width;
        final maxBubbleWidth = base * (context.isTablet ? 0.6 : 0.72);
        return _buildReplyBubble(
          context,
          reply,
          firstInGroup: firstInGroup,
          lastInGroup: lastInGroup,
          maxBubbleWidth: maxBubbleWidth,
        );
      },
    );
  }

  Widget _buildReplyBubble(
    BuildContext context,
    Map<String, dynamic> reply, {
    required bool firstInGroup,
    required bool lastInGroup,
    required double maxBubbleWidth,
  }) {
    final theme = context.tTheme;
    final isStaff = intOf(reply, 'is_staff') == 1;
    final recalled = reply['recalled'] == true;
    final sender = strOf(reply, 'sender_name');
    final quote = mapOf(reply['quote']);
    final attachments = listOf(
      reply['attachments'],
    ).where((item) => item['deleted'] != true).toList();

    final content = strOf(reply, 'content').trim();
    final hasBody =
        content.isNotEmpty || attachments.isNotEmpty || quote.isNotEmpty;

    final radius = theme.radiusLarge;
    final bubble = Container(
      constraints: BoxConstraints(maxWidth: maxBubbleWidth),
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.gapM,
        vertical: AppSpacing.gapM,
      ),
      decoration: BoxDecoration(
        // 我方（客户）右侧品牌浅底；客服左侧灰底（比 secondaryContainer 深一档，
        // 避免在页面底色上几乎不可见）。
        color: isStaff ? theme.bgColorComponent : theme.brandColorLight,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(radius),
          topRight: Radius.circular(radius),
          bottomLeft: Radius.circular(isStaff ? theme.radiusSmall : radius),
          bottomRight: Radius.circular(isStaff ? radius : theme.radiusSmall),
        ),
      ),
      child: recalled
          ? Text('消息已撤回', style: AppType.secondary(theme))
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (quote.isNotEmpty) _buildQuoteBlock(context, quote),
                if (quote.isNotEmpty && content.isNotEmpty)
                  const SizedBox(height: AppSpacing.gapXs),
                // 空内容不渲染空文案：只显示占位提示，避免出现空白气泡。
                if (content.isNotEmpty)
                  Text(
                    content,
                    style: AppType.body(theme).copyWith(height: 1.5),
                  )
                else if (!hasBody)
                  Text(
                    '（无文字内容）',
                    style: AppType.secondary(
                      theme,
                    ).copyWith(color: theme.textColorPlaceholder),
                  ),
                if (attachments.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.gapS),
                  Wrap(
                    spacing: AppSpacing.gapS,
                    runSpacing: AppSpacing.gapS,
                    children: [
                      for (final attachment in attachments)
                        _buildAttachmentThumb(context, attachment),
                    ],
                  ),
                ],
              ],
            ),
    );

    final timeLabel = lastInGroup ? _chatTimeLabel(reply) : '';

    return Column(
      crossAxisAlignment: isStaff
          ? CrossAxisAlignment.start
          : CrossAxisAlignment.end,
      children: [
        // 组内只在第一条显示客服署名；自己的消息不重复署名，减少视觉噪音。
        if (isStaff && firstInGroup)
          Padding(
            padding: const EdgeInsets.only(
              left: AppSpacing.gapXs,
              bottom: AppSpacing.gapXs,
            ),
            child: Text(
              sender.isEmpty ? '客服' : sender,
              style: AppType.caption(theme),
            ),
          ),
        bubble,
        if (timeLabel.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(
              top: AppSpacing.gapXs,
              left: AppSpacing.gapXs,
              right: AppSpacing.gapXs,
            ),
            child: Text(timeLabel, style: AppType.caption(theme)),
          ),
      ],
    );
  }

  Widget _buildQuoteBlock(BuildContext context, Map<String, dynamic> quote) {
    final theme = context.tTheme;
    final recalled = quote['recalled'] == true;
    final sender = strOf(quote, 'sender_name');
    final content = recalled ? '消息已撤回' : strOf(quote, 'content');
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.gapS,
        vertical: 6,
      ),
      decoration: BoxDecoration(
        color: theme.bgColorContainer.withValues(alpha: 0.6),
        border: Border(
          left: BorderSide(color: theme.borderLevel2Color, width: 3),
        ),
        borderRadius: BorderRadius.circular(theme.radiusSmall),
      ),
      child: Text(
        '${sender.isEmpty ? '' : '$sender：'}$content',
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: AppType.caption(theme),
      ),
    );
  }

  Widget _buildAttachmentThumb(
    BuildContext context,
    Map<String, dynamic> attachment,
  ) {
    final theme = context.tTheme;
    final url = strOf(attachment, 'url');
    return ClipRRect(
      borderRadius: BorderRadius.circular(theme.radiusDefault),
      child: SizedBox(
        width: 72,
        height: 72,
        child: url.isEmpty
            ? Container(
                color: theme.bgColorContainer,
                child: Icon(
                  TIcons.image,
                  size: 22,
                  color: theme.textColorPlaceholder,
                ),
              )
            : Image.network(
                url,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => Container(
                  color: theme.bgColorContainer,
                  child: Icon(
                    TIcons.image_error,
                    size: 22,
                    color: theme.textColorPlaceholder,
                  ),
                ),
              ),
      ),
    );
  }

  Widget _buildReplyBar(BuildContext context) {
    final theme = context.tTheme;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // 已选图片：缩略图 + 删除角标；上传中补一个进度方块。
        if (_replyImages.isNotEmpty || _uploadingImage) ...[
          SizedBox(
            height: _replyThumbSize,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _replyImages.length + (_uploadingImage ? 1 : 0),
              separatorBuilder: (_, __) =>
                  const SizedBox(width: AppSpacing.gapS),
              itemBuilder: (context, index) {
                if (index >= _replyImages.length) {
                  return _buildUploadingThumb(theme);
                }
                return _buildReplyThumb(theme, index);
              },
            ),
          ),
          const SizedBox(height: AppSpacing.gapS),
        ],
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            _buildPickImageButton(context),
            const SizedBox(width: AppSpacing.gapXs),
            Expanded(
              // TInput 根节点是 mainAxisSize.max 的 Column，会撑满父级给的
              // 高度上限；而 Scaffold.bottomNavigationBar 给的上限是整页高度，
              // 直接放进来会让输入框长到整页、把详情内容压成 0 高。
              // 这里解除纵向约束，让输入框按内容自然高度布局（1 行到 4 行）。
              child: UnconstrainedBox(
                constrainedAxis: Axis.horizontal,
                alignment: Alignment.centerLeft,
                child: TInput(
                  controller: _replyController,
                  focusNode: _replyFocus,
                  hintText: '输入回复内容',
                  maxLines: 4,
                  minLines: 1,
                  enabled: !_sending,
                ),
              ),
            ),
            const SizedBox(width: AppSpacing.gapS),
            ValueListenableBuilder<TextEditingValue>(
              valueListenable: _replyController,
              builder: (context, value, _) => _buildSendButton(
                sending: _sending,
                canSend:
                    value.text.trim().isNotEmpty || _replyImages.isNotEmpty,
              ),
            ),
          ],
        ),
      ],
    );
  }

  /// 选图入口：文字型圆形按钮，与右侧发送按钮同尺寸对齐。
  Widget _buildPickImageButton(BuildContext context) {
    final pending = _uploadingImage || _sending;
    return Semantics(
      button: true,
      enabled: !pending,
      label: '上传图片',
      child: TButton(
        size: TButtonSize.large,
        shape: TButtonShape.circle,
        variant: TButtonVariant.text,
        colorPreset: TButtonColorPreset.defaultTheme,
        onPressed: pending ? null : () => unawaited(_pickReplyImages()),
        icon: _uploadingImage
            ? const TLoading(size: 18)
            : const Icon(TIcons.image_add, size: 22),
      ),
    );
  }

  Widget _buildReplyThumb(TThemeData theme, int index) {
    final image = _replyImages[index];
    return SizedBox(
      width: _replyThumbSize,
      height: _replyThumbSize,
      child: Stack(
        fit: StackFit.expand,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(theme.radiusLarge),
            child: image.url.isEmpty
                ? Container(
                    color: theme.bgColorContainer,
                    child: Icon(
                      TIcons.image,
                      size: 20,
                      color: theme.textColorPlaceholder,
                    ),
                  )
                : Image.network(
                    image.url,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(
                      color: theme.bgColorContainer,
                      child: Icon(
                        TIcons.image_error,
                        size: 20,
                        color: theme.textColorPlaceholder,
                      ),
                    ),
                  ),
          ),
          Positioned(
            top: 2,
            right: 2,
            child: GestureDetector(
              onTap: () => _removeReplyImage(index),
              child: Container(
                padding: const EdgeInsets.all(1),
                decoration: const BoxDecoration(
                  color: Colors.black54,
                  shape: BoxShape.circle,
                ),
                child: const Icon(TIcons.close, size: 12, color: Colors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildUploadingThumb(TThemeData theme) {
    final progress = _uploadTotal == 0 ? 0.0 : _uploadDone / _uploadTotal;
    return Container(
      width: _replyThumbSize,
      height: _replyThumbSize,
      decoration: BoxDecoration(
        color: theme.bgColorContainer,
        borderRadius: BorderRadius.circular(theme.radiusLarge),
      ),
      child: Center(
        child: SizedBox(
          width: 20,
          height: 20,
          child: TProgress.microCircular(value: progress),
        ),
      ),
    );
  }

  /// TDesign 圆形图标发送按钮：发送中显示 loading 并整体禁用。
  Widget _buildSendButton({required bool sending, required bool canSend}) {
    final disabled = sending || _uploadingImage;
    return Semantics(
      button: true,
      enabled: canSend && !disabled,
      label: sending ? '发送中' : '发送',
      child: TButton(
        size: TButtonSize.large,
        shape: TButtonShape.circle,
        colorPreset: TButtonColorPreset.primary,
        onPressed: disabled
            ? null
            : () {
                if (!canSend) {
                  AppFeedback.toast(context, '请输入回复内容或上传图片');
                  _replyFocus.requestFocus();
                  return;
                }
                unawaited(_sendReply());
              },
        icon: sending
            ? const TLoading(size: 18)
            : const Icon(TIcons.send, size: 20),
      ),
    );
  }

  Widget _buildClosedBar(BuildContext context) {
    return TNoticeBar(
      status: TNoticeBarStatus.info,
      content: '工单已关闭，无法继续回复',
      prefix: Icon(
        TIcons.lock_on,
        size: 18,
        color: context.tTheme.textColorPlaceholder,
      ),
    );
  }
}
