import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';

import 'rfb_protocol.dart';

/// RFB 客户端状态。
enum RfbState { idle, connecting, authenticating, connected, disconnected, error }

/// 帧缓冲矩形更新（RGBA 像素）。
class RfbRect {
  RfbRect({
    required this.x,
    required this.y,
    required this.width,
    required this.height,
    required this.pixels,
  });

  final int x;
  final int y;
  final int width;
  final int height;

  /// RGBA8888，长度 = width * height * 4。
  final Uint8List pixels;
}

/// 最小 RFB（VNC）客户端，运行在任意双向字节流上（App 里是 WebSocket）。
///
/// 支持：协议 3.3/3.7/3.8、安全类型 None / VNC Authentication、Raw / CopyRect 编码、
/// DesktopSize 伪编码。服务端必须至少支持 Raw（RFC 6143 要求）。
///
/// 使用：[attach] 挂上服务端字节流 → [start] 携带凭据开始握手 → 监听回调。
class RfbClient {
  RfbClient({
    required this.onRect,
    required this.onStateChanged,
    this.onBell,
    this.onCutText,
    this.onDesktopSize,
  });

  final void Function(RfbRect rect) onRect;
  final void Function(RfbState state, String? message) onStateChanged;
  final void Function()? onBell;
  final void Function(String text)? onCutText;
  final void Function(int width, int height)? onDesktopSize;

  /// 发往服务端的字节流。
  final StreamController<Uint8List> _out = StreamController<Uint8List>.broadcast();

  Stream<Uint8List> get output => _out.stream;

  final List<int> _pending = <int>[];
  RfbState _state = RfbState.idle;
  RfbState get state => _state;

  int frameWidth = 0;
  int frameHeight = 0;

  bool _streamAttached = false;
  bool _handshakeDone = false;
  String _version = '';

  /// 协商后的协议次版本号（3.3 → 3、3.7 → 7、3.8 → 8），决定 SecurityResult 等差异。
  int _protocolMinor = 8;
  int _securityType = -1;

  /// 认证 challenge 是否已发送（避免分片数据重复进入认证分支）。
  bool _challengeSent = false;
  bool _authenticated = false;
  String _password = '';

  // ── 生命周期 ────────────────────────────────────────────────────────

  /// 是否已携带凭据启动握手。未 start 前只缓存字节，不推进状态机，
  /// 否则会在密码尚未就绪时跑到 VncAuth 分支而报错。
  bool _started = false;

  void attach(Stream<Uint8List> stream) {
    if (_streamAttached) return;
    _streamAttached = true;
    _setState(RfbState.connecting, '正在连接');
    stream.listen(
      (data) {
        _pending.addAll(data);
        if (_started) _process();
      },
      onError: (Object error) => _fail('连接异常：$error'),
      onDone: () {
        if (_state != RfbState.error) {
          _setState(RfbState.disconnected, '连接已断开');
        }
      },
      cancelOnError: true,
    );
  }

  /// 携带凭据开始握手（服务端版本先到时也可后调，内部会续跑）。
  void start({required String password, String username = ''}) {
    _password = password;
    _started = true;
    _process();
  }

  void _setState(RfbState state, String? message) {
    _state = state;
    // 调试期输出协议进展，便于在 logcat 定位握手卡点（仅 debug 构建）。
    if (kDebugMode) {
      debugPrint('[RFB] $state ${message ?? ''} pending=${_pending.length}');
    }
    onStateChanged(state, message);
  }

  void _fail(String message) {
    if (_state == RfbState.error) return;
    if (kDebugMode) debugPrint('[RFB] FAILED: $message');
    _setState(RfbState.error, message);
  }

  void _send(List<int> bytes) {
    if (!_out.isClosed) _out.add(Uint8List.fromList(bytes));
  }

  // ── 主循环 ──────────────────────────────────────────────────────────

