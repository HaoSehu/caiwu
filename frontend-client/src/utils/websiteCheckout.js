const WEBSITE_CHECKOUT_KEY = 'website_pending_checkout'

export function buildIdempotencyKey(prefix = 'web-checkout') {
  const randomPart = crypto.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`
  return `${prefix}-${randomPart}`
}

export function savePendingWebsiteCheckout(payload) {
  if (!payload || typeof payload !== 'object') return
  sessionStorage.setItem(WEBSITE_CHECKOUT_KEY, JSON.stringify(payload))
}

export function getPendingWebsiteCheckout() {
  const raw = sessionStorage.getItem(WEBSITE_CHECKOUT_KEY)
  if (!raw) return null

  try {
    const parsed = JSON.parse(raw)
    return parsed && typeof parsed === 'object' ? parsed : null
  } catch {
    return null
  }
}

export function clearPendingWebsiteCheckout() {
  sessionStorage.removeItem(WEBSITE_CHECKOUT_KEY)
}
