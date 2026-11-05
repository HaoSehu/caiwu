import 'package:caiwu_app/app.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('App 可构建（不依赖网络）', (tester) async {
    await tester.pumpWidget(const CaiwuApp());
    await tester.pump();
    // 启动后应落在登录页（无本地会话时由路由守卫重定向）。
    expect(find.text('Caiwu 云控制台'), findsOneWidget);
  });
}
