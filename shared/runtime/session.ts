const ACTIVITY_EVENTS = ['pointerdown', 'keydown', 'scroll', 'focus']
const ACTIVITY_TOUCH_INTERVAL_MS = 1000

export interface SessionDriverOptions {
  tokenKey: string
  lastActiveKey: string
  idleTimeoutMs?: number
  /**
   * Cookie 作用域。设置后会话信息写入 Cookie 而非 localStorage，
   * 以便在同一主域的不同子域 / 端口间共享登录态。
   * 例如生产环境传 `.coyjs.cn`，开发环境可留空（同主机不同端口的 Cookie 默认共享）。
   */
  cookieDomain?: string
}

export interface SessionDriver {
  getToken: () => string | null
  setToken: (_token: string) => void
  removeToken: () => void
  isLoggedIn: () => boolean
  isSessionExpired: () => boolean
  touchSessionActivity: () => void
  readStorageItem: (_key: string) => string
}

// 与后端 sanctum 个人访问令牌 24 小时绝对过期对齐：Cookie 存活期不得超过令牌有效期，
// 避免前端持已失效令牌的长期 Cookie 扩大泄露窗口。
const COOKIE_MAX_AGE_SECONDS = 24 * 60 * 60

// 会话凭证的存储姿态说明（已知残余风险，勿当作已彻底解决）：
// 后端当前是纯 Bearer Token 模式（backend/config/sanctum.php 的 stateful 为空），
// 凭证必须能被前端 JS 读到才能放进 Authorization 头，因此这里写入的 Cookie 只能是
// 非 HttpOnly 的脚本 Cookie，XSS 仍可读取。真正的收敛需要后端改为下发
// HttpOnly + Secure + SameSite=Strict 的会话 Cookie 并调整 CORS/Sanctum 配置，
// 属于认证模型重构，不在本节范围内。
// 当前能做的收缩：SameSite=Lax（阻断跨站 POST/iframe/子资源携带，但不阻断
// 顶层 GET 导航）、https 下强制 Secure、删除时同样带 Secure（否则浏览器允许
// Secure Cookie 与同名非 Secure 指令并存，退出登录后可能删不掉）。
// 为什么不是 Strict：三端之间、以及邮件/搜索结果/广告进来都是跨站顶层导航，
// Strict 会让首个 HTML 请求与首屏 API 请求都不带凭证，表现为「外链进站显示未登录、
// 刷新后才登录」。已用 Playwright 造两个 registrable domain 实测：Strict 下落地页
// 首个请求 cookie 为 (none)，Lax 下正常携带。

function readCookie(name: string) {
  if (typeof document === 'undefined') {
    return ''
  }

  const prefix = `${encodeURIComponent(name)}=`
  const segments = document.cookie ? document.cookie.split('; ') : []
  for (const segment of segments) {
    if (segment.startsWith(prefix)) {
      return decodeURIComponent(segment.slice(prefix.length))
    }
  }

  return ''
}

function writeCookie(name: string, value: string, cookieDomain?: string) {
  if (typeof document === 'undefined') {
    return
  }

  const secure = typeof window !== 'undefined' && window.location.protocol === 'https:'
  const parts = [
    `${encodeURIComponent(name)}=${encodeURIComponent(value)}`,
    'path=/',
    `max-age=${COOKIE_MAX_AGE_SECONDS}`,
    // 用 Lax 而非 Strict：需要允许跨站顶层 GET 导航携带凭证（见文件头说明）。
    'SameSite=Lax',
  ]
  if (cookieDomain) {
    parts.push(`domain=${cookieDomain}`)
  }
  if (secure) {
    parts.push('Secure')
  }

  document.cookie = parts.join('; ')
}

function deleteCookie(name: string, cookieDomain?: string) {
  if (typeof document === 'undefined') {
    return
  }

  const parts = [
    `${encodeURIComponent(name)}=`,
    'path=/',
    'max-age=0',
    // 必须与写入路径的 SameSite 取值一致，否则同名 Cookie 可能删不掉。
    'SameSite=Lax',
  ]
  if (cookieDomain) {
    parts.push(`domain=${cookieDomain}`)
  }
  // 与写入路径保持一致：https 下写入的是 Secure Cookie，删除指令也必须带 Secure，
  // 否则部分浏览器会保留原 Cookie，导致「退出登录后仍是登录态」。
  if (typeof window !== 'undefined' && window.location.protocol === 'https:') {
    parts.push('Secure')
  }

  document.cookie = parts.join('; ')
}

