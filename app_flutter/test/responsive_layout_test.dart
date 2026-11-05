import 'package:caiwu_app/core/layout/responsive.dart';
import 'package:caiwu_app/core/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// 响应式基础能力与关键布局规则的单测。
void main() {
  group('Breakpoints', () {
    test('按宽度分档', () {
      expect(Breakpoints.of(320), WindowSize.compact);
      expect(Breakpoints.of(599), WindowSize.compact);
      expect(Breakpoints.of(600), WindowSize.medium);
      expect(Breakpoints.of(839), WindowSize.medium);
      expect(Breakpoints.of(840), WindowSize.expanded);
      expect(Breakpoints.of(1194), WindowSize.expanded);
    });

    test('内容与表单最大宽度恒定', () {
      expect(Breakpoints.contentMaxWidth, 840);
      expect(Breakpoints.formMaxWidth, 560);
      // 表单限宽必须小于内容限宽，否则嵌套时失去意义。
      expect(Breakpoints.formMaxWidth, lessThan(Breakpoints.contentMaxWidth));
    });
  });

  group('WindowSizeProvider', () {
    testWidgets('注入窗口尺寸并向下传递', (tester) async {
      late bool tablet;
      late double padding;
      late int columns;

      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme(),
          home: Builder(
            builder: (context) => WindowSizeProvider(
              size: WindowSize.expanded,
              width: 1194,
              child: Builder(
                builder: (inner) {
                  tablet = inner.isTablet;
                  padding = inner.pagePadding;
                  columns = inner.gridColumns;
                  return const SizedBox.shrink();
                },
              ),
            ),
          ),
        ),
      );

      expect(tablet, isTrue);
      expect(padding, 24);
      expect(columns, 4);
    });

    testWidgets('手机断点取窄边距与 2 列', (tester) async {
      late bool tablet;
      late double padding;
      late int columns;

      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme(),
          home: WindowSizeProvider(
            size: WindowSize.compact,
            width: 390,
            child: Builder(
              builder: (inner) {
                tablet = inner.isTablet;
                padding = inner.pagePadding;
                columns = inner.gridColumns;
                return const SizedBox.shrink();
              },
            ),
          ),
        ),
      );

      expect(tablet, isFalse);
      expect(padding, 16);
      expect(columns, 2);
    });

    testWidgets('未包裹时安全回落到 compact', (tester) async {
      late WindowSize size;
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme(),
          home: Builder(
            builder: (context) {
              size = context.windowSize;
              return const SizedBox.shrink();
            },
          ),
        ),
      );
      expect(size, WindowSize.compact);
    });
  });

  group('ContentWidthLimiter', () {
    testWidgets('手机不限宽，平板限宽并居中', (tester) async {
      Future<double> widthAt(
        WindowSize size,
        Size screen,
      ) async {
        tester.view.physicalSize = screen;
        tester.view.devicePixelRatio = 1.0;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);

        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.lightTheme(),
            home: WindowSizeProvider(
              size: size,
              width: screen.width,
              child: Scaffold(
                body: ContentWidthLimiter(
                  child: Container(key: const Key('content'), height: 40),
                ),
              ),
            ),
          ),
        );
        return tester.getSize(find.byKey(const Key('content'))).width;
      }

      // 手机：不超过屏幕宽度。
      expect(await widthAt(WindowSize.compact, const Size(390, 844)), 390);
      // 平板：夹到 contentMaxWidth 并居中。
      expect(
        await widthAt(WindowSize.expanded, const Size(1600, 900)),
        Breakpoints.contentMaxWidth,
      );
    });
  });
}
