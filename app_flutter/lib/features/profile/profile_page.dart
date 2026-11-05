import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../../core/layout/responsive.dart';
import '../../../core/net/app_services.dart';
import '../../../core/store/session.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/util/format.dart';
import '../../../widgets/app_feedback.dart';
import '../../../widgets/app_kit.dart';
import '../../../widgets/data_state.dart';

/// 「我的」Tab 页：账户概览、资料查看、修改密码、常用入口与退出登录。
///
/// 下拉刷新会调用 `/auth/info` 同步最新用户资料到会话。
class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  static const String _appVersion = '0.2.1';

  bool _loggingOut = false;

  /// 功能列表右侧次要信息：未读消息数、待支付账单数。
  int _unread = 0;
  int _unpaid = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadBadges();
    });
  }

  Future<void> _refresh() async {
    await Future.wait<void>([_syncUser(), _loadBadges()]);
  }

  Future<void> _syncUser() async {
    final services = AppServices.of(context);
    final session = context.read<Session>();
    try {
      final info = await services.auth.info();
      final rawUser = info['user'] is Map ? mapOf(info['user']) : info;
      if (rawUser.isNotEmpty) {
        await session.updateUser(rawUser);
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
    }
  }

  /// 功能列表角标：任一接口失败按 0 处理，不阻塞页面。
  Future<void> _loadBadges() async {
    final api = AppServices.of(context);
    final results = await Future.wait<Object?>([
      _safe(api.content.notificationUnreadCount),
      _safe(api.finance.invoiceSummary),
    ]);
    if (!mounted) return;
    setState(() {
      _unread = results[0] is int ? results[0] as int : 0;
      final summary = results[1] is Map<String, dynamic>
          ? results[1] as Map<String, dynamic>
          : const <String, dynamic>{};
      _unpaid = intOf(summary, 'unpaid');
    });
  }

  Future<T?> _safe<T>(Future<T> Function() fetch) async {
    try {
      return await fetch();
    } catch (_) {
      return null;
    }
  }

  /// 消息公告：进入后返回时刷新未读角标（可能已读）。
  Future<void> _openNotices() async {
    await context.push('/notices');
    if (mounted) await _loadBadges();
  }

  /// 账单与订单：返回时刷新待支付张数（可能已支付）。
  Future<void> _openFinance() async {
    await context.push('/finance');
    if (mounted) await _loadBadges();
  }

  /// 编辑资料：后端仅开放昵称修改。
  Future<void> _editProfile() async {
    final session = context.read<Session>();
    final current = strOf(session.user, 'nickname').trim();
    final nickname = await _promptText(
      title: '编辑资料',
      label: '昵称',
      initial: current,
      hint: '请输入昵称（最多 50 字）',
    );
    if (nickname == null || !mounted) return;
    if (nickname.isEmpty) {
      AppFeedback.error(context, '昵称不能为空');
      return;
    }
    if (nickname == current) return;
    final services = AppServices.of(context);
    final loading = AppFeedback.loading(context, '正在保存…');
    try {
      await services.auth.updateProfile(nickname: nickname);
    } catch (error) {
      AppFeedback.dismissLoading(loading);
      if (mounted) AppFeedback.error(context, AppServices.errorText(error));
      return;
    }
    AppFeedback.dismissLoading(loading);
    if (!mounted) return;
    // 后端只回 nickname/display_name，合并进现有用户资料再同步。
    final mergedUser = <String, dynamic>{
      ...(session.user ?? const <String, dynamic>{}),
      'nickname': nickname,
    };
    // 昵称改了但 display_name 由后端按优先级计算；服务端没有回显时按新昵称兜底。
    if (strOf(session.user, 'display_name').trim() == current) {
      mergedUser['display_name'] = nickname;
    }
    await session.updateUser(mergedUser);
    if (mounted) AppFeedback.success(context, '资料已更新');
  }

  Future<String?> _promptText({
    required String title,
    required String label,
    required String initial,
    String? hint,
  }) async {
    final controller = TextEditingController(text: initial);
    final theme = context.tTheme;
    final confirmed = await TDialog.show<bool>(
      context,
      dialog: TDialog(
        title: Text(title),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: AppType.secondary(theme)),
            const SizedBox(height: 6),
            TInput(controller: controller, hintText: hint, maxLength: 50),
          ],
        ),
        actions: const [
          TDialogAction(child: Text('取消'), result: false),
          TDialogAction(
            child: Text('保存'),
            result: true,
            role: TDialogActionRole.primary,
          ),
        ],
      ),
    );
    final text = controller.text.trim();
    controller.dispose();
    return confirmed == true ? text : null;
  }

  Future<void> _showAccountInfo() async {
    final user = context.read<Session>().user ?? const <String, dynamic>{};
    await TDialog.show<void>(
      context,
      barrierDismissible: true,
      dialog: TDialog(
        title: const Text('账户资料'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _infoRow('邮箱', strOf(user, 'email'), empty: '未绑定'),
            _infoRow('手机', strOf(user, 'phone'), empty: '未绑定'),
            _infoRow('昵称', strOf(user, 'nickname'), empty: '未设置'),
            _infoRow('注册时间', formatDateTime(strOf(user, 'created_at'))),
          ],
        ),
        actions: const [TDialogAction(child: Text('关闭'))],
      ),
    );
  }

  Widget _infoRow(String label, String value, {String empty = '--'}) {
    final theme = context.tTheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 72,
            child: Text(label, style: AppType.secondary(theme)),
          ),
          Expanded(
            child: Text(
              value.trim().isEmpty ? empty : value,
              style: AppType.body(theme),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _changePassword() async {
    final changed = await TDialog.show<bool>(
      context,
      dialog: const _ChangePasswordDialog(),
    );
    if (changed != true || !mounted) return;
    AppFeedback.success(context, '密码修改成功，请重新登录');
    await context.read<Session>().clear();
    if (mounted) context.go('/login');
  }

  Future<void> _showAbout() async {
    final theme = context.tTheme;
    await TDialog.show<void>(
      context,
      barrierDismissible: true,
      dialog: TDialog(
        title: const Text('关于'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Caiwu 控制台', style: AppType.section(theme)),
            const SizedBox(height: AppSpacing.gapS),
            Text('版本 $_appVersion', style: AppType.secondary(theme)),
          ],
        ),
        actions: const [TDialogAction(child: Text('关闭'))],
      ),
    );
  }

  Future<void> _logout() async {
    final confirmed = await AppFeedback.confirm(
      context,
      title: '退出登录',
      content: '确定要退出当前账号吗？',
      confirmText: '退出',
      danger: true,
    );
    if (!confirmed || !mounted) return;

    setState(() => _loggingOut = true);
    final services = AppServices.of(context);
    final session = context.read<Session>();
    try {
      // 服务端注销失败不阻塞本地清理，避免用户被困在已登录态。
      await services.auth.logout();
    } catch (_) {
      // 忽略：本地会话仍需清理。
    }
    await session.clear();
    if (!mounted) return;
    context.go('/login');
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    return AppScaffold(
      title: '我的',
      showBack: false,
      body: RefreshIndicator(
        color: context.tTheme.brandColor,
        onRefresh: _refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.fromLTRB(
            context.pagePadding,
            AppSpacing.gapM,
            context.pagePadding,
            AppSpacing.pageBottom,
          ),
          children: [
            if (context.isTablet)
              _buildWideLayout(session)
            else ...[
              _buildProfileCard(session),
              const SizedBox(height: AppSpacing.gapM),
              _buildMenuCard(),
              const SizedBox(height: AppSpacing.gapXl),
              _buildLogoutButton(),
            ],
          ],
        ),
      ),
    );
  }

  /// 平板两栏：左侧头像卡（窄栏，编辑入口下移到余额行），右侧功能列表。
  ///
  /// 左栏按内容区 42% 取宽并夹在 300–360 之间：低于 300 时账号文字会被
  /// 「编辑资料」按钮挤到只剩几十像素，高于 360 又与右栏比例失衡。
  Widget _buildWideLayout(Session session) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final leftWidth = (constraints.maxWidth * 0.42).clamp(300.0, 360.0);
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: leftWidth,
              child: _buildProfileCard(session, wide: true),
            ),
            const SizedBox(width: AppSpacing.gapXl),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _buildMenuCard(),
                  const SizedBox(height: AppSpacing.gapXl),
                  _buildLogoutButton(),
                ],
              ),
            ),
          ],
        );
      },
    );
  }

  /// 头像卡：品牌色圆形头像 + 昵称 + 账号 + 余额一行。
  ///
  /// [wide] 为真时（平板左栏）编辑入口移到余额行右侧，让账号文字能完整显示。
  Widget _buildProfileCard(Session session, {bool wide = false}) {
    final theme = context.tTheme;
    final displayName = session.displayName;
    final avatarLetter = displayName.isNotEmpty
        ? displayName.characters.first
        : '用';
    final editChip = InkWell(
      onTap: _editProfile,
      borderRadius: BorderRadius.circular(AppRadius.pill),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: theme.bgColorContainer,
          borderRadius: BorderRadius.circular(AppRadius.pill),
          border: Border.all(color: theme.borderLevel1Color),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(TIcons.edit, size: 13, color: theme.brandColor),
            const SizedBox(width: 3),
            Text(
              '编辑资料',
              style: AppType.caption(theme).copyWith(color: theme.brandColor),
            ),
          ],
        ),
      ),
    );
    return AppCard(
      padding: const EdgeInsets.all(20),
      highlight: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 56,
                height: 56,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: theme.brandColor,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  avatarLetter,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.gapM),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      displayName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppType.section(theme),
                    ),
                    const SizedBox(height: AppSpacing.gapXs),
                    Text(
                      session.accountLabel.isEmpty
                          ? '未绑定邮箱或手机'
                          : session.accountLabel,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppType.secondary(theme),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.gapS),
              if (!wide) editChip,
            ],
          ),
          const SizedBox(height: AppSpacing.gapL),
          Divider(height: 1, color: theme.borderLevel1Color),
          const SizedBox(height: AppSpacing.gapM),
          Row(
            children: [
              Expanded(child: Text('账户余额', style: AppType.secondary(theme))),
              Text(
                formatMoneyText(session.balance),
                style: AppType.money(theme, size: 20),
              ),
            ],
          ),
          // 窄栏放不下「昵称 + 账号 + 编辑按钮」一行，编辑入口落在余额行下方，
          // 否则账号会被压缩成「2908990…」。
          if (wide) ...[
            const SizedBox(height: AppSpacing.gapM),
            Align(alignment: Alignment.centerLeft, child: editChip),
          ],
        ],
      ),
    );
  }

  /// 功能列表卡：每行左图标、右箭头，卡内用细分割线分组。
  Widget _buildMenuCard() {
    final theme = context.tTheme;
    final items = <_MenuEntry>[
      _MenuEntry(icon: TIcons.user, label: '账户资料', onTap: _showAccountInfo),
      _MenuEntry(icon: TIcons.lock_on, label: '修改密码', onTap: _changePassword),
      _MenuEntry(
        icon: TIcons.notification,
        label: '消息公告',
        badge: _unread > 0 ? (_unread > 99 ? '99+' : '$_unread') : null,
        onTap: _openNotices,
      ),
      _MenuEntry(
        icon: TIcons.bill,
        label: '账单与订单',
        note: _unpaid > 0 ? '$_unpaid 张待支付' : null,
        onTap: _openFinance,
      ),
      _MenuEntry(
        icon: TIcons.terminal,
        label: '我的服务器',
        note: 'SSH 主机',
        onTap: () => context.push('/ssh'),
      ),
      _MenuEntry(
        icon: TIcons.info_circle,
        label: '关于',
        onTap: _showAbout,
        showDivider: false,
      ),
    ];
    // 使用 TDesign 官方 Cell 组：通栏、行高、分割线位置、箭头都由组件决定。
    return TCellGroup(
      cells: [
        for (final entry in items)
          TCell(
            prefix: Icon(entry.icon, size: 22, color: theme.brandColor),
            title: Text(entry.label),
            note: entry.note == null ? null : Text(entry.note!),
            trailing: entry.badge == null
                ? null
                : TBadge(label: entry.badge!, showZero: false),
            arrow: true,
            onTap: entry.onTap,
          ),
      ],
    );
  }

  /// 退出登录：危险文字按钮，上方以分割线 + 版本号与功能列表分离。
  Widget _buildLogoutButton() {
    final theme = context.tTheme;
    return Column(
      children: [
        Divider(height: 1, color: theme.borderLevel1Color),
        const SizedBox(height: AppSpacing.gapL),
        Center(
          child: Text('App 版本 $_appVersion', style: AppType.caption(theme)),
        ),
        const SizedBox(height: AppSpacing.gapS),
        SizedBox(
          height: 48,
          child: TextButton(
            onPressed: _loggingOut ? null : _logout,
            child: _loggingOut
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(
                    '退出登录',
                    style: AppType.body(theme).copyWith(
                      color: theme.errorColor,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
          ),
        ),
      ],
    );
  }
}

/// 「我的」功能列表的一行配置。
class _MenuEntry {
  const _MenuEntry({
    required this.icon,
    required this.label,
    required this.onTap,
    this.note,
    this.badge,
    this.showDivider = true,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final String? note;

  /// 右侧角标（未读数），为空时不展示。
  final String? badge;
  final bool showDivider;
}

/// 修改密码弹窗：旧密码 / 新密码 / 确认新密码。
///
/// 提交成功后返回 `true`，由调用方清理会话并回到登录页
/// （后端改密会吊销全部 Token）。
class _ChangePasswordDialog extends StatefulWidget {
  const _ChangePasswordDialog();

  @override
  State<_ChangePasswordDialog> createState() => _ChangePasswordDialogState();
}

class _ChangePasswordDialogState extends State<_ChangePasswordDialog> {
  final _oldController = TextEditingController();
  final _newController = TextEditingController();
  final _confirmController = TextEditingController();

  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _oldController.dispose();
    _newController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final oldPassword = _oldController.text;
    final newPassword = _newController.text;
    final confirmPassword = _confirmController.text;

    if (oldPassword.isEmpty) {
      setState(() => _error = '请输入原密码');
      return;
    }
    if (newPassword.length < 8) {
      setState(() => _error = '新密码至少 8 位');
      return;
    }
    if (newPassword != confirmPassword) {
      setState(() => _error = '两次输入的新密码不一致');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await AppServices.of(context).auth.updatePassword(
        oldPassword: oldPassword,
        newPassword: newPassword,
        confirmPassword: confirmPassword,
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = AppServices.errorText(error);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return TDialog(
      title: const Text('修改密码'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TInput(
            controller: _oldController,
            hintText: '原密码',
            obscureText: true,
            showPasswordToggle: true,
          ),
          const SizedBox(height: AppSpacing.gapM),
          TInput(
            controller: _newController,
            hintText: '新密码（至少 8 位）',
            obscureText: true,
            showPasswordToggle: true,
          ),
          const SizedBox(height: AppSpacing.gapM),
          TInput(
            controller: _confirmController,
            hintText: '确认新密码',
            obscureText: true,
            showPasswordToggle: true,
          ),
          if (_error != null) ...[
            const SizedBox(height: AppSpacing.gapM),
            Text(
              _error!,
              textAlign: TextAlign.left,
              style: AppType.secondary(theme).copyWith(color: theme.errorColor),
            ),
          ],
        ],
      ),
      actions: [
        const TDialogAction(child: Text('取消'), result: false),
        TDialogAction(
          child: Text(_submitting ? '提交中…' : '确定'),
          role: TDialogActionRole.primary,
          closeOnPressed: false,
          disabled: _submitting,
          onPressed: _submit,
        ),
      ],
    );
  }
}
