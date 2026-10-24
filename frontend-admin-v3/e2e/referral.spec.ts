import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 推荐页（referral）回归：这一页的表格样式依赖跨端 theme.css 的 #app .referral-page .t-table 规则，
// 金额依赖 shared 的千分位实现（docs/DESIGN.md §6）。两者都曾经被误删/误改过，且没有任何 e2e 覆盖
// （审查条目 P2-3、P2-A）。
const OVERVIEW_RESPONSE = {
  summary: {
    total_sales_amount: '1234567.8',
    frozen_amount: '1234.5',
    available_amount: '9876.54',
    withdrawn_amount: '1000',
  },
  top_referrers: [
    {
      id: 1,
      user_id: 1,
      nickname: '测试推广人',
      email: 'referrer@example.com',
      total_sales_amount: '1234567.8',
      referral_frozen_amount: '1234.5',
      referral_available_amount: '9876.54',
      referral_withdrawing_amount: '0',
      referral_withdrawn_amount: '1000',
      promotion_ambassador: { name: '金牌大使', reward_rate: '10', renewal_reward_rate: '5' },
    },
  ],
};

const OVERVIEW_PATH = '**/v2/admin/referral/overview*';
const LIST_PATHS = ['**/v2/admin/referral/rewards*', '**/v2/admin/referral-withdrawals*'];
// 空列表响应：list 必须显式标注类型，否则 tsconfig 的 noImplicitAny 会把 [] 推断成 any[]（TS7018）
const EMPTY_LIST = { list: [] as Record<string, unknown>[], total: 0, page: 1, page_size: 20 };
const JSON_HEADERS = { status: 200, contentType: 'application/json' };

// 页面挂载时会同时拉概览与两个列表（奖励记录 / 提现记录），三个都要 mock，
// 否则未匹配的请求会由 preview 返回 404 HTML，被前端记成 console error。
async function mockReferralPage(page: Page) {
  const overviewBody = JSON.stringify(envelope(OVERVIEW_RESPONSE));
  await page.route(OVERVIEW_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: overviewBody }));
  const listBody = JSON.stringify(envelope(EMPTY_LIST));
  for (const listPath of LIST_PATHS) {
    await page.route(listPath, (route) => route.fulfill({ ...JSON_HEADERS, body: listBody }));
  }
}

test('推荐概览：金额千分位与共用表格样式生效', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockReferralPage(page);
  await page.goto('/admin/referral', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('.referral-metrics strong').first()).toHaveText('¥1,234,567.80');
  await expect(page.getByText('测试推广人')).toBeVisible();
  await expect(page.locator('.referral-page .stack-cell span').first()).toHaveText('referrer@example.com');

  // theme.css 的共用规则：透明底、th/td 内边距 10px 18px 与底边框、表头用次要文字色
  const table = page.locator('.referral-page .t-table').first();
  const cell = table.locator('tbody td').first();
  await expect(table).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
  await expect(cell).toHaveCSS('padding-top', '10px');
  await expect(cell).toHaveCSS('padding-right', '18px');
  await expect(cell).toHaveCSS('border-bottom-width', '1px');
  await expect(table.locator('thead th').first()).toHaveCSS('font-size', '12px');

  expect(errors).toEqual([]);
});
