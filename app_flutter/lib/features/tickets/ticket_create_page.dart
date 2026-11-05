import 'dart:async';

import 'package:dio/dio.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../../core/layout/responsive.dart';
import '../../../core/net/app_services.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../widgets/app_feedback.dart';
import '../../../widgets/app_kit.dart';
import '../../../widgets/data_state.dart';

/// 新建工单：主题 / 部门 / 优先级 / 关联服务 / 内容 / 图片。
///
/// - 表单使用 TDesign `TForm` + `TFormItem`，输入使用 `TInput` / `TTextarea`；
///   部门与优先级使用 [AppChip] 选择，关联服务沿用 TDesign `TDialog` + `TCell` 选择器。
/// - 必填项未完成时底部提交按钮禁用，点击「禁用按钮」仅在对应段落下方显示就近错误；
/// - 图片最多 3 张，选中后逐张上传并展示批次进度，提交时只携带后端返回的 `path`；
/// - 有未提交内容时离开页面先二次确认。
class TicketCreatePage extends StatefulWidget {
  const TicketCreatePage({super.key});

  @override
  State<TicketCreatePage> createState() => _TicketCreatePageState();
}

/// 已上传图片：`path` 用于提交，`url` 用于本地缩略图展示。
class _UploadedImage {
  const _UploadedImage({required this.path, required this.url});

  final String path;
  final String url;
}

class _TicketCreatePageState extends State<TicketCreatePage> {
  static const int _maxImages = 3;
  static const int _maxContentLength = 10000;
  static const int _contentWarnLength = 9000;

  static const List<({String value, String label})> _departments = [
    (value: 'sales', label: '销售'),
    (value: 'support', label: '技术支持'),
    (value: 'billing', label: '财务'),
    (value: 'abuse', label: '投诉'),
  ];

  static const List<({int value, String label})> _priorities = [
    (value: 1, label: '低'),
    (value: 2, label: '中'),
    (value: 3, label: '高'),
    (value: 4, label: '紧急'),
  ];

  final _subjectController = TextEditingController();
  final _contentController = TextEditingController();
  final _subjectFocus = FocusNode();
  final _contentFocus = FocusNode();

  String? _department;
  int _priority = 2;

  int? _serviceId;
  String _serviceName = '';
  String _servicePath = '';
  String _serviceStatusLabel = '';
  String _serviceStatusTone = '';

  List<Map<String, dynamic>> _serviceOptions = [];
  bool _loadingServices = false;

  final List<_UploadedImage> _images = [];
  bool _uploading = false;
  int _uploadTotal = 0;
  int _uploadDone = 0;

  bool _submitting = false;
  bool _submitted = false;
  bool _revealed = false;
  bool _subjectTouched = false;
  bool _contentTouched = false;
  bool _confirmingLeave = false;

  @override
  void initState() {
    super.initState();
    _subjectFocus.addListener(_onSubjectFocusChanged);
    _contentFocus.addListener(_onContentFocusChanged);
  }

  @override
  void dispose() {
    _subjectFocus.removeListener(_onSubjectFocusChanged);
    _contentFocus.removeListener(_onContentFocusChanged);
    _subjectController.dispose();
    _contentController.dispose();
    _subjectFocus.dispose();
    _contentFocus.dispose();
    super.dispose();
  }

  /// 离开必填输入框时点亮就近错误，用户不用等提交才知道缺了什么。
  void _onSubjectFocusChanged() {
    if (!_subjectFocus.hasFocus && mounted) {
      setState(() => _subjectTouched = true);
    }
  }

  void _onContentFocusChanged() {
    if (!_contentFocus.hasFocus && mounted) {
      setState(() => _contentTouched = true);
    }
  }

  // ── 状态推导 ────────────────────────────────────────────────

  String get _prioritySummary {
    return switch (_priority) {
      1 => '低优先级',
      3 => '高优先级',
      4 => '紧急',
      _ => '中等优先级',
    };
  }

  /// 主题、部门必填；内容与图片至少一项。
  bool get _isValid {
    return _subjectController.text.trim().isNotEmpty &&
        (_department ?? '').isNotEmpty &&
        (_contentController.text.trim().isNotEmpty || _images.isNotEmpty);
  }

  bool get _canSubmit => _isValid && !_uploading;

  bool get _isDirty {
    return _subjectController.text.trim().isNotEmpty ||
        _contentController.text.trim().isNotEmpty ||
        _department != null ||
        _priority != 2 ||
        _serviceId != null ||
        _images.isNotEmpty;
  }

