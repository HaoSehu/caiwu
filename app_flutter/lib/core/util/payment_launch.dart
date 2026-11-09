/// 手机端支付拉起：把网关下发的支付链接转换为可直接打开的支付入口。
///
/// 后端 `qr_code` 在不同网关下可能是：
/// - `https://qr.alipay.com/xxx`（支付宝当面付二维码内容）
/// - `https://<收银台域名>/pay/...`（聚合网关收银台页面）
/// - `alipays://...` / `weixin://...` / `wxp://...`（已可直接唤起的 scheme）
///
/// 支付宝能靠 scheme 直接唤起 App；微信没有可用的公开外部唤起协议，
/// 只能原样打开 https 收银台（微信内）或引导用户复制链接到微信内打开。
/// 口径与 `frontend-user-v4-console/src/domains/finance/paymentLaunch.ts`
/// 保持一致，两端任一侧调整都要同步另一侧。
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

/// 把支付链接规范化为最终要打开的目标。
///
/// 微信没有可用的公开外部唤起协议（`weixin://dl/businessWebview/link/` 会触发
/// 微信「提示：invalid_source」），因此 https 收银台原样返回，由调用方引导用户
/// 复制到微信内打开；已是 `weixin://` 等 scheme 的同样原样透传。
String buildWechatLaunchUrl(String rawUrl) {
  return normalizePaymentUrl(rawUrl);
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

/// 微信渠道链接能否由 App 直接拉起。
///
/// 只有 `weixin://` 等原生 scheme 才可能在微信外被系统处理；https 收银台是
/// 公众号 JSAPI 支付页，在微信外打开会被 302 到微信 OAuth 并拒绝（invalid_source），
/// 因此这类链接必须引导用户复制到微信内打开，不能「打开即视为成功」（M-28③）。
/// 口径与 `frontend-user-v4-console/src/domains/finance/paymentLaunch.ts` 一致。
bool canLaunchWechatUrl(String rawUrl) {
  final normalized = normalizePaymentUrl(rawUrl).toLowerCase();
  if (normalized.isEmpty) return false;
  return !normalized.startsWith('http://') && !normalized.startsWith('https://');
}

/// 拉起支付链接。
///
/// 顺序：按渠道打开支付入口 → 失败回退原始链接。
/// 返回是否至少有一次拉起成功；全部失败时调用方给出「请在浏览器中完成支付」提示。
///
/// 微信：没有可用的外部唤起协议，https 收银台原样交给系统浏览器/微信打开；
/// 调用方据此提示「复制链接到微信内打开」。
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
      return _tryLaunch(Uri.parse(buildWechatLaunchUrl(normalized)));
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
