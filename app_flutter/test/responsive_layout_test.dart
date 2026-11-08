import 'package:caiwu_app/core/layout/responsive.dart';
import 'package:caiwu_app/core/theme/app_theme.dart';
import 'package:caiwu_app/core/theme/app_tokens.dart';
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

    test('手机横屏按短边判定，不升级为平板', () {
      // 844×390 是 iPhone 14 横屏：宽度 844 >= 840，但设备仍是手机。
      expect(Breakpoints.resolve(const Size(844, 390)), WindowSize.compact);
      expect(Breakpoints.resolve(const Size(915, 412)), WindowSize.compact);
      // 竖屏手机保持 compact。
      expect(Breakpoints.resolve(const Size(390, 844)), WindowSize.compact);
    });

    test('平板竖屏与横屏按宽度分档', () {
      expect(Breakpoints.resolve(const Size(834, 1112)), WindowSize.medium);
      expect(Breakpoints.resolve(const Size(1112, 834)), WindowSize.expanded);
      expect(Breakpoints.resolve(const Size(600, 960)), WindowSize.medium);
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
        Size screen, {
        bool landscapePhone = false,
      }) async {
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
              isLandscapePhone: landscapePhone,
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
      // 手机横屏：宽度已超过 600，但仍收窄到 phoneLandscapeMaxWidth。
      expect(
        await widthAt(
          WindowSize.compact,
          const Size(844, 390),
          landscapePhone: true,
        ),
        Breakpoints.phoneLandscapeMaxWidth,
      );
    });
  });

  group('AppSpacing', () {
    test('触控热区不低于 44', () {
      expect(AppSpacing.minTapTarget, 44);
    });

    test('区块间距平板更大', () {
      // 列表流页面：手机 20、平板 28（实机验收后收紧的节奏）。
      expect(AppSpacing.sectionGap(false), 20);
      expect(AppSpacing.sectionGap(true), 28);
      expect(
        AppSpacing.sectionGap(true),
        greaterThan(AppSpacing.sectionGap(false)),
      );
    });
  });

  group('AppFieldGrid', () {
    testWidgets('平板平板列数更多，字段不铺满整行', (tester) async {
      Future<int> columnsAt(WindowSize size) async {
        tester.view.physicalSize = const Size(1000, 800);
        tester.view.devicePixelRatio = 1.0;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        late int columns;
        await tester.pumpWidget(
          MaterialApp(
            theme: AppTheme.lightTheme(),
            home: WindowSizeProvider(
              size: size,
              width: 1000,
              child: Builder(
                builder: (context) {
                  columns = context.gridColumns;
                  return const SizedBox.shrink();
                },
              ),
            ),
          ),
        );
        return columns;
      }

      expect(await columnsAt(WindowSize.compact), 2);
      expect(await columnsAt(WindowSize.medium), 3);
      expect(await columnsAt(WindowSize.expanded), 4);
    });
  });
}
