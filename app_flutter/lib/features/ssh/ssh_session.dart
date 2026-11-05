import 'dart:async';
import 'dart:convert';
import 'dart:io' show SocketException;

import 'package:dartssh2/dartssh2.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:xterm/xterm.dart';

import '../../core/net/api_exception.dart';
import '../../core/net/api_services.dart';
import 'host_store.dart';

/// SSH 会话状态机。
enum SshSessionState {
  idle,
  connecting,
  authenticating,
  connected,
  disconnected,
  error,
}

/// 连接目标：caiwu 托管服务，或本地保存的主机。
sealed class SshTarget {
  const SshTarget();
}

/// caiwu 托管服务：连接信息经 `/v2/client/services/{id}/connection` 获取。
class ServiceSshTarget extends SshTarget {
  const ServiceSshTarget(this.serviceId);

  final int serviceId;
}

/// 手动添加的主机：凭据由调用方从 [SshHostStore] 读出后随目标一起传入。
class HostSshTarget extends SshTarget {
  const HostSshTarget({required this.host, required this.secret});

  final SshHost host;

  /// 密码或 PEM 私钥明文（调用方从安全存储读取）。
  final String secret;
}

/// 首次连接 / 指纹变化时交给 UI 人工确认的挑战信息。
class SshHostKeyChallenge {
  const SshHostKeyChallenge({
    required this.endpoint,
    required this.type,
    required this.fingerprint,
    this.knownFingerprint,
  });

  final String endpoint;
  final String type;
  final String fingerprint;

  /// 已保存的指纹；为 null 表示首次连接。
  final String? knownFingerprint;

  bool get isChanged => knownFingerprint != null;
}

/// 返回 true 表示信任并继续连接。
typedef SshHostKeyPrompt = Future<bool> Function(SshHostKeyChallenge challenge);

/// 一条独立的 SSH 终端会话。
///
/// 负责连接建立、认证、跳板机转发、保活、host key 校验与资源释放，
/// 页面只渲染 [terminal] 并调用 [connect] / [send] / [resize]。
class SshSession extends ChangeNotifier {
  SshSession({
    required this.target,
    this.serviceApi,
    this.hostStore,
    this.onHostKeyPrompt,
  }) {
    // 默认把终端输入写入 shell；页面需要拦截 Ctrl 组合键时可覆盖该回调。
    _terminal.onOutput = send;
    _terminal.onResize = resize;
  }

  final SshTarget target;

  /// 服务模式取凭据用；主机模式不使用。
  final ServiceApi? serviceApi;

  /// 主机模式读取跳板机配置与凭据用。
  final SshHostStore? hostStore;

  /// 缺少该回调时，任何未信任的 host key 都会被拒绝。
  final SshHostKeyPrompt? onHostKeyPrompt;

  final Terminal _terminal = Terminal(maxLines: 10000);

  SshSessionState _state = SshSessionState.idle;
  String _message = '未连接';

  SSHSocket? _socket;
  SSHClient? _client;
  SSHSession? _shell;

  SSHSocket? _jumpSocket;
  SSHClient? _jumpClient;

  StreamSubscription<Uint8List>? _stdoutSub;
  StreamSubscription<Uint8List>? _stderrSub;
  final List<Timer> _keepAliveTimers = <Timer>[];

  final StreamController<String> _dataController =
      StreamController<String>.broadcast();

  /// 每次 connect / dispose 递增，用于丢弃过期的异步回调。
  int _generation = 0;
  bool _connecting = false;
  bool _disposed = false;

  SshSessionState get state => _state;

  String? get message => _message;

  /// 终端实例：页面用 `TerminalView(terminal)` 渲染，复制全部输出也读它。
  Terminal get terminal => _terminal;

  /// 解码后的输出流（可选订阅；不管是否订阅都会写入 [terminal]）。
  Stream<String> get onData => _dataController.stream;

