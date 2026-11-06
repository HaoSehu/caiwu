import type { Page, Route } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 移动端（手机视口）逐页适配回归。
 *
 * 基准视口 375×812（iPhone SE / 主流安卓窄屏）与 390×844。
 * 断言只关心三件事，与后端数据无关：
 *   1. 页面根元素不产生横向溢出（scrollWidth 不超过 clientWidth）；
 *   2. 列表页在手机端不是桌面表格形态，或表格由横向滚动容器承载；
 *   3. 主要操作按钮在 375px 下可见且命中区不小于 44px。
 *
 * 新增页面适配后在此表追加路由即可，用例自动覆盖。
 */

export const MOBILE_VIEWPORT = { width: 375, height: 812 };

/**
 * 各页在手机端应声明的形态（执行计划「统一判定口径」第 9 条）。
 * 只断言「值属于枚举」在形态写反时仍会通过，因此这里逐页钉住期望值。
 */
const EXPECTED_MOBILE_VIEW: Record<string, 'card' | 'scroll' | 'detail' | 'form'> = {
  '/admin/finance/orders': 'card',
  '/admin/finance/invoices': 'card',
  '/admin/finance/recharges': 'card',
  '/admin/finance/new-customers': 'card',
  '/admin/users': 'card',
  '/admin/tickets': 'card',
  '/admin/services': 'card',
  '/admin/logs': 'card',
  '/admin/notifications': 'card',
  '/admin/content/notices': 'card',
  '/admin/content/help': 'card',
  '/admin/products': 'scroll',
  '/admin/site-info': 'form',
  '/admin/system/database': 'scroll',
  '/admin/integration-plugins': 'card',
  '/admin/member-levels': 'card',
  '/admin/coupons': 'card',
  '/admin/system/staff': 'card',
  '/admin/system/roles': 'card',
  '/admin/specs': 'scroll',
};

/** 运行时异常：pageerror 进入的是 "TypeError: ..."，控制台里的是 "Uncaught ..."，两者都要算 */
function isRuntimeError(text: string) {
  return text.includes('Uncaught') || /^(?:Type|Reference|Range|Syntax|URI)Error\b/.test(text);
}

/** 手机端与桌面端形态必须互换的列表类页面（路径取自 src/router/modules/admin/*.ts）。 */
const LIST_ROUTES: Array<{ path: string; name: string }> = [
  { path: '/admin/finance/orders', name: '订单' },
  { path: '/admin/finance/invoices', name: '账单' },
  { path: '/admin/finance/recharges', name: '充值记录' },
  { path: '/admin/finance/new-customers', name: '新客户日报' },
  { path: '/admin/users', name: '用户' },
  { path: '/admin/tickets', name: '工单' },
  { path: '/admin/services', name: '服务列表' },
  { path: '/admin/logs', name: '日志' },
  { path: '/admin/notifications', name: '通知' },
  { path: '/admin/content/notices', name: '公告管理' },
  { path: '/admin/content/help', name: '帮助管理' },
  { path: '/admin/products', name: '产品' },
  { path: '/admin/site-info', name: '站点信息' },
  { path: '/admin/system/database', name: '数据库' },
  { path: '/admin/integration-plugins', name: '集成插件' },
  { path: '/admin/member-levels', name: '会员等级' },
  { path: '/admin/coupons', name: '优惠券' },
  // 以下三页本轮新增了手机卡片列表，此前不在路由表内属于覆盖盲区
  { path: '/admin/system/staff', name: '员工' },
  { path: '/admin/system/roles', name: '角色' },
  { path: '/admin/specs', name: '实例规格' },
];

/** 通用列表接口兜底：命中后返回空列表，避免用例依赖真实后端。 */
async function mockEmptyListApis(page: Page) {
  await page.route('**/v2/admin/**', (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    if (path.endsWith('/auth/info') || path.endsWith('/dashboard/stats') || path.endsWith('/monthly-revenue')) {
      return route.fallback();
    }
    // 统一返回空集合形态：各端点读 list / items / data 任一字段均不报错
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope({ list: [], items: [], total: 0, page: 1, page_size: 20 })),
    });
  });
}

/** 读取当前文档的横向溢出量（px）。 */
function overflowOf(page: Page) {
  return page.evaluate(() => {
    const el = document.documentElement;
    return el.scrollWidth - el.clientWidth;
  });
}

