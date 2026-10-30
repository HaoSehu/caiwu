import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 「批量归类」弹层回归（P2-6：从 ProductCatalog.vue 抽成 BatchCategoryDialog.vue）。
 *
 * 断言目的：
 * 1. 父级按钮 → 子组件 defineExpose({ open }) 的连线仍然成立；
 * 2. 可选分类仍按商品类型收窄（父级传 selectableProductGroupOptions，不是全量分类）；
 * 3. 未选分类时被子组件校验拦下，选中后提交成功并关闭弹层。
 */
const JSON_HEADERS = { status: 200, contentType: 'application/json' };

const PRODUCT_TYPES = [{ value: 'cloud', label: '云服务器' }];

const PRODUCT_LIST = {
  list: [
    {
      id: 77,
      name: 'E2E 云服务器',
      display_name: 'E2E 云服务器',
      effective_product_group_full_name: '云服务器 / 计算型',
      monthly_price: '99.00',
      stock: 10,
      status: 1,
      services_count: 2,
      product_type: 'cloud',
    },
  ],
  total: 1,
  page: 1,
  page_size: 20,
};

const CATEGORY_TREE = {
  tree: [
    {
      id: 1,
      name: '云服务器',
      label: '云服务器',
      level: 1,
      effective_product_group_level: 1,
      effective_product_group_id: 1,
      first_product_group_code: 'cloud',
      product_type: 'cloud',
      children: [
        {
          id: 11,
          name: '计算型',
          label: '计算型',
          level: 2,
          effective_product_group_level: 2,
          effective_product_group_id: 11,
          first_product_group_code: 'cloud',
          product_type: 'cloud',
          children_count: 0,
          children: [] as unknown[],
        },
      ],
    },
  ],
};

async function mockCatalogPage(page: Page) {
  const fulfill = (body: unknown) => ({ ...JSON_HEADERS, body: JSON.stringify(envelope(body)) });
  await page.route('**/v2/admin/product-types*', (route) => route.fulfill(fulfill(PRODUCT_TYPES)));
  await page.route('**/v2/admin/product-groups/tree*', (route) => route.fulfill(fulfill(CATEGORY_TREE)));
  const batchBody = fulfill({ updated_count: 1, target_effective_product_group_name: '计算型' });
  await page.route('**/v2/admin/products/category-batches*', (route) => route.fulfill(batchBody));
  await page.route('**/v2/admin/products*', (route) => route.fulfill(fulfill(PRODUCT_LIST)));
}

test('批量归类：弹层打开、未选分类被拦、选分类后提交成功', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockCatalogPage(page);

  await page.goto('/admin/products', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('E2E 云服务器').first()).toBeVisible();

  await page.locator('.t-table tbody tr').first().locator('.t-checkbox').click();

  const entry = page.getByRole('button', { name: '批量归类' });
  await expect(entry).toBeVisible();
  await entry.click();

  const dialog = page.locator('.t-dialog').filter({ hasText: '批量归类' });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText('将 1 个商品移动到目标分类。')).toBeVisible();

  // 未选分类直接确定：应被拦下且弹层仍在
  await dialog.getByRole('button', { name: '确定' }).click();
  await expect(page.getByText('请选择目标分类')).toBeVisible();
  await expect(dialog).toBeVisible();

  // 下拉是 attach 到 body 的浮层，必须限定在 .t-select__dropdown 内取选项，
  // 否则会命中左侧分类树里同名的「计算型」节点。
  await dialog.locator('.t-select').click();
  const option = page.locator('.t-select__dropdown .t-select-option').filter({ hasText: '计算型' }).first();
  await expect(option).toBeVisible();
  await option.click();

  await dialog.getByRole('button', { name: '确定' }).click();

  await expect(page.getByText('已将 1 个商品移入「计算型」')).toBeVisible();
  await expect(dialog).toBeHidden();

  expect(errors).toEqual([]);
});