  // ── 连接 ─────────────────────────────────────────────────────────────

  Future<void> connect() async {
    if (_disposed || _connecting) return;
    _connecting = true;
    final generation = ++_generation;
    await _teardown();
    if (!_isCurrent(generation)) {
      _connecting = false;
      return;
    }

    _setState(SshSessionState.connecting, '正在获取连接信息');
    try {
      final connection = await _resolveConnection();
      _checkCurrent(generation);

      _setState(SshSessionState.connecting, '正在连接 ${connection.endpoint}');
      await _openConnection(connection, generation);

      _checkCurrent(generation);
      _setState(SshSessionState.connected, '已连接');
    } catch (error) {
      await _teardown();
      // 连接过程中被 disconnect / 新一次 connect 打断时，不覆盖当前状态。
      if (!_isCurrent(generation)) return;
      _setState(SshSessionState.error, _errorText(error));
    } finally {
      _connecting = false;
    }
  }

  /// 主动断开当前连接，终端内容保留，可再次 [connect]。
  Future<void> disconnect() async {
    if (_disposed) return;
    // 连接尚未结束时，让进行中的 connect 在下一个检查点感知到取消。
    // 连接已建立时，标记断开并释放。
    _generation++;
    _setState(SshSessionState.disconnected, '已断开连接');
    await _teardown();
  }

  /// 连接目标解析：服务模式走 caiwu 接口，主机模式读本地配置与跳板机。
  Future<_ResolvedConnection> _resolveConnection() {
    final current = target;
    return switch (current) {
      ServiceSshTarget() => _resolveService(current),
      HostSshTarget() => _resolveHost(current),
    };
  }

  Future<_ResolvedConnection> _resolveService(ServiceSshTarget target) async {
    final api = serviceApi;
    if (api == null) {
      throw const _SshConnectError('缺少服务连接接口，无法获取 SSH 登录凭据');
    }
    final connection = await api.connection(target.serviceId);

    // NAT 控制台实例的公网 hostname/22 端口不可直连，必须走 NAT 映射地址与端口
    // （与 v4 控制台的连接端点规则一致）。
    final isNat = connection['is_nat_console'] == true;
    final hostname = _str(connection, 'hostname').trim();
    final dedicatedIp = _str(connection, 'dedicated_ip').trim();
    var host = hostname;
    var port = _int(connection, 'port');
    if (isNat) {
      final natHost = _str(connection, 'nat_remote_address').trim();
      final natHostAlt = _str(connection, 'nat_remote_host').trim();
      final natPort = _int(connection, 'nat_remote_port');
      if (natHost.isNotEmpty) {
        host = natHost;
      } else if (natHostAlt.isNotEmpty) {
        host = natHostAlt;
      }
      if (natPort > 0) port = natPort;
    }
    // 上游常把 hostname 下发为实例代号（如 ser301548566523），它不可解析；
    // 此时必须回落到真实公网 IP，否则 DNS 失败必然连不上。
    if (!_isResolvableHost(host) && _isResolvableHost(dedicatedIp)) {
      host = dedicatedIp;
    }
    if (host.isEmpty) host = dedicatedIp;
    if (port <= 0) port = 22;
    final username = _str(connection, 'username').trim();
    final password = _str(connection, 'password');

    if (host.isEmpty) {
      throw const _SshConnectError('服务未返回 SSH 连接地址');
    }
    // 主机名既不是 IP 也不是域名时（常见于上游只下发了实例代号，如 ser123），
    // DNS 必然解析失败。提前给出可理解的提示，不让用户干等 15 秒超时。
    if (!_isResolvableHost(host)) {
      throw _SshConnectError(
        '实例未分配可用 IP（当前地址：$host），暂时无法直接连接。'
        '请在服务商控制台确认实例网络状态，或联系客服处理。',
      );
    }
    if (username.isEmpty) {
      throw const _SshConnectError('服务未返回 SSH 登录用户名');
    }
    if (password.isEmpty) {
      throw const _SshConnectError('该服务暂未配置 SSH 登录密码，无法连接');
    }

    return _ResolvedConnection(
      host: host,
      port: port,
      username: username,
      password: password,
      keepAliveSeconds: _defaultKeepAliveSeconds,
      // 沿用旧键 `ssh_hostkey.<serviceId>.<host>:<port>`：同一 IP 被不同实例复用时
      // 互不污染，且不会让用户已信任的指纹记录失效。
      hostKeyPrefsKey: 'ssh_hostkey.${target.serviceId}.$host:$port',
    );
  }

