import { createHttpClient } from '@caiwu/shared/runtime';
import { MessagePlugin } from 'tdesign-vue-next';

import { getClientToken, removeClientToken } from '@/app/runtime/session';
import router from '@/router';

let lastErrorMsg = '';
let lastErrorTimer: ReturnType<typeof setTimeout> | null = null;
let redirectingToLogin = false;

function showError(msg: string) {
  if (msg === lastErrorMsg) {
    return;
  }

  lastErrorMsg = msg;
  MessagePlugin.error(msg);
  if (lastErrorTimer) {
    clearTimeout(lastErrorTimer);
  }
  lastErrorTimer = setTimeout(() => {
    lastErrorMsg = '';
  }, 1000);
}

function redirectLogin() {
  if (redirectingToLogin) {
    return;
  }

  redirectingToLogin = true;
  removeClientToken();
  if (typeof window !== 'undefined') {
    window.localStorage.removeItem('client_user');
  }
  router.push('/client/login').finally(() => {
    redirectingToLogin = false;
  });
}

const apiBaseUrl = String(import.meta.env.VITE_API_BASE_URL || '')
  .trim()
  .replace(/\/+$/, '');

if (!apiBaseUrl) {
  throw new Error('VITE_API_BASE_URL 必须配置');
}

const httpClient = createHttpClient({
  baseURL: apiBaseUrl,
  showError,
  onUnauthorized: () => redirectLogin(),
  // 公共站点 GET 无需登录态，避免携带过期 token 触发 401 跳转
  resolveToken: (config) => {
    const requestUrl = String(config.url || '');
    const isPublicSiteGetRequest =
      String(config.method || 'get').toLowerCase() === 'get' && requestUrl.startsWith('/v2/site/');
    return isPublicSiteGetRequest ? null : getClientToken();
  },
});

export default httpClient;
