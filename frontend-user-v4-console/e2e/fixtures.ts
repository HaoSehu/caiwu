import type { Page } from '@playwright/test';
import { test as base } from '@playwright/test';

/**
 * 控制台 e2e 公共夹具。
 *
 * 设计前提（均已核实）：
 * - 会话令牌存于 `client_token` Cookie，而非 localStorage（见 shared/runtime/session.ts 的
 *   createSessionDriver → setToken 走 writeCookie）。因此预置登录态必须写 Cookie。
 * - 后端信封为 `{ code, message, data }`，`code !== 0` 会被响应拦截器判为失败
 *   （见 shared/runtime/http/client.ts 的 `if (res.code !== 0)`）。
 * - .env.e2e 把 VITE_API_BASE_URL 指向同源 `/api`，所有接口由本文件的 route mock 承接，
 *   不依赖本地后端，也不需要测试账号凭据。
 */

/** 必须与 playwright.config.ts 的 baseURL 保持一致。 */
export const E2E_ORIGIN = 'http://127.0.0.1:4173';

/** e2e 专用假令牌：只用于让路由守卫识别为已登录，不指向任何真实会话。 */
export const E2E_CLIENT_TOKEN = 'e2e-client-token';

export const E2E_USER_INFO = {
  id: 1,
  name: '测试用户',
  nickname: '测试用户',
  email: 'e2e@example.com',
  phone: '13800000000',
  cash_balance: '1000.00',
  is_verified: 1,
  verification_status: 1,
};

export const E2E_SITE_CONFIG = {
  site_name: '创欧云',
  browser_title: '创欧云用户控制台',
  service_qq_group: '123456789',
  service_email: 'support@example.com',
  service_hours: '9:00 - 18:00',
};

export function envelope<T>(data: T, code = 0, message = 'ok') {
  return { code, message, data };
}

function jsonResponse(body: unknown, status = 200) {
  return {
    status,
    contentType: 'application/json',
    body: JSON.stringify(body),
  };
}

/** 预置登录态：写 `client_token` Cookie，使 permission.ts 的 requireAuth 分支通过。 */
export async function seedClientSession(page: Page, token = E2E_CLIENT_TOKEN) {
  await page.context().addCookies([
    {
      name: 'client_token',
      value: token,
      // 注意：Playwright 不允许 url 与 path 同时出现，这里用 url（已隐含 path=/）
      url: E2E_ORIGIN,
      sameSite: 'Lax',
    },
  ]);
}

/** 站点配置：每个路由切换都会拉取，登录页也需要，必须 mock。 */
export async function mockSiteConfig(page: Page, data: Record<string, unknown> = E2E_SITE_CONFIG) {
  await page.route('**/api/v2/site/config*', (route) => route.fulfill(jsonResponse(envelope(data))));
}

/** 当前登录用户信息：requireAuth 路由在 profileHydrated 为空时会调用。 */
export async function mockAuthInfo(page: Page, data: Record<string, unknown> = E2E_USER_INFO) {
  await page.route('**/api/v2/client/auth/info', (route) => route.fulfill(jsonResponse(envelope(data))));
}

/** 一次性配齐「已登录 + 公共接口」。 */
export async function mockAuthenticatedSession(page: Page) {
  await seedClientSession(page);
  await mockSiteConfig(page);
  await mockAuthInfo(page);
}

/** 收集页面控制台错误与未捕获异常，用于断言无 JS 异常。 */
export function collectConsoleErrors(page: Page) {
  const errors: string[] = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      errors.push(msg.text());
    }
  });
  page.on('pageerror', (err) => {
    errors.push(String(err));
  });
  return errors;
}

export const test = base;
export { expect } from '@playwright/test';
