import 'dart:async';

import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';
import 'package:xterm/xterm.dart';

import '../../core/net/app_services.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/theme/console_theme.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import '../../widgets/data_state.dart';
import 'host_store.dart';
import 'session_registry.dart';
import 'ssh_session.dart';

// 深色控制台配色统一取自 ConsoleColors，SSH 与 VNC 两页共用一套。
const Color _terminalBackground = ConsoleColors.canvas;
const Color _barBackground = ConsoleColors.bar;
const Color _barText = ConsoleColors.barText;
const Color _barSubtle = ConsoleColors.barSubtle;
const Color _barDivider = ConsoleColors.barDivider;

/// 深色沉浸式页面的状态栏样式：透明底 + 浅色图标，避免顶栏上方出现白条。
const SystemUiOverlayStyle _darkOverlayStyle = SystemUiOverlayStyle(
  statusBarColor: Colors.transparent,
  statusBarIconBrightness: Brightness.light,
  statusBarBrightness: Brightness.dark,
  systemNavigationBarColor: _terminalBackground,
  systemNavigationBarIconBrightness: Brightness.light,
);

/// SSH 终端页面：原生 dartssh2 长连接 + xterm 终端渲染（禁止 WebView）。
///
/// 两种入口：
/// - 服务模式（[serviceId]）：凭据经 caiwu connection 接口获取；
/// - 主机模式（[sshHostId]）：从本地 [SshHostStore] 读取主机与凭据。
class SshPage extends StatefulWidget {
  const SshPage({super.key, required this.serviceId, this.sshHostId});

  final int serviceId;

  /// 非空时进入主机模式，忽略 [serviceId]。
  final String? sshHostId;

  @override
  State<SshPage> createState() => _SshPageState();
}

class _SshPageState extends State<SshPage> {
  SshSession? _session;
  AppServices? _services;

  SshSessionState _state = SshSessionState.connecting;
  String _message = '正在获取连接信息';
  String _title = 'SSH 终端';
  bool _ctrlArmed = false;
  bool _initialized = false;

  bool get _isHostMode => (widget.sshHostId ?? '').isNotEmpty;

