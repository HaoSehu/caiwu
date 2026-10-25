import type { Page } from '@playwright/test';

import { collectConsoleErrors, expect, mockDashboard, test } from './fixtures';

/**
 * 刷新性能回归：守卫不得在每次刷新时都串行请求 /v2/admin/auth/info。
 *
 * 背景：user store 只持久化 userInfo，permission store 的 menuBuilt 刷新即 false，
 * 原守卫因此每次刷新都要先 await /v2/admin/auth/info（生产 ttfb 约 540-650ms）才放行目标路由，
 * 这段等待完全落在首屏白屏区间。修复后 profileSyncedAt 一并持久化，
 * 未过期时直接用本地资料重建菜单（buildMenus 为同步纯计算），不再发请求。
 *
 * 同时保留兜底：超过 PROFILE_HYDRATION_TTL_MS（5 分钟）的旧资料视为未水合，仍回源一次，
 * 保证管理员权限调整后不会无限期沿用陈旧权限。
 */

function watchAuthInfo(page: Page) {
  const urls: string[] = [];
  page.on('request', (request) => {
    if (/\/v2\/admin\/auth\/info/.test(request.url())) {
      urls.push(request.url());
    }
  });
  return urls;
}

/** 等菜单渲染完成：守卫放行后布局才会挂载侧边菜单。 */
async function waitForMenu(page: Page) {
  await page.locator('.t-menu__item').first().waitFor({ state: 'visible' });
}

test('刷新复用本地资料：不再重复请求 /v2/admin/auth/info', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  const authRequests = watchAuthInfo(page);
  await mockDashboard(page);

  // 首次访问：localStorage 无资料，必须回源一次
  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
  await waitForMenu(page);
  await expect.poll(() => authRequests.length).toBe(1);

  // 水合时间戳必须随 userInfo 一起落盘，否则刷新后无法判断缓存是否新鲜
  const persisted = await page.evaluate(() => localStorage.getItem('user'));
  expect(persisted).toContain('profileSyncedAt');

  await page.reload({ waitUntil: 'domcontentloaded' });
  await waitForMenu(page);

  // 刷新后直接用本地资料重建菜单，请求数保持不变
  // 用 poll 而非一次性断言：菜单已渲染时尾部仍可能有迟到请求在途
  await expect.poll(() => authRequests.length).toBe(1);
  expect(errors).toEqual([]);
});

test('本地资料超过有效期后仍回源一次 /v2/admin/auth/info', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  const authRequests = watchAuthInfo(page);
  await mockDashboard(page);

  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
  await waitForMenu(page);
  await expect.poll(() => authRequests.length).toBe(1);

  // 把水合时间戳改到 TTL 之外，模拟长时间挂着不刷新的标签页
  await page.evaluate(() => {
    const raw = localStorage.getItem('user');
    if (!raw) {
      throw new Error('未找到持久化的 user store');
    }
    const parsed = JSON.parse(raw) as Record<string, unknown>;
    parsed.profileSyncedAt = Date.now() - 10 * 60 * 1000;
    localStorage.setItem('user', JSON.stringify(parsed));
  });

  await page.reload({ waitUntil: 'domcontentloaded' });
  await waitForMenu(page);

  // 过期资料不信任，仍要回源刷新一次权限
  await expect.poll(() => authRequests.length).toBe(2);
  expect(errors).toEqual([]);
});

test('有本地资料但无 token 时不得视为已水合，直接回登录页', async ({ page }) => {
  // 只种持久化资料、不种 Cookie：模拟 token 已过期/被清理但 localStorage 仍有 userInfo 的残留现场。
  // afterHydrate 必须以 Cookie 里的 token 为准，否则残留资料会让守卫误判为已登录。
  // 故意不 mock /auth/info：这条路径本就不该发出该请求。
  await page.addInitScript(() => {
    localStorage.setItem(
      'user',
      JSON.stringify({
        userInfo: { name: '残留管理员', roles: ['super_admin'], permissions: ['*'] },
        profileSyncedAt: Date.now(),
      }),
    );
  });

  const authRequests = watchAuthInfo(page);
  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });

  await expect(page).toHaveURL(/\/admin\/login/);
  // 未登录就不该去打 auth/info（登录页自身不发这个请求）
  expect(authRequests.length).toBe(0);
});