  /// 主题缺失：用户输入后清空并离开输入框才提示。
  bool get _subjectMissing =>
      _subjectController.text.trim().isEmpty && _subjectTouched;

  /// 内容缺失：内容与图片都没有，且用户离开过内容框。
  bool get _contentMissing =>
      _contentController.text.trim().isEmpty &&
      _images.isEmpty &&
      _contentTouched;

  /// 部门缺失：胶囊没有失焦时机，点过底部禁用按钮后才提示。
  bool get _departmentMissing => _revealed && (_department ?? '').isEmpty;

  // ── 选择服务 ────────────────────────────────────────────────

  Future<void> _pickService() async {
    if (_loadingServices) return;
    final theme = context.tTheme;
    if (_serviceOptions.isEmpty) {
      setState(() => _loadingServices = true);
      final api = AppServices.of(context).services;
      try {
        final data = await api.list(page: 1, pageSize: 50);
        if (!mounted) return;
        setState(() {
          _serviceOptions = listOf(data['list']);
          _loadingServices = false;
        });
      } catch (error) {
        if (!mounted) return;
        setState(() => _loadingServices = false);
        AppFeedback.error(context, AppServices.errorText(error));
        return;
      }
    }
    if (!mounted) return;

    final selected = await TDialog.show<int>(
      context,
      dialog: TDialog(
        title: const Text('选择关联服务'),
        content: SizedBox(
          width: double.infinity,
          height: 320,
          child: ListView(
            shrinkWrap: true,
            children: [
              TCell(
                title: const Text('不关联服务'),
                note: _serviceId == null
                    ? Icon(TIcons.check, size: 18, color: theme.brandColor)
                    : null,
                onTap: () => Navigator.of(context, rootNavigator: true).pop(-1),
              ),
              for (final option in _serviceOptions)
                TCell(
                  title: Text(_serviceLabel(option)),
                  subtitle: switch (_serviceSubtitle(option)) {
                    final subtitle? => Text(subtitle),
                    null => null,
                  },
                  note: intOf(option, 'id') == _serviceId
                      ? Icon(TIcons.check, size: 18, color: theme.brandColor)
                      : null,
                  onTap: () => Navigator.of(
                    context,
                    rootNavigator: true,
                  ).pop(intOf(option, 'id')),
                ),
            ],
          ),
        ),
        actions: [
          TDialogAction(child: const Text('取消'), variant: TButtonVariant.text),
        ],
      ),
    );
    if (!mounted || selected == null) return;
    setState(() {
      if (selected <= 0) {
        _serviceId = null;
        _serviceName = '';
        _servicePath = '';
        _serviceStatusLabel = '';
        _serviceStatusTone = '';
      } else {
        _serviceId = selected;
        final matched = _serviceOptions
            .where((option) => intOf(option, 'id') == selected)
            .toList();
        final option = matched.isEmpty ? <String, dynamic>{} : matched.first;
        _serviceName = option.isEmpty ? '' : _serviceLabel(option);
        _servicePath = _serviceSubtitle(option) ?? '';
        _serviceStatusLabel = strOf(option, 'status_label');
        _serviceStatusTone = strOf(option, 'status_tone');
      }
    });
  }

  String _serviceLabel(Map<String, dynamic> option) {
    final display = strOf(option, 'product_display_name');
    if (display.isNotEmpty) return display;
    final name = strOf(option, 'name');
    return name.isNotEmpty ? name : '服务 #${intOf(option, 'id')}';
  }

  String? _serviceSubtitle(Map<String, dynamic> option) {
    final path = strOf(option, 'product_full_path');
    if (path.isNotEmpty) return path;
    final domain = strOf(option, 'domain');
    return domain.isNotEmpty ? domain : null;
  }

  // ── 图片 ────────────────────────────────────────────────────

  Future<void> _pickImages() async {
    if (_uploading) return;
    final remaining = _maxImages - _images.length;
    if (remaining <= 0) {
      AppFeedback.toast(context, '最多上传 $_maxImages 张图片');
      return;
    }
    final api = AppServices.of(context).tickets;
    final picker = ImagePicker();
    List<XFile> picked;
    try {
      picked = await picker.pickMultiImage(imageQuality: 82, limit: remaining);
    } catch (_) {
      if (mounted) AppFeedback.error(context, '选择图片失败，请重试');
      return;
    }
    if (!mounted || picked.isEmpty) return;
    if (picked.length > remaining) picked = picked.sublist(0, remaining);

    setState(() {
      _uploading = true;
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
        final url = strOf(data, 'url');
        if (path.isEmpty) {
          throw StateError('图片上传返回缺少 path');
        }
        if (!mounted) return;
        setState(() {
          _images.add(_UploadedImage(path: path, url: url));
          _uploadDone += 1;
        });
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) {
        setState(() {
          _uploading = false;
          _uploadTotal = 0;
          _uploadDone = 0;
        });
      }
    }
  }

