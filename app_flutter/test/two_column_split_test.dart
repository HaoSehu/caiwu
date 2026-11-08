import 'package:caiwu_app/core/layout/responsive.dart';
import 'package:flutter_test/flutter_test.dart';

/// 双栏切分的守门测试。
///
/// 曾经把它改成「前半/后半」，结果分页追加时中点右移，已渲染的行整批换列。
/// 这里锁住「第 i 条永远落在同一栏」这个性质。
void main() {
  group('splitIntoTwoColumns', () {
    test('按奇偶交替分栏', () {
      final result = splitIntoTwoColumns([0, 1, 2, 3, 4]);
      expect(result.left, [0, 2, 4]);
      expect(result.right, [1, 3]);
    });

    test('分页追加不会让已有条目换栏', () {
      final firstPage = List.generate(6, (i) => i);
      final secondPage = List.generate(12, (i) => i);

      final before = splitIntoTwoColumns(firstPage);
      final after = splitIntoTwoColumns(secondPage);

      // 首屏每一条在其所在栏中的相对顺序必须保持不变；
      // 追加只允许出现在两栏末尾。
      for (var i = 0; i < firstPage.length; i++) {
        final column = i.isEven ? before.left : before.right;
        final newColumn = i.isEven ? after.left : after.right;
        final oldIndex = column.indexOf(i);
        expect(newColumn.indexOf(i), oldIndex,
            reason: '条目 $i 在追加后位置漂移了');
      }
    });

    test('空列表与单条不影响分栏形状', () {
      expect(splitIntoTwoColumns<int>([]).left, isEmpty);
      expect(splitIntoTwoColumns<int>([]).right, isEmpty);
      final single = splitIntoTwoColumns([1]);
      expect(single.left, [1]);
      expect(single.right, isEmpty);
    });
  });
}
