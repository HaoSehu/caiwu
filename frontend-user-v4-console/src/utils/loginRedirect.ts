/**
 * 登录导向统一口径：所有入口（路由守卫、401 拦截、登录页）都走这里，
 * 避免不同入口登录成功后跳转目标不一致。
 */

/** 用户控制台登录页路径。 */
export const CLIENT_LOGIN_PATH = '/client/login';

/** 登录后默认落地页。 */
export const CLIENT_HOME_PATH = '/client/dashboard';

function decodeURIComponentSafe(value: string): string {
  try {
    return decodeURIComponent(value);
  } catch {
    return value;
  }
}

/**
 * 归一化登录后跳转目标：仅接受站内绝对路径，拒绝 `//evil.com` 这类协议相对形态。
 */
export function resolveLoginRedirectPath(raw: unknown, fallback: string = CLIENT_HOME_PATH): string {
  const value = typeof raw === 'string' ? decodeURIComponentSafe(raw).trim() : '';

  return value.startsWith('/') && !value.startsWith('//') ? value : fallback;
}

/**
 * 为登录页构造带 `redirect` 回跳参数的目标，已是登录页或路径非法时退回纯登录路径。
 */
export function buildLoginLocation(currentFullPath: string): string | { path: string; query: { redirect: string } } {
  const redirect = resolveLoginRedirectPath(currentFullPath, '');
  if (!redirect || redirect === CLIENT_LOGIN_PATH || redirect.startsWith(`${CLIENT_LOGIN_PATH}?`)) {
    return CLIENT_LOGIN_PATH;
  }

  return { path: CLIENT_LOGIN_PATH, query: { redirect } };
}