  Future<void> _removeImage(_UploadedImage image) async {
    final confirmed = await AppFeedback.confirm(
      context,
      title: '移除图片',
      content: '确定从工单中移除这张图片吗？',
      confirmText: '移除',
      danger: true,
    );
    if (!confirmed || !mounted) return;
    setState(() => _images.remove(image));
  }

  // ── 提交与离开确认 ──────────────────────────────────────────

  /// 提交被拦下时（例如点到了禁用按钮）点亮全部缺失项，并把焦点带到第一处。
  void _revealErrors() {
    setState(() {
      _revealed = true;
      _subjectTouched = true;
      _contentTouched = true;
    });
    if (_subjectController.text.trim().isEmpty) {
      _subjectFocus.requestFocus();
    } else if (_contentController.text.trim().isEmpty && _images.isEmpty) {
      _contentFocus.requestFocus();
    }
  }

  /// 禁用按钮上的文案直接说明还差什么，用户不必等报错。
  String get _submitLabel {
    if (_uploading) return '图片上传中…';
    if (_subjectController.text.trim().isEmpty) return '请填写工单主题';
    if ((_department ?? '').isEmpty) return '请选择工单部门';
    if (_contentController.text.trim().isEmpty && _images.isEmpty) {
      return '请填写问题描述';
    }
    return '提交工单';
  }