  Terminal? get _terminal => _session?.terminal;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _connect();
    });
  }

  @override
  void dispose() {
    _unregisterSession();
    final session = _session;
    _session = null;
    unawaited(session?.dispose());
    super.dispose();
  }

  // ── 连接 ─────────────────────────────────────────────────────────────

  Future<void> _connect() async {
    if (!mounted) return;
    if (!_initialized) {
      _initialized = true;
      _services = AppServices.of(context);
    }
    final services = _services;
    if (services == null) return;

    // 已持有会话时直接重连：沿用同一个终端，保留断开前的滚动历史。
    final existing = _session;
    if (existing != null) {
      setState(() {
        _state = SshSessionState.connecting;
        _message = '正在重新连接';
        _ctrlArmed = false;
      });
      unawaited(existing.connect());
      return;
    }

    setState(() {
      _state = SshSessionState.connecting;
      _message = '正在获取连接信息';
      _ctrlArmed = false;
      _title = _isHostMode ? 'SSH 终端' : _title;
    });

    SshTarget target;
    try {
      target = await _buildTarget(services);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _state = SshSessionState.error;
        _message = '$error';
      });
      return;
    }
    if (!mounted) return;

    final session = SshSession(
      target: target,
      serviceApi: services.services,
      hostStore: services.sshHosts,
      onHostKeyPrompt: _confirmHostKey,
    );
    // Ctrl 组合键在页面层拦截：打开 Ctrl 后，下一个按键转成控制码写入。
    session.terminal.onOutput = _handleTerminalOutput;
    session.addListener(() => _onSessionChanged(session));

    setState(() {
      _session = session;
      _state = session.state;
      _message = session.message ?? '';
    });

    unawaited(_loadTitles(services));
    unawaited(session.connect());
  }

  /// 解析连接目标；主机模式下先补齐主机记录与凭据。
  Future<SshTarget> _buildTarget(AppServices services) async {
    final hostId = widget.sshHostId;
    if (hostId == null || hostId.isEmpty) {
      return ServiceSshTarget(widget.serviceId);
    }
    final store = services.sshHosts;
    if (!store.loaded) await store.load();
    final host = store.byId(hostId);
    if (host == null) {
      throw const _PageError('主机不存在或已被删除，请返回主机列表重新选择');
    }
    final secret = await store.readSecret(host.id);
    if (secret.trim().isEmpty) {
      throw _PageError(
        '主机「${host.displayLabel}」未保存登录${host.authType == SshAuthType.privateKey ? '私钥' : '密码'}，'
        '请先在主机管理中补全凭据',
      );
    }
    return HostSshTarget(host: host, secret: secret);
  }

  /// 本页在会话注册表中的 id（连接成功后登记，断开时注销）。
  String? _registryId;

  void _registerSession(SshSession session) {
    final hostId = widget.sshHostId;
    final hostMode = hostId != null && hostId.isNotEmpty;
    final host = hostMode ? _services?.sshHosts.byId(hostId) : null;
    final id = hostMode ? 'host:$hostId' : 'service:${widget.serviceId}';
    _registryId = id;
    SshSessionRegistry.instance.register(
      SshSessionInfo(
        id: id,
        title: _title.isEmpty ? (host?.displayLabel ?? 'SSH 会话') : _title,
        subtitle: hostMode
            ? '${host?.username ?? ''}@${host?.endpoint ?? ''}'
            : '服务 #${widget.serviceId}',
        startedAt: DateTime.now(),
        kind: hostMode ? 'host' : 'service',
        serviceId: hostMode ? null : widget.serviceId,
        hostId: hostMode ? hostId : null,
      ),
    );
    SshSessionRegistry.instance.markActive(id);
  }

  void _unregisterSession() {
    final id = _registryId;
    if (id == null) return;
    _registryId = null;
    SshSessionRegistry.instance.unregister(id);
  }

  void _onSessionChanged(SshSession session) {
    if (!mounted || !identical(session, _session)) return;
    final wasConnected = _state == SshSessionState.connected;
    setState(() {
      _state = session.state;
      _message = session.message ?? '';
      if (session.state != SshSessionState.connected) _ctrlArmed = false;
    });
    // 仅主机模式记录最近连接时间，用于列表排序；失败不影响终端。
    final hostId = widget.sshHostId;
    if (!wasConnected &&
        session.state == SshSessionState.connected &&
        hostId != null &&
        hostId.isNotEmpty) {
      unawaited(_services?.sshHosts.touchConnected(hostId));
    }
    if (!wasConnected && session.state == SshSessionState.connected) {
      _registerSession(session);
    } else if (wasConnected && session.state != SshSessionState.connected) {
      _unregisterSession();
    }
  }

  Future<void> _loadTitles(AppServices services) async {
    final hostId = widget.sshHostId;
    if (hostId != null && hostId.isNotEmpty) {
      final host = services.sshHosts.byId(hostId);
      if (host != null && mounted) {
        setState(() => _title = host.displayLabel);
      }
      return;
    }
    try {
      final detail = await services.services.detail(widget.serviceId);
      final name = strOf(detail, 'name').trim();
      if (name.isNotEmpty && mounted) {
        setState(() => _title = name);
      }
    } catch (_) {
      // 标题加载失败不影响终端连接。
    }
  }

  /// 首次连接展示指纹并落盘；已保存则严格比对，不一致时允许用户人工复核后更新。
  Future<bool> _confirmHostKey(SshHostKeyChallenge challenge) async {
    if (!mounted) return false;
    final theme = context.tTheme;
    final changed = challenge.isChanged;

    final accepted = await TDialog.show<bool>(
      context,
      dialog: TDialog(
        title: Text(changed ? '服务器指纹已变化' : '首次连接，确认服务器指纹'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              challenge.endpoint,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
            ),
            if (!changed) ...[
              const SizedBox(height: 6),
              Text(
                challenge.type,
                style: TextStyle(fontSize: 12, color: theme.textColorSecondary),
              ),
            ],
            if (changed) ...[
              const SizedBox(height: 8),
              Text(
                '已保存指纹：',
                style: TextStyle(fontSize: 12, color: theme.textColorSecondary),
              ),
              SelectableText(
                challenge.knownFingerprint!,
                style: const TextStyle(fontSize: 11, fontFamily: 'monospace'),
              ),
              const SizedBox(height: 8),
              Text(
                '本次指纹：',
                style: TextStyle(fontSize: 12, color: theme.textColorSecondary),
              ),
              SelectableText(
                challenge.fingerprint,
                style: const TextStyle(fontSize: 11, fontFamily: 'monospace'),
              ),
            ] else ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: theme.bgColorPage,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: SelectableText(
                  challenge.fingerprint,
                  style: const TextStyle(
                    fontSize: 12,
                    fontFamily: 'monospace',
                    height: 1.4,
                  ),
                ),
              ),
            ],
            const SizedBox(height: 10),
            Text(
              changed
                  ? '如果刚刚重装过系统或迁移过实例，可以信任新指纹；否则请勿继续，可能是中间人攻击。'
                  : '请与服务器管理员核对指纹，确认一致后再继续连接',
              style: TextStyle(fontSize: 12, color: theme.textColorSecondary),
            ),
          ],
        ),
        actions: changed
            ? const [
                TDialogAction(child: Text('取消'), result: false),
                TDialogAction(
                  child: Text('信任新指纹'),
                  result: true,
                  role: TDialogActionRole.destructive,
                ),
              ]
            : const [
                TDialogAction(child: Text('取消'), result: false),
                TDialogAction(
                  child: Text('信任并继续'),
                  result: true,
                  role: TDialogActionRole.primary,
                ),
              ],
      ),
    );
    return accepted == true;
  }

  void _disconnect() {
    final session = _session;
    if (session == null) return;
    unawaited(session.disconnect());
  }

  // ── 输入 ─────────────────────────────────────────────────────────────

  void _handleTerminalOutput(String data) {
    final session = _session;
    if (session == null) return;
    if (_ctrlArmed) {
      _ctrlArmed = false;
      if (mounted) setState(() {});
      if (data.length == 1) {
        final control = _controlCodeOf(data.codeUnitAt(0));
        if (control != null) {
          session.send(String.fromCharCode(control));
          return;
        }
      }
    }
    session.send(data);
  }

  /// 字母键 → Ctrl 控制码（Ctrl+A=0x01 … Ctrl+Z=0x1A 及常见符号）。
  int? _controlCodeOf(int code) {
    if (code >= 0x61 && code <= 0x7A) return code - 0x60;
    if (code >= 0x41 && code <= 0x5A) return code - 0x40;
    return switch (code) {
      0x40 => 0x00, // @
      0x5B => 0x1B, // [
      0x5C => 0x1C, // \
      0x5D => 0x1D, // ]
      0x5E => 0x1E, // ^
      0x5F => 0x1F, // _
      0x3F => 0x7F, // ?
      _ => null,
    };
  }

  void _write(String data) => _session?.send(data);

  void _toggleCtrl() {
    setState(() => _ctrlArmed = !_ctrlArmed);
  }

  Future<void> _paste() async {
    final data = await Clipboard.getData(Clipboard.kTextPlain);
    final text = data?.text ?? '';
    if (text.isEmpty) {
      if (mounted) AppFeedback.toast(context, '剪贴板为空');
      return;
    }
    _session?.terminal.paste(text);
  }

  /// 把终端缓冲区（含滚动历史）的纯文本写入剪贴板。
  Future<void> _copyAll() async {
    final terminal = _terminal;
    if (terminal == null) return;
    final text = terminal.buffer.getText();
    if (text.trim().isEmpty) {
      if (mounted) AppFeedback.toast(context, '终端暂无输出');
      return;
    }
    await Clipboard.setData(ClipboardData(text: text));
    if (mounted) AppFeedback.success(context, '已复制全部输出');
  }

  // ── UI ───────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final connected = _state == SshSessionState.connected;
    final terminal = _terminal;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: _darkOverlayStyle,
      child: Scaffold(
        backgroundColor: _terminalBackground,
        resizeToAvoidBottomInset: true,
        body: SafeArea(
          child: Column(
            children: [
              _buildTopBar(),
              Expanded(
                child: connected && terminal != null
                    ? _buildTerminal(terminal)
                    : _buildStatusBody(),
              ),
              // 工具条必须常驻：Ctrl / Esc / Tab / 方向键正是打字时最需要的，
              // 键盘一弹就把它们摘掉，用户得先收起键盘才能按。
              // 高度换来的是这几行终端可视区，但键盘本身已占了 300 左右，
              // 再省 44 行并不能真正「看到上下文」，反而牺牲了控制键。
              if (connected) _buildToolbar(),
            ],
          ),
        ),
      ),
    );
  }

  /// 顶栏保持深色终端风格；状态圆点与副行文案取 TDesign 语义色。
  Widget _buildTopBar() {
    final theme = context.tTheme;
    final statusColor = switch (_state) {
      SshSessionState.connected => theme.successColor,
      SshSessionState.connecting ||
      SshSessionState.authenticating ||
      SshSessionState.idle => theme.warningColor,
      SshSessionState.disconnected || SshSessionState.error => theme.errorColor,
    };
    final statusText = switch (_state) {
      SshSessionState.connected => '已连接',
      SshSessionState.connecting ||
      SshSessionState.authenticating ||
      SshSessionState.idle => '连接中',
      SshSessionState.disconnected => '已断开',
      SshSessionState.error => '连接失败',
    };
    final canRetry =
        _state == SshSessionState.disconnected ||
        _state == SshSessionState.error;
    final connected = _state == SshSessionState.connected;

    return Container(
      height: 56,
      color: _barBackground,
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.gapXs),
      child: Row(
        children: [
          Semantics(
            button: true,
            label: '返回',
            child: InkResponse(
              onTap: () => context.pop(),
              radius: 22,
              child: const SizedBox(
                width: 44,
                height: 44,
                child: Icon(Icons.arrow_back, color: _barText, size: 22),
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.gapXs),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppType.cardTitle(theme).copyWith(color: Colors.white),
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    AppDot(color: statusColor, size: 7),
                    const SizedBox(width: 5),
                    Text(
                      statusText,
                      style: AppType.caption(
                        theme,
                      ).copyWith(color: statusColor),
                    ),
                  ],
                ),
              ],
            ),
          ),
          _TopBarIconAction(
            icon: Icons.content_copy,
            label: '复制全部输出',
            onTap: _copyAll,
          ),
          if (connected)
            _TopBarTextAction(
              label: '断开',
              color: theme.errorColor,
              icon: Icons.link_off,
              onTap: _disconnect,
            )
          else if (canRetry)
            _TopBarTextAction(
              label: '重连',
              color: _barText,
              icon: Icons.refresh,
              onTap: _connect,
            ),
          const SizedBox(width: AppSpacing.gapXs),
        ],
      ),
    );
  }

  Widget _buildTerminal(Terminal terminal) {
    return Container(
      color: _terminalBackground,
      child: TerminalView(
        terminal,
        theme: _terminalTheme,
        textStyle: const TerminalStyle(fontSize: 13, fontFamily: 'monospace'),
        padding: const EdgeInsets.all(8),
        autofocus: true,
        deleteDetection: true,
        backgroundOpacity: 1,
      ),
    );
  }

  Widget _buildStatusBody() {
    final theme = context.tTheme;
    final isError = _state == SshSessionState.error;
    final isDisconnected = _state == SshSessionState.disconnected;
    final stopped = isError || isDisconnected;
    final title = switch (_state) {
      SshSessionState.connecting ||
      SshSessionState.authenticating ||
      SshSessionState.idle => '正在连接',
      SshSessionState.connected => '已连接',
      SshSessionState.disconnected => '会话已结束',
      SshSessionState.error => '连接失败',
    };
    final returnText = _isHostMode ? '返回主机列表' : '返回服务详情';
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(
          horizontal: 32,
          vertical: AppSpacing.gapXl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (stopped)
              Icon(
                isError ? Icons.error_outline : Icons.link_off,
                color: isError ? theme.errorColor : _barSubtle,
                size: 48,
              )
            else
              SizedBox(
                width: 48,
                height: 48,
                child: CircularProgressIndicator(
                  strokeWidth: 3,
                  color: context.tTheme.brandColor,
                ),
              ),
            const SizedBox(height: AppSpacing.gapXl),
            Text(
              title,
              textAlign: TextAlign.center,
              style: AppType.section(theme).copyWith(color: Colors.white),
            ),
            if (_message.trim().isNotEmpty && _message.trim() != title) ...[
              const SizedBox(height: AppSpacing.gapS),
              Text(
                _message,
                textAlign: TextAlign.center,
                style: AppType.secondary(
                  theme,
                ).copyWith(color: _barSubtle),
              ),
            ],
            const SizedBox(height: AppSpacing.gapXl),
            SizedBox(
              width: double.infinity,
              child: TButton(
                size: TButtonSize.large,
                colorPreset: TButtonColorPreset.primary,
                onPressed: _connect,
                child: Text(stopped ? '重新连接' : '立即重试'),
              ),
            ),
            const SizedBox(height: AppSpacing.gapS),
            SizedBox(
              width: double.infinity,
              child: TButton(
                size: TButtonSize.large,
                variant: TButtonVariant.outline,
                colorPreset: TButtonColorPreset.defaultTheme,
                onPressed: () => context.pop(),
                child: Text(returnText),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildToolbar() {
    return Container(
      decoration: const BoxDecoration(
        color: _barBackground,
        border: Border(top: BorderSide(color: _barDivider, width: 0.5)),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.gapM,
          vertical: AppSpacing.gapS,
        ),
        child: Row(
          children: [
            _toolButton('Ctrl', active: _ctrlArmed, onTap: _toggleCtrl),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton('Esc', onTap: () => _write('\x1b')),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton('Tab', onTap: () => _write('\t')),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton('↑', onTap: () => _write('\x1b[A')),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton('↓', onTap: () => _write('\x1b[B')),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton('←', onTap: () => _write('\x1b[D')),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton('→', onTap: () => _write('\x1b[C')),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton('粘贴', onTap: _paste),
          ],
        ),
      ),
    );
  }

  /// 工具条按钮统一 40 高、8 圆角，按下时降低透明度给出反馈。
  Widget _toolButton(
    String label, {
    bool active = false,
    required VoidCallback onTap,
  }) {
    final theme = context.tTheme;
    return Semantics(
      button: true,
      label: label,
      child: Material(
        color: active ? theme.brandColor : _barDivider,
        borderRadius: BorderRadius.circular(8),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(8),
          splashColor: Colors.white.withValues(alpha: 0.08),
          highlightColor: Colors.white.withValues(alpha: 0.06),
          child: Container(
            // 终端/桌面工具条是高频点按区，高度与最小宽度都取 44。
            height: 44,
            constraints: const BoxConstraints(minWidth: 44),
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Text(
              label,
              style: AppType.body(theme).copyWith(
                fontSize: 13,
                color: active ? Colors.white : _barText,
                fontWeight: active ? FontWeight.w600 : FontWeight.w400,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// 顶栏文字动作：图标 + 文案，带 44 高度可点区域。
class _TopBarTextAction extends StatelessWidget {
  const _TopBarTextAction({
    required this.label,
    required this.color,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final Color color;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(width: 4),
            Text(label, style: AppType.secondary(theme).copyWith(color: color)),
          ],
        ),
      ),
    );
  }
}

/// 顶栏纯图标动作（复制等），语义标签供无障碍读屏。
class _TopBarIconAction extends StatelessWidget {
  const _TopBarIconAction({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: label,
      child: InkResponse(
        onTap: onTap,
        radius: 22,
        child: SizedBox(
          width: 40,
          height: 44,
          child: Icon(icon, color: _barText, size: 18),
        ),
      ),
    );
  }
}

/// 页面级错误（主机记录缺失、凭据缺失等），文案直接展示给用户。
class _PageError implements Exception {
  const _PageError(this.message);

  final String message;

  @override
  String toString() => message;
}

/// 终端主题：深色背景 + TDesign 语义色。
const TerminalTheme _terminalTheme = TerminalTheme(
  cursor: Color(0xFFE5E7EB),
  selection: Color(0x664B8DFF),
  foreground: Color(0xFFE5E7EB),
  background: _terminalBackground,
  black: Color(0xFF1F1F1F),
  red: Color(0xFFF04438),
  green: Color(0xFF12B76A),
  yellow: Color(0xFFF79009),
  blue: Color(0xFF4B8DFF),
  magenta: Color(0xFFD670D6),
  cyan: Color(0xFF29B8DB),
  white: Color(0xFFE5E5E5),
  brightBlack: Color(0xFF6B7280),
  brightRed: Color(0xFFF97066),
  brightGreen: Color(0xFF32D583),
  brightYellow: Color(0xFFFDB022),
  brightBlue: Color(0xFF84ADFF),
  brightMagenta: Color(0xFFE9A8E9),
  brightCyan: Color(0xFF67E3F9),
  brightWhite: Color(0xFFFFFFFF),
  searchHitBackground: Color(0xFFFFFF2B),
  searchHitBackgroundCurrent: Color(0xFF31FF26),
  searchHitForeground: Color(0xFF000000),
);
