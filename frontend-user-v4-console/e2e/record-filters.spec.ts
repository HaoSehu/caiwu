import type { Page } from '@playwright/test';

import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 筛选「清空」语义：t-select 的 clearable 清空后值是 undefined 而不是空串
// （node_modules/tdesign-vue-next/es/select/select.mjs:596-607 的 onClear 走 setInnerValue(void 0)）。
// 若 hasActiveFilters 只判 `!== ''`，用户点掉状态筛选后空态仍会说「未找到匹配结果」，
// 并给出一个「清除筛选条件」按钮——筛选早已清空，这个按钮点了什么也不会发生。

const EMPTY_PAGE = { list: [] as Record<string, unknown>[], total: 0, page: 1, page_size: 20 };

async function mockEmptyOrders(page: Page) {
  await page.route('**/api/v2/client/orders*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope(EMPTY_PAGE)),
    });
  });
}

test('订单列表：清空状态筛选后空态回到「暂无订单记录」，不再谎报命中筛选', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockEmptyOrders(page);

  await page.goto('/client/orders', { waitUntil: 'domcontentloaded' });

  // 初始无筛选：空态文案是业务空态
  await expect(page.getByText('暂无订单记录')).toBeVisible();

  // 选中一个状态 → 变成「命中筛选但 0 条」
  const statusSelect = page.locator('.record-toolbar .t-select').first();
  await statusSelect.click();
  await page.getByText('待支付', { exact: true }).last().click();
  await expect(page.getByText('未找到匹配结果')).toBeVisible();

  // 点清空图标：值是 undefined（不是空串），空态必须回到业务空态
  await statusSelect.hover();
  await statusSelect.locator('.t-input__suffix-clear').click();

  await expect(page.getByText('暂无订单记录')).toBeVisible();
  await expect(page.getByText('未找到匹配结果')).toHaveCount(0);
  await expect(page.getByRole('button', { name: '清除筛选条件' })).toHaveCount(0);
});
