import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// E-01 / E-02：404 通配路由的两个可达性问题。
// E-01 未登录访问不存在的地址时，通配路由没有 requireAuth，守卫直接放行，
//      用户只看到 404 卡片、拿不到「先去登录」的提示，登录后也无法回跳。
// E-02 404 页的「返回控制台首页」原先用 push，从 404 跳回首页会留下历史记录，
//      用户一点浏览器后退就立刻退回这个不存在的地址，退不出去。

const MISSING_PATH = '/client/this-path-does-not-exist';

const EMPTY_PAGE = { list: [] as Record<string, unknown>[], total: 0, page: 1, page_size: 20 };

const SITE_CONFIG = { site_name: '创欧云', browser_title: '创欧云用户控制台' };

test('404：未登录访问不存在的地址会先跳登录并带上回跳地址', async ({ page }) => {
  await page.route('**/api/v2/site/config*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope(SITE_CONFIG)),
    });
  });

  await page.goto(MISSING_PATH, { waitUntil: 'domcontentloaded' });

  await expect(page).toHaveURL(/\/client\/login/);
  const redirect = new URL(page.url()).searchParams.get('redirect');
  expect(redirect).toBe(MISSING_PATH);
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
});

test('404：登录态下返回首页用 replace，浏览器后退不会退回 404', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await page.route('**/api/v2/client/orders*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope(EMPTY_PAGE)),
    });
  });

  // 先在站内留一条历史，这样「后退」有地方可去
  await page.goto('/client/orders', { waitUntil: 'domcontentloaded' });
  await page.waitForURL(/\/client\/orders/);

  await page.goto(MISSING_PATH, { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { level: 1 })).toContainText('页面不存在');

  await page.getByRole('button', { name: '返回控制台首页' }).click();
  await page.waitForURL(/\/client\/dashboard/);

  // replace 生效的唯一可观测量：后退一步不能回到刚才那个不存在的地址
  await page.goBack();
  await expect(page).not.toHaveURL(/this-path-does-not-exist/);
});
