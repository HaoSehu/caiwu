import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 已登录态下的真实页面渲染：覆盖本轮改动的可观测结果。
 *
 * 断言目的：
 * - P0-1（TDesign 按需注册）：后台布局、t-table、筛选区仍正常渲染；
 * - P2-15（金额统一走 shared/finance）：订单金额列显示千分位；
 * - P1-6/7/8（页面边距）：内容容器桌面端为 20px 24px，由布局层唯一提供。
 */

const ORDERS_RESPONSE = {
  list: [
    {
      id: 1,
      order_no: 'E2E202610230001',
      user_id: 1,
      user: { nickname: '测试用户', email: 'e2e@example.com' },
      product_name: '云服务器 E2E',
      type: 'normal',
      amount: '12345.6',
      discount: '0',
      quantity: 1,
      status: 'paid',
      created_at: '2026-10-23 10:00:00',
    },
  ],
  total: 1,
  page: 1,
  page_size: 20,
};

test('已登录可进入订单列表并渲染表格与千分位金额', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/orders*', (route) => {
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope(ORDERS_RESPONSE)),
    });
  });

  await page.goto('/admin/finance/orders', { waitUntil: 'domcontentloaded' });

  // 布局与表格渲染（TDesign 按需注册后仍可用）
  await expect(page.locator('#main-content')).toBeVisible();
  await expect(page.getByRole('button', { name: '查询' })).toBeVisible();
  await expect(page.getByRole('button', { name: '重置' })).toBeVisible();
  await expect(page.getByText('E2E202610230001')).toBeVisible();

  // 金额统一走 shared/finance：12345.6 → ¥12,345.60
  await expect(page.getByText('¥12,345.60')).toBeVisible();

  // 页面边距由布局层唯一提供：桌面端 20px 24px
  const padding = await page.locator('#main-content').evaluate((el) => {
    const style = getComputedStyle(el);
    return { top: style.paddingTop, left: style.paddingLeft };
  });
  expect(padding).toEqual({ top: '20px', left: '24px' });

  expect(errors).toEqual([]);
});
