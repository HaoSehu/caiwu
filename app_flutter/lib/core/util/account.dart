/// 账号标识判定，对齐后端 `App\Support\AccountIdentifier`：
/// - 手机号归一：去除非数字字符，去掉国家码前缀 `86`，再按 `^1[3-9]\d{9}$` 判定；
/// - 其余按邮箱处理（含 `@`）。
library;

/// 归一化手机号：只保留数字并去掉 `86` 前缀（与后端 normalizePhone 同口径）。
String normalizePhone(String raw) {
  var digits = raw.replaceAll(RegExp(r'[^0-9]'), '');
  if (digits.length == 13 && digits.startsWith('86')) {
    digits = digits.substring(2);
  }
  return digits;
}

/// 是否是合法手机号（后端规则：`^1[3-9]\d{9}$`）。
bool isPhoneAccount(String raw) {
  return RegExp(r'^1[3-9]\d{9}$').hasMatch(normalizePhone(raw));
}

/// 是否是合法邮箱（宽松校验，最终以后端为准）。
bool isEmailAccount(String raw) {
  return raw.contains('@') && raw.length <= 100;
}

/// 是否是后端可识别的账号（手机号或邮箱）。
bool isValidAccount(String raw) {
  final value = raw.trim();
  return isPhoneAccount(value) || isEmailAccount(value);
}
