function normalizeConsoleOrigin() {
  const configuredOrigin = String(import.meta.env.VITE_CONSOLE_SITE_URL || '').trim().replace(/\/+$/, '')
  if (configuredOrigin) {
    return configuredOrigin
  }

  if (typeof window === 'undefined') {
    return ''
  }

  const { protocol, hostname, port } = window.location
  if (!hostname.startsWith('www.')) {
    return ''
  }

  const consoleHostname = `console.${hostname.slice(4)}`
  return `${protocol}//${consoleHostname}${port ? `:${port}` : ''}`
}

function normalizeConsolePath(path = '/client/dashboard') {
  const normalized = String(path || '/client/dashboard').trim()
  return normalized.startsWith('/') ? normalized : `/${normalized}`
}

function appendQuery(url, query = {}) {
  const entries = Object.entries(query)
    .filter(([, value]) => value !== undefined && value !== null && value !== '')

  if (!entries.length) {
    return url
  }

  const separator = url.includes('?') ? '&' : '?'
  const search = new URLSearchParams()
  entries.forEach(([key, value]) => {
    search.set(key, String(value))
  })
  return `${url}${separator}${search.toString()}`
}

export function buildConsoleUrl(path = '/client/dashboard', query = {}) {
  const consolePath = normalizeConsolePath(path)
  const consoleOrigin = normalizeConsoleOrigin()

  if (!consoleOrigin) {
    return appendQuery(consolePath, query)
  }

  try {
    return appendQuery(new URL(consolePath, `${consoleOrigin}/`).toString(), query)
  } catch {
    return appendQuery(consolePath, query)
  }
}

export function isConsolePath(path = '') {
  return normalizeConsolePath(path).startsWith('/client/')
}

export function navigateToConsole(path = '/client/dashboard', query = {}) {
  const targetUrl = buildConsoleUrl(path, query)

  if (typeof window !== 'undefined') {
    window.location.assign(targetUrl)
  }

  return targetUrl
}
