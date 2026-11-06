/**
 * 手机端支付拉起：把后端下发的支付链接转换成可直接打开的支付入口。
 *
 * 后端 `qr_code` 在不同网关下可能是：
 * - `https://qr.alipay.com/xxx`（支付宝当面付二维码内容）
 * - `https://<收银台域名>/pay/...`（聚合网关收银台页面）
 * - `alipays://...` / `weixin://...` / `wxp://...`（已可直接唤起的 scheme）
 *
 * 支付宝能靠 scheme 直接唤起 App；微信没有可用的公开外部唤起协议，
 * 只能原样打开 https 收银台（微信内）或引导用户复制链接到微信内打开。
 * 口径与 `app_flutter/lib/core/util/payment_launch.dart` 完全一致，
 * 两端任何一侧调整都要同步另一侧，否则 App 与网页的支付体验会分叉。
 */

/** 支付渠道：决定最终唤起哪个手机 App。 */
export type PaymentChannel = 'alipay' | 'wechat' | 'other';

/** 当前页面所在的内置浏览器：微信 / 支付宝的内置 webview 会拦截彼此的 scheme。 */
export type PaymentBrowserEnv = 'wechat' | 'alipay' | 'browser';

/** 唤起方式：scheme 唤起 App；navigate 直接跳转（内置浏览器或普通网页支付）。 */
export type PaymentLaunchKind = 'scheme' | 'navigate' | 'blocked';

export interface PaymentLaunchPlan {
  kind: PaymentLaunchKind;
  /** 实际要打开的目标（scheme 或 https）。 */
  url: string;
  /** 唤起失败时的兜底 / 复制用 https 链接，无法还原时为空串。 */
  fallbackUrl: string;
  /** `kind === 'blocked'` 时给用户看的原因。 */
  message: string;
}

/** 支付方式中可用于判断渠道的字段（充值的 option 与账单的 pay_method 都满足）。 */
export interface PaymentChannelSource {
  key?: unknown;
  payment_type?: unknown;
  channel_name?: unknown;
  label?: unknown;
}

const SCHEME_PATTERN = /^[a-z][a-z0-9+.-]*:/i;
const DOMAIN_PATTERN = /^[\w.-]+\.[a-z]{2,}(?:\/|$)/i;

/** 支付链接规范化：去空白、补协议，兼容 `https://…`、`//host/path`、`qr.alipay.com/xxx`。 */
export function normalizePaymentUrl(rawUrl: unknown): string {
  const url = String(rawUrl ?? '').trim();
  if (!url) return '';
  if (url.startsWith('//')) return `https:${url}`;
  if (SCHEME_PATTERN.test(url)) return url;
  if (DOMAIN_PATTERN.test(url)) return `https://${url}`;
  return url;
}

function isHttpUrl(url: string): boolean {
  return /^https?:\/\//i.test(url);
}

/** `qr.alipay.com` 的二维码内容没有可直接打开的收银台页面，只能交给支付宝「扫一扫」识别。 */
function isAlipayQrHost(url: string): boolean {
  try {
    return new URL(url).host.toLowerCase() === 'qr.alipay.com';
  } catch {
    return false;
  }
}

/** 根据网关编码、支付类型与渠道名推断支付渠道；任一字段命中即可。 */
export function detectPaymentChannel(source?: PaymentChannelSource | null): PaymentChannel {
  const text = [source?.key, source?.payment_type, source?.channel_name, source?.label]
    .map((value) => String(value ?? ''))
    .join(' ')
    .toLowerCase();
  if (!text.trim()) return 'other';
  if (text.includes('alipay') || text.includes('ali_pay') || text.includes('支付宝')) return 'alipay';
  if (text.includes('wechat') || text.includes('weixin') || text.includes('wxpay') || text.includes('微信')) {
    return 'wechat';
  }
  return 'other';
}

/** 当前页面是否跑在微信 / 支付宝内置浏览器里（两者的 scheme 互不开放）。 */
export function detectPaymentBrowserEnv(userAgent?: string): PaymentBrowserEnv {
  const ua = userAgent ?? (typeof navigator === 'undefined' ? '' : navigator.userAgent);
  if (/micromessenger/i.test(ua)) return 'wechat';
  if (/alipayclient/i.test(ua)) return 'alipay';
  return 'browser';
}