  Future<void> _submit() async {
    if (_submitting) return;
    if (!_isValid) {
      _revealErrors();
      return;
    }
    if (_uploading) {
      AppFeedback.toast(context, '图片上传中，请稍候');
      return;
    }
    final subject = _subjectController.text.trim();
    final content = _contentController.text.trim();
    final department = _department!;

    FocusScope.of(context).unfocus();
    setState(() => _submitting = true);
    try {
      final api = AppServices.of(context).tickets;
      await api.create(
        subject: subject,
        department: department,
        content: content,
        priority: _priority,
        serviceId: _serviceId,
        attachments: _images.map((image) => image.path).toList(),
      );
      if (!mounted) return;
      _submitted = true;
      AppFeedback.success(context, '工单提交成功');
      context.pop(true);
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<bool> _confirmLeave() async {
    if (_confirmingLeave) return false;
    _confirmingLeave = true;
    try {
      return await AppFeedback.confirm(
        context,
        title: '离开此页？',
        content: '已填写的内容不会保存，确定离开吗？',
        confirmText: '离开',
        cancelText: '继续编辑',
        danger: true,
      );
    } finally {
      _confirmingLeave = false;
    }
  }

  Future<void> _handleBack() async {
    if (_submitting || _submitted) return;
    if (!_isDirty) {
      _popPage(false);
      return;
    }
    final leave = await _confirmLeave();
    if (!mounted || !leave) return;
    _popPage(false);
  }

  void _popPage(bool result) {
    if (context.canPop()) {
      context.pop(result);
    } else {
      context.go('/tickets');
    }
  }

  // ── 构建 ────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return PopScope<Object?>(
      canPop: !_isDirty && !_submitting && !_submitted,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        unawaited(_handleBack());
      },
      child: AppScaffold(
        title: '新建工单',
        onBack: () => unawaited(_handleBack()),
        bottomBar: _buildSubmitBar(context),
        body: GestureDetector(
          behavior: HitTestBehavior.translucent,
          onTap: () => FocusScope.of(context).unfocus(),
          child: _buildForm(context),
        ),
      ),
    );
  }

  Widget _buildForm(BuildContext context) {
    final form = TForm(
      child: ListView(
        padding: const EdgeInsets.only(bottom: AppSpacing.pageBottom),
        children: [
          _buildSubjectItem(context),
          _buildDepartmentItem(context),
          _buildPriorityItem(context),
          _buildServiceItem(context),
          _buildContentItem(context),
          _buildAttachmentItem(context),
        ],
      ),
    );
    // 平板时表单限宽居中：表单字段过宽会难以扫读。
    if (!context.isTablet) return form;
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: Breakpoints.formMaxWidth),
        child: form,
      ),
    );
  }

  /// 主题输入项：必填，失焦后为空才提示。
  Widget _buildSubjectItem(BuildContext context) {
    return TFormItem(
      label: '主题',
      required: true,
      help: _subjectMissing ? null : '请简要描述问题，便于客服快速定位',
      errorText: _subjectMissing ? '请输入工单主题' : null,
      child: TInput(
        controller: _subjectController,
        focusNode: _subjectFocus,
        hintText: '请简要描述问题',
        maxLength: 200,
        onChanged: (_) => setState(() {}),
      ),
    );
  }

  /// 部门选择项：必填，点过禁用提交后才提示。
  /// 平板时四个部门胶囊排成一行（`Wrap` 不换行），手机按需换行。
  Widget _buildDepartmentItem(BuildContext context) {
    return TFormItem(
      label: '部门',
      required: true,
      errorText: _departmentMissing ? '请选择工单部门' : null,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapS),
        child: _buildChips([
          for (final department in _departments)
            AppChip(
              label: department.label,
              selected: _department == department.value,
              onTap: () => setState(() => _department = department.value),
            ),
        ]),
      ),
    );
  }

  Widget _buildPriorityItem(BuildContext context) {
    return TFormItem(
      label: '优先级',
      help: _prioritySummary,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapS),
        child: _buildChips([
          for (final priority in _priorities)
            AppChip(
              label: priority.label,
              selected: _priority == priority.value,
              onTap: () => setState(() => _priority = priority.value),
            ),
        ]),
      ),
    );
  }

  /// 胶囊行：平板固定一行排开（4 个短标签在 560 表单宽度内放得下），
  /// 手机用 `Wrap` 自然换行。
  Widget _buildChips(List<Widget> chips) {
    final spacing = AppSpacing.gapS;
    if (!context.isTablet) {
      return Wrap(spacing: spacing, runSpacing: spacing, children: chips);
    }
    return Row(
      children: [
        for (var i = 0; i < chips.length; i++) ...[
          if (i > 0) SizedBox(width: spacing),
          chips[i],
        ],
      ],
    );
  }

  Widget _buildServiceItem(BuildContext context) {
    return TFormItem(
      label: '关联服务',
      help: '可选；不关联时提交不带服务信息',
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => unawaited(_pickService()),
        child: _buildServicePicker(context.tTheme),
      ),
    );
  }

  Widget _buildContentItem(BuildContext context) {
    final theme = context.tTheme;
    return TFormItem(
      label: '问题描述',
      required: true,
      verticalAlignment: TFormItemVerticalAlignment.start,
      help: _contentMissing ? null : '填写问题详情或上传图片（至少一项）',
      errorText: _contentMissing ? '请填写工单内容或上传图片' : null,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapS),
        child: ValueListenableBuilder<TextEditingValue>(
          valueListenable: _contentController,
          builder: (context, value, _) {
            final length = value.text.characters.length;
            final warning = length > _contentWarnLength;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TTextarea(
                  controller: _contentController,
                  focusNode: _contentFocus,
                  hintText: '请详细描述问题，便于我们快速处理',
                  maxLines: 6,
                  minLines: 4,
                  maxLength: _maxContentLength,
                  indicator: true,
                  status: _contentMissing
                      ? TInputStatus.error
                      : (warning ? TInputStatus.warning : TInputStatus.normal),
                  onChanged: (_) => setState(() {}),
                ),
                if (warning)
                  Padding(
                    padding: const EdgeInsets.only(top: AppSpacing.gapXs),
                    child: Text(
                      '内容接近 $_maxContentLength 字上限',
                      style: AppType.caption(
                        theme,
                      ).copyWith(color: theme.warningColor),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }

  /// 关联服务选择行：已选时展示服务名与状态标签，未选时展示占位文案。
  Widget _buildServicePicker(TThemeData theme) {
    final hasService = _serviceId != null;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.gapS),
      child: Row(
        children: [
          Expanded(
            child: hasService
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              _serviceName.isNotEmpty
                                  ? _serviceName
                                  : '服务 #$_serviceId',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: AppType.body(theme),
                            ),
                          ),
                          if (_serviceStatusLabel.isNotEmpty) ...[
                            const SizedBox(width: AppSpacing.gapS),
                            AppStatusTag(
                              label: _serviceStatusLabel,
                              preset: AppStatusTag.presetOf(_serviceStatusTone),
                            ),
                          ],
                        ],
                      ),
                      if (_servicePath.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          _servicePath,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppType.caption(theme),
                        ),
                      ],
                    ],
                  )
                : Text(
                    '不关联服务（可选）',
                    style: AppType.secondary(
                      theme,
                    ).copyWith(color: theme.textColorPlaceholder),
                  ),
          ),
          if (_loadingServices)
            const TLoading(size: 16)
          else
            Icon(
              TIcons.chevron_right,
              size: 20,
              color: theme.textColorPlaceholder,
            ),
        ],
      ),
    );
  }

  /// 附件项：图片上传与进度，右侧展示张数。
  Widget _buildAttachmentItem(BuildContext context) {
    final theme = context.tTheme;
    final remaining = _maxImages - _images.length;
    return TFormItem(
      label: '附件',
      verticalAlignment: TFormItemVerticalAlignment.start,
      help: remaining > 0
          ? '支持 JPG/PNG，剩余可添加 $remaining 张'
          : '已达上传上限（$_maxImages 张）',
      extra: Text(
        '${_images.length}/$_maxImages',
        style: AppType.caption(theme),
      ),
      child: Padding(
        padding: const EdgeInsets.only(top: AppSpacing.gapS),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_uploading)
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.gapS),
                child: Text(
                  '正在上传 $_uploadDone/$_uploadTotal 张…',
                  style: AppType.caption(
                    theme,
                  ).copyWith(color: theme.brandColor),
                ),
              ),
            _buildImageSection(context),
          ],
        ),
      ),
    );
  }

  Widget _buildImageSection(BuildContext context) {
    final theme = context.tTheme;
    final canAdd = _images.length < _maxImages;
    final progress = _uploadTotal == 0 ? 0.0 : _uploadDone / _uploadTotal;
    return Wrap(
      spacing: AppSpacing.gapM,
      runSpacing: AppSpacing.gapM,
      children: [
        for (final image in _images)
          SizedBox(
            width: 80,
            height: 80,
            child: Stack(
              fit: StackFit.expand,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(theme.radiusLarge),
                  child: image.url.isNotEmpty
                      ? Image.network(
                          image.url,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => Container(
                            color: theme.bgColorSecondaryContainer,
                            child: Icon(
                              TIcons.image_error,
                              size: 24,
                              color: theme.textColorPlaceholder,
                            ),
                          ),
                        )
                      : Container(
                          color: theme.bgColorSecondaryContainer,
                          child: Icon(
                            TIcons.image,
                            size: 24,
                            color: theme.textColorPlaceholder,
                          ),
                        ),
                ),
                Positioned(
                  top: 4,
                  right: 4,
                  child: GestureDetector(
                    onTap: () => unawaited(_removeImage(image)),
                    child: Icon(
                      TIcons.close_circle,
                      size: 18,
                      color: theme.textColorPrimary,
                    ),
                  ),
                ),
              ],
            ),
          ),
        if (_uploading)
          Container(
            width: 80,
            height: 80,
            decoration: BoxDecoration(
              color: theme.bgColorSecondaryContainer,
              borderRadius: BorderRadius.circular(theme.radiusLarge),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                SizedBox(
                  width: 24,
                  height: 24,
                  child: TProgress.microCircular(value: progress),
                ),
                const SizedBox(height: AppSpacing.gapXs),
                Text(
                  '${(progress * 100).round()}%',
                  style: AppType.caption(theme),
                ),
              ],
            ),
          )
        else if (canAdd)
          GestureDetector(
            onTap: () => unawaited(_pickImages()),
            child: Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                color: theme.bgColorPage,
                borderRadius: BorderRadius.circular(theme.radiusLarge),
                border: Border.all(color: theme.borderLevel1Color),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(TIcons.add, size: 24, color: theme.textColorSecondary),
                  const SizedBox(height: AppSpacing.gapXs),
                  Text('添加', style: AppType.caption(theme)),
                ],
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildSubmitBar(BuildContext context) {
    return ListenableBuilder(
      listenable: Listenable.merge([_subjectController, _contentController]),
      builder: (context, _) {
        final blocked = !_isValid && !_submitting;
        return SizedBox(
          height: 48,
          child: Stack(
            fit: StackFit.expand,
            children: [
              TButton(
                size: TButtonSize.large,
                colorPreset: TButtonColorPreset.primary,
                // 必填未齐时保持禁用态；点击由透明层接管，点亮就近错误而不是毫无反应。
                onPressed: _canSubmit && !_submitting
                    ? () => unawaited(_submit())
                    : null,
                child: _submitting ? const Text('提交中…') : Text(_submitLabel),
              ),
              if (blocked)
                GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTap: _revealErrors,
                  child: const SizedBox.expand(),
                ),
            ],
          ),
        );
      },
    );
  }
}
