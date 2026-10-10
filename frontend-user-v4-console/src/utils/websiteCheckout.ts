const CHECKOUT_STORAGE_KEY = 'website_pending_checkout';
// 待建单 payload 有效期：超时后视为过期，避免旧会话 payload 被再次自动建单
const CHECKOUT_TTL_MS = 30 * 60 * 1000;

export interface PendingWebsiteCheckout {
  source?: string;
  idempotencyKey?: string;
  orderPayload?: Record<string, unknown>;
  savedAt?: number;
  [key: string]: unknown;
}

function toBase64Url(value: string) {
  const bytes = new TextEncoder().encode(value);
  let binary = '';
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });

  return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

function fromBase64Url(value: string) {
  const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
  const padded = normalized.padEnd(Math.ceil(normalized.length / 4) * 4, '=');
  const binary = window.atob(padded);
  const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
  return new TextDecoder().decode(bytes);
}

function canUseSessionStorage() {
  return typeof window !== 'undefined' && Boolean(window.sessionStorage);
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isScalar(value: unknown): value is string | number | boolean | null {
  return value === null || ['string', 'number', 'boolean'].includes(typeof value);
}

/**
 * `config` 只允许「键 → 标量」的一层映射：拒绝任意嵌套与超长键，
 * 避免外部载荷把深层结构塞进建单请求。
 */
function normalizeOrderConfig(raw: unknown): Record<string, string | number | boolean | null> | undefined {
  if (!isPlainObject(raw)) return undefined;
  const config: Record<string, string | number | boolean | null> = {};
  for (const [key, value] of Object.entries(raw)) {
    if (key.length === 0 || key.length > 64 || !isScalar(value)) continue;
    config[key] = value;
  }
  return Object.keys(config).length > 0 ? config : undefined;
}

/**
 * 校验并收敛「待建单载荷」。
 *
 * `checkout_payload` 来自 URL（base64），`website_pending_checkout` 来自 sessionStorage，
 * 两者都是外部可写输入，不能 `JSON.parse` 后直接当作建单参数使用：
 * 这里按白名单收敛字段并做类型/范围校验，任何不合规载荷一律返回 null（交由页面走空态）。
 *
 * 注意：券归属与最终价格仍以服务端为准，本函数只保证「不会把垃圾字段送进建单接口」。
 */
export function normalizePendingWebsiteCheckout(raw: unknown): PendingWebsiteCheckout | null {
  if (!isPlainObject(raw)) return null;

  const orderRaw = raw.orderPayload;
  if (!isPlainObject(orderRaw)) return null;

  const productId = Number(orderRaw.product_id);
  if (!Number.isInteger(productId) || productId <= 0) return null;

  const billingCycle = typeof orderRaw.billing_cycle === 'string' ? orderRaw.billing_cycle.trim() : '';
  if (!billingCycle || billingCycle.length > 32) return null;

  const quantity = Number(orderRaw.quantity);
  if (!Number.isInteger(quantity) || quantity <= 0 || quantity > 1000) return null;

  const orderPayload: Record<string, unknown> = { product_id: productId, billing_cycle: billingCycle, quantity };

  const config = normalizeOrderConfig(orderRaw.config);
  if (config) orderPayload.config = config;

  if (typeof orderRaw.quote_token === 'string' && orderRaw.quote_token.trim()) {
    orderPayload.quote_token = orderRaw.quote_token.trim().slice(0, 256);
  }

  const couponId = Number(orderRaw.user_coupon_id);
  if (Number.isInteger(couponId) && couponId > 0) orderPayload.user_coupon_id = couponId;

  const savedAt = Number(raw.savedAt);

  return {
    source: typeof raw.source === 'string' ? raw.source.slice(0, 64) : undefined,
    idempotencyKey: typeof raw.idempotencyKey === 'string' ? raw.idempotencyKey.slice(0, 128) : undefined,
    savedAt: Number.isFinite(savedAt) ? savedAt : undefined,
    orderPayload,
  };
}

export function buildIdempotencyKey(prefix = 'web-checkout') {
  const random = Math.random().toString(36).slice(2, 10);
  return `${prefix}-${Date.now()}-${random}`;
}

export function encodePendingWebsiteCheckout(payload: PendingWebsiteCheckout) {
  return toBase64Url(JSON.stringify(payload || {}));
}

export function decodePendingWebsiteCheckout(value: string): PendingWebsiteCheckout | null {
  if (!value) {
    return null;
  }

  try {
    return normalizePendingWebsiteCheckout(JSON.parse(fromBase64Url(value)));
  } catch {
    return null;
  }
}

export function savePendingWebsiteCheckout(payload: PendingWebsiteCheckout) {
  if (!canUseSessionStorage()) {
    return;
  }
  const withTimestamp = { savedAt: Date.now(), ...(payload || {}) };
  window.sessionStorage.setItem(CHECKOUT_STORAGE_KEY, JSON.stringify(withTimestamp));
}

export function getPendingWebsiteCheckout(): PendingWebsiteCheckout | null {
  if (!canUseSessionStorage()) {
    return null;
  }

  const raw = window.sessionStorage.getItem(CHECKOUT_STORAGE_KEY);
  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as PendingWebsiteCheckout;
    if (typeof parsed.savedAt === 'number' && Date.now() - parsed.savedAt > CHECKOUT_TTL_MS) {
      window.sessionStorage.removeItem(CHECKOUT_STORAGE_KEY);
      return null;
    }
    return normalizePendingWebsiteCheckout(parsed);
  } catch {
    return null;
  }
}

export function clearPendingWebsiteCheckout() {
  if (canUseSessionStorage()) {
    window.sessionStorage.removeItem(CHECKOUT_STORAGE_KEY);
  }
}
