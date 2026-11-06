import type { Page, Route } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 控制台移动端（手机视口）逐页适配回归。
 *
 * 与 docs/执行计划/进行中/三端手机端适配与自适应-2026-11-06.md 的「移动形态标记」契约配套：
 * 手机端主容器必须带 data-mobile-view（card / scroll / detail / form），
 * 用它判定页面是否真的做了手机形态，而不是只恰好没有溢出。
 */

export const MOBILE_VIEWPORT = { width: 375, height: 812 };

/**
 * 各页在手机端应声明的形态（执行计划「统一判定口径」第 9 条）：
 * 列表页 card、以表格横向滚动承载的页 scroll、详情 detail、表单 form。
 * 只断言「值属于枚举」在形态写反时仍会通过，因此这里逐页钉住期望值。
 */
const EXPECTED_MOBILE_VIEW: Record<string, 'card' | 'scroll' | 'detail' | 'form'> = {
  '/client/dashboard': 'card',
  '/client/services': 'card',
  '/client/catalog': 'card',
  '/client/orders': 'card',
  '/client/invoices': 'card',
  '/client/recharge': 'form',
  '/client/payments': 'card',
  '/client/coupons': 'card',
  '/client/referral': 'scroll',
  '/client/tickets': 'card',
  '/client/notices': 'card',
  '/client/help': 'card',
  '/client/verification': 'form',
  '/client/profile': 'form',
};

/** 运行时异常：pageerror 进入的是 "TypeError: ..."，控制台里的是 "Uncaught ..."，两者都要算 */
function isRuntimeError(text: string) {
  return text.includes('Uncaught') || /^(?:Type|Reference|Range|Syntax|URI)Error\b/.test(text);
}

const ROUTES: Array<{ path: string; name: string }> = [
  { path: '/client/dashboard', name: '仪表盘' },
  { path: '/client/services', name: '服务管理' },
  { path: '/client/catalog', name: '商品目录' },
  { path: '/client/orders', name: '订单' },
  { path: '/client/invoices', name: '账单' },
  { path: '/client/recharge', name: '充值' },
  { path: '/client/payments', name: '支付记录' },
  { path: '/client/coupons', name: '优惠券' },
  { path: '/client/referral', name: '推广' },
  { path: '/client/tickets', name: '工单' },
  { path: '/client/notices', name: '公告' },
  { path: '/client/help', name: '帮助中心' },
  { path: '/client/verification', name: '实名认证' },
  { path: '/client/profile', name: '个人资料' },
];

/** 通用接口兜底：返回空集合，避免依赖真实后端。 */
async function mockFallbackApis(page: Page) {
  await page.route('**/api/v2/client/**', (route) => {
    const path = new URL(route.request().url()).pathname;
    if (path.endsWith('/auth/info')) return route.fallback();
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope({ list: [], items: [], total: 0, page: 1, page_size: 20, data: [] })),
    });
  });
}

function overflowOf(page: Page) {
  return page.evaluate(() => {
    const el = document.documentElement;
    return el.scrollWidth - el.clientWidth;
  });
}

test.describe('控制台手机端适配', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(MOBILE_VIEWPORT);
    await mockAuthenticatedSession(page);
    await mockFallbackApis(page);
  });

  for (const route of ROUTES) {
    test(`${route.name}页在 375px 下无横向溢出`, async ({ page }) => {
      const errors = collectConsoleErrors(page);
      await page.goto(route.path, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('#app')).toBeVisible();
      await expect.poll(() => overflowOf(page), { timeout: 10_000 }).toBeLessThanOrEqual(1);
      // pageerror 推入的是 "TypeError: ..." 这类文本，不含 "Uncaught"，
      // 只按 Uncaught 过滤会把真实的运行时异常整类漏掉
      expect(errors.filter(isRuntimeError)).toEqual([]);
    });

    test(`${route.name}页声明手机端形态`, async ({ page }) => {
      const errors = collectConsoleErrors(page);
      await page.goto(route.path, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('#app')).toBeVisible();

      const markers = page.locator('[data-mobile-view]');
      await expect(markers).not.toHaveCount(0, { timeout: 5_000 });
      // 注意：空数据时卡片容器可能高度为 0，这里只要求标记不落在被 CSS
      // display:none 隐藏的节点上；有数据时的可见性由各端「切换为卡片形态」用例覆盖。
      const value = await markers.first().getAttribute('data-mobile-view');
      // 断言本页应有的具体形态，而不是「属于枚举即可」：
      // 后者在 card/form 写反时依旧通过
      expect(value).toBe(EXPECTED_MOBILE_VIEW[route.path]);
      expect(errors.filter(isRuntimeError)).toEqual([]);
    });

    // 反向契约：平板竖屏（768~991）与桌面都不能渲染形态标记。
    // 只测 1280 会漏掉用 isDesktop(<992) 判定标记的页面在平板区间泄漏。
    for (const width of [820, 1280]) {
      test(`${route.name}页在 ${width}px 不渲染形态标记`, async ({ page }) => {
        await page.setViewportSize({ width, height: 800 });
        await page.goto(route.path, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('#app')).toBeVisible();
        await expect(page.locator('[data-mobile-view]')).toHaveCount(0);
      });
    }
  }

  test('手机端侧栏以抽屉形态展开', async ({ page }) => {
    await page.goto('/client/orders', { waitUntil: 'domcontentloaded' });

    await page.locator('[aria-label="打开菜单"]').first().click();
    await expect(page.locator('.tdesign-starter-sidebar-mobile-open')).toBeVisible();
    await expect(page.locator('.tdesign-starter-side-nav-mask')).toBeVisible();
  });

  test('手机端财务列表切换为卡片形态', async ({ page }) => {
    const invoicesBody = envelope({
      list: [
        {
          id: 1,
          invoice_no: 'INV-E2E-0001',
          amount: '1280.00',
          paid_amount: '1280.00',
          status: 'unpaid',
          created_at: '2026-11-06 10:00:00',
          due_date: '2026-11-13',
        },
      ],
      total: 1,
      page: 1,
      page_size: 20,
    });
    const fulfillInvoices = (route: Route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(invoicesBody),
      });
    await page.route('**/api/v2/client/invoices?*', fulfillInvoices);

    await page.goto('/client/invoices', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.record-mobile-list')).toBeVisible();
    await expect(page.locator('.record-mobile-card')).toHaveCount(1);
  });
});
