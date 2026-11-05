import 'package:caiwu_app/app.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// 响应式布局冒烟：手机 / 平板 / 横屏三种尺寸下 App 都能构建且无溢出异常。
///
/// 这些用例只验证「布局不炸」，不依赖网络（登录态缺失会停在登录页）。
void main() {
  Future<void> pumpAt(WidgetTester tester, Size size) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(const CaiwuApp());
    await tester.pump(const Duration(milliseconds: 100));
    // 渲染异常（溢出、断言）会通过 FlutterError 抛出，这里断言没有未捕获错误。
    expect(tester.takeException(), isNull);
  }

  testWidgets('手机竖屏（390×844）', (tester) async {
    await pumpAt(tester, const Size(390, 844));
    expect(find.text('Caiwu 云控制台'), findsOneWidget);
  });

  testWidgets('平板竖屏（834×1112）', (tester) async {
    await pumpAt(tester, const Size(834, 1112));
    expect(find.text('Caiwu 云控制台'), findsOneWidget);
  });

  testWidgets('平板横屏（1194×834）', (tester) async {
    await pumpAt(tester, const Size(1194, 834));
    expect(find.text('Caiwu 云控制台'), findsOneWidget);
  });

  testWidgets('小屏手机（320×568）不溢出', (tester) async {
    await pumpAt(tester, const Size(320, 568));
    expect(tester.takeException(), isNull);
  });
}