  Future<_ResolvedConnection> _resolveHost(HostSshTarget target) async {
    final host = target.host;
    _ResolvedConnection? jump;
    final jumpHostId = host.jumpHostId;
    if (jumpHostId != null && jumpHostId.isNotEmpty) {
      final store = hostStore;
      if (store == null) {
        throw const _SshConnectError('缺少主机仓库，无法读取跳板机配置');
      }
      final jumpHost = store.byId(jumpHostId);
      if (jumpHost == null) {
        throw const _SshConnectError('跳板机配置不存在，请在主机编辑页重新选择跳板机');
      }
      final jumpSecret = await store.readSecret(jumpHost.id);
      if (jumpSecret.trim().isEmpty) {
        throw const _SshConnectError('跳板机未保存登录凭据，请先在主机管理中补全');
      }
      jump = _fromHost(jumpHost, jumpSecret, role: '跳板机');
    }
    return _fromHost(host, target.secret, role: '主机', jump: jump);
  }

  _ResolvedConnection _fromHost(
    SshHost host,
    String secret, {
    required String role,
    _ResolvedConnection? jump,
  }) {
    final address = host.host.trim();
    final username = host.username.trim();
    final port = host.port > 0 ? host.port : 22;
    if (address.isEmpty) {
      throw _SshConnectError('$role地址为空，请检查主机配置');
    }
    if (username.isEmpty) {
      throw _SshConnectError('$role登录用户名为空，请检查主机配置');
    }

    List<SSHKeyPair>? identities;
    String? password;
    if (host.authType == SshAuthType.privateKey) {
      identities = _parsePrivateKey(secret, '$role私钥');
    } else {
      if (secret.isEmpty) {
        throw _SshConnectError('$role未保存登录密码，请先补全主机凭据');
      }
      password = secret;
    }

    return _ResolvedConnection(
      host: address,
      port: port,
      username: username,
      password: password,
      identities: identities,
      keepAliveSeconds: host.keepAliveSeconds,
      // 手动主机按端点信任指纹：同一端点无论从哪条主机记录进入都共用一条信任记录。
      hostKeyPrefsKey: 'ssh_hostkey.$address:$port',
      jump: jump,
    );
  }

  /// 解析 PEM 私钥；失败时给出可执行的中文提示。
  List<SSHKeyPair> _parsePrivateKey(String pem, String label) {
    final text = pem.replaceAll('\r\n', '\n').trim();
    if (text.isEmpty) {
      throw _SshConnectError('$label为空，请重新粘贴');
    }
    try {
      if (SSHKeyPair.isEncryptedPem(text)) {
        throw _SshConnectError('$label带有密码保护，请先移除私钥密码后再导入');
      }
      final pairs = SSHKeyPair.fromPem(text);
      if (pairs.isEmpty) {
        throw _SshConnectError('$label内容无法识别，请确认粘贴的是完整的私钥');
      }
      return pairs;
    } on _SshConnectError {
      rethrow;
    } catch (_) {
      throw _SshConnectError(
        '$label解析失败，请确认粘贴的是完整 PEM 私钥'
        '（以 -----BEGIN OPENSSH PRIVATE KEY----- 或 -----BEGIN RSA PRIVATE KEY----- 开头）',
      );
    }
  }

