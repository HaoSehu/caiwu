/**
 * 官网下单 → 控制台建单 的跨端载荷契约。
 *
 * 官网（frontend-user-v3-www）负责写入 sessionStorage 并生成 base64url 的 checkout_payload，
 * 控制台（frontend-user-v4-console）负责解码、校验并提交建单。两端此前各写一份实现，
 * 字段名（savedAt vs createdAt）、TTL、载荷校验强度都不一致 —— 这里收敛成唯一真源。
 *
 * 安全边界：URL 参数与 sessionStorage 都是外部可写输入，decode 必须走白名单收敛，
 * 任何不合规载荷返回 null，由页面走空态，绝不把垃圾字段送进建单接口。
 */

export const CHECKOUT_STORAGE_KEY = 'website_pending_checkout';

/** 待建单载荷有效期：超时后视为过期，避免旧会话 payload 被再次自动建单。 */
export const CHECKOUT_TTL_MS = 30 * 60 * 1000;

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

  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

function fromBase64Url(value: string) {
  const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
  const padded = normalized.padEnd(Math.ceil(normalized.length / 4) * 4, '=');
  const binary = atob(padded);
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

  // 数量上限必须与后端一致：StoreRequest 是 quantity in 1..1，CheckoutService 另有
  // $quantity !== 1 的硬断言。放到 1000 只会让非法载荷通过前端校验、最终在建单接口
  // 报一个看不懂的 422。
  const quantity = Number(orderRaw.quantity);
  if (!Number.isInteger(quantity) || quantity !== 1) return null;

  const orderPayload: Record<string, unknown> = { product_id: productId, billing_cycle: billingCycle, quantity };

  const config = normalizeOrderConfig(orderRaw.config);
  if (config) orderPayload.config = config;

  // 报价凭证上限对齐后端 StoreRequest 的 max:120。真实凭证是 quote_ + 48 位随机串（54 字符），
  // 但外部可构造超长值，截到 256 会绕过前端收敛、把注意力丢到后端 422 上。
  if (typeof orderRaw.quote_token === 'string' && orderRaw.quote_token.trim()) {
    orderPayload.quote_token = orderRaw.quote_token.trim().slice(0, 120);
  }

  const couponId = Number(orderRaw.user_coupon_id);
  if (Number.isInteger(couponId) && couponId > 0) orderPayload.user_coupon_id = couponId;

  // TTL 基准必须落在「官网点击购买的时刻」。旧版官网只写 createdAt、不写 savedAt，
  // 这里回退读 createdAt，避免整条链路因为字段名不一致而永不判过期。
  const savedAt = Number(raw.savedAt ?? raw.createdAt);

  return {
    source: typeof raw.source === 'string' ? raw.source.slice(0, 64) : undefined,
    idempotencyKey: typeof raw.idempotencyKey === 'string' ? raw.idempotencyKey.slice(0, 128) : undefined,
    savedAt: Number.isFinite(savedAt) ? savedAt : undefined,
    orderPayload,
  };
}

export function buildIdempotencyKey(prefix = 'web-checkout') {
  const randomPart = globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  return `${prefix}-${randomPart}`;
}

export function encodePendingWebsiteCheckout(payload: PendingWebsiteCheckout | Record<string, unknown>) {
  try {
    return toBase64Url(JSON.stringify(payload || {}));
  } catch {
    return '';
  }
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
  if (!canUseSessionStorage() || !payload || typeof payload !== 'object') {
    return;
  }
  // savedAt 只在缺失时补打：控制台落地时会再调一次本函数，
  // 若无条件覆盖，TTL 基准会从「官网点击购买」漂移到「控制台落地」，窗口被无限续期。
  const hasTimestamp = Number.isFinite(Number(payload.savedAt));
  const withTimestamp = hasTimestamp ? { ...payload } : { ...payload, savedAt: Date.now() };
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
    // 与 savePendingWebsiteCheckout 的判定口径保持一致：那边用 Number.isFinite(Number(payload.savedAt))
    // 认字符串形式的数字时间戳，这边若只认 typeof === 'number'，外部（旧版官网、手改的 sessionStorage）
    // 写进来的 "1700000000000" 就会跳过 TTL 判定 —— 存档能写进去、却永不过期。
    const savedAt = Number(parsed.savedAt);
    if (Number.isFinite(savedAt) && Date.now() - savedAt > CHECKOUT_TTL_MS) {
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
