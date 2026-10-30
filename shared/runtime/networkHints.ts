const hintedOrigins = new Set<string>()

function getConnection() {
  if (typeof navigator === 'undefined') {
    return null
  }

  const runtimeNavigator = navigator as Navigator & {
    connection?: unknown
    mozConnection?: unknown
    webkitConnection?: unknown
  }

  return (runtimeNavigator.connection || runtimeNavigator.mozConnection || runtimeNavigator.webkitConnection || null) as {
    effectiveType?: string
    saveData?: boolean
    downlink?: number
    rtt?: number
  } | null
}

export function toOrigin(candidate: string) {
  if (!candidate || typeof window === 'undefined') {
    return ''
  }

  try {
    const url = new URL(String(candidate).trim(), window.location.origin)
    if (!['http:', 'https:'].includes(url.protocol)) {
      return ''
    }

    return url.origin
  } catch {
    return ''
  }
}

function toDnsPrefetchHref(origin: string) {
  try {
    return `//${new URL(origin).host}`
  } catch {
    return ''
  }
}

function hasHeadLink(rel: string, href: string) {
  if (typeof document === 'undefined') {
    return false
  }

  return Array.from(document.head.querySelectorAll(`link[rel="${rel}"]`))
    .some((node) => node.getAttribute('href') === href)
}

function appendHeadLink(rel: string, href: string, crossOrigin = false) {
  if (!href || typeof document === 'undefined' || hasHeadLink(rel, href)) {
    return
  }

  const link = document.createElement('link')
  link.rel = rel
  link.href = href

  if (crossOrigin) {
    link.crossOrigin = 'anonymous'
  }

  document.head.appendChild(link)
}

export function getNetworkProfile() {
  const connection = getConnection()
  const effectiveType = String(connection?.effectiveType || '').toLowerCase()
  const saveData = Boolean(connection?.saveData)
  const isWeakConnection = saveData || ['slow-2g', '2g', '3g'].includes(effectiveType)

  return {
    effectiveType,
    saveData,
    downlink: Number(connection?.downlink || 0),
    rtt: Number(connection?.rtt || 0),
    isWeakConnection,
  }
}

export function waitFor(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

export function primeConnectionHints({ urls = [], origins = [] }: { urls?: Array<string | undefined | null>, origins?: Array<string | undefined | null> } = {}) {
  if (typeof document === 'undefined' || typeof window === 'undefined') {
    return
  }

  const candidates = new Set<string>()

  origins.forEach((origin) => {
    const normalized = toOrigin(String(origin || ''))
    if (normalized) {
      candidates.add(normalized)
    }
  })

  urls.forEach((url) => {
    const normalized = toOrigin(String(url || ''))
    if (normalized) {
      candidates.add(normalized)
    }
  })

  candidates.forEach((origin) => {
    if (!origin || origin === window.location.origin || hintedOrigins.has(origin)) {
      return
    }

    hintedOrigins.add(origin)
    appendHeadLink('dns-prefetch', toDnsPrefetchHref(origin))
    appendHeadLink('preconnect', origin, true)
  })
}

/**
 * 初始化运行时连接预热。
 *
 * 这里只预热调用方显式传入的地址：第三方域名（验证码、支付二维码等）由各端在
 * 自己的入口按需传入 urls，shared 不再内置任何业务域名，避免单个端要用的域名
 * 变成所有端都会预连接的公共开销。
 */
export function initRuntimeConnectionHints(options: { apiBaseUrl?: string, urls?: Array<string | undefined | null> } = {}) {
  primeConnectionHints({
    urls: [
      options.apiBaseUrl,
      ...(Array.isArray(options.urls) ? options.urls : []),
    ],
  })
}