  Future<void> _openConnection(
    _ResolvedConnection connection,
    int generation,
  ) async {
    final jump = connection.jump;
    if (jump != null) {
      _setState(SshSessionState.authenticating, '正在连接跳板机 ${jump.endpoint}');
      final jumpSocket = await SSHSocket.connect(
        jump.host,
        jump.port,
        timeout: const Duration(seconds: 15),
      );
      _jumpSocket = jumpSocket;
      _checkCurrent(generation);

      final jumpClient = _createClient(jump, jumpSocket);
      _jumpClient = jumpClient;
      await jumpClient.authenticated;
      _checkCurrent(generation);
      _startKeepAlive(jumpClient, jump.keepAliveSeconds);

      // dartssh2 4.1.0 的 forwardLocal 直接返回实现 SSHSocket 的转发通道，
      // 可把它当作“本地端口连接”交给目标 SSHClient，无需再手动监听本地端口。
      _setState(
        SshSessionState.authenticating,
        '正在通过跳板机转发到 ${connection.endpoint}',
      );
      final forwarded = await jumpClient.forwardLocal(
        connection.host,
        connection.port,
      );
      _socket = forwarded;
      _checkCurrent(generation);
    } else {
      final socket = await SSHSocket.connect(
        connection.host,
        connection.port,
        timeout: const Duration(seconds: 15),
      );
      _socket = socket;
      _checkCurrent(generation);
    }

    _setState(SshSessionState.authenticating, '正在验证登录凭据');
    final client = _createClient(connection, _socket!);
    _client = client;
    await client.authenticated;
    _checkCurrent(generation);
    _startKeepAlive(client, connection.keepAliveSeconds);

    _setState(SshSessionState.connecting, '正在打开终端会话');
    final shell = await client.shell(
      pty: SSHPtyConfig(
        type: 'xterm-256color',
        width: _terminal.viewWidth,
        height: _terminal.viewHeight,
      ),
    );
    if (!_isCurrent(generation)) {
      shell.close();
      throw const _SshConnectError('连接已取消');
    }
    _shell = shell;
    _bindShell(shell, generation);
  }

  SSHClient _createClient(_ResolvedConnection connection, SSHSocket socket) {
    return SSHClient(
      socket,
      username: connection.username,
      onPasswordRequest: connection.password == null
          ? null
          : () => connection.password,
      identities: connection.identities,
      onVerifyHostKey: (type, fingerprint) =>
          _verifyHostKey(connection, type, fingerprint),
      // 保活由本类按 keepAliveSeconds 统一调度，避免两套定时器重复 ping。
      keepAliveInterval: null,
      // 首次连接要在对话框里人工核对指纹，25 秒会掐断握手；
      // 留足阅读时间（用户确认后才继续 KEX）。
      handshakeTimeout: const Duration(minutes: 5),
      authTimeout: const Duration(seconds: 60),
    );
  }

  void _bindShell(SSHSession shell, int generation) {
    // 用带状态的流式解码器：SSH 信道会把一个 UTF-8 字符切到两个 chunk，
    // 逐帧 utf8.decode 会在边界产生 U+FFFD 乱码（中文/emoji 常见）。
    final stdoutDecoder = const Utf8Decoder(
      allowMalformed: true,
    ).startChunkedConversion(_StringSinkAdapter(_writeTerminal));
    final stderrDecoder = const Utf8Decoder(
      allowMalformed: true,
    ).startChunkedConversion(_StringSinkAdapter(_writeTerminal));
    _stdoutSub = shell.stdout.listen(stdoutDecoder.add);
    _stderrSub = shell.stderr.listen(stderrDecoder.add);

    unawaited(
      shell.done.then((_) {
        stdoutDecoder.close();
        stderrDecoder.close();
        if (!_isCurrent(generation) || _shell != shell) return;
        _shell = null;
        // 传输层已结束（对端断开 / 网络丢失 / client.close），定时器与 client 一并收尾，
        // 否则重连前会留下指向死连接的 keep-alive。
        unawaited(_teardown());
        _setState(SshSessionState.disconnected, '连接已断开');
      }),
    );
  }

