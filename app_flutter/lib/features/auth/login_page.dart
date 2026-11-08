import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../../core/net/api_exception.dart';
import '../../../core/net/app_services.dart';
import '../../../core/store/session.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/util/account.dart';
import '../../../widgets/app_feedback.dart';
import '../../../widgets/app_kit.dart';
import 'auth_layout.dart';

/// 登录页：仅支持验证码登录。
///
/// App 不加载 Geetest（项目红线：不允许网页嵌套），也不提供密码入口：
/// 密码登录在有图形行为验证的站点会直接不可用，且失败次数会触发风控锁定。
/// 发码接口对 App 免除人机验证（后端按 `X-Client-Platform: android` 识别，
/// 见 backend/app/Support/ClientPlatform.php），因此验证码是 App 唯一可靠通道。
/// 需要密码的场景走「忘记密码」的验证码重置。
class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _accountController = TextEditingController();
  final _codeController = TextEditingController();
  final _accountFocus = FocusNode();
  final _codeFocus = FocusNode();

  bool _submitting = false;
  bool _sendingCode = false;
  int _countdown = 0;
  String? _error;

  /// 逐字段错误：就近显示在对应输入框下方。
  String? _accountError;

  @override
  void dispose() {
    _accountController.dispose();
    _codeController.dispose();
    _accountFocus.dispose();
    _codeFocus.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final account = _accountController.text.trim();
    final code = _codeController.text.trim();

    setState(() {
      _error = null;
      _accountError = account.isEmpty
          ? '请输入邮箱或手机号'
          : (!isValidAccount(account) ? '请输入正确的邮箱或手机号' : null);
    });
    if (_accountError != null) {
      _accountFocus.requestFocus();
      return;
    }
    if (code.length != 6) {
      setState(() => _error = '请输入 6 位验证码');
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() {
      _submitting = true;
      _error = null;
    });

    final services = AppServices.of(context);
    final session = context.read<Session>();
    try {
      final result = await services.auth.loginByCode(
        account: account,
        code: code,
      );
      final token = '${result['token'] ?? ''}';
      final user = result['user'];
      if (token.isEmpty || user is! Map) {
        throw ApiException(code: -1, message: '登录响应异常，请稍后重试');
      }
      await session.applyLogin(token, Map<String, dynamic>.from(user));
      if (!mounted) return;
      context.go('/home');
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = _friendlyAuthError(error));
    } on NetworkException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = '登录失败，请稍后重试');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  /// 把行为验证相关错误转成可操作的提示。
  ///
  /// 正常链路下 App 已免除人机验证；仍出现时说明请求头缺失（如旧版客户端），
  /// 提示升级而不是让用户以为是账号问题。
  String _friendlyAuthError(ApiException error) {
    final message = error.message;
    final needsCaptcha =
        error.code == 42210 ||
        message.contains('行为验证') ||
        (message.contains('验证码') && message.contains('完成'));
    if (needsCaptcha) {
      return '当前需要图形行为验证，请升级 App 到最新版本后重试';
    }
    return message;
  }

  Future<void> _sendCode() async {
    final account = _accountController.text.trim();
    if (account.isEmpty) {
      setState(() => _error = '请先输入邮箱或手机号');
      _accountFocus.requestFocus();
      return;
    }
    if (!isValidAccount(account)) {
      setState(() => _error = '请输入正确的邮箱或手机号');
      return;
    }
    final services = AppServices.of(context);
    final isPhone = isPhoneAccount(account);
    setState(() {
      _sendingCode = true;
      _error = null;
    });
    try {
      if (isPhone) {
        await services.auth.sendPhoneCode(account);
      } else {
        await services.auth.sendEmailCode(account);
      }
      if (!mounted) return;
      setState(() => _countdown = 60);
      AppFeedback.success(context, '验证码已发送');
      _codeFocus.requestFocus();
      _startCountdown();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = _friendlyAuthError(error));
    } on NetworkException catch (error) {
      if (mounted) setState(() => _error = error.message);
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

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return AuthLayout(
      brand: _buildBrandHeader(theme),
      form: AppCard(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
        child: _buildForm(theme),
      ),
      footer: _buildFooter(theme),
    );
  }

  /// 品牌头：图标 + 名称 + 一句说明。
  ///
  /// 不用渐变、不加发光：登录页只需要让人确认「这是哪个 App」，
  /// 其余的视觉重量应该留给表单本身。
  Widget _buildBrandHeader(TThemeData theme) {
    return Column(
      children: [
        Container(
          width: 64,
          height: 64,
          decoration: BoxDecoration(
            color: theme.brandColor,
            borderRadius: BorderRadius.circular(theme.radiusExtraLarge),
          ),
          child: const Icon(TIcons.cloud, color: Colors.white, size: 34),
        ),
        const SizedBox(height: AppSpacing.gapL),
        Text('Caiwu 云控制台', style: AppType.pageTitle(theme)),
        const SizedBox(height: AppSpacing.gapXs),
        Text('随时管理你的服务器与账单', style: AppType.secondary(theme)),
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
        if (_accountError != null) ...[
          const SizedBox(height: 4),
          Text(
            _accountError!,
            style: AppType.caption(theme).copyWith(color: theme.errorColor),
          ),
        ],
        const SizedBox(height: AppSpacing.gapL),
        _labeledField(
          theme,
          label: '验证码',
          child: Row(
            children: [
              Expanded(
                child: TInput(
                  controller: _codeController,
                  focusNode: _codeFocus,
                  hintText: '6 位验证码',
                  inputType: TextInputType.number,
                  onSubmitted: (_) => _submit(),
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
                    style: AppType.body(theme),
                  ),
                ),
              ),
            ],
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
                : const Text('验证码登录'),
          ),
        ),
      ],
    );
  }

  Widget _labeledField(
    TThemeData theme, {
    required String label,
    required Widget child,
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
      ],
    );
  }

  /// 输入框前置图标。
  ///
  /// IgnorePointer 必须加：TDesign 的 prefix 槽会包一层 Center，图标区域会吞掉点击，
  /// 用户点到图标附近时输入框不聚焦（真机表现为"点了没反应"）。让点击穿透给整行输入框。
  Widget _fieldIcon(TThemeData theme, IconData icon) {
    return IgnorePointer(
      child: Padding(
        padding: const EdgeInsets.only(left: 12, right: 6),
        child: Icon(icon, size: 20, color: theme.textColorPlaceholder),
      ),
    );
  }

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

  Widget _buildFooter(TThemeData theme) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: TextButton(
                onPressed: () => context.push('/register'),
                child: Text(
                  '注册账号',
                  style: AppType.secondary(
                    theme,
                  ).copyWith(color: theme.brandColor),
                ),
              ),
            ),
            Container(width: 1, height: 14, color: theme.borderLevel1Color),
            Expanded(
              child: TextButton(
                onPressed: () => context.push('/forgot-password'),
                child: Text(
                  '忘记密码',
                  style: AppType.secondary(
                    theme,
                  ).copyWith(color: theme.brandColor),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
