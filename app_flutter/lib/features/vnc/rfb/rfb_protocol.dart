import 'dart:typed_data';

/// RFB（VNC）协议常量与字节编解码工具。
///
/// 参考 RFC 6143（The Remote Framebuffer Protocol）与 noVNC rfbproto 实现。
/// 只实现 App 首版所需：协议 3.3/3.7/3.8、安全类型 None / VNC Authentication、
/// 编码 Raw / CopyRect。
class Rfb {
  Rfb._();

  /// 客户端消息类型。
  static const int msgSetPixelFormat = 0;
  static const int msgSetEncodings = 2;
  static const int msgFramebufferUpdateRequest = 3;
  static const int msgKeyEvent = 4;
  static const int msgPointerEvent = 5;
  static const int msgClientCutText = 6;

  /// 服务端消息类型。
  static const int msgFramebufferUpdate = 0;
  static const int msgSetColourMapEntries = 1;
  static const int msgBell = 2;
  static const int msgServerCutText = 3;

  /// 安全类型。
  static const int securityInvalid = 0;
  static const int securityNone = 1;
  static const int securityVncAuth = 2;

  /// 编码类型（负数，取 32 位有符号）。
  static const int encodingRaw = 0;
  static const int encodingCopyRect = 1;
  static const int encodingDesktopSize = -223;

  /// 客户端 VNC 认证：把 8 字节密码（截断/补零）用 DES 加密 challenge。
  ///
  /// RFB 的 VNC Authentication 特殊之处：每个密码字节的比特顺序反转后作为 DES key，
  /// challenge 作为明文，16 字节密文回传给服务端。
  static Uint8List encryptVncChallenge(String password, Uint8List challenge) {
    final key = Uint8List(8);
    final bytes = password.codeUnits;
    for (var i = 0; i < 8; i++) {
      final byte = i < bytes.length ? bytes[i] : 0;
      key[i] = _reverseBits(byte);
    }
    return _desEncrypt(key, challenge.sublist(0, 16));
  }

  static int _reverseBits(int value) {
    var result = 0;
    for (var i = 0; i < 8; i++) {
      result = (result << 1) | ((value >> i) & 1);
    }
    return result;
  }

  /// 极简 DES-ECB 加密（单块，RFB challenge 只有 16 字节 = 2 块）。
  static Uint8List _desEncrypt(Uint8List key, Uint8List data) {
    final out = Uint8List(data.length);
    for (var offset = 0; offset + 8 <= data.length; offset += 8) {
      final block = data.sublist(offset, offset + 8);
      out.setRange(offset, offset + 8, _desEncryptBlock(key, block));
    }
    return out;
  }

