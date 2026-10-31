import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 财务账单列表的列口径：
 * - 优惠 / 优惠券两列已从列表移除（折扣信息只在账单详情查看）；
 * - 新增「计费周期」列：billing_cycle 是下单时按商品定价周期写入的英文键，
 *   前端翻成 一个月 / 三个月 / 半年 / 一年 / 按量，不允许把英文键原样漏出。
 */

const INVOICES_RESPONSE = {
  list: [
    {
      id: 1,
      invoice_no: 'E2E202610230001',
      user: { nickname: '测试用户', email: 'e2e@example.com' },
      product_full_path: '云服务器 / 标准型',
      type: 'new',
      type_label: '新购',
      amount: '99.00',
      billing_cycle: 'annually',
      paid_amount: '99.00',
      status: 1,
      created_at: '2026-10-23 10:00:00',
      paid_at: '2026-10-23 10:01:00',
      finished_at: '2026-10-23 10:01:00',
    },
  ],
  total: 1,
  page: 1,
  page_size: 20,
};

test('账单列表：展示计费周期，不再展示优惠/优惠券列', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/invoices*', (route) => {
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope(INVOICES_RESPONSE)),
    });
  });

  await page.goto('/admin/finance/invoices', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('E2E202610230001')).toBeVisible();

  // 计费周期：billing_cycle=annually → 一年
  await expect(page.getByRole('columnheader', { name: '计费周期' })).toBeVisible();
  await expect(page.getByRole('cell', { name: '一年', exact: true })).toBeVisible();

  // 优惠 / 优惠券列已移除
  await expect(page.getByRole('columnheader', { name: '优惠', exact: true })).toHaveCount(0);
  await expect(page.getByRole('columnheader', { name: '优惠券', exact: true })).toHaveCount(0);

  // 创建时间 / 结束时间：已支付账单的结束时间即支付完成时间
  await expect(page.getByRole('columnheader', { name: '创建时间' })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: '结束时间' })).toBeVisible();
  await expect(page.getByRole('cell', { name: '2026-10-23 10:01:00', exact: true })).toBeVisible();
});
