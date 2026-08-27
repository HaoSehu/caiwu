import { createHttpClient } from '@caiwu/shared/runtime';
import { MessagePlugin } from 'tdesign-vue-next';

import { getClientToken, removeClientToken } from '@/app/runtime/session';
import router from '@/router';

let lastErrorMsg = '';
let lastErrorTimer: ReturnType<typeof setTimeout> | null = null;
let redirectingToLogin = false;

// 登录提交期间（含成功后跳转窗口）屏蔽「同域其他标签页/残余请求 401」清 token 与弹跳的竞态，
// 避免登录成功后 token 被并发 401 清掉导致停在登录页。
let loginRedirectHoldUntil = 0;

/** 登录流程期间调用：窗口内 401 不会清理 token 或弹跳回登录页 */
export function holdLoginRedirect(milliseconds = 15000) {
  loginRedirectHoldUntil = Date.now() + milliseconds;
}

/** 登录失败后调用：立即恢复 401 的清理与弹跳行为 */
export function clearLoginRedirectHold() {
  loginRedirectHoldUntil = 0;
}

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

function redirectLogin(msg?: string, requestToken?: string | null) {
  if (Date.now() < loginRedirectHoldUntil) {
    return;
  }

  if (redirectingToLogin) {
    return;
  }

  // 触发 401 的请求携带的凭证与当前凭证不同 => 期间已重新登录，
  // 旧请求的失效作废，避免误清新登录态（常见于同域多标签页竞态）。
  if (requestToken !== undefined && getClientToken() !== requestToken) {
    return;
  }

  redirectingToLogin = true;
  removeClientToken();
  if (typeof window !== 'undefined') {
    window.localStorage.removeItem('client_user');
  }
  // HTTP 401（凭证失效）由拦截器静默弹回，补一次提示避免用户困惑；
  // 业务 40100（含中文 message）由调用方自行提示，不在此重复。
  if (msg && !/[\u4E00-\u9FA5]/.test(msg)) {
    MessagePlugin.warning('登录状态已失效，请重新登录');
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

// 认证前的公开接口（与后端 v2-client.php 公开路由保持一致）不携带 token：
// 避免残留凭证在登录流程中触发 401 弹跳。
const PUBLIC_AUTH_PATHS = [
  '/v2/site/',
  '/v2/client/login',
  '/v2/client/register',
  '/v2/client/auth/captcha-config',
  '/v2/client/auth/captcha-script',
  '/v2/client/auth/login-by-code',
  '/v2/client/auth/phone-code',
  '/v2/client/auth/email-code',
  '/v2/client/auth/reset-password',
  '/v2/client/auth/login-as/exchange',
  '/v2/client/verification/callback',
  '/v2/client/verification/scan',
];

function isPublicAuthRequest(url: string) {
  return PUBLIC_AUTH_PATHS.some((path) => url.startsWith(path));
}

const httpClient = createHttpClient({
  baseURL: apiBaseUrl,
  showError,
  onUnauthorized: (msg, requestToken) => redirectLogin(msg, requestToken),
  // 认证前公开接口不携带过期 token，避免它们 401 干扰登录流程
  resolveToken: (config) => {
    const requestUrl = String(config.url || '');
    // 登录后仍走公开发码接口的"验证已绑定手机/邮箱"场景需要显式要求携带凭证
    if ((config as { authBearer?: boolean }).authBearer) {
      return getClientToken();
    }
    return isPublicAuthRequest(requestUrl) ? null : getClientToken();
  },
});

export default httpClient;
