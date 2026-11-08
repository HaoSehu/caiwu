import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/gestures.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:tdesign_flutter/tdesign_flutter.dart';

import '../../core/config/env.dart';
import '../../core/net/app_services.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/theme/console_theme.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/app_kit.dart';
import 'rfb/rfb_client.dart';

// 深色控制台配色统一取自 ConsoleColors，SSH 与 VNC 两页共用一套。
const Color _desktopBackground = ConsoleColors.desktopCanvas;
const Color _barBackground = ConsoleColors.bar;
const Color _barText = ConsoleColors.barText;
const Color _barSubtle = ConsoleColors.barSubtle;
const Color _barDivider = ConsoleColors.barDivider;
const Color _toolBackground = ConsoleColors.toolBar;

/// 深色沉浸式页面的状态栏样式：透明底 + 浅色图标。
const SystemUiOverlayStyle _darkOverlayStyle = SystemUiOverlayStyle(
  statusBarColor: Colors.transparent,
  statusBarIconBrightness: Brightness.light,
  statusBarBrightness: Brightness.dark,
  systemNavigationBarColor: _desktopBackground,
  systemNavigationBarIconBrightness: Brightness.light,
);

/// VNC 远程桌面（原生 RFB over WebSocket，非网页嵌套）。
///
/// 流程：GET /services/{id}/vnc → 解析 noVNC URL 的 token/relay_path/api_base →
/// GET /vnc-tokens/{token} 换 relay token → WebSocket 连 relay（Origin 白名单）→ 跑 RFB。
class VncPage extends StatefulWidget {
  const VncPage({super.key, required this.serviceId});

  final int serviceId;

  @override
  State<VncPage> createState() => _VncPageState();
}

class _VncPageState extends State<VncPage> {
  WebSocket? _socket;
  RfbClient? _rfb;
  StreamSubscription<dynamic>? _socketSub;

  RfbState _state = RfbState.idle;
  String? _message;
  String _serviceName = 'VNC 控制台';

  // framebuffer：本地 RGBA 缓冲 + 合成后的 ui.Image。
  ui.Image? _frameImage;
  int _frameWidth = 0;
  int _frameHeight = 0;

  // 视图变换
  double _scale = 1.0;
  Offset _offset = Offset.zero;
  double _lastScale = 1.0;

  // 鼠标按键状态（用于拖拽时保持按下）
  int _buttonMask = 0;