  void _process() {
    try {
      if (!_handshakeDone) {
        if (!_runHandshake()) return;
        _handshakeDone = true;
        _sendPixelFormat();
        _sendEncodings();
        _requestUpdate(incremental: false);
        _setState(RfbState.connected, '已连接');
        onDesktopSize?.call(frameWidth, frameHeight);
      }
      _pumpMessages();
    } catch (error) {
      _fail('$error');
    }
  }

  // ── 握手（分阶段推进，数据不足即返回 false 等下次）─────────────────

  bool _runHandshake() {
    if (_version.isEmpty) {
      if (_pending.length < 12) return false;
      _version = ascii.decode(_take(12), allowInvalid: true);
      if (!_version.startsWith('RFB ')) {
        throw StateError('目标不是 VNC 服务');
      }
      final parts = _version.substring(4, 11).split('.');
      final major = int.tryParse(parts[0]) ?? 3;
      final minor = int.tryParse(parts[1]) ?? 8;
      final reply = (major > 3 || (major == 3 && minor >= 8))
          ? 'RFB 003.008\n'
          : (minor >= 7)
              ? 'RFB 003.007\n'
              : 'RFB 003.003\n';
      _send(ascii.encode(reply));
    }

    if (_securityType < 0) {
      _protocolMinor = int.tryParse(_version.substring(4, 11).split('.')[1]) ?? 8;
      if (_protocolMinor >= 7) {
        if (_pending.isEmpty) return false;
        final count = _pending[0];
        if (count == 0) {
          if (_pending.length < 5) return false;
          _take(1);
          final reason = utf8.decode(_take(4), allowMalformed: true);
          throw StateError('服务端拒绝连接：$reason');
        }
        if (_pending.length < 1 + count) return false;
        _take(1);
        final types = _take(count);
        final supported = types
            .where((t) =>
                t == Rfb.securityVncAuth || t == Rfb.securityNone)
            .toList();
        if (supported.isEmpty) {
          throw StateError('不支持的 VNC 安全类型：$types');
        }
        _securityType = supported.contains(Rfb.securityVncAuth)
            ? Rfb.securityVncAuth
            : Rfb.securityNone;
        _send([_securityType]);
      } else {
        // RFB 3.3：安全类型是 32 位大端整数（1=None、2=VncAuth），必须读整个 U32，
        // 不能取首字节（首字节恒为 0，会把所有 3.3 服务端判成非法安全类型）。
        if (_pending.length < 4) return false;
        _securityType = ByteReader(Uint8List.fromList(_take(4))).readU32();
        if (_securityType != Rfb.securityNone &&
            _securityType != Rfb.securityVncAuth) {
          throw StateError('不支持的 VNC 安全类型：$_securityType');
        }
      }
    }

    if (!_authenticated) {
      if (_securityType == Rfb.securityVncAuth) {
        if (_password.isEmpty) {
          throw StateError('该实例需要 VNC 密码，请回实例详情重新获取');
        }
        // 用独立阶段标记，避免数据分片到达时重复进入本段（会重复发状态、
        // 甚至把 SecurityResult 当成 challenge 消费掉）。
        if (!_challengeSent) {
          _setState(RfbState.authenticating, '正在认证');
          if (_pending.length < 16) return false;
          final challenge = _take(16);
          _send(Rfb.encryptVncChallenge(_password, Uint8List.fromList(challenge)));
          _challengeSent = true;
        }
        if (_pending.length < 4) return false;
        final result = ByteReader(Uint8List.fromList(_take(4))).readU32();
        if (result != 0) {
          throw StateError('VNC 认证失败，请检查实例密码');
        }
      } else if (_protocolMinor >= 8) {
        // RFC 6143：3.8+ 对 None 安全类型也会先发 4 字节 SecurityResult（0=成功）。
        // 漏读这 4 字节会让后续 ServerInit 整体错位，表现为永远停在"正在连接"。
        if (_pending.length < 4) return false;
        final result = ByteReader(Uint8List.fromList(_take(4))).readU32();
        if (result != 0) {
          throw StateError('服务端安全协商失败');
        }
      }
      _authenticated = true;
      _send([1]); // ClientInit: shared=1
    }

    // ServerInit: width(2) height(2) pixelFormat(16) nameLength(4) name
    // 整个包一起判满，避免消费部分字节后再抛错导致状态不一致。
    if (frameWidth == 0) {
      if (_pending.length < 24) return false;
      final nameLength = _readU32(_pending, 20);
      if (_pending.length < 24 + nameLength) return false;
      final reader = ByteReader(Uint8List.fromList(_take(24 + nameLength)));
      frameWidth = reader.readU16();
      frameHeight = reader.readU16();
      reader.skip(16);
      reader.skip(4);
      reader.skip(nameLength);
      if (frameWidth <= 0 || frameHeight <= 0) {
        throw StateError('服务端未返回有效画面尺寸');
      }
    }

    return true;
  }