  // ── DES 表 ────────────────────────────────────────────────────────────
  static const List<int> _ip = [
    58, 50, 42, 34, 26, 18, 10, 2, 60, 52, 44, 36, 28, 20, 12, 4,
    62, 54, 46, 38, 30, 22, 14, 6, 64, 56, 48, 40, 32, 24, 16, 8,
    57, 49, 41, 33, 25, 17, 9, 1, 59, 51, 43, 35, 27, 19, 11, 3,
    61, 53, 45, 37, 29, 21, 13, 5, 63, 55, 47, 39, 31, 23, 15, 7,
  ];
  static const List<int> _fp = [
    40, 8, 48, 16, 56, 24, 64, 32, 39, 7, 47, 15, 55, 23, 63, 31,
    38, 6, 46, 14, 54, 22, 62, 30, 37, 5, 45, 13, 53, 21, 61, 29,
    36, 4, 44, 12, 52, 20, 60, 28, 35, 3, 43, 11, 51, 19, 59, 27,
    34, 2, 42, 10, 50, 18, 58, 26, 33, 1, 41, 9, 49, 17, 57, 25,
  ];
  static const List<int> _e = [
    32, 1, 2, 3, 4, 5, 4, 5, 6, 7, 8, 9,
    8, 9, 10, 11, 12, 13, 12, 13, 14, 15, 16, 17,
    16, 17, 18, 19, 20, 21, 20, 21, 22, 23, 24, 25,
    24, 25, 26, 27, 28, 29, 28, 29, 30, 31, 32, 1,
  ];
  static const List<int> _p = [
    16, 7, 20, 21, 29, 12, 28, 17, 1, 15, 23, 26, 5, 18, 31, 10,
    2, 8, 24, 14, 32, 27, 3, 9, 19, 13, 30, 6, 22, 11, 4, 25,
  ];
  static const List<int> _pc1 = [
    57, 49, 41, 33, 25, 17, 9, 1, 58, 50, 42, 34, 26, 18,
    10, 2, 59, 51, 43, 35, 27, 19, 11, 3, 60, 52, 44, 36,
    63, 55, 47, 39, 31, 23, 15, 7, 62, 54, 46, 38, 30, 22,
    14, 6, 61, 53, 45, 37, 29, 21, 13, 5, 28, 20, 12, 4,
  ];
  static const List<int> _pc2 = [
    14, 17, 11, 24, 1, 5, 3, 28, 15, 6, 21, 10,
    23, 19, 12, 4, 26, 8, 16, 7, 27, 20, 13, 2,
    41, 52, 31, 37, 47, 55, 30, 40, 51, 45, 33, 48,
    44, 49, 39, 56, 34, 53, 46, 42, 50, 36, 29, 32,
  ];
  static const List<int> _shifts = [
    1, 1, 2, 2, 2, 2, 2, 2, 1, 2, 2, 2, 2, 2, 2, 1,
  ];
  static const List<List<int>> _sboxes = [
    [14, 4, 13, 1, 2, 15, 11, 8, 3, 10, 6, 12, 5, 9, 0, 7,
     0, 15, 7, 4, 14, 2, 13, 1, 10, 6, 12, 11, 9, 5, 3, 8,
     4, 1, 14, 8, 13, 6, 2, 11, 15, 12, 9, 7, 3, 10, 5, 0,
     15, 12, 8, 2, 4, 9, 1, 7, 5, 11, 3, 14, 10, 0, 6, 13],
    [15, 1, 8, 14, 6, 11, 3, 4, 9, 7, 2, 13, 12, 0, 5, 10,
     3, 13, 4, 7, 15, 2, 8, 14, 12, 0, 1, 10, 6, 9, 11, 5,
     0, 14, 7, 11, 10, 4, 13, 1, 5, 8, 12, 6, 9, 3, 2, 15,
     13, 8, 10, 1, 3, 15, 4, 2, 11, 6, 7, 12, 0, 5, 14, 9],
    [10, 0, 9, 14, 6, 3, 15, 5, 1, 13, 12, 7, 11, 4, 2, 8,
     13, 7, 0, 9, 3, 4, 6, 10, 2, 8, 5, 14, 12, 11, 15, 1,
     13, 6, 4, 9, 8, 15, 3, 0, 11, 1, 2, 12, 5, 10, 14, 7,
     1, 10, 13, 0, 6, 9, 8, 7, 4, 15, 14, 3, 11, 5, 2, 12],
    [7, 13, 14, 3, 0, 6, 9, 10, 1, 2, 8, 5, 11, 12, 4, 15,
     13, 8, 11, 5, 6, 15, 0, 3, 4, 7, 2, 12, 1, 10, 14, 9,
     10, 6, 9, 0, 12, 11, 7, 13, 15, 1, 3, 14, 5, 2, 8, 4,
     3, 15, 0, 6, 10, 1, 13, 8, 9, 4, 5, 11, 12, 7, 2, 14],
    [2, 12, 4, 1, 7, 10, 11, 6, 8, 5, 3, 15, 13, 0, 14, 9,
     14, 11, 2, 12, 4, 7, 13, 1, 5, 0, 15, 10, 3, 9, 8, 6,
     4, 2, 1, 11, 10, 13, 7, 8, 15, 9, 12, 5, 6, 3, 0, 14,
     11, 8, 12, 7, 1, 14, 2, 13, 6, 15, 0, 9, 10, 4, 5, 3],
    [12, 1, 10, 15, 9, 2, 6, 8, 0, 13, 3, 4, 14, 7, 5, 11,
     10, 15, 4, 2, 7, 12, 9, 5, 6, 1, 13, 14, 0, 11, 3, 8,
     9, 14, 15, 5, 2, 8, 12, 3, 7, 0, 4, 10, 1, 13, 11, 6,
     4, 3, 2, 12, 9, 5, 15, 10, 11, 14, 1, 7, 6, 0, 8, 13],
    [4, 11, 2, 14, 15, 0, 8, 13, 3, 12, 9, 7, 5, 10, 6, 1,
     13, 0, 11, 7, 4, 9, 1, 10, 14, 3, 5, 12, 2, 15, 8, 6,
     1, 4, 11, 13, 12, 3, 7, 14, 10, 15, 6, 8, 0, 5, 9, 2,
     6, 11, 13, 8, 1, 4, 10, 7, 9, 5, 0, 15, 14, 2, 3, 12],
    [13, 2, 8, 4, 6, 15, 11, 1, 10, 9, 3, 14, 5, 0, 12, 7,
     1, 15, 13, 8, 10, 3, 7, 4, 12, 5, 6, 11, 0, 14, 9, 2,
     7, 11, 4, 1, 9, 12, 14, 2, 0, 6, 10, 13, 15, 3, 5, 8,
     2, 1, 14, 7, 4, 10, 8, 13, 15, 12, 9, 0, 3, 5, 6, 11],
  ];

