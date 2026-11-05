import 'package:caiwu_app/core/util/format.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('formatMoney', () {
    test('千分位与两位小数', () {
      expect(formatMoney(0), '0.00');
      expect(formatMoney('1234.5'), '1,234.50');
      expect(formatMoney(1234567.891), '1,234,567.89');
      expect(formatMoney('123456789'), '123,456,789.00');
    });

    test('非法值回落 0.00', () {
      expect(formatMoney(null), '0.00');
      expect(formatMoney('abc'), '0.00');
    });

    test('负数取绝对值（符号由 formatMoneyText 负责）', () {
      expect(formatMoney(-12.3), '12.30');
    });
  });

  group('formatMoneyText', () {
    test('正数与负数符号顺序（DESIGN.md §6：负号在 ¥ 之前）', () {
      expect(formatMoneyText('1234.56'), '¥1,234.56');
      expect(formatMoneyText(-5), '-¥5.00');
      expect(formatMoneyText('0.00'), '¥0.00');
    });

    test('非法值回落 ¥0.00', () {
      expect(formatMoneyText(null), '¥0.00');
      expect(formatMoneyText('x'), '¥0.00');
    });
  });

  group('formatDateTime', () {
    test('统一 YYYY-MM-DD HH:mm:ss', () {
      expect(formatDateTime('2026-10-24 02:12:43'), '2026-10-24 02:12:43');
    });

    test('缺秒补 00、T 分隔兼容', () {
      expect(formatDateTime('2026-10-24 02:12'), '2026-10-24 02:12:00');
      expect(formatDateTime('2026-10-24T02:12:43'), '2026-10-24 02:12:43');
    });

    test('空值与非法输入', () {
      expect(formatDateTime(null), '--');
      expect(formatDateTime(''), '--');
      expect(formatDateTime('不是时间'), '不是时间');
    });
  });

  group('formatDate', () {
    test('只保留日期', () {
      expect(formatDate('2026-10-24 02:12:43'), '2026-10-24');
      expect(formatDate('2026-10-24'), '2026-10-24');
      expect(formatDate(null), '--');
    });
  });
}
