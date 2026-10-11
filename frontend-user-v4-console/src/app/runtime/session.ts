import { createSessionDriver, initSessionActivityTracking } from '@caiwu/shared/runtime';

import { localStorageKeys, removeLocalStorage } from '@/utils/storage';

const driver = createSessionDriver({
  tokenKey: 'client_token',
  lastActiveKey: 'client_last_active_at',
  cookieDomain: (import.meta.env.VITE_SESSION_COOKIE_DOMAIN as string | undefined) || undefined,
});

export function getClientToken() {
  return driver.getToken();
}

export function setClientToken(token: string) {
  driver.setToken(token);
}

export function removeClientToken() {
  driver.removeToken();
}

export function clearClientSessionArtifacts() {
  driver.removeToken();
  removeLocalStorage(localStorageKeys.clientUser);
}

export function isClientLoggedIn() {
  return driver.isLoggedIn();
}

export function isClientSessionExpired() {
  return driver.isSessionExpired();
}

export function initClientSessionActivityTracking() {
  initSessionActivityTracking('client', () => driver.touchSessionActivity());
}
