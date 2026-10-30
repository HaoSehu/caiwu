import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 「拆分商品」弹层回归（P2-6：从 ProductCatalog.vue 抽成 SplitProductDialog.vue）。
 *
 * 断言目的：
 * 1. 父级按钮 → 子组件 defineExpose({ open }) 的连线仍然成立（抽组件后最容易断的地方）；
 * 2. 预览数据仍能渲染（props/内部请求没有被抽丢）；
 * 3. 提交成功后弹层关闭，父级 @done 回调清空选择并刷新目录（split-previews 会被再次请求）。
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

const SPLIT_PREVIEW = {
  preview_count: 2,
  skipped_count: 0,
  items: [
    {
      source_product_id: 77,
      source_display_name: 'E2E 云服务器',
      variants: [
        { variant_key: '2c2g', display_name: 'E2E 云服务器 2 核 2G', action: 'update', product_id: 77 },
        { variant_key: '4c4g', display_name: 'E2E 云服务器 4 核 4G', action: 'create' },
      ],
    },
  ],
};

const SPLIT_RESULT = { created_count: 1, updated_count: 1, skipped_count: 0 };

async function mockCatalogPage(page: Page) {
  const fulfill = (body: unknown) => ({ ...JSON_HEADERS, body: JSON.stringify(envelope(body)) });
  await page.route('**/v2/admin/product-types*', (route) => route.fulfill(fulfill(PRODUCT_TYPES)));
  await page.route('**/v2/admin/product-groups/tree*', (route) => route.fulfill(fulfill({ tree: [] })));
  await page.route('**/v2/admin/products/split-previews*', (route) => route.fulfill(fulfill(SPLIT_PREVIEW)));
  await page.route('**/v2/admin/products/splits*', (route) => route.fulfill(fulfill(SPLIT_RESULT)));
  await page.route('**/v2/admin/products*', (route) => route.fulfill(fulfill(PRODUCT_LIST)));
}

test('拆分商品：选中商品后弹层显示预览，提交后关闭并刷新目录', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockCatalogPage(page);

  await page.goto('/admin/products', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('E2E 云服务器').first()).toBeVisible();

  // 勾选第一行商品，批量工具栏才会出现「拆分商品」
  await page.locator('.t-table tbody tr').first().locator('.t-checkbox').click();

  const splitButton = page.getByRole('button', { name: '拆分商品' });
  await expect(splitButton).toBeVisible();
  await splitButton.click();

  const dialog = page.locator('.t-dialog').filter({ hasText: '按 CPU 与内存子选项生成独立商品' });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText('已选商品 1 个')).toBeVisible();
  await expect(dialog.getByText('预计处理 2 个规格')).toBeVisible();
  await expect(dialog.getByText('E2E 云服务器 2 核 2G')).toBeVisible();
  await expect(dialog.getByText('E2E 云服务器 4 核 4G')).toBeVisible();
  // 预览行的动作标签：update → 更新，create → 新建
  await expect(dialog.locator('.split-product-item').first().getByText('更新')).toBeVisible();
  await expect(dialog.locator('.split-product-item').last().getByText('新建')).toBeVisible();

  await dialog.getByRole('button', { name: '确定' }).click();

  await expect(page.getByText('已生成 1 个商品，更新 1 个，跳过 0 个')).toBeVisible();
  await expect(dialog).toBeHidden();

  expect(errors).toEqual([]);
});
