import type { Page } from '@playwright/test';

import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 推荐页（referral）回归：金额千分位复用 shared 实现（docs/DESIGN.md §6），空态文案按 §6 用语表。
// 此前该页没有任何 e2e 覆盖，千分位与空态修复都没有回归网（审查条目 P2-3）。

const OVERVIEW_RESPONSE = {
  referral_link: 'https://example.com/client/register?ref=E2E',
  reward_rate: '10',
  renewal_reward_rate: '5',
  withdraw_min_amount: '20',
  referral_available_amount: '1234567.8',
  referral_frozen_amount: '1234.5',
  total_reward_amount: '9876.54',
  referral_withdrawn_amount: '1000',
  direct_referral_count: 1,
  rewarded_orders_count: 3,
  current_ambassador: { name: '金牌大使', reward_rate: '10', renewal_reward_rate: '5' },
};

const DIRECT_REFERRALS = {
  list: [
    {
      id: 1,
      display_name: '测试被邀请人',
      email: 'invitee@example.com',
      referred_at: '2026-01-01 10:00:00',
      customer_consumption: '9876.5',
      my_earnings: '987.65',
    },
  ],
  total: 1,
  page: 1,
  page_size: 10,
};

// 空列表响应：list 必须显式标注类型，否则 noImplicitAny 会把 [] 推断成 any[]（TS7018）
const EMPTY_LIST = { list: [] as Record<string, unknown>[], total: 0, page: 1, page_size: 10 };
const JSON_BODY = { status: 200, contentType: 'application/json' };

/** 页面挂载时并行拉概览、四张列表与支付宝账号，全部 mock 掉，避免未匹配请求污染 console。 */
async function mockReferralPage(page: Page, directReferrals: unknown = DIRECT_REFERRALS) {
  const endpoints: [string, unknown][] = [
    ['**/api/v2/client/referral/overview*', OVERVIEW_RESPONSE],
    ['**/api/v2/client/referral/rewards*', EMPTY_LIST],
    ['**/api/v2/client/referral/account-logs*', EMPTY_LIST],
    ['**/api/v2/client/referral/withdrawals*', EMPTY_LIST],
    ['**/api/v2/client/referral/direct-referrals*', directReferrals],
    ['**/api/v2/client/auth/alipay-account*', { is_bound: false, account: '' }],
  ];
  for (const [endpoint, data] of endpoints) {
    await page.route(endpoint, (route) => route.fulfill({ ...JSON_BODY, body: JSON.stringify(envelope(data)) }));
  }
}

test('推广大使：金额带千分位，与 admin 同口径', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockReferralPage(page);
  await page.goto('/client/referral', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('.referral-stats strong').first()).toHaveText('¥1,234,567.80');
  await expect(page.getByText('测试被邀请人')).toBeVisible();
  await expect(page.locator('.referral-money').first()).toHaveText('¥9,876.50');
});

test('推广大使：被邀请人为空时展示空态文案', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockReferralPage(page, EMPTY_LIST);
  await page.goto('/client/referral', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('暂无被邀请用户，复制上方推广链接邀请好友')).toBeVisible();
});
