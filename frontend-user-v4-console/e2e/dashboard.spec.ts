import type { Page, Route } from '@playwright/test';

import { E2E_SITE_CONFIG, E2E_USER_INFO, envelope, expect, seedClientSession, test } from './fixtures';

// 控制台首屏（/client/dashboard）回归：此前该页没有任何 e2e 覆盖，两项首屏优化都没有回归网。
// - 重复请求：路由守卫完成 userInfo 水合后，页面必须复用同一份结果，
//   整页加载只应命中一次 /v2/client/auth/info（改前守卫 + 页面各拉一次，共 2 次）。
// - 首屏渲染：整页 loading 遮罩已拆为分区骨架，壳层与各数据区独立就绪，
//   慢接口未返回时页面壳层与骨架必须已经可见，而不是整页空白。
// 接口全部 mock，不依赖本地后端。

const JSON_BODY = { status: 200, contentType: 'application/json' };

const NOTICES_RESPONSE = {
  list: [{ id: 11, title: '控制台首屏公告', publish_at: '2026-01-01 10:00:00', created_at: '2026-01-01 10:00:00' }],
  total: 1,
  page: 1,
  page_size: 5,
};

// 空列表：list 必须显式标注类型，否则 noImplicitAny 会把 [] 推断成 any[]（TS7018）
const EMPTY_LIST = { list: [] as Record<string, unknown>[], total: 0, page: 1, page_size: 100 };

const LEDGER_RESPONSE = {
  summary: { cash_balance: '1000.00', recharge_in: '120.00', invoice_payment_out: '45.60', unpaid_count: 2 },
};

const COUPON_SUMMARY_RESPONSE = { total: 5, available: 3, used_up: 2, expired: 0 };

const SERVICE_OVERVIEW_RESPONSE = {
  total: 1,
  category_total: 1,
  list: [{ key: 'cloud_server', title: '云服务器', active_count: 2, expiring_count: 1 }],
  catalog_types: [] as Record<string, unknown>[],
};

// available_coupons 小于优惠券汇总的 3，用于确认「仅在更大时覆盖」的分支不会被误触发
const REFERRAL_OVERVIEW_RESPONSE = { available_coupons: 0 };

const UNREAD_COUNT_RESPONSE = { count: 3 };

function jsonHandler(data: unknown, options: { shouldFail?: () => boolean; delayMs?: number } = {}) {
  return async (route: Route) => {
    if (options.delayMs) {
      await new Promise((resolve) => setTimeout(resolve, options.delayMs));
    }
    if (options.shouldFail?.()) {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ code: 500, message: '服务器开小差了', data: null }),
      });
      return;
    }
    await route.fulfill({ ...JSON_BODY, body: JSON.stringify(envelope(data)) });
  };
}

interface DashboardMockOptions {
  /** 首屏六个接口是否整体失败；返回 false 即恢复成功响应（供错误态重试用例翻转）。 */
  failFirstScreen?: () => boolean;
  /** 每次命中 /v2/client/auth/info 时回调，用于断言重复请求。 */
  onAuthInfo?: () => void;
  /** 产品概览接口的延迟毫秒数，用于观察产品区骨架。 */
  slowGroupedOverviewMs?: number;
  /** 推荐概览接口的延迟毫秒数，用于观察指标卡骨架（summaryReady 等六个接口全部落定）。 */
  slowReferralMs?: number;
}

