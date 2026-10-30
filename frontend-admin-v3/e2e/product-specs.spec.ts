import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 实例规格目录回归（审查条目 L-27）。
 *
 * 规格行内嵌「绑定配置」的 product-binding-tree-select，组件挂载即拉商品树
 * （src/hooks/useProductBindingTree.ts 的 loadTree → adminApi.coupons.productTree），
 * 因此除实例规格目录外还必须给出空商品分组列表让其一次终止，否则未匹配请求会由
 * preview 返回 404 HTML 并被记成 console error。
 *
 * 只验证：路由可达、规格文本/别名/说明/状态渲染、无 console error。
 */
const SPECS_RESPONSE = {
  list: [
    {
      id: 1,
      text: 'ecs.g9i.2c2g',
      alias: '2 核 2G',
      note: '入门款实例规格',
      status: '展示中',
      sort_order: 1,
      bindings: [] as Record<string, unknown>[],
      binding_ids: [] as number[],
    },
  ],
};

const EMPTY_GROUP_LIST = { list: [] as Record<string, unknown>[], total: 0, page: 1, page_size: 100 };
const JSON_HEADERS = { status: 200, contentType: 'application/json' };

/** 规格目录请求路径 */
const SPECS_PATH = '**/v2/admin/instance-spec-catalog*';
/** 商品分组请求路径（商品树第一层，空列表即终止递归） */
const PRODUCT_GROUPS_PATH = '**/v2/admin/coupon-product-groups*';

async function mockSpecsPage(page: Page) {
  const specsBody = JSON.stringify(envelope(SPECS_RESPONSE));
  const groupsBody = JSON.stringify(envelope(EMPTY_GROUP_LIST));
  await page.route(SPECS_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: specsBody }));
  // 商品树：空根分组即短路，不会递归 children 也不会批量拉产品
  await page.route(PRODUCT_GROUPS_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: groupsBody }));
}

test('实例规格：目录表渲染规格文本、别名与说明', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockSpecsPage(page);

  await page.goto('/admin/specs', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#main-content')).toBeVisible();
  await expect(page.getByText('ecs.g9i.2c2g')).toBeVisible();
  await expect(page.getByText('2 核 2G')).toBeVisible();
  await expect(page.getByText('入门款实例规格')).toBeVisible();
  await expect(page.getByText('展示中')).toBeVisible();
  await expect(page.getByText('规格目录会统一保存到后台设置中，绑定配置仅保存关联信息。')).toBeVisible();

  expect(errors).toEqual([]);
});
