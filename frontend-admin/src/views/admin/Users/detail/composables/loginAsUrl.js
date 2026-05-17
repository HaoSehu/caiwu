const CLIENT_LOGIN_AS_PATH = '/client/login-as'
const CLIENT_DASHBOARD_PATH_RE = /\/client\/dashboard\/?$/i
const CLIENT_LOGIN_AS_PATH_RE = /\/client\/login-as\/?$/i
const DOMAIN_LIKE_RE = /^(localhost|127(?:\.\d{1,3}){3}|(?:[a-z\d-]+\.)+[a-z\d-]+)(?::\d+)?(?:[/?#].*)?$/i

export function buildClientLoginAsUrl(code, options = {}) {
  const cleanCode = encodeURIComponent(String(code || '').trim())
  const redirectLoginAsUrl = resolveClientLoginAsUrlFromRedirect(options.redirectUrl || '')
  if (!redirectLoginAsUrl) {
    throw new Error('未获取到客户端跳转地址，请检查后端 FRONTEND_URL 配置')
  }

  return `${redirectLoginAsUrl}?code=${cleanCode}`
}

export function resolveClientLoginAsUrlFromRedirect(redirectUrl = '') {
  const absoluteUrl = normalizeAbsoluteUrl(redirectUrl)
  if (!absoluteUrl) return ''

  try {
    const url = new URL(absoluteUrl)
    url.pathname = normalizeClientLoginAsPath(url.pathname)
    url.search = ''
    url.hash = ''

    return trimTrailingSlash(url.toString())
  } catch {
    return ''
  }
}

export function normalizeClientBaseUrl(value = '') {
  const normalized = normalizeAbsoluteUrl(value)
  return normalized ? trimTrailingSlash(normalized) : ''
}

function normalizeClientLoginAsPath(pathname = '') {
  const path = String(pathname || '')
  if (CLIENT_DASHBOARD_PATH_RE.test(path)) {
    return path.replace(CLIENT_DASHBOARD_PATH_RE, CLIENT_LOGIN_AS_PATH)
  }
  if (CLIENT_LOGIN_AS_PATH_RE.test(path)) {
    return path.replace(CLIENT_LOGIN_AS_PATH_RE, CLIENT_LOGIN_AS_PATH)
  }
  return CLIENT_LOGIN_AS_PATH
}

function normalizeAbsoluteUrl(value = '') {
  const normalized = String(value || '').trim()
  if (!normalized) return ''
  if (/^[a-z][a-z\d+\-.]*:\/\//i.test(normalized)) return normalized
  if (normalized.startsWith('//')) return `https:${normalized}`
  if (DOMAIN_LIKE_RE.test(normalized)) {
    return `https://${normalized}`
  }

  return ''
}

function trimTrailingSlash(value = '') {
  return String(value).replace(/\/+$/, '')
}
