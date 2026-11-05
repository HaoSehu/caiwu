import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/net/app_services.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/util/format.dart';
import '../../widgets/app_kit.dart';
import '../../widgets/data_state.dart';

/// 公告详情。正文按纯文本渲染（剥离 HTML 标签），不使用 WebView。
///
/// 版式：一张卡片承载「标题 → 元信息 → 分隔线 → 正文」，
/// 正文以 1.7 行高、段落间留白呈现，避免整段文字贴在一起。
class NoticeDetailPage extends StatefulWidget {
  const NoticeDetailPage({super.key, required this.noticeId});

  final int noticeId;

  @override
  State<NoticeDetailPage> createState() => _NoticeDetailPageState();
}

class _NoticeDetailPageState extends State<NoticeDetailPage> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _article;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final services = AppServices.of(context);
    try {
      final article = await services.content.noticeDetail(widget.noticeId);
      // 进入即标记已读；失败不阻断阅读。
      try {
        await services.content.markNoticeRead(widget.noticeId);
      } catch (_) {}
      if (!mounted) return;
      setState(() {
        _article = article;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = AppServices.errorText(error);
        _loading = false;
      });
    }
  }

  /// 轻量 HTML → 纯文本：去标签、还原常见实体、保留换行。
  String _plainText(String raw) {
    if (raw.trim().isEmpty) return '';
    var text = raw
        .replaceAll(RegExp(r'<br\s*/?>', caseSensitive: false), '\n')
        .replaceAll(RegExp(r'</p\s*>', caseSensitive: false), '\n\n')
        .replaceAll(RegExp(r'<[^>]+>'), '')
        .replaceAll('&nbsp;', ' ')
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .replaceAll('&amp;', '&')
        .replaceAll('&quot;', '"')
        .replaceAll('&#39;', "'");
    return text.replaceAll(RegExp(r'\n{3,}'), '\n\n').trim();
  }

  /// 纯文本 → 段落列表：段内换行合并为空格，段间保留。
  List<String> _paragraphs(String text) {
    if (text.trim().isEmpty) return const [];
    return text
        .split(RegExp(r'\n\s*\n'))
        .map(
          (paragraph) => paragraph.replaceAll(RegExp(r'\s*\n\s*'), ' ').trim(),
        )
        .where((paragraph) => paragraph.isNotEmpty)
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    return AppScaffold(
      title: '公告详情',
      body: DataState(
        loading: _loading,
        error: _error,
        retry: _load,
        child: _buildContent(context),
      ),
    );
  }

  Widget _buildContent(BuildContext context) {
    final theme = context.tTheme;
    final article = _article;
    if (article == null) return const SizedBox.shrink();
    final title = strOf(article, 'title').trim();
    final publishAt = strOf(article, 'publish_at').isNotEmpty
        ? strOf(article, 'publish_at')
        : strOf(article, 'created_at');
    final category = strOf(article, 'category_name').trim();
    final paragraphs = _paragraphs(_plainText(strOf(article, 'content')));

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(
          context.pagePadding,
          AppSpacing.gapM,
          context.pagePadding,
          AppSpacing.pageBottom,
        ),
        children: [
          // 正文阅读舒适宽度 720：超宽屏上居中收窄，避免长行难扫读。
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 720),
              child: AppCard(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title.isEmpty ? '(无标题)' : title,
                      style: AppType.pageTitle(theme).copyWith(height: 1.4),
                    ),
                    const SizedBox(height: AppSpacing.gapM),
                    Text(
                      [category, formatDateTime(publishAt)]
                          .where(
                            (text) => text.trim().isNotEmpty && text != '--',
                          )
                          .join(' · '),
                      style: AppType.caption(theme),
                    ),
                    const SizedBox(height: AppSpacing.gapS),
                    TDivider(),
                    if (paragraphs.isEmpty)
                      Text('暂无正文', style: AppType.secondary(theme))
                    else
                      for (var i = 0; i < paragraphs.length; i++) ...[
                        if (i > 0) const SizedBox(height: AppSpacing.gapL),
                        Text(
                          paragraphs[i],
                          style: AppType.body(theme).copyWith(height: 1.7),
                        ),
                      ],
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
