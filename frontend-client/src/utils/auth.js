import {
  getClientToken,
  initClientSessionActivityTracking,
  isClientLoggedIn,
  isClientSessionExpired,
  removeClientToken,
  setClientToken,
} from '@/app/runtime/session'

export function isSessionExpired() {
  return isClientSessionExpired()
}

export function getToken() {
  return getClientToken()
}

export function setToken(token) {
  setClientToken(token)
}

export function touchSessionActivity() {
  const token = getClientToken()
  if (token) {
    setClientToken(token)
  }
}

export function removeToken() {
  removeClientToken()
}

export function isLoggedIn() {
  return isClientLoggedIn()
}

export function getUserType() {
  return 'client'
}

export function initSessionActivityTracking() {
  initClientSessionActivityTracking()
}
