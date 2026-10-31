import type { Page } from '@playwright/test';

import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 支付记录列表：用户端只能看到「支付渠道」，插件名（如「易支付」）不得出现在任何位置。
// 后端 /api/v2/client/payments 的 list 项已拆成 plugin_name / channel_name 两个字段，
// page 只渲染 channel_name，筛选项也只有渠道、不含插件名。

const PAYMENT_ROWS = [
  {
    id: 1,
    payment_no: 'PY202610310001',
    trade_no: 'T202610310001',
    gateway: 'yipay',
    gateway_key: 'yipay',
    gateway_label: '微信支付',
    plugin_name: '易支付',
    channel_name: '微信支付',
    payment_type: 'wxpay',
    amount: '99.00',
    status: 1,
    status_label: '成功',
    paid_at: '2026-10-31 10:00:00',
    created_at: '2026-10-31 09:59:00',
  },
];

function jsonOk(data: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(envelope(data)) };
}

async function mockPayments(page: Page) {
  await page.route('**/api/v2/client/payments?*', (route) => {
    const body = { list: PAYMENT_ROWS, total: PAYMENT_ROWS.length, page: 1, page_size: 20 };
    return route.fulfill(jsonOk(body));
  });
}

test('支付记录：只展示支付渠道，筛选下拉与列表都不出现插件名', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockPayments(page);

  await page.goto('/client/payments', { waitUntil: 'domcontentloaded' });

  // 列表渲染渠道名
  await expect(page.getByText('微信支付').first()).toBeVisible();
  // 页面任何位置都不允许出现插件名
  await expect(page.locator('body')).not.toContainText('易支付');

  // 「全部渠道」下拉的候选项里同样不能有插件名
  const gatewaySelect = page.locator('.record-toolbar .t-select').nth(1);
  await gatewaySelect.click();
  await expect(page.locator('.t-select__dropdown')).not.toContainText('易支付');
  await expect(page.locator('.t-select__dropdown')).toContainText('支付宝');
});
