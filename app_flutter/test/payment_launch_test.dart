import 'package:caiwu_app/core/util/payment_launch.dart';
import 'package:flutter_test/flutter_test.dart';

/// 支付拉起口径回归。
///
/// 背景（2026-11 线上问题）：微信支付链接曾被打包成
/// `weixin://dl/businessWebview/link/?url=…` 试图从外部浏览器唤起微信，
/// 微信对该来源返回「提示：invalid_source」。该路径不是微信公开协议，
/// 因此这里固定两条口径：https 收银台原样交给系统打开（由页面引导用户
/// 复制到微信内），已有的 `weixin://` scheme 原样透传。
void main() {
  group('normalizePaymentUrl', () {
    test('补协议并保留已有 scheme', () {
      expect(
        normalizePaymentUrl('//pay.example.com/a'),
        'https://pay.example.com/a',
      );
      expect(
        normalizePaymentUrl('pay.example.com/a'),
        'https://pay.example.com/a',
      );
      expect(
        normalizePaymentUrl('weixin://wxpay/bizpayurl?pr=x'),
        'weixin://wxpay/bizpayurl?pr=x',
      );
      expect(normalizePaymentUrl('  '), '');
    });
  });

  group('buildWechatLaunchUrl', () {
    test('https 收银台原样返回，不拼 businessWebview', () {
      const url = 'https://pay.example.com/pay/jspay/1/';
      final result = buildWechatLaunchUrl(url);

      expect(result, url);
      expect(result, isNot(contains('businessWebview')));
    });

    test('weixin:// scheme 原样透传', () {
      const url = 'weixin://wxpay/bizpayurl?pr=x';
      expect(buildWechatLaunchUrl(url), url);
    });
  });

  group('buildAlipayLaunchUri', () {
    test('当面付二维码走扫一扫', () {
      final uri = buildAlipayLaunchUri(
        Uri.parse('https://qr.alipay.com/abc'),
      );
      expect(uri.toString(), startsWith('alipays://platformapi/startapp?saId=10000007'));
    });

    test('普通收银台走支付宝内置浏览器', () {
      final uri = buildAlipayLaunchUri(
        Uri.parse('https://pay.example.com/cashier/1'),
      );
      expect(uri.toString(), startsWith('alipays://platformapi/startapp?appId=20000067'));
    });
  });

  group('detectPaymentChannel', () {
    test('按 payment_type / 渠道名识别微信与支付宝', () {
      expect(
        detectPaymentChannel(gateway: 'yi_pay', paymentType: 'wxpay'),
        PaymentChannel.wechat,
      );
      expect(
        detectPaymentChannel(gateway: 'yi_pay', channelName: '微信支付'),
        PaymentChannel.wechat,
      );
      expect(
        detectPaymentChannel(gateway: 'ali_pay', paymentType: 'alipay'),
        PaymentChannel.alipay,
      );
      expect(detectPaymentChannel(), PaymentChannel.other);
    });
  });
}