function readStorageItem(_key: string) {
  if (typeof window === 'undefined') {
    return ''
  }

  return window.localStorage.getItem(_key) || ''
}

export function createSessionDriver(options: SessionDriverOptions): SessionDriver {
  const idleTimeoutMs = Number(options.idleTimeoutMs || 3 * 60 * 60 * 1000)
  const cookieDomain = options.cookieDomain || undefined

  function readSession(key: string) {
    const fromCookie = readCookie(key)
    if (fromCookie) {
      return fromCookie
    }
    // 平滑迁移：历史登录态存于 localStorage，读到后回写 Cookie。
    // 只有确认回写成功后删除 localStorage 副本；回写失败（隐私模式、Cookie 被禁用）
    // 时保留副本，否则用户会凭空掉线。
    const legacy = readStorageItem(key)
    if (legacy) {
      writeCookie(key, legacy, cookieDomain)
      const persisted = readCookie(key)
      if (persisted === legacy) {
        window.localStorage.removeItem(key)
      }
      return legacy
    }
    return ''
  }

  function getLastActiveAt() {
    const raw = readSession(options.lastActiveKey)
    const timestamp = Number(raw)

    return Number.isFinite(timestamp) && timestamp > 0 ? timestamp : 0
  }

  function setLastActiveAt(timestamp = Date.now()) {
    writeCookie(options.lastActiveKey, String(timestamp), cookieDomain)
  }

  function clearStoredToken() {
    deleteCookie(options.tokenKey, cookieDomain)
    deleteCookie(options.lastActiveKey, cookieDomain)
    // 清理历史遗留的 localStorage 数据，避免新旧机制并存
    if (typeof window !== 'undefined') {
      window.localStorage.removeItem(options.tokenKey)
      window.localStorage.removeItem(options.lastActiveKey)
    }
  }

  function isSessionExpired() {
    const storedToken = readSession(options.tokenKey)

    if (!storedToken) {
      return false
    }

    const lastActiveAt = getLastActiveAt()

    if (!lastActiveAt) {
      setLastActiveAt()
      return false
    }

    return Date.now() - lastActiveAt >= idleTimeoutMs
  }

  function getToken() {
    const storedToken = readSession(options.tokenKey)

    if (!storedToken) {
      return null
    }

    if (isSessionExpired()) {
      clearStoredToken()
      return null
    }

    if (!getLastActiveAt()) {
      setLastActiveAt()
    }

    return storedToken
  }

  function setToken(token: string) {
    if (!token || typeof token !== 'string') {
      return
    }

    writeCookie(options.tokenKey, token, cookieDomain)
    setLastActiveAt()
  }

  function touchSessionActivity() {
    if (!readSession(options.tokenKey)) {
      return
    }

    if (isSessionExpired()) {
      clearStoredToken()
      return
    }

    setLastActiveAt()
  }

  function removeToken() {
    clearStoredToken()
  }

  function isLoggedIn() {
    return !!getToken()
  }

  return {
    getToken,
    setToken,
    removeToken,
    isLoggedIn,
    isSessionExpired,
    touchSessionActivity,
    readStorageItem,
  }
}

const initializedTrackers = new Set<string>()
const pendingTouchTimers = new Map<string, number>()

export function initSessionActivityTracking(key: string, touch: () => void) {
  if (initializedTrackers.has(key) || typeof window === 'undefined' || typeof document === 'undefined') {
    return
  }

  const scheduleTouch = () => {
    if (pendingTouchTimers.has(key)) {
      return
    }

    const timer = window.setTimeout(() => {
      pendingTouchTimers.delete(key)
      touch()
    }, ACTIVITY_TOUCH_INTERVAL_MS)

    pendingTouchTimers.set(key, timer)
  }

  ACTIVITY_EVENTS.forEach((eventName) => {
    window.addEventListener(eventName, scheduleTouch, { passive: true })
  })

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      scheduleTouch()
    }
  })

  initializedTrackers.add(key)
}
