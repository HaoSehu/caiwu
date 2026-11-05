import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:caiwu_app/features/vnc/rfb/rfb_client.dart';
import 'package:flutter_test/flutter_test.dart';

/// RFB 握手回归：用构造的字节流模拟服务端，验证真实服务端的两种常见形态
/// 都能完成握手（这两处在审查中曾导致"永远连不上"）。
void main() {
  /// 构造 ServerInit：宽、高、16 字节像素格式、名称长度、名称。
  List<int> serverInit(int width, int height, String name) {
    final nameBytes = utf8.encode(name);
    return [
      (width >> 8) & 0xFF, width & 0xFF,
      (height >> 8) & 0xFF, height & 0xFF,
      // pixel format（16 字节，客户端会覆盖，内容无所谓）
      ...List<int>.filled(16, 0),
      (nameBytes.length >> 24) & 0xFF,
      (nameBytes.length >> 16) & 0xFF,
      (nameBytes.length >> 8) & 0xFF,
      nameBytes.length & 0xFF,
      ...nameBytes,
    ];
  }

  test('RFB 3.8 + 安全类型 None：必须消费 SecurityResult，否则握手挂死', () async {
    final frames = <Uint8List>[];
    final client = RfbClient(
      onRect: (_) {},
      onStateChanged: (state, message) {
        if (state == RfbState.connected) {
          frames.add(Uint8List.fromList([state.index]));
        }
      },
    );

    final input = StreamController<Uint8List>();
    client.attach(input.stream);
    client.start(password: '');

    // 服务端版本
    input.add(Uint8List.fromList(ascii.encode('RFB 003.008\n')));
    await pumpEventQueue();
    // 安全类型：1 种，类型 1（None）
    input.add(Uint8List.fromList([1, 1]));
    await pumpEventQueue();
    // 3.8 的 None 也会先下发 SecurityResult（4 字节 0）——旧实现漏读，导致错位
    input.add(Uint8List.fromList([0, 0, 0, 0]));
    await pumpEventQueue();
    // ServerInit
    input.add(Uint8List.fromList(serverInit(1024, 768, 'test')));
    await pumpEventQueue();

    expect(client.state, RfbState.connected);
    expect(client.frameWidth, 1024);
    expect(client.frameHeight, 768);

    await input.close();
    client.dispose();
  });

  test('RFB 3.3：安全类型是 32 位整数，必须读完整 U32', () async {
    final client = RfbClient(
      onRect: (_) {},
      onStateChanged: (_, __) {},
    );
    final input = StreamController<Uint8List>();
    client.attach(input.stream);
    client.start(password: '');

    input.add(Uint8List.fromList(ascii.encode('RFB 003.003\n')));
    await pumpEventQueue();
    // 3.3：4 字节大端整数 1 = None（旧实现取首字节得 0，判为非法类型）
    input.add(Uint8List.fromList([0, 0, 0, 1]));
    await pumpEventQueue();
    input.add(Uint8List.fromList(serverInit(800, 600, 'old')));
    await pumpEventQueue();

    expect(client.state, RfbState.connected);
    expect(client.frameWidth, 800);
    expect(client.frameHeight, 600);

    await input.close();
    client.dispose();
  });

  test('跨包分片：版本号被切成两段也能握手', () async {
    final client = RfbClient(onRect: (_) {}, onStateChanged: (_, __) {});
    final input = StreamController<Uint8List>();
    client.attach(input.stream);
    client.start(password: '');

    input.add(Uint8List.fromList(ascii.encode('RFB 003.')));
    await pumpEventQueue();
    input.add(Uint8List.fromList(ascii.encode('008\n')));
    await pumpEventQueue();
    input.add(Uint8List.fromList([1, 1]));
    await pumpEventQueue();
    input.add(Uint8List.fromList([0, 0, 0, 0]));
    await pumpEventQueue();
    input.add(Uint8List.fromList(serverInit(640, 480, 'split')));
    await pumpEventQueue();

    expect(client.state, RfbState.connected);
    expect(client.frameWidth, 640);

    await input.close();
    client.dispose();
  });
}
