import 'dart:async';

import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/layout/responsive.dart';
import '../../core/theme/app_tokens.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import '../../widgets/data_state.dart';
import 'host_store.dart';

/// SSH 主机新增/编辑页。
///
/// `hostId == null` 为新建，否则从 [SshHostStore] 读取现有配置。
/// 密码/私钥单独存 Keystore：编辑态留空表示保留已保存的密钥，不覆盖。
class SshHostEditPage extends StatefulWidget {
  const SshHostEditPage({super.key, this.hostId});

  final String? hostId;

  @override
  State<SshHostEditPage> createState() => _SshHostEditPageState();
}

class _SshHostEditPageState extends State<SshHostEditPage> {
  final SshHostStore _store = SshHostStore();

  final _labelController = TextEditingController();
  final _hostController = TextEditingController();
  final _portController = TextEditingController(text: '22');
  final _userController = TextEditingController(text: 'root');
  final _secretController = TextEditingController();
  final _remarkController = TextEditingController();

  final _hostFocus = FocusNode();
  final _portFocus = FocusNode();
  final _userFocus = FocusNode();
  final _secretFocus = FocusNode();

  SshHost? _original;
  bool _loading = true;
  bool _loadDone = false;
  bool _missing = false;

  /// 本地存储读取失败的原因；非空时显示错误态。
  String? _loadError;
  bool _hasSavedSecret = false;

  SshAuthType _authType = SshAuthType.password;
  String? _jumpHostId;
  int _keepAliveSeconds = 30;
  bool _saving = false;

  String? _hostError;
  String? _portError;
  String? _userError;
  String? _secretError;