  // ── 服务端消息 ──────────────────────────────────────────────────────

  void _pumpMessages() {
    while (_handshakeDone && _pending.isNotEmpty) {
      if (!_handleOneMessage()) return;
    }
  }

  /// 返回 false 表示需要更多数据；抛错表示协议异常。
  bool _handleOneMessage() {
    final type = _pending[0];
    switch (type) {
      case Rfb.msgFramebufferUpdate:
        return _handleFramebufferUpdate();
      case Rfb.msgBell:
        _take(1);
        onBell?.call();
        return true;
      case Rfb.msgServerCutText:
        return _handleCutText();
      case Rfb.msgSetColourMapEntries:
        return _skipColourMap();
      default:
        throw StateError('未知的服务端消息类型 $type');
    }
  }

  bool _handleFramebufferUpdate() {
    if (_pending.length < 4) return false;
    final rectCount = (_pending[2] << 8) | _pending[3];

    var cursor = 4;
    final parsing = List<int>.of(_pending);
    final parsed = <_ParsedRect>[];
    for (var i = 0; i < rectCount; i++) {
      if (parsing.length < cursor + 12) return false;
      final x = (parsing[cursor] << 8) | parsing[cursor + 1];
      final y = (parsing[cursor + 2] << 8) | parsing[cursor + 3];
      final w = (parsing[cursor + 4] << 8) | parsing[cursor + 5];
      final h = (parsing[cursor + 6] << 8) | parsing[cursor + 7];
      final encoding = _readS32(parsing, cursor + 8);
      cursor += 12;

      if (encoding == Rfb.encodingRaw) {
        final bytes = w * h * 4;
        if (parsing.length < cursor + bytes) return false;
        final pixels = Uint8List.fromList(parsing.sublist(cursor, cursor + bytes));
        _bgraToRgba(pixels);
        parsed.add(_ParsedRect(x, y, w, h, pixels, isDesktopSize: false));
        cursor += bytes;
      } else if (encoding == Rfb.encodingCopyRect) {
        if (parsing.length < cursor + 4) return false;
        cursor += 4;
        // 未声明 CopyRect，正常不会收到；收到则跳过（不误当成尺寸变化）。
        parsed.add(_ParsedRect(x, y, w, h, Uint8List(0), isDesktopSize: false));
      } else if (encoding == Rfb.encodingDesktopSize) {
        if (w > 0 && h > 0) {
          frameWidth = w;
          frameHeight = h;
        }
        parsed.add(_ParsedRect(x, y, w, h, Uint8List(0), isDesktopSize: true));
      } else {
        throw StateError('服务端返回未请求的编码 $encoding');
      }
    }

    _take(cursor);
    for (final rect in parsed) {
      if (rect.pixels.isEmpty) {
        if (rect.isDesktopSize) {
          onDesktopSize?.call(frameWidth, frameHeight);
        }
        continue;
      }
      onRect(RfbRect(
        x: rect.x,
        y: rect.y,
        width: rect.width,
        height: rect.height,
        pixels: rect.pixels,
      ));
    }
    _requestUpdate(incremental: true);
    return true;
  }

  bool _handleCutText() {
    if (_pending.length < 8) return false;
    final length = _readU32(_pending, 4);
    if (_pending.length < 8 + length) return false;
    final text = utf8.decode(
      _pending.sublist(8, 8 + length),
      allowMalformed: true,
    );
    _take(8 + length);
    onCutText?.call(text);
    return true;
  }

