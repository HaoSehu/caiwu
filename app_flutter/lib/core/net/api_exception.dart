/// 后端统一错误外层对应的异常。
///
/// 后端所有 JSON API 返回 `{code, message, data, timestamp}`：
/// - `code == 0` 成功
/// - `code == 40100` 未登录或登录过期
/// - `code == 42200` 参数/业务校验失败，`data.errors` 是字段错误
/// - 其余按业务码处理（见 docs/参考资料/接口/API格式规范.md）
class ApiException implements Exception {
  ApiException({
    required this.code,
    required this.message,
    this.errors = const {},
    this.httpStatus,
  });

  final int code;
  final String message;
  final Map<String, List<String>> errors;
  final int? httpStatus;

  bool get isUnauthorized => code == 40100;
  bool get isValidation => code == 42200;

  /// 字段错误的首条文案，用于表单行内提示。
  String? firstErrorOf(String field) {
    final list = errors[field];
    if (list == null || list.isEmpty) return null;
    return list.first;
  }

  @override
  String toString() => 'ApiException($code): $message';
}

/// 网络层异常（超时、断网、非 JSON 响应等）。
class NetworkException implements Exception {
  NetworkException(this.message);

  final String message;

  @override
  String toString() => 'NetworkException: $message';
}
