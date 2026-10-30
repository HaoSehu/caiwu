import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 「批量主机名规则」弹层回归（P2-6：从 ProductCatalog.vue 抽成 ProvisionHostnameDialog.vue）。
 *
 * 断言目的：
 * 1. 父级按钮 → 子组件 defineExpose({ open }) 的连线仍然成立；
 * 2. 子组件内部的模式切换与预览文案仍随表单响应（scoped 化后最易丢的响应式）；
 * 3. 非 system 模式缺值时被子组件校验拦下（不发请求），补值后提交成功并关闭弹层。
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
      provision_hostname: { mode: 'system', value: '', length: 12 },
    },
  ],
  total: 1,
  page: 1,
  page_size: 20,
};

async function mockCatalogPage(page: Page) {
  const fulfill = (body: unknown) => ({ ...JSON_HEADERS, body: JSON.stringify(envelope(body)) });
  await page.route('**/v2/admin/product-types*', (route) => route.fulfill(fulfill(PRODUCT_TYPES)));
  await page.route('**/v2/admin/product-groups/tree*', (route) => route.fulfill(fulfill({ tree: [] })));
  const batchBody = fulfill({ updated_count: 1 });
  await page.route('**/v2/admin/products/provision-hostname-batches*', (route) => route.fulfill(batchBody));
  await page.route('**/v2/admin/products*', (route) => route.fulfill(fulfill(PRODUCT_LIST)));
}

test('批量主机名：弹层打开、切到固定主机名后校验拦截、补值提交成功', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockCatalogPage(page);

  await page.goto('/admin/products', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('E2E 云服务器').first()).toBeVisible();

  await page.locator('.t-table tbody tr').first().locator('.t-checkbox').click();

  const entry = page.getByRole('button', { name: '批量主机名' });
  await expect(entry).toBeVisible();
  await entry.click();

  const dialog = page.locator('.t-dialog').filter({ hasText: '设置商品开通主机名' });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText('已选商品 1 个')).toBeVisible();
  // 首行规则是 system，预览文案应给默认口径
  await expect(dialog.getByText('默认优先按上游商品规则生成')).toBeVisible();

  await dialog.getByText('固定主机名', { exact: true }).click();
  await expect(dialog.getByText('固定主机名模式需要填写一个上游允许的 host 值。')).toBeVisible();

  // 缺值直接提交：子组件内部校验应拦截，且不发请求
  await dialog.getByRole('button', { name: '保存规则' }).click();
  await expect(page.getByText('请输入固定主机名')).toBeVisible();
  await expect(dialog).toBeVisible();

  await dialog.getByPlaceholder('例如 hk-vps-core').fill('hk-vps-e2e');
  await expect(dialog.getByText('开通时将固定提交主机名：hk-vps-e2e。')).toBeVisible();
  await dialog.getByRole('button', { name: '保存规则' }).click();

  await expect(page.getByText('商品开通主机名规则已更新')).toBeVisible();
  await expect(dialog).toBeHidden();

  expect(errors).toEqual([]);
});
