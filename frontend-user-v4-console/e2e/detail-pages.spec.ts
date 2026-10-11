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

test('订单详情：配置单缺系统盘参数时补默认系统盘行（Linux 30G / Windows 40G）', async ({ page }) => {
  await mockAuthenticatedSession(page);

  // 完整配置单（含 os）但无 system_disk_size：商品未配系统盘项时的真实快照形态
  const orderWithPricing = {
    ...E2E_ORDER,
    config_pricing_snapshot: {
      base_amount: '128.00',
      config_amount: '0.00',
      total_amount: '128.00',
      items: [
        { field: 'area', label: '区域', value: 'hk', value_label: '香港', amount: '0.00' },
        { field: 'os', label: '操作系统', value: 'centos7', value_label: 'CentOS 7', amount: '0.00' },
        { field: 'cpu', label: 'CPU', value: '2', value_label: '2核', amount: '0.00' },
        { field: 'memory', label: '内存', value: '2048', value_label: '2G', amount: '0.00' },
        { field: 'bw', label: '带宽', value: '10', value_label: '10Mbps', amount: '0.00' },
        { field: 'data_disk_size', label: '数据盘', value: '50', value_label: '50G', amount: '0.00' },
      ],
    },
  };
  const payload = JSON.stringify(envelope(orderWithPricing));
  await page.route('**/api/v2/client/orders/*', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: payload });
  });

  await page.goto('/client/orders/1', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('E2E-ORDER-0001')).toBeVisible();

  // 计价明细在「产品配置」tab 的「配置定价」块
  await page.getByText('产品配置', { exact: true }).click();
  await expect(page.getByText('配置定价')).toBeVisible();
  await expect(page.getByText('系统盘', { exact: true })).toBeVisible();
  await expect(page.getByText('Linux 30G / Windows 40G')).toBeVisible();
});

test('订单详情：快照已携带系统盘时不重复追加默认行', async ({ page }) => {
  await mockAuthenticatedSession(page);

  const orderWithSystemDisk = {
    ...E2E_ORDER,
    config_pricing_snapshot: {
      base_amount: '128.00',
      config_amount: '0.00',
      total_amount: '128.00',
      items: [
        { field: 'area', label: '区域', value: 'hk', value_label: '香港', amount: '0.00' },
        { field: 'os', label: '操作系统', value: 'centos7', value_label: 'CentOS 7', amount: '0.00' },
        { field: 'system_disk_size', label: '系统盘', value: '40', value_label: '40G', amount: '0.00' },
        { field: 'memory', label: '内存', value: '2048', value_label: '2G', amount: '0.00' },
      ],
    },
  };
  const payload = JSON.stringify(envelope(orderWithSystemDisk));
  await page.route('**/api/v2/client/orders/*', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: payload });
  });

  await page.goto('/client/orders/1', { waitUntil: 'domcontentloaded' });
  await page.getByText('产品配置', { exact: true }).click();

  // 真实行原样展示（带金额段），且不出现默认值文本、不出现重复「系统盘」行
  await expect(page.getByText('40G / 金额 ¥0.00')).toBeVisible();
  await expect(page.getByText('Linux 30G / Windows 40G')).toBeHidden();
  await expect(page.getByText('系统盘', { exact: true })).toHaveCount(1);
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
