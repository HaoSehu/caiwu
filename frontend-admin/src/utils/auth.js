import {
  getAdminToken,
  initAdminSessionActivityTracking,
  isAdminLoggedIn,
  isAdminSessionExpired,
  removeAdminToken,
  setAdminToken,
} from '@/app/runtime/session'

export function isSessionExpired(userType = 'admin') {
  return userType === 'client' ? false : isAdminSessionExpired()
}

export function getToken(userType = 'admin') {
  return userType === 'client' ? null : getAdminToken()
}

export function setToken(token, userType = 'admin') {
  if (userType === 'admin') {
    setAdminToken(token)
  }
}

export function touchSessionActivity(userType = 'admin') {
  if (userType === 'admin') {
    const token = getAdminToken()
    if (token) {
      setAdminToken(token)
    }
  }
}

export function removeToken(userType = 'admin') {
  if (userType === 'admin') {
    removeAdminToken()
  }
}

export function getUserType() {
  return getAdminToken() ? 'admin' : 'admin'
}

export function isLoggedIn(userType = 'admin') {
  return userType === 'client' ? false : isAdminLoggedIn()
}

export function initSessionActivityTracking(userType = 'admin') {
  if (userType === 'admin') {
    initAdminSessionActivityTracking()
  }
}
