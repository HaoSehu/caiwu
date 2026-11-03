import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 切换页面性能回归：本用例锁住三轮「切页慢」修复里最容易回退的三处行为。
 *
 * 1. 全局路由过渡：旧实现用 mode="out-in" 且离场 0.28s，每次切页内容区会先空 ~300ms。
 *    这里直接量「点击菜单后主内容区出现非空文本」的时间，阈值留足 CI 抖动余量。
 * 2. 商品树选择器懒加载：它此前在 setup 里无条件拉整棵树（1 次根分组 + 20 次子分组 +
 *    1 次批量产品）。现在只有展开下拉才请求；用户详情这类常驻弹层的页面进入时不得触发。
 * 3. keep-alive 缓存键：同一组件被多条路由复用时（商品目录/流量包/提供商共用
 *    keepAliveName=AdminProducts），缓存键必须按 keepAliveName 归并，否则每访问一条
 *    路由就多缓存一份实例、切页时整组一起重新拉数据。
 */

const JSON_HEADERS = { status: 200, contentType: 'application/json' };

const EMPTY_LIST = { list: [] as unknown[], total: 0, page: 1, page_size: 20 };

/** 记录请求次数：只关心商品树与页面主列表，避免被无关接口干扰。 */
function countRequests(page: Page) {
  const hits: string[] = [];
  page.on('request', (request) => {
    const url = request.url();
    if (/\/api\/v2\/admin\/(?:coupon-product-groups|users|products|instance-spec-catalog)/.test(url)) {
      hits.push(url.replace(/^https?:\/\/[^/]+/, '').split('?')[0]);
    }
  });
  return hits;
}

async function mockCommonApis(page: Page) {
  await mockAuthenticatedSession(page);
  // Playwright 的 page.route 是「后注册优先匹配」，因此兜底必须先注册，
  // 否则它会盖住下面所有精确 mock，让页面拿到空的列表结构。
  // 登录态与用户信息必须留给 mockAuthenticatedSession 的 handler，否则权限菜单建不起来。
  await page.route('**/v2/admin/**', (route) => {
    if (/\/auth\/info/.test(route.request().url())) return route.fallback();
    return route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope({ list: [], total: 0 })) });
  });
  const routes: Array<[RegExp, unknown]> = [
    [/\/v2\/admin\/dashboard\/stats/, {}],
    [/\/v2\/admin\/dashboard\/monthly-revenue/, {}],
    [/\/v2\/admin\/users\/\d+$/, { user: { id: 1, nickname: '测试用户' }, stats: {}, referral: {} }],
    [/\/v2\/admin\/users/, EMPTY_LIST],
    [/\/v2\/admin\/member-levels/, EMPTY_LIST],
    [/\/v2\/admin\/promotion-ambassadors/, EMPTY_LIST],
    [/\/v2\/admin\/coupon-product-groups/, EMPTY_LIST],
    [/\/v2\/admin\/product-types/, []],
    [/\/v2\/admin\/product-groups\/tree/, { tree: [] }],
    [/\/v2\/admin\/products/, EMPTY_LIST],
    [/\/v2\/admin\/settings/, { settings: {} }],
  ];
  for (const [pattern, body] of routes) {
    await page.route(pattern, (route) => route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope(body)) }));
  }
}

test('切页性能：主内容区在过渡结束后立即可见，不出现整屏空白', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockCommonApis(page);

  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#main-content')).toBeVisible();
  // 等首页数据落地，避免把「首页自身加载」算进切页耗时
  await page.waitForTimeout(1200);

  const started = Date.now();
  await page.evaluate(() => {
    const router = (document.querySelector('#app') as any).__vue_app__.config.globalProperties.$router;
    return router.push('/admin/users');
  });

  // 轮询主内容区：出现任何非空文本即视为「用户看到新页面」
  let firstContentMs = -1;
  const deadline = Date.now() + 5000;
  while (Date.now() < deadline) {
    // 用 textContent 而非 innerText：这里只判断「内容区是否已渲染出文本」，
    // 不需要触发布局计算，避免测量本身引入的开销污染首帧时间。
    const length = await page.evaluate(
      () => (document.querySelector('#main-content')?.textContent || '').trim().length,
    );
    if (length > 0) {
      firstContentMs = Date.now() - started;
      break;
    }
    await page.waitForTimeout(16);
  }

  expect(firstContentMs).toBeGreaterThanOrEqual(0);
  // 旧实现固定先空 280ms 再挂载；这里给足 250ms 仍能在 CI 上稳定区分
  expect(firstContentMs).toBeLessThan(250);
  await expect(page).toHaveURL(/\/admin\/users$/);
  expect(errors).toEqual([]);
});

