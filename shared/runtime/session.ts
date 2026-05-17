const ACTIVITY_EVENTS = ['pointerdown', 'keydown', 'scroll', 'focus']
const ACTIVITY_TOUCH_INTERVAL_MS = 1000

export interface SessionDriverOptions {
  tokenKey: string
  lastActiveKey: string
  idleTimeoutMs?: number
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

function readStorageItem(_key: string) {
  if (typeof window === 'undefined') {
    return ''
  }

  return window.localStorage.getItem(_key) || ''
}

export function createSessionDriver(options: SessionDriverOptions): SessionDriver {
  const idleTimeoutMs = Number(options.idleTimeoutMs || 3 * 60 * 60 * 1000)

  function getLastActiveAt() {
    const raw = readStorageItem(options.lastActiveKey)
    const timestamp = Number(raw)

    return Number.isFinite(timestamp) && timestamp > 0 ? timestamp : 0
  }

  function setLastActiveAt(timestamp = Date.now()) {
    if (typeof window === 'undefined') {
      return
    }

    window.localStorage.setItem(options.lastActiveKey, String(timestamp))
  }

  function clearStoredToken() {
    if (typeof window === 'undefined') {
      return
    }

    window.localStorage.removeItem(options.tokenKey)
    window.localStorage.removeItem(options.lastActiveKey)
  }

  function isSessionExpired() {
    const storedToken = readStorageItem(options.tokenKey)

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
    const storedToken = readStorageItem(options.tokenKey)

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
    if (!token || typeof token !== 'string' || typeof window === 'undefined') {
      return
    }

    window.localStorage.setItem(options.tokenKey, token)
    setLastActiveAt()
  }

  function touchSessionActivity() {
    if (!readStorageItem(options.tokenKey)) {
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
