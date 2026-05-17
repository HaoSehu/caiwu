function normalizeAlipayUrl(rawUrl) {
  const value = String(rawUrl || '').trim()

  if (!value) {
    return ''
  }

  if (/^alipays?:\/\//i.test(value)) {
    return value
  }

  if (/^https?:\/\//i.test(value)) {
    return value
  }

  if (/^\/\//.test(value)) {
    return `https:${value}`
  }

  if (/^qr\.alipay\.com\//i.test(value) || /^mclient\.alipay\.com\//i.test(value)) {
    return `https://${value}`
  }

  return ''
}

export function buildAlipayLaunchUrl(rawUrl) {
  const url = normalizeAlipayUrl(rawUrl)

  if (!url) {
    return ''
  }

  if (/^alipays?:\/\//i.test(url)) {
    return url
  }

  return `alipays://platformapi/startapp?appId=20000067&url=${encodeURIComponent(url)}`
}
