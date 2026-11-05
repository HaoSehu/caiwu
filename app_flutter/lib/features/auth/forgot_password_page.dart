import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../../core/net/app_services.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/util/account.dart';
import '../../../widgets/app_feedback.dart';
import '../../../widgets/app_kit.dart';
import 'auth_layout.dart';

/// 找回密码页：账号 + 验证码 + 新密码。
///
/// 版式与 `login_page.dart` 同构：品牌头 + 卡片表单 + 错误条。
/// 验证码发送按账号格式自动选短信/邮箱通道（purpose: reset），
/// 重置成功后提示并返回上一页（通常是登录页）。
class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
  final _accountController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  final _accountFocus = FocusNode();
  final _codeFocus = FocusNode();
  final _passwordFocus = FocusNode();

  bool _submitting = false;
  bool _sendingCode = false;
  int _countdown = 0;
  String? _error;

  /// 逐字段错误：就近显示在对应输入框下方。
  String? _accountError;
  String? _codeError;
  String? _passwordError;
  String? _confirmError;

  @override
  void dispose() {
    _accountController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    _accountFocus.dispose();
    _codeFocus.dispose();
    _passwordFocus.dispose();
    super.dispose();
  }

  void _back() {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go('/login');
    }
  }

  Future<void> _sendCode() async {
    final account = _accountController.text.trim();
    if (account.isEmpty) {
      setState(() => _accountError = '请先输入邮箱或手机号');
      _accountFocus.requestFocus();
      return;
    }
    if (!isValidAccount(account)) {
      setState(() => _accountError = '请输入正确的邮箱或手机号');
      return;
    }
    setState(() {
      _sendingCode = true;
      _accountError = null;
      _error = null;
    });
    try {
      if (isPhoneAccount(account)) {
        await AppServices.of(
          context,
        ).auth.sendPhoneCode(account, purpose: 'reset');
      } else {
        await AppServices.of(
          context,
        ).auth.sendEmailCode(account, purpose: 'reset');
      }
      if (!mounted) return;
      setState(() => _countdown = 60);
      AppFeedback.success(context, '验证码已发送');
      _codeFocus.requestFocus();
      _startCountdown();
    } catch (error) {
      if (mounted) setState(() => _error = AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _sendingCode = false);
    }
  }

  void _startCountdown() {
    Future<void>.delayed(const Duration(seconds: 1), () {
      if (!mounted || _countdown <= 1) {
        if (mounted) setState(() => _countdown = 0);
        return;
      }
      setState(() => _countdown -= 1);
      _startCountdown();
    });
  }

  Future<void> _submit() async {
    final account = _accountController.text.trim();
    final code = _codeController.text.trim();
    final password = _passwordController.text;
    final confirm = _confirmController.text;

    setState(() {
      _error = null;
      _accountError = account.isEmpty
          ? '请输入邮箱或手机号'
          : (!isValidAccount(account) ? '请输入正确的邮箱或手机号' : null);
      _codeError = code.length != 6 ? '请输入 6 位验证码' : null;
      _passwordError = password.length < 8 ? '密码至少 8 位' : null;
      _confirmError = (password.length >= 8 && password != confirm)
          ? '两次输入的密码不一致'
          : null;
    });
    if (_accountError != null) {
      _accountFocus.requestFocus();
      return;
    }
    if (_codeError != null) {
      _codeFocus.requestFocus();
      return;
    }
    if (_passwordError != null) {
      _passwordFocus.requestFocus();
      return;
    }
    if (_confirmError != null) {
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() => _submitting = true);
    try {
      await AppServices.of(context).auth.resetPassword(
        account: account,
        code: code,
        password: password,
        passwordConfirmation: confirm,
      );
      if (!mounted) return;
      AppFeedback.success(context, '密码重置成功');
      _back();
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = AppServices.errorText(error));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return AuthLayout(
      brand: _buildBrandHeader(theme),
      form: AppCard(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
        child: _buildForm(theme),
      ),
      footer: Center(
        child: TextButton(
          onPressed: _back,
          child: Text(
            '返回登录',
            style: AppType.secondary(
              theme,
            ).copyWith(color: theme.textColorSecondary),
          ),
        ),
      ),
    );
  }

  /// 品牌头内容：间距由 [AuthLayout] 按断点控制，这里只保留内容本身。
  Widget _buildBrandHeader(TThemeData theme) {
    return Column(
      children: [
        Container(
          width: 64,
          height: 64,
          decoration: BoxDecoration(
            color: theme.brandColor,
            borderRadius: BorderRadius.circular(
              context.tTheme.radiusExtraLarge,
            ),
          ),
          child: const Icon(TIcons.lock_on, color: Colors.white, size: 32),
        ),
        const SizedBox(height: AppSpacing.gapL),
        Text('找回密码', style: AppType.pageTitle(theme)),
        const SizedBox(height: AppSpacing.gapXs),
        Text('通过验证码重置账号密码', style: AppType.secondary(theme)),
      ],
    );
  }

  Widget _buildForm(TThemeData theme) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _labeledField(
          theme,
          label: '账号',
          error: _accountError,
          child: TInput(
            controller: _accountController,
            focusNode: _accountFocus,
            hintText: '邮箱或手机号',
            inputType: TextInputType.emailAddress,
            clearButtonMode: TInputClearButtonMode.always,
            onSubmitted: (_) => _codeFocus.requestFocus(),
            prefix: _fieldIcon(theme, TIcons.user),
          ),
        ),
        const SizedBox(height: AppSpacing.gapL),
        _labeledField(
          theme,
          label: '验证码',
          error: _codeError,
          child: Row(
            children: [
              Expanded(
                child: TInput(
                  controller: _codeController,
                  focusNode: _codeFocus,
                  hintText: '6 位验证码',
                  inputType: TextInputType.number,
                  onSubmitted: (_) => _passwordFocus.requestFocus(),
                  prefix: _fieldIcon(theme, TIcons.secured),
                ),
              ),
              const SizedBox(width: AppSpacing.gapS),
              SizedBox(
                height: 48,
                child: TButton(
                  size: TButtonSize.medium,
                  variant: TButtonVariant.outline,
                  colorPreset: TButtonColorPreset.primary,
                  onPressed: (_sendingCode || _countdown > 0)
                      ? null
                      : _sendCode,
                  child: Text(
                    _countdown > 0 ? '${_countdown}s' : '发送验证码',
                    style: const TextStyle(fontSize: 13),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.gapL),
        _labeledField(
          theme,
          label: '新密码',
          error: _passwordError,
          child: TInput(
            controller: _passwordController,
            focusNode: _passwordFocus,
            hintText: '至少 8 位',
            obscureText: true,
            showPasswordToggle: true,
            prefix: _fieldIcon(theme, TIcons.lock_on),
          ),
        ),
        const SizedBox(height: AppSpacing.gapL),
        _labeledField(
          theme,
          label: '确认新密码',
          error: _confirmError,
          child: TInput(
            controller: _confirmController,
            hintText: '再次输入新密码',
            obscureText: true,
            showPasswordToggle: true,
            onSubmitted: (_) => _submit(),
            prefix: _fieldIcon(theme, TIcons.lock_on),
          ),
        ),
        if (_error != null) ...[
          const SizedBox(height: AppSpacing.gapM),
          _buildError(theme, _error!),
        ],
        const SizedBox(height: AppSpacing.gapXl),
        SizedBox(
          height: 48,
          child: TButton(
            size: TButtonSize.large,
            colorPreset: TButtonColorPreset.primary,
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('重置密码'),
          ),
        ),
      ],
    );
  }

  Widget _labeledField(
    TThemeData theme, {
    required String label,
    required Widget child,
    String? error,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppType.secondary(theme).copyWith(fontWeight: FontWeight.w500),
        ),
        const SizedBox(height: 6),
        child,
        if (error != null) ...[
          const SizedBox(height: 4),
          Text(error, style: TextStyle(fontSize: 12, color: theme.errorColor)),
        ],
      ],
    );
  }

  Widget _fieldIcon(TThemeData theme, IconData icon) {
    // IgnorePointer：prefix 槽的 Center 会吞点击，导致图标附近点不聚焦。
    return IgnorePointer(
      child: Padding(
        padding: const EdgeInsets.only(left: 12, right: 6),
        child: Icon(icon, size: 20, color: theme.textColorPlaceholder),
      ),
    );
  }

  /// 与 `login_page.dart` 同款错误条（同一实现，不抽公共文件）。
  Widget _buildError(TThemeData theme, String message) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: theme.errorColor1,
        borderRadius: BorderRadius.circular(context.tTheme.radiusDefault),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(TIcons.error_circle, size: 16, color: theme.errorColor),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              message,
              style: AppType.secondary(
                theme,
              ).copyWith(color: theme.errorColor8),
            ),
          ),
        ],
      ),
    );
  }
}