  void _writeTerminal(String text) {
    if (_disposed) return;
    _terminal.write(text);
    if (!_dataController.isClosed) _dataController.add(text);
  }

  // ── host key ─────────────────────────────────────────────────────────

  /// 首次连接展示指纹并落盘；已保存则严格比对，不一致时交给 UI 人工复核。
  Future<bool> _verifyHostKey(
    _ResolvedConnection connection,
    String type,
    Uint8List fingerprintBytes,
  ) async {
    final fingerprint = utf8.decode(fingerprintBytes, allowMalformed: true);
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(connection.hostKeyPrefsKey);
    final known = (saved != null && saved.isNotEmpty) ? saved : null;
    if (known == fingerprint) return true;

    final prompt = onHostKeyPrompt;
    if (prompt == null || _disposed) return false;
    final accepted = await prompt(
      SshHostKeyChallenge(
        endpoint: connection.endpoint,
        type: type,
        fingerprint: fingerprint,
        knownFingerprint: known,
      ),
    );
    if (accepted != true) return false;
    await prefs.setString(connection.hostKeyPrefsKey, fingerprint);
    return true;
  }

  // ── 保活 ─────────────────────────────────────────────────────────────

  void _startKeepAlive(SSHClient client, int seconds) {
    if (seconds <= 0) return;
    _keepAliveTimers.add(
      Timer.periodic(Duration(seconds: seconds), (_) {
        unawaited(_ping(client));
      }),
    );
  }

  Future<void> _ping(SSHClient client) async {
    try {
      await client.ping();
    } catch (_) {
      // 连接断开由 shell.done / 传输层统一收尾，这里只忽略单次 ping 失败。
    }
  }

  // ── 输入 / 尺寸 ──────────────────────────────────────────────────────

  /// 写入 shell（终端输入、工具条按键、粘贴都走这里）。
  void send(String data) {
    final shell = _shell;
    if (shell == null || data.isEmpty) return;
    try {
      shell.write(Uint8List.fromList(utf8.encode(data)));
    } catch (_) {
      // 会话已关闭时忽略写入。
    }
  }

  /// 同步终端尺寸；shell 尚未就绪时忽略，PTY 会按终端当前尺寸创建。
  void resize(int cols, int rows, int pxW, int pxH) {
    final shell = _shell;
    if (shell == null || cols <= 0 || rows <= 0) return;
    try {
      shell.resizeTerminal(cols, rows, pxW, pxH);
    } catch (_) {
      // 会话关闭过程中尺寸变更可能失败，忽略即可。
    }
  }

  // ── 释放 ─────────────────────────────────────────────────────────────

  @override
  Future<void> dispose() async {
    if (_disposed) return;
    _disposed = true;
    _generation++;
    await _teardown();
    await _dataController.close();
    super.dispose();
  }

  /// 依次关闭 shell → client → socket，并取消所有订阅与定时器。
  Future<void> _teardown() async {
    for (final timer in _keepAliveTimers) {
      timer.cancel();
    }
    _keepAliveTimers.clear();

    final stdoutSub = _stdoutSub;
    _stdoutSub = null;
    final stderrSub = _stderrSub;
    _stderrSub = null;
    await stdoutSub?.cancel();
    await stderrSub?.cancel();

    final shell = _shell;
    _shell = null;
    try {
      shell?.close();
    } catch (_) {}

    final client = _client;
    _client = null;
    if (client != null) {
      try {
        await client.close();
      } catch (_) {}
    }

    final socket = _socket;
    _socket = null;
    try {
      socket?.destroy();
    } catch (_) {}

    // 跳板机资源在目标连接之后释放：先断开目标，再断开承载它的跳板机。
    final jumpClient = _jumpClient;
    _jumpClient = null;
    if (jumpClient != null) {
      try {
        await jumpClient.close();
      } catch (_) {}
    }

    final jumpSocket = _jumpSocket;
    _jumpSocket = null;
    try {
      jumpSocket?.destroy();
    } catch (_) {}
  }

