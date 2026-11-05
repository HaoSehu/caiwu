/// 金额与时间格式化。
///
/// 口径对齐 `docs/DESIGN.md` §6 与 `shared/finance/money.ts`：
/// - 金额：千分位 + 两位小数；带符号时负号在 `¥` 之前（`-¥5.00`）。
/// - 时间：`YYYY-MM-DD HH:mm:ss`，空值占位 `--`。
library;

/// 金额（不含货币符号）：`1,234.56`，非法值回落 `0.00`。
String formatMoney(Object? value) {
  final amount = double.tryParse('${value ?? ''}') ?? 0;
  if (amount.isNaN || amount.isInfinite) return '0.00';
  final fixed = amount.abs().toStringAsFixed(2);
  final parts = fixed.split('.');
  final intPart = parts[0];
  final decimals = parts.length > 1 ? parts[1] : '00';
  final buffer = StringBuffer();
  for (var i = 0; i < intPart.length; i++) {
    if (i > 0 && (intPart.length - i) % 3 == 0) buffer.write(',');
    buffer.write(intPart[i]);
  }
  return '$buffer.$decimals';
}

/// 带货币符号的金额：`¥1,234.56`，负数 `-¥1,234.56`。
String formatMoneyText(Object? value) {
  final amount = double.tryParse('${value ?? ''}') ?? 0;
  final sign = amount < 0 ? '-' : '';
  return '$sign¥${formatMoney(amount.abs())}';
}

final _dateTimeRe = RegExp(
  r'^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?',
);

/// 日期时间：`YYYY-MM-DD HH:mm:ss`；解析失败返回原文，空值 `--`。
String formatDateTime(Object? value) {
  final raw = '${value ?? ''}'.trim();
  if (raw.isEmpty) return '--';
  final match = _dateTimeRe.firstMatch(raw);
  if (match == null) return raw;
  final second = match.group(6) ?? '00';
  return '${match.group(1)}-${match.group(2)}-${match.group(3)} '
      '${match.group(4)}:${match.group(5)}:$second';
}

/// 纯日期：`YYYY-MM-DD`；空值 `--`。
String formatDate(Object? value) {
  final raw = '${value ?? ''}'.trim();
  if (raw.isEmpty) return '--';
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(raw);
  if (match == null) return raw;
  return '${match.group(1)}-${match.group(2)}-${match.group(3)}';
}

/// 当前时间戳（毫秒），给监控接口用。
int nowMillis() => DateTime.now().millisecondsSinceEpoch;

/// 生成 `YYYY-MM-DD HH:mm:ss` 的当前时间（用于监控自定义区间兜底）。
String formatNow() => formatDateTime(DateTime.now());