  static int _permute(int value, int bits, List<int> table) {
    var result = 0;
    for (final position in table) {
      result = (result << 1) | ((value >> (bits - position)) & 1);
    }
    return result;
  }

  static Uint8List _desEncryptBlock(Uint8List key, Uint8List block) {
    var keyBits = 0;
    for (final byte in key) {
      keyBits = (keyBits << 8) | byte;
    }
    final permutedKey = _permute(keyBits, 64, _pc1);
    var c = (permutedKey >> 28) & 0x0FFFFFFF;
    var d = permutedKey & 0x0FFFFFFF;

    final subkeys = List<int>.filled(16, 0);
    for (var round = 0; round < 16; round++) {
      final shift = _shifts[round];
      c = ((c << shift) | (c >> (28 - shift))) & 0x0FFFFFFF;
      d = ((d << shift) | (d >> (28 - shift))) & 0x0FFFFFFF;
      subkeys[round] = _permute((c << 28) | d, 56, _pc2);
    }

    var dataBits = 0;
    for (final byte in block) {
      dataBits = (dataBits << 8) | byte;
    }
    final permuted = _permute(dataBits, 64, _ip);
    var left = (permuted >> 32) & 0xFFFFFFFF;
    var right = permuted & 0xFFFFFFFF;

    for (var round = 0; round < 16; round++) {
      final f = _feistel(right, subkeys[round]);
      final next = left ^ f;
      left = right;
      right = next;
    }

    final preOutput = (right << 32) | left;
    final result = _permute(preOutput, 64, _fp);
    final out = Uint8List(8);
    var remaining = result;
    for (var i = 7; i >= 0; i--) {
      out[i] = remaining & 0xFF;
      remaining >>= 8;
    }
    return out;
  }

  static int _feistel(int right, int subkey) {
    final expanded = _permute(right, 32, _e) ^ subkey;
    var sOutput = 0;
    for (var i = 0; i < 8; i++) {
      final chunk = (expanded >> (42 - 6 * i)) & 0x3F;
      final row = ((chunk >> 4) & 0x2) | (chunk & 0x1);
      final column = (chunk >> 1) & 0xF;
      sOutput = (sOutput << 4) | _sboxes[i][row * 16 + column];
    }
    return _permute(sOutput, 32, _p);
  }
}

/// 大端 / 小端读写工具。
class ByteReader {
  ByteReader(this._data);

  final Uint8List _data;
  int offset = 0;

  int get remaining => _data.length - offset;

  int readU8() {
    _check(1);
    return _data[offset++];
  }

  int readU16() {
    _check(2);
    final value = (_data[offset] << 8) | _data[offset + 1];
    offset += 2;
    return value;
  }

  int readS32() {
    _check(4);
    final value = (_data[offset] << 24) |
        (_data[offset + 1] << 16) |
        (_data[offset + 2] << 8) |
        _data[offset + 3];
    offset += 4;
    // 转有符号
    return value >= 0x80000000 ? value - 0x100000000 : value;
  }

  int readU32() {
    _check(4);
    final value = (_data[offset] << 24) |
        (_data[offset + 1] << 16) |
        (_data[offset + 2] << 8) |
        _data[offset + 3];
    offset += 4;
    return value;
  }

  Uint8List readBytes(int length) {
    _check(length);
    final result = _data.sublist(offset, offset + length);
    offset += length;
    return result;
  }

  void skip(int length) {
    _check(length);
    offset += length;
  }

  void _check(int length) {
    if (remaining < length) {
      throw StateError('RFB 数据包不完整：需要 $length 字节，剩余 $remaining');
    }
  }
}

class ByteWriter {
  final BytesBuilder _builder = BytesBuilder();

  void writeU8(int value) => _builder.addByte(value & 0xFF);

  void writeU16(int value) {
    _builder.addByte((value >> 8) & 0xFF);
    _builder.addByte(value & 0xFF);
  }

  void writeU32(int value) {
    _builder.addByte((value >> 24) & 0xFF);
    _builder.addByte((value >> 16) & 0xFF);
    _builder.addByte((value >> 8) & 0xFF);
    _builder.addByte(value & 0xFF);
  }

  void writeS32(int value) => writeU32(value < 0 ? value + 0x100000000 : value);

  void writeBytes(List<int> bytes) => _builder.add(bytes);

  Uint8List takeBytes() => _builder.takeBytes();
}