  // ── 内部工具 ─────────────────────────────────────────────────────────

  bool _isCurrent(int generation) => !_disposed && generation == _generation;

  void _checkCurrent(int generation) {
    if (!_isCurrent(generation)) {
      throw const _SshConnectError('连接已取消');
    }
  }

  void _setState(SshSessionState state, String message) {
    if (_disposed) return;
    if (_state == state && _message == message) return;
    _state = state;
    _message = message;
    notifyListeners();
  }

  String _errorText(Object error) {
    if (error is _SshConnectError) return error.message;
    if (error is SSHHostkeyError) return '服务器指纹校验未通过，已断开连接';
    if (error is SSHAuthFailError) return 'SSH 认证失败：${error.message}';
    if (error is SSHAuthAbortError) return 'SSH 认证中断：${error.message}';
    if (error is SSHHandshakeError) return 'SSH 握手失败：${error.message}';
    if (error is SSHChannelOpenError) {
      return '无法打开 SSH 通道，服务器可能限制了会话数量';
    }
    if (error is SSHDisconnectError) return '服务器断开连接：${error.message}';
    if (error is SSHError) return 'SSH 连接失败，请稍后重试';
    if (error is SocketException) return '无法连接服务器，请检查网络或端口';
    if (error is TimeoutException) return '连接超时，请稍后重试';
    if (error is ApiException) return error.message;
    if (error is NetworkException) return error.message;
    return '连接失败，请稍后重试';
  }

  static String _str(Map<String, dynamic> map, String key) {
    final value = map[key];
    return value == null ? '' : '$value';
  }

  static int _int(Map<String, dynamic> map, String key) {
    final value = map[key];
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('${value ?? ''}') ?? 0;
  }

  /// 主机地址是否可解析：IPv4 / IPv6 / 含点的域名。纯代号（如 ser123）判定为不可用。
  static bool _isResolvableHost(String host) {
    if (RegExp(r'^\d{1,3}(\.\d{1,3}){3}$').hasMatch(host)) return true;
    if (host.contains(':')) return true; // IPv6
    // 域名至少包含一个点，且顶级域为字母。
    return RegExp(
      r'^[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$',
    ).hasMatch(host);
  }

  /// 服务模式的保活间隔（秒）：与 dartssh2 默认 10 秒保持一致，避免长时间无输出被网关断开。
  static const int _defaultKeepAliveSeconds = 10;
}

/// 解析后的单跳连接参数（目标机或跳板机）。
class _ResolvedConnection {
  const _ResolvedConnection({
    required this.host,
    required this.port,
    required this.username,
    required this.hostKeyPrefsKey,
    required this.keepAliveSeconds,
    this.password,
    this.identities,
    this.jump,
  });

  final String host;
  final int port;
  final String username;
  final String hostKeyPrefsKey;
  final int keepAliveSeconds;
  final String? password;
  final List<SSHKeyPair>? identities;

  /// 跳板机参数；为 null 表示直连目标机。
  final _ResolvedConnection? jump;

  String get endpoint => '$host:$port';
}

class _SshConnectError implements Exception {
  const _SshConnectError(this.message);

  final String message;

  @override
  String toString() => message;
}

/// 把解码后的字符串转发给终端；`Utf8Decoder.startChunkedConversion` 需要一个 `Sink<String>`。
class _StringSinkAdapter implements Sink<String> {
  _StringSinkAdapter(this._onData);

  final void Function(String text) _onData;

  @override
  void add(String data) => _onData(data);

  @override
  void close() {}
}
