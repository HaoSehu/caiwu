import type { Page } from '@playwright/test';

import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 财务详情页用例：错误态可重试（T14）、关键设计令牌可解析（T04）。
// 接口全部 mock，不依赖本地后端。

const E2E_ORDER = {
  id: 1,
  order_no: 'E2E-ORDER-0001',
  type: 'new',
  type_label: '新购',
  status: 1,
  amount: '128.00',
  paid_amount: '128.00',
  product_name: '云服务器 S1',
  product_full_path: '云服务器 / S1',
  billing_cycle: 'monthly',
  created_at: '2026-01-01 10:00:00',
};

/**
 * 拦截订单详情接口。
 *
 * 注意：详情组件在首屏存在时序性的二次挂载（布局 tab 初始化的 alive 切换），
 * 因此不能靠「第 1 次失败、第 2 次成功」来构造错误态——必须让请求持续失败，
 * 再由用例显式翻转开关，才能稳定观察到错误态与重试行为。
 */
async function mockOrderDetail(page: Page, shouldFail: () => boolean) {
  await page.route('**/api/v2/client/orders/*', async (route) => {
    if (shouldFail()) {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ code: 500, message: '服务器开小差了', data: null }),
      });
      return;
    }
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(envelope(E2E_ORDER)) });
  });
}

test('订单详情：接口失败时展示可重试的错误态，点「重新加载」后恢复', async ({ page }) => {
  await mockAuthenticatedSession(page);

  let failing = true;
  await mockOrderDetail(page, () => failing);

  await page.goto('/client/orders/1', { waitUntil: 'domcontentloaded' });

  // 失败时不再静默成空态：应出现 DataState 的错误态与「重新加载」按钮
  const retry = page.getByRole('button', { name: '重新加载' });
  await expect(retry).toBeVisible();
  await expect(page.getByText('未找到该订单')).toBeHidden();
  await expect(page.getByText('E2E-ORDER-0001')).toBeHidden();

  // 翻转后重试，应能正常渲染详情
  failing = false;
  await retry.click();
  await expect(page.getByText('E2E-ORDER-0001')).toBeVisible();
  await expect(retry).toBeHidden();
});

test('订单详情：关键设计令牌在运行时可解析（未定义令牌会让引用它的整条声明失效）', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockOrderDetail(page, () => false);

  await page.goto('/client/orders/1', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('E2E-ORDER-0001')).toBeVisible();

  const tokens = await page.evaluate(() => {
    const style = getComputedStyle(document.documentElement);
    return [
      '--td-component-stroke',
      '--td-border-level-1-color',
      '--td-border-level-2-color',
      '--td-bg-color-page',
      '--td-text-color-primary',
      '--td-brand-color',
    ].map((name) => ({ name, value: style.getPropertyValue(name).trim() }));
  });

  for (const token of tokens) {
    expect(token.value, `${token.name} 未定义`).not.toBe('');
  }
});