test.describe('管理端手机端适配', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(MOBILE_VIEWPORT);
    await mockAuthenticatedSession(page);
    await mockEmptyListApis(page);
  });

  for (const route of LIST_ROUTES) {
    test(`${route.name}页在 375px 下无横向溢出`, async ({ page }) => {
      const errors = collectConsoleErrors(page);
      await page.goto(route.path, { waitUntil: 'domcontentloaded' });

      // 首屏渲染完成（布局根节点存在）后再判定溢出
      await expect(page.locator('#app')).toBeVisible();
      await expect.poll(() => overflowOf(page), { timeout: 10_000 }).toBeLessThanOrEqual(1);

      // pageerror 推入的是 "TypeError: ..." 这类文本，不含 "Uncaught"，
      // 只按 Uncaught 过滤会把运行时异常整类漏掉
      expect(errors.filter(isRuntimeError)).toEqual([]);
    });

    // 逐页适配契约：手机端必须显式声明形态（卡片 / 横向滚动 / 详情 / 表单），
    // 只做到「恰好不溢出」不算完成。桌面端不渲染该标记。
    test(`${route.name}页声明手机端形态`, async ({ page }) => {
      const errors = collectConsoleErrors(page);
      await page.goto(route.path, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('#app')).toBeVisible();

      const markers = page.locator('[data-mobile-view]');
      // 先确认元素存在，避免形态缺失时空等 30s 拖慢整轮回归
      await expect(markers).not.toHaveCount(0, { timeout: 5_000 });
      // 空数据时卡片容器高度可能为 0，不在本用例断言可见性；
      // 有数据时的可见性由「订单列表在手机端切换为卡片形态」覆盖。
      const value = await markers.first().getAttribute('data-mobile-view');
      // 断言本页应有的具体形态：只断言「属于枚举」在 card/scroll 写反时仍然通过
      expect(value).toBe(EXPECTED_MOBILE_VIEW[route.path]);
      expect(errors.filter(isRuntimeError)).toEqual([]);
    });

    // 反向契约：平板竖屏（768~991）与桌面都不得渲染形态标记。
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

  // 移动卡片形态只在有数据时渲染，空列表走 t-empty；因此单独 mock 一条订单。
  test('订单列表在手机端切换为卡片形态', async ({ page }) => {
    const ordersBody = envelope({
      list: [
        {
          id: 1,
          order_no: 'E2E-MOBILE-0001',
          user: { nickname: '测试用户', email: 'e2e@example.com' },
          product_name: '云服务器 E2E',
          type: 'normal',
          paid_amount: '1280.00',
          billing_cycle: 'monthly',
          quantity: 1,
          status: 'paid',
          paid_at: '2026-11-06 10:05:00',
          created_at: '2026-11-06 10:00:00',
        },
      ],
      total: 1,
      page: 1,
      page_size: 20,
    });
    const fulfillOrders = (route: Route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(ordersBody),
      });
    await page.route('**/v2/admin/orders?*', fulfillOrders);

    await page.goto('/admin/finance/orders', { waitUntil: 'domcontentloaded' });

    await expect(page.locator('.order-mobile-list')).toBeVisible();
    await expect(page.locator('.order-mobile-list .mobile-record-card')).toHaveCount(1);
    // 桌面表格在手机端必须隐藏，不能两套并存
    await expect(page.locator('.table-scroll')).toHaveCount(0);
  });

  test('手机端侧栏以抽屉形态展开并可关闭', async ({ page }) => {
    await page.goto('/admin/finance/orders', { waitUntil: 'domcontentloaded' });

    const mask = page.locator('.tdesign-starter-side-nav-mask');
    await expect(mask).toHaveCount(0);

    await page.locator('[aria-label="展开菜单"]').first().click();
    await expect(page.locator('.tdesign-starter-sidebar-mobile-open')).toBeVisible();

    await mask.first().click({ force: true });
    await expect(page.locator('.tdesign-starter-sidebar-mobile-open')).toHaveCount(0);
  });

  test('手机端主要操作按钮命中区不小于 44px', async ({ page }) => {
    await page.goto('/admin/finance/orders', { waitUntil: 'domcontentloaded' });

    // 取筛选区「查询」按钮：可见、非弹层内按钮，代表工具栏操作
    const primary = page.locator('.filter-actions .t-button--theme-primary').first();
    await expect(primary).toBeVisible();
    const box = await primary.boundingBox();
    expect(box?.height ?? 0).toBeGreaterThanOrEqual(43);
  });
});
