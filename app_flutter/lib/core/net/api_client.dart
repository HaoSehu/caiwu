import 'package:dio/dio.dart';

import '../config/env.dart';
import 'api_exception.dart';

/// 后端统一 JSON 外层解析。
///
/// 所有接口返回 `{code, message, data, timestamp}`。这里只负责：
/// 1. Bearer Token 注入；
/// 2. 外层解包与错误码转 [ApiException]；
/// 3. 网络层错误统一转 [NetworkException]。
///
/// 401 的跳登录由上层 [Session] 监听 `onUnauthorized` 回调完成，避免网络层依赖路由。
class ApiClient {
  ApiClient({
    required this.tokenProvider,
    this.onUnauthorized,
    Dio? dio,
  }) : _dio = dio ??
            Dio(BaseOptions(
              baseUrl: Env.apiBase,
              connectTimeout: const Duration(seconds: 15),
              receiveTimeout: const Duration(seconds: 30),
              headers: {'Accept': 'application/json'},
            )) {
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        final token = tokenProvider();
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
    ));
  }

  final Dio _dio;
  final String? Function() tokenProvider;
  final void Function()? onUnauthorized;

  /// GET，返回 `data` 字段。
  Future<dynamic> get(String path, {Map<String, dynamic>? query}) {
    return _request(() => _dio.get(path, queryParameters: query));
  }

  /// POST，返回 `data` 字段。
  Future<dynamic> post(String path, {dynamic data}) {
    return _request(() => _dio.post(path, data: data));
  }

  /// PUT，返回 `data` 字段。
  Future<dynamic> put(String path, {dynamic data}) {
    return _request(() => _dio.put(path, data: data));
  }

  /// DELETE，返回 `data` 字段。
  Future<dynamic> delete(String path) {
    return _request(() => _dio.delete(path));
  }

  /// multipart 上传（工单图片等），返回 `data` 字段。
  Future<dynamic> upload(String path, FormData formData) {
    return _request(() => _dio.post(path, data: formData));
  }

  Future<dynamic> _request(Future<Response<dynamic>> Function() send) async {
    try {
      final response = await send();
      return _unwrap(response.data);
    } on DioException catch (error) {
      final body = error.response?.data;
      if (body is Map<String, dynamic>) {
        final exception = _toApiException(body, error.response?.statusCode);
        _notifyUnauthorized(exception);
        throw exception;
      }
      throw NetworkException(_networkMessage(error));
    }
  }

  dynamic _unwrap(dynamic body) {
    if (body is! Map<String, dynamic>) {
      throw NetworkException('服务响应格式异常');
    }
    final code = body['code'];
    if (code == 0) {
      return body['data'];
    }
    final exception = _toApiException(body, null);
    _notifyUnauthorized(exception);
    throw exception;
  }

  ApiException _toApiException(Map<String, dynamic> body, int? httpStatus) {
    final code = body['code'] is int ? body['code'] as int : -1;
    final message = (body['message'] as String?)?.trim();
    final data = body['data'];
    final errors = <String, List<String>>{};
    if (data is Map<String, dynamic>) {
      final rawErrors = data['errors'];
      if (rawErrors is Map<String, dynamic>) {
        rawErrors.forEach((key, value) {
          if (value is List) {
            errors[key] = value.map((item) => item.toString()).toList();
          } else if (value != null) {
            errors[key] = [value.toString()];
          }
        });
      }
    }
    return ApiException(
      code: code,
      message: (message == null || message.isEmpty) ? '请求失败，请稍后重试' : message,
      errors: errors,
      httpStatus: httpStatus,
    );
  }

  void _notifyUnauthorized(ApiException exception) {
    if (exception.isUnauthorized) {
      onUnauthorized?.call();
    }
  }

  String _networkMessage(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return '网络连接超时，请检查网络后重试';
      case DioExceptionType.connectionError:
        return '无法连接服务器，请检查网络';
      case DioExceptionType.cancel:
        return '请求已取消';
      default:
        return '网络异常，请稍后重试';
    }
  }
}