test('商品树懒加载：进入用户详情不拉整棵商品树', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockCommonApis(page);
  const hits = countRequests(page);

  await page.goto('/admin/users/1?tab=services', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#main-content')).toBeVisible();
  await page.waitForTimeout(1200);

  // 新建实例弹层是常驻挂载的：即使从未打开，也不得请求商品树
  // （旧实现会在组件 setup 里无条件拉 1 次根分组 + 20 次子分组 + 1 次批量产品）
  expect(hits.filter((url) => url.includes('/coupon-product-groups'))).toEqual([]);

  expect(errors).toEqual([]);
});

test('keep-alive：同一 keepAliveName 的多条路由复用同一实例，切走不残留', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockCommonApis(page);

  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1000);

  const push = (path: string) =>
    page.evaluate(async (target) => {
      const router = (document.querySelector('#app') as any).__vue_app__.config.globalProperties.$router;
      await router.push(target);
    }, path);

  // 商品目录 / 流量包 / 提供商共用 keepAliveName=AdminProducts。
  // 缓存键若仍按 route.path 分桶，每组路径都会各留一份实例（同屏可查到多份 .products-page）。
  await push('/admin/products');
  await page.waitForTimeout(800);
  expect(await page.locator('.products-page').count()).toBe(1);

  await push('/admin/products/traffic-packages');
  await page.waitForTimeout(800);
  expect(await page.locator('.products-page').count()).toBe(1);

  // 回到目录：走缓存槽位恢复，不新增实例
  await push('/admin/products');
  await page.waitForTimeout(800);
  expect(await page.locator('.products-page').count()).toBe(1);

  expect(errors).toEqual([]);
});

test('keep-alive：参数化路由必须按 path 分实例，从商品 1 切到商品 2 要重新取数', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  // Playwright 后注册的 route 优先匹配，兜底必须先注册，否则会盖住商品详情 mock。
  await page.route('**/v2/admin/**', (route) => {
    // /auth/info 必须落回 mockAuthenticatedSession 的 handler，否则权限菜单为空、页面被判无权限
    if (/\/auth\/info/.test(route.request().url())) return route.fallback();
    const emptyPayload = { list: [] as unknown[], total: 0, tree: [] as unknown[] };
    return route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope(emptyPayload)) });
  });
  // 商品详情按 id 返回不同名称，用来判断页面是否真的换成了另一条记录。
  // 接口是 /v2/admin/products/{id}；名称需嵌套在 display.display_name（normalizeV2ProductDetail 的约定）。
  await page.route('**/v2/admin/products/*', (route) => {
    const requestUrl = route.request().url();
    const id = requestUrl.match(/products\/(\d+)/)?.[1] || '0';
    const product = { id: Number(id), display: { display_name: `商品-${id}` } };
    return route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope({ product })) });
  });

  const push = (path: string) =>
    page.evaluate(async (target) => {
      const router = (document.querySelector('#app') as any).__vue_app__.config.globalProperties.$router;
      await router.push(target);
    }, path);

  // 商品名称在 t-input 里，文本框的值不进 textContent，必须读 inputValue。
  // 页面有多个隐藏 section 的同名输入框，用「商品名称」表单项下的那个定位。
  const nameInput = () => page.locator('.t-form__item', { hasText: '商品名称' }).locator('input').first();

  await page.goto('/admin/products/1/edit', { waitUntil: 'domcontentloaded' });
  await expect(nameInput()).toHaveValue('商品-1');

  await push('/admin/products/2/edit');
  // 若共用缓存实例，这里会原样保留「商品-1」；按 path 分实例才会重新挂载并取回「商品-2」
  await expect(nameInput()).toHaveValue('商品-2');

  expect(errors).toEqual([]);
});

test('tab 组：推广页切到「奖励记录」会重新请求该 tab 数据', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  const requests: string[] = [];
  page.on('request', (request) => {
    if (/\/api\/v2\/admin\/referral/.test(request.url())) {
      requests.push(
        request
          .url()
          .replace(/^https?:\/\/[^/]+/, '')
          .split('?')[0],
      );
    }
  });
  await page.route('**/v2/admin/**', (route) => {
    // /auth/info 必须落回 mockAuthenticatedSession 的 handler，否则权限菜单为空、页面被判无权限
    if (/\/auth\/info/.test(route.request().url())) return route.fallback();
    const emptyPayload = { list: [] as unknown[], total: 0 };
    return route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope(emptyPayload)) });
  });

  const push = (path: string) =>
    page.evaluate(async (target) => {
      const router = (document.querySelector('#app') as any).__vue_app__.config.globalProperties.$router;
      await router.push(target);
    }, path);

  await page.goto('/admin/referral', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1200);
  expect(requests.length).toBeGreaterThan(0);

  // 切到同组的另一条路由：守卫若按路由名写死就会拦住，接口不会再发
  requests.length = 0;
  await push('/admin/referral/rewards');
  await page.waitForTimeout(1200);
  expect(requests.length).toBeGreaterThan(0);

  expect(errors).toEqual([]);
});
