import type { Page, Route } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockDashboard, test } from './fixtures';

/**
 * 多标签（tabs-router）回归：审查 M2 与 L1 的验收用例。
 *
 * 为什么必须用「渲染出来的标签」做相邻计算：
 * store 的 tabRouterList 会保留 meta.keepAlive === false 的路由（如 /admin/system/database），
 * 但这些标签的 isAlive 恒为 false，LayoutContent 的 computed 会把它们过滤掉、不渲染任何标签。
 * 于是「v-for 的下标」与「store 下标」不对齐，任何按下标取邻居的写法都会把用户带到
 * 一个**没有标签的页面**上——这条用例锁住的就是这个行为。
 */
const JSON_HEADERS = { status: 200, contentType: 'application/json' };

const MEMBER_LEVELS_RESPONSE = {
  list: [
    {
      id: 1,
      name: '金牌会员',
      status: 1,
      remark: '年均消费满 10 万',
      updated_at: '2026-04-01 10:00:00',
    },
  ],
};

const AMBASSADORS_RESPONSE = {
  list: [
    {
      id: 1,
      name: '金牌大使',
      reward_rate: '10',
      renewal_reward_rate: '5',
      status: 1,
      remark: '年度合作',
      updated_at: '2026-04-01 10:00:00',
    },
  ],
};

const DATABASE_RESPONSE: Record<string, unknown> = {
  database: 'caiwu',
  list: [{ name: 'orders', rows: 12, size_mb: 0.5, update_time: '2026-04-01 10:00:00' }],
  total_count: 1,
  total_rows: 12,
  total_size_mb: 0.5,
  optimization: {
    candidate_count: 0,
    estimated_reclaimable_mb: 0,
    candidates: [],
    cooldown_remaining_seconds: 0,
    last_optimized_at: null,
  },
};

/** 记录 member-levels 接口被调用次数，用于验证「刷新」真的重挂了页面而不是空转。 */
interface RequestCounter {
  count: number;
}

async function mockTabsApis(page: Page, counter: RequestCounter) {
  const levelsBody = JSON.stringify(envelope(MEMBER_LEVELS_RESPONSE));
  const ambassadorsBody = JSON.stringify(envelope(AMBASSADORS_RESPONSE));
  const databaseBody = JSON.stringify(envelope(DATABASE_RESPONSE));
  await mockDashboard(page);
  await page.route('**/v2/admin/member-levels*', (route) => {
    counter.count += 1;
    return route.fulfill({ ...JSON_HEADERS, body: levelsBody });
  });
  const ambassadorsRoute = (route: Route) => route.fulfill({ ...JSON_HEADERS, body: ambassadorsBody });
  const databaseRoute = (route: Route) => route.fulfill({ ...JSON_HEADERS, body: databaseBody });
  await page.route('**/v2/admin/promotion-ambassadors*', ambassadorsRoute);
  await page.route('**/v2/admin/database/status*', databaseRoute);
}

const tabsNav = (page: Page) => page.locator('.tdesign-starter-layout-tabs-nav .t-tabs__nav-item');

/** 按标签可见文案取标签容器（首页标签只有图标，无文案）。 */
const tabByText = (page: Page, text: string) => tabsNav(page).filter({ hasText: text });

test('多标签：路由逐个生成标签，点击标签可切回且不新增标签', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  const counter: RequestCounter = { count: 0 };
  await mockTabsApis(page, counter);

  await page.goto('/admin/member-levels', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('金牌会员')).toBeVisible();
  // 首页常驻标签 + 会员等级
  await expect(tabsNav(page)).toHaveCount(2);

  await page.goto('/admin/promotion-ambassadors', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('金牌大使')).toBeVisible();
  await expect(tabsNav(page)).toHaveCount(3);

  // 点回会员等级：应复用已有标签（数量不变）并切回该页
  await tabByText(page, '会员等级').click();
  await expect(page).toHaveURL(/\/admin\/member-levels$/);
  await expect(page.getByText('金牌会员')).toBeVisible();
  await expect(tabsNav(page)).toHaveCount(3);

  expect(errors).toEqual([]);
});

test('多标签：刷新菜单会重挂页面并重新请求数据，URL 与标签数不变', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  const counter: RequestCounter = { count: 0 };
  await mockTabsApis(page, counter);

  await page.goto('/admin/member-levels', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('金牌会员')).toBeVisible();
  const before = counter.count;

  await tabByText(page, '会员等级').locator('.tab-item-more').click();
  await page.locator('.t-dropdown__item-text').filter({ hasText: '刷新' }).click();

  await expect.poll(() => counter.count).toBeGreaterThan(before);
  await expect(page).toHaveURL(/\/admin\/member-levels$/);
  await expect(page.getByText('金牌会员')).toBeVisible();
  await expect(tabsNav(page)).toHaveCount(2);

  expect(errors).toEqual([]);
});

test('多标签：不保活路由不产生标签，关闭当前标签落到可见的相邻标签', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  const counter: RequestCounter = { count: 0 };
  await mockTabsApis(page, counter);

  await page.goto('/admin/member-levels', { waitUntil: 'domcontentloaded' });
  await expect(tabsNav(page)).toHaveCount(2);

  // meta.keepAlive === false：会进 store 的 tabRouterList，但不渲染标签
  await page.goto('/admin/system/database', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('[aria-label="数据表列表"]')).toBeVisible();
  await expect(tabsNav(page)).toHaveCount(2);

  // 回到会员等级后关闭它：相邻标签必须是渲染出来的首页标签，而不是那个不可见的数据库标签
  await tabByText(page, '会员等级').click();
  await expect(page).toHaveURL(/\/admin\/member-levels$/);

  await tabByText(page, '会员等级').locator('.remove-btn').click();

  await expect(page).toHaveURL(/\/admin\/dashboard$/);
  await expect(tabsNav(page)).toHaveCount(1);
  await expect(page.locator('#main-content')).toBeVisible();

  expect(errors).toEqual([]);
});
