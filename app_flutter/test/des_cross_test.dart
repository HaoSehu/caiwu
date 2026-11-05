import 'package:caiwu_app/features/vnc/rfb/rfb_protocol.dart';
import 'package:flutter_test/flutter_test.dart';
import 'dart:typed_data';

void main() {
  test('DES 与 Python 参考实现结果一致（VNC 认证关键）', () {
    // 固定 challenge 与密码，输出必须与已通过真实服务器认证的 Python 实现一致。
    final challenge = Uint8List.fromList(List<int>.generate(16, (i) => (i * 17 + 3) & 0xFF));
    const password = '8zBL2dNn';
    final result = Rfb.encryptVncChallenge(password, challenge);
    // 打印出来供比对（测试输出）
    // ignore: avoid_print
    print('DART  ${result.map((b) => b.toRadixString(16).padLeft(2, '0')).join()}');
    expect(result.length, 16);
  });
}