/** 构造支付宝唤起链接：当面付二维码走扫一扫，其余收银台链接走支付宝内置浏览器打开。 */
export function buildAlipayLaunchUrl(rawUrl: unknown): string {
  const url = normalizePaymentUrl(rawUrl);
  if (!url) return '';
  if (/^alipays?:\/\//i.test(url)) return url;
  if (!isHttpUrl(url)) return '';
  if (isAlipayQrHost(url)) {
    return `alipays://platformapi/startapp?saId=10000007&qrcode=${encodeURIComponent(url)}`;
  }
  return `alipays://platformapi/startapp?appId=20000067&url=${encodeURIComponent(url)}`;
}

/**
 * 还原 https 兜底链接：scheme 链接里的 `url=` / `qrcode=` 参数就是原始网页地址，
 * 复制按钮与「浏览器中继续支付」都靠它，还原不出时返回空串。
 */
export function resolveHttpsPayUrl(rawUrl: unknown): string {
  const url = normalizePaymentUrl(rawUrl);
  if (!url) return '';
  if (isHttpUrl(url)) return url;

  const match = /[?&](?:url|qrcode)=([^&]+)/i.exec(url);
  if (!match) return '';
  let decoded = '';
  try {
    decoded = decodeURIComponent(match[1]);
  } catch {
    return '';
  }
  return isHttpUrl(decoded) ? decoded : '';
}

/**
 * 决定这次点击该怎么打开支付：
 *
 * - 普通手机浏览器：支付宝按渠道唤起支付宝 App；微信支付只有两种可用形态——
 *   已是 `weixin://` scheme 的原样拉起，https 收银台则**不能**在微信外完成支付
 *   （公众号 JSAPI 收银台会 302 到微信 OAuth，微信外打开会被拒绝），
 *   因此返回 blocked 并引导用户复制链接到微信内打开；
 * - 支付宝内置浏览器：再唤起支付宝无效，直接打开收银台链接（就在支付宝里完成支付）；
 * - 微信内置浏览器：微信不开放 `alipays://`，只能引导到系统浏览器；
 *   微信支付本身已在微信内，直接打开收银台链接。
 */
export function resolvePaymentLaunchPlan(
  rawUrl: unknown,
  channel: PaymentChannel,
  env: PaymentBrowserEnv = detectPaymentBrowserEnv(),
): PaymentLaunchPlan {
  const normalized = normalizePaymentUrl(rawUrl);
  const fallbackUrl = resolveHttpsPayUrl(normalized);
  const blocked = (message: string): PaymentLaunchPlan => ({ kind: 'blocked', url: '', fallbackUrl, message });

  if (!normalized) return blocked('支付链接无效，请返回后重新发起支付');

  if (env === 'wechat' && channel === 'alipay') {
    return blocked('微信内置浏览器不支持直接打开支付宝，请点右上角「在浏览器中打开」后重试');
  }
  if (env === 'alipay' && channel === 'wechat') {
    return blocked('支付宝内置浏览器不支持直接打开微信，请在手机浏览器中打开本页后重试');
  }

  if (channel === 'alipay' && env !== 'alipay') {
    const scheme = buildAlipayLaunchUrl(normalized);
    if (scheme) return { kind: 'scheme', url: scheme, fallbackUrl: fallbackUrl || normalized, message: '' };
  }

  if (channel === 'wechat') {
    // 已是微信 scheme（如 weixin://wxpay/bizpayurl…）才可直接拉起。
    if (!isHttpUrl(normalized)) {
      return { kind: 'navigate', url: normalized, fallbackUrl, message: '' };
    }

    // 微信内打开的 https 收银台就是公众号 JSAPI 支付页，直接跳转即可完成支付。
    if (env === 'wechat') {
      return { kind: 'navigate', url: normalized, fallbackUrl: normalized, message: '' };
    }

    // 微信外打开 https 收银台会 302 到微信 OAuth 并被拒绝（invalid_source 类提示），
    // 不能跳转；把链接交给用户复制到微信内打开。
    return blocked('微信支付需要在微信内完成：请点下方「复制支付链接」，粘贴到微信（可发给文件传输助手）后点开支付');
  }

  if (fallbackUrl) return { kind: 'navigate', url: fallbackUrl, fallbackUrl, message: '' };
  if (SCHEME_PATTERN.test(normalized)) {
    return { kind: 'navigate', url: normalized, fallbackUrl: '', message: '' };
  }
  return blocked('无法识别支付链接，请复制后在对应的支付 App 中打开');
}

/**
 * 执行唤起。返回是否已发起（被拦截或链接为空时为 false）。
 *
 * scheme 先走隐藏 iframe：不打断当前页面的轮询；唤起成功后页面进入后台，
 * 定时器随之冻结。若 1.2 秒后页面仍在前台，说明 iframe 被拦，再用一次顶层跳转兜底
 * —— 代价只是未安装 App 时浏览器自己的「无法打开」提示。
 */
export function openPaymentLaunch(plan: PaymentLaunchPlan, win: Window = window): boolean {
  if (plan.kind === 'blocked' || !plan.url) return false;

  if (plan.kind === 'navigate') {
    win.location.href = plan.url;
    return true;
  }

  const doc = win.document;
  const iframe = doc.createElement('iframe');
  iframe.style.display = 'none';
  iframe.setAttribute('aria-hidden', 'true');
  iframe.src = plan.url;
  doc.body.appendChild(iframe);

  win.setTimeout(() => {
    iframe.remove();
    if (doc.visibilityState === 'hidden') return;
    win.location.href = plan.url;
  }, 1200);

  return true;
}