  bool _skipColourMap() {
    if (_pending.length < 6) return false;
    final count = (_pending[4] << 8) | _pending[5];
    final total = 6 + count * 6;
    if (_pending.length < total) return false;
    _take(total);
    return true;
  }

  // ── 客户端消息 ──────────────────────────────────────────────────────

  void _sendPixelFormat() {
    // 32bpp / depth 24 / 小端 / true-color / RGB 各 8 位，shift: R16 G8 B0
    _send([
      Rfb.msgSetPixelFormat, 0, 0, 0,
      32, 24, 0, 1,
      0, 255, 0, 255, 0, 255,
      16, 8, 0,
      0, 0, 0,
    ]);
  }

  void _sendEncodings() {
    // 只声明 Raw：RFB 规范要求服务端必须支持 Raw。CopyRect 需要本地帧缓冲做区域拷贝，
    // 当前实现只做"整块新像素覆盖"，若声明了 CopyRect 却拷贝未实现，滚动区域会永久残缺。
    final w = ByteWriter()
      ..writeU8(Rfb.msgSetEncodings)
      ..writeU8(0)
      ..writeU16(1)
      ..writeS32(Rfb.encodingRaw);
    _send(w.takeBytes());
  }

  void _requestUpdate({required bool incremental}) {
    final w = ByteWriter()
      ..writeU8(Rfb.msgFramebufferUpdateRequest)
      ..writeU8(incremental ? 1 : 0)
      ..writeU16(0)
      ..writeU16(0)
      ..writeU16(frameWidth)
      ..writeU16(frameHeight);
    _send(w.takeBytes());
  }

  /// 键盘事件（X11 keysym）。
  void sendKey(int keysym, {required bool down}) {
    final w = ByteWriter()
      ..writeU8(Rfb.msgKeyEvent)
      ..writeU8(down ? 1 : 0)
      ..writeU8(0)
      ..writeU8(0)
      ..writeU32(keysym);
    _send(w.takeBytes());
  }

  /// 鼠标事件；buttonMask：bit0 左键、bit1 中键、bit2 右键。
  void sendPointer(int x, int y, int buttonMask) {
    if (x < 0 || y < 0 || x > 65535 || y > 65535) return;
    final w = ByteWriter()
      ..writeU8(Rfb.msgPointerEvent)
      ..writeU8(buttonMask & 0xFF)
      ..writeU16(x)
      ..writeU16(y);
    _send(w.takeBytes());
  }

  // ── 工具 ────────────────────────────────────────────────────────────

  /// 服务端按我们请求的格式回 BGRA；转成 RGBA 供 Flutter 直接解码。
  void _bgraToRgba(Uint8List pixels) {
    for (var i = 0; i + 3 < pixels.length; i += 4) {
      final b = pixels[i];
      final g = pixels[i + 1];
      final r = pixels[i + 2];
      pixels[i] = r;
      pixels[i + 1] = g;
      pixels[i + 2] = b;
      pixels[i + 3] = 255;
    }
  }

  static int _readU32(List<int> data, int offset) =>
      (data[offset] << 24) |
      (data[offset + 1] << 16) |
      (data[offset + 2] << 8) |
      data[offset + 3];

  static int _readS32(List<int> data, int offset) {
    final value = _readU32(data, offset);
    return value >= 0x80000000 ? value - 0x100000000 : value;
  }

  List<int> _take(int length) {
    final result = _pending.sublist(0, length);
    _pending.removeRange(0, length);
    return result;
  }

  bool get isConnected => _state == RfbState.connected;

  void dispose() {
    if (!_out.isClosed) {
      _out.close();
    }
  }
}

class _ParsedRect {
  _ParsedRect(
    this.x,
    this.y,
    this.width,
    this.height,
    this.pixels, {
    required this.isDesktopSize,
  });

  final int x;
  final int y;
  final int width;
  final int height;
  final Uint8List pixels;
  final bool isDesktopSize;
}