  // 首次进入的缩放/拖动手势提示：3 秒后淡出，只出现一次。
  bool _showHint = false;
  bool _hintShown = false;
  Timer? _hintTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _connect());
  }

  @override
  void dispose() {
    _hintTimer?.cancel();
    _composeTimer?.cancel();
    _socketSub?.cancel();
    _socket?.close();
    _rfb?.dispose();
    _frameImage?.dispose();
    _focusNode?.dispose();
    _imeController.dispose();
    super.dispose();
  }

  /// 首次连上后显示手势提示，3 秒后收起（定时器在 dispose 中释放）。
  void _maybeShowHint() {
    if (_hintShown) return;
    _hintShown = true;
    _showHint = true;
    _hintTimer?.cancel();
    _hintTimer = Timer(const Duration(seconds: 3), () {
      if (!mounted) return;
      setState(() => _showHint = false);
    });
  }

  Future<void> _connect() async {
    final services = AppServices.of(context);
    setState(() {
      _state = RfbState.connecting;
      _message = '正在获取连接信息';
    });

    try {
      // 1. 详情中的服务名（用于标题）
      try {
        final detail = await services.services.detail(widget.serviceId);
        final name = '${detail['name'] ?? detail['combined_display_name'] ?? ''}';
        if (name.trim().isNotEmpty && mounted) _serviceName = name.trim();
      } catch (_) {}

      // 2. 取 VNC 链接与凭据
      final vnc = await services.services.vnc(widget.serviceId);
      final url = '${vnc['url'] ?? ''}';
      final credentials = _mapOf(vnc['vnc_credentials']);
      final password = '${credentials['password'] ?? ''}';
      final username = '${credentials['username'] ?? ''}';

      if (url.isEmpty) {
        throw StateError('未获取到 VNC 链接');
      }
      final uri = Uri.tryParse(url);
      if (uri == null) throw StateError('VNC 链接格式异常');

      final publicToken = uri.queryParameters['token'] ?? '';
      var relayPath = uri.queryParameters['relay_path'] ?? '/ws/vnc';
      var apiBase = uri.queryParameters['api_base'] ?? '';

      // 直接地址（host/port 直连，无 relay）场景：首版不支持，给出明确提示。
      if (publicToken.isEmpty) {
        throw StateError('当前实例未配置 VNC 中转，暂不支持直连模式');
      }

      // 3. 公开 token 换 relay token
      setState(() => _message = '正在建立安全通道');
      final relay = await services.services.vncToken(publicToken);
      final relayToken = '${relay['token'] ?? ''}';
      if (relayToken.isEmpty) throw StateError('VNC 中转凭证无效');
      relayPath = '${relay['relay_path'] ?? relayPath}';
      if (apiBase.isEmpty) apiBase = Env.apiBase;

      // 4. 打开 WebSocket
      final wsBase = Env.wsBaseFromApiBase(apiBase);
      final wsUrl = '$wsBase${relayPath.startsWith('/') ? '' : '/'}$relayPath?token=$relayToken';
      final socket = await WebSocket.connect(
        wsUrl,
        headers: {'Origin': Env.vncOrigin},
      );
      socket.add(Uint8List(0)); // 某些代理需要先有一帧才转发
      _socket = socket;
      socket.pingInterval = const Duration(seconds: 20);

      final rfb = RfbClient(
        onRect: _applyRect,
        onStateChanged: (state, message) {
          if (!mounted) return;
          if (state == RfbState.connected) _maybeShowHint();
          setState(() {
            _state = state;
            _message = message;
          });
        },
        onDesktopSize: (w, h) {
          if (!mounted) return;
          setState(() {
            _frameWidth = w;
            _frameHeight = h;
          });
          // 分辨率变化：重建帧缓冲（尺寸按新分辨率）。
          _allocFramebuffer();
        },
        onCutText: (text) {
          // 服务端剪贴板：暂只提示（写入系统剪贴板需要额外权限，首版不自动覆盖用户剪贴板）。
          if (!mounted) return;
          AppFeedback.toast(context, '远端剪贴板：${text.length > 40 ? '${text.substring(0, 40)}…' : text}');
        },
      );
      _rfb = rfb;
      // 顺序很关键：必须先订阅 rfb.output，再 start()。
      // start() 会立刻发送版本响应等握手数据，若此时还没人监听，
      // 这些帧会全部丢失，服务端收不到任何回复 → 一直卡在"正在认证"。
      _socketSub = rfb.output.listen(socket.add);
      rfb.attach(socket.cast<Uint8List>());
      rfb.start(password: password, username: username);

      if (mounted) setState(() {});
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _state = RfbState.error;
        _message = AppServices.errorText(error);
      });
    }
  }

  void _applyRect(RfbRect rect) {
    // 首帧（或尺寸变化）分配帧缓冲。
    if (_framebuffer == null ||
        _frameWidth != _imageWidth ||
        _frameHeight != _imageHeight) {
      _allocFramebuffer();
    }
    if (_frameImage == null) {
      _allocFramebuffer();
    }
    if (_framebuffer == null) return;
    _blitRect(rect);
    _markDirty();
  }

  int _imageWidth = 0;
  int _imageHeight = 0;

  /// 本地帧缓冲（RGBA8888，按远端分辨率）。所有矩形先写入这里，再节流合成图像，
  /// 避免"每次矩形都全帧解码"和"渲染中到达的矩形被丢弃"。
  Uint8List? _framebuffer;
  bool _dirtyRect = false;
  bool _composing = false;
  Timer? _composeTimer;

  void _allocFramebuffer() {
    final width = _frameWidth;
    final height = _frameHeight;
    if (width <= 0 || height <= 0) return;
    _imageWidth = width;
    _imageHeight = height;
    final size = width * height * 4;
    final old = _framebuffer;
    _framebuffer = Uint8List(size);
    if (old != null && old.length == size) {
      _framebuffer!.setRange(0, size, old);
    }
    // 空画面填黑（不透明白，避免未绘制区域看起来像白屏）。
    final buffer = _framebuffer!;
    for (var i = 0; i + 3 < buffer.length; i += 4) {
      buffer[i + 3] = 255;
    }
    _markDirty();
  }

  /// 把服务端矩形写入本地帧缓冲（stride 按当前画布宽度）。
  void _blitRect(RfbRect rect) {
    final buffer = _framebuffer;
    if (buffer == null) return;
    if (rect.x < 0 || rect.y < 0) return;
    if (rect.x + rect.width > _imageWidth || rect.y + rect.height > _imageHeight) {
      // 尺寸不一致（远端刚改分辨率、DesktopSize 尚未处理完）时，安全丢弃该帧。
      return;
    }
    final stride = _imageWidth * 4;
    final rowBytes = rect.width * 4;
    for (var row = 0; row < rect.height; row++) {
      final dstY = rect.y + row;
      final dstStart = dstY * stride + rect.x * 4;
      final srcStart = row * rowBytes;
      buffer.setRange(dstStart, dstStart + rowBytes, rect.pixels, srcStart);
    }
  }

  /// 标记有未合成内容；用 16ms 合并窗口把一批矩形压成一次图像重建。
  void _markDirty() {
    _dirtyRect = true;
    if (_composing) return;
    _composeTimer ??= Timer(const Duration(milliseconds: 16), () {
      _composeTimer = null;
      unawaited(_compose());
    });
  }

  Future<void> _compose() async {
    if (_composing) return;
    final buffer = _framebuffer;
    if (buffer == null || !_dirtyRect) return;
    // 拷贝一份送解码：ui 解码期间新矩形继续写入原缓冲，不会撕裂。
    final snapshot = Uint8List.fromList(buffer);
    _dirtyRect = false;
    _composing = true;
    final width = _imageWidth;
    final height = _imageHeight;
    try {
      final image = await _decodePixels(snapshot, width, height);
      if (image == null) return;
      if (!mounted) {
        image.dispose();
        return;
      }
      setState(() {
        _frameImage?.dispose();
        _frameImage = image;
      });
    } finally {
      _composing = false;
      if (_dirtyRect) _markDirty();
    }
  }

  /// `ui.decodeImageFromPixels` 是回调式 API，包装成 Future。
  Future<ui.Image?> _decodePixels(Uint8List pixels, int width, int height) {
    final completer = Completer<ui.Image?>();
    ui.decodeImageFromPixels(
      pixels,
      width,
      height,
      ui.PixelFormat.rgba8888,
      (image) {
        if (!completer.isCompleted) completer.complete(image);
      },
    );
    return completer.future;
  }

  void _retry() {
    _composeTimer?.cancel();
    _composeTimer = null;
    _socketSub?.cancel();
    _socket?.close();
    _rfb?.dispose();
    _frameImage?.dispose();
    _frameImage = null;
    _framebuffer = null;
    _frameWidth = 0;
    _frameHeight = 0;
    _imageWidth = 0;
    _imageHeight = 0;
    _connect();
  }

  // ── 输入映射 ────────────────────────────────────────────────────────

  Offset _toRemote(Offset local, Size viewSize) {
    if (_frameWidth == 0 || _frameHeight == 0) return Offset.zero;
    final fit = _fitScale(viewSize);
    final scale = fit * _scale;
    // 画面以 BoxFit.contain 居中，必须扣除 FittedBox 产生的 letterbox 留白，
    // 否则宽高比不一致的设备上点击整体偏移。
    final originX = (viewSize.width - _frameWidth * fit) / 2;
    final originY = (viewSize.height - _frameHeight * fit) / 2;
    final dx = (local.dx - _offset.dx - originX) / scale;
    final dy = (local.dy - _offset.dy - originY) / scale;
    return Offset(dx.clamp(0, _frameWidth - 1.0), dy.clamp(0, _frameHeight - 1.0));
  }

  double _fitScale(Size viewSize) {
    if (_frameWidth == 0 || _frameHeight == 0) return 1;
    final sx = viewSize.width / _frameWidth;
    final sy = viewSize.height / _frameHeight;
    return sx < sy ? sx : sy;
  }

  void _sendPointer(Offset local, Size viewSize, {int? mask}) {
    final rfb = _rfb;
    if (rfb == null || !rfb.isConnected) return;
    final remote = _toRemote(local, viewSize);
    rfb.sendPointer(remote.dx.round(), remote.dy.round(), mask ?? _buttonMask);
  }

  /// Flutter 逻辑键 → X11 keysym。
  int? _keysymOf(LogicalKeyboardKey key) {
    // 常见控制键
    final map = <LogicalKeyboardKey, int>{
      LogicalKeyboardKey.escape: 0xFF1B,
      LogicalKeyboardKey.tab: 0xFF09,
      LogicalKeyboardKey.enter: 0xFF0D,
      LogicalKeyboardKey.numpadEnter: 0xFF0D,
      LogicalKeyboardKey.backspace: 0xFF08,
      LogicalKeyboardKey.delete: 0xFFFF,
      LogicalKeyboardKey.arrowLeft: 0xFF51,
      LogicalKeyboardKey.arrowUp: 0xFF52,
      LogicalKeyboardKey.arrowRight: 0xFF53,
      LogicalKeyboardKey.arrowDown: 0xFF54,
      LogicalKeyboardKey.home: 0xFF50,
      LogicalKeyboardKey.end: 0xFF57,
      LogicalKeyboardKey.pageUp: 0xFF55,
      LogicalKeyboardKey.pageDown: 0xFF56,
      LogicalKeyboardKey.insert: 0xFF63,
      LogicalKeyboardKey.controlLeft: 0xFFE3,
      LogicalKeyboardKey.controlRight: 0xFFE4,
      LogicalKeyboardKey.altLeft: 0xFFE9,
      LogicalKeyboardKey.altRight: 0xFFEA,
      LogicalKeyboardKey.shiftLeft: 0xFFE1,
      LogicalKeyboardKey.shiftRight: 0xFFE2,
      LogicalKeyboardKey.metaLeft: 0xFFEB,
      LogicalKeyboardKey.metaRight: 0xFFEC,
      LogicalKeyboardKey.space: 0x20,
    };
    final mapped = map[key];
    if (mapped != null) return mapped;
    if (key.keyLabel.length == 1) {
      final code = key.keyLabel.codeUnitAt(0);
      return code;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final theme = context.tTheme;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: _darkOverlayStyle,
      child: Scaffold(
        backgroundColor: _desktopBackground,
        body: SafeArea(
          child: Column(
            children: [
              _buildTopBar(theme),
              Expanded(
                child: _buildBody(theme),
              ),
              if (_state == RfbState.connected) _buildToolbar(theme),
            ],
          ),
        ),
      ),
    );
  }

  String get _statusText => switch (_state) {
        RfbState.connected => '已连接',
        RfbState.error => '连接失败',
        RfbState.disconnected => '已断开',
        _ => '连接中',
      };

  Color _statusColor(TThemeData theme) => switch (_state) {
        RfbState.connected => theme.successColor,
        RfbState.error || RfbState.disconnected => theme.errorColor,
        _ => theme.warningColor,
      };

  Widget _buildTopBar(TThemeData theme) {
    final connected = _state == RfbState.connected;
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
                  _serviceName,
                  style: AppType.cardTitle(theme).copyWith(color: Colors.white),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    AppDot(color: _statusColor(theme), size: 7),
                    const SizedBox(width: 5),
                    Text(
                      _statusText,
                      style: AppType.caption(theme)
                          .copyWith(color: _statusColor(theme)),
                    ),
                    if (connected && _frameWidth > 0 && _frameHeight > 0) ...[
                      const SizedBox(width: AppSpacing.gapS),
                      Text(
                        '$_frameWidth×$_frameHeight',
                        style: AppType.caption(theme)
                            .copyWith(color: _barSubtle),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
          _TopBarIconAction(
            icon: Icons.refresh,
            semanticLabel: '重新连接',
            onTap: _retry,
          ),
          _TopBarIconAction(
            icon: Icons.keyboard,
            semanticLabel: '键盘',
            onTap: _toggleKeyboardButton,
          ),
          const SizedBox(width: AppSpacing.gapXs),
        ],
      ),
    );
  }

  FocusNode? _focusNode;
  final TextEditingController _imeController = TextEditingController();
  bool _imeTextLocked = false;

  /// 系统软键盘输入 -> RFB 按键事件。
  ///
  /// Android 的软键盘不会产生 Flutter 的 KeyEvent（尤其是中文输入法），
  /// 必须用一个隐藏 TextField 承接文本，再逐字符转成 keysym 发给远端。
  void _onImeChanged(String value) {
    final rfb = _rfb;
    if (rfb == null || !rfb.isConnected || _imeTextLocked) return;
    if (value.isEmpty) return;
    for (final rune in value.runes) {
      rfb.sendKey(rune, down: true);
      rfb.sendKey(rune, down: false);
    }
    // 清空输入框，避免累积（清空时不要再触发转发）。
    _imeTextLocked = true;
    _imeController.clear();
    _imeTextLocked = false;
  }

  /// 点击画面或键盘按钮时唤起系统软键盘（隐藏 TextField 承接输入）。
  void _openKeyboard() {
    _focusNode ??= FocusNode();
    _focusNode!.requestFocus();
  }

  /// 键盘按钮：已聚焦则收起键盘，否则唤起。
  void _toggleKeyboardButton() {
    _focusNode ??= FocusNode();
    if (_focusNode!.hasFocus) {
      _focusNode!.unfocus();
      FocusManager.instance.primaryFocus?.unfocus();
    } else {
      _openKeyboard();
    }
  }



  Widget _buildBody(TThemeData theme) {
    if (_state != RfbState.connected || _frameImage == null) {
      return _buildStatusBody(theme);
    }

    return Stack(
      children: [
        Positioned.fill(child: _buildRemoteSurface()),
        Positioned(
          left: 0,
          right: 0,
          bottom: AppSpacing.gapL,
          child: IgnorePointer(
            child: AnimatedOpacity(
              opacity: _showHint ? 1 : 0,
              duration: const Duration(milliseconds: 300),
              child: Center(child: _buildGestureHint(theme)),
            ),
          ),
        ),
      ],
    );
  }

  /// 居中状态页：大图标 + 主文案 + 说明 + 主/次按钮（不再是小转圈配大片空白）。
  Widget _buildStatusBody(TThemeData theme) {
    final isError = _state == RfbState.error;
    final isDisconnected = _state == RfbState.disconnected;
    final stopped = isError || isDisconnected || _state == RfbState.idle;
    final title = switch (_state) {
      RfbState.error => '连接失败',
      RfbState.disconnected => '连接已断开',
      _ => '正在连接远程桌面',
    };
    final description = _message?.trim() ?? '';

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(
          horizontal: 32,
          vertical: AppSpacing.gapXl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (isError)
              Icon(Icons.error_outline, color: theme.errorColor, size: 48)
            else if (isDisconnected)
              const Icon(
                Icons.desktop_access_disabled,
                color: _barSubtle,
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
            if (description.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.gapS),
              Text(
                description,
                textAlign: TextAlign.center,
                style: AppType.secondary(theme)
                    .copyWith(color: _barSubtle),
              ),
            ],
            const SizedBox(height: AppSpacing.gapXl),
            SizedBox(
              width: double.infinity,
              child: TButton(
                size: TButtonSize.large,
                colorPreset: TButtonColorPreset.primary,
                onPressed: _retry,
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
                child: const Text('返回服务详情'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// 手势提示：半透明胶囊，3 秒后由 AnimatedOpacity 淡出。
  Widget _buildGestureHint(TThemeData theme) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: ConsoleColors.scrim,
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Text(
        '双指缩放 · 拖动平移 · 点击操作',
        style: AppType.caption(theme).copyWith(color: Colors.white),
      ),
    );
  }

  Widget _buildRemoteSurface() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final size = Size(constraints.maxWidth, constraints.maxHeight);
        return Stack(
          children: [
            // 0 尺寸隐藏输入框：承接软键盘，转成 RFB 按键。
            Positioned(
              left: 0,
              top: 0,
              width: 1,
              height: 1,
              child: Opacity(
                opacity: 0,
                child: TextField(
                  controller: _imeController,
                  focusNode: _focusNode ??= FocusNode(),
                  onChanged: _onImeChanged,
                  enableSuggestions: false,
                  autocorrect: false,
                  showCursor: false,
                  keyboardType: TextInputType.text,
                  decoration: const InputDecoration(
                    border: InputBorder.none,
                    isCollapsed: true,
                  ),
                ),
              ),
            ),
            Positioned.fill(
              child: _buildFocusSurface(size),
            ),
          ],
        );
      },
    );
  }

  Widget _buildFocusSurface(Size size) {
        return Focus(
          skipTraversal: true,
          onKeyEvent: (node, event) {
            final rfb = _rfb;
            if (rfb == null || !rfb.isConnected) return KeyEventResult.ignored;
            final keysym = _keysymOf(event.logicalKey);
            if (keysym == null) return KeyEventResult.ignored;
            if (event is KeyDownEvent || event is KeyRepeatEvent) {
              rfb.sendKey(keysym, down: true);
              return KeyEventResult.handled;
            }
            if (event is KeyUpEvent) {
              rfb.sendKey(keysym, down: false);
              return KeyEventResult.handled;
            }
            return KeyEventResult.ignored;
          },
          child: Listener(
            onPointerDown: (event) {
              _openKeyboard();
              if (event.buttons & kPrimaryButton != 0) {
                _buttonMask = 1;
              } else if (event.buttons & kSecondaryButton != 0) {
                _buttonMask = 4;
              } else if (event.buttons & kMiddleMouseButton != 0) {
                _buttonMask = 2;
              }
              _sendPointer(event.localPosition, size);
            },
            onPointerMove: (event) {
              _sendPointer(event.localPosition, size);
            },
            onPointerUp: (event) {
              _buttonMask = 0;
              _sendPointer(event.localPosition, size);
            },
            onPointerSignal: (event) {
              if (event is PointerScrollEvent) {
                // 滚轮：RFB 用按钮 4（上）/5（下）
                final rfb = _rfb;
                if (rfb == null || !rfb.isConnected) return;
                final remote = _toRemote(event.localPosition, size);
                final button = event.scrollDelta.dy < 0 ? 4 : 5;
                rfb.sendPointer(remote.dx.round(), remote.dy.round(), button);
                rfb.sendPointer(remote.dx.round(), remote.dy.round(), 0);
              }
            },
            child: GestureDetector(
              onScaleStart: (details) {
                _lastScale = _scale;
              },
              onScaleUpdate: (details) {
                if (details.pointerCount >= 2) {
                  setState(() {
                    _scale = (_lastScale * details.scale).clamp(0.5, 4.0);
                    _offset += details.focalPointDelta;
                  });
                } else {
                  setState(() {
                    _offset += details.focalPointDelta;
                  });
                }
              },
              child: ClipRect(
                child: Transform.translate(
                  offset: _offset,
                  child: Transform.scale(
                    scale: _scale,
                    alignment: Alignment.topLeft,
                    child: SizedBox(
                      width: size.width,
                      height: size.height,
                      child: Center(
                        child: FittedBox(
                          fit: BoxFit.contain,
                          child: SizedBox(
                            width: _frameWidth.toDouble(),
                            height: _frameHeight.toDouble(),
                            child: RawImage(
                              image: _frameImage,
                              filterQuality: FilterQuality.none,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
  }

  Widget _buildToolbar(TThemeData theme) {
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
            _toolButton(theme, 'Ctrl', onTap: () => _tapKey(0xFFE3)),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton(theme, 'Esc', onTap: () => _tapKey(0xFF1B)),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton(theme, 'Tab', onTap: () => _tapKey(0xFF09)),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton(theme, '↑', onTap: () => _tapKey(0xFF52)),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton(theme, '↓', onTap: () => _tapKey(0xFF54)),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton(theme, '←', onTap: () => _tapKey(0xFF51)),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton(theme, '→', onTap: () => _tapKey(0xFF53)),
            const SizedBox(width: AppSpacing.gapS),
            _toolButton(theme, '粘贴', onTap: _pasteToRemote),
          ],
        ),
      ),
    );
  }

  /// 工具条按钮统一 40 高、8 圆角，按下时给出水波纹反馈。
  Widget _toolButton(TThemeData theme, String label, {required VoidCallback onTap}) {
    return Semantics(
      button: true,
      label: label,
      child: Material(
        color: _toolBackground,
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
                color: _barText,
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _tapKey(int keysym) {
    final rfb = _rfb;
    if (rfb == null) return;
    rfb.sendKey(keysym, down: true);
    rfb.sendKey(keysym, down: false);
  }

  Future<void> _pasteToRemote() async {
    final rfb = _rfb;
    if (rfb == null) return;
    final text = await _readClipboard();
    if (text.isEmpty) return;
    for (final code in text.codeUnits) {
      rfb.sendKey(code, down: true);
      rfb.sendKey(code, down: false);
    }
  }

  Future<String> _readClipboard() async {
    final data = await Clipboard.getData(Clipboard.kTextPlain);
    return data?.text ?? '';
  }

  Map<String, dynamic> _mapOf(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return value.map((k, v) => MapEntry('$k', v));
    return <String, dynamic>{};
  }
}

/// 顶栏图标动作：44×44 可点区域，深色顶栏下用浅色图标。
class _TopBarIconAction extends StatelessWidget {
  const _TopBarIconAction({
    required this.icon,
    required this.semanticLabel,
    required this.onTap,
  });

  final IconData icon;
  final String semanticLabel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: semanticLabel,
      child: InkResponse(
        onTap: onTap,
        radius: 22,
        child: SizedBox(
          width: 44,
          height: 44,
          child: Icon(icon, size: 22, color: _barText),
        ),
      ),
    );
  }
}