/** 页面挂载时首屏六接口 + 次屏四接口全量 mock，避免未匹配请求污染 console 与断言。 */
async function mockDashboard(page: Page, options: DashboardMockOptions = {}) {
  const authHandler = jsonHandler(E2E_USER_INFO);
  await page.route('**/api/v2/client/auth/info', async (route) => {
    options.onAuthInfo?.();
    await authHandler(route);
  });
  await page.route('**/api/v2/site/config*', jsonHandler(E2E_SITE_CONFIG));

  await page.route(
    /\/api\/v2\/client\/notices(\?|$)/,
    jsonHandler(NOTICES_RESPONSE, { shouldFail: options.failFirstScreen }),
  );
  await page.route(/\/api\/v2\/client\/notices\/unread-count/, jsonHandler(UNREAD_COUNT_RESPONSE));
  await page.route(
    /\/api\/v2\/client\/tickets(\?|$)/,
    jsonHandler(EMPTY_LIST, { shouldFail: options.failFirstScreen }),
  );
  await page.route(
    /\/api\/v2\/client\/ledger(\?|$)/,
    jsonHandler(LEDGER_RESPONSE, { shouldFail: options.failFirstScreen }),
  );
  await page.route(
    /\/api\/v2\/client\/coupons\/summary(\?|$)/,
    jsonHandler(COUPON_SUMMARY_RESPONSE, { shouldFail: options.failFirstScreen }),
  );
  await page.route(
    /\/api\/v2\/client\/services\/grouped-overview(\?|$)/,
    jsonHandler(SERVICE_OVERVIEW_RESPONSE, {
      shouldFail: options.failFirstScreen,
      delayMs: options.slowGroupedOverviewMs,
    }),
  );
  await page.route(
    /\/api\/v2\/client\/referral\/overview(\?|$)/,
    jsonHandler(REFERRAL_OVERVIEW_RESPONSE, {
      shouldFail: options.failFirstScreen,
      delayMs: options.slowReferralMs,
    }),
  );

  await page.route(/\/api\/v2\/client\/help-articles(\?|$)/, jsonHandler(EMPTY_LIST));
  await page.route(/\/api\/v2\/client\/balance-logs(\?|$)/, jsonHandler(EMPTY_LIST));
  await page.route(/\/api\/v2\/client\/invoices(\?|$)/, jsonHandler(EMPTY_LIST));
}

test('控制台首屏：整页加载只请求一次 /v2/client/auth/info', async ({ page }) => {
  let authInfoHits = 0;
  await seedClientSession(page);
  await mockDashboard(page, {
    onAuthInfo: () => {
      authInfoHits += 1;
    },
  });

  await page.goto('/client/dashboard', { waitUntil: 'domcontentloaded' });

  // 首屏数据与次屏请求全部落定后再断言，避免请求仍在途时提前通过
  await expect(page.getByText('控制台首屏公告')).toBeVisible();
  await expect(page.locator('.metric-card__value').first()).toBeVisible();
  await page.waitForLoadState('networkidle');

  expect(authInfoHits).toBe(1);
});

test('控制台首屏：慢接口未返回时壳层与分区骨架已渲染', async ({ page }) => {
  await seedClientSession(page);
  await mockDashboard(page, { slowGroupedOverviewMs: 6000, slowReferralMs: 6000 });

  await page.goto('/client/dashboard', { waitUntil: 'domcontentloaded' });

  // 改前整页 loading 遮罩会把这些静态壳层一起挡住
  await expect(page.getByText('我的产品', { exact: true })).toBeVisible();
  await expect(page.getByText('消息中心', { exact: true })).toBeVisible();
  await expect(page.getByText('待办事项', { exact: true })).toBeVisible();
  await expect(page.getByText('快捷入口', { exact: true })).toBeVisible();

  // 慢接口仍在途：指标卡与产品区应显示骨架，而不是真实数值
  await expect(page.locator('.metric-card__skeleton')).toHaveCount(4);
  await expect(page.locator('.product-tile-skeleton')).toHaveCount(6);
  await expect(page.locator('.metric-card__value')).toHaveCount(0);

  // 慢接口返回后骨架消失、数值接管
  await expect(page.locator('.metric-card__value').first()).toBeVisible({ timeout: 20_000 });
  await expect(page.locator('.metric-card__skeleton')).toHaveCount(0);
  await expect(page.locator('.product-tile-skeleton')).toHaveCount(0);
});

test('控制台首屏：核心接口全部失败时展示可重试错误态', async ({ page }) => {
  let failing = true;
  await seedClientSession(page);
  await mockDashboard(page, { failFirstScreen: () => failing });

  await page.goto('/client/dashboard', { waitUntil: 'domcontentloaded' });

  const retry = page.getByRole('button', { name: '重新加载' });
  await expect(retry).toBeVisible();
  await expect(page.getByText('控制台数据加载失败，请重试')).toBeVisible();
  await expect(page.locator('.metric-card__skeleton')).toHaveCount(0);

  // 翻转后重试，应恢复为首屏数据
  failing = false;
  await retry.click();
  await expect(page.getByText('控制台首屏公告')).toBeVisible();
  await expect(page.locator('.metric-card__value').first()).toHaveText('¥1,000.00');
  await expect(retry).toBeHidden();
});
