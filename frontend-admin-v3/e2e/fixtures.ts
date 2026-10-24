import type { Page } from '@playwright/test';
import { test as base } from '@playwright/test';

/**
 * 管理端 e2e 公共夹具。
 *
 * 设计前提（均已核实）：
 * - 会话令牌存于 `admin_token` Cookie，而非 localStorage（见 shared/runtime/session.ts 的
 *   createSessionDriver → setToken 走 writeCookie），因此预置登录态必须写 Cookie。
 * - 后端信封为 `{ code, message, data }`，请求层只有 `code === 0` 才返回 `data`
 *   （见 frontend-admin-v3/src/utils/request/index.ts 的 transformRequestHook），
 *   因此所有接口 mock 必须返回该信封。
 * - 接口基址来自 `VITE_API_BASE_URL`，由 scripts/build-e2e.mjs 在构建期固定为同源 `/api`
 *   （管理端没有 .env.e2e，不能退回 .env 里的线上域名）。管理端 preview 没有 /api 代理，
 *   因此用例内一律用通配路径拦截 /v2/admin 下的所有接口，不依赖本地后端，也不需要测试账号凭据。
 */

/** 必须与 playwright.config.ts 的 baseURL 保持一致。 */
export const E2E_ORIGIN = 'http://127.0.0.1:4173';

/** e2e 专用假令牌：只用于让路由守卫识别为已登录，不指向任何真实会话。 */
export const E2E_ADMIN_TOKEN = 'e2e-admin-token';

/** 管理端当前登录用户：permissions 给 `*` 即全量权限放行（见 constants/permissions.ts）。 */
export const E2E_ADMIN_INFO = {
  admin: {
    id: 1,
    nickname: '测试管理员',
    username: 'e2e-admin',
    email: 'e2e@example.com',
    role: 'super_admin',
    permissions: ['*'],
  },
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

/** 预置登录态：写 `admin_token` Cookie，使 permission.ts 的已登录分支通过。 */
export async function seedAdminSession(page: Page, token = E2E_ADMIN_TOKEN) {
  await page.context().addCookies([
    {
      name: 'admin_token',
      value: token,
      // 注意：Playwright 不允许 url 与 path 同时出现，这里用 url（已隐含 path=/）
      url: E2E_ORIGIN,
      sameSite: 'Lax',
    },
  ]);
}

/** 当前登录用户信息：守卫在菜单未构建时会调用。 */
export async function mockAuthInfo(page: Page, data: Record<string, unknown> = E2E_ADMIN_INFO) {
  // 末尾必须带通配：请求层会给 GET 追加 `?_t=` 时间戳防缓存，精确路径匹配不到
  await page.route('**/v2/admin/auth/info*', (route) => route.fulfill(jsonResponse(envelope(data))));
}

/** 一次性配齐「已登录 + 用户信息」。 */
export async function mockAuthenticatedSession(page: Page) {
  await seedAdminSession(page);
  await mockAuthInfo(page);
}

/**
 * 配齐已登录 + 首页看板接口。
 *
 * 看板页挂载后会并发请求 stats 与 monthly-revenue（见 src/pages/dashboard/base/index.vue:266-269），
 * 其 catch 分支静默置空，未 mock 时请求会穿透到线上域名：既依赖外网，也让「无 console 错误」的断言失真。
 */
export async function mockDashboard(
  page: Page,
  stats: Record<string, unknown> = {},
  monthly: Record<string, unknown> = {},
) {
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/dashboard/stats*', (route) => route.fulfill(jsonResponse(envelope(stats))));
  await page.route('**/v2/admin/dashboard/monthly-revenue*', (route) => route.fulfill(jsonResponse(envelope(monthly))));
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
