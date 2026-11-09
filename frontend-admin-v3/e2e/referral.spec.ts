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

// 已通过待打款的支付宝提现：验证「确认打款 / 标记失败」两个动作都在 APPROVED 行可见
const APPROVED_WITHDRAWAL: Record<string, unknown> = {
  id: 7,
  amount: '50.00',
  method: 'alipay',
  account_name: '测试用户',
  account_no: 'test@example.test',
  status: 1,
  status_label: '已通过',
  payment_no: null,
  remark: null,
  operator: 'admin',
  paid_at: null,
  created_at: '2026-11-09 10:00:00',
  processed_at: '2026-11-09 10:05:00',
  user: { id: 2, email: 'withdraw@example.com', nickname: '提现用户', display_name: '提现用户' },
};

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

test('提现打款失败：APPROVED 行可标记失败并提交原因', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await page.route(OVERVIEW_PATH, (route) =>
    route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope(OVERVIEW_RESPONSE)) }),
  );
  await page.route('**/v2/admin/referral/rewards*', (route) =>
    route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope(EMPTY_LIST)) }),
  );

  let failurePayload: Record<string, unknown> | null = null;
  // 用 ** 跨路径段：列表是 /referral-withdrawals?page=…，动作是 /referral-withdrawals/7/payment-failures
  await page.route('**/v2/admin/referral-withdrawals**', (route) => {
    if (route.request().method() === 'GET') {
      const list = { list: [APPROVED_WITHDRAWAL], total: 1, page: 1, page_size: 20 };
      return route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope(list)) });
    }

    failurePayload = route.request().postDataJSON() as Record<string, unknown>;
    return route.fulfill({
      ...JSON_HEADERS,
      body: JSON.stringify(
        envelope({
          id: 7,
          status: 'completed',
          message: '已标记打款失败',
          detail: { type: 'payment_failure', withdrawal: { id: 7, status: 4 } },
        }),
      ),
    });
  });

  await page.goto('/admin/referral?tab=withdrawals', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('button', { name: '标记失败' }).first()).toBeVisible();
  await page.getByRole('button', { name: '标记失败' }).first().click();
  await expect(page.getByText('标记打款失败')).toBeVisible();

  await page
    .getByPlaceholder('打款失败原因，必填（如账号不存在、被退回）')
    .fill('支付宝退回：账号不存在');
  await page.locator('.t-dialog:visible').getByRole('button', { name: '确定' }).click();

  await expect(page.getByText('已标记打款失败，资金已退回可提现余额')).toBeVisible();
  expect(failurePayload).toEqual({ reason: '支付宝退回：账号不存在' });
  expect(errors).toEqual([]);
});
