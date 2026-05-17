export const IDENTITY_CARD_PATTERN = /^[1-9]\d{5}(18|19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/

export function resolveVerificationQrUrl(payload) {
  if (typeof payload === 'string') {
    return payload.trim()
  }

  if (!payload || typeof payload !== 'object') {
    return ''
  }

  const proxyUrl = typeof payload.proxy_url === 'string' ? payload.proxy_url.trim() : ''
  if (proxyUrl) {
    return proxyUrl
  }

  return typeof payload.url === 'string' ? payload.url.trim() : ''
}
