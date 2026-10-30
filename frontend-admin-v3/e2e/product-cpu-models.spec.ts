import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * CPU 型号目录回归（审查条目 L-27）。
 *
 * 行内「绑定配置」同样是 product-binding-tree-select，需给出空商品分组列表让其短路。
 * 只验证：路由可达、分组与型号渲染、主频/睿频带 GHz 单位、无 console error。
 */
const CPU_RESPONSE = {
  list: [
    {
      id: 1,
      name: 'Intel 至强',
      sort_order: 1,
      model_count: 1,
      models: [
        {
          id: 11,
          name: 'Xeon Gold 6248',
          value: 'xeon-gold-6248',
          base_frequency: '2.5',
          turbo_frequency: '3.9',
          sort_order: 1,
          bindings: [] as Record<string, unknown>[],
          binding_ids: [] as number[],
        },
      ],
    },
  ],
};

const EMPTY_GROUP_LIST = { list: [] as Record<string, unknown>[], total: 0, page: 1, page_size: 100 };
const JSON_HEADERS = { status: 200, contentType: 'application/json' };

/** CPU 型号目录请求路径 */
const CPU_PATH = '**/v2/admin/cpu-model-catalog*';
/** 商品分组请求路径（商品树第一层，空列表即终止递归） */
const PRODUCT_GROUPS_PATH = '**/v2/admin/coupon-product-groups*';

async function mockCpuModelsPage(page: Page) {
  const cpuBody = JSON.stringify(envelope(CPU_RESPONSE));
  const groupsBody = JSON.stringify(envelope(EMPTY_GROUP_LIST));
  await page.route(CPU_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: cpuBody }));
  await page.route(PRODUCT_GROUPS_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: groupsBody }));
}

test('CPU 型号：分组与型号表渲染主频睿频', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockCpuModelsPage(page);

  await page.goto('/admin/cpu-models', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#main-content')).toBeVisible();
  await expect(page.locator('.cpu-group-item').getByText('Intel 至强', { exact: true })).toBeVisible();
  await expect(page.getByText('当前分组：Intel 至强')).toBeVisible();
  await expect(page.getByText('Xeon Gold 6248')).toBeVisible();
  await expect(page.getByText('主频 2.5GHz')).toBeVisible();
  await expect(page.getByText('睿频 3.9GHz')).toBeVisible();

  expect(errors).toEqual([]);
});
