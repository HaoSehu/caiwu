import 'dart:typed_data';

import 'package:caiwu_app/features/vnc/rfb/rfb_protocol.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('ByteWriter/ByteReader', () {
    test('U16/U32/S32 大端往返一致', () {
      final w = ByteWriter()
        ..writeU8(7)
        ..writeU16(0xBEEF)
        ..writeU32(0xDEADBEEF)
        ..writeS32(-1)
        ..writeS32(Rfb.encodingRaw)
        ..writeS32(Rfb.encodingDesktopSize);
      final r = ByteReader(w.takeBytes());
      expect(r.readU8(), 7);
      expect(r.readU16(), 0xBEEF);
      expect(r.readU32(), 0xDEADBEEF);
      expect(r.readS32(), -1);
      expect(r.readS32(), Rfb.encodingRaw);
      expect(r.readS32(), Rfb.encodingDesktopSize);
      expect(r.remaining, 0);
    });

    test('数据不足时抛 StateError', () {
      final r = ByteReader(Uint8List.fromList([1, 2]));
      expect(r.readU16(), 0x0102);
      expect(() => r.readU32(), throwsStateError);
    });
  });

  group('VNC 认证 DES', () {
    test('密码不足 8 位按 0 补齐、输出 16 字节', () {
      final challenge = Uint8List.fromList(List<int>.generate(16, (i) => i));
      final result = Rfb.encryptVncChallenge('abc', challenge);
      expect(result.length, 16);
      // 空密码与短密码都必须稳定输出，且不同密码结果不同
      final other = Rfb.encryptVncChallenge('abd', challenge);
      expect(result, isNot(equals(other)));
    });

    test('标准 DES 向量：key=133457799BBCDFF1, plain=0123456789ABCDEF', () {
      // VNC 认证会逐字节反转密码的比特得到 DES key；把标准 key 反过来
      // 就得到等价密码字节，从而用 NIST 公开向量验证 DES 实现。
      final standardKey = [0x13, 0x34, 0x57, 0x79, 0x9B, 0xBC, 0xDF, 0xF1];
      final passwordBytes = standardKey.map(_reverseBits).toList();
      final password = String.fromCharCodes(passwordBytes);
      final plain = Uint8List.fromList([
        0x01, 0x23, 0x45, 0x67, 0x89, 0xAB, 0xCD, 0xEF,
        0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00,
      ]);
      final result = Rfb.encryptVncChallenge(password, plain);
      expect(
        result.sublist(0, 8),
        orderedEquals([0x85, 0xE8, 0x13, 0x54, 0x0F, 0x0A, 0xB4, 0x05]),
        reason: 'NIST DES 示例向量',
      );
    });

    test('同一输入可复现', () {
      final challenge = Uint8List.fromList(List<int>.filled(16, 0x11));
      final first = Rfb.encryptVncChallenge('password', challenge);
      final second = Rfb.encryptVncChallenge('password', challenge);
      expect(first, equals(second), reason: '同一输入必须产生相同密文');
      expect(first.length, 16);
    });
  });

  group('RFB 常量', () {
    test('编码值符合 RFC 6143', () {
      expect(Rfb.encodingRaw, 0);
      expect(Rfb.encodingCopyRect, 1);
      expect(Rfb.encodingDesktopSize, -223);
    });

    test('安全类型值符合规范', () {
      expect(Rfb.securityNone, 1);
      expect(Rfb.securityVncAuth, 2);
    });
  });
}

int _reverseBits(int value) {
  var result = 0;
  for (var i = 0; i < 8; i++) {
    result = (result << 1) | ((value >> i) & 1);
  }
  return result;
}