  bool get _isEdit => widget.hostId != null;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  @override
  void dispose() {
    // load() 尚未结束时不能在 dispose 里销毁 store，否则完成回调 notifyListeners
    // 会命中已销毁的 ChangeNotifier；改由 _load 在完成后补一次销毁。
    if (_loadDone) _store.dispose();
    _labelController.dispose();
    _hostController.dispose();
    _portController.dispose();
    _userController.dispose();
    _secretController.dispose();
    _remarkController.dispose();
    _hostFocus.dispose();
    _portFocus.dispose();
    _userFocus.dispose();
    _secretFocus.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      await _store.load();
    } catch (_) {
      // 本地存储读取失败时不能停在 _loading：页面会永远转圈。
      if (mounted) {
        setState(() {
          _loading = false;
          _loadError = '服务器配置读取失败';
        });
      }
      _loadDone = true;
      if (!mounted) _store.dispose();
      return;
    }
    _loadDone = true;
    if (!mounted) {
      _store.dispose();
      return;
    }
    final id = widget.hostId;
    if (id == null) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    final host = _store.byId(id);
    if (host == null) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _missing = true;
      });
      return;
    }
    final hasSecret = await _store.hasSecret(id);
    if (!mounted) return;
    setState(() {
      _original = host;
      _hasSavedSecret = hasSecret;
      _authType = host.authType;
      _jumpHostId = host.jumpHostId;
      _keepAliveSeconds = host.keepAliveSeconds;
      _labelController.text = host.label;
      _hostController.text = host.host;
      _portController.text = '${host.port}';
      _userController.text = host.username;
      _remarkController.text = host.remark;
      _loading = false;
    });
  }

  // ── 校验与保存 ───────────────────────────────────────────────────────

  /// 主机地址：IPv4 / IPv6 / 含点域名（至少一级点，顶级域为字母）。
  bool _isValidHost(String value) {
    if (RegExp(r'^\d{1,3}(\.\d{1,3}){3}$').hasMatch(value)) return true;
    if (value.contains(':')) return true;
    return RegExp(
      r'^[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$',
    ).hasMatch(value);
  }

  bool _validate() {
    final host = _hostController.text.trim();
    final port = int.tryParse(_portController.text.trim());
    final user = _userController.text.trim();
    final secret = _secretController.text;

    setState(() {
      _hostError = host.isEmpty
          ? '请输入主机地址'
          : (!_isValidHost(host) ? '请输入正确的域名或 IP 地址' : null);
      _portError = port == null || port < 1 || port > 65535
          ? '端口范围为 1–65535'
          : null;
      _userError = user.isEmpty ? '请输入登录用户名' : null;
      // 编辑态已有保存的密钥时允许留空（表示不修改）。
      final secretRequired = !(_isEdit && _hasSavedSecret);
      _secretError = secretRequired && secret.trim().isEmpty
          ? (_authType == SshAuthType.password ? '请输入登录密码' : '请粘贴 SSH 私钥')
          : null;
    });

    if (_hostError != null) {
      _hostFocus.requestFocus();
      return false;
    }
    if (_portError != null) {
      _portFocus.requestFocus();
      return false;
    }
    if (_userError != null) {
      _userFocus.requestFocus();
      return false;
    }
    if (_secretError != null) {
      _secretFocus.requestFocus();
      return false;
    }
    return true;
  }

  Future<void> _save() async {
    if (_saving || !_validate()) return;
    FocusScope.of(context).unfocus();

    final id =
        widget.hostId ?? DateTime.now().microsecondsSinceEpoch.toString();
    final original = _original;
    final host = SshHost(
      id: id,
      label: _labelController.text.trim(),
      host: _hostController.text.trim(),
      port: int.parse(_portController.text.trim()),
      username: _userController.text.trim(),
      authType: _authType,
      remark: _remarkController.text.trim(),
      jumpHostId: _jumpHostId,
      keepAliveSeconds: _keepAliveSeconds,
      createdAt: original?.createdAt ?? DateTime.now(),
      lastConnectedAt: original?.lastConnectedAt,
    );

    setState(() => _saving = true);
    try {
      final secret = _secretController.text;
      // 编辑态留空 = 不修改已保存的密码/私钥。
      if (secret.isNotEmpty) {
        await _store.saveSecret(id, secret);
      }
      await _store.save(host);
      if (!mounted) return;
      AppFeedback.success(context, _isEdit ? '服务器已更新' : '服务器已添加');
      context.pop(true);
    } catch (_) {
      if (mounted) AppFeedback.error(context, '保存失败，请稍后重试');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _delete() async {
    final host = _original;
    if (host == null) return;
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
    context.pop(true);
  }

  // ── 构建 ─────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    if (_loadError != null) {
      return AppScaffold(
        title: _isEdit ? '编辑服务器' : '添加服务器',
        body: DataState(
          loading: false,
          error: _loadError,
          retry: () {
            setState(() {
              _loadError = null;
              _loading = true;
            });
            unawaited(_load());
          },
          child: const SizedBox.shrink(),
        ),
      );
    }
    if (_loading) {
      return AppScaffold(
        title: _isEdit ? '编辑服务器' : '添加服务器',
        body: const DataState(loading: true, child: SizedBox.shrink()),
      );
    }
    if (_missing) {
      return AppScaffold(
        title: '编辑服务器',
        body: Center(
          child: TEmpty(
            icon: TIcons.server,
            emptyText: '服务器不存在或已被删除',
            operation: TButton(
              size: TButtonSize.small,
              variant: TButtonVariant.outline,
              onPressed: () => context.pop(),
              child: const Text('返回'),
            ),
          ),
        ),
      );
    }

    return AppScaffold(
      title: _isEdit ? '编辑服务器' : '添加服务器',
      body: GestureDetector(
        behavior: HitTestBehavior.translucent,
        onTap: () => FocusScope.of(context).unfocus(),
        // 表单限宽 560 居中：字段过宽会难以扫读（平板尤其明显）。
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(
              maxWidth: Breakpoints.formMaxWidth,
            ),
            child: ListView(
              // TDesign 表单是通栏白底分组，左右不留页面内边距。
              padding: const EdgeInsets.only(
                top: AppSpacing.gapM,
                bottom: AppSpacing.pageBottom,
              ),
              children: [
                _formFrame(_buildConnectionCard()),
                const SizedBox(height: AppSpacing.gapM),
                _formFrame(_buildAuthCard()),
                const SizedBox(height: AppSpacing.gapM),
                _formFrame(_buildAdvancedCard()),
                if (_isEdit) ...[
                  const SizedBox(height: AppSpacing.gapL),
                  Center(
                    child: TButton(
                      size: TButtonSize.small,
                      variant: TButtonVariant.text,
                      colorPreset: TButtonColorPreset.danger,
                      icon: const Icon(TIcons.delete, size: 16),
                      onPressed: _saving ? null : _delete,
                      child: const Text('删除该服务器'),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
      bottomBar: context.isTablet
          ? Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(
                  maxWidth: Breakpoints.formMaxWidth,
                ),
                child: _buildSaveButton(),
              ),
            )
          : _buildSaveButton(),
    );
  }

  Widget _buildSaveButton() {
    return SizedBox(
      height: 48,
      child: TButton(
        size: TButtonSize.large,
        variant: TButtonVariant.fill,
        colorPreset: TButtonColorPreset.primary,
        onPressed: _saving ? null : _save,
        child: _saving
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Colors.white,
                ),
              )
            : Text(_isEdit ? '保存修改' : '添加服务器'),
      ),
    );
  }

  /// 平板：给通栏白底表单补上与其它页一致的卡片圆角与描边；手机保持通栏。
  Widget _formFrame(Widget child) {
    if (!context.isTablet) return child;
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: context.tTheme.borderLevel1Color),
      ),
      clipBehavior: Clip.antiAlias,
      child: child,
    );
  }

  // ── 连接信息 ─────────────────────────────────────────────────────────

  Widget _buildConnectionCard() {
    return TForm(
      child: Column(
        children: [
          TFormItem(
            label: '名称',
            help: '可选，留空时显示为 主机:端口',
            child: TInput(
              controller: _labelController,
              hintText: '例如：生产环境 Web',
              clearButtonMode: TInputClearButtonMode.always,
            ),
          ),
          TFormItem(
            label: '主机地址',
            required: true,
            errorText: _hostError,
            child: TInput(
              controller: _hostController,
              focusNode: _hostFocus,
              hintText: 'example.com 或 203.0.113.10',
              inputType: TextInputType.url,
              status: _hostError == null
                  ? TInputStatus.normal
                  : TInputStatus.error,
              onChanged: (_) {
                if (_hostError != null) setState(() => _hostError = null);
              },
            ),
          ),
          TFormItem(
            label: '端口',
            required: true,
            errorText: _portError,
            child: TInput(
              controller: _portController,
              focusNode: _portFocus,
              hintText: '22',
              inputType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              status: _portError == null
                  ? TInputStatus.normal
                  : TInputStatus.error,
              onChanged: (_) {
                if (_portError != null) setState(() => _portError = null);
              },
            ),
          ),
          TFormItem(
            label: '用户名',
            required: true,
            errorText: _userError,
            child: TInput(
              controller: _userController,
              focusNode: _userFocus,
              hintText: 'root',
              status: _userError == null
                  ? TInputStatus.normal
                  : TInputStatus.error,
              onChanged: (_) {
                if (_userError != null) setState(() => _userError = null);
              },
            ),
          ),
        ],
      ),
    );
  }

  // ── 认证方式 ─────────────────────────────────────────────────────────

  Widget _buildAuthCard() {
    final isPassword = _authType == SshAuthType.password;
    final savedHint = _isEdit && _hasSavedSecret ? '已保存，留空则不修改' : null;
    return TForm(
      child: Column(
        children: [
          TFormItem(
            label: '认证方式',
            verticalAlignment: TFormItemVerticalAlignment.center,
            child: Align(
              alignment: AlignmentDirectional.centerEnd,
              child: TRadioGroup<SshAuthType>(
                value: _authType,
                onChanged: (value) => setState(() {
                  _authType = value;
                  _secretError = null;
                }),
                child: Wrap(
                  alignment: WrapAlignment.end,
                  spacing: AppSpacing.gapXl,
                  children: const [
                    TRadio<SshAuthType>(
                      value: SshAuthType.password,
                      title: '密码',
                      variant: TRadioVariant.inline,
                      size: TRadioSize.small,
                    ),
                    TRadio<SshAuthType>(
                      value: SshAuthType.privateKey,
                      title: '私钥',
                      variant: TRadioVariant.inline,
                      size: TRadioSize.small,
                    ),
                  ],
                ),
              ),
            ),
          ),
          if (isPassword)
            TFormItem(
              label: '登录密码',
              required: true,
              errorText: _secretError,
              child: TInput(
                controller: _secretController,
                focusNode: _secretFocus,
                hintText: savedHint ?? '请输入 SSH 登录密码',
                obscureText: true,
                showPasswordToggle: true,
                status: _secretError == null
                    ? TInputStatus.normal
                    : TInputStatus.error,
                onChanged: (_) {
                  if (_secretError != null) setState(() => _secretError = null);
                },
              ),
            )
          else
            TFormItem(
              label: '私钥',
              required: true,
              errorText: _secretError,
              help: '粘贴 OpenSSH 格式私钥（-----BEGIN OPENSSH PRIVATE KEY----- 开头）',
              child: TTextarea(
                controller: _secretController,
                focusNode: _secretFocus,
                hintText: savedHint ?? '粘贴 OpenSSH 格式私钥',
                maxLines: 8,
                status: _secretError == null
                    ? TInputStatus.normal
                    : TInputStatus.error,
                onChanged: (_) {
                  if (_secretError != null) setState(() => _secretError = null);
                },
              ),
            ),
        ],
      ),
    );
  }

  // ── 高级 ─────────────────────────────────────────────────────────────

  Widget _buildAdvancedCard() {
    final otherHosts = _store.hosts
        .where((host) => host.id != widget.hostId)
        .toList();
    final jumpMissing =
        _jumpHostId != null &&
        !_store.hosts.any((host) => host.id == _jumpHostId);
    final hasJumpOptions = otherHosts.isNotEmpty || jumpMissing;

    return TForm(
      child: Column(
        children: [
          TFormItem(
            label: '跳板机',
            verticalAlignment: TFormItemVerticalAlignment.start,
            help: hasJumpOptions ? null : '暂无其它服务器可作为跳板机，先添加一台再回来设置。',
            child: hasJumpOptions
                ? Wrap(
                    alignment: WrapAlignment.end,
                    spacing: AppSpacing.gapS,
                    runSpacing: AppSpacing.gapS,
                    children: [
                      AppChip(
                        label: '直连',
                        selected: _jumpHostId == null,
                        onTap: () => setState(() => _jumpHostId = null),
                      ),
                      for (final host in otherHosts)
                        AppChip(
                          label: host.displayLabel,
                          selected: host.id == _jumpHostId,
                          onTap: () => setState(() => _jumpHostId = host.id),
                        ),
                      if (jumpMissing)
                        AppChip(
                          label: '原跳板机已删除',
                          selected: true,
                          onTap: () => setState(() => _jumpHostId = null),
                        ),
                    ],
                  )
                : const SizedBox.shrink(),
          ),
          TFormItem(
            label: '保活间隔',
            verticalAlignment: TFormItemVerticalAlignment.start,
            child: Wrap(
              alignment: WrapAlignment.end,
              spacing: AppSpacing.gapS,
              runSpacing: AppSpacing.gapS,
              children: [
                for (final item in const [
                  (0, '关闭'),
                  (30, '30 秒'),
                  (60, '60 秒'),
                  (120, '120 秒'),
                ])
                  AppChip(
                    label: item.$2,
                    selected: item.$1 == _keepAliveSeconds,
                    onTap: () => setState(() => _keepAliveSeconds = item.$1),
                  ),
              ],
            ),
          ),
          TFormItem(
            label: '备注',
            verticalAlignment: TFormItemVerticalAlignment.start,
            child: TTextarea(
              controller: _remarkController,
              hintText: '例如：证书续期、日志排查',
              maxLines: 3,
            ),
          ),
        ],
      ),
    );
  }
}
