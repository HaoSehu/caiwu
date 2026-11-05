/// 手机端支付拉起：把网关下发的支付链接转换为支付宝 / 微信 App 的
/// scheme 并直接唤起，失败时才回退系统浏览器。
///
/// 后端 `qr_code` 在不同网关下可能是：
/// - `https://qr.alipay.com/xxx`（支付宝当面付二维码内容）
/// - `https://<收银台域名>/pay/...`（聚合网关收银台页面）
/// - `alipays://...` / `weixin://...` / `wxp://...`（已可直接唤起的 scheme）
///
/// 口径与 `frontend-user-v4-console/src/domains/finance/useRecharge.ts` 的
/// `resolveAlipayLaunchUrl` 一致：支付宝统一走 `appId=20000067&url=`，
/// 当面付二维码走扫一扫 `saId=10000007&qrcode=`。
library;

import 'package:flutter/foundation.dart';
import 'package:url_launcher/url_launcher.dart';

/// 支付渠道：决定最终唤起哪个手机 App。
enum PaymentChannel {
  /// 支付宝。
  alipay,

  /// 微信支付。
  wechat,

  /// 其它渠道（银联等）：只打开原始链接。
  other,
}

/// 支付宝内置浏览器 scheme。
const String _alipayScheme = 'alipays://';

/// 微信内置浏览器 scheme。
const String _wechatScheme = 'weixin://';

/// 根据网关编码、支付类型与渠道名推断支付渠道。
///
/// 任一入参命中即可，调用方只需传手上有的字段：
/// - 账单支付：`gateway`（如 `alipay` / `yi_pay`）+ `payment_type`（`alipay` / `wxpay`）
/// - 充值：`option_key`（如 `yi_pay:wxpay`）+ `channel_name`（「微信支付」）
PaymentChannel detectPaymentChannel({
  String? gateway,
  String? paymentType,
  String? channelName,
}) {
  final text = [
    gateway,
    paymentType,
    channelName,
  ].whereType<String>().join(' ').toLowerCase();
  if (text.isEmpty) return PaymentChannel.other;
  if (text.contains('alipay') ||
      text.contains('ali_pay') ||
      text.contains('支付宝')) {
    return PaymentChannel.alipay;
  }
  if (text.contains('wechat') ||
      text.contains('weixin') ||
      text.contains('wxpay') ||
      text.contains('微信')) {
    return PaymentChannel.wechat;
  }
  return PaymentChannel.other;
}

/// 支付链接规范化：去空白、补协议。
///
/// 支持三种网关常见写法：`https://...`、`//host/path`、`qr.alipay.com/xxx`。
/// 返回值无法识别为链接时为空串。
String normalizePaymentUrl(String rawUrl) {
  final url = rawUrl.trim();
  if (url.isEmpty) return '';
  if (url.startsWith('//')) return 'https:$url';
  if (RegExp(r'^[a-zA-Z][a-zA-Z0-9+.\-]*:').hasMatch(url)) return url;
  if (RegExp(r'^[\w.\-]+\.[a-zA-Z]{2,}(/|$)').hasMatch(url)) {
    return 'https://$url';
  }
  return url;
}

/// 是否为 App 自定义 scheme（非 http/https）。
bool isAppScheme(String url) {
  final lower = url.toLowerCase();
  if (lower.startsWith('http://') || lower.startsWith('https://')) {
    return false;
  }
  return RegExp(r'^[a-z][a-z0-9+.\-]*:').hasMatch(lower);
}

/// 构造支付宝唤起链接。
///
/// 当面付二维码（`qr.alipay.com`）走「扫一扫识别二维码」，其余收银台链接
/// 走支付宝内置浏览器打开，与网页端保持同一口径。
Uri buildAlipayLaunchUri(Uri payUrl) {
  final raw = payUrl.toString();
  if (payUrl.host.toLowerCase() == 'qr.alipay.com') {
    return Uri.parse(
      '${_alipayScheme}platformapi/startapp'
      '?saId=10000007&qrcode=${Uri.encodeComponent(raw)}',
    );
  }
  return Uri.parse(
    '${_alipayScheme}platformapi/startapp'
    '?appId=20000067&url=${Uri.encodeComponent(raw)}',
  );
}

/// 构造微信唤起链接：用微信内置浏览器打开收银台页面，
/// 页面内的 `weixin://wap/pay` 跳转才会被微信识别为支付。
Uri buildWechatLaunchUri(Uri payUrl) {
  return Uri.parse(
    '${_wechatScheme}dl/businessWebview/link/'
    '?url=${Uri.encodeComponent(payUrl.toString())}',
  );
}

/// 从 `alipays://platformapi/startapp?...url=<encoded>` 还原原始 https 链接，
/// 作为支付宝未安装时的浏览器兜底；无法还原时返回 null。
Uri? httpsFallbackFromAlipayScheme(Uri uri) {
  final raw = uri.queryParameters['url'];
  if (raw == null || raw.isEmpty) return null;
  final parsed = Uri.tryParse(raw);
  if (parsed == null) return null;
  final scheme = parsed.scheme.toLowerCase();
  if (scheme != 'http' && scheme != 'https') return null;
  return parsed;
}

/// 拉起支付链接。
///
/// 顺序：按渠道唤起 App → 唤起失败回退原始 https 链接（系统浏览器）。
/// 返回是否至少有一次拉起成功；全部失败时调用方给出「请在浏览器中完成支付」提示。
Future<bool> launchPaymentUrl(
  String rawUrl, {
  required PaymentChannel channel,
}) async {
  final normalized = normalizePaymentUrl(rawUrl);
  if (normalized.isEmpty) return false;
  final original = Uri.tryParse(normalized);
  if (original == null) return false;

  // Web 端没有 App scheme：保持直接打开链接的既有行为。
  if (kIsWeb) return _tryLaunch(original);

  if (isAppScheme(normalized)) {
    final scheme = original.scheme.toLowerCase();
    if (scheme != 'alipays' && scheme != 'alipay') return _tryLaunch(original);
    if (await _tryLaunch(original)) return true;
    final fallback = httpsFallbackFromAlipayScheme(original);
    return fallback != null && await _tryLaunch(fallback);
  }

  switch (channel) {
    case PaymentChannel.alipay:
      if (await _tryLaunch(buildAlipayLaunchUri(original))) return true;
      return _tryLaunch(original);
    case PaymentChannel.wechat:
      if (await _tryLaunch(buildWechatLaunchUri(original))) return true;
      return _tryLaunch(original);
    case PaymentChannel.other:
      return _tryLaunch(original);
  }
}

/// 单次 `launchUrl` 包装：异常视为失败，交由上层回退。
Future<bool> _tryLaunch(Uri uri) async {
  try {
    return await launchUrl(uri, mode: LaunchMode.externalApplication);
  } on Exception {
    return false;
  }
}
