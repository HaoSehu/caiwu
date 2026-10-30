import type { Page } from '@playwright/test';

import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 本文件覆盖控制台两处「语义变更但此前无 e2e」的行为（布局 keep-alive 重写、未读数拆分）：
// 1) 布局内容区改为「按路由 name 做 key + meta.keepAlive === false 走不缓存旁路」：
//    同一详情组件在多个 id 之间切换会复用同一实例，必须按新 id 重新拉取，绝不能串数据。
// 2) 未读数拆成两个语义不同的计数器：铃铛 = 公告 + 通知（/notifications/unread-count），
//    公告菜单角标 = 仅公告（/notices/unread-count），两处数字必须各自独立、互不覆盖。
// 接口全部 mock，不依赖本地后端与真实账号。

const ORDER_ROWS = [
  {
    id: 1,
    order_no: 'E2E-KEEP-0001',
    type: 'new',
    type_label: '新购',
    status: 1,
    amount: '128.00',
    paid_amount: '128.00',
    billing_cycle: 'monthly',
    product_name: '云服务器 S1',
    created_at: '2026-01-01 10:00:00',
  },
  {
    id: 2,
    order_no: 'E2E-KEEP-0002',
    type: 'new',
    type_label: '新购',
    status: 1,
    amount: '256.00',
    paid_amount: '256.00',
    billing_cycle: 'monthly',
    product_name: '云服务器 S2',
    created_at: '2026-01-02 10:00:00',
  },
];

const ORDER_LIST = { list: ORDER_ROWS, total: ORDER_ROWS.length, page: 1, page_size: 20 };

/**
 * 列表与详情用两条独立的 glob 注册：
 * Playwright 的 `*` 只匹配单段路径（不跨 `/`），`**\/api/v2/client/orders*` 的实际正则是
 * `^.*\/api\/v2\/client\/orders([^/]*)$`，永远拦不到 `/orders/1`。
 * 写成一条会让「详情」用例看起来有 mock、实际靠兜底或缓存通过，将来 goto 详情页就会漏 mock。
 */
async function mockOrders(page: Page) {
  await page.route('**/api/v2/client/orders/*', async (route) => {
    const id = new URL(route.request().url()).pathname.match(/\/orders\/(\d+)$/)?.[1];
    const detail = ORDER_ROWS.find((row) => String(row.id) === id);

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope(detail ?? ORDER_ROWS[0])),
    });
  });

  // 列表可能带查询串也可能不带，用 `orders*` 覆盖两种形态：它的 `*` 不跨 `/`，因此不会抢走详情。
  await page.route('**/api/v2/client/orders*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope(ORDER_LIST)),
    });
  });
}

test('订单详情：同一组件在多个 id 之间切换时按新 id 重新拉取，不串数据', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockOrders(page);

  await page.goto('/client/orders', { waitUntil: 'domcontentloaded' });

  // 从列表进入订单 1
  await page.getByRole('row').filter({ hasText: 'E2E-KEEP-0001' }).getByRole('button', { name: '详情' }).click();
  await expect(page.getByText('E2E-KEEP-0001').first()).toBeVisible();

  // SPA 返回列表：keep-alive 会把订单详情实例留在缓存里，下一次进入会复用同一实例
  await page.getByRole('button', { name: '返回列表' }).click();
  const secondRow = page.getByRole('row').filter({ hasText: 'E2E-KEEP-0002' });
  await expect(secondRow).toBeVisible();

  // 进入订单 2：复用的实例必须换成订单 2 的数据，且不得残留订单 1 的内容
  await secondRow.getByRole('button', { name: '详情' }).click();
  await expect(page.getByText('E2E-KEEP-0002').first()).toBeVisible();
  await expect(page.getByText('E2E-KEEP-0001')).toHaveCount(0);
});

test('未读数：铃铛（公告+通知）与公告菜单角标（仅公告）各自独立显示', async ({ page }) => {
  // 兜底：首屏还有若干与本用例无关的接口，统一返回空信封，避免未匹配请求污染 console。
  // 注册顺序即优先级（Playwright 后注册者优先），因此先兜底，再覆盖登录态与两个未读接口。
  await page.route('**/api/v2/client/**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope({ list: [], total: 0 })),
    });
  });
  await mockAuthenticatedSession(page);
  await page.route('**/api/v2/client/notifications/unread-count*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope({ count: 7 })),
    });
  });
  await page.route('**/api/v2/client/notices/unread-count*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope({ count: 3 })),
    });
  });

  // 首屏（/client/dashboard）会触发公告未读拉取（useDashboardSecondary → fetchUnreadCount），
  // 铃铛未读则由布局 Header 挂载即拉取，两者在同一页面上同时可见。
  await page.goto('/client/dashboard', { waitUntil: 'domcontentloaded' });

  // 铃铛角标 = 7：限定在「站内信」按钮所在的 badge 内，避免与公告角标混同
  const bellBadge = page.locator('.t-badge').filter({ has: page.getByRole('button', { name: '站内信' }) });
  await expect(bellBadge).toContainText('7');

  // 公告菜单角标 = 3：只有「系统公告」菜单项会渲染 .menu-badge-dot
  await expect(page.locator('.menu-badge-dot')).toHaveText('3');
});
